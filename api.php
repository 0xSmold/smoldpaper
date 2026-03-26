<?php
/**
 * SmoldPaper Backend API (Zero-Knowledge Architecture)
 * Этот скрипт отвечает исключительно за хранение зашифрованных "контейнеров".
 * Сервер физически не имеет доступа к ключам шифрования и не может 
 * прочитать содержимое базы данных.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // Разрешаем запросы с любых доменов
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

// === АВТОМАТИЧЕСКАЯ ЗАЩИТА БАЗЫ ДАННЫХ И HTTPS ===
$htaccessFile = __DIR__ . '/.htaccess';
if (!file_exists($htaccessFile)) {
    $htContent = "RewriteEngine On\nRewriteCond %{HTTPS} off\nRewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]\n\n<Files ~ \"\\.sqlite$\">\n    Order allow,deny\n    Deny from all\n</Files>";
    file_put_contents($htaccessFile, $htContent);
}

$dbFile = __DIR__ . '/database.sqlite';
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// === ОПТИМИЗАЦИЯ SQLITE ДЛЯ ВЫСОКИХ НАГРУЗОК ===
// WAL (Write-Ahead Logging) позволяет читать базу во время записи
$pdo->exec("PRAGMA journal_mode = WAL;");
$pdo->exec("PRAGMA synchronous = NORMAL;");
$pdo->exec("PRAGMA busy_timeout = 5000;");

// Структура таблицы зашифрованных сообщений
$pdo->exec("CREATE TABLE IF NOT EXISTS messages (
    id TEXT PRIMARY KEY,
    room_hash TEXT NOT NULL,
    payload TEXT NOT NULL,
    public_label TEXT DEFAULT '',
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    expires_at TEXT NOT NULL,
    max_reads INTEGER DEFAULT 1,
    current_reads INTEGER DEFAULT 0,
    failed_attempts INTEGER DEFAULT 0
)");

// Структура таблицы настроек и словарей
$pdo->exec("CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT
)");

// Сидирование: установка пароля администратора по умолчанию
$stmt = $pdo->query("SELECT value FROM settings WHERE key = 'admin_password'");
if (!$stmt->fetchColumn()) {
    $pdo->prepare("INSERT INTO settings (key, value) VALUES ('admin_password', :pass)")->execute(['pass' => 'admin123']);
}

// Сидирование: текст футера по умолчанию
$stmt = $pdo->query("SELECT value FROM settings WHERE key = 'footer_html'");
if (!$stmt->fetchColumn()) {
    $pdo->prepare("INSERT INTO settings (key, value) VALUES ('footer_html', :val)")->execute(['val' => 'E2E шифрование. Мы не храним ключи, не читаем сообщения и удаляем их аппаратно.']);
}

/**
 * Проверка пароля администратора
 */
function verifyAdmin($password, $pdo) {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'admin_password'");
    $stmt->execute();
    $realPass = $stmt->fetchColumn();
    if (!$realPass) $realPass = 'admin123';
    return $password === $realPass;
}

/**
 * Аппаратный уборщик (Garbage Collector)
 */
function cleanupDatabase($pdo) {
    $stmt = $pdo->prepare("DELETE FROM messages WHERE datetime(expires_at) <= datetime('now') OR current_reads >= max_reads OR failed_attempts >= 5");
    $stmt->execute();
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);
$action = $data['action'] ?? '';

// === ОПТИМИЗАЦИЯ ОЧИСТКИ ===
// Вызываем уборщика только 1 раз из 50 запросов, чтобы не "пилить" диск
if (mt_rand(1, 50) === 1) {
    cleanupDatabase($pdo);
}

// ==========================================
// БЛОК 1: СИСТЕМНЫЕ МЕТОДЫ И АДМИН-ПАНЕЛЬ
// ==========================================

if ($action === 'get_settings') {
    $stmt = $pdo->query("SELECT key, value FROM settings");
    $all = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    echo json_encode([
        'success' => true,
        'data' => [
            'footer_html' => $all['footer_html'] ?? '',
            'custom_dict_ru' => $all['custom_dict_ru'] ?? '{}',
            'custom_dict_en' => $all['custom_dict_en'] ?? '{}',
            'custom_dict_de' => $all['custom_dict_de'] ?? '{}',
            'custom_dict_fr' => $all['custom_dict_fr'] ?? '{}',
            'custom_dict_es' => $all['custom_dict_es'] ?? '{}'
        ]
    ]);
    exit;
}

if ($action === 'admin_login') {
    $pass = $data['password'] ?? '';
    if (verifyAdmin($pass, $pdo)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Неверный пароль']);
    }
    exit;
}

