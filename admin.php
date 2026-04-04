<?php
/**
 * SmoldPaper v3.2 — Admin Panel
 * Скрытый вход: admin.php?manage=1
 * Без параметра — отдаёт 404.
 */

// Если нет секретного параметра — притворяемся что файла нет
if (!isset($_GET['manage']) || $_GET['manage'] !== '1') {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SmoldPaper — Admin</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --bg: #0c0c10;
    --surface: #14141a;
    --card: #1a1a22;
    --input: #1e1e28;
    --border: #2c2c3a;
    --border-focus: #4a4a5c;
    --text: #e0ddd8;
    --text2: #908a80;
    --hint: #585250;
    --accent: #c4a882;
    --accent-dim: #8a7a60;
    --danger: #d45555;
    --success: #55b880;
    --radius: 10px;
}

html { background: var(--bg); color: var(--text); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; font-size: 14px; line-height: 1.6; }

body { min-height: 100vh; display: flex; flex-direction: column; align-items: center; padding: 30px 16px; }

.wrap { width: 100%; max-width: 700px; }

h1 { font-family: 'Courier New', monospace; font-size: 1.3em; letter-spacing: 4px; text-transform: uppercase; color: var(--accent); text-align: center; margin-bottom: 4px; }

.sub { font-size: 0.78em; color: var(--hint); text-align: center; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 24px; }

/* Карточка */
.card { background: var(--card); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; margin-bottom: 16px; box-shadow: 0 2px 12px rgba(0,0,0,0.3); }

.card h2 { font-size: 0.95em; color: var(--accent); letter-spacing: 1px; margin-bottom: 14px; }

/* Поля */
label { display: block; font-size: 0.78em; color: var(--text2); letter-spacing: 0.5px; text-transform: uppercase; margin-bottom: 5px; margin-top: 12px; }

label:first-child { margin-top: 0; }

input, textarea, select {
    width: 100%;
    background: var(--input);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text);
    font-family: 'Courier New', monospace;
    font-size: 0.92em;
    padding: 10px 12px;
    outline: none;
    transition: border-color 0.25s;
}

input:focus, textarea:focus { border-color: var(--border-focus); box-shadow: 0 0 0 3px rgba(196,168,130,0.08); }

textarea { min-height: 80px; resize: vertical; line-height: 1.5; }

select {
    -webkit-appearance: none;
    appearance: none;
    cursor: pointer;
    font-family: inherit;
}

/* Кнопки */
.btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 11px 20px; border: 1px solid var(--border); border-radius: 8px;
    background: var(--card); color: var(--text); font-family: inherit;
    font-size: 0.88em; cursor: pointer; transition: all 0.2s; margin-top: 12px;
}
.btn:hover { border-color: var(--border-focus); background: var(--input); }
.btn-accent { border-color: var(--accent-dim); color: var(--accent); background: rgba(196,168,130,0.08); }
.btn-accent:hover { background: rgba(196,168,130,0.15); border-color: var(--accent); }
.btn-danger { border-color: rgba(212,85,85,0.25); color: var(--danger); }
.btn-full { width: 100%; }

.btn-row { display: flex; gap: 10px; margin-top: 12px; }
.btn-row .btn { flex: 1; margin-top: 0; }

/* Статус */
.status { font-size: 0.82em; min-height: 20px; margin-top: 8px; text-align: center; }
.status.ok { color: var(--success); }
.status.err { color: var(--danger); }
.status.wait { color: var(--accent-dim); }

/* Вкладки языков */
.lang-tabs { display: flex; gap: 4px; margin-bottom: 12px; flex-wrap: wrap; }
.lang-tab {
    padding: 7px 14px; border: 1px solid var(--border); border-radius: 8px;
    background: transparent; color: var(--text2); font-family: inherit;
    font-size: 0.82em; cursor: pointer; transition: all 0.2s; letter-spacing: 1px;
}
.lang-tab:hover { border-color: var(--accent-dim); }
.lang-tab.active { background: rgba(196,168,130,0.1); border-color: var(--accent); color: var(--accent); }

