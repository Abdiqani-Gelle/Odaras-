<?php
// ODARS Config — Railway compatible
// Railway uses MYSQL_DATABASE (not MYSQLDATABASE)

$db_host = getenv('MYSQLHOST')      ?: getenv('DB_HOST') ?: 'localhost';
$db_user = getenv('MYSQLUSER')      ?: getenv('DB_USER') ?: 'root';
$db_pass = getenv('MYSQLPASSWORD')  ?: getenv('DB_PASS') ?: '';
$db_port = getenv('MYSQLPORT')      ?: '3306';

// Railway uses MYSQL_DATABASE — check both
$db_name = getenv('MYSQL_DATABASE') 
        ?: getenv('MYSQLDATABASE')  
        ?: getenv('DB_NAME')        
        ?: 'railway';   // Railway default database name

function db() {
    global $db_host, $db_user, $db_pass, $db_name, $db_port;
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        die(json_encode(['ok'=>false,'error'=>'DB fashilantay: '.$e->getMessage()]));
    }
}
