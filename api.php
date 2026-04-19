<?php
/**
 * SmoldPaper v3.5.0 — api.php
 * ZERO-LOCK ARCHITECTURE
 */

define('DB_FILE',         __DIR__ . '/data/smoldpaper.sqlite');
define('TEXTS_FILE',      __DIR__ . '/data/texts.json');
define('ADMIN_HASH_FILE', __DIR__ . '/data/admin.hash');
define('CLEANUP_LOCK',    __DIR__ . '/data/.cleanup_lock');
define('CHAT_IDLE_TTL',   3600); // Room destruction after 1 hour idle
define('CHAT_WAIT_TTL',   7200);
define('NOTE_DEFAULT_TTL', 86400);
define('QCHAT_DEAD_TTL',  86400); // Dead quick-chat entries live 24h
define('TYPING_TTL',      2);

define('MAX_PAYLOAD_SIZE', 250000); 
define('RATE_LIMIT_DIR',  __DIR__ . '/data/rates');
define('RATE_LIMIT_MAX',  120); // max requests per minute per IP
define('RATE_LIMIT_WINDOW', 60);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

function check_rate_limit(): void {
    $dir = RATE_LIMIT_DIR;
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $file = $dir . '/' . md5($ip) . '.rate';
    $now = time();
    
    // Clean old rate files roughly every ~100 requests
    if (mt_rand(1, 100) === 1) {
        foreach (glob($dir . '/*.rate') as $f) {
            if ($now - filemtime($f) > RATE_LIMIT_WINDOW * 2) @unlink($f);
        }
    }
    
    $count = 0; $window_start = $now - RATE_LIMIT_WINDOW;
    if (file_exists($file)) {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $lines = array_filter($lines, function($ts) use ($window_start) { return (int)$ts > $window_start; });
        $count = count($lines);
    } else {
        $lines = [];
    }
    
    if ($count >= RATE_LIMIT_MAX) {
        json_out(429, ['error' => 'Too many requests. Try again later.']);
    }
    
    $lines[] = $now;
    @file_put_contents($file, implode("\n", $lines) . "\n", LOCK_EX);
}

function json_out(int $code, array $d): void {
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}
function get_input(): array {
    $d = json_decode(file_get_contents('php://input'), true);
    return is_array($d) ? $d : [];
}
function need(array $in, array $f): void {
    foreach ($f as $k) if (!isset($in[$k]) || $in[$k] === '') json_out(400, ['error' => "Missing: $k"]);
}

function init_db(): PDO {
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) mkdir($dir, 0750, true);
    if (!file_exists($dir . '/.htaccess'))
        file_put_contents($dir . '/.htaccess', "Deny from all\n");
    $rootHt = __DIR__ . '/.htaccess';
    if (!file_exists($rootHt))
        file_put_contents($rootHt,
            "Options -Indexes\n<FilesMatch \"\\.(sqlite|db|hash|json|lock)$\">\n    Deny from all\n</FilesMatch>\n");
    if (!file_exists(ADMIN_HASH_FILE))
        file_put_contents(ADMIN_HASH_FILE, password_hash('smoldpaper', PASSWORD_DEFAULT));

    $db = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('PRAGMA journal_mode=WAL; PRAGMA busy_timeout=3000; PRAGMA foreign_keys=ON;');
    $db->exec('CREATE TABLE IF NOT EXISTS entries (
        hash_id TEXT PRIMARY KEY, type TEXT NOT NULL DEFAULT "note",
        content TEXT NOT NULL DEFAULT "", users_count INTEGER NOT NULL DEFAULT 0,
        ttl INTEGER NOT NULL DEFAULT 86400, burn_on_read INTEGER NOT NULL DEFAULT 1,
        typing_1 INTEGER NOT NULL DEFAULT 0, typing_2 INTEGER NOT NULL DEFAULT 0,
        created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL
    )');
    $db->exec('CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT, room_id TEXT NOT NULL,
        content TEXT NOT NULL, sender INTEGER NOT NULL DEFAULT 1,
        created_at INTEGER NOT NULL,
        FOREIGN KEY(room_id) REFERENCES entries(hash_id) ON DELETE CASCADE
    )');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_msg ON messages(room_id,id)');
    return $db;
}

