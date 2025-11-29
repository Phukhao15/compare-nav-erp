<?php
/* * เนื้อหาจาก nav_sale.php 
 * (ลบ require, esc(), date_default, HTML head/body ออก)
 */

// REMOVED: $schema, $company, $T_SO, $T_SP (ใช้ตัวแปรกลางจาก index.php)

$pfx = $_GET['pfx'] ?? '';
if (!preg_match('/^\d{4}$/',$pfx)) { $pfx = date('y').date('m'); }
$SO_PATTERN = "ITG-SO{$pfx}%";

/* NAV */
$sqlNav = "
SELECT
  COALESCE(NULLIF(LTRIM(RTRIM(sh.[Salesperson Code])), ''), 'UNKNOWN') AS sp_code,
  MAX(sp.[Name]) AS sp_name,
  COUNT(*) AS nav_total
FROM $T_SO AS sh
LEFT JOIN $T_SP AS sp ON sp.[Code] = sh.[Salesperson Code]
WHERE sh.[No_] LIKE :pfx
GROUP BY COALESCE(NULLIF(LTRIM(RTRIM(sh.[Salesperson Code])), ''), 'UNKNOWN')
";
$st = $pdoNav->prepare($sqlNav);
$st->execute([':pfx'=>$SO_PATTERN]);
$navRows = $st->fetchAll(PDO::FETCH_ASSOC);
$navMap = [];
foreach ($navRows as $r) {
  $code = strtoupper($r['sp_code']);
  $navMap[$code] = ['name'=>$r['sp_name'] ?? '', 'nav'=>(int)$r['nav_total']];
}

/* ERP */
$sqlErp = "
SELECT
  st.sales_person AS sp_code,
  COALESCE(sp.sales_person_name, st.sales_person) AS sp_name,
  SUM(so.docstatus = 0) AS draft,
  SUM(so.docstatus = 1) AS submitted,
  SUM(so.docstatus IN (0,1)) AS total_erp
FROM `tabSales Order` AS so
JOIN `tabSales Team` AS st
  ON st.parent = so.name
  AND st.parenttype = 'Sales Order'
LEFT JOIN `tabSales Person` AS sp ON sp.name = st.sales_person
WHERE so.nav_ref LIKE :pfx
  AND so.docstatus != 2
GROUP BY st.sales_person, sp.sales_person_name
";
$se = $pdoErp->prepare($sqlErp);
$se->execute([':pfx'=>$SO_PATTERN]);
$erpRows = $se->fetchAll(PDO::FETCH_ASSOC);
$erpMap = [];
foreach ($erpRows as $r) {
  $code = strtoupper($r['sp_code'] ?? '');
  if ($code === '') $code = 'UNKNOWN';
  $erpMap[$code] = [
    'name'=>$r['sp_name'] ?? '',
    'draft'=>(int)$r['draft'],
    'submitted'=>(int)$r['submitted'],
    'total'=>(int)$r['total_erp']
  ];
}

/* รวม NAV+ERP */
// (Logic การรวม $rows และ $tot เหมือนเดิม ... )
$minCode = 1; $maxCode = 35;
$rows = [];
$tot = ['nav'=>0,'t'=>0];
// UNKNOWN
$code = 'UNKNOWN';
$nm = $navMap[$code]['name'] ?? ($erpMap[$code]['name'] ?? '');
$nv = $navMap[$code]['nav'] ?? 0;
$dr = $erpMap[$code]['draft'] ?? 0;
$sb = $erpMap[$code]['submitted'] ?? 0;
$tt = $erpMap[$code]['total'] ?? ($dr+$sb);
$df = $nv-$tt;
if ($nv || $tt) {
  $rows[] = [
    'code'=>'ไม่ระบุ Sales Code',
    'name'=>$nm ?: 'ไม่ระบุชื่อ Sales',
    'nav'=>$nv,'d'=>$dr,'s'=>$sb,'t'=>$tt,'diff'=>$df
  ];
  $tot['nav']+=$nv; $tot['t']+=$tt;
}
// S001-S035
for ($i=$minCode; $i<=$maxCode; $i++) {
  $code='S'.str_pad((string)$i,3,'0',STR_PAD_LEFT);
  $nm=$navMap[$code]['name'] ?? ($erpMap[$code]['name'] ?? '');
  $nv=$navMap[$code]['nav'] ?? 0;
  $dr=$erpMap[$code]['draft'] ?? 0;
  $sb=$erpMap[$code]['submitted'] ?? 0;
  $tt=$erpMap[$code]['total'] ?? ($dr+$sb);
  $df=$nv-$tt;
  if ($nv || $tt) {
    $rows[]=[
      'code'=>$code,
      'name'=>$nm ?: 'ไม่ระบุชื่อ Sales',
      'nav'=>$nv,'d'=>$dr,'s'=>$sb,'t'=>$tt,'diff'=>$df
    ];
    $tot['nav']+=$nv; $tot['t']+=$tt;
  }
}

