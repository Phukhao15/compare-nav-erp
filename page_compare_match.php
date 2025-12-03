<?php
/* page_compare_match.php (Modern UI Version) */

$pfx = $_GET['pfx'] ?? '';
$filterStatus = $_GET['filter_status'] ?? 'all'; 

if (!preg_match('/^\d{4}$/', $pfx)) {
    $pfx = date('y') . date('m'); 
}

$navPattern = "ITG-SO{$pfx}%"; 
$erpPattern = "%SO{$pfx}%";    

if (!function_exists('normalizeKey')) {
    function normalizeKey($str) {
        return strtoupper(str_replace('ITG-', '', trim((string)$str)));
    }
}
if (!function_exists('esc')) {
    function esc($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}

if (!isset($T_SL)) { 
    echo "<div class='alert alert-danger'>Error: ไม่พบตัวแปร \$T_SL กรุณาตรวจสอบ index.php</div>";
    return;
}

// 1. Fetch NAV
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

// 2. Fetch ERP
$sqlErp = "
SELECT so.name AS erp_no, so.nav_ref, so.docstatus, st.sales_person AS sp_code
FROM `tabSales Order` AS so
LEFT JOIN `tabSales Team` AS st ON st.parent = so.name
WHERE so.nav_ref LIKE :pfx AND so.docstatus != 2
";
$se = $pdoErp->prepare($sqlErp);
$se->execute([':pfx' => $erpPattern]);
$erpData = $se->fetchAll(PDO::FETCH_ASSOC);

$erpList = [];
foreach ($erpData as $r) {
    $key = normalizeKey((string)$r['nav_ref']);
    if (!isset($erpList[$key])) $erpList[$key] = [];
    $erpList[$key][] = [
        'erp_no' => (string)$r['erp_no'],
        'full_ref' => (string)$r['nav_ref'],
        'sp' => strtoupper(trim($r['sp_code'] ?? '')),
        'status' => (int)$r['docstatus']
    ];
}

// 3. Stats
$totalNav = count($navList);
$statMissing = 0; $statDuplicate = 0; $statComplete = 0; $statMismatch = 0;

foreach ($navList as $key => $navItem) {
    $erpItems = $erpList[$key] ?? [];
    $hasErp = !empty($erpItems);
    if (!$hasErp) {
        $statMissing++;
    } else {
        if (count($erpItems) > 1) $statDuplicate++;
        $navSp = $navItem['sp'];
        $erpSp = $erpItems[0]['sp'];
        if ($navSp !== $erpSp) $statMismatch++;
        else $statComplete++;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1">ตรวจสอบยอดชนใบ (Match Check)</h3>
        <p class="text-muted mb-0">เปรียบเทียบข้อมูล NAV และ ERP รายเอกสาร ประจำงวด: <span class="badge bg-primary"><?= esc($pfx) ?></span></p>
    </div>
</div>

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
            <h3 class="text-success"><?= number_format($statComplete) ?></h3>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-red-light"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="stat-info">
            <h5>Missing</h5>
            <h3 class="text-danger"><?= number_format($statMissing) ?></h3>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon bg-orange-light"><i class="fas fa-copy"></i></div>
        <div class="stat-info">
            <h5>Duplicate</h5>
            <h3 class="text-warning"><?= number_format($statDuplicate) ?></h3>
        </div>
    </div>
</div>

<form class="search-box row g-3" method="get">
    <input type="hidden" name="page" value="match_list"> 

    <div class="col-md-3">
        <label class="form-label">Month / Prefix</label>
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="fas fa-calendar"></i></span>
            <input type="text" class="form-control" name="pfx" value="<?= esc($pfx) ?>" placeholder="2511">
        </div>
    </div>

    <div class="col-md-3">
        <label class="form-label">Filter Status</label>
        <select class="form-select" name="filter_status">
            <option value="all" <?= $filterStatus == 'all' ? 'selected' : '' ?>>แสดงทั้งหมด</option>
            <option value="missing" <?= $filterStatus == 'missing' ? 'selected' : '' ?>>เฉพาะที่หายไป (Missing)</option>
            <option value="mismatch" <?= $filterStatus == 'mismatch' ? 'selected' : '' ?>>เฉพาะ Sales ไม่ตรง</option>
            <option value="duplicate" <?= $filterStatus == 'duplicate' ? 'selected' : '' ?>>เฉพาะใบซ้ำ (Duplicate)</option>
        </select>
    </div>

    <div class="col-md-2 d-flex align-items-end">
        <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-search me-1"></i> ค้นหา
        </button>
    </div>
</form>

<div class="content-wrapper p-0 overflow-hidden">
    <div class="table-responsive border-0 shadow-none">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th class="text-center" style="width: 50px;">#</th> 
                    <th style="width: 20%;">NAV NO</th>
                    <th style="width: 25%;">ERP REF / NO</th>
                    <th class="text-center" style="width: 10%;">STATUS</th> 
                    <th style="width: 20%;">SALES CHECK</th>
                    <th class="text-center" style="width: 15%;">REMARK</th>
                </tr>
            </thead>
            <tbody>
                <?php $count = 0; if (empty($navList)): ?>
                    <tr><td colspan="6" class="text-center py-5 text-muted">ไม่พบข้อมูล NAV ที่มียอดเงินในเดือนนี้</td></tr>
                <?php else: ?>
                    <?php foreach ($navList as $key => $navItem): 
                        $erpItems = $erpList[$key] ?? [];
                        $hasErp = !empty($erpItems);
                        $navSp = $navItem['sp'];
                        
                        // Checks
                        $isMissing = !$hasErp;
                        $isDuplicate = count($erpItems) > 1;
                        $firstErp = $hasErp ? $erpItems[0] : null;
                        $erpSp = $firstErp ? $firstErp['sp'] : '';
                        $isSpMismatch = $hasErp && ($navSp !== $erpSp);

                        // Filters
                        if ($filterStatus == 'missing' && !$isMissing) continue;
                        if ($filterStatus == 'mismatch' && !$isSpMismatch) continue;
                        if ($filterStatus == 'duplicate' && !$isDuplicate) continue;

                        $count++;
                        
                        // Status Badge Logic
                        $statusBadge = '';
                        if ($isMissing) $statusBadge = '<span class="badge-custom badge-danger"><i class="fas fa-times me-1"></i> Missing</span>';
                        elseif ($isDuplicate) $statusBadge = '<span class="badge-custom badge-warning"><i class="fas fa-copy me-1"></i> Duplicate</span>';
                        elseif ($isSpMismatch) $statusBadge = '<span class="badge-custom badge-warning"><i class="fas fa-user-times me-1"></i> Sales Diff</span>';
                        else $statusBadge = '<span class="badge-custom badge-success"><i class="fas fa-check me-1"></i> Match</span>';
                    ?>
                    <tr class="<?= $isMissing ? 'bg-red-light' : '' ?>">
                        <td class="text-center text-muted small"><?= $count ?></td>
                        <td class="fw-bold text-primary font-monospace"><?= esc($navItem['full_no']) ?></td>
                        
                        <td>
                            <?php if ($hasErp): ?>
                                <?php foreach ($erpItems as $idx => $eItem): ?>
                                    <div class="<?= $idx > 0 ? 'border-top mt-2 pt-2' : '' ?>">
                                        <div class="d-flex flex-column">
                                            <span class="fw-bold text-dark font-monospace"><?= esc($eItem['erp_no']) ?></span>
                                            <small class="text-muted"><?= esc($eItem['full_ref']) ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="text-muted fst-italic">Not found</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-center">
                            <?php if ($hasErp): ?>
                                <?php foreach ($erpItems as $idx => $eItem): ?>
                                    <div class="<?= $idx > 0 ? 'mt-3' : '' ?>">
                                        <?php if ($eItem['status'] === 0): ?>
                                            <span class="badge-custom badge-gray">Draft</span>
                                        <?php elseif ($eItem['status'] === 1): ?>
                                            <span class="badge-custom badge-info">Submitted</span>
                                        <?php else: ?>
                                            <span class="badge-custom badge-gray"><?= $eItem['status'] ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?> - <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($hasErp): ?>
                                <?php foreach ($erpItems as $idx => $eItem): 
                                    $spMatch = ($navSp === $eItem['sp']);
                                ?>
                                    <div class="<?= $idx > 0 ? 'border-top mt-2 pt-2' : '' ?>">
                                        <?php if (!$spMatch): ?>
                                            <div class="text-danger fw-bold"><i class="fas fa-times me-1"></i> <?= esc($eItem['sp']) ?></div>
                                            <small class="text-muted">(NAV: <?= esc($navSp) ?>)</small>
                                        <?php else: ?>
                                            <div class="text-success"><i class="fas fa-check me-1"></i> <?= esc($eItem['sp']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?> - <?php endif; ?>
                        </td>

                        <td class="text-center"><?= $statusBadge ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="bg-light p-3 border-top d-flex justify-content-between align-items-center">
        <span class="text-muted small">Showing <?= number_format($count) ?> entries</span>
        <span class="text-muted small"><i class="fas fa-info-circle me-1"></i> ตัด Prefix 'ITG-' ออกอัตโนมัติเพื่อการจับคู่</span>
    </div>
</div>