/* Группа полей одного языка */
.lang-group { display: none; }
.lang-group.active { display: block; }

/* Список ключей */
.key-field { margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid rgba(44,44,58,0.5); }
.key-field:last-child { border-bottom: none; margin-bottom: 0; }
.key-name { font-family: 'Courier New', monospace; font-size: 0.78em; color: var(--accent-dim); margin-bottom: 4px; }

/* Логин-форма */
#login-view, #admin-view { display: none; }
#login-view.active, #admin-view.active { display: block; }

/* Анимация */
@keyframes fadeIn { from { opacity:0; transform:translateY(6px); } to { opacity:1; transform:translateY(0); } }
.card { animation: fadeIn 0.3s ease; }

/* Адаптив */
@media (max-width: 480px) {
    body { padding: 20px 10px; }
    .card { padding: 16px; }
    .btn { padding: 12px 16px; }
}
</style>
</head>
<body>
<div class="wrap">
    <h1>SmoldPaper</h1>
    <div class="sub">Administration Panel</div>

    <!-- ═══ ЛОГИН ═══ -->
    <div id="login-view" class="active">
        <div class="card">
            <h2>🔐 Authentication</h2>
            <label>Admin Password</label>
            <input type="password" id="login-pw" placeholder="Enter admin password" onkeydown="if(event.key==='Enter')doLogin()">
            <button class="btn btn-accent btn-full" onclick="doLogin()">Enter</button>
            <div id="login-status" class="status"></div>
        </div>
    </div>

    <!-- ═══ ПАНЕЛЬ АДМИНА ═══ -->
    <div id="admin-view">

        <!-- Редактор текстов -->
        <div class="card">
            <h2>📝 Interface Texts</h2>
            <p style="font-size:0.82em;color:var(--text2);margin-bottom:12px">
                Edit texts for each language. Empty fields will use built-in defaults.
                The <code style="background:var(--input);padding:2px 5px;border-radius:3px">about_html</code> field supports HTML.
            </p>

            <div class="lang-tabs" id="lang-tabs"></div>
            <div id="lang-editors"></div>

            <div class="btn-row">
                <button class="btn btn-accent" onclick="saveTexts()">💾 Save all texts</button>
                <button class="btn" onclick="loadTexts()">↻ Reload</button>
            </div>
            <div id="texts-status" class="status"></div>
        </div>

        <!-- Смена пароля -->
        <div class="card">
            <h2>🔑 Change Password</h2>
            <label>Current Password</label>
            <input type="password" id="cur-pw" placeholder="Current password">
            <label>New Password (min 6 chars)</label>
            <input type="password" id="new-pw" placeholder="New password">
            <label>Confirm New Password</label>
            <input type="password" id="new-pw2" placeholder="Repeat new password" onkeydown="if(event.key==='Enter')changePw()">
            <button class="btn btn-accent btn-full" onclick="changePw()">Change Password</button>
            <div id="pw-status" class="status"></div>
        </div>

        <!-- Выход -->
        <div style="text-align:center;margin-top:12px">
            <button class="btn btn-danger" onclick="doLogout()">🚪 Logout</button>
        </div>
    </div>
</div>

<script>
const API = 'api.php';

