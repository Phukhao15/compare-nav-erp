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

<!-- Custom CSS for Universal Compatibility -->
<style>
    .dashboard-container {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 20px;
    }
    .stat-card {
        flex: 1;
        min-width: 200px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
    }
    .stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        margin-right: 15px;
    }
    .stat-info h5 { margin: 0 0 5px 0; font-size: 0.85rem; color: #777; text-transform: uppercase; font-weight: bold; }
    .stat-info h3 { margin: 0; font-size: 1.5rem; font-weight: bold; color: #333; }
    
    .bg-blue-light { background-color: #e3f2fd; color: #1976d2; }
    .bg-green-light { background-color: #e8f5e9; color: #2e7d32; }
    .bg-red-light { background-color: #ffebee; color: #c62828; }
    .bg-orange-light { background-color: #fff3e0; color: #ef6c00; }
    
    /* Ensure form is visible */
    .search-box {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #ddd;
        margin-bottom: 20px;
    }
</style>

<div class="mb-4">
    <h3 style="margin-bottom: 20px;">
        <i class="fas fa-filter text-primary me-2"></i> เปรียบเทียบตาม SalesV2 (เฉพาะ Amount มีค่า)
    </h3>
    
    <!-- Summary Dashboard -->
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
                <h3 style="color: #2e7d32;"><?= number_format($tot['t']) ?></h3>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-red-light"><i class="fas fa-balance-scale"></i></div>
            <div class="stat-info">
                <h5>Difference</h5>
                <h3 style="color: <?= $totalDiff != 0 ? '#c62828' : '#2e7d32' ?>;"><?= number_format($totalDiff) ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Search Form -->
<form class="search-box row" method="get" style="margin-left: 0; margin-right: 0;">
  <input type="hidden" name="page" value="salesv2">
  
  <div class="col-md-3 col-sm-6" style="margin-bottom: 10px;">
    <label style="font-weight: bold;">MONTH / PREFIX</label>
    <input class="form-control" name="pfx" value="<?= esc($pfx) ?>" placeholder="เช่น 2510">
  </div>
  
  <div class="col-md-2 col-sm-12" style="margin-bottom: 10px;">
    <label>&nbsp;</label>
    <button class="btn btn-primary w-100 btn-block">
        <i class="fas fa-search"></i> Filter
    </button>
  </div>
</form>

<div class="table-responsive">
    <table class="table table-bordered table-hover table-sm align-middle">
      <thead class="table-primary text-center" style="background-color: #e3f2fd;">
        <tr>
          <th style="width:10%">CODE</th>
          <th style="width:30%">SALES NAME</th>
          <th style="width:10%">NAV</th>
          <th style="width:10%">ERP-DRAFT</th>
          <th style="width:10%">ERP-SUBMIT</th>
          <th style="width:10%">TOTAL ERP</th>
          <th style="width:10%">DIFF</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r) : ?>
          <tr>
            <td class="text-center fw-bold text-muted"><?= esc($r['code']) ?></td>
            <td><?= esc($r['name']) ?></td>
            <td class="text-end fw-bold text-primary"><?= number_format($r['nav']) ?></td>
            <td class="text-end text-muted"><?= number_format($r['d']) ?></td>
            <td class="text-end text-success"><?= number_format($r['s']) ?></td>
            <td class="text-end fw-bold text-dark"><?= number_format($r['t']) ?></td>
            <td class="text-end fw-bold" style="color: <?= $r['diff'] != 0 ? '#c62828' : '#2e7d32' ?>;">
                <?= number_format($r['diff']) ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background-color: #f8f9fa; font-weight: bold;">
          <td colspan="2" class="text-end">TOTAL</td>
          <td class="text-end text-primary"><?= number_format($tot['nav']) ?></td>
          <td class="text-end text-muted">-</td>
          <td class="text-end text-muted">-</td>
          <td class="text-end text-dark"><?= number_format($tot['t']) ?></td>
          <td class="text-end" style="color: <?= $totalDiff != 0 ? '#c62828' : '#2e7d32' ?>;">
            <?= number_format($totalDiff) ?>
          </td>
        </tr>
      </tfoot>
    </table>
</div>

<div class="card-footer bg-white py-3" style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
    <div class="text-muted small">
        <i class="fas fa-info-circle me-1"></i> <strong>Note:</strong> กรองเฉพาะ Sales Order ที่มี Amount > 0 เท่านั้น
    </div>
</div>