/* ---------- render (เฉพาะส่วนเนื้อหา) ---------- */
?>
  
  <!-- <h1>ข้อมูล Sales Order <?=esc($pfx)?></h1>
  
  <form class="row g-2 mb-3" method="get">
    <input type="hidden" name="page" value="sales">
  
    <div class="col-md-3">
      <label class="form-label">Prefix เดือน (เช่น 2509, 2510)</label>
      <input class="form-control" name="pfx" value="<?=esc($pfx)?>" placeholder="เช่น 2510"> --> -->
<?php
/* * เนื้อหาจาก nav_sale.php 
 * (ลบ require, esc(), date_default, HTML head/body ออก)
 */

// REMOVED: $schema, $company, $T_SO, $T_SP (ใช้ตัวแปรกลางจาก index.php)

$pfx = $_GET['pfx'] ?? '';
if (!preg_match('/^\d{4}$/',$pfx)) { $pfx = date('y').date('m'); }
$SO_PATTERN = "ITG-SO{$pfx}%";

/* NAV */
$sqlNav = "
SELECT
  COALESCE(NULLIF(LTRIM(RTRIM(sh.[Salesperson Code])), ''), 'UNKNOWN') AS sp_code,
  MAX(sp.[Name]) AS sp_name,
  COUNT(*) AS nav_total
FROM $T_SO AS sh
LEFT JOIN $T_SP AS sp ON sp.[Code] = sh.[Salesperson Code]
WHERE sh.[No_] LIKE :pfx
GROUP BY COALESCE(NULLIF(LTRIM(RTRIM(sh.[Salesperson Code])), ''), 'UNKNOWN')
";
$st = $pdoNav->prepare($sqlNav);
$st->execute([':pfx'=>$SO_PATTERN]);
$navRows = $st->fetchAll(PDO::FETCH_ASSOC);
$navMap = [];
foreach ($navRows as $r) {
  $code = strtoupper($r['sp_code']);
  $navMap[$code] = ['name'=>$r['sp_name'] ?? '', 'nav'=>(int)$r['nav_total']];
}

/* ERP */
$sqlErp = "
SELECT
  st.sales_person AS sp_code,
  COALESCE(sp.sales_person_name, st.sales_person) AS sp_name,
  SUM(so.docstatus = 0) AS draft,
  SUM(so.docstatus = 1) AS submitted,
  SUM(so.docstatus IN (0,1)) AS total_erp
FROM `tabSales Order` AS so
JOIN `tabSales Team` AS st
  ON st.parent = so.name
  AND st.parenttype = 'Sales Order'
LEFT JOIN `tabSales Person` AS sp ON sp.name = st.sales_person
WHERE so.nav_ref LIKE :pfx
  AND so.docstatus != 2