if ($action === 'save_settings') {
    $admin_password = $data['admin_password'] ?? '';
    if (!verifyAdmin($admin_password, $pdo)) { 
        echo json_encode(['success' => false, 'message' => 'Доступ запрещен']); 
        exit; 
    }

    $key = $data['key'] ?? '';
    $val = $data['value'] ?? '';
    if ($key) {
        $stmt = $pdo->prepare("INSERT INTO settings (key, value) VALUES (:key, :value) ON CONFLICT(key) DO UPDATE SET value = :value");
        $stmt->execute(['key' => $key, 'value' => $val]);
        echo json_encode(['success' => true]);
    } else { 
        echo json_encode(['success' => false]); 
    }
    exit;
}

// ==========================================
// БЛОК 2: МЕТОДЫ ТАЙНИКА (КОМНАТ)
// ==========================================

if ($action === 'get_messages') {
    $room_hash = $data['room_hash'] ?? '';
    if (!$room_hash) { echo json_encode(['success' => false]); exit; }
    
    $stmt = $pdo->prepare("SELECT id, public_label, created_at, expires_at, max_reads, current_reads, failed_attempts FROM messages WHERE room_hash = :hash ORDER BY created_at DESC");
    $stmt->execute(['hash' => $room_hash]);
    echo json_encode(['success' => true, 'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($action === 'post_message') {
    $room_hash = $data['room_hash'] ?? '';
    $payload = $data['payload'] ?? ''; 
    $public_label = $data['public_label'] ?? '';
    $minutes = (int)($data['expires_minutes'] ?? 10);
    $max_reads = (int)($data['max_reads'] ?? 1);

    if (!$room_hash || !$payload) { echo json_encode(['success' => false]); exit; }

    $id = bin2hex(random_bytes(8)); 
    $expires_at = gmdate('Y-m-d H:i:s', time() + ($minutes * 60)); 

    $stmt = $pdo->prepare("INSERT INTO messages (id, room_hash, payload, public_label, expires_at, max_reads) VALUES (:id, :hash, :payload, :label, :expires, :reads)");
    $stmt->execute(['id' => $id, 'hash' => $room_hash, 'payload' => $payload, 'label' => $public_label, 'expires' => $expires_at, 'reads' => $max_reads]);

    echo json_encode(['success' => true, 'id' => $id]);
    exit;
}

if ($action === 'get_payload') {
    $id = $data['id'] ?? '';
    $room_hash = $data['room_hash'] ?? '';

    $stmt = $pdo->prepare("SELECT payload, max_reads, current_reads FROM messages WHERE id = :id AND room_hash = :hash");
    $stmt->execute(['id' => $id, 'hash' => $room_hash]);
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($msg) {
        echo json_encode(['success' => true, 'payload' => $msg['payload']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Сообщение не найдено, удалено или истек срок.']);
    }
    exit;
}

if ($action === 'register_fail') {
    $id = $data['id'] ?? '';
    
    $stmt = $pdo->prepare("UPDATE messages SET failed_attempts = failed_attempts + 1 WHERE id = :id");
    $stmt->execute(['id' => $id]);
    
    $stmtCheck = $pdo->prepare("SELECT failed_attempts FROM messages WHERE id = :id");
    $stmtCheck->execute(['id' => $id]);
    $attempts = $stmtCheck->fetchColumn();
    
    if ($attempts >= 5) {
        $pdo->prepare("DELETE FROM messages WHERE id = :id")->execute(['id' => $id]);
        echo json_encode(['success' => true, 'deleted' => true, 'message' => 'Сообщение уничтожено.']);
    } else {
        echo json_encode(['success' => true, 'deleted' => false, 'attempts_left' => 5 - $attempts]);
    }
    exit;
}

if ($action === 'mark_read') {
    $id = $data['id'] ?? '';
    $stmt = $pdo->prepare("UPDATE messages SET current_reads = current_reads + 1 WHERE id = :id");
    $stmt->execute(['id' => $id]);
    
    $stmtCheck = $pdo->prepare("SELECT current_reads, max_reads FROM messages WHERE id = :id");
    $stmtCheck->execute(['id' => $id]);
    $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    
    $deleted = false;
    if ($row && $row['current_reads'] >= $row['max_reads']) {
        $pdo->prepare("DELETE FROM messages WHERE id = :id")->execute(['id' => $id]);
        $deleted = true;
    }
    
    echo json_encode(['success' => true, 'deleted' => $deleted]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Неизвестная команда']);