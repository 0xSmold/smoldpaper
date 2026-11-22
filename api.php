<?php
/**
 * SmoldPaper v4.0.0 — api.php
 * Free edition: https://github.com/0xSmold/smoldpaper
 *
 * Also works as a library for admin.php: define('SP_LIB', true) before require,
 * then everything below is loaded but the router is not run.
 */

ini_set('display_errors', '0');

define('SP_VERSION',  '4.0.0');
define('SP_PROTOCOL', 3);          // bumped when index.html and api.php must change together

// ─── PATHS ──────────────────────────────────────────────
// Service files live in data/ as *.php whose first line is a PHP "exit" statement:
// even where .htaccess does not work (nginx, AllowOverride None) they show nothing.
// The database gets a random name (data/db_<random>.sqlite).

define('DATA_DIR',       __DIR__ . '/data');
define('RATE_LIMIT_DIR', DATA_DIR . '/rates');
define('SVC_HEAD',       "<?php exit; ?" . ">\n"); // written in two parts so this file never contains a closing tag
define('LAYOUT_MARK',    DATA_DIR . '/.layout2');
define('CLEANUP_LOCK',   DATA_DIR . '/.cleanup_lock');
define('MIGRATE_LOCK',   DATA_DIR . '/.migrate_lock');
define('SCHEMA_VERSION', 4);

// ─── LIFETIMES ──────────────────────────────────────────
define('CHAT_WAIT_TTL',    7200);
define('NOTE_DEFAULT_TTL', 86400);
define('QCHAT_DEAD_TTL',   86400);
define('PRESENCE_TTL',     90);       // a chat member without a poll for this long counts as gone
define('SEEN_STEP',        20);       // last_seen is written at most this often (fewer database writes)
define('ROOM_FREE_FAILS',  5);        // wrong chat passwords in a row before newcomers have to wait
define('ROOM_LOCK_STEPS',  [60, 300, 900]); // the waits after that, seconds

// ─── LIMITS ─────────────────────────────────────────────
define('MAX_PAYLOAD_SIZE', 250000);   // one chat message (base64)
define('MAX_NOTE_SIZE',    300000);   // note content: base64 of iv + encrypted payload (text up to 60 000 chars + file list)
define('MAX_JSON_BODY',    1048576);
define('RATE_LIMIT_WINDOW', 60);
define('RATE_SALT_TTL',    3600);
define('RATE_BUCKETS', [
    'main'  => 120,   // everything else
    'poll'  => 600,   // chat polling: several people behind one router
    'write' => 30,    // creating notes and rooms, joining
]);
define('TIMER_CHOICES', [300, 900, 3600, 21600, 43200, 86400]);
define('TEXT_LANGS',    ['en', 'ru', 'de', 'fr', 'es']);

// ─── ADMIN PROTECTION ───────────────────────────────────
define('ADMIN_COOKIE',       'sp_admin');
define('ADMIN_OTP_TTL',      300);    // 5 minutes to enter the code after the password
define('ADMIN_IDLE_TTL',     1800);   // 30 minutes without activity
define('ADMIN_MAX_TTL',      28800);  // 8 hours in total
define('ADMIN_MAX_FAILS',    5);      // per address (anonymised, see admin_lock_key)
define('ADMIN_LOCK_STEPS',   [60, 300, 900, 3600]);
define('ADMIN_GLOBAL_MAX_FAILS', 100); // all addresses together, within ADMIN_GLOBAL_WINDOW
define('ADMIN_GLOBAL_WINDOW',    3600);
define('ADMIN_GLOBAL_LOCK',      900);
define('ADMIN_SESSION_MAX_BAD',  5);   // wrong passwords/codes inside one open session
define('ADMIN_PW_MIN',       8);
define('ADMIN_PW_MAX_BYTES', 72);     // bcrypt ignores everything after 72 bytes
define('WEAK_PW',            'smoldpaper');
define('TOTP_PERIOD',        30);
define('TOTP_DIGITS',        6);
define('TOTP_WINDOW',        1);      // accept the neighbour 30-second steps
define('TOTP_DRIFT_STEPS',   10);     // clock diagnostics: look +-5 minutes
define('TOTP_PENDING_TTL',   900);    // 15 minutes to confirm a new secret
define('TOTP_CHECK_MAX',     5);      // clock checks per admin session
define('TOTP_BACKUP_COUNT',  8);
define('TOTP_BACKUP_ABC',    'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'); // no 0/O, 1/I

define('DEFAULT_CONFIG', [
    'app_name'          => 'SmoldPaper',
    'max_attempts'      => 5,
    'chat_idle_minutes' => 60,
]);

// ─── UTILITIES ──────────────────────────────────────────

function json_out(int $code, array $d): void {
    if (!headers_sent()) http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function get_input(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > MAX_JSON_BODY) json_out(413, ['error' => 'Payload too large']);
    $raw = file_get_contents('php://input', false, null, 0, MAX_JSON_BODY + 1);
    if (is_string($raw) && strlen($raw) > MAX_JSON_BODY) json_out(413, ['error' => 'Payload too large']);
    $d = json_decode((string)$raw, true);
    $cache = is_array($d) ? $d : [];
    return $cache;
}

// Required fields: a non-empty string or a number (arrays and objects are refused)
function need(array $in, array $fields): void {
    foreach ($fields as $k) {
        $v = $in[$k] ?? null;
        $ok = (is_string($v) && $v !== '') || is_int($v) || is_float($v);
        if (!$ok) json_out(400, ['error' => "Missing: $k"]);
    }
}

function in_str(array $in, string $k): string {
    return is_string($in[$k] ?? null) ? $in[$k] : '';
}

function valid_id($v, array $lens): bool {
    return is_string($v) && in_array(strlen($v), $lens, true) && preg_match('/^[a-f0-9]+$/', $v) === 1;
}

function need_id($v, array $lens): void {
    if (!valid_id($v, $lens)) json_out(400, ['error' => 'Bad id']);
}

// Runs $fn inside BEGIN IMMEDIATE; rolls back and rethrows on any error
function tx(PDO $db, callable $fn) {
    $db->exec('BEGIN IMMEDIATE');
    try {
        $r = $fn();
        $db->exec('COMMIT');
        return $r;
    } catch (Throwable $e) {
        try { $db->exec('ROLLBACK'); } catch (Throwable $e2) {}
        throw $e;
    }
}

// Address used only for rate limiting: IPv4 as is, IPv6 cut to its /64 network
function client_net(): string {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $bin = @inet_pton($ip);
    if ($bin !== false && strlen($bin) === 16) {
        if (substr($bin, 0, 12) === "\0\0\0\0\0\0\0\0\0\0\xff\xff") return (string)inet_ntop(substr($bin, 12));
        return bin2hex(substr($bin, 0, 8)) . '::/64';
    }
    return $ip !== '' ? $ip : 'unknown';
}


// ─── SERVICE FILES (data/*.php) ─────────────────────────

function svc_path(string $name): string { return DATA_DIR . '/' . $name . '.php'; }

// Content without the protective first line, or null if the file does not exist
function svc_read(string $name): ?string {
    $f = svc_path($name);
    if (!is_file($f)) return null;
    $c = @file_get_contents($f);
    if ($c === false) return '';
    $h = strlen(SVC_HEAD);
    return strncmp($c, SVC_HEAD, $h) === 0 ? substr($c, $h) : $c;
}

// Atomic write: temporary file + rename, the result is checked
function svc_write(string $name, string $data): bool {
    $f = svc_path($name);
    $tmp = DATA_DIR . '/.tmp_' . bin2hex(random_bytes(6)) . '.php';
    $payload = SVC_HEAD . $data;
    $n = @file_put_contents($tmp, $payload);
    if ($n !== strlen($payload)) { @unlink($tmp); return false; }
    if (!@rename($tmp, $f)) { @unlink($tmp); return false; }
    return true;
}

function svc_delete(string $name): bool {
    $f = svc_path($name);
    return !is_file($f) || @unlink($f);
}

function svc_json(string $name): ?array {
    $raw = svc_read($name);
    if ($raw === null) return null;
    $d = json_decode($raw, true);
    return is_array($d) ? $d : null;
}

function load_config(): array {
    return DEFAULT_CONFIG;
}

// ─── FOLDER PROTECTION AND FIRST-RUN MIGRATION ──────────

define('DATA_HTACCESS',
    "# SmoldPaper: no web access to this folder\n"
  . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
  . "<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n");

define('ROOT_HT_BEGIN', '# BEGIN SmoldPaper');
define('ROOT_HT_END',   '# END SmoldPaper');
define('ROOT_HT_BLOCK',
    ROOT_HT_BEGIN . "\n"
  . "<FilesMatch \"\\.(sqlite|sqlite-wal|sqlite-shm|db|hash|key|lock|session|pending|totp|enc|rate)$\">\n"
  . "    <IfModule mod_authz_core.c>\n        Require all denied\n    </IfModule>\n"
  . "    <IfModule !mod_authz_core.c>\n        Order deny,allow\n        Deny from all\n    </IfModule>\n"
  . "</FilesMatch>\n"
  . ROOT_HT_END . "\n");

// What older versions wrote into the site root (it also blocked manifest.json)
define('LEGACY_ROOT_HT', "Options -Indexes\n<FilesMatch \"\\.(sqlite|db|hash|json|lock)$\">\n    Deny from all\n</FilesMatch>\n");

