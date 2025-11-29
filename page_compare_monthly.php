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

  <form class="row g-2 mb-3" method="get">
    <input type="hidden" name="page" value="monthly">
    
    <div class="col-md-5">
      <label class="form-label">Prefix เดือน (เช่น 2509,2510)</label>
      <input class="form-control" name="pfxs" value="<?=esc($_GET['pfxs'] ?? '')?>" placeholder="2509,2510">
    </div>
    <div class="col-md-5">
      <label class="form-label">เดือน (YYYY-MM) เช่น 2025-09,2025-10</label>
      <input class="form-control" name="yms" value="<?=esc($_GET['yms'] ?? '')?>" placeholder="2025-09,2025-10">
    </div>
    <div class="col-md-2 align-self-end">
      <button class="btn btn-success w-100">Compare</button>
    </div>
    <div class="col-12 small-muted mt-1">ช่อง <b>REMARK</b> พิมพ์เองได้ และระบบจะจำค่าไว้ในเบราว์เซอร์อัตโนมัติ (localStorage) ตามเดือนและประเภทเอกสาร</div>
  </form>

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
  <div class="box" data-ym="<?=esc($job['ym'])?>">
    <div class="hdr"><?=esc($job['title'])?></div>
    <table>
      <thead>
        <tr>
          <th rowspan="2" style="width:18%">Document<br>Type</th>
          <th rowspan="2" style="width:10%">NAV</th>
          <th colspan="3" style="width:33%">ERP</th>
          <th rowspan="2" style="width:14%">DIFF<br>(NAV-ERP)</th>
          <th rowspan="2" style="width:25%">REMARK</th>
        </tr>
        <tr class="subhdr">
          <th>DRAFT</th><th>SUBMITED</th><th>TOTAL (ERP)</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($rows as $r): ?>
        <tr data-doc="<?=esc($r['key'])?>">
          <td><?=esc($r['label'])?></td>
          <td class="num"><?=number_format($r['nav'])?></td>
          <td class="num"><?=number_format($r['d'])?></td>
          <td class="num"><?=number_format($r['s'])?></td>
          <td class="num"><?=number_format($r['t'])?></td>
          <td class="num"><?=number_format($r['diff'])?></td>
          <td class="remark" contenteditable="true" spellcheck="false"></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      
      <tfoot>
        <tr>
          <td>Total</td>
          <td class="num"><?=number_format($sum['nav'])?></td>
          <td class="num">-</td> <td class="num">-</td> <td class="num"><?=number_format($sum['t'])?></td>
          <td class="num"><?=number_format($sum['diff'])?></td>
          <td>-</td>
        </tr>
      </tfoot>
      </table>
  </div>
<?php } ?>

  <?php if (!empty($erpWarnings)): ?>
  <div class="alert alert-warning mt-3">
    ⚠️ ERP warnings:
    <ul class="mb-0">
      <?php foreach(array_unique($erpWarnings) as $w): ?>
        <li><code><?=esc($w)?></code></li>
      <?php endforeach; ?>
    </ul>
    <small>สร้าง Custom Field ให้ครบ: SO/PO/PR = <code>nav_ref</code>, DN = <code>nav_reference_number</code></small>
  </div>
  <?php endif; ?>