function maybe_cleanup(PDO $db): void {
    $lock = __DIR__ . '/data/.cleanup_lock';
    if (file_exists($lock) && (time() - filemtime($lock)) < 60) return;
    @touch($lock);
    
    $now = time();
    $db->exec("DELETE FROM entries WHERE type='chat' AND ($now - updated_at) > " . CHAT_IDLE_TTL);
    $db->exec("DELETE FROM entries WHERE type='chat' AND users_count < 2 AND ($now - created_at) > " . CHAT_WAIT_TTL);
    $db->exec("DELETE FROM entries WHERE type='note' AND burn_on_read=0 AND ($now - created_at) > ttl");
    $db->exec("DELETE FROM entries WHERE type='note' AND burn_on_read=1 AND ($now - created_at) > 86400");
    $db->exec("DELETE FROM entries WHERE type='qchat' AND users_count<3 AND ($now - updated_at) > " . CHAT_IDLE_TTL);
    $db->exec("DELETE FROM entries WHERE type='qchat' AND users_count=3 AND ($now - updated_at) > " . QCHAT_DEAD_TTL);
    $db->exec("DELETE FROM entries WHERE type='qchat' AND users_count<2 AND ($now - created_at) > " . CHAT_WAIT_TTL);
}

function create_note(PDO $db): void {
    $in = get_input(); need($in, ['content']);
    
    if (strlen($in['content']) > MAX_PAYLOAD_SIZE) {
        json_out(413, ['error' => 'Payload too large (Max 250KB)']);
    }

    $id = bin2hex(random_bytes(16)); $now = time();
    $burn = isset($in['burn_on_read']) ? (int)$in['burn_on_read'] : 1;
    $ttl = NOTE_DEFAULT_TTL;
    if (!$burn && isset($in['ttl'])) {
        $ok = [3600, 21600, 43200, 86400];
        $ttl = in_array((int)$in['ttl'], $ok) ? (int)$in['ttl'] : NOTE_DEFAULT_TTL;
    }
    $db->prepare('INSERT INTO entries (hash_id,type,content,burn_on_read,ttl,created_at,updated_at) VALUES(?,?,?,?,?,?,?)')
       ->execute([$id,'note',$in['content'],$burn,$ttl,$now,$now]);
    json_out(201, ['id'=>$id,'expires'=>$burn?null:$now+$ttl,'burn'=>(bool)$burn]);
}

function read_note(PDO $db): void {
    $id = $_GET['id'] ?? ''; if (!$id) json_out(400, ['error'=>'Missing id']);
    $s = $db->prepare('SELECT * FROM entries WHERE hash_id=? AND type="note"'); $s->execute([$id]);
    $e = $s->fetch(); if (!$e) json_out(404, ['error'=>'Not found']);
    $r = ['content'=>$e['content'],'created_at'=>(int)$e['created_at'],'burn'=>(bool)$e['burn_on_read']];
    if ((int)$e['burn_on_read']) $db->prepare('DELETE FROM entries WHERE hash_id=?')->execute([$id]);
    json_out(200, $r);
}

function check_room(PDO $db): void {
    $in = get_input(); need($in, ['hash_id']);
    $s = $db->prepare('SELECT users_count FROM entries WHERE hash_id=? AND type="chat"');
    $s->execute([$in['hash_id']]); $r = $s->fetch();
    if (!$r || (int)$r['users_count'] >= 2) json_out(200, ['status'=>'available']);
    json_out(200, ['status'=>'waiting']);
}

function create_room(PDO $db): void {
    $in = get_input(); need($in, ['hash_id']);
    $h = $in['hash_id']; $now = time();
    $db->exec('BEGIN IMMEDIATE');
    try {
        $s = $db->prepare('SELECT users_count FROM entries WHERE hash_id=? AND type="chat"');
        $s->execute([$h]); $r = $s->fetch();
        if ($r && (int)$r['users_count'] >= 2) { $db->exec('COMMIT'); json_out(200, ['status'=>'full']); return; }
        if ($r) {
            $db->prepare('UPDATE entries SET users_count=2, updated_at=? WHERE hash_id=?')->execute([$now,$h]);
            $db->exec('COMMIT');
            json_out(200, ['status'=>'joined','user'=>2]); return;
        }
        $db->prepare('INSERT INTO entries (hash_id,type,content,users_count,created_at,updated_at) VALUES(?,"chat","",1,?,?)')
           ->execute([$h,$now,$now]);
        $db->exec('COMMIT');
        json_out(201, ['status'=>'created','user'=>1]);
    } catch (\Exception $e) {
        $db->exec('ROLLBACK');
        json_out(500, ['error'=>'Server error']);
    }
}

