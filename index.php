<?php
// 1. โหลด Config และเชื่อมต่อ DB (จากโฟลเดอร์นอกเว็บ)
// *** แก้ Path ตรงนี้ให้ถูก ***
require __DIR__ . '/dbconnect.inc.php';

// 2. ตรวจสอบว่าผู้ใช้ต้องการดูหน้าไหน
// global $page; // ประกาศเพื่อให้ header.php รู้
$page = $_GET['page'] ?? 'monthly'; // หน้า default คือ 'monthly'

// 3. กำหนด Theme Color ตามเดือน
$month = (int)date('m'); // Default = เดือนปัจจุบัน

// พยายามหาเดือนจาก GET parameter
if (!empty($_GET['pfx']) && preg_match('/^\d{4}$/', $_GET['pfx'])) {
    $month = (int)substr($_GET['pfx'], 2, 2);
} elseif (!empty($_GET['pfxs'])) {
    $pfxs = explode(',', $_GET['pfxs']);
    if (isset($pfxs[0]) && preg_match('/^\d{4}$/', trim($pfxs[0]))) {
        $month = (int)substr(trim($pfxs[0]), 2, 2);
    }
} elseif (!empty($_GET['yms'])) {
    $yms = explode(',', $_GET['yms']);
    if (isset($yms[0]) && preg_match('/^\d{4}-\d{2}$/', trim($yms[0]))) {
        $month = (int)substr(trim($yms[0]), 5, 2);
    }
}

// Map สีตามเดือน (1-12)
$themeColors = [
    1 => '#F9A825', // Jan - Yellow Dark
    2 => '#D81B60', // Feb - Pink
    3 => '#00ACC1', // Mar - Cyan
    4 => '#43A047', // Apr - Green
    5 => '#FB8C00', // May - Orange
    6 => '#1E88E5', // Jun - Blue
    7 => '#8E24AA', // Jul - Purple
    8 => '#3949AB', // Aug - Indigo
    9 => '#00897B', // Sep - Teal
    10 => '#E53935', // Oct - Red
    11 => '#FFB300', // Nov - Amber
    12 => '#546E7A', // Dec - Blue Grey
];

$themeColor = $themeColors[$month] ?? '#2e7d32'; // Default Green

// 4. โหลด Header (HTML, CSS, Menu)
require __DIR__ . '/common_header.php';

// 5. โหลดเนื้อหาหน้า (Page Content)
echo '<div class="content-wrapper" id="page-content-' . esc($page) . '">';
switch ($page) {
    case 'sales':
        // โหลดตัวแปรที่จำเป็นสำหรับหน้านี้ (จาก dbconnect)
        $T_SO = $NAV_T['SO'];
        $T_SP = $NAV_T['SP'];
        require __DIR__ . '/page_compare_sales.php'; 
        break;

    case 'salesv2': // หน้าเปรียบเทียบ Sales (เฉพาะ Amount > 0)
        $T_SO = $NAV_T['SO'];
        $T_SP = $NAV_T['SP'];
        $T_SL = $NAV_T['SL']; // ใช้สำหรับเช็ค Amount
        require __DIR__ . '/page_compare_salesV2.php';
        break;

    /* --- VVVV เพิ่มส่วนนี้สำหรับหน้าใหม่ (Match Check) VVVV --- */
    case 'match_list': 
        // โหลดตัวแปรตารางที่จำเป็น
        $T_SO = $NAV_T['SO'];
        $T_SP = $NAV_T['SP'];
        $T_SL = $NAV_T['SL']; // ใช้สำหรับเช็ค Amount ในหน้า Match ด้วย
        
        // เรียกใช้ไฟล์ที่เราเพิ่งสร้าง
        require __DIR__ . '/page_compare_match.php';
        break;
    /* --- ^^^^ จบส่วนที่เพิ่ม ^^^^ --- */

    case 'monthly':
    default:
        // โหลดตัวแปรที่จำเป็นสำหรับหน้านี้
        $T_SO = $NAV_T['SO'];
        $T_SH = $NAV_T['SH'];
        $T_PO = $NAV_T['PO'];
        $T_PR = $NAV_T['PR'];
        require __DIR__ . '/page_compare_monthly.php';
        break;
}
echo '</div>';

// 6. โหลด Footer (ปิด HTML, JS)
require __DIR__ . '/common_footer.php';

?>