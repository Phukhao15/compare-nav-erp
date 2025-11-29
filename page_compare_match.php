<?php
/*
 * page_compare_match.php
 * เปรียบเทียบข้อมูลรายใบ (Collision Check)
 * - กรอง NAV Amount != 0
 * - จับคู่แบบ Loose Prefix (ITG-SO vs SO)
 * - แจ้งเตือน Sales Mismatch
 * - [New] แสดงลำดับ (#)
 * - [New] แสดงสถานะเอกสาร (Draft/Submitted)
 */

$pfx = $_GET['pfx'] ?? '';
$filterStatus = $_GET['filter_status'] ?? 'all'; // รับค่า Filter

if (!preg_match('/^\d{4}$/', $pfx)) {
    $pfx = date('y') . date('m'); 
}

$navPattern = "ITG-SO{$pfx}%"; 
$erpPattern = "%SO{$pfx}%";    

// Helper: ตัด ITG- ออก
function normalizeKey($str) {
    return strtoupper(str_replace('ITG-', '', trim((string)$str)));
}

// ---------------------------------------------------------
// 1. ดึงข้อมูลจาก NAV (Main Base) + กรอง Amount != 0
// ---------------------------------------------------------
if (!isset($T_SL)) { 
    echo "<div class='alert alert-danger'>Error: ไม่พบตัวแปร \$T_SL กรุณาตรวจสอบ index.php</div>";
    return;
}

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
// 2. ดึงข้อมูลจาก ERP (Lookup Base) + [NEW] เอา docstatus
// ---------------------------------------------------------
$sqlErp = "
SELECT
    so.nav_ref,
    so.docstatus,  -- <--- เพิ่มการดึงสถานะ
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
    
    if (!isset($erpList[$key])) {
        $erpList[$key] = [
            'full_ref' => $originalRef,
            'sp' => strtoupper(trim($r['sp_code'] ?? '')),
            'status' => (int)$r['docstatus'] // <--- เก็บสถานะ (0=Draft, 1=Submitted)
        ];
    }
}

// นับจำนวนทั้งหมดก่อนกรอง
$totalNav = count($navList);
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3>ตรวจสอบข้อมูล NAV vs ERP (<?= esc($pfx) ?>)</h3>
    <span class="badge bg-secondary fs-6">พบข้อมูลต้นทาง (NAV): <?= number_format($totalNav) ?> ใบ</span>
</div>

