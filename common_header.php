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

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
  
  <!-- Bootstrap CSS -->
  <link href="./bootstrap/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    :root {
      --primary-color: <?=$themeColor ?? '#2e7d32'?>;
      --primary-light: #e8f5e9;
      --text-color: #333;
      --border-color: #e0e0e0;
      --bg-color: #f8f9fa;
      --card-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }

    body {
      font-family: "Prompt", system-ui, -apple-system, sans-serif;
      font-size: 15px;
      background-color: var(--bg-color);
      color: var(--text-color);
      line-height: 1.6;
    }

    .container {
      margin-top: 30px;
      margin-bottom: 50px;
      max-width: 1200px;
    }

    h1 {
      text-align: center;
      font-weight: 600;
      color: var(--primary-color);
      margin-bottom: 25px;
      font-size: 2rem;
    }

    /* Card Style */
    .content-wrapper {
      background: #fff;
      border-radius: 12px;
      box-shadow: var(--card-shadow);
      padding: 25px;
      margin-top: 20px;
      border: 1px solid rgba(0,0,0,0.03);
    }

    /* Table Styling */
    .table {
      margin-bottom: 0;
      border-collapse: separate; 
      border-spacing: 0;
    }
    
    .table thead th {
      background-color: var(--primary-color);
      color: #fff;
      border: none;
      font-weight: 500;
      padding: 12px 15px;
      white-space: nowrap;
    }
    
    .table thead th:first-child { border-top-left-radius: 8px; }
    .table thead th:last-child { border-top-right-radius: 8px; }

    .table tbody td {
      padding: 12px 15px;
      border-bottom: 1px solid var(--border-color);
      vertical-align: middle;
      color: #444;
    }

    .table tbody tr:hover {
      background-color: var(--primary-light);
      transition: background-color 0.2s ease;
    }

    .table tfoot td {
      background-color: #f1f8e9;
      font-weight: 600;
      border-top: 2px solid var(--primary-color);
      color: var(--primary-color);
    }

    .num { text-align: right; font-variant-numeric: tabular-nums; }
    .muted { color: #999; }
    
    /* Custom Box for Compare Page */
    .box {
      border: 1px solid var(--border-color);
      border-radius: 10px;
      margin-bottom: 25px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    }
    
    .hdr {
      background: #fff9c4;
      padding: 10px 15px;
      font-weight: 600;
      text-align: center;
      color: #f57f17;
      border-bottom: 1px solid #fff59d;
    }
    
    .subhdr th {
      background: var(--primary-light);
      color: var(--primary-color);
      font-weight: 600;
    }
    
    .remark { min-width: 180px; }
    .small-muted { color: #888; font-size: 12px; }

    /* Navigation Tabs */
    .nav-tabs {
      border-bottom: 2px solid var(--border-color);
      gap: 5px;
    }
    
    .nav-tabs .nav-link {
      border: none;
      color: #666;
      font-weight: 500;
      padding: 10px 20px;
      border-radius: 8px 8px 0 0;
      transition: all 0.2s;
    }
    
    .nav-tabs .nav-link:hover {
      color: var(--primary-color);
      background: rgba(46, 125, 50, 0.05);
    }
    
    .nav-tabs .nav-link.active {
      color: var(--primary-color);
      background: #fff;
      border-bottom: 3px solid var(--primary-color);
      font-weight: 600;
    }

    .nav-link p { margin: 0; display: inline; }
    .nav-icon { margin-right: 8px; font-size: 0.9em; }
    
    /* Form Elements */
    .form-control {
      border-radius: 8px;
      padding: 10px 15px;
      border: 1px solid var(--border-color);
    }
    
    .form-control:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 0.2rem rgba(46, 125, 50, 0.25);
    }
    
    .btn-success {
      background-color: var(--primary-color);
      border: none;
      border-radius: 8px;
      padding: 10px 20px;
      font-weight: 500;
      box-shadow: 0 2px 4px rgba(46, 125, 50, 0.3);
      transition: all 0.2s;
    }
    
    .btn-success:hover {
      background-color: #1b5e20;
      transform: translateY(-1px);
      box-shadow: 0 4px 8px rgba(46, 125, 50, 0.4);
    }

    /* Loading Overlay */
    #loading-overlay {
      position: fixed;
      top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(255, 255, 255, 0.8);
      z-index: 9999;
      display: none;
      justify-content: center;
      align-items: center;
      flex-direction: column;
    }
    .spinner {
      width: 50px; height: 50px;
      border: 5px solid var(--primary-light);
      border-top: 5px solid var(--primary-color);
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin-bottom: 10px;
    }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

    /* Copy Button */
    .btn-copy {
      background-color: #fff;
      border: 1px solid var(--border-color);
      color: #555;
      font-size: 0.85rem;
      padding: 5px 10px;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.2s;
      margin-bottom: 5px;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }
    .btn-copy:hover {
      background-color: #f0f0f0;
      color: var(--primary-color);
      border-color: var(--primary-color);
    }
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