function ensure_htaccess(): void {
    $f = DATA_DIR . '/.htaccess';
    if (@file_get_contents($f) !== DATA_HTACCESS) @file_put_contents($f, DATA_HTACCESS);

    $r = __DIR__ . '/.htaccess';
    $cur = is_file($r) ? @file_get_contents($r) : '';
    if ($cur === false) return;
    if ($cur === '' || $cur === LEGACY_ROOT_HT) { @file_put_contents($r, ROOT_HT_BLOCK); return; }
    $a = strpos($cur, ROOT_HT_BEGIN);
    $b = strpos($cur, ROOT_HT_END);
    if ($a !== false && $b !== false && $b > $a) {
        $new = substr($cur, 0, $a) . ROOT_HT_BLOCK . ltrim(substr($cur, $b + strlen(ROOT_HT_END)), "\n");
        if ($new !== $cur) @file_put_contents($r, $new);
    }
    // Any other content is the owner's own rules: left untouched, the admin panel shows a hint
}

function root_htaccess_state(): string {
    $r = __DIR__ . '/.htaccess';
    $cur = is_file($r) ? (string)@file_get_contents($r) : '';
    if ($cur === '') return 'missing';
    return strpos($cur, ROOT_HT_BEGIN) !== false ? 'ok' : 'custom';
}

function migrate_lock() {
    $fh = @fopen(MIGRATE_LOCK, 'c');
    if ($fh) flock($fh, LOCK_EX);
    return $fh;
}

function migrate_unlock($fh): void {
    if ($fh) { flock($fh, LOCK_UN); fclose($fh); }
}

// Moves files of older versions to the protected layout. Runs once (data/.layout2).
function migrate_legacy(): void {
    $lk = migrate_lock();
    if (is_file(LAYOUT_MARK)) { migrate_unlock($lk); return; }
    $ok = true;
    $map = [
        'admin.hash' => 'admin_hash', 'admin.key' => 'admin_key', 'admin.totp' => 'admin_totp',
        'admin.session' => 'admin_session', 'admin.pending' => 'admin_pending',
        'config.json' => 'config', 'texts.json' => 'texts'
    ];
    foreach ($map as $old => $new) {
        $of = DATA_DIR . '/' . $old;
        if (!is_file($of)) continue;
        $c = @file_get_contents($of);
        if ($c === false) { $ok = false; continue; }
        if (svc_read($new) === null) {
            // An admin.hash that still holds the public default password is dropped:
            // the setup code from data/setup-code.php takes its place
            $dropDefault = $new === 'admin_hash' && password_verify(WEAK_PW, trim($c));
            if (!$dropDefault && !svc_write($new, $c)) { $ok = false; continue; }
        }
        @unlink($of);
    }
    @unlink(DATA_DIR . '/admin.lock');
    @unlink(RATE_LIMIT_DIR . '/.salt');
    if (!migrate_db_file()) $ok = false;
    
    if ($ok) @file_put_contents(LAYOUT_MARK, SP_VERSION . "\n");
    migrate_unlock($lk);
}

function db_find(): ?string {
    $list = glob(DATA_DIR . '/db_*.sqlite') ?: [];
    sort($list);
    return $list ? $list[0] : null;
}

// data/smoldpaper.sqlite -> data/db_<random>.sqlite
function migrate_db_file(): bool {
    $old = DATA_DIR . '/smoldpaper.sqlite';
    if (!is_file($old) || db_find() !== null) return true;
    try {
        $p = new PDO('sqlite:' . $old, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $p->exec('PRAGMA busy_timeout=10000');
        $p->exec('PRAGMA wal_checkpoint(TRUNCATE)');
        $p->exec('PRAGMA journal_mode=DELETE');
        $p = null;
    } catch (Throwable $e) {
        return false;
    }
    $new = DATA_DIR . '/db_' . bin2hex(random_bytes(12)) . '.sqlite';
    if (!@rename($old, $new)) return false;
    @unlink($old . '-wal');
    @unlink($old . '-shm');
    return true;
}

function db_path(): string {
    $f = db_find();
    if ($f !== null) return $f;
    $lk = migrate_lock();
    $f = db_find();
    if ($f === null) {
        $f = DATA_DIR . '/db_' . bin2hex(random_bytes(12)) . '.sqlite';
        @touch($f);
    }
    migrate_unlock($lk);
    return $f;
}

// Called by the router and by admin.php before anything else
function sp_bootstrap(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    foreach ([DATA_DIR, RATE_LIMIT_DIR] as $d) {
        if (!is_dir($d)) @mkdir($d, 0750, true);
    }
    if (!is_dir(DATA_DIR) || !is_writable(DATA_DIR)) throw new RuntimeException('Data folder is not writable');
    ensure_htaccess();
    if (!is_file(LAYOUT_MARK)) migrate_legacy();
    if (admin_hash_state() === 'missing' && svc_read('setup-code') === null) setup_code_create();
}

// ─── DATABASE ───────────────────────────────────────────

function open_db(): PDO {
    $db = new PDO('sqlite:' . db_path(), null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 10,
    ]);
    $db->exec('PRAGMA busy_timeout=10000');
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec('PRAGMA foreign_keys=ON');
    $db->exec('PRAGMA secure_delete=ON'); // deleted rows are overwritten in the database file
    if ((int)$db->query('PRAGMA user_version')->fetchColumn() < SCHEMA_VERSION) db_migrate($db);
    return $db;
}

function db_migrate(PDO $db): void {
    $stale = tx($db, function () use ($db) {
        $ver = (int)$db->query('PRAGMA user_version')->fetchColumn();
        if ($ver >= SCHEMA_VERSION) return [];
        $stale = [];
        if ($ver < 3) {
            // Version 4.2 changed the note and attachment formats; old notes, chats and files
            // cannot be opened by the new page, so they are removed
            $db->exec('DROP TABLE IF EXISTS messages');
            $db->exec('DROP TABLE IF EXISTS entries');
        }
        // reads_left: reads remaining (notes); auth_hash: sha256 of the access token derived from the
        // note password in the browser (for chats: sha256 of the password check value); salt: PBKDF2 salt (hex); up_*: one-time upload permission
        $db->exec("CREATE TABLE IF NOT EXISTS entries (
            hash_id TEXT PRIMARY KEY, type TEXT NOT NULL DEFAULT 'note',
            content TEXT NOT NULL DEFAULT '', users_count INTEGER NOT NULL DEFAULT 0,
            ttl INTEGER NOT NULL DEFAULT 86400, reads_left INTEGER NOT NULL DEFAULT 1,
            attempts INTEGER NOT NULL DEFAULT 0, max_attempts INTEGER NOT NULL DEFAULT 5,
            auth_hash TEXT NOT NULL DEFAULT '', salt TEXT NOT NULL DEFAULT '',
            up_hash TEXT NOT NULL DEFAULT '', up_left INTEGER NOT NULL DEFAULT 0, up_until INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL, updated_at INTEGER NOT NULL
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT, room_id TEXT NOT NULL,
            content TEXT NOT NULL, sender INTEGER NOT NULL DEFAULT 1,
            created_at INTEGER NOT NULL,
            FOREIGN KEY(room_id) REFERENCES entries(hash_id) ON DELETE CASCADE
        )");
        // Version 4.3 chats: every member has a random token made by the browser (only sha256 here),
        // the sender of a message comes from the token; left_at > 0 – the member closed the page
        $db->exec("CREATE TABLE IF NOT EXISTS members (
            room_id TEXT NOT NULL, user INTEGER NOT NULL, token_hash TEXT NOT NULL,
            last_seen INTEGER NOT NULL, left_at INTEGER NOT NULL DEFAULT 0,
            PRIMARY KEY(room_id, user),
            FOREIGN KEY(room_id) REFERENCES entries(hash_id) ON DELETE CASCADE
        )");
        $db->exec('CREATE INDEX IF NOT EXISTS idx_members_tok ON members(room_id,token_hash)');
        if ($ver === 3) {
            // Chats of version 4.2 have no member tokens and cannot be joined by the new page; notes stay
            $old = $db->query("SELECT hash_id FROM entries WHERE type IN ('chat','qchat')")->fetchAll(PDO::FETCH_COLUMN);
            if ($old) $stale = array_merge($stale, delete_entries($db, $old));
        }
        $db->exec('CREATE INDEX IF NOT EXISTS idx_msg ON messages(room_id,id)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_entries_type ON entries(type)');
        // Logs older than version 4.1 kept full ids of notes and rooms
        $db->exec('PRAGMA user_version=' . SCHEMA_VERSION);
        return $stale;
    });
}

// ─── CLEANUP ────────────────────────────────────────────

// Deletes entries (messages go with them by cascade)
function delete_entries(PDO $db, array $ids): array {
    $fids = [];
    foreach (array_chunk($ids, 200) as $chunk) {
        $in = implode(',', array_fill(0, count($chunk), '?'));
        $db->prepare("DELETE FROM entries WHERE hash_id IN ($in)")->execute($chunk);
    }
    return $fids;
}


// $where gets only integers built here (never user input); they are written into the SQL
// as numbers on purpose: a bound string would compare as TEXT against the arithmetic result
function purge_entries(PDO $db, string $where): void {
    $ids = $db->query("SELECT hash_id FROM entries WHERE $where")->fetchAll(PDO::FETCH_COLUMN);
    if (!$ids) return;
    $fids = delete_entries($db, $ids);
}

