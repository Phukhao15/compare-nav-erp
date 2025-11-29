<?php
// 1. โหลด Config และเชื่อมต่อ DB (จากโฟลเดอร์นอกเว็บ)
// *** แก้ Path ตรงนี้ให้ถูก ***
require __DIR__ . '/../dbconnect.inc.php';

// 2. ตรวจสอบว่าผู้ใช้ต้องการดูหน้าไหน
// global $page; // ประกาศเพื่อให้ header.php รู้
$page = $_GET['page'] ?? 'monthly'; // หน้า default คือ 'monthly'

// 3. โหลด Header (HTML, CSS, Menu)
require __DIR__ . '/common_header.php';

// 4. โหลดเนื้อหาหน้า (Page Content)
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

// 5. โหลด Footer (ปิด HTML, JS)
require __DIR__ . '/common_footer.php';

?>