const EDITABLE_KEYS = [
    'subtitle', 'mode_note', 'mode_chat',
    'note_label', 'note_placeholder', 'note_pw_label', 'note_pw_placeholder',
    'burn_label', 'burn_read', 'note_encrypt_btn',
    'link_label', 'burn_info_read', 'burn_info_time', 'create_another',
    'read_pw_label', 'read_pw_placeholder', 'decrypt_btn', 'decrypted_label',
    'burn_notice', 'burn_notice_timer', 'back',
    'stealth_label', 'stealth_placeholder', 'identicon_hint',
    'nickname_label', 'nickname_placeholder', 'stealth_btn',
    'identicon_verify', 'chat_pw_label', 'chat_pw_placeholder', 'enter_chat_btn',
    'waiting_partner', 'both_connected', 'chat_welcome', 'chat_input_placeholder',
    'typing_text', 'export_btn', 'shred_btn', 'shred_confirm',
    'room_destroyed', 'send_error', 'no_messages',
    'copied', 'copy_fallback',
    'encrypting', 'connecting', 'generating_key', 'decrypting', 'loading',
    'wrong_password', 'note_expired', 'network_error',
    'min_chars', 'enter_password', 'write_something', 'decrypt_fail',
    'close', 'show_wallets', 'hide_wallets',
    'donate_text', 'donate_text2', 'donate_text3',
    'footer_text', 'room_timer_label', 'idle_timer', 'phrase_busy',
    
    // Кнопка About
    'about_btn',
    
    // Ключи инструкции Стелс-связи
    'htu_title', 'htu_sub', 
    'htu_1_t', 'htu_1_d', 
    'htu_2_t', 'htu_2_d', 
    'htu_3_t', 'htu_3_d', 
    'htu_4_t', 'htu_4_d',

    // Ключи инструкции Записок
    'htu_note_title', 'htu_n_sub', 
    'htu_n_1_t', 'htu_n_1_d', 
    'htu_n_2_t', 'htu_n_2_d', 
    'htu_n_3_t', 'htu_n_3_d', 
    'htu_n_4_t', 'htu_n_4_d'
];

const LANGS = ['en', 'ru', 'de', 'fr', 'es'];
const LANG_NAMES = { en: 'English', ru: 'Русский', de: 'Deutsch', fr: 'Français', es: 'Español' };

let adminPassword = '';
let textsData = {};
let currentLang = 'en';

// ─── API ──────────────────────────────────────────────────

