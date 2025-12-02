<?php
/* * เนื้อหาจาก compare_nav_erp.php 
 * (ลบ require, esc(), date_default, HTML head/body ออก)
 */

/* ---------- helpers (เฉพาะหน้านี้) ---------- */
function qcol(PDO $pdo, string $sql, array $p=[]){ $st=$pdo->prepare($sql); $st->execute($p); return (int)$st->fetchColumn(); }
function prefixToYm(string $pfx): ?string {
  if (!preg_match('/^\d{4}$/',$pfx)) return null;
  $yy=substr($pfx,0,2); $mm=substr($pfx,2,2);
  if((int)$mm<1|| (int)$mm>12) return null;
  return "20$yy-$mm";
}
function hasColumn(PDO $pdo, string $tableWithBackticks, string $column): bool {
  $st = $pdo->prepare("SHOW COLUMNS FROM $tableWithBackticks LIKE :col");
  $st->execute([':col'=>$column]);
  return (bool)$st->fetch(PDO::FETCH_ASSOC);
}

/* ---------- CONFIG ERP (เฉพาะหน้านี้) ---------- */
$ERP_T = [
  // 'SO' => ['`tabSales Order`',      'transaction_date'], // --- ตัด SO ออก ---
  'PO' => ['`tabPurchase Order`',   'transaction_date'],
  'PR' => ['`tabPurchase Receipt`', 'posting_date'],
  'DN' => ['`tabDelivery Note`',    'posting_date'],
];
// --- ตัด SO ออก ---
$LABEL = ['PO'=>'Purchase Order','PR'=>'Purchase Receipt','DN'=>'Delivery Note'];

/* ---------- params ---------- */
$pfxs = array_filter(array_map('trim', explode(',', $_GET['pfxs'] ?? '')));
$yms  = array_filter(array_map('trim', explode(',', $_GET['yms']  ?? '')));

$jobs = [];
// (Logic การสร้าง $jobs เหมือนเดิม... )
if ($pfxs) {
  foreach ($pfxs as $p) {
    $ym = prefixToYm($p);
    if ($ym) $jobs[] = ['title'=>DateTime::createFromFormat('Y-m',$ym)->format('m/Y'), 'pfx'=>$p, 'ym'=>$ym];
  }
}
if ($yms) {
  foreach ($yms as $ym) {
    if (!preg_match('/^\d{4}-\d{2}$/',$ym)) continue;
    $p = substr($ym,2,2).substr($ym,5,2);
    $jobs[] = ['title'=>DateTime::createFromFormat('Y-m',$ym)->format('m/Y'), 'pfx'=>$p, 'ym'=>$ym];
  }
}
if (!$jobs) { // default = เดือนปัจจุบัน
  $ym = date('Y-m'); $p = substr($ym,2,2).substr($ym,5,2);
  $jobs[] = ['title'=>date('m/Y'),'pfx'=>$p,'ym'=>$ym];
}


/* ---------- NAV (ตาม prefix) ---------- */
function getNavByPrefix(PDO $pdo, string $pfx, array $T): array {
  $nav = [];
  // --- ตัด SO ออก ---
  // $nav['SO'] = qcol($pdo, "SELECT COUNT(*) FROM {$T['SO']} WHERE [No_] LIKE ?", ["ITG-SO{$pfx}%"]);
  $nav['DN'] = qcol($pdo, "SELECT COUNT(*) FROM {$T['DN']} WHERE [No_] LIKE ?", ["ITG-SH{$pfx}%"]);
  $nav['PO'] = qcol($pdo, "SELECT COUNT(*) FROM {$T['PO']} WHERE [No_] LIKE ? OR [No_] LIKE ?", ["ITG-POD{$pfx}%","ITG-POF{$pfx}%"]);
  $nav['PR'] = qcol($pdo, "SELECT COUNT(*) FROM {$T['PR']} WHERE [No_] LIKE ?", ["ITG-RC{$pfx}%"]);
  return $nav;
}

