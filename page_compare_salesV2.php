<?php
/* * เนื้อหาจาก nav_sale.php (Updated Logic)
 * ข้อกำหนด:
 * 1. กรอง SO ที่ Amount = 0 ออกทั้ง NAV และ ERP
 * 2. NAV: ใช้ตัวแปร $T_SL ที่รับค่ามาจาก index.php
 * 3. ใช้ pattern %pfx%
 * 4. ERP: ป้องกันการนับซ้ำจาก Sales Team หลายคน (เลือกคนแรกสุด)
 * * FIX (ล่าสุด): เพิ่มการกรอง [Document Type] = 1 (Order) ใน NAV
 * เพราะตาราง Sales Header เก็บทั้ง Quote(0), Order(1), Invoice(2) ฯลฯ
 */

$pfx = $_GET['pfx'] ?? '';
if (!preg_match('/^\d{4}$/', $pfx)) {
    $pfx = date('y') . date('m');
}

// 1. เปลี่ยน Pattern การค้นหาเป็น %pfx%
$SO_PATTERN = "%{$pfx}%";

/* ---------------------------------------------------------
   NAV SQL QUERY
   - เพิ่ม [Document Type] = 1 เพื่อระบุว่าเป็น Sales Order เท่านั้น
   - เพิ่มการ JOIN กับ $T_SL เพื่อกรอง Amount != 0
   --------------------------------------------------------- */
$sqlNav = "
SELECT
  COALESCE(NULLIF(LTRIM(RTRIM(sh.[Salesperson Code])), ''), 'UNKNOWN') AS sp_code,
  MAX(sp.[Name]) AS sp_name,
  COUNT(DISTINCT sh.[No_]) AS nav_total
FROM $T_SO AS sh 
LEFT JOIN $T_SP AS sp ON sp.[Code] = sh.[Salesperson Code]

/* --- UPDATED: ใช้ตัวแปร $T_SL กรอง Amount != 0 --- */
JOIN (
    SELECT DISTINCT [Document No_]
    FROM $T_SL
    WHERE [Document Type] = 1  -- !! สำคัญ: กรองเฉพาะ Line ที่เป็น Order
    GROUP BY [Document No_]
    HAVING SUM(COALESCE([Amount], 0)) != 0
) AS sl ON sl.[Document No_] = sh.[No_]
/* --- END UPDATE --- */

WHERE sh.[Document Type] = 1   -- !! สำคัญ: กรองเฉพาะ Header ที่เป็น Order
  AND sh.[No_] LIKE :pfx
GROUP BY COALESCE(NULLIF(LTRIM(RTRIM(sh.[Salesperson Code])), ''), 'UNKNOWN')
";

$st = $pdoNav->prepare($sqlNav);
$st->execute([':pfx' => $SO_PATTERN]);
$navRows = $st->fetchAll(PDO::FETCH_ASSOC);

$navMap = [];
foreach ($navRows as $r) {
    $code = strtoupper($r['sp_code']);
    $navMap[$code] = ['name' => $r['sp_name'] ?? '', 'nav' => (int) $r['nav_total']];
}

/* ---------------------------------------------------------
   ERP SQL QUERY
   - กรอง Amount != 0
   - ป้องกันนับซ้ำโดยเลือก Sales Person คนแรก (idx ต่ำสุด)
   --------------------------------------------------------- */
$sqlErp = "
SELECT
  main_st.sales_person AS sp_code,
  COALESCE(sp.sales_person_name, main_st.sales_person) AS sp_name,
  SUM(so.docstatus = 0) AS draft,
  SUM(so.docstatus = 1) AS submitted,
  SUM(so.docstatus IN (0,1)) AS total_erp
FROM `tabSales Order` AS so

/* --- 1. กรอง SO ที่มี Amount != 0 --- */
JOIN (
    SELECT DISTINCT `parent`
    FROM `tabSales Order Item`
    GROUP BY `parent`
    HAVING SUM(COALESCE(`amount`, 0)) != 0
) AS soi ON soi.parent = so.name

/* --- 2. เลือกเฉพาะ Sales Person หลัก (idx=0 หรือต่ำสุด) เพื่อป้องกันนับซ้ำ --- */
JOIN (
    SELECT 
        st.parent,
        st.sales_person,
        /* ใช้ ROW_NUMBER แบ่งกลุ่มตาม SO แล้วเรียงตาม idx */
        ROW_NUMBER() OVER (PARTITION BY st.parent ORDER BY st.idx ASC, st.name ASC) AS rn
    FROM `tabSales Team` AS st
    WHERE st.parenttype = 'Sales Order'
) AS main_st ON main_st.parent = so.name AND main_st.rn = 1
/* --- END: เลือกเฉพาะคนแรก --- */

LEFT JOIN `tabSales Person` AS sp ON sp.name = main_st.sales_person