function maybe_cleanup(PDO $db, array $cfg): void {
    $idleMin = max(1, (int)$cfg['chat_idle_minutes']);
    $interval = min(300, max(60, $idleMin * 30));
    if (is_file(CLEANUP_LOCK) && time() - (int)@filemtime(CLEANUP_LOCK) < $interval) return;
    @touch(CLEANUP_LOCK);
    $now = time();
    $idle = $idleMin * 60;
    purge_entries($db, "type='chat' AND updated_at < " . ($now - $idle));
    purge_entries($db, "type='chat' AND users_count < 2 AND created_at < " . ($now - CHAT_WAIT_TTL));
    purge_entries($db, "type='note' AND ($now - created_at) > ttl");
    purge_entries($db, "type='qchat' AND users_count < 99 AND updated_at < " . ($now - $idle));
    purge_entries($db, "type='qchat' AND users_count = 99 AND updated_at < " . ($now - QCHAT_DEAD_TTL));
    purge_entries($db, "type='qchat' AND users_count < 2 AND created_at < " . ($now - CHAT_WAIT_TTL));
    foreach (glob(DATA_DIR . '/.tmp_*.php') ?: [] as $t) {
        if ($now - (int)@filemtime($t) > 3600) @unlink($t);
    }
}



// ─── RATE LIMITING ──────────────────────────────────────
// Bucket file names are HMACs of the client address with a random salt that is replaced
// every hour (all old buckets are deleted then), so they never turn back into addresses.

function rate_salt(): string {
    static $salt = null;
    if ($salt !== null) return $salt;
    $raw = trim((string)svc_read('rate_salt'));
    if (preg_match('/^(\d+):([a-f0-9]{64})$/', $raw, $m) && time() - (int)$m[1] < RATE_SALT_TTL) return $salt = $m[2];
    $salt = bin2hex(random_bytes(32));
    svc_write('rate_salt', time() . ':' . $salt);
    foreach (glob(RATE_LIMIT_DIR . '/*.rate') ?: [] as $old) @unlink($old);
    return $salt;
}

function client_tag(): string {
    return hash_hmac('sha256', client_net(), rate_salt());
}

function rate_bucket(string $action): string {
    if (in_array($action, ['poll_messages', 'check_qchat', 'leave_room'], true)) return 'poll';
    if (in_array($action, ['create_note', 'create_room', 'create_qchat', 'join_qchat'], true)) return 'write';
    return 'main';
}

function check_rate_limit(string $bucket): void {
    $max = RATE_BUCKETS[$bucket] ?? RATE_BUCKETS['main'];
    $now = time();
    if (mt_rand(1, 100) === 1) {
        foreach (glob(RATE_LIMIT_DIR . '/*.rate') ?: [] as $f) {
            if ($now - (int)@filemtime($f) > RATE_LIMIT_WINDOW * 2) @unlink($f);
        }
    }
    $file = RATE_LIMIT_DIR . '/' . substr(client_tag(), 0, 40) . '_' . $bucket . '.rate';
    $fh = @fopen($file, 'c+');
    if (!$fh) return;
    flock($fh, LOCK_EX);
    $from = $now - RATE_LIMIT_WINDOW;
    $lines = array_values(array_filter(explode("\n", (string)stream_get_contents($fh)), function ($t) use ($from) {
        return (int)$t > $from;
    }));
    if (count($lines) >= $max) {
        flock($fh, LOCK_UN);
        fclose($fh);
        if (!headers_sent()) header('Retry-After: 30');
        json_out(429, ['error' => 'Too many requests. Try again later.']);
    }
    $lines[] = $now;
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, implode("\n", $lines));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
}


// sha256 of the token (hex) equals the stored hash
function token_ok(string $hash, $token): bool {
    if ($hash === '' || !is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) return false;
    return hash_equals($hash, hash('sha256', hex2bin($token)));
}


// ─── NOTES ──────────────────────────────────────────────
// The browser derives two things from the note password (PBKDF2 600 000 + HKDF): the encryption key
// and an access token. The server keeps only sha256 of the token. It hands out the encrypted note,
// spends a read and counts wrong attempts itself – nobody gets the ciphertext without the password.

function create_note(PDO $db): void {
    $in = get_input();
    $content = $in['content'] ?? null;
    if (!is_string($content) || $content === '') json_out(400, ['error' => 'Missing: content']);
    if (strlen($content) > MAX_NOTE_SIZE) json_out(413, ['error' => 'Payload too large']);
    if (!preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $content)) json_out(400, ['error' => 'Bad content']);
    $salt = in_str($in, 'salt');
    $authHash = in_str($in, 'auth_hash');
    if (!preg_match('/^[a-f0-9]{32}$/', $salt) || !preg_match('/^[a-f0-9]{64}$/', $authHash)) json_out(400, ['error' => 'Bad key data']);
    $cfg = load_config();
    $maxReads = 1;
    $ttl = NOTE_DEFAULT_TTL;
    if (isset($in['ttl'])) {
        $reqTtl = (int)$in['ttl'];
        $allowed = TIMER_CHOICES;
        if (in_array($reqTtl, $allowed, true)) $ttl = $reqTtl;
    }
    $files = (int)($in['files'] ?? 0);
    $maxFiles = 0;
    if ($files < 0 || $files > $maxFiles) json_out(409, ['error' => 'Too many files']);
    $upToken = '';
    $upHash = '';

    $maxAtt = max(1, min(20, (int)$cfg['max_attempts']));
    $id = bin2hex(random_bytes(16));
    $now = time();
    $db->prepare('INSERT INTO entries (hash_id,type,content,reads_left,ttl,max_attempts,auth_hash,salt,up_hash,up_left,up_until,created_at,updated_at)
                  VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)')
       ->execute([$id, 'note', $content, $maxReads, $ttl, $maxAtt, $authHash, $salt, $upHash, $files, 0, $now, $now]);
    $out = ['id' => $id, 'expires' => $now + $ttl, 'max_reads' => $maxReads];
    json_out(201, $out);
}

// What the page needs before asking for the password: the salt and the attempts left. Spends nothing.
function note_meta(PDO $db): void {
    $id = get_input()['id'] ?? '';
    need_id($id, [32]);
    $s = $db->prepare("SELECT salt, attempts, max_attempts, ttl, created_at FROM entries WHERE hash_id=? AND type='note'");
    $s->execute([$id]);
    $e = $s->fetch();
    if (!$e || time() - (int)$e['created_at'] > (int)$e['ttl']) json_out(404, ['error' => 'Not found']);
    json_out(200, ['salt' => $e['salt'], 'attempts_left' => max(0, (int)$e['max_attempts'] - (int)$e['attempts'])]);
}

// A correct token returns the note and spends one read; the last read deletes it.
// A wrong token is a wrong password: counted here, the note is destroyed at the limit.
function read_note(PDO $db): void {
    $in = get_input();
    $id = $in['id'] ?? '';
    need_id($id, [32]);
    $auth = in_str($in, 'auth');
    if (!preg_match('/^[a-f0-9]{64}$/', $auth)) json_out(400, ['error' => 'Bad token']);
    $r = tx($db, function () use ($db, $id, $auth) {
        $now = time();
        $s = $db->prepare("SELECT * FROM entries WHERE hash_id=? AND type='note'");
        $s->execute([$id]);
        $e = $s->fetch();
        if (!$e || $now - (int)$e['created_at'] > (int)$e['ttl']) return ['gone'];
        if (!token_ok((string)$e['auth_hash'], $auth)) {
            $att = (int)$e['attempts'] + 1;
            $max = (int)$e['max_attempts'];
            if ($att >= $max) return ['destroyed', delete_entries($db, [$id])];
            $db->prepare('UPDATE entries SET attempts=? WHERE hash_id=?')->execute([$att, $id]);
            return ['wrong', $max - $att];
        }
        $left = (int)$e['reads_left'];
        $isLast = $left <= 1;
        $burn = ["DELETE FROM entries WHERE hash_id=? AND type='note'", [$id]];
        if ($isLast) {
            $u = $db->prepare($burn[0]);
            $u->execute($burn[1]);
        } else {
            $u = $db->prepare('UPDATE entries SET reads_left=reads_left-1, updated_at=? WHERE hash_id=? AND reads_left>1');
            $u->execute([$now, $id]);
        }
        if ($u->rowCount() !== 1) return ['gone'];
        return ['ok', [
            'status'       => 'ok',
            'content'      => $e['content'],
            'created_at'   => (int)$e['created_at'],
            'reads_left'   => $isLast ? 0 : $left - 1,
            'is_last_read' => $isLast,
        ]];
    });
    if ($r[0] === 'gone') json_out(404, ['error' => 'Not found']);
    if ($r[0] === 'destroyed') {
        json_out(200, ['status' => 'destroyed']);
    }
    if ($r[0] === 'wrong') json_out(200, ['status' => 'wrong', 'attempts_left' => $r[1]]);
    json_out(200, $r[1]);
}

// ─── CHATS: MEMBERS ─────────────────────────────────────
// Every member of a chat has a random token made by the browser; the server keeps only its sha256.
// The token decides who sends a message, and the same token returns the same seat after a reload
// (joining is idempotent). A room also keeps sha256 of a "check" value derived from the chat
// password: a wrong password is refused at the door instead of producing unreadable messages.
// Presence: last poll time of each member; a closed page sends leave_room.

function hex_hash($v): string {
    return is_string($v) && preg_match('/^[a-f0-9]{64}$/', $v) ? hash('sha256', hex2bin($v)) : '';
}

function need_hex64(array $in, string $k): string {
    $h = hex_hash($in[$k] ?? null);
    if ($h === '') json_out(400, ['error' => "Bad $k"]);
    return $h;
}

