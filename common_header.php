<?php
// ดึงตัวแปร $page จาก index.php
global $page; 
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>NAV ERP Comparison</title>
  <link rel="icon" href="./logo.png">

  <!-- Google Fonts: Prompt -->
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Bootstrap 5 CSS -->
  <link href="./bootstrap/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    :root {
      --primary-color: <?=$themeColor ?? '#2e7d32'?>;
      --primary-dark: #1b5e20;
      --primary-light: #e8f5e9;
      --secondary-color: #f57f17;
      --text-color: #333;
      --text-muted: #6c757d;
      --border-color: #e0e0e0;
      --bg-color: #f4f6f8;
      --card-shadow: 0 4px 6px rgba(0,0,0,0.04);
      --header-height: 70px;
    }

    body {
      font-family: "Prompt", system-ui, -apple-system, sans-serif;
      font-size: 16px;
      background-color: var(--bg-color);
      color: var(--text-color);
      line-height: 1.6;
    }

    /* --- Universal Dashboard Styles --- */
    .dashboard-container {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 25px;
    }
    .stat-card {
        flex: 1;
        min-width: 220px;
        background: #fff;
        border: none;
        border-radius: 12px;
        padding: 20px;
        box-shadow: var(--card-shadow);
        display: flex;
        align-items: center;
        transition: transform 0.2s;
    }
    .stat-card:hover {
        transform: translateY(-2px);
    }
    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-right: 20px;
    }
    .stat-info h5 { margin: 0 0 5px 0; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
    .stat-info h3 { margin: 0; font-size: 1.75rem; font-weight: 700; color: var(--text-color); }
    
    .bg-blue-light { background-color: #e3f2fd; color: #1976d2; }
    .bg-green-light { background-color: #e8f5e9; color: #2e7d32; }
    .bg-red-light { background-color: #ffebee; color: #c62828; }
    .bg-orange-light { background-color: #fff3e0; color: #ef6c00; }

    /* --- Layout & Containers --- */
    .main-header {
      background: #fff;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
      padding: 15px 0;
      margin-bottom: 30px;
      position: sticky;
      top: 0;
      z-index: 1000;
    }

    .container {
      max-width: 96%;
      width: 100%;
    }

    .page-title {
      font-weight: 700;
      color: var(--primary-color);
      margin: 0;
      font-size: 1.5rem;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    /* --- Navigation --- */
    .nav-pills .nav-link {
      color: var(--text-muted);
      font-weight: 500;
      padding: 8px 16px;
      border-radius: 8px;
      transition: all 0.2s;
      margin-right: 5px;
    }
    
    .nav-pills .nav-link:hover {
      background-color: rgba(0,0,0,0.03);
      color: var(--primary-color);
    }
    
    .nav-pills .nav-link.active {
      background-color: var(--primary-color);
      color: #fff;
      box-shadow: 0 2px 4px rgba(46, 125, 50, 0.3);
    }

    .nav-icon { margin-right: 6px; }

    /* --- Cards & Content --- */
    .content-wrapper {
      background: #fff;
      border-radius: 12px;
      box-shadow: var(--card-shadow);
      padding: 25px;
      margin-bottom: 30px;
      border: 1px solid rgba(0,0,0,0.02);
    }

    /* --- Tables --- */
    .table-custom {
      border-collapse: separate;
      border-spacing: 0;
      width: 100%;
    }
    .table-custom thead th {
      background-color: var(--primary-light);
      color: var(--primary-dark);
      font-weight: 600;
      padding: 12px 15px;
      border-bottom: 2px solid var(--primary-color);
      white-space: nowrap;
    }
    .table-custom tbody td {
      padding: 12px 15px;
      border-bottom: 1px solid var(--border-color);
      vertical-align: middle;
    }
    .table-custom tbody tr:hover {
      background-color: #fafafa;
    }

    /* --- Enhanced Table Styling for Better Visibility --- */
    .table {
      font-size: 1.05rem;
    }
    .table thead th {
      font-size: 1.1rem;
      padding: 16px 14px;
      font-weight: 600;
    }
    .table tbody td {
      padding: 15px 14px;
      font-size: 1rem;
    }
    .table tfoot td {
      padding: 17px 14px;
      font-size: 1.1rem;
    }
    
    /* --- Forms --- */
    .form-control {
      border-radius: 8px;
      padding: 11px 16px;
      border: 1px solid #ced4da;
      transition: border-color 0.2s, box-shadow 0.2s;
      font-size: 1rem;
    }
    .form-control:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 0.2rem rgba(46, 125, 50, 0.15);
    }
    .btn-primary {
      background-color: var(--primary-color);
      border-color: var(--primary-color);
      border-radius: 8px;
      padding: 11px 22px;
      font-weight: 500;
      font-size: 1rem;
      box-shadow: 0 2px 4px rgba(46, 125, 50, 0.2);
    }
    .btn-primary:hover {
      background-color: var(--primary-dark);
      border-color: var(--primary-dark);
      transform: translateY(-1px);
    }

    /* --- Utilities --- */
    .text-primary { color: var(--primary-color) !important; }
    .text-success { color: #2e7d32 !important; }
    .text-danger { color: #c62828 !important; }
    
    /* Loading Overlay */
    #loading-overlay {
      position: fixed;
      top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(255, 255, 255, 0.9);
      z-index: 9999;
      display: none;
      justify-content: center;
      align-items: center;
      flex-direction: column;
      backdrop-filter: blur(2px);
    }
    .spinner {
      width: 50px; height: 50px;
      border: 4px solid var(--primary-light);
      border-top: 4px solid var(--primary-color);
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
      margin-bottom: 15px;
    }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

    /* Copy Button */
    .btn-copy {
      background-color: #fff;
      border: 1px solid #ddd;
      color: #555;
      font-size: 0.85rem;
      padding: 5px 12px;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.2s;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }
    .btn-copy:hover {
      background-color: #f8f9fa;
      color: var(--primary-color);
      border-color: var(--primary-color);
    }
  </style>
</head>
<body>

<!-- Main Header -->
<header class="main-header">
  <div class="container d-flex justify-content-between align-items-center">
    <div class="d-flex align-items-center">
        <!-- Logo or Icon -->
        <div class="me-3 d-flex align-items-center justify-content-center bg-green-light rounded-circle" style="width: 40px; height: 40px;">
            <i class="fas fa-chart-line text-success"></i>
        </div>
        <h1 class="page-title">NAV-ERP Compare</h1>
    </div>
    
    <!-- Navigation -->
    <ul class="nav nav-pills">
      <li class="nav-item">
        <a class="nav-link <?=($page === 'monthly') ? 'active' : ''?>" href="index.php?page=monthly">
          <i class="nav-icon fas fa-calendar-alt"></i> รายเดือน
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?=($page === 'sales') ? 'active' : ''?>" href="index.php?page=sales">
          <i class="nav-icon fas fa-user-tie"></i> Sales
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= ($page == 'salesv2') ? 'active' : '' ?>" href="index.php?page=salesv2">
          <i class="nav-icon fas fa-filter"></i> Sales V2
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= ($page == 'match_list') ? 'active' : '' ?>" href="index.php?page=match_list">
          <i class="nav-icon fas fa-exchange-alt"></i> เช็คยอดชนใบ
        </a>
      </li>
    </ul>
  </div>
</header>

<div class="container">