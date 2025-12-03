<?php
/* * เนื้อหาจาก nav_sale.php (Updated Logic)
 * ข้อกำหนด:
 * 1. กรอง SO ที่ Amount = 0 ออกทั้ง NAV และ ERP
 * 2. NAV: ใช้ตัวแปร $T_SL ที่รับค่ามาจาก index.php
 * 3. ใช้ pattern %pfx%
 * 4. ERP: ป้องกันการนับซ้ำจาก Sales Team หลายคน (เลือกคนแรกสุด)
 * * FIX (ล่าสุด): เพิ่มการกรอง [Document Type] = 1 (Order) ใน NAV
 * เพราะตาราง Sales Header เก็บทั้ง Quote(0), Order(1), Invoice(2) ฯลฯ
 * 
 * [New] Modern UI & Summary Dashboard (Universal CSS)
 */

$pfx = $_GET['pfx'] ?? '';
if (!preg_match('/^\d{4}$/', $pfx)) {
    $pfx = date('y') . date('m');
}

// 1. กำหนด Pattern การค้นหา
// NAV: ค้นหาแบบระบุ Prefix ชัดเจน (ITG-SOxxxx)
$navPattern = "ITG-SO{$pfx}%"; 
// ERP: ค้นหาใน nav_ref แบบกว้าง (%xxxx%) ตามที่ user request
$erpPattern = "%{$pfx}%";

// Helper: esc
if (!function_exists('esc')) {
    function esc($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

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
$st->execute([':pfx' => $navPattern]);
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
$se->execute([':pfx' => $erpPattern]);
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

$totalDiff = $tot['nav'] - $tot['t'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">เปรียบเทียบตาม SalesV2</h3>
        <p class="text-muted mb-0">กรองเฉพาะเอกสารที่มี Amount > 0</p>
    </div>
</div>

<div class="dashboard-container">
    <div class="stat-card">
        <div class="stat-icon bg-blue-light"><i class="fas fa-file-invoice"></i></div>
        <div class="stat-info">
            <h5>Total NAV</h5>
            <h3><?= number_format($tot['nav']) ?></h3>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-green-light"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <h5>Total ERP</h5>
            <h3 class="text-success"><?= number_format($tot['t']) ?></h3>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-red-light"><i class="fas fa-balance-scale"></i></div>
        <div class="stat-info">
            <h5>Difference</h5>
            <h3 class="<?= $totalDiff != 0 ? 'text-danger' : 'text-success' ?>"><?= number_format($totalDiff) ?></h3>
        </div>
    </div>
</div>

<form class="search-box row g-3" method="get">
  <input type="hidden" name="page" value="salesv2">
  
  <div class="col-md-3">
    <label class="form-label">Month / Prefix</label>
    <div class="input-group">
        <span class="input-group-text bg-white"><i class="fas fa-calendar"></i></span>
        <input class="form-control" name="pfx" value="<?= esc($pfx) ?>" placeholder="เช่น 2510">
    </div>
  </div>
  
  <div class="col-md-2 d-flex align-items-end">
    <button class="btn btn-primary w-100">
        <i class="fas fa-search me-1"></i> Filter
    </button>
  </div>
</form>

<div class="content-wrapper p-0 overflow-hidden">
    <div class="table-responsive border-0 shadow-none">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th class="text-center" style="width:10%">CODE</th>
              <th style="width:30%">SALES NAME</th>
              <th class="text-end" style="width:10%">NAV</th>
              <th class="text-end" style="width:10%">ERP DRAFT</th>
              <th class="text-end" style="width:10%">ERP SUBMIT</th>
              <th class="text-end" style="width:10%">TOTAL ERP</th>
              <th class="text-end" style="width:10%">DIFF</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r) : ?>
              <tr>
                <td class="text-center fw-bold text-muted small"><?= esc($r['code']) ?></td>
                <td class="fw-bold text-dark"><?= esc($r['name']) ?></td>
                <td class="text-end font-monospace text-primary"><?= number_format($r['nav']) ?></td>
                <td class="text-end font-monospace text-muted"><?= number_format($r['d']) ?></td>
                <td class="text-end font-monospace text-success"><?= number_format($r['s']) ?></td>
                <td class="text-end font-monospace fw-bold"><?= number_format($r['t']) ?></td>
                <td class="text-end font-monospace fw-bold <?= $r['diff'] != 0 ? 'text-danger' : 'text-success' ?>">
                    <?= number_format($r['diff']) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="bg-light">
            <tr>
              <td colspan="2" class="text-end fw-bold">GRAND TOTAL</td>
              <td class="text-end fw-bold text-primary"><?= number_format($tot['nav']) ?></td>
              <td class="text-end text-muted">-</td>
              <td class="text-end text-muted">-</td>
              <td class="text-end fw-bold text-dark"><?= number_format($tot['t']) ?></td>
              <td class="text-end fw-bold <?= $totalDiff != 0 ? 'text-danger' : 'text-success' ?>">
                <?= number_format($totalDiff) ?>
              </td>
            </tr>
          </tfoot>
        </table>
    </div>
</div>