/* ---------- ERP (ตาม prefix) ---------- */
function getErpByPrefix(PDO $pdo, array $ERP_T, string $pfx, array &$warnings): array {
  $out = [];
  // --- ตัด SO ออก ---
  foreach (['PO','PR','DN'] as $k) {
    [$tbl, ] = $ERP_T[$k];
    $col = ($k === 'DN') ? 'nav_reference_number' : 'nav_ref';

    if (!hasColumn($pdo, $tbl, $col)) {
      $warnings[] = sprintf('%s: missing column `%s`', trim($tbl,'`'), $col);
      $out[$k] = ['draft'=>0,'submitted'=>0,'total'=>0];
      continue;
    }
    
    // (Logic ที่เหลือของ getErpByPrefix... )
    $sql = "
      SELECT 
        COUNT(*) AS total_orders,
        docstatus,
        COALESCE(`status`, '') AS status
      FROM $tbl
      WHERE `{$col}` LIKE :pfx
        AND docstatus != 2
      GROUP BY docstatus, COALESCE(`status`, '')
      ORDER BY docstatus, COALESCE(`status`, '')
    ";
    try {
      $st = $pdo->prepare($sql);
      $st->execute([':pfx' => "%{$pfx}%"]);
      $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      $warnings[] = sprintf('%s: %s', trim($tbl,'`'), $e->getMessage());
      $out[$k] = ['draft'=>0,'submitted'=>0,'total'=>0];
      continue;
    }
    $draft = 0; $submitted = 0;
    foreach ($rows as $r) {
      $cnt = (int)$r['total_orders'];
      if ((int)$r['docstatus'] === 0) $draft += $cnt;
      elseif ((int)$r['docstatus'] === 1) $submitted += $cnt;
    }
    $out[$k] = ['draft'=>$draft,'submitted'=>$submitted,'total'=>$draft+$submitted];
  }
  return $out;
}

/* ---------- render (เฉพาะส่วนเนื้อหา) ---------- */
?>

<?php
// Helper: esc (Safe definition)
if (!function_exists('esc')) {
    function esc($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
    }
}
?>

<!-- Search Form -->
<div class="content-wrapper mb-4">
  <form class="row g-3 align-items-end" method="get">
    <input type="hidden" name="page" value="monthly">
    
    <div class="col-md-5">
      <label class="form-label fw-bold text-muted">Prefix เดือน (เช่น 2509,2510)</label>
      <input class="form-control" name="pfxs" value="<?=esc($_GET['pfxs'] ?? '')?>" placeholder="2509,2510">
    </div>
    <div class="col-md-5">
      <label class="form-label fw-bold text-muted">เดือน (YYYY-MM) เช่น 2025-09,2025-10</label>
      <input class="form-control" name="yms" value="<?=esc($_GET['yms'] ?? '')?>" placeholder="2025-09,2025-10">
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Compare</button>
    </div>
    <div class="col-12">
      <small class="text-muted"><i class="fas fa-info-circle me-1"></i> ช่อง <b>REMARK</b> พิมพ์เองได้ และระบบจะจำค่าไว้ในเบราว์เซอร์อัตโนมัติ (localStorage)</small>
    </div>
  </form>
</div>

<?php
// --- ตัด SO ออก ---
$T = ['DN'=>$T_SH,'PO'=>$T_PO,'PR'=>$T_PR];
$erpWarnings = [];