WHERE so.nav_ref LIKE :pfx
  AND so.docstatus != 2 -- ไม่นับ Cancelled

GROUP BY main_st.sales_person, sp.sales_person_name
";

$se = $pdoErp->prepare($sqlErp);
$se->execute([':pfx' => $SO_PATTERN]);
$erpRows = $se->fetchAll(PDO::FETCH_ASSOC);

$erpMap = [];
foreach ($erpRows as $r) {
    $code = strtoupper($r['sp_code'] ?? '');
    if ($code === '') {
        $code = 'UNKNOWN';
    }
    $erpMap[$code] = [
        'name' => $r['sp_name'] ?? '',
        'draft' => (int) $r['draft'],
        'submitted' => (int) $r['submitted'],
        'total' => (int) $r['total_erp']
    ];
}

/* ---------------------------------------------------------
   MERGE DATA & CALCULATE (Logic เดิม)
   --------------------------------------------------------- */
$minCode = 1;
$maxCode = 35;
$rows = [];
$tot = ['nav' => 0, 't' => 0];

// UNKNOWN
$code = 'UNKNOWN';
$nm = $navMap[$code]['name'] ?? ($erpMap[$code]['name'] ?? '');
$nv = $navMap[$code]['nav'] ?? 0;
$dr = $erpMap[$code]['draft'] ?? 0;
$sb = $erpMap[$code]['submitted'] ?? 0;
$tt = $erpMap[$code]['total'] ?? ($dr + $sb);
$df = $nv - $tt;
if ($nv || $tt) {
    $rows[] = [
        'code' => 'ไม่ระบุ Sales Code',
        'name' => $nm ?: 'ไม่ระบุชื่อ Sales',
        'nav' => $nv, 'd' => $dr, 's' => $sb, 't' => $tt, 'diff' => $df
    ];
    $tot['nav'] += $nv;
    $tot['t'] += $tt;
}

// S001-S035
for ($i = $minCode; $i <= $maxCode; $i++) {
    $code = 'S' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
    $nm = $navMap[$code]['name'] ?? ($erpMap[$code]['name'] ?? '');
    $nv = $navMap[$code]['nav'] ?? 0;
    $dr = $erpMap[$code]['draft'] ?? 0;
    $sb = $erpMap[$code]['submitted'] ?? 0;
    $tt = $erpMap[$code]['total'] ?? ($dr + $sb);
    $df = $nv - $tt;
    if ($nv || $tt) {
        $rows[] = [
            'code' => $code,
            'name' => $nm ?: 'ไม่ระบุชื่อ Sales',
            'nav' => $nv, 'd' => $dr, 's' => $sb, 't' => $tt, 'diff' => $df
        ];
        $tot['nav'] += $nv;
        $tot['t'] += $tt;
    }
}

/* ---------- render (เฉพาะส่วนเนื้อหา) ---------- */
?>

<h1>ข้อมูล Sales Order (Prefix: <?= htmlspecialchars($pfx) ?>)</h1>

<form class="row g-2 mb-3" method="get">
  <input type="hidden" name="page" value="salesv2">
  <div class="col-md-3">
    <label class="form-label">Prefix เดือน (เช่น 2509, 2510)</label>
    <input class="form-control" name="pfx" value="<?= htmlspecialchars($pfx) ?>" placeholder="เช่น 2510">
  </div>
  <div class="col-md-2 align-self-end">
    <button class="btn btn-success w-100">Filter</button>
  </div>
</form>

<table class="table table-bordered table-striped">
  <thead>
    <tr>
      <th style="width:16%">Salesperson Code</th>
      <th style="width:34%">Sales Name</th>
      <th style="width:8%">NAV</th>
      <th style="width:10%">ERP-DRAFT</th>
      <th style="width:10%">ERP-SUBMIT</th>
      <th style="width:10%">Total ERP</th>
      <th style="width:12%">DIFF(NAV-ERP)</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $r) : ?>
      <tr>
        <td><?= htmlspecialchars($r['code']) ?></td>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td class="num"><?= number_format($r['nav']) ?></td>
        <td class="num"><?= number_format($r['d']) ?></td>
        <td class="num"><?= number_format($r['s']) ?></td>
        <td class="num"><?= number_format($r['t']) ?></td>
        <td class="num" style="<?= $r['diff'] != 0 ? 'color:red;font-weight:bold;' : '' ?>">
            <?= number_format($r['diff']) ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <td><b>Total</b></td>
      <td></td>
      <td class="num"><b><?= number_format($tot['nav']) ?></b></td>
      <td class="num muted">-</td>
      <td class="num muted">-</td>
      <td class="num"><b><?= number_format($tot['t']) ?></b></td>
      <td class="num"><b><?= number_format($tot['nav'] - $tot['t']) ?></b></td>
    </tr>
  </tfoot>
</table>