async function apiPost(action, body) {
    const res = await fetch(`${API}?action=${action}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
    });
    return res.json();
}

async function apiGet(action) {
    const res = await fetch(`${API}?action=${action}`);
    return res.json();
}

// ─── ЛОГИН ────────────────────────────────────────────────

async function doLogin() {
    const pw = document.getElementById('login-pw').value;
    if (!pw) return setStatus('login-status', 'Enter password', 'err');

    setStatus('login-status', 'Authenticating...', 'wait');
    try {
        const res = await apiPost('admin_login', { admin_password: pw });
        if (res.error) {
            setStatus('login-status', 'Wrong password', 'err');
            return;
        }
        adminPassword = pw;
        document.getElementById('login-view').classList.remove('active');
        document.getElementById('admin-view').classList.add('active');
        buildEditor();
        loadTexts();
    } catch(e) {
        setStatus('login-status', 'Connection error', 'err');
    }
}

function doLogout() {
    adminPassword = '';
    textsData = {};
    document.getElementById('admin-view').classList.remove('active');
    document.getElementById('login-view').classList.add('active');
    document.getElementById('login-pw').value = '';
}

// ─── ПОСТРОЕНИЕ РЕДАКТОРА ─────────────────────────────────

function buildEditor() {
    // Вкладки языков
    const tabsEl = document.getElementById('lang-tabs');
    tabsEl.innerHTML = LANGS.map(l =>
        `<button class="lang-tab ${l === currentLang ? 'active' : ''}" onclick="switchLang('${l}')">${l.toUpperCase()} — ${LANG_NAMES[l]}</button>`
    ).join('');

    // Группы полей
    const editorsEl = document.getElementById('lang-editors');
    editorsEl.innerHTML = LANGS.map(l => {
        let html = `<div class="lang-group ${l === currentLang ? 'active' : ''}" id="lang-${l}">`;

        // Обычные ключи — input
        for (const key of EDITABLE_KEYS) {
            html += `<div class="key-field">`;
            html += `<div class="key-name">${key}</div>`;
            html += `<input type="text" id="txt-${l}-${key}" placeholder="(default)" data-lang="${l}" data-key="${key}">`;
            html += `</div>`;
        }

        // about_html — textarea
        html += `<div class="key-field">`;
        html += `<div class="key-name">about_html <span style="color:var(--hint)">(supports HTML)</span></div>`;
        html += `<textarea id="txt-${l}-about_html" placeholder="(default)" rows="8" data-lang="${l}" data-key="about_html"></textarea>`;
        html += `</div>`;

        html += `</div>`;
        return html;
    }).join('');
}

function switchLang(lang) {
    currentLang = lang;
    document.querySelectorAll('.lang-tab').forEach(t => t.classList.toggle('active', t.textContent.startsWith(lang.toUpperCase())));
    document.querySelectorAll('.lang-group').forEach(g => g.classList.toggle('active', g.id === 'lang-' + lang));
}

// ─── ЗАГРУЗКА / СОХРАНЕНИЕ ────────────────────────────────

async function loadTexts() {
    setStatus('texts-status', 'Loading...', 'wait');
    try {
        const res = await apiGet('get_texts');
        textsData = res.texts || {};

        // Заполняем поля
        for (const l of LANGS) {
            const langData = textsData[l] || {};
            for (const key of [...EDITABLE_KEYS, 'about_html']) {
                const el = document.getElementById(`txt-${l}-${key}`);
                if (el) el.value = langData[key] || '';
            }
        }

        setStatus('texts-status', 'Loaded ✓', 'ok');
        setTimeout(() => setStatus('texts-status', ''), 2000);
    } catch(e) {
        setStatus('texts-status', 'Load error', 'err');
    }
}

async function saveTexts() {
    setStatus('texts-status', 'Saving...', 'wait');

    // Собираем данные из полей
    const data = {};
    for (const l of LANGS) {
        data[l] = {};
        for (const key of [...EDITABLE_KEYS, 'about_html']) {
            const el = document.getElementById(`txt-${l}-${key}`);
            if (el && el.value.trim()) {
                data[l][key] = el.value.trim();
            }
        }
        // Если языковой объект пустой — не включаем
        if (Object.keys(data[l]).length === 0) delete data[l];
    }

    try {
        const res = await apiPost('save_texts', {
            admin_password: adminPassword,
            texts: data
        });
        if (res.error) {
            setStatus('texts-status', res.error, 'err');
            return;
        }
        textsData = data;
        setStatus('texts-status', 'Saved ✓', 'ok');
        setTimeout(() => setStatus('texts-status', ''), 2000);
    } catch(e) {
        setStatus('texts-status', 'Save error', 'err');
    }
}

// ─── СМЕНА ПАРОЛЯ ─────────────────────────────────────────

async function changePw() {
    const cur = document.getElementById('cur-pw').value;
    const np  = document.getElementById('new-pw').value;
    const np2 = document.getElementById('new-pw2').value;

    if (!cur) return setStatus('pw-status', 'Enter current password', 'err');
    if (np.length < 6) return setStatus('pw-status', 'Min 6 characters', 'err');
    if (np !== np2) return setStatus('pw-status', 'Passwords do not match', 'err');

    setStatus('pw-status', 'Changing...', 'wait');
    try {
        const res = await apiPost('change_password', {
            admin_password: cur,
            new_password: np
        });
        if (res.error) {
            setStatus('pw-status', res.error, 'err');
            return;
        }
        adminPassword = np;
        document.getElementById('cur-pw').value = '';
        document.getElementById('new-pw').value = '';
        document.getElementById('new-pw2').value = '';
        setStatus('pw-status', 'Password changed ✓', 'ok');
        setTimeout(() => setStatus('pw-status', ''), 3000);
    } catch(e) {
        setStatus('pw-status', 'Error', 'err');
    }
}

// ─── УТИЛИТЫ ──────────────────────────────────────────────

function setStatus(id, text, type) {
    const el = document.getElementById(id);
    if (el) { el.textContent = text; el.className = 'status' + (type ? ' ' + type : ''); }
}

// Фокус на поле пароля при загрузке
document.getElementById('login-pw').focus();
</script>
</body>
</html>
