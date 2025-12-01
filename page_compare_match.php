<?php
/*
 * page_compare_match.php
 * เปรียบเทียบข้อมูลรายใบ (Collision Check)
 * - กรอง NAV Amount != 0
 * - จับคู่แบบ Loose Prefix (ITG-SO vs SO)
 * - แจ้งเตือน Sales Mismatch
 * - [New] แสดงลำดับ (#)
 * - [New] แสดงสถานะเอกสาร (Draft/Submitted)
 * - [New] ตรวจสอบใบซ้ำ (Duplicate Check)
 * - [New] แสดงเลข ERP (ERP No)
 * - [New] Summary Dashboard (Universal UI)
 */

$pfx = $_GET['pfx'] ?? '';
$filterStatus = $_GET['filter_status'] ?? 'all'; // รับค่า Filter

if (!preg_match('/^\d{4}$/', $pfx)) {
    $pfx = date('y') . date('m'); 
}

$navPattern = "ITG-SO{$pfx}%"; 
$erpPattern = "%SO{$pfx}%";    

// Helper: ตัด ITG- ออก
if (!function_exists('normalizeKey')) {
    function normalizeKey($str) {
        return strtoupper(str_replace('ITG-', '', trim((string)$str)));
    }
}

// Helper: esc (ป้องกัน Error หากไม่มีฟังก์ชันนี้)
if (!function_exists('esc')) {
    function esc($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

// ---------------------------------------------------------
// 1. ดึงข้อมูลจาก NAV (Main Base) + กรอง Amount != 0
// ---------------------------------------------------------
if (!isset($T_SL)) { 
    echo "<div class='alert alert-danger'>Error: ไม่พบตัวแปร \$T_SL กรุณาตรวจสอบ index.php</div>";
    return;
}

// ตัดการดึง SUM(Amount) ออก เหลือแค่เช็คว่ามี Amount != 0
$sqlNav = "
SELECT
    sh.[No_] AS doc_no,
    COALESCE(NULLIF(LTRIM(RTRIM(sh.[Salesperson Code])), ''), 'UNKNOWN') AS sp_code
FROM $T_SO AS sh
JOIN (
    SELECT [Document No_]
    FROM $T_SL
    GROUP BY [Document No_]
    HAVING SUM(COALESCE([Amount], 0)) != 0
) AS sl ON sl.[Document No_] = sh.[No_]

WHERE sh.[No_] LIKE :pfx
ORDER BY sh.[No_] ASC
";

$st = $pdoNav->prepare($sqlNav);
$st->execute([':pfx' => $navPattern]);
$navData = $st->fetchAll(PDO::FETCH_ASSOC);

$navList = [];
foreach ($navData as $r) {
    $originalNo = (string)$r['doc_no'];
    $key = normalizeKey($originalNo);
    $navList[$key] = [
        'full_no' => $originalNo,
        'sp' => strtoupper(trim($r['sp_code']))
    ];
}

// ---------------------------------------------------------
// 2. ดึงข้อมูลจาก ERP (Lookup Base) + [NEW] เอา docstatus + ERP No
// ---------------------------------------------------------
$sqlErp = "
SELECT
    so.name AS erp_no,
    so.nav_ref,
    so.docstatus,
    st.sales_person AS sp_code
FROM `tabSales Order` AS so
LEFT JOIN `tabSales Team` AS st ON st.parent = so.name
WHERE so.nav_ref LIKE :pfx
  AND so.docstatus != 2
";

$se = $pdoErp->prepare($sqlErp);
$se->execute([':pfx' => $erpPattern]);
$erpData = $se->fetchAll(PDO::FETCH_ASSOC);

$erpList = [];
foreach ($erpData as $r) {
    $originalRef = (string)$r['nav_ref'];
    $key = normalizeKey($originalRef);
    
    // เก็บเป็น Array ของรายการ เพื่อรองรับใบซ้ำ
    if (!isset($erpList[$key])) {
        $erpList[$key] = [];
    }
    
    $erpList[$key][] = [
        'erp_no' => (string)$r['erp_no'],
        'full_ref' => $originalRef,
        'sp' => strtoupper(trim($r['sp_code'] ?? '')),
        'status' => (int)$r['docstatus'] // 0=Draft, 1=Submitted
    ];
}

// ---------------------------------------------------------
// 3. คำนวณ Summary Stats
// ---------------------------------------------------------
$totalNav = count($navList);
$statMissing = 0;
$statDuplicate = 0;
$statComplete = 0;
$statMismatch = 0;

foreach ($navList as $key => $navItem) {
    $erpItems = $erpList[$key] ?? [];
    $hasErp = !empty($erpItems);
    $erpCount = count($erpItems);
    
    if (!$hasErp) {
        $statMissing++;
    } else {
        if ($erpCount > 1) {
            $statDuplicate++;
        }
        
        // Check Sales Mismatch
        $navSp = $navItem['sp'];
        $firstErp = $erpItems[0];
        $erpSp = $firstErp['sp'];
        
        if ($navSp !== $erpSp) {
            $statMismatch++;
        } else {
            $statComplete++;
        }
    }
}

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
        <i class="fas fa-exchange-alt text-primary me-2"></i> ตรวจสอบข้อมูล NAV vs ERP (<?= esc($pfx) ?>)
    </h3>
    
    <!-- Summary Dashboard (Flexbox with fallback) -->
    <div class="dashboard-container">
        <div class="stat-card">
            <div class="stat-icon bg-blue-light"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-info">
                <h5>Total NAV</h5>
                <h3><?= number_format($totalNav) ?></h3>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-green-light"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <h5>Complete</h5>
                <h3 style="color: #2e7d32;"><?= number_format($statComplete) ?></h3>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-red-light"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-info">
                <h5>Missing</h5>
                <h3 style="color: #c62828;"><?= number_format($statMissing) ?></h3>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-orange-light"><i class="fas fa-copy"></i></div>
            <div class="stat-info">
                <h5>Duplicate</h5>
                <h3 style="color: #ef6c00;"><?= number_format($statDuplicate) ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Search Form -->
<form class="search-box row" method="get" style="margin-left: 0; margin-right: 0;">
    <input type="hidden" name="page" value="match_list"> 

    <div class="col-md-3 col-sm-6" style="margin-bottom: 10px;">
        <label style="font-weight: bold;">MONTH / PREFIX</label>
        <input type="text" class="form-control" name="pfx" value="<?= esc($pfx) ?>" placeholder="2511">
    </div>

    <div class="col-md-3 col-sm-6" style="margin-bottom: 10px;">
        <label style="font-weight: bold;">FILTER STATUS</label>
        <select class="form-select form-control" name="filter_status">
            <option value="all" <?= $filterStatus == 'all' ? 'selected' : '' ?>>แสดงทั้งหมด</option>
            <option value="missing" <?= $filterStatus == 'missing' ? 'selected' : '' ?>>เฉพาะที่หายไป (Missing)</option>
            <option value="mismatch" <?= $filterStatus == 'mismatch' ? 'selected' : '' ?>>เฉพาะ Sales ไม่ตรง</option>
            <option value="duplicate" <?= $filterStatus == 'duplicate' ? 'selected' : '' ?>>เฉพาะใบซ้ำ (Duplicate)</option>
        </select>
    </div>

    <div class="col-md-2 col-sm-12" style="margin-bottom: 10px;">
        <label>&nbsp;</label>
        <button type="submit" class="btn btn-primary w-100 btn-block">
            <i class="fas fa-search"></i> ค้นหา
        </button>
    </div>
</form>

<!-- Table -->
<div class="table-responsive">
    <table class="table table-bordered table-hover table-sm align-middle">
        <thead class="table-primary text-center" style="background-color: #e3f2fd;">
            <tr>
                <th style="width: 5%;">#</th> 
                <th style="width: 15%;">NAV NO</th>
                <th style="width: 25%;">ERP REF / NO</th>
                <th style="width: 10%;">STATUS</th> 
                <th style="width: 15%;">SALES CHECK</th>
                <th style="width: 15%;">REMARK</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $count = 0; // ตัวนับลำดับ
            if (empty($navList)): 
            ?>
                <tr>
                    <td colspan="6" class="text-center text-muted p-5">
                        <i class="fas fa-info-circle fa-2x mb-3 text-muted opacity-50"></i><br>
                        ไม่พบข้อมูล NAV ที่มียอดเงินในเดือนนี้
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($navList as $key => $navItem): ?>
                    <?php
                        $erpItems = $erpList[$key] ?? [];
                        $hasErp  = !empty($erpItems);
                        $erpCount = count($erpItems);
                        
                        $navSp = $navItem['sp'];

                        // Logic ตรวจสอบ
                        $isMissing = !$hasErp;
                        $isDuplicate = $erpCount > 1;
                        
                        // ตรวจสอบ Sales (เทียบกับใบแรกที่เจอ)
                        $firstErp = $hasErp ? $erpItems[0] : null;
                        $erpSp = $firstErp ? $firstErp['sp'] : '';
                        $docStatus = $firstErp ? $firstErp['status'] : -1;

                        $isSpMismatch = $hasErp && ($navSp !== $erpSp);

                        // Filtering
                        if ($filterStatus == 'missing' && !$isMissing) continue;
                        if ($filterStatus == 'mismatch' && !$isSpMismatch) continue;
                        if ($filterStatus == 'duplicate' && !$isDuplicate) continue;

                        $count++; // เพิ่มลำดับ
                        
                        // Style Row
                        $rowClass = '';
                        $remark = [];

                        if ($isMissing) {
                            $rowClass = 'warning'; // Bootstrap 3/4 uses 'warning' class on tr
                            $remark[] = '<span class="badge badge-danger" style="background-color: #d32f2f;">Missing</span>';
                        }
                        
                        if ($isDuplicate) {
                            $rowClass = 'danger'; // Bootstrap 3/4 uses 'danger' class on tr
                            $remark[] = '<span class="badge badge-warning" style="background-color: #f57c00;">Duplicate (' . $erpCount . ')</span>';
                        }

                        if ($isSpMismatch) {
                            $remark[] = '<span class="badge badge-warning" style="background-color: #fbc02d; color: #333;">Sales Diff</span>';
                        }
                        
                        if (empty($remark) && $hasErp) {
                            $remark[] = '<span class="badge badge-success" style="background-color: #388e3c;">OK</span>';
                        }
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td class="text-center fw-bold text-muted"><?= $count ?></td>

                        <td class="fw-bold text-primary">
                            <?= esc($navItem['full_no']) ?>
                        </td>
                        
                        <td>
                            <?php if ($hasErp): ?>
                                <?php foreach ($erpItems as $idx => $eItem): ?>
                                    <div class="<?= $idx > 0 ? 'border-top mt-2 pt-2' : '' ?>" style="<?= $idx > 0 ? 'border-top: 1px solid #eee; margin-top: 5px; padding-top: 5px;' : '' ?>">
                                        <div style="display: flex; align-items: center;">
                                            <div style="flex-grow: 1;">
                                                <div style="font-weight: bold; color: #333;"><?= esc($eItem['erp_no']) ?></div>
                                                <div style="font-size: 0.85em; color: #777;"><?= esc($eItem['full_ref']) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted fst-italic" style="color: #999;">Not found in ERP</span>
                            <?php endif; ?>
                        </td>
                        
                        <td class="text-center">
                            <?php if ($hasErp): ?>
                                <?php foreach ($erpItems as $idx => $eItem): ?>
                                    <div class="<?= $idx > 0 ? 'border-top mt-2 pt-2' : '' ?>" style="<?= $idx > 0 ? 'border-top: 1px solid #eee; margin-top: 5px; padding-top: 5px;' : '' ?>">
                                        <?php if ($eItem['status'] === 0): ?>
                                            <span class="badge" style="background-color: #757575;">Draft</span>
                                        <?php elseif ($eItem['status'] === 1): ?>
                                            <span class="badge" style="background-color: #2e7d32;">Submitted</span>
                                        <?php else: ?>
                                            <span class="badge" style="background-color: #333;"><?= $eItem['status'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-center">
                            <?php if ($hasErp): ?>
                                <?php foreach ($erpItems as $idx => $eItem): ?>
                                    <div class="<?= $idx > 0 ? 'border-top mt-2 pt-2' : '' ?>" style="<?= $idx > 0 ? 'border-top: 1px solid #eee; margin-top: 5px; padding-top: 5px;' : '' ?>">
                                        <?php 
                                            $thisSp = $eItem['sp'];
                                            $spMatch = ($navSp === $thisSp);
                                        ?>
                                        <?php if (!$spMatch): ?>
                                            <div style="color: #c62828; font-weight: bold; font-size: 0.9em;">
                                                <i class="fas fa-times-circle"></i> <?= esc($thisSp) ?>
                                            </div>
                                            <div style="color: #777; font-size: 0.8em;">(NAV: <?= esc($navSp) ?>)</div>
                                        <?php else: ?>
                                            <div style="color: #2e7d32; font-size: 0.9em;">
                                                <i class="fas fa-check-circle"></i> <?= esc($thisSp) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        
                        <td class="text-center">
                            <?= implode(' ', $remark) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card-footer bg-white py-3" style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">
            แสดงผล <span class="fw-bold text-dark"><?= number_format($count) ?></span> รายการ
        </div>
        <div class="text-muted small" style="color: #777;">
            <i class="fas fa-info-circle me-1"></i> Duplicate: หากพบ ERP Ref ซ้ำกันมากกว่า 1 ใบ จะแสดงรายการทั้งหมดในช่องเดียวกัน
        </div>
    </div>
</div>