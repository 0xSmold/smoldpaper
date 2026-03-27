<?php
/**
 * SmoldPaper Backend API
 * * Zero-Knowledge Secret Stash
 * - Stores encrypted payloads only
 * - Physical DELETE on read/expiration
 * - Brute-force protection
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$dbFile = __DIR__ . '/database.sqlite';
$firstInit = !file_exists($dbFile);

try {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Initial Database Setup
    if ($firstInit) {
        $db->exec("CREATE TABLE IF NOT EXISTS messages (
            id TEXT PRIMARY KEY,
            room_hash TEXT,
            payload TEXT,
            public_label TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME,
            max_reads INTEGER DEFAULT 1,
            current_reads INTEGER DEFAULT 0,
            failed_attempts INTEGER DEFAULT 0
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT
        )");

        // Set default admin credentials and footer
        $db->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('admin_password', 'admin123')");
        $db->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('footer_html', 'End-to-End Encrypted. <a href=\"https://github.com/0xSmold/smoldpaper\" target=\"_blank\">GitHub</a>')");
        
        // Auto-protect ONLY the database files (Fixed 403 Forbidden issue)
        $htaccess = __DIR__ . '/.htaccess';
        if (!file_exists($htaccess)) {
            $htaccessContent = "<FilesMatch \"\\.(sqlite|sqlite3|db)$\">\nOrder allow,deny\nDeny from all\n</FilesMatch>";
            file_put_contents($htaccess, $htaccessContent);
        }
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    // Cleanup expired messages on every request
    $db->exec("DELETE FROM messages WHERE expires_at < DATETIME('now')");

    switch ($action) {
        case 'get_settings':
            $stmt = $db->query("SELECT * FROM settings");
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            echo json_encode(['success' => true, 'data' => $settings]);
            break;

        case 'admin_login':
            $pass = $input['password'] ?? '';
            $stmt = $db->prepare("SELECT value FROM settings WHERE key = 'admin_password'");
            $stmt->execute();
            $realPass = $stmt->fetchColumn();
            echo json_encode(['success' => ($pass === $realPass)]);
            break;

        case 'save_settings':
            $adminPass = $input['admin_password'] ?? '';
            $key = $input['key'] ?? '';
            $value = $input['value'] ?? '';

            // Security check
            $stmt = $db->prepare("SELECT value FROM settings WHERE key = 'admin_password'");
            $stmt->execute();
            if ($adminPass !== $stmt->fetchColumn()) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }

            // Execute update
            $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)");
            $stmt->execute([$key, $value]);
            echo json_encode(['success' => true]);
            break;

        case 'get_messages':
            $roomHash = $input['room_hash'] ?? '';
            $stmt = $db->prepare("SELECT id, public_label, created_at, expires_at, max_reads, current_reads, failed_attempts FROM messages WHERE room_hash = ? ORDER BY created_at DESC");
            $stmt->execute([$roomHash]);
            echo json_encode(['success' => true, 'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'post_message':
            $id = bin2hex(random_bytes(8));
            $roomHash = $input['room_hash'] ?? '';
            $payload = $input['payload'] ?? '';
            $label = $input['public_label'] ?? '';
            $maxReads = intval($input['max_reads'] ?? 1);
            $expiresMin = intval($input['expires_minutes'] ?? 60);
            
            $stmt = $db->prepare("INSERT INTO messages (id, room_hash, payload, public_label, expires_at, max_reads) VALUES (?, ?, ?, ?, DATETIME('now', '+$expiresMin minutes'), ?)");
            $stmt->execute([$id, $roomHash, $payload, $label, $maxReads]);
            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'get_payload':
            $id = $input['id'] ?? '';
            $roomHash = $input['room_hash'] ?? '';
            $stmt = $db->prepare("SELECT payload FROM messages WHERE id = ? AND room_hash = ?");
            $stmt->execute([$id, $roomHash]);
            $payload = $stmt->fetchColumn();
            echo json_encode(['success' => !!$payload, 'payload' => $payload]);
            break;

        case 'mark_read':
            $id = $input['id'] ?? '';
            $db->prepare("UPDATE messages SET current_reads = current_reads + 1 WHERE id = ?")->execute([$id]);
            // Auto-burn if limit reached
            $stmt = $db->prepare("SELECT 1 FROM messages WHERE id = ? AND current_reads >= max_reads");
            $stmt->execute([$id]);
            if ($stmt->fetch()) {
                $db->prepare("DELETE FROM messages WHERE id = ?")->execute([$id]);
                echo json_encode(['success' => true, 'deleted' => true]);
            } else {
                echo json_encode(['success' => true, 'deleted' => false]);
            }
            break;

        case 'register_fail':
            $id = $input['id'] ?? '';
            $db->prepare("UPDATE messages SET failed_attempts = failed_attempts + 1 WHERE id = ?")->execute([$id]);
            $stmt = $db->prepare("SELECT failed_attempts FROM messages WHERE id = ?");
            $stmt->execute([$id]);
            $fails = $stmt->fetchColumn();
            
            if ($fails >= 5) {
                $db->prepare("DELETE FROM messages WHERE id = ?")->execute([$id]);
                echo json_encode(['success' => true, 'deleted' => true]);
            } else {
                echo json_encode(['success' => true, 'deleted' => false, 'attempts_left' => 5 - $fails]);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