function room_max_users(array $cfg, string $type): int {
    $n = 2;
    return $n;
}

function member_of(PDO $db, string $room, string $tokHash): int {
    if ($tokHash === '') return 0;
    $s = $db->prepare('SELECT user FROM members WHERE room_id=? AND token_hash=?');
    $s->execute([$room, $tokHash]);
    return (int)$s->fetchColumn();
}

// The member is here now; written at most every SEEN_STEP seconds
function member_seen(PDO $db, string $room, int $user): void {
    $now = time();
    $db->prepare('UPDATE members SET last_seen=?, left_at=0 WHERE room_id=? AND user=? AND (left_at>0 OR last_seen<?)')
       ->execute([$now, $room, $user, $now - SEEN_STEP]);
}

// [members who ever joined, members present now]
function room_presence(PDO $db, string $room): array {
    $s = $db->prepare('SELECT COUNT(*), COALESCE(SUM(left_at=0 AND last_seen>=?),0) FROM members WHERE room_id=?');
    $s->execute([time() - PRESENCE_TTL, $room]);
    return array_map('intval', $s->fetch(PDO::FETCH_NUM));
}

// Joins (or returns to) a room inside one transaction. $mode: 'new' – only create it (or return the
// creator with the same token), 'any' – create or join (stealth), 'join' – only join an existing room.
// Wrong passwords are counted per room (rooms use entries.attempts for the count and entries.up_until for
// the wait): after ROOM_FREE_FAILS in a row newcomers wait 1, 5, then 15 minutes; members can still return.
// The room is never destroyed for wrong passwords, or a stranger with the link could kill it.
// Result: [status, user, joined] with status created | joined | rejoined | wrong | locked | full | gone | taken
// (for locked the third value is the number of seconds to wait)
function room_enter(PDO $db, string $room, string $type, string $tokHash, string $checkHash, int $maxUsers, string $mode): array {
    return tx($db, function () use ($db, $room, $type, $tokHash, $checkHash, $maxUsers, $mode) {
        $now = time();
        $s = $db->prepare('SELECT type, users_count, auth_hash, attempts, up_until FROM entries WHERE hash_id=?');
        $s->execute([$room]);
        $e = $s->fetch();
        if (!$e) {
            if ($mode === 'join') return ['gone', 0, 0];
            $db->prepare('INSERT INTO entries (hash_id,type,content,users_count,auth_hash,created_at,updated_at) VALUES(?,?,?,1,?,?,?)')
               ->execute([$room, $type, '', $checkHash, $now, $now]);
            $db->prepare('INSERT INTO members (room_id,user,token_hash,last_seen) VALUES(?,1,?,?)')->execute([$room, $tokHash, $now]);
            return ['created', 1, 1];
        }
        if ($e['type'] !== $type || (int)$e['users_count'] >= 99) return [$mode === 'new' ? 'taken' : 'gone', 0, 0];
        $user = member_of($db, $room, $tokHash);
        $wait = (int)$e['up_until'] - $now;
        if ($user <= 0 && $wait > 0) return ['locked', 0, $wait];
        if (!hash_equals((string)$e['auth_hash'], $checkHash)) {
            $fails = (int)$e['attempts'] + 1;
            $until = 0;
            if ($fails >= ROOM_FREE_FAILS) {
                $steps = ROOM_LOCK_STEPS;
                $until = $now + $steps[min($fails - ROOM_FREE_FAILS, count($steps) - 1)];
            }
            $db->prepare('UPDATE entries SET attempts=?, up_until=? WHERE hash_id=?')->execute([$fails, $until, $room]);
            return ['wrong', 0, 0];
        }
        if ((int)$e['attempts'] > 0) $db->prepare('UPDATE entries SET attempts=0, up_until=0 WHERE hash_id=?')->execute([$room]);
        if ($user > 0) {
            $db->prepare('UPDATE members SET last_seen=?, left_at=0 WHERE room_id=? AND user=?')->execute([$now, $room, $user]);
            return ['rejoined', $user, (int)$e['users_count']];
        }
        if ($mode === 'new') return ['taken', 0, 0];
        $n = (int)$e['users_count'];
        if ($n >= $maxUsers) return ['full', 0, $n];
        $user = (int)$db->query('SELECT COALESCE(MAX(user),0) FROM members WHERE room_id=' . $db->quote($room))->fetchColumn() + 1;
        $db->prepare('INSERT INTO members (room_id,user,token_hash,last_seen) VALUES(?,?,?,?)')->execute([$room, $user, $tokHash, $now]);
        $db->prepare('UPDATE entries SET users_count=?, updated_at=? WHERE hash_id=?')->execute([$n + 1, $now, $room]);
        return ['joined', $user, $n + 1];
    });
}

function room_enter_reply(PDO $db, array $r, string $room, int $maxUsers): void {
    if ($r[0] === 'wrong') { sleep(1); json_out(200, ['status' => 'wrong']); }
    if ($r[0] === 'locked') json_out(200, ['status' => 'locked', 'retry_after' => $r[2]]);
    if ($r[0] === 'full') json_out(200, ['status' => 'full']);
    if ($r[0] === 'gone' || $r[0] === 'taken') json_out(200, ['status' => 'gone']);
    json_out($r[0] === 'created' ? 201 : 200, ['status' => $r[0], 'user' => $r[1], 'joined' => $r[2], 'max_users' => $maxUsers]);
}

// ─── STEALTH CHAT ───────────────────────────────────────
// The room id comes from the stealth phrase (PBKDF2 600 000 in the browser); the first one creates the room.

function create_room(PDO $db): void {
    $in = get_input();
    $h = $in['hash_id'] ?? '';
    need_id($h, [64]);
    $tok = need_hex64($in, 'token');
    $check = need_hex64($in, 'check');
    $maxUsers = room_max_users(load_config(), 'chat');
    room_enter_reply($db, room_enter($db, $h, 'chat', $tok, $check, $maxUsers, 'any'), $h, $maxUsers);
}

// ─── MESSAGING ──────────────────────────────────────────

// [open room, member] for the token, or null. Not a member, no room and a destroyed room look the same
// from outside, so a stranger cannot learn whether somebody uses a room.
function room_find(PDO $db, string $rid, string $tok): ?array {
    $s = $db->prepare("SELECT * FROM entries WHERE hash_id=? AND type IN ('chat','qchat')");
    $s->execute([$rid]);
    $room = $s->fetch();
    if (!$room || (int)$room['users_count'] >= 99) return null;
    $user = member_of($db, $rid, $tok);
    return $user > 0 ? [$room, $user] : null;
}

// The open room and the member behind the token, or an error reply
function room_member(PDO $db, array $in, string $idKey = 'room_id'): array {
    $rid = $in[$idKey] ?? '';
    need_id($rid, [32, 64]);
    $tok = hex_hash($in['token'] ?? null);
    if ($tok === '') json_out(400, ['error' => 'Bad token']);
    $m = room_find($db, $rid, $tok);
    if (!$m) json_out(403, ['error' => 'Not a member']);
    return [$rid, $m[0], $m[1]];
}

function send_message(PDO $db): void {
    $in = get_input();
    $content = $in['content'] ?? null;
    if (!is_string($content) || $content === '') json_out(400, ['error' => 'Missing: content']);
    if (strlen($content) > MAX_PAYLOAD_SIZE) json_out(413, ['error' => 'Payload too large']);
    if (!preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $content)) json_out(400, ['error' => 'Bad content']);
    $rid = $in['room_id'] ?? '';
    need_id($rid, [32, 64]);
    $tok = hex_hash($in['token'] ?? null);
    if ($tok === '') json_out(400, ['error' => 'Bad token']);
    // One transaction with the member check: a message cannot slip in after the room is destroyed.
    // A repeated request (lost answer) brings the same ciphertext (random IV): it gets the id of the
    // stored message instead of a copy.
    $r = tx($db, function () use ($db, $rid, $tok, $content) {
        $m = room_find($db, $rid, $tok);
        if (!$m) return null;
        $s = $db->prepare('SELECT id, created_at FROM messages WHERE room_id=? AND content=? LIMIT 1');
        $s->execute([$rid, $content]);
        $old = $s->fetch();
        if ($old) return [$m[1], (int)$old['id'], (int)$old['created_at']];
        $now = time();
        $db->prepare('INSERT INTO messages (room_id,content,sender,created_at) VALUES(?,?,?,?)')
           ->execute([$rid, $content, $m[1], $now]);
        $mid = (int)$db->lastInsertId();
        $db->prepare('UPDATE entries SET updated_at=? WHERE hash_id=?')->execute([$now, $rid]);
        return [$m[1], $mid, $now];
    });
    if (!$r) json_out(403, ['error' => 'Not a member']);
    member_seen($db, $rid, $r[0]);
    json_out(201, ['id' => $r[1], 'created_at' => $r[2]]);
}

function poll_messages(PDO $db): void {
    $in = get_input();
    [$rid, $room, $user] = room_member($db, $in);
    $after = max(0, (int)($in['after'] ?? 0));
    member_seen($db, $rid, $user);
    [$joined, $present] = room_presence($db, $rid);
    $cfg = load_config();
    $s = $db->prepare('SELECT id,content,sender,created_at FROM messages WHERE room_id=? AND id>? ORDER BY id');
    $s->execute([$rid, $after]);
    json_out(200, [
        'messages'    => $s->fetchAll(),
        'user'        => $user,
        'users_count' => $present,
        'joined'      => $joined,
        'away'        => max(0, $joined - $present),
        'max_users'   => room_max_users($cfg, $room['type']),
        'created_at'  => (int)$room['created_at'],
        'updated_at'  => (int)$room['updated_at'],
        'idle_ttl'    => max(1, (int)$cfg['chat_idle_minutes']) * 60
    ]);
}

