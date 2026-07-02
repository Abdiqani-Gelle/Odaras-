<?php
// ═══════════════════════════════════════════════════════
// ODARS API — Online Version
// Waaxda Arimaha Bulshada, Hargeysa
// ═══════════════════════════════════════════════════════

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/config.php';

function ok($data = [])      { echo json_encode(array_merge(['ok'=>true], $data)); exit; }
function err($msg = 'Khalad'){ echo json_encode(['ok'=>false,'error'=>$msg]);       exit; }

$action = trim($_GET['action'] ?? '');
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// ═══ PING / Health check ═══
if ($action === 'ping') {
    $d = db();
    $v = $d->query("SELECT VERSION() v")->fetch_row()[0];
    ok([
        'msg'     => 'ODARS API Online ✓',
        'db'      => $d->select_db(getenv('MYSQLDATABASE') ?: 'odars_db') ? 'connected' : 'ok',
        'mysql'   => $v,
        'time'    => date('Y-m-d H:i:s'),
        'server'  => gethostname()
    ]);
}

// ═══ SETUP — First run: create tables ═══
elseif ($action === 'setup') {
    $d = db();
    $sqls = [
        "CREATE TABLE IF NOT EXISTS documents (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            magac         VARCHAR(255) NOT NULL,
            section       VARCHAR(100) NOT NULL,
            sannad        YEAR NOT NULL,
            nooc          VARCHAR(10)  NOT NULL DEFAULT 'PDF',
            cidda_gelisay VARCHAR(100) DEFAULT 'Admin',
            taariikhda    DATE NOT NULL,
            cabbirka      VARCHAR(20)  DEFAULT '—',
            xog_dheeraad  TEXT,
            la_geliyay    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_section (section),
            INDEX idx_sannad  (sannad),
            INDEX idx_date    (la_geliyay)
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

    $done = [];
    foreach ($sqls as $sql) {
        if ($d->query($sql)) $done[] = 'OK';
        else $done[] = 'ERR: ' . $d->error;
    }
    ok(['tables' => $done, 'msg' => 'Setup dhammaystay ✓']);
}

// ═══ STATS ═══
elseif ($action === 'stats') {
    $d = db();

    $check = @$d->query("SHOW TABLES LIKE 'documents'");
    if (!$check || $check->num_rows === 0) {
        err("Tables ma jiraan — riix Setup Database (table 'documents' ma jirto)");
    }

    $total   = (int)$d->query("SELECT COUNT(*) FROM documents")->fetch_row()[0];
    $today   = (int)$d->query("SELECT COUNT(*) FROM documents WHERE DATE(la_geliyay)=CURDATE()")->fetch_row()[0];
    $thismon = (int)$d->query("SELECT COUNT(*) FROM documents WHERE MONTH(la_geliyay)=MONTH(NOW()) AND YEAR(la_geliyay)=YEAR(NOW())")->fetch_row()[0];

    $mbRow = $d->query("SELECT COALESCE(SUM(
        CASE
          WHEN cabbirka REGEXP '^[0-9]+(\\.[0-9]+)? GB$'
               THEN CAST(REPLACE(cabbirka,' GB','') AS DECIMAL(10,2))*1024
          WHEN cabbirka REGEXP '^[0-9]+(\\.[0-9]+)? MB$'
               THEN CAST(REPLACE(cabbirka,' MB','') AS DECIMAL(10,2))
          ELSE 1.5
        END
    ),0) FROM documents");
    $storage_mb = round((float)$mbRow->fetch_row()[0], 2);

    $by_sec  = $d->query("SELECT section, COUNT(*) c FROM documents GROUP BY section ORDER BY c DESC")->fetch_all(MYSQLI_ASSOC);
    $by_year = $d->query("SELECT sannad, COUNT(*) c FROM documents GROUP BY sannad ORDER BY sannad ASC")->fetch_all(MYSQLI_ASSOC);
    $by_mon  = $d->query("SELECT MONTH(la_geliyay) m, COUNT(*) c FROM documents WHERE YEAR(la_geliyay)=YEAR(NOW()) GROUP BY m ORDER BY m")->fetch_all(MYSQLI_ASSOC);
    $activity= $d->query("SELECT * FROM activity_log ORDER BY waqtiga DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);

    ok([
        'total'      => $total,
        'today'      => $today,
        'thismon'    => $thismon,
        'storage_mb' => $storage_mb,
        'by_sec'     => $by_sec,
        'by_year'    => $by_year,
        'by_mon'     => $by_mon,
        'activity'   => $activity,
    ]);
}

// ═══ GET DOCS ═══
elseif ($action === 'get_docs') {
    $d       = db();
    $section = trim($_GET['section'] ?? '');
    $sannad  = trim($_GET['sannad']  ?? '');
    $nooc    = trim($_GET['nooc']    ?? '');
    $raadi   = trim($_GET['raadi']   ?? '');

    $where = "WHERE 1=1"; $p = []; $t = '';
    if ($section) { $where .= " AND section=?";   $p[]=$section; $t.='s'; }
    if ($sannad)  { $where .= " AND sannad=?";    $p[]=$sannad;  $t.='s'; }
    if ($nooc)    { $where .= " AND nooc=?";      $p[]=$nooc;    $t.='s'; }
    if ($raadi)   {
        $where .= " AND (magac LIKE ? OR cidda_gelisay LIKE ? OR CAST(sannad AS CHAR) LIKE ?)";
        $q="%$raadi%"; $p[]=$q; $p[]=$q; $p[]=$q; $t.='sss';
    }

    $cnt = $d->prepare("SELECT COUNT(*) FROM documents $where");
    if ($t) $cnt->bind_param($t, ...$p);
    $cnt->execute();
    $total = (int)$cnt->get_result()->fetch_row()[0];

    $stmt = $d->prepare("SELECT * FROM documents $where ORDER BY la_geliyay DESC LIMIT 500");
    if ($t) $stmt->bind_param($t, ...$p);
    $stmt->execute();
    $docs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    ok(['docs' => $docs, 'total' => $total]);
}

// ═══ ADD DOC ═══
elseif ($action === 'add_doc' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $d     = db();
    $magac = trim($body['magac']         ?? '');
    $sec   = trim($body['section']       ?? '');
    $san   = (int)($body['sannad']       ?? 0);
    $nooc  = trim($body['nooc']          ?? 'PDF');
    $cid   = trim($body['cidda_gelisay'] ?? 'Admin');
    $cab   = trim($body['cabbirka']      ?? '—');
    $tar   = $body['taariikhda']         ?? date('Y-m-d');

    if (!$magac || !$sec || !$san) err('Buuxi goobaha loo baahan yahay');

    $s = $d->prepare("INSERT INTO documents (magac,section,sannad,nooc,cidda_gelisay,taariikhda,cabbirka) VALUES (?,?,?,?,?,?,?)");
    $s->bind_param('ssissss', $magac, $sec, $san, $nooc, $cid, $tar, $cab);
    $s->execute();
    $newId = $d->insert_id;

    $log = $d->prepare("INSERT INTO activity_log (user_magac,ficil,fayl_magac,section) VALUES (?,?,?,?)");
    $f = 'geliyay';
    $log->bind_param('ssss', $cid, $f, $magac, $sec);
    $log->execute();

    ok(['id' => $newId]);
}

// ═══ DELETE DOC ═══
elseif ($action === 'del_doc') {
    $d  = db();
    $id = (int)($_GET['id'] ?? $body['id'] ?? 0);
    if (!$id) err('ID aan jirin');

    $row = $d->query("SELECT magac,section FROM documents WHERE id=$id")->fetch_assoc();
    if ($row) {
        $d->query("DELETE FROM documents WHERE id=$id");
        $log = $d->prepare("INSERT INTO activity_log (user_magac,ficil,fayl_magac,section) VALUES (?,?,?,?)");
        $u = 'Admin'; $f = 'tirtiray';
        $log->bind_param('ssss', $u, $f, $row['magac'], $row['section']);
        $log->execute();
    }
    ok();
}

// ═══ GET USERS ═══
elseif ($action === 'get_users') {
    $users = db()->query(
        "SELECT id,magac,xil,section,role,
                fasax_arag,fasax_geli,fasax_deji,fasax_tir,fasax_warbixin,la_abuuray
         FROM users
         ORDER BY FIELD(role,'admin','staff'), id ASC"
    )->fetch_all(MYSQLI_ASSOC);
    ok(['users' => $users]);
}

// ═══ ADD USER ═══
elseif ($action === 'add_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $d     = db();
    $magac = trim($body['magac']    ?? '');
    $xil   = trim($body['xil']     ?? 'Shaqaale');
    $sec   = trim($body['section'] ?? '');
    $pass  = md5($body['password'] ?? 'odars2024');
    $perms = $body['perms']        ?? [];

    if (!$magac) err('Magaca isticmaalaha geli');

    $arag = in_array('view',     $perms) ? 1 : 0;
    $geli = in_array('upload',   $perms) ? 1 : 0;
    $deji = in_array('download', $perms) ? 1 : 0;
    $tir  = in_array('delete',   $perms) ? 1 : 0;
    $war  = in_array('report',   $perms) ? 1 : 0;

    $s = $d->prepare(
        "INSERT INTO users (magac,xil,section,password,role,fasax_arag,fasax_geli,fasax_deji,fasax_tir,fasax_warbixin)
         VALUES (?,?,?,?,'staff',?,?,?,?,?)"
    );
    $s->bind_param('ssssiiiii', $magac, $xil, $sec, $pass, $arag, $geli, $deji, $tir, $war);
    $s->execute();
    ok(['id' => $d->insert_id]);
}

// ═══ UPDATE PERMS ═══
elseif ($action === 'update_perms' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $d     = db();
    $uid   = (int)($body['id']    ?? 0);
    $perms = $body['perms']       ?? [];
    if (!$uid) err('ID aan jirin');

    $arag = in_array('view',     $perms) ? 1 : 0;
    $geli = in_array('upload',   $perms) ? 1 : 0;
    $deji = in_array('download', $perms) ? 1 : 0;
    $tir  = in_array('delete',   $perms) ? 1 : 0;
    $war  = in_array('report',   $perms) ? 1 : 0;

    $s = $d->prepare(
        "UPDATE users
         SET fasax_arag=?,fasax_geli=?,fasax_deji=?,fasax_tir=?,fasax_warbixin=?
         WHERE id=? AND role='staff'"
    );
    $s->bind_param('iiiiii', $arag, $geli, $deji, $tir, $war, $uid);
    $s->execute();
    ok();
}

// ═══ DELETE USER ═══
elseif ($action === 'del_user') {
    $d  = db();
    $id = (int)($_GET['id'] ?? $body['id'] ?? 0);
    if (!$id) err('ID aan jirin');
    $d->query("DELETE FROM users WHERE id=$id AND role='staff'");
    ok();
}

else {
    err('Action aan la garanayn: ' . $action);
}
?>