function send_message(PDO $db): void {
    $in = get_input(); need($in, ['room_id','content','sender']);
    
    if (strlen($in['content']) > MAX_PAYLOAD_SIZE) {
        json_out(413, ['error' => 'Payload too large (Max 250KB)']);
    }
    
    $sender = (int)$in['sender'];
    if ($sender !== 1 && $sender !== 2) {
        json_out(400, ['error' => 'Invalid sender']);
    }

    $now = time();
    $s = $db->prepare('SELECT users_count FROM entries WHERE hash_id=? AND type IN ("chat","qchat")');
    $s->execute([$in['room_id']]); $room = $s->fetch();
    if (!$room) json_out(404, ['error'=>'Room not found']);
    if ((int)$room['users_count'] >= 3) json_out(403, ['error'=>'Room closed']);
    if ($sender > (int)$room['users_count']) json_out(403, ['error'=>'Not joined']);
    
    $db->prepare('INSERT INTO messages (room_id,content,sender,created_at) VALUES(?,?,?,?)')
       ->execute([$in['room_id'],$in['content'],$sender,$now]);
    
    $db->prepare('UPDATE entries SET updated_at=? WHERE hash_id=?')->execute([$now,$in['room_id']]);
    json_out(201, ['id'=>(int)$db->lastInsertId(),'created_at'=>$now]);
}

function poll_messages(PDO $db): void {
    $rid = $_GET['room_id'] ?? ''; $after = (int)($_GET['after'] ?? 0);
    if (!$rid) json_out(400, ['error'=>'Missing room_id']);
    
    $s = $db->prepare('SELECT * FROM entries WHERE hash_id=? AND type IN ("chat","qchat")');
    $s->execute([$rid]); 
    $room = $s->fetch();
    
    if (!$room) json_out(404, ['error'=>'Room destroyed']);
    if ((int)$room['users_count'] >= 3) json_out(410, ['error'=>'Room closed']);
    
    // POLLING 100% READ-ONLY! 
    // No UPDATE here, database is never locked by polling.

    $s = $db->prepare('SELECT id,content,sender,created_at FROM messages WHERE room_id=? AND id>? ORDER BY id');
    $s->execute([$rid,$after]);
    
    json_out(200, [
        'messages'=>$s->fetchAll(),
        'users_count'=>(int)$room['users_count'],
        'typing'=>[], // Disabled
        'created_at'=>(int)$room['created_at'],
        'updated_at'=>(int)$room['updated_at'],
        'idle_ttl'=>CHAT_IDLE_TTL
    ]);
}

function set_typing(PDO $db): void {
    // Stub function kept for compatibility. Does not write to DB.
    json_out(200, ['ok'=>true]);
}

function destroy_room(PDO $db): void {
    $in = get_input(); need($in, ['room_id']);
    $s = $db->prepare('SELECT type FROM entries WHERE hash_id=? AND type IN ("chat","qchat")');
    $s->execute([$in['room_id']]); $room = $s->fetch();
    if (!$room) { json_out(200, ['status'=>'destroyed']); return; }
    if ($room['type'] === 'qchat') {
        // Mark as dead (users_count=3), keep entry for fake redirect
        $now = time();
        $db->prepare('DELETE FROM messages WHERE room_id=?')->execute([$in['room_id']]);
        $db->prepare('UPDATE entries SET users_count=3, content="", updated_at=? WHERE hash_id=?')->execute([$now, $in['room_id']]);
    } else {
        $db->prepare('DELETE FROM entries WHERE hash_id=?')->execute([$in['room_id']]);
    }
    json_out(200, ['status'=>'destroyed']);
}

