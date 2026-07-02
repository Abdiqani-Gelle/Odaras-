<?php
// ODARS API — PDO version — Railway compatible
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/config.php';

function ok($d=[])   { echo json_encode(array_merge(['ok'=>true],$d)); exit; }
function err($m='Khalad') { echo json_encode(['ok'=>false,'error'=>$m]); exit; }

$action = trim($_GET['action'] ?? '');
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// ── PING ──
if ($action === 'ping') {
    $pdo = db();
    $v = $pdo->query("SELECT VERSION()")->fetchColumn();
    ok(['msg'=>'ODARS API Online ✓', 'mysql'=>$v, 'time'=>date('Y-m-d H:i:s')]);
}

// ── SETUP: create all tables ──
elseif ($action === 'setup') {
    $pdo = db();
    $tables = [];

    $sqls = [
        "CREATE TABLE IF NOT EXISTS documents (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            magac         VARCHAR(255) NOT NULL,
            section       VARCHAR(100) NOT NULL,
            sannad        YEAR NOT NULL,
            nooc          VARCHAR(10) NOT NULL DEFAULT 'PDF',
            cidda_gelisay VARCHAR(100) DEFAULT 'Admin',
            taariikhda    DATE NOT NULL,
            cabbirka      VARCHAR(20) DEFAULT '—',
            xog_dheeraad  TEXT,
            la_geliyay    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sec (section),
            INDEX idx_san (sannad),
            INDEX idx_dat (la_geliyay)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS users (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            magac          VARCHAR(100) NOT NULL,
            xil            VARCHAR(100) DEFAULT 'Shaqaale',
            section        VARCHAR(100) DEFAULT '',
            password       VARCHAR(255) NOT NULL,
            role           ENUM('admin','staff') DEFAULT 'staff',
            fasax_arag     TINYINT(1) DEFAULT 1,
            fasax_geli     TINYINT(1) DEFAULT 0,
            fasax_deji     TINYINT(1) DEFAULT 0,
            fasax_tir      TINYINT(1) DEFAULT 0,
            fasax_warbixin TINYINT(1) DEFAULT 0,
            la_abuuray     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS activity_log (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            user_magac VARCHAR(100) DEFAULT 'Nidaamka',
            ficil      VARCHAR(100) DEFAULT '',
            fayl_magac VARCHAR(255) DEFAULT '',
            section    VARCHAR(100) DEFAULT '',
            waqtiga    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_waqti (waqtiga)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "INSERT IGNORE INTO users
            (id,magac,xil,section,password,role,fasax_arag,fasax_geli,fasax_deji,fasax_tir,fasax_warbixin)
         VALUES
            (1,'Agaasimaha','Agaasimaha Waaxda','Agaasimaha',MD5('admin2024'),'admin',1,1,1,1,1),
            (2,'A. Gelle','Maamulaha Nidaamka','Agaasimaha',MD5('gelle2024'),'admin',1,1,1,1,1)"
    ];

    foreach ($sqls as $sql) {
        try { $pdo->exec($sql); $tables[] = 'OK'; }
        catch (PDOException $e) { $tables[] = 'ERR: '.$e->getMessage(); }
    }
    ok(['tables'=>$tables,'msg'=>'Setup dhammaystay ✓']);
}

// ── STATS ──
elseif ($action === 'stats') {
    $pdo = db();
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'documents'")->rowCount();
        if (!$check) err("Tables ma jiraan — /api.php?action=setup fur");

        $total   = (int)$pdo->query("SELECT COUNT(*) FROM documents")->fetchColumn();
        $today   = (int)$pdo->query("SELECT COUNT(*) FROM documents WHERE DATE(la_geliyay)=CURDATE()")->fetchColumn();
        $thismon = (int)$pdo->query("SELECT COUNT(*) FROM documents WHERE MONTH(la_geliyay)=MONTH(NOW()) AND YEAR(la_geliyay)=YEAR(NOW())")->fetchColumn();

        $by_sec  = $pdo->query("SELECT section, COUNT(*) c FROM documents GROUP BY section ORDER BY c DESC")->fetchAll();
        $by_year = $pdo->query("SELECT sannad, COUNT(*) c FROM documents GROUP BY sannad ORDER BY sannad ASC")->fetchAll();
        $by_mon  = $pdo->query("SELECT MONTH(la_geliyay) m, COUNT(*) c FROM documents WHERE YEAR(la_geliyay)=YEAR(NOW()) GROUP BY m ORDER BY m")->fetchAll();
        $activity= $pdo->query("SELECT * FROM activity_log ORDER BY waqtiga DESC LIMIT 8")->fetchAll();

        ok([
            'total'=>$total,'today'=>$today,'thismon'=>$thismon,
            'storage_mb'=>0,
            'by_sec'=>$by_sec,'by_year'=>$by_year,
            'by_mon'=>$by_mon,'activity'=>$activity
        ]);
    } catch (PDOException $e) { err($e->getMessage()); }
}

// ── GET DOCS ──
elseif ($action === 'get_docs') {
    $pdo = db();
    $sec   = trim($_GET['section'] ?? '');
    $san   = trim($_GET['sannad']  ?? '');
    $nooc  = trim($_GET['nooc']    ?? '');
    $raadi = trim($_GET['raadi']   ?? '');

    $where = "WHERE 1=1"; $p = [];
    if ($sec)   { $where .= " AND section=?";   $p[] = $sec; }
    if ($san)   { $where .= " AND sannad=?";    $p[] = $san; }
    if ($nooc)  { $where .= " AND nooc=?";      $p[] = $nooc; }
    if ($raadi) {
        $where .= " AND (magac LIKE ? OR cidda_gelisay LIKE ? OR CAST(sannad AS CHAR) LIKE ?)";
        $q="%$raadi%"; $p[]=$q; $p[]=$q; $p[]=$q;
    }

    try {
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM documents $where");
        $cnt->execute($p); $total = (int)$cnt->fetchColumn();

        $stmt = $pdo->prepare("SELECT * FROM documents $where ORDER BY la_geliyay DESC LIMIT 500");
        $stmt->execute($p); $docs = $stmt->fetchAll();

        ok(['docs'=>$docs,'total'=>$total]);
    } catch (PDOException $e) { err($e->getMessage()); }
}

// ── ADD DOC ──
elseif ($action === 'add_doc' && $_SERVER['REQUEST_METHOD']==='POST') {
    $pdo  = db();
    $magac = trim($body['magac']         ?? '');
    $sec   = trim($body['section']       ?? '');
    $san   = (int)($body['sannad']       ?? 0);
    $nooc  = trim($body['nooc']          ?? 'PDF');
    $cid   = trim($body['cidda_gelisay'] ?? 'Admin');
    $cab   = trim($body['cabbirka']      ?? '—');
    $tar   = $body['taariikhda']         ?? date('Y-m-d');

    if (!$magac || !$sec || !$san) err('Buuxi goobaha loo baahan yahay');

    try {
        $s = $pdo->prepare("INSERT INTO documents (magac,section,sannad,nooc,cidda_gelisay,taariikhda,cabbirka) VALUES (?,?,?,?,?,?,?)");
        $s->execute([$magac,$sec,$san,$nooc,$cid,$tar,$cab]);
        $newId = $pdo->lastInsertId();

        $log = $pdo->prepare("INSERT INTO activity_log (user_magac,ficil,fayl_magac,section) VALUES (?,?,?,?)");
        $log->execute([$cid,'geliyay',$magac,$sec]);

        ok(['id'=>(int)$newId]);
    } catch (PDOException $e) { err($e->getMessage()); }
}

// ── DELETE DOC ──
elseif ($action === 'del_doc') {
    $pdo = db();
    $id  = (int)($_GET['id'] ?? $body['id'] ?? 0);
    if (!$id) err('ID aan jirin');

    try {
        $row = $pdo->prepare("SELECT magac,section FROM documents WHERE id=?");
        $row->execute([$id]); $doc = $row->fetch();

        $pdo->prepare("DELETE FROM documents WHERE id=?")->execute([$id]);

        if ($doc) {
            $log = $pdo->prepare("INSERT INTO activity_log (user_magac,ficil,fayl_magac,section) VALUES (?,?,?,?)");
            $log->execute(['Admin','tirtiray',$doc['magac'],$doc['section']]);
        }
        ok();
    } catch (PDOException $e) { err($e->getMessage()); }
}

// ── GET USERS ──
elseif ($action === 'get_users') {
    $pdo = db();
    try {
        $users = $pdo->query(
            "SELECT id,magac,xil,section,role,fasax_arag,fasax_geli,fasax_deji,fasax_tir,fasax_warbixin,la_abuuray
             FROM users ORDER BY FIELD(role,'admin','staff'), id ASC"
        )->fetchAll();
        ok(['users'=>$users]);
    } catch (PDOException $e) { err($e->getMessage()); }
}

// ── ADD USER ──
elseif ($action === 'add_user' && $_SERVER['REQUEST_METHOD']==='POST') {
    $pdo   = db();
    $magac = trim($body['magac']    ?? '');
    $xil   = trim($body['xil']     ?? 'Shaqaale');
    $sec   = trim($body['section'] ?? '');
    $pass  = md5($body['password'] ?? 'odars2024');
    $perms = $body['perms']        ?? [];

    if (!$magac) err('Magaca isticmaalaha geli');

    $arag = in_array('view',     $perms)?1:0;
    $geli = in_array('upload',   $perms)?1:0;
    $deji = in_array('download', $perms)?1:0;
    $tir  = in_array('delete',   $perms)?1:0;
    $war  = in_array('report',   $perms)?1:0;

    try {
        $s = $pdo->prepare(
            "INSERT INTO users (magac,xil,section,password,role,fasax_arag,fasax_geli,fasax_deji,fasax_tir,fasax_warbixin)
             VALUES (?,?,?,?,'staff',?,?,?,?,?)"
        );
        $s->execute([$magac,$xil,$sec,$pass,$arag,$geli,$deji,$tir,$war]);
        ok(['id'=>(int)$pdo->lastInsertId()]);
    } catch (PDOException $e) { err($e->getMessage()); }
}

// ── UPDATE PERMS ──
elseif ($action === 'update_perms' && $_SERVER['REQUEST_METHOD']==='POST') {
    $pdo   = db();
    $uid   = (int)($body['id']    ?? 0);
    $perms = $body['perms']       ?? [];
    if (!$uid) err('ID aan jirin');

    $arag = in_array('view',     $perms)?1:0;
    $geli = in_array('upload',   $perms)?1:0;
    $deji = in_array('download', $perms)?1:0;
    $tir  = in_array('delete',   $perms)?1:0;
    $war  = in_array('report',   $perms)?1:0;

    try {
        $s = $pdo->prepare(
            "UPDATE users SET fasax_arag=?,fasax_geli=?,fasax_deji=?,fasax_tir=?,fasax_warbixin=?
             WHERE id=? AND role='staff'"
        );
        $s->execute([$arag,$geli,$deji,$tir,$war,$uid]);
        ok();
    } catch (PDOException $e) { err($e->getMessage()); }
}

// ── DELETE USER ──
elseif ($action === 'del_user') {
    $pdo = db();
    $id  = (int)($_GET['id'] ?? $body['id'] ?? 0);
    if (!$id) err('ID aan jirin');
    try {
        $pdo->prepare("DELETE FROM users WHERE id=? AND role='staff'")->execute([$id]);
        ok();
    } catch (PDOException $e) { err($e->getMessage()); }
}

else { err('Action aan la garanayn: '.$action); }
?>
