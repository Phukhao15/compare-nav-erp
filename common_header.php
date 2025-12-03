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

  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <link href="./bootstrap/css/bootstrap.min.css" rel="stylesheet">
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    :root {
      /* ธีมสีหลัก รับค่าจาก PHP หรือใช้ค่า Default */
      --primary-color: <?=$themeColor ?? '#2e7d32'?>;
      --primary-hover: color-mix(in srgb, var(--primary-color), black 10%);
      --bg-color: #f0f2f5;
      --card-bg: #ffffff;
      --text-main: #2d3748;
      --text-muted: #718096;
      --border-color: #e2e8f0;
      --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
      --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
      --radius: 12px;
    }

    body {
      font-family: "Prompt", system-ui, -apple-system, sans-serif;
      font-size: 15px;
      background-color: var(--bg-color);
      color: var(--text-main);
      -webkit-font-smoothing: antialiased;
    }

    /* --- Navbar Styling --- */
    .main-header {
      background: var(--card-bg);
      box-shadow: var(--shadow-sm);
      padding: 0.75rem 0;
      position: sticky;
      top: 0;
      z-index: 1000;
      margin-bottom: 2rem;
    }
    
    .brand-logo {
      font-weight: 700;
      font-size: 1.25rem;
      color: var(--primary-color);
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .nav-pills .nav-link {
      color: var(--text-muted);
      font-weight: 500;
      border-radius: 8px;
      padding: 8px 16px;
      transition: all 0.2s ease;
    }
    
    .nav-pills .nav-link:hover {
      background-color: #f7fafc;
      color: var(--primary-color);
    }
    
    .nav-pills .nav-link.active {
      background-color: var(--primary-color);
      color: #fff;
      box-shadow: 0 2px 4px rgba(0,0,0,0.15);
    }

    /* --- Card Container --- */
    .content-wrapper {
      background: var(--card-bg);
      border-radius: var(--radius);
      box-shadow: var(--shadow-sm);
      padding: 1.5rem;
      margin-bottom: 1.5rem;
      border: 1px solid var(--border-color);
    }

    /* --- Search Form --- */
    .search-box {
      background: #f8fafc;
      border: 1px solid var(--border-color);
      padding: 1.5rem;
      border-radius: var(--radius);
      margin-bottom: 1.5rem;
    }
    .form-label {
      font-weight: 600;
      font-size: 0.85rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--text-muted);
      margin-bottom: 0.5rem;
    }
    .form-control, .form-select {
      border-radius: 8px;
      border: 1px solid #cbd5e0;
      padding: 0.6rem 1rem;
      font-size: 0.95rem;
    }
    .form-control:focus, .form-select:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary-color), white 80%);
    }

    /* --- Dashboard Stats Cards --- */
    .dashboard-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    .stat-card {
      background: var(--card-bg);
      border-radius: var(--radius);
      padding: 1.5rem;
      box-shadow: var(--shadow-sm);
      border: 1px solid var(--border-color);
      display: flex;
      align-items: center;
      transition: transform 0.2s;
    }
    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }
    .stat-icon {
      width: 48px;
      height: 48px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      margin-right: 1rem;
      flex-shrink: 0;
    }
    /* Icon Colors */
    .bg-blue-light { background: #ebf8ff; color: #3182ce; }
    .bg-green-light { background: #f0fff4; color: #38a169; }
    .bg-red-light { background: #fff5f5; color: #e53e3e; }
    .bg-orange-light { background: #fffaf0; color: #dd6b20; }
    .bg-purple-light { background: #faf5ff; color: #805ad5; }

    .stat-info h5 {
      margin: 0 0 4px 0;
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      color: var(--text-muted);
    }
    .stat-info h3 {
      margin: 0;
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--text-main);
      line-height: 1.2;
    }

    /* --- Table Styling --- */
    .table-responsive {
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: var(--shadow-sm);
      border: 1px solid var(--border-color);
    }
    .table {
      margin-bottom: 0;
      width: 100%;
    }
    .table thead th {
      background-color: #f7fafc;
      color: var(--text-muted);
      font-weight: 600;
      text-transform: uppercase;
      font-size: 0.8rem;
      letter-spacing: 0.5px;
      padding: 12px 16px;
      border-bottom: 2px solid var(--border-color);
      white-space: nowrap;
    }
    .table tbody td {
      padding: 12px 16px;
      vertical-align: middle;
      color: var(--text-main);
      border-bottom: 1px solid var(--border-color);
    }
    .table tbody tr:hover {
      background-color: #f8fafc;
    }
    
    /* Status Badges */
    .badge-custom {
      padding: 6px 10px;
      border-radius: 6px;
      font-weight: 500;
      font-size: 0.75rem;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .badge-success { background: #def7ec; color: #03543f; }
    .badge-warning { background: #fdf6b2; color: #723b13; }
    .badge-danger { background: #fde8e8; color: #9b1c1c; }
    .badge-gray { background: #f3f4f6; color: #374151; }
    .badge-info { background: #e1effe; color: #1e429f; }

    /* Button Styling */
    .btn-primary {
      background-color: var(--primary-color);
      border-color: var(--primary-color);
      padding: 0.6rem 1.2rem;
      font-weight: 500;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .btn-primary:hover {
      background-color: var(--primary-hover);
      border-color: var(--primary-hover);
      transform: translateY(-1px);
    }

    /* Loading Overlay */
    #loading-overlay {
      position: fixed; top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(255, 255, 255, 0.8);
      backdrop-filter: blur(4px);
      z-index: 9999;
      display: none;
      justify-content: center;
      align-items: center;
      flex-direction: column;
    }
    .spinner {
      width: 40px; height: 40px;
      border: 3px solid #e2e8f0;
      border-top: 3px solid var(--primary-color);
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
  </style>
</head>
<body>

<header class="main-header">
  <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
    <div class="brand-logo">
        <div class="bg-green-light rounded-3 p-2 me-2">
            <i class="fas fa-layer-group text-success"></i>
        </div>
        <span>NAV-ERP Compare</span>
    </div>
    
    <ul class="nav nav-pills d-none d-md-flex">
      <li class="nav-item">
        <a class="nav-link <?=($page === 'monthly') ? 'active' : ''?>" href="index.php?page=monthly">
          <i class="fas fa-calendar-alt me-1"></i> รายเดือน
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?=($page === 'sales') ? 'active' : ''?>" href="index.php?page=sales">
          <i class="fas fa-user-tie me-1"></i> Sales
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= ($page == 'salesv2') ? 'active' : '' ?>" href="index.php?page=salesv2">
          <i class="fas fa-filter me-1"></i> Sales V2
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= ($page == 'match_list') ? 'active' : '' ?>" href="index.php?page=match_list">
          <i class="fas fa-check-double me-1"></i> เช็คยอดชนใบ
        </a>
      </li>
    </ul>
  </div>
</header>

<div class="container-fluid px-4">