function create_qchat(PDO $db): void {
    $id = bin2hex(random_bytes(16)); $now = time();
    $db->prepare('INSERT INTO entries (hash_id,type,content,users_count,created_at,updated_at) VALUES(?,"qchat","",1,?,?)')
       ->execute([$id,$now,$now]);
    json_out(201, ['id'=>$id,'status'=>'created','user'=>1]);
}

function join_qchat(PDO $db): void {
    $in = get_input(); need($in, ['room_id']);
    $id = $in['room_id'];
    $db->exec('BEGIN IMMEDIATE');
    try {
        $s = $db->prepare('SELECT users_count FROM entries WHERE hash_id=? AND type="qchat"');
        $s->execute([$id]); $r = $s->fetch();
        if (!$r) { $db->exec('COMMIT'); json_out(404, ['error'=>'expired']); return; }
        if ((int)$r['users_count'] >= 2) { $db->exec('COMMIT'); json_out(200, ['status'=>'expired']); return; }
        $now = time();
        $db->prepare('UPDATE entries SET users_count=2, updated_at=? WHERE hash_id=?')->execute([$now,$id]);
        $db->exec('COMMIT');
        json_out(200, ['status'=>'joined','user'=>2]);
    } catch (\Exception $e) {
        $db->exec('ROLLBACK');
        json_out(500, ['error'=>'Server error']);
    }
}

function check_qchat(PDO $db): void {
    $id = $_GET['id'] ?? ''; if (!$id) json_out(400, ['error'=>'Missing id']);
    $s = $db->prepare('SELECT users_count FROM entries WHERE hash_id=? AND type="qchat"');
    $s->execute([$id]); $r = $s->fetch();
    if (!$r || (int)$r['users_count'] >= 2) { json_out(200, ['status'=>'expired']); return; }
    json_out(200, ['status'=>'waiting']);
}

function admin_auth(): bool {
    $in = get_input(); $pw = $in['admin_password'] ?? '';
    if (!$pw || !file_exists(ADMIN_HASH_FILE)) return false;
    return password_verify($pw, trim(file_get_contents(ADMIN_HASH_FILE)));
}

function get_texts(): void {
    if (!file_exists(TEXTS_FILE)) { json_out(200, ['texts'=>new \stdClass()]); return; }
    json_out(200, ['texts'=>json_decode(file_get_contents(TEXTS_FILE),true)?:new \stdClass()]);
}

function save_texts(): void {
    if (!admin_auth()) json_out(403, ['error'=>'Unauthorized']);
    $in = get_input(); if (!isset($in['texts'])) json_out(400, ['error'=>'Missing texts']);
    file_put_contents(TEXTS_FILE, json_encode($in['texts'], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));
    json_out(200, ['status'=>'saved']);
}

function change_pw(): void {
    if (!admin_auth()) json_out(403, ['error'=>'Unauthorized']);
    $np = get_input()['new_password'] ?? '';
    if (strlen($np) < 6) json_out(400, ['error'=>'Min 6 chars']);
    file_put_contents(ADMIN_HASH_FILE, password_hash($np, PASSWORD_DEFAULT));
    json_out(200, ['status'=>'ok']);
}

function admin_login(): void {
    if (!admin_auth()) json_out(403, ['error'=>'Wrong password']);
    json_out(200, ['status'=>'ok']);
}

$db = init_db();
maybe_cleanup($db);
check_rate_limit();
$routes = [
    'POST:create_note'=>'create_note','GET:read_note'=>'read_note',
    'POST:check_room'=>'check_room','POST:create_room'=>'create_room',
    'POST:send_message'=>'send_message','GET:poll_messages'=>'poll_messages',
    'POST:typing'=>'set_typing','POST:destroy_room'=>'destroy_room',
    'POST:create_qchat'=>'create_qchat','POST:join_qchat'=>'join_qchat',
    'GET:check_qchat'=>'check_qchat',
    'GET:get_texts'=>'get_texts','POST:save_texts'=>'save_texts',
    'POST:change_password'=>'change_pw','POST:admin_login'=>'admin_login',
];
$key = $_SERVER['REQUEST_METHOD'] . ':' . ($_GET['action'] ?? '');
if (isset($routes[$key])) $routes[$key]($db);
else json_out(404, ['error'=>'Unknown']);