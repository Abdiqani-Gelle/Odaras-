<?php
// ═══════════════════════════════════════════════════════
// ODARS Config — Online Version
// Platform: Railway.app / Any PHP Host
// ═══════════════════════════════════════════════════════

// Railway.app waxay environment variables u isticmaashaa
// Haddaad isticmaalayso shared hosting, beddel qiimayaasha hoose

$db_host = getenv('MYSQLHOST')     ?: getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('MYSQLUSER')     ?: getenv('DB_USER') ?: 'root';
$db_pass = getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '';
$db_name = getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'odars_db';
$db_port = getenv('MYSQLPORT')     ?: 3306;

function db() {
    global $db_host, $db_user, $db_pass, $db_name, $db_port;
    static $conn = null;
    if ($conn && $conn->ping()) return $conn;
    
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, (int)$db_port);
    $conn->set_charset('utf8mb4');
    
    if ($conn->connect_error) {
        http_response_code(500);
        die(json_encode([
            'ok'    => false,
            'error' => 'DB xidida fashilantay: ' . $conn->connect_error
        ]));
    }
    $conn->report_mode = MYSQLI_REPORT_OFF; // avoid throwing, we check errors manually
    return $conn;
}
