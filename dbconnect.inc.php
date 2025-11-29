<?php
/**
 * dbconnect.inc.php
 * ----------------------------
 * ?????????????????????? + Config ????
 * *** ?????????????????????? Web Root ***
 * ----------------------------
 */

// 2 ??????????????????????? ??? (?????)
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

ini_set('max_execution_time', 300);
date_default_timezone_set('Asia/Bangkok');

// ---------------------------------------------
// ?? NAV (SQL Server)
// ---------------------------------------------
$NAV_SERVER   = "192.168.50.213,53017";
$NAV_DB       = "ITG2016";
$NAV_USER     = "dbreader";
$NAV_PASS     = "ouj8nvisylzjko@ITG"; // <-- ?????????????????????????????

try {
    $pdoNav = new PDO(
        "sqlsrv:Server=$NAV_SERVER;Database=$NAV_DB;TrustServerCertificate=yes;Encrypt=no;LoginTimeout=5",
        $NAV_USER,
        $NAV_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("<b style='color:red'>NAV connection failed:</b> " . htmlspecialchars($e->getMessage()));
}

// ---------------------------------------------
// ?? ERPNext (MariaDB/MySQL)
// ---------------------------------------------
$ERP_SERVER = "172.16.200.190";
$ERP_PORT   = "3306";
$ERP_DB     = "_1bd3e0294da19198";
$ERP_USER   = "erpnext";
$ERP_PASS   = "nui1998"; // <-- ?????????????????????????????

try {
    $pdoErp = new PDO(
        "mysql:host=$ERP_SERVER;port=$ERP_PORT;dbname=$ERP_DB;charset=utf8mb4",
        $ERP_USER,
        $ERP_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    // ??? die ??????????????????
    echo "<p style='color:orange'>?? ERPNext connection warning: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// ---------------------------------------------
// ?? CONFIG ???? (??????? PHP ???????)
// ---------------------------------------------
$NAV_SCHEMA  = 'dbo';
$NAV_COMPANY = 'IT Green Public Company Limite';

$NAV_T = [
  'SO' => "[$NAV_SCHEMA].[$NAV_COMPANY\$Sales Header]",
  'SH' => "[$NAV_SCHEMA].[$NAV_COMPANY\$Sales Shipment Header]",
  'PO' => "[$NAV_SCHEMA].[$NAV_COMPANY\$Purchase Header]",
  'PR' => "[$NAV_SCHEMA].[$NAV_COMPANY\$Purch_ Rcpt_ Header]",
  'SP' => "[$NAV_SCHEMA].[$NAV_COMPANY\$Salesperson_Purchaser]",
  'SL' => "[$NAV_SCHEMA].[$NAV_COMPANY\$Sales Line]", // <--- !!! ผมเพิ่มบรรทัดนี้ให้แล้ว !!!
];

// ---------------------------------------------
// ?? ???????? Helpers ?????????????
// ---------------------------------------------
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

?>