GROUP BY st.sales_person, sp.sales_person_name
";
$se = $pdoErp->prepare($sqlErp);
$se->execute([':pfx'=>$SO_PATTERN]);
$erpRows = $se->fetchAll(PDO::FETCH_ASSOC);
$erpMap = [];
foreach ($erpRows as $r) {
  $code = strtoupper($r['sp_code'] ?? '');
  if ($code === '') $code = 'UNKNOWN';
  $erpMap[$code] = [
    'name'=>$r['sp_name'] ?? '',
    'draft'=>(int)$r['draft'],
    'submitted'=>(int)$r['submitted'],
    'total'=>(int)$r['total_erp']
  ];
}

/* รวม NAV+ERP */
// (Logic การรวม $rows และ $tot เหมือนเดิม ... )
$minCode = 1; $maxCode = 35;
$rows = [];
$tot = ['nav'=>0,'t'=>0];
// UNKNOWN
$code = 'UNKNOWN';
$nm = $navMap[$code]['name'] ?? ($erpMap[$code]['name'] ?? '');
$nv = $navMap[$code]['nav'] ?? 0;
$dr = $erpMap[$code]['draft'] ?? 0;
$sb = $erpMap[$code]['submitted'] ?? 0;
$tt = $erpMap[$code]['total'] ?? ($dr+$sb);
$df = $nv-$tt;
if ($nv || $tt) {
  $rows[] = [
    'code'=>'ไม่ระบุ Sales Code',
    'name'=>$nm ?: 'ไม่ระบุชื่อ Sales',
    'nav'=>$nv,'d'=>$dr,'s'=>$sb,'t'=>$tt,'diff'=>$df
  ];
  $tot['nav']+=$nv; $tot['t']+=$tt;
}
// S001-S035
for ($i=$minCode; $i<=$maxCode; $i++) {
  $code='S'.str_pad((string)$i,3,'0',STR_PAD_LEFT);
  $nm=$navMap[$code]['name'] ?? ($erpMap[$code]['name'] ?? '');
  $nv=$navMap[$code]['nav'] ?? 0;
  $dr=$erpMap[$code]['draft'] ?? 0;
  $sb=$erpMap[$code]['submitted'] ?? 0;
  $tt=$erpMap[$code]['total'] ?? ($dr+$sb);
  $df=$nv-$tt;
  if ($nv || $tt) {
    $rows[]=[
      'code'=>$code,
      'name'=>$nm ?: 'ไม่ระบุชื่อ Sales',
      'nav'=>$nv,'d'=>$dr,'s'=>$sb,'t'=>$tt,'diff'=>$df
    ];
    $tot['nav']+=$nv; $tot['t']+=$tt;
  }
}

/* ---------- render (เฉพาะส่วนเนื้อหา) ---------- */
?>
  
  <h1>ข้อมูล Sales Order ทั้งหมด <?=esc($pfx)?></h1>
  
  <form class="row g-2 mb-3" method="get">
    <input type="hidden" name="page" value="sales">
    
    <div class="col-md-3">
      <label class="form-label">Prefix เดือน (เช่น 2509, 2510)</label>
      <input class="form-control" name="pfx" value="<?=esc($pfx)?>" placeholder="เช่น 2510">
    </div>
    <div class="col-md-2 align-self-end">
      <button class="btn btn-success w-100">Filter</button>
    </div>
  </form>
  
  <div class="table-responsive">
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
      <?php foreach ($rows as $r): ?>
      <tr>
        <td><?=esc($r['code'])?></td>
        <td><?=esc($r['name'])?></td>
        <td class="num"><?=number_format($r['nav'])?></td>
        <td class="num"><?=number_format($r['d'])?></td>
        <td class="num"><?=number_format($r['s'])?></td>
        <td class="num"><?=number_format($r['t'])?></td>
        <td class="num"><?=number_format($r['diff'])?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td><b>Total</b></td>
        <td></td>
        <td class="num"><b><?=number_format($tot['nav'])?></b></td>
        <td class="num muted">-</td>
        <td class="num muted">-</td>
        <td class="num"><b><?=number_format($tot['t'])?></b></td>
        <td class="num"><b><?=number_format($tot['nav'] - $tot['t'])?></b></td>
      </tr>
    </tfoot>
  </table>
  </div>