foreach ($jobs as $job) {
  // NAV ตาม prefix
  $nav = getNavByPrefix($pdoNav, $job['pfx'], $T);
  // ERP
  $erp = getErpByPrefix($pdoErp, $ERP_T, $job['pfx'], $erpWarnings);

  $sum=['nav'=>0,'d'=>0,'s'=>0,'t'=>0,'diff'=>0];
  $rows=[];
  // --- ตัด SO ออก ---
  foreach(['PO','PR','DN'] as $k){
    $n=$nav[$k]??0; $d=$erp[$k]['draft']??0; $s=$erp[$k]['submitted']??0; $t=$erp[$k]['total']??0; $diff=$n-$t;
    $rows[]=['key'=>$k,'label'=>$LABEL[$k],'nav'=>$n,'d'=>$d,'s'=>$s,'t'=>$t,'diff'=>$diff];
    $sum['nav']+=$n; $sum['d']+=$d; $sum['s']+=$s; $sum['t']+=$t; $sum['diff']+=$diff;
  }
?>
  
  <div class="content-wrapper mb-4" data-ym="<?=esc($job['ym'])?>">
    <!-- Enhanced Month Header with Gradient -->
    <div class="month-header mb-4">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="header-icon-wrapper me-3">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div>
                    <h3 class="mb-1 fw-bold"><?=esc($job['title'])?></h3>
                    <p class="mb-0 text-muted small">
                        <i class="fas fa-tag me-1"></i>Prefix: <span class="badge bg-secondary"><?=esc($job['pfx'])?></span>
                    </p>
                </div>
            </div>
            <div class="text-end">
                <span class="badge bg-primary px-3 py-2">
                    <i class="fas fa-chart-bar me-1"></i>Monthly Report
                </span>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="dashboard-container mb-4">
        <div class="stat-card">
            <div class="stat-icon bg-blue-light"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-info">
                <h5>Total NAV</h5>
                <h3><?= number_format($sum['nav']) ?></h3>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-green-light"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <h5>Total ERP</h5>
                <h3 class="text-success"><?= number_format($sum['t']) ?></h3>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-red-light"><i class="fas fa-balance-scale"></i></div>
            <div class="stat-info">
                <h5>Difference</h5>
                <h3 style="color: <?= $sum['diff'] != 0 ? '#c62828' : '#2e7d32' ?>;"><?= number_format($sum['diff']) ?></h3>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light text-center">
          <tr>
            <th rowspan="2" style="width:15%" class="align-middle">Document Type</th>
            <th rowspan="2" style="width:10%" class="align-middle">NAV</th>
            <th colspan="3" style="width:30%" class="align-middle">ERP</th>
            <th rowspan="2" style="width:10%" class="align-middle">DIFF</th>
            <th rowspan="2" style="width:35%" class="align-middle">REMARK</th>
          </tr>
          <tr class="text-muted small">
            <th>DRAFT</th><th>SUBMIT</th><th>TOTAL</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($rows as $r): ?>
          <tr data-doc="<?=esc($r['key'])?>">
            <td class="fw-bold text-muted"><?=esc($r['label'])?></td>
            <td class="text-end fw-bold text-primary"><?=number_format($r['nav'])?></td>
            <td class="text-end text-muted"><?=number_format($r['d'])?></td>
            <td class="text-end text-success"><?=number_format($r['s'])?></td>
            <td class="text-end fw-bold text-dark"><?=number_format($r['t'])?></td>
            <td class="text-end fw-bold" style="color: <?= $r['diff'] != 0 ? '#c62828' : '#2e7d32' ?>;">
                <?=number_format($r['diff'])?>
            </td>
            <td class="remark bg-light border" contenteditable="true" spellcheck="false" style="outline: none; min-width: 200px;"></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        
        <tfoot class="table-light fw-bold">
          <tr>
            <td>Total</td>
            <td class="text-end text-primary"><?=number_format($sum['nav'])?></td>
            <td class="text-end">-</td> <td class="text-end">-</td> <td class="text-end text-dark"><?=number_format($sum['t'])?></td>
            <td class="text-end" style="color: <?= $sum['diff'] != 0 ? '#c62828' : '#2e7d32' ?>;">
                <?=number_format($sum['diff'])?>
            </td>
            <td>-</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
<?php } ?>

  <?php if (!empty($erpWarnings)): ?>
  <div class="alert alert-warning mt-3 shadow-sm">
    <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>ERP Warnings</h5>
    <ul class="mb-0">
      <?php foreach(array_unique($erpWarnings) as $w): ?>
        <li><code><?=esc($w)?></code></li>
      <?php endforeach; ?>
    </ul>
    <hr>
    <small class="mb-0">สร้าง Custom Field ให้ครบ: SO/PO/PR = <code>nav_ref</code>, DN = <code>nav_reference_number</code></small>
  </div>
  <?php endif; ?>

<style>
/* Enhanced Month Header */
.month-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 12px;
  padding: 20px 25px;
  color: white;
  box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
  position: relative;
  overflow: hidden;
}
.month-header::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -10%;
  width: 200px;
  height: 200px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 50%;
}
.month-header h3 {
  color: white;
  font-size: 1.5rem;
  text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.month-header .text-muted {
  color: rgba(255, 255, 255, 0.85) !important;
}
.header-icon-wrapper {
  width: 60px;
  height: 60px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.8rem;
  color: white;
  backdrop-filter: blur(10px);
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}
.month-header .badge {
  font-size: 0.85rem;
  font-weight: 500;
  letter-spacing: 0.3px;
}
.month-header .bg-secondary {
  background-color: rgba(255, 255, 255, 0.25) !important;
  border: 1px solid rgba(255, 255, 255, 0.3);
}
.month-header .bg-primary {
  background-color: rgba(255, 255, 255, 0.2) !important;
  border: 1px solid rgba(255, 255, 255, 0.3);
  backdrop-filter: blur(10px);
}
</style>