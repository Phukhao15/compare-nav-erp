<?php
// ดึงตัวแปร $page จาก index.php
global $page; 
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <title>NAV ERP</title>
  
<link rel="icon" href="./logo.png">




  <link href="./bootstrap/css/bootstrap.min.css" rel="stylesheet">
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    body{font-family:"TH Sarabun New",system-ui,Arial;font-size:16px;background:#f6f6f6}
    .container{margin-top:20px}
    h1{text-align:center;font-weight:700;color:#1b5e20;margin-bottom:12px}
    table{width:100%;border-collapse:collapse;background:#fff}
    th,td{border:1px solid #cdd;padding:6px 8px;text-align:center}
    thead th{background:#2e7d32;color:#fff}
    tfoot td{background:#e7f2e7;font-weight:700}
    .num{text-align:right}
    .muted{color:#aaa}
    
    /* Style จาก compare_nav_erp.php */
    .box{border:1px solid #c9d8c1;border-radius:8px;margin-bottom:20px;overflow:hidden}
    .hdr{background:#fff59d;padding:6px 10px;font-weight:700;text-align:center}
    .subhdr th{background:#e5f2e3;color:#1b5e20}
    .remark{min-width: 180px;}
    .small-muted{color:#888;font-size:12px}

    /* ปรับแต่ง Nav Link ให้ไอคอนอยู่บรรทัดเดียวกับข้อความ */
    .nav-link p { margin: 0; display: inline; }
    .nav-icon { margin-right: 5px; }
  </style>
</head>
<body>

<div class="container">
  
  <ul class="nav nav-tabs mb-3">
    <li class="nav-item">
      <a class="nav-link <?=($page === 'monthly') ? 'active' : ''?>" href="index.php?page=monthly">
        เปรียบเทียบรายเดือน
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?=($page === 'sales') ? 'active' : ''?>" href="index.php?page=sales">
        เปรียบเทียบตาม Sales
      </a>
    </li>

    <li class="nav-item">
      <a class="nav-link <?= ($page == 'salesv2') ? 'active' : '' ?>" href="index.php?page=salesv2">
        <i class="nav-icon fas fa-filter"></i> 
        เปรียบเทียบตาม SalesV2 (เฉพาะ Amount มีค่า)
      </a>
    </li>
    
    <li class="nav-item">
      <a class="nav-link <?= ($page == 'match_list') ? 'active' : '' ?>" href="index.php?page=match_list">
        <i class="nav-icon fas fa-exchange-alt"></i>
        เช็คยอดชนใบ (NAV vs ERP)
      </a>
    </li>
  </ul>