// Sent by the page when it is closed (navigator.sendBeacon); the seat stays for a return with the same token
function leave_room(PDO $db): void {
    $in = get_input();
    $rid = $in['room_id'] ?? '';
    $tok = hex_hash($in['token'] ?? null);
    if (valid_id($rid, [32, 64]) && $tok !== '') {
        $db->prepare('UPDATE members SET left_at=? WHERE room_id=? AND token_hash=?')->execute([time(), $rid, $tok]);
    }
    json_out(200, ['status' => 'ok']);
}

function destroy_room(PDO $db): void {
    $in = get_input();
    $rid = $in['room_id'] ?? '';
    need_id($rid, [32, 64]);
    $s = $db->prepare("SELECT type, users_count FROM entries WHERE hash_id=? AND type IN ('chat','qchat')");
    $s->execute([$rid]);
    $room = $s->fetch();
    if (!$room || (int)$room['users_count'] >= 99) json_out(200, ['status' => 'destroyed']);
    if (member_of($db, $rid, hex_hash($in['token'] ?? null)) <= 0) json_out(403, ['error' => 'Not a member']);
    if ($room['type'] === 'qchat') {
        // A dead quick room stays as a marker (the old link must not open a new room); it is not renewed
        $fids = tx($db, function () use ($db, $rid) {
            $fids = [];
            $db->prepare('DELETE FROM messages WHERE room_id=?')->execute([$rid]);
            $db->prepare('DELETE FROM members WHERE room_id=?')->execute([$rid]);
            $db->prepare("UPDATE entries SET users_count=99, content='', auth_hash='', updated_at=? WHERE hash_id=?")->execute([time(), $rid]);
            return $fids;
        });
    } else {
        $fids = delete_entries($db, [$rid]);
    }
    json_out(200, ['status' => 'destroyed']);
}

// ─── QUICK CHAT ─────────────────────────────────────────
// The browser makes the room id itself: the chat key is derived from the password with the id as salt,
// and the password check value must be known at creation. A repeated request (lost answer) is harmless.

function create_qchat(PDO $db): void {
    $in = get_input();
    $id = $in['id'] ?? '';
    need_id($id, [32]);
    $tok = need_hex64($in, 'token');
    $check = need_hex64($in, 'check');
    $maxUsers = room_max_users(load_config(), 'qchat');
    $r = room_enter($db, $id, 'qchat', $tok, $check, $maxUsers, 'new');
    if ($r[0] === 'created' || ($r[0] === 'rejoined' && $r[1] === 1)) {
        json_out(201, ['id' => $id, 'status' => 'created', 'user' => 1, 'max_users' => $maxUsers]);
    }
    json_out(409, ['error' => 'Id taken']);
}

function join_qchat(PDO $db): void {
    $in = get_input();
    $id = $in['room_id'] ?? '';
    need_id($id, [32]);
    $tok = need_hex64($in, 'token');
    $check = need_hex64($in, 'check');
    $maxUsers = room_max_users(load_config(), 'qchat');
    room_enter_reply($db, room_enter($db, $id, 'qchat', $tok, $check, $maxUsers, 'join'), $id, $maxUsers);
}

// The creator's page while it waits for the partner: waiting | joined | gone
function check_qchat(PDO $db): void {
    $in = get_input();
    $id = $in['id'] ?? '';
    need_id($id, [32]);
    // Membership first: a stranger gets the same answer for a live, missing or destroyed room
    $user = member_of($db, $id, hex_hash($in['token'] ?? null));
    if ($user <= 0) json_out(403, ['error' => 'Not a member']);
    $s = $db->prepare("SELECT users_count FROM entries WHERE hash_id=? AND type='qchat'");
    $s->execute([$id]);
    $n = $s->fetchColumn();
    if ($n === false || (int)$n >= 99) json_out(200, ['status' => 'gone']);
    member_seen($db, $id, $user);
    json_out(200, ['status' => (int)$n >= 2 ? 'joined' : 'waiting', 'joined' => (int)$n]);
}

// ─── ADMIN: PROTECTION HELPERS ──────────────────────────
// data/admin_key.php     – sha256 of the secret address key (delete via FTP: admin.php?manage=1 again)
// data/admin_hash.php    – password hash (delete via FTP: a new setup code appears in data/setup-code.php)
// data/setup-code.php    – one-time code for the first sign-in, deleted after setup
// data/admin_lock.php    – sign-in limiter, no IP addresses stored
// data/admin_session.php – one admin session, only sha256 of the cookie token

function is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') return true;
    if (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') return true;
    return (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function admin_cookie_path(): string {
    $p = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $p = rtrim($p, '/');
    return $p === '' ? '/' : $p . '/';
}

function admin_set_cookie(string $value, int $expires): void {
    setcookie(ADMIN_COOKIE, $value, [
        'expires'  => $expires,
        'path'     => admin_cookie_path(),
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}

// none | ok | damaged (a damaged key file closes the admin panel for everyone)
function admin_key_state(): string {
    $raw = svc_read('admin_key');
    if ($raw === null) return 'none';
    return preg_match('/^[a-f0-9]{64}$/', trim($raw)) ? 'ok' : 'damaged';
}

function admin_key_ok(string $k): bool {
    $st = admin_key_state();
    if ($st === 'none') return true; // first run or reset via FTP
    if ($st !== 'ok') return false;
    return $k !== '' && hash_equals(trim((string)svc_read('admin_key')), hash('sha256', $k));
}

function valid_admin_key(string $k): bool {
    return preg_match('/^[A-Za-z0-9_-]{12,64}$/', $k) === 1;
}

function admin_key_weak(string $k): bool {
    return count(array_unique(str_split($k))) < 8;
}

// missing | ok | damaged
function admin_hash_state(): string {
    $raw = svc_read('admin_hash');
    if ($raw === null) return 'missing';
    return preg_match('/^\$(2[aby]|argon2id?)\$/', trim($raw)) ? 'ok' : 'damaged';
}

function admin_pw_ok(string $pw): bool {
    if ($pw === '' || admin_hash_state() !== 'ok') return false;
    return password_verify($pw, trim((string)svc_read('admin_hash')));
}

function admin_hash_write(string $pw): bool {
    return svc_write('admin_hash', password_hash($pw, PASSWORD_DEFAULT));
}

// Returns an error message for an unacceptable new password, or '' if it is fine
function new_pw_error(string $np): string {
    if (strlen($np) < ADMIN_PW_MIN) return 'Min 8 chars';
    if (strlen($np) > ADMIN_PW_MAX_BYTES) return 'Max 72 bytes';
    if ($np === WEAK_PW) return 'Default password not allowed';
    return '';
}

function setup_code_create(): void {
    $abc = TOTP_BACKUP_ABC;
    $n = strlen($abc);
    $c = '';
    for ($i = 0; $i < 16; $i++) $c .= $abc[random_int(0, $n - 1)];
    svc_write('setup-code', implode('-', str_split($c, 4)) . "\n");
}

function setup_code_get(): string {
    $raw = svc_read('setup-code');
    if ($raw === null) return '';
    $line = trim((string)strtok($raw, "\n"));
    return otp_normalize($line);
}

// A full session lives in data/admin_session.php. After the password, when two-factor is on,
// a half-session ("otp" stage) is kept separately in data/admin_pending.php, so someone who
// only knows the password cannot throw the owner out of an open admin panel.
function admin_session_start(bool $needPw, string $stage = 'full'): string {
    $token = bin2hex(random_bytes(32));
    $csrf  = bin2hex(random_bytes(32));
    $now   = time();
    $name  = $stage === 'otp' ? 'admin_pending' : 'admin_session';
    $ok = svc_write($name, json_encode([
        'th' => hash('sha256', $token), 'csrf' => $csrf,
        'created' => $now, 'last' => $now, 'need_pw' => $needPw, 'bad' => 0, 'chk' => 0
    ]));
    if (!$ok) json_out(500, ['error' => 'Server error']);
    if ($stage !== 'otp') svc_delete('admin_pending');
    admin_set_cookie($token, 0);
    return $csrf;
}

function admin_session_end(?array $s = null): void {
    if ($s && ($s['stage'] ?? 'full') === 'otp') svc_delete('admin_pending');
    else svc_delete('admin_session');
    admin_set_cookie('', time() - 3600);
}

function admin_session_save(array $s): void {
    $stage = $s['stage'] ?? 'full';
    unset($s['stage']);
    svc_write($stage === 'otp' ? 'admin_pending' : 'admin_session', json_encode($s));
}

function admin_session_get(): ?array {
    $tok = $_COOKIE[ADMIN_COOKIE] ?? '';
    if (!is_string($tok) || $tok === '') return null;
    $th = hash('sha256', $tok);
    $now = time();
    foreach (['admin_session' => 'full', 'admin_pending' => 'otp'] as $name => $stage) {
        $s = svc_json($name);
        if (!$s || empty($s['th']) || empty($s['csrf'])) continue;
        if (!hash_equals((string)$s['th'], $th)) continue;
        $s['stage'] = $stage;
        if ($stage === 'otp') {
            if ($now - (int)$s['created'] > ADMIN_OTP_TTL) { admin_session_end($s); return null; }
            return $s;
        }
        if ($now - (int)$s['last'] > ADMIN_IDLE_TTL || $now - (int)$s['created'] > ADMIN_MAX_TTL) {
            admin_session_end($s);
            return null;
        }
        if ($now - (int)$s['last'] >= 30) { // do not rewrite the file on every request
            $s['last'] = $now;
            admin_session_save($s);
        }
        return $s;
    }
    return null;
}

// A wrong password or code inside an open session; after ADMIN_SESSION_MAX_BAD the session is closed
function admin_session_bad(array $s, string $error): void {
    sleep(1);
    $s['bad'] = (int)($s['bad'] ?? 0) + 1;
    if ($s['bad'] >= ADMIN_SESSION_MAX_BAD) {
        admin_session_end($s);
        json_out(401, ['error' => 'Unauthorized', 'reason' => 'attempts']);
    }
    admin_session_save($s);
    json_out(403, ['error' => $error]);
}

function admin_setup_state(array $s): array {
    return [
        'need_pw'  => !empty($s['need_pw']) || admin_hash_state() === 'missing',
        'need_key' => admin_key_state() === 'none'
    ];
}

function admin_csrf_ok(array $s): bool {
    $csrf = $_SERVER['HTTP_X_SP_CSRF'] ?? '';
    return is_string($csrf) && $csrf !== '' && hash_equals((string)$s['csrf'], $csrf);
}

// Valid full session + CSRF header; unless $allowSetup, the first-time setup must be finished
function admin_require(bool $allowSetup = false): array {
    $s = admin_session_get();
    if (!$s || $s['stage'] !== 'full') json_out(401, ['error' => 'Unauthorized']);
    if (!admin_csrf_ok($s)) json_out(403, ['error' => 'Unauthorized']);
    if (!$allowSetup) {
        $st = admin_setup_state($s);
        if ($st['need_pw'] || $st['need_key']) json_out(403, ['error' => 'Setup required']);
    }
    return $s;
}

// ─── ADMIN: SIGN-IN LIMITER (data/admin_lock.php) ───────
// "p": counters per anonymised address (HMAC with the hourly salt, the address itself is never stored),
// so a stranger who knows the admin address locks out only himself.
// "g": a higher shared ceiling against attacks from many addresses.

function admin_lock_open() {
    $fh = @fopen(svc_path('admin_lock'), 'c+');
    if (!$fh) json_out(500, ['error' => 'Server error']);
    flock($fh, LOCK_EX); // attempts are handled strictly one at a time
    return $fh;
}

function admin_lock_key(): string { return substr(client_tag(), 0, 32); }

function admin_lock_read($fh): array {
    rewind($fh);
    $raw = (string)stream_get_contents($fh);
    if (strncmp($raw, SVC_HEAD, strlen(SVC_HEAD)) === 0) $raw = substr($raw, strlen(SVC_HEAD));
    $now = time();
    $st = ['g' => ['fails' => 0, 'since' => $now, 'until' => 0], 'p' => []];
    if (trim($raw) === '') return $st;
    $d = json_decode($raw, true);
    if (!is_array($d) || !is_array($d['g'] ?? null) || !is_array($d['p'] ?? null)) {
        // Damaged counter: sign-in pauses for a minute, then starts over
        $st['g']['until'] = $now + 60;
        return $st;
    }
    $g = $d['g'];
    $st['g'] = ['fails' => (int)($g['fails'] ?? 0), 'since' => (int)($g['since'] ?? $now), 'until' => (int)($g['until'] ?? 0)];
    foreach ($d['p'] as $k => $p) {
        if (!is_string($k) || !is_array($p)) continue;
        $p = ['fails' => (int)($p['fails'] ?? 0), 'level' => (int)($p['level'] ?? 0), 'until' => (int)($p['until'] ?? 0), 't' => (int)($p['t'] ?? 0)];
        if ($p['until'] < $now && $now - $p['t'] > 86400) continue; // forgotten after a quiet day
        $st['p'][$k] = $p;
    }
    return $st;
}

function admin_lock_write($fh, array $st): bool {
    $data = SVC_HEAD . json_encode($st);
    if (!ftruncate($fh, 0)) return false;
    rewind($fh);
    $n = fwrite($fh, $data);
    fflush($fh);
    return $n === strlen($data);
}

function admin_lock_close($fh): void {
    flock($fh, LOCK_UN);
    fclose($fh);
}

// Stops right here with 429 while sign-in is closed
function admin_lock_check($fh, array $st): void {
    $now = time();
    $until = max((int)$st['g']['until'], (int)($st['p'][admin_lock_key()]['until'] ?? 0));
    if ($until > $now) {
        admin_lock_close($fh);
        json_out(429, ['error' => 'Locked', 'retry' => $until - $now]);
    }
}

// Counts one wrong attempt; replies 429 when a limit is reached, otherwise 403 with $error.
// If the counter cannot be saved, sign-in is refused (503): no guessing without counting.
function admin_lock_fail($fh, array $st, string $error): void {
    sleep(1);
    $now = time();
    $k = admin_lock_key();
    $p = $st['p'][$k] ?? ['fails' => 0, 'level' => 0, 'until' => 0, 't' => $now];
    $p['fails']++;
    $p['t'] = $now;
    $retry = 0;
    if ($p['fails'] >= ADMIN_MAX_FAILS) {
        $steps = ADMIN_LOCK_STEPS;
        $retry = $steps[min($p['level'], count($steps) - 1)];
        $p['until'] = $now + $retry;
        $p['level']++;
        $p['fails'] = 0;
    }
    $st['p'][$k] = $p;
    $g = $st['g'];
    if ($now - $g['since'] > ADMIN_GLOBAL_WINDOW) { $g['fails'] = 0; $g['since'] = $now; }
    $g['fails']++;
    if ($g['fails'] >= ADMIN_GLOBAL_MAX_FAILS) {
        $g['until'] = $now + ADMIN_GLOBAL_LOCK;
        $g['fails'] = 0;
        $g['since'] = $now;
        $retry = max($retry, ADMIN_GLOBAL_LOCK);
    }
    $st['g'] = $g;
    $ok = admin_lock_write($fh, $st);
    admin_lock_close($fh);
    if (!$ok) json_out(503, ['error' => 'Server error']);
    if ($retry) json_out(429, ['error' => 'Locked', 'retry' => $retry]);
    json_out(403, ['error' => $error]);
}

// After a correct password/code: this address starts over, the shared counter too
function admin_lock_reset($fh, array $st): bool {
    unset($st['p'][admin_lock_key()]);
    $st['g']['fails'] = 0;
    $st['g']['since'] = time();
    return admin_lock_write($fh, $st);
}

// ─── ADMIN: TWO-FACTOR (TOTP, RFC 6238) ─────────────────
// data/admin_totp.php: {"secret": base32, "last_step": int, "backup": [sha256...], "created": int}
// The secret has to be readable by the server to check codes – this is how TOTP works.

function b32_encode(string $bin): string {
    $abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $out = ''; $buf = 0; $bits = 0;
    for ($i = 0, $n = strlen($bin); $i < $n; $i++) {
        $buf = ($buf << 8) | ord($bin[$i]);
        $bits += 8;
        while ($bits >= 5) {
            $bits -= 5;
            $out .= $abc[($buf >> $bits) & 31];
        }
        $buf &= (1 << $bits) - 1;
    }
    if ($bits > 0) $out .= $abc[($buf << (5 - $bits)) & 31];
    return $out;
}

function b32_decode(string $s): string {
    $abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s));
    $out = ''; $buf = 0; $bits = 0;
    for ($i = 0, $n = strlen($s); $i < $n; $i++) {
        $v = strpos($abc, $s[$i]);
        if ($v === false) continue;
        $buf = ($buf << 5) | $v;
        $bits += 5;
        if ($bits >= 8) {
            $bits -= 8;
            $out .= chr(($buf >> $bits) & 255);
        }
        $buf &= (1 << $bits) - 1;
    }
    return $out;
}

function totp_at(string $key, int $step): string {
    $msg = pack('N', ($step >> 32) & 0xFFFFFFFF) . pack('N', $step & 0xFFFFFFFF);
    $h = hash_hmac('sha1', $msg, $key, true);
    $o = ord($h[19]) & 0x0F;
    $v = ((ord($h[$o]) & 0x7F) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string)($v % (10 ** TOTP_DIGITS)), TOTP_DIGITS, '0', STR_PAD_LEFT);
}

function totp_now_step(): int { return intdiv(time(), TOTP_PERIOD); }

// Returns the matching step within +-$win steps around now, or null
function totp_find(string $secretB32, string $code, int $win): ?int {
    if (!preg_match('/^\d{' . TOTP_DIGITS . '}$/', $code)) return null;
    $key = b32_decode($secretB32);
    if ($key === '') return null;
    $now = totp_now_step();
    $found = null;
    for ($d = -$win; $d <= $win; $d++) { // no early exit: same timing for every code
        if (hash_equals(totp_at($key, $now + $d), $code) && $found === null) $found = $now + $d;
    }
    return $found;
}

function totp_load(): ?array {
    $t = svc_json('admin_totp');
    if (!$t || !is_string($t['secret'] ?? null) || strlen(b32_decode($t['secret'])) < 10) return null;
    $t['last_step'] = (int)($t['last_step'] ?? 0);
    $t['backup'] = is_array($t['backup'] ?? null) ? array_values($t['backup']) : [];
    return $t;
}

// off | on | damaged (a damaged file blocks sign-in instead of silently turning two-factor off)
function totp_state(): string {
    if (svc_read('admin_totp') === null) return 'off';
    return totp_load() ? 'on' : 'damaged';
}

function totp_save(array $t): bool {
    return svc_write('admin_totp', json_encode($t));
}

// "k7m2 qx9p" -> "K7M2QX9P"; "123 456" -> "123456"
function otp_normalize(string $in): string {
    return strtoupper(preg_replace('/[\s\-]+/', '', $in));
}

function backup_hash(string $code): string { return hash('sha256', 'sp-backup:' . otp_normalize($code)); }

// Returns [plain codes for the owner, hashes for the file]
function backup_generate(): array {
    $abc = TOTP_BACKUP_ABC; $n = strlen($abc);
    $plain = []; $hashes = [];
    while (count($plain) < TOTP_BACKUP_COUNT) {
        $c = '';
        for ($i = 0; $i < 8; $i++) $c .= $abc[random_int(0, $n - 1)];
        $c = substr($c, 0, 4) . '-' . substr($c, 4);
        if (in_array($c, $plain, true)) continue;
        $plain[] = $c;
        $hashes[] = backup_hash($c);
    }
    return [$plain, $hashes];
}

// Checks a code from the app (replay-protected) or a backup code (used up).
// Changes $t in memory; the caller saves it. Returns 'totp', 'backup' or ''.
function otp_check(array &$t, string $input): string {
    $c = otp_normalize($input);
    if (preg_match('/^\d{' . TOTP_DIGITS . '}$/', $c)) {
        $step = totp_find($t['secret'], $c, TOTP_WINDOW);
        if ($step === null || $step <= (int)$t['last_step']) return '';
        $t['last_step'] = $step;
        return 'totp';
    }
    if (preg_match('/^[' . TOTP_BACKUP_ABC . ']{8}$/', $c)) {
        $h = backup_hash($c);
        foreach ($t['backup'] as $i => $bh) {
            if (is_string($bh) && hash_equals($bh, $h)) {
                array_splice($t['backup'], $i, 1);
                return 'backup';
            }
        }
    }
    return '';
}

// Clock diagnostics: how far (in seconds) the phone's clock is from the server's, or null
function totp_drift(string $secretB32, string $input): ?int {
    $c = otp_normalize($input);
    $step = totp_find($secretB32, $c, TOTP_DRIFT_STEPS);
    if ($step === null) return null;
    return ($step - totp_now_step()) * TOTP_PERIOD;
}

function totp_issuer(): string {
    $cfg = load_config();
    $name = trim(str_replace(':', ' ', (string)($cfg['app_name'] ?? '')));
    return $name !== '' ? $name : 'SmoldPaper';
}

function totp_host(): string {
    $h = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $h = preg_replace('/[^A-Za-z0-9.\-\[\]:]/', '', $h);
    $h = preg_replace('/:\d+$/', '', $h); // the port is not needed in the app label
    return $h !== '' ? $h : 'localhost';
}

// ─── ADMIN: ACTIONS ─────────────────────────────────────

function admin_login(): void {
    $in = get_input();
    $pw = in_str($in, 'password');
    // Without the secret key the login endpoint pretends not to exist
    if (!admin_key_ok(in_str($in, 'k'))) json_out(404, ['error' => 'Unknown']);
    $hs = admin_hash_state();
    if ($hs === 'damaged') json_out(503, ['error' => 'Password file damaged']);
    $ts = totp_state();
    if ($ts === 'damaged' && $hs === 'ok') json_out(503, ['error' => 'Two-factor file damaged']);

    $fh = admin_lock_open();
    $st = admin_lock_read($fh);
    admin_lock_check($fh, $st);

    // No password yet (first run or admin_hash.php deleted via FTP): the setup code signs in
    if ($hs === 'missing') {
        $code = setup_code_get();
        if ($code === '' || !hash_equals($code, otp_normalize($pw))) admin_lock_fail($fh, $st, 'Wrong setup code');
        $ok = admin_lock_reset($fh, $st);
        admin_lock_close($fh);
        if (!$ok) json_out(503, ['error' => 'Server error']);
        $csrf = admin_session_start(true);
        json_out(200, ['status' => 'ok', 'csrf' => $csrf, 'setup' => admin_setup_state(['need_pw' => true])]);
    }

    if (!admin_pw_ok($pw)) admin_lock_fail($fh, $st, 'Wrong password');

    if ($ts === 'on') {
        // Second step. The counter is NOT reset here: only a correct code resets it,
        // otherwise someone who knows the password could guess codes without end.
        admin_lock_close($fh);
        $csrf = admin_session_start(false, 'otp');
        json_out(200, ['status' => 'otp', 'csrf' => $csrf]);
    }

    $ok = admin_lock_reset($fh, $st);
    admin_lock_close($fh);
    if (!$ok) json_out(503, ['error' => 'Server error']);
    $csrf = admin_session_start(false);
    json_out(200, ['status' => 'ok', 'csrf' => $csrf, 'setup' => admin_setup_state([])]);
}

// Second step of sign-in: code from the app or a backup code
function admin_otp(): void {
    $s = admin_session_get();
    if (!$s || $s['stage'] !== 'otp' || !admin_csrf_ok($s)) json_out(401, ['error' => 'Unauthorized']);
    $code = in_str(get_input(), 'code');

    $fh = admin_lock_open();
    $st = admin_lock_read($fh);
    admin_lock_check($fh, $st);

    $ts = totp_state();
    if ($ts === 'damaged') { admin_lock_close($fh); json_out(503, ['error' => 'Two-factor file damaged']); }
    $t = totp_load();
    $kind = 'none';
    if ($t) { // the file may have been deleted via FTP in the meantime: then the password is enough
        $kind = otp_check($t, $code);
        if ($kind === '') admin_lock_fail($fh, $st, 'Wrong code');
        if (!totp_save($t)) { admin_lock_close($fh); json_out(500, ['error' => 'Server error']); }
    }
    $ok = admin_lock_reset($fh, $st);
    admin_lock_close($fh);
    if (!$ok) json_out(503, ['error' => 'Server error']);

    $csrf = admin_session_start(!empty($s['need_pw'])); // new token, the half-session is removed
    json_out(200, [
        'status' => 'ok', 'csrf' => $csrf,
        'setup' => admin_setup_state(['need_pw' => !empty($s['need_pw'])]),
        'used_backup' => $kind === 'backup',
        'backup_left' => $t ? count($t['backup']) : 0
    ]);
}

// Lets the open admin page continue an existing session after a reload.
// Needs the secret key like sign-in: a script elsewhere on the domain cannot pick up the CSRF token.
function admin_status(): void {
    $in = get_input();
    if (!admin_key_ok(in_str($in, 'k'))) json_out(404, ['error' => 'Unknown']);
    $s = admin_session_get();
    if (!$s) {
        $hs = admin_hash_state();
        $damaged = $hs === 'damaged' ? 'hash' : (($hs === 'ok' && totp_state() === 'damaged') ? 'totp' : '');
        json_out(401, ['error' => 'Unauthorized', 'need_code' => $hs === 'missing', 'damaged' => $damaged]);
    }
    if ($s['stage'] === 'otp') json_out(200, ['status' => 'otp', 'csrf' => $s['csrf']]);
    json_out(200, ['status' => 'ok', 'csrf' => $s['csrf'], 'setup' => admin_setup_state($s)]);
}

// ─── ADMIN: TWO-FACTOR MANAGEMENT ───────────────────────

function totp_status(): void {
    admin_require();
    $t = totp_load();
    json_out(200, ['enabled' => (bool)$t, 'backup_left' => $t ? count($t['backup']) : 0]);
}

// Step 1 of turning on: a new secret is kept only in the session until confirmed
function totp_begin(): void {
    $s = admin_require();
    $pw = in_str(get_input(), 'admin_password');
    if (!admin_pw_ok($pw)) admin_session_bad($s, 'Wrong password');
    if (totp_state() !== 'off') json_out(400, ['error' => 'Already enabled']);

    $secret = b32_encode(random_bytes(20));
    $s['totp_pending'] = $secret;
    $s['totp_pending_at'] = time();
    admin_session_save($s);

    $issuer = totp_issuer();
    $label = $issuer . ':' . totp_host();
    $uri = 'otpauth://totp/' . rawurlencode($label) . '?secret=' . $secret
         . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=' . TOTP_DIGITS . '&period=' . TOTP_PERIOD;
    json_out(200, ['status' => 'ok', 'secret' => $secret, 'uri' => $uri, 'label' => $label]);
}

// Step 2: the code from the app proves the phone has the secret; then the file is written.
// Under the sign-in lock, so a double Enter cannot create two different sets of backup codes.
function totp_enable(): void {
    $s = admin_require();
    $code = otp_normalize(in_str(get_input(), 'code'));
    $secret = is_string($s['totp_pending'] ?? null) ? $s['totp_pending'] : '';
    if ($secret === '' || time() - (int)($s['totp_pending_at'] ?? 0) > TOTP_PENDING_TTL)
        json_out(400, ['error' => 'Setup expired']);

    $fh = admin_lock_open();
    if (totp_state() !== 'off') { admin_lock_close($fh); json_out(400, ['error' => 'Already enabled']); }
    $step = totp_find($secret, $code, TOTP_WINDOW);
    if ($step === null) {
        admin_lock_close($fh);
        sleep(1);
        json_out(400, ['error' => 'Wrong code', 'drift' => totp_drift($secret, $code), 'server_time' => time()]);
    }
    [$plain, $hashes] = backup_generate();
    $ok = totp_save(['secret' => $secret, 'last_step' => $step, 'backup' => $hashes, 'created' => time()]);
    admin_lock_close($fh);
    if (!$ok) json_out(500, ['error' => 'Server error']);
    $s = admin_session_get() ?? $s;
    unset($s['totp_pending'], $s['totp_pending_at']);
    admin_session_save($s);
    json_out(200, ['status' => 'ok', 'codes' => $plain]);
}

// "Check clock": tells how far the phone's clock is from the server's.
// Limited per session, because a match within +-5 minutes reveals a code that is valid later.
function totp_check(): void {
    $s = admin_require();
    $s['chk'] = (int)($s['chk'] ?? 0) + 1;
    if ($s['chk'] > TOTP_CHECK_MAX) json_out(429, ['error' => 'Too many checks']);
    admin_session_save($s);
    $code = otp_normalize(in_str(get_input(), 'code'));
    if (!totp_load()) json_out(400, ['error' => 'Not enabled']);
    if (!preg_match('/^\d{' . TOTP_DIGITS . '}$/', $code)) json_out(400, ['error' => 'Wrong code', 'drift' => null, 'server_time' => time()]);

    $fh = admin_lock_open(); // the same lock keeps last_step consistent with sign-in
    $t = totp_load();
    $step = $t ? totp_find($t['secret'], $code, TOTP_WINDOW) : null;
    if ($step !== null) {
        if ($step > $t['last_step']) { $t['last_step'] = $step; totp_save($t); }
        admin_lock_close($fh);
        json_out(200, ['status' => 'ok', 'drift' => 0, 'server_time' => time()]);
    }
    admin_lock_close($fh);
    sleep(1);
    json_out(400, ['error' => 'Wrong code', 'drift' => $t ? totp_drift($t['secret'], $code) : null, 'server_time' => time()]);
}

// Password + code (from the app or a backup one) for disabling and for new backup codes
function totp_require_both(): array {
    $s = admin_require();
    $in = get_input();
    if (!admin_pw_ok(in_str($in, 'admin_password'))) admin_session_bad($s, 'Wrong password');
    $fh = admin_lock_open();
    $t = totp_load();
    if (!$t) { admin_lock_close($fh); json_out(400, ['error' => 'Not enabled']); }
    if (otp_check($t, in_str($in, 'code')) === '') { admin_lock_close($fh); admin_session_bad($s, 'Wrong code'); }
    return [$fh, $t];
}

function totp_disable(): void {
    [$fh, $t] = totp_require_both();
    $ok = svc_delete('admin_totp');
    admin_lock_close($fh);
    if (!$ok) json_out(500, ['error' => 'Server error']);
    json_out(200, ['status' => 'ok']);
}

function totp_new_codes(): void {
    [$fh, $t] = totp_require_both();
    [$plain, $hashes] = backup_generate();
    $t['backup'] = $hashes; // old unused codes stop working right away
    $ok = totp_save($t);
    admin_lock_close($fh);
    if (!$ok) json_out(500, ['error' => 'Server error']);
    json_out(200, ['status' => 'ok', 'codes' => $plain]);
}

function admin_setup(): void {
    $s = admin_require(true);
    $st = admin_setup_state($s);
    $in = get_input();
    $np = in_str($in, 'new_password');
    $k  = in_str($in, 'key');

    // Validate everything first, then write
    if ($st['need_pw']) {
        $err = new_pw_error($np);
        if ($err !== '') json_out(400, ['error' => $err]);
    }
    if ($st['need_key']) {
        if (!valid_admin_key($k)) json_out(400, ['error' => 'Invalid key']);
        if (admin_key_weak($k)) json_out(400, ['error' => 'Key too simple']);
    }

    if ($st['need_pw']) {
        if (!admin_hash_write($np)) json_out(500, ['error' => 'Server error']);
        svc_delete('setup-code');
        $s['need_pw'] = false;
        admin_session_save($s);
    }
    if ($st['need_key'] && !svc_write('admin_key', hash('sha256', $k))) json_out(500, ['error' => 'Server error']);
    json_out(200, ['status' => 'ok', 'key_set' => $st['need_key']]);
}

function admin_logout(): void {
    $s = admin_session_get();
    if ($s) admin_session_end($s);
    else admin_set_cookie('', time() - 3600);
    json_out(200, ['status' => 'ok']);
}

function change_pw(): void {
    $s = admin_require();
    $in = get_input();
    if (!admin_pw_ok(in_str($in, 'admin_password'))) admin_session_bad($s, 'Wrong password');
    $np = in_str($in, 'new_password');
    $err = new_pw_error($np);
    if ($err !== '') json_out(400, ['error' => $err]);
    if (!admin_hash_write($np)) json_out(500, ['error' => 'Server error']);
    svc_delete('admin_pending'); // a half-session of someone who knew the old password
    admin_session_end();         // sign in again with the new password
    json_out(200, ['status' => 'ok']);
}

function change_key(): void {
    $s = admin_require();
    $in = get_input();
    if (!admin_pw_ok(in_str($in, 'admin_password'))) admin_session_bad($s, 'Wrong password');
    $k = in_str($in, 'key');
    if (!valid_admin_key($k)) json_out(400, ['error' => 'Invalid key']);
    if (admin_key_weak($k)) json_out(400, ['error' => 'Key too simple']);
    if (!svc_write('admin_key', hash('sha256', $k))) json_out(500, ['error' => 'Server error']);
    json_out(200, ['status' => 'ok']);
}

// ─── TEXTS AND SETTINGS ─────────────────────────────────

function get_texts(): void {
    $t = svc_json('texts');
    json_out(200, ['texts' => $t ?: new \stdClass()]);
}

// Only an object "language – key – string" is accepted, with size limits
function save_texts(): void {
    admin_require();
    $in = get_input();
    if (!array_key_exists('texts', $in)) json_out(400, ['error' => 'Missing texts']);
    $texts = $in['texts'];
    if (!is_array($texts)) json_out(400, ['error' => 'Invalid value: texts']);
    $out = [];
    foreach ($texts as $lang => $vals) {
        if (!is_string($lang) || !in_array($lang, TEXT_LANGS, true) || !is_array($vals))
            json_out(400, ['error' => 'Invalid value: texts']);
        foreach ($vals as $k => $v) {
            if (!is_string($k) || !preg_match('/^[a-z0-9_]{1,40}$/', $k) || !is_string($v))
                json_out(400, ['error' => 'Invalid value: texts.' . $lang]);
            $lim = $k === 'about_html' ? 60000 : 20000;
            if (mb_strlen($v, 'UTF-8') > $lim) json_out(400, ['error' => 'Invalid value: texts.' . $lang . '.' . $k]);
            $out[$lang][$k] = $v;
        }
    }
    $json = json_encode($out ?: new \stdClass(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
    if (strlen($json) > 600000) json_out(400, ['error' => 'Texts too large']);
    if (!svc_write('texts', $json)) json_out(500, ['error' => 'Server error']);
    json_out(200, ['status' => 'saved']);
}

function get_app_config(): void {
    $cfg = load_config();
    json_out(200, ['config' => [
        'version'           => SP_VERSION,
        'protocol'          => SP_PROTOCOL
    ]]);
}



// ─── ROUTER ─────────────────────────────────────────────

if (defined('SP_LIB')) return;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store, max-age=0');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");

// One answer for every unexpected error: no paths, no SQL in the reply
set_exception_handler(function (Throwable $e) {
    $busy = $e instanceof PDOException && preg_match('/locked|busy/i', $e->getMessage());
    if (!headers_sent()) {
        http_response_code($busy ? 503 : 500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => $busy ? 'Server busy' : 'Server error']);
});

$routes = [
    'POST:create_note'     => 'create_note',
    'POST:note_meta'       => 'note_meta',
    'POST:read_note'       => 'read_note',
    'POST:create_room'     => 'create_room',
    'POST:send_message'    => 'send_message',
    'POST:poll_messages'   => 'poll_messages',
    'POST:destroy_room'    => 'destroy_room',
    'POST:create_qchat'    => 'create_qchat',
    'POST:join_qchat'      => 'join_qchat',
    'POST:check_qchat'     => 'check_qchat',
    'POST:leave_room'      => 'leave_room',
    'GET:get_texts'        => 'get_texts',
    'POST:save_texts'      => 'save_texts',
    'POST:change_password' => 'change_pw',
    'POST:change_key'      => 'change_key',
    'POST:admin_login'     => 'admin_login',
    'POST:admin_status'    => 'admin_status',
    'POST:admin_setup'     => 'admin_setup',
    'POST:admin_logout'    => 'admin_logout',
    'POST:admin_otp'       => 'admin_otp',
    'POST:totp_status'     => 'totp_status',
    'POST:totp_begin'      => 'totp_begin',
    'POST:totp_enable'     => 'totp_enable',
    'POST:totp_check'      => 'totp_check',
    'POST:totp_disable'    => 'totp_disable',
    'POST:totp_new_codes'  => 'totp_new_codes',
    'GET:get_config'       => 'get_app_config',
];

$action = $_GET['action'] ?? '';
if (!is_string($action)) $action = '';
$key = ($_SERVER['REQUEST_METHOD'] ?? 'GET') . ':' . $action;
if (!isset($routes[$key])) json_out(404, ['error' => 'Unknown']);

sp_bootstrap();
check_rate_limit(rate_bucket($action));
$db = open_db();
// Cleanup only after the rate limiter
$cleanup = true;
if ($cleanup) maybe_cleanup($db, load_config());
$routes[$key]($db);