<form class="row g-2 mb-3 bg-light p-3 rounded border" method="get">
    <input type="hidden" name="page" value="match_list"> 

    <div class="col-md-3">
        <label class="form-label fw-bold">ระบุเดือน (เช่น 2511)</label>
        <div class="input-group">
            <span class="input-group-text">ITG-SO</span>
            <input type="text" class="form-control" name="pfx" value="<?= esc($pfx) ?>" placeholder="2511">
        </div>
    </div>

    <div class="col-md-3">
        <label class="form-label fw-bold">ตัวกรองผลลัพธ์</label>
        <select class="form-select" name="filter_status">
            <option value="all" <?= $filterStatus == 'all' ? 'selected' : '' ?>>แสดงทั้งหมด</option>
            <option value="missing" <?= $filterStatus == 'missing' ? 'selected' : '' ?>>เฉพาะที่หายไป (Missing)</option>
            <option value="mismatch" <?= $filterStatus == 'mismatch' ? 'selected' : '' ?>>เฉพาะ Sales ไม่ตรง</option>
        </select>
    </div>

    <div class="col-md-2 align-self-end">
        <button type="submit" class="btn btn-primary w-100">
            <i class="fas fa-search"></i> ค้นหา
        </button>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-bordered table-hover table-sm align-middle">
        <thead class="table-primary text-center">
            <tr>
                <th style="width: 5%;">#</th> <th style="width: 15%;">NO-NAV</th>
                <th style="width: 15%;">ERP REF</th>
                <th style="width: 15%;">ERP STATUS</th> <th style="width: 15%;">ERP SALES</th>
                <th style="width: 15%;">NAV SALES</th>
                <th style="width: 20%;">MATCH CHECK</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $count = 0; // ตัวนับลำดับ
            if (empty($navList)): 
            ?>
                <tr>
                    <td colspan="7" class="text-center text-muted p-4">ไม่พบข้อมูล NAV ที่มียอดเงินในเดือนนี้</td>
                </tr>
            <?php else: ?>
                <?php foreach ($navList as $key => $navItem): ?>
                    <?php
                        $erpItem = $erpList[$key] ?? null;
                        $hasErp  = !empty($erpItem);
                        
                        $navSp = $navItem['sp'];
                        $erpSp = $hasErp ? $erpItem['sp'] : '';
                        
                        // สถานะเอกสาร (0=Draft, 1=Submitted)
                        $docStatus = $hasErp ? $erpItem['status'] : -1; 

                        // Logic ตรวจสอบ
                        $isMissing = !$hasErp;
                        $isSpMismatch = $hasErp && ($navSp !== $erpSp);
                        
                        // Filtering
                        if ($filterStatus == 'missing' && !$isMissing) continue;
                        if ($filterStatus == 'mismatch' && !$isSpMismatch) continue;

                        $count++; // เพิ่มลำดับ
                        
                        // Style Row
                        $rowClass = '';
                        $statusBadge = '<span class="badge bg-success">Complete</span>';

                        if ($isMissing) {
                            $rowClass = 'table-warning'; 
                            $statusBadge = '<span class="badge bg-danger">Missing in ERP</span>';
                        } elseif ($isSpMismatch) {
                            $statusBadge = '<span class="badge bg-warning text-dark">Sales Mismatch</span>';
                        }
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td class="text-center fw-bold"><?= $count ?></td>

                        <td><?= esc($navItem['full_no']) ?></td>

                        <td class="text-center">
                            <?php if ($hasErp): ?>
                                <?= esc($erpItem['full_ref']) ?>
                            <?php else: ?>
                                <span class="text-danger fw-bold">#N/A</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-center">
                            <?php if ($hasErp): ?>
                                <?php if ($docStatus === 0): ?>
                                    <span class="badge bg-secondary text-light" style="width:80px">Draft</span>
                                <?php elseif ($docStatus === 1): ?>
                                    <span class="badge bg-success" style="width:80px">Submitted</span>
                                <?php else: ?>
                                    <span class="badge bg-dark"><?= $docStatus ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-center">
                            <?php if ($hasErp): ?>
                                <?php if ($isSpMismatch): ?>
                                    <span class="text-danger fw-bold"><?= esc($erpSp) ?></span>
                                    <i class="fas fa-exclamation-circle text-danger small"></i>
                                <?php else: ?>
                                    <span class="text-success"><?= esc($erpSp) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-center">
                            <?= esc($navSp) ?>
                        </td>

                        <td class="text-center">
                            <?= $statusBadge ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr class="table-light fw-bold">
                <td colspan="7" class="text-end px-4">
                    แสดงผลทั้งหมด: <span class="text-primary fs-5"><?= number_format($count) ?></span> รายการ
                </td>
            </tr>
        </tfoot>
    </table>
    
    <?php if (!empty($navList) && $count == 0): ?>
        <div class="alert alert-info text-center">
            ไม่พบข้อมูลตามเงื่อนไขตัวกรอง (<?= esc($filterStatus) ?>)
        </div>
    <?php endif; ?>
</div>

<div class="mt-3 text-muted small">
    <strong>หมายเหตุ:</strong><br>
    * <strong>NAV Data:</strong> กรองเฉพาะใบที่มี Amount > 0<br>
    * <strong>Prefix Match:</strong> ตัดคำว่า "ITG-" ออกเพื่อจับคู่<br>
    * <strong>Status:</strong> <span class="badge bg-secondary">Draft</span> = ยังไม่ Submit, <span class="badge bg-success">Submitted</span> = ยืนยันแล้ว
</div>