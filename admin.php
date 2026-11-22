<?php
/**
 * SmoldPaper v4.0.0 — Admin Panel
 * Entry: admin.php?k=<secret key> once the owner has set a key (data/admin_key.php).
 * Before that (first run, or after deleting data/admin_key.php via FTP): admin.php?manage=1
 * Any other address returns 404. A damaged key file closes the panel for everyone.
 */

define('SP_LIB', true);
require __DIR__ . '/api.php';

function admin_page_404(): void {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 Not Found</h1></body></html>';
    exit;
}

try {
    sp_bootstrap();
    $keyState = admin_key_state();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Server error: the data folder next to admin.php is not writable.';
    exit;
}

if ($keyState === 'ok') {
    $k = $_GET['k'] ?? '';
    $allowed = is_string($k) && $k !== '' && admin_key_ok($k);
} elseif ($keyState === 'none') {
    $allowed = ($_GET['manage'] ?? '') === '1';
} else {
    $allowed = false;
}
if (!$allowed) admin_page_404();

header('Content-Type: text/html; charset=utf-8');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, max-age=0');
header("Content-Security-Policy: default-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'; object-src 'none'");
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<meta name="referrer" content="no-referrer">
<title>SmoldPaper – Admin</title>
<link rel="icon" href="favicon.svg?v=2" type="image/svg+xml">
<script>
// Apply saved theme before first paint (same storage key as the main site)
(function(){var t='dark';try{if(localStorage.getItem('sp_t')==='light')t='light'}catch(e){}document.documentElement.setAttribute('data-theme',t)})();
</script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

/* Theme palettes – identical to index.html */
[data-theme="dark"]{
  --bg:#1a1815;--card:#282620;--card2:#302c26;--input:#1a1815;
  --border:#3a3530;--border2:#4a4540;
  --text:#b8ab93;--text2:#908578;--hint:#6a6055;
  --red1:#b52e37;--red2:#8a2028;--red-text:#e04050;
  --accent:#b8ab93;--accent2:#c8b8a0;--accent-bg:rgba(184,171,147,.08);
  --green:#6ab88a;--green-bg:rgba(106,184,138,.1);
  --danger:#e04040;
  --shadow:0 2px 16px rgba(0,0,0,.5);
  --grad:linear-gradient(135deg,#b52e37,#8a2028);
}
[data-theme="light"]{
  --bg:#ede8df;--card:#f5f1ea;--card2:#eee9e0;--input:#e8e3da;
  --border:#d8d0c4;--border2:#c8c0b4;
  --text:#2a2420;--text2:#706050;--hint:#a09888;
  --red1:#b52e37;--red2:#8a2028;--red-text:#a01028;
  --accent:#8a7050;--accent2:#a08060;--accent-bg:rgba(138,112,80,.08);
  --green:#408860;--green-bg:rgba(64,136,96,.06);
  --danger:#c04040;
  --shadow:0 2px 12px rgba(0,0,0,.06);
  --grad:linear-gradient(135deg,#b52e37,#8a2028);
}

html{background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:15.5px;line-height:1.6;transition:background .2s,color .2s}
body{min-height:100vh;min-height:100dvh;display:flex;flex-direction:column;align-items:center;padding:0 16px 24px}
.container{width:100%;max-width:680px}

/* Header */
.header{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 0;margin-bottom:8px}
.logo{display:flex;align-items:center;gap:10px;min-width:0}
.logo-icon{width:36px;height:36px;border-radius:10px;background:var(--grad);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.logo-icon svg{width:22px;height:22px;color:#fff}
.logo-text{font-weight:700;font-size:1.05em;letter-spacing:1px;color:var(--text)}
.logo-sub{font-size:.68em;color:var(--hint);letter-spacing:.5px;text-transform:uppercase}
.toolbar{display:flex;align-items:center;gap:6px;flex-shrink:0}
.tool-btn{display:flex;align-items:center;justify-content:center;width:36px;height:36px;border:1px solid var(--border);border-radius:10px;background:var(--card);color:var(--text2);cursor:pointer;transition:all .2s}
.tool-btn:hover{border-color:var(--red1);color:var(--red-text)}
.tool-btn svg{width:18px;height:18px}
.tool-select{height:36px;padding:0 10px;border:1px solid var(--border);border-radius:10px;background:var(--card);color:var(--text2);font-family:inherit;font-size:.8em;cursor:pointer;outline:none;-webkit-appearance:none;appearance:none;font-weight:600}

/* Cards */
.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:24px;margin-bottom:16px;box-shadow:var(--shadow);transition:background .2s;animation:vIn .25s ease}
.card h2{font-size:1.05em;color:var(--red-text);font-weight:800;margin-bottom:12px}
.desc{font-size:.85em;color:var(--text2);line-height:1.6;margin-bottom:12px}
.note{font-size:.75em;color:var(--hint);line-height:1.55;margin:5px 0 4px}
@keyframes vIn{from{opacity:0;transform:translateY(5px)}to{opacity:1;transform:translateY(0)}}

/* Fields */
.lbl{display:block;font-size:.78em;color:var(--text2);font-weight:600;margin:16px 0 5px}
.lbl:first-of-type{margin-top:0}
.hint{color:var(--hint);font-weight:400}
input[type="text"],input[type="password"],input[type="number"],textarea{width:100%;background:var(--input);border:1px solid var(--border);border-radius:10px;color:var(--text);font-family:inherit;font-size:.95em;padding:11px 14px;outline:none;transition:border .2s,box-shadow .2s;-webkit-appearance:none}
input:focus,textarea:focus{border-color:var(--red1);box-shadow:0 0 0 3px rgba(181,46,55,.12)}
textarea{min-height:70px;resize:vertical;line-height:1.5}
.mono{font-family:'Courier New',monospace;font-size:.85em}
input[type="checkbox"]{width:16px;height:16px;accent-color:var(--red1);cursor:pointer;flex-shrink:0}
.check{display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.88em;color:var(--text)}
code{background:var(--input);border:1px solid var(--border);padding:1px 6px;border-radius:5px;font-family:'Courier New',monospace;font-size:.88em;overflow-wrap:anywhere}

/* Buttons */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:13px 20px;border:none;border-radius:12px;font-family:inherit;font-size:.92em;font-weight:600;cursor:pointer;transition:all .2s;margin-top:16px}
.btn:active{transform:scale(.98)}
.btn:disabled{opacity:.5;cursor:not-allowed}
.btn-primary{background:var(--grad);color:#fff;box-shadow:0 4px 16px rgba(181,46,55,.2)}
.btn-primary:hover{box-shadow:0 6px 24px rgba(181,46,55,.3);filter:brightness(1.05)}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--text)}
.btn-outline:hover{border-color:var(--border2);background:var(--card2)}
.btn-danger{background:transparent;border:1px solid rgba(224,64,64,.3);color:var(--danger)}
.btn-danger:hover{background:rgba(224,64,64,.08);border-color:var(--danger)}
.btn-sm{padding:10px 16px;font-size:.82em;border-radius:10px}
.btn-row{display:flex;gap:10px;margin-top:16px}
.btn-row .btn{flex:1;margin-top:0}

/* Status line */
.st{text-align:center;font-size:.82em;color:var(--hint);margin-top:10px;min-height:18px}
.st.ok{color:var(--green)}.st.err{color:var(--danger)}.st.wait{color:var(--text2)}

/* Views: nothing is shown until the server says which screen is needed (no sign-in flash) */
.view{display:none}
.view.active{display:block}
#boot-view .st{opacity:0;animation:bootIn .3s ease .6s forwards}
@keyframes bootIn{to{opacity:1}}

/* Admin layout: side menu + one section at a time */
body.wide .container{max-width:1040px}
.layout{display:flex;gap:20px;align-items:flex-start}
.side{width:230px;flex-shrink:0;position:sticky;top:12px;background:var(--card);border:1px solid var(--border);border-radius:16px;padding:10px;box-shadow:var(--shadow);max-height:calc(100vh - 24px);overflow-y:auto}
.nav-grp{font-size:.68em;color:var(--hint);text-transform:uppercase;letter-spacing:.6px;font-weight:700;padding:12px 10px 4px}
.nav-grp:first-child{padding-top:4px}
.nav-item{display:block;width:100%;text-align:left;padding:9px 10px;border:1px solid transparent;border-radius:10px;background:transparent;color:var(--text2);font-family:inherit;font-size:.86em;font-weight:600;cursor:pointer;transition:all .15s;line-height:1.35}
.nav-item:hover{color:var(--text);background:var(--card2)}
.nav-item.active{background:var(--input);border-color:var(--border);color:var(--red-text)}
.nav-sep{border-top:1px solid var(--border);margin:10px 4px}
.nav-out{color:var(--danger)}
.nav-ver{margin:14px 8px 4px;font-size:.75em;color:var(--hint);text-align:center}
.main{flex:1;min-width:0}
.sec{display:none}
.sec.active{display:block}
.menu-btn{display:none;width:100%;align-items:center;gap:10px;padding:11px 14px;margin-bottom:12px;border:1px solid var(--border);border-radius:12px;background:var(--card);color:var(--text);font-family:inherit;font-size:.9em;font-weight:700;cursor:pointer;box-shadow:var(--shadow)}
.menu-btn .mb-icon{font-size:1.15em;color:var(--red-text)}
.menu-btn .mb-cur{flex:1;text-align:left;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.side-shade{display:none}
@media(max-width:860px){
  .layout{display:block}
  .menu-btn{display:flex}
  .side{position:fixed;top:0;left:0;bottom:0;z-index:50;width:270px;max-width:85vw;max-height:none;border-radius:0 16px 16px 0;transform:translateX(-105%);transition:transform .2s ease;padding-top:16px}
  .side.open{transform:translateX(0)}
  .side-shade.open{display:block;position:fixed;inset:0;z-index:40;background:rgba(0,0,0,.45)}
}

/* Two-factor */
.sub{border-top:1px solid var(--border);margin-top:20px;padding-top:4px}
.sub h3{font-size:.92em;color:var(--text);font-weight:700;margin:14px 0 6px}
.badge{display:inline-block;padding:6px 12px;border-radius:10px;font-size:.85em;font-weight:700;margin-bottom:12px}
.badge.on{background:var(--green-bg);color:var(--green);border:1px solid var(--green)}
.badge.off{background:var(--input);color:var(--text2);border:1px solid var(--border)}
.badge.low{background:rgba(224,64,64,.08);color:var(--danger);border:1px solid var(--danger)}
.qr-box{text-align:center;margin:10px 0 6px}
.qr-box canvas{width:220px;max-width:100%;image-rendering:pixelated;border-radius:8px;background:#fff}
.secret{background:var(--input);border:1px solid var(--border);border-radius:10px;padding:10px 12px;font-family:'Courier New',monospace;font-size:.95em;letter-spacing:1px;color:var(--text);word-break:break-all;user-select:all;text-align:center}
.codes{display:grid;grid-template-columns:repeat(2,1fr);gap:8px;margin:12px 0}
.codes div{background:var(--input);border:1px solid var(--border);border-radius:8px;padding:8px;text-align:center;font-family:'Courier New',monospace;font-size:1.02em;font-weight:700;letter-spacing:1px;color:var(--text)}
.hint-box{display:none;margin-top:12px;padding:12px 14px;border-radius:10px;font-size:.84em;line-height:1.6;border:1px solid var(--border);background:var(--card2);color:var(--text)}
.hint-box.show{display:block}
.hint-box.warn{border-color:var(--danger);background:rgba(224,64,64,.06)}
.hint-box.ok{border-color:var(--green);background:var(--green-bg)}
.hint-box .btn{margin-top:10px}
.otp-input{font-family:'Courier New',monospace;font-size:1.25em!important;letter-spacing:3px;text-align:center}

/* Secret address key */
#setup-pw-box .lbl:first-of-type,#setup-key-box .lbl:first-of-type{margin-top:16px}
.key-row{display:flex;gap:8px;align-items:stretch}
.key-row input{flex:1;min-width:0}
.key-row .btn{width:auto;margin-top:0;white-space:nowrap}
.addr{background:var(--input);border:1px solid var(--border);border-radius:10px;padding:12px 14px;font-family:'Courier New',monospace;font-size:.85em;color:var(--text);word-break:break-all;user-select:all}

/* Interface texts editor */
.lang-tabs{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:14px;background:var(--input);border:1px solid var(--border);border-radius:12px;padding:4px}
.lang-tab{flex:1;min-width:90px;padding:8px 6px;border:1px solid transparent;border-radius:9px;background:transparent;color:var(--text2);font-family:inherit;font-size:.8em;font-weight:700;cursor:pointer;transition:all .2s}
.lang-tab:hover{color:var(--text)}
.lang-tab.active{background:var(--card);border-color:var(--border);color:var(--red-text)}
.lang-group{display:none}.lang-group.active{display:block}
details.grp{border:1px solid var(--border);border-radius:12px;margin-bottom:10px;background:var(--card2);overflow:hidden}
details.grp summary{cursor:pointer;padding:12px 16px;font-weight:700;font-size:.86em;color:var(--text);list-style:none;display:flex;align-items:center;gap:10px}
details.grp summary::-webkit-details-marker{display:none}
details.grp summary::after{content:'▾';margin-left:auto;color:var(--hint);transition:transform .2s}
details.grp[open] summary::after{transform:rotate(180deg)}
.grp-n{font-size:.85em;color:var(--hint);font-weight:400}
.grp-body{padding:2px 16px 12px;border-top:1px solid var(--border)}
.key-field{padding:10px 0;border-bottom:1px solid var(--border)}
.key-field:last-child{border-bottom:none}
.key-name{font-family:'Courier New',monospace;font-size:.75em;color:var(--hint);margin-bottom:4px}


@media(max-width:480px){
  body{padding:0 10px 16px}
  .card{padding:18px;border-radius:14px}
  .header{padding:10px 0}
  .logo-sub{display:none}
  .btn-row,.log-btns,.key-row{flex-direction:column}
}
</style>
</head>
<body>
<div class="container">

<svg style="display:none" xmlns="http://www.w3.org/2000/svg">
  <symbol id="i-flame" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></symbol>
  <symbol id="i-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></symbol>
  <symbol id="i-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></symbol>
</svg>

<div class="header">
  <div class="logo">
    <div class="logo-icon"><svg><use href="#i-flame"/></svg></div>
    <div><div class="logo-text">SMOLDPAPER</div><div class="logo-sub" data-i18n="admin_panel"></div></div>
  </div>
  <div class="toolbar">
    <select class="tool-select" id="sel-lang" onchange="setLang(this.value)" data-i18n-title="lang_label"><option value="en">EN</option><option value="ru">RU</option><option value="de">DE</option><option value="fr">FR</option><option value="es">ES</option></select>
    <button class="tool-btn" id="btn-theme" onclick="toggleTheme()" data-i18n-title="theme_toggle"><svg><use href="#i-sun"/></svg></button>
  </div>
</div>

<!-- ═══ LOADING (until admin_status answers) ═══ -->
<div id="boot-view" class="view active"><div class="st" data-i18n="st_loading"></div></div>

<!-- ═══ LOGIN (password, or the setup code on the first run) ═══ -->
<div id="login-view" class="view">
  <div class="card">
    <h2 id="login-title" data-i18n="login_title"></h2>
    <p class="desc" id="login-code-note" style="display:none" data-i18n="login_code_note"></p>
    <div id="login-form">
      <label class="lbl" for="login-pw" id="login-label" data-i18n="login_pw_label"></label>
      <input type="password" id="login-pw" data-i18n-ph="login_pw_ph" autocomplete="current-password" spellcheck="false" onkeydown="if(event.key==='Enter')doLogin()">
    </div>
    <button class="btn btn-primary" id="btn-login" onclick="doLogin()" data-i18n="login_btn"></button>
    <div id="login-status" class="st"></div>
  </div>
</div>

<!-- ═══ SECOND STEP: ONE-TIME CODE ═══ -->
<div id="otp-view" class="view">
  <div class="card">
    <h2 data-i18n="otp_title"></h2>
    <p class="desc" data-i18n="otp_desc"></p>
    <label class="lbl" for="otp-code" data-i18n="otp_label"></label>
    <input type="text" id="otp-code" class="otp-input" data-i18n-ph="otp_ph" autocomplete="one-time-code" autocapitalize="characters" autocorrect="off" spellcheck="false" maxlength="12" onkeydown="if(event.key==='Enter')doOtp()">
    <p class="note" data-i18n="otp_backup_note"></p>
    <div class="btn-row">
      <button class="btn btn-outline" onclick="doLogout()" data-i18n="cancel_btn"></button>
      <button class="btn btn-primary" onclick="doOtp()" data-i18n="otp_btn"></button>
    </div>
    <div id="otp-status" class="st"></div>
    <p class="note" data-i18n="otp_clock_note"></p>
  </div>
</div>

<!-- ═══ FIRST-TIME SETUP ═══ -->
<div id="setup-view" class="view">
  <div class="card">
    <h2 data-i18n="setup_title"></h2>
    <p class="desc" data-i18n="setup_desc"></p>
    <div id="setup-pw-box">
      <p class="note" data-i18n="setup_pw_note"></p>
      <label class="lbl" for="setup-pw" data-i18n="pw_new_label"></label>
      <input type="password" id="setup-pw" data-i18n-ph="pw_new_ph" autocomplete="new-password">
      <label class="lbl" for="setup-pw2" data-i18n="pw_rep_label"></label>
      <input type="password" id="setup-pw2" data-i18n-ph="pw_rep_ph" autocomplete="new-password">
    </div>
    <div id="setup-key-box">
      <label class="lbl" for="setup-key" data-i18n="key_label"></label>
      <div class="key-row">
        <input type="text" id="setup-key" class="mono" data-i18n-ph="key_ph" autocomplete="off" spellcheck="false" maxlength="64">
        <button class="btn btn-outline btn-sm" onclick="genKeyInto('setup-key')" data-i18n="key_gen_btn"></button>
      </div>
      <p class="note" data-i18n="key_desc"></p>
    </div>
    <button class="btn btn-primary" onclick="doSetup()" data-i18n="setup_btn"></button>
    <div id="setup-status" class="st"></div>
  </div>
  <div style="text-align:center">
    <button class="btn btn-danger btn-sm" style="width:auto" onclick="doLogout()" data-i18n="logout_btn"></button>
  </div>
</div>

<!-- ═══ NEW ADMIN ADDRESS ═══ -->
<div id="done-view" class="view">
  <div class="card">
    <h2 data-i18n="done_title"></h2>
    <p class="desc" data-i18n="done_desc"></p>
    <div class="addr" id="done-url"></div>
    <div class="btn-row">
      <button class="btn btn-outline" onclick="copyDoneUrl()" data-i18n="copy_btn"></button>
      <button class="btn btn-primary" onclick="goDoneUrl()" data-i18n="done_btn"></button>
    </div>
    <div id="done-status" class="st"></div>
  </div>
</div>

<!-- ═══ ADMIN PANEL ═══ -->
<div id="admin-view" class="view">
<div class="layout">
  <nav class="side" id="side">
    <button class="nav-item" data-sec="texts" data-i18n="texts_title"></button>
    <div class="nav-grp" data-i18n="nav_grp_security"></div>
    <button class="nav-item" data-sec="totp" data-i18n="totp_title"></button>
    <button class="nav-item" data-sec="key" data-i18n="key_title"></button>
    <button class="nav-item" data-sec="pw" data-i18n="pw_title"></button>
    <div class="nav-sep"></div>
    <button class="nav-item nav-out" onclick="doLogout()" data-i18n="logout_btn"></button>
    <div class="nav-ver">SmoldPaper v<?= htmlspecialchars(SP_VERSION) ?></div>
  </nav>
  <div class="side-shade" id="side-shade" onclick="closeMenu()"></div>

  <div class="main">
  <button class="menu-btn" onclick="openMenu()"><span class="mb-icon">☰</span><span class="mb-cur" id="menu-cur"></span><span data-i18n="nav_menu" style="color:var(--hint);font-size:.85em"></span></button>


  <section class="sec" id="sec-texts">
  <div class="card">
    <h2 data-i18n="texts_title"></h2>
    <p class="desc" data-i18n="texts_desc"></p>
    <div class="lang-tabs" id="lang-tabs"></div>
    <div id="lang-editors"></div>
    <div class="btn-row">
      <button class="btn btn-primary" onclick="saveTexts()" data-i18n="texts_save_btn"></button>
      <button class="btn btn-outline" onclick="loadTexts()" data-i18n="texts_reload_btn"></button>
    </div>
    <div id="texts-status" class="st"></div>
  </div>
  </section>


  <section class="sec" id="sec-totp">
  <div class="card">
    <h2 data-i18n="totp_title"></h2>
    <p class="desc" data-i18n="totp_desc"></p>
    <div id="totp-badge" class="badge off"></div>
    <div id="totp-low" class="note" style="color:var(--danger);display:none" data-i18n="totp_low_codes"></div>

    <!-- Off: turn on -->
    <div id="totp-off" style="display:none">
      <label class="lbl" for="totp-pw" data-i18n="pw_cur_label"></label>
      <input type="password" id="totp-pw" data-i18n-ph="pw_cur_ph" autocomplete="current-password" onkeydown="if(event.key==='Enter')totpBegin()">
      <button class="btn btn-primary" id="btn-totp-begin" onclick="totpBegin()" data-i18n="totp_enable_btn"></button>
    </div>

    <!-- Turning on: QR + confirmation code -->
    <div id="totp-setup" style="display:none">
      <p class="desc" data-i18n="totp_step1"></p>
      <div class="qr-box"><canvas id="totp-qr"></canvas></div>
      <div class="note" id="totp-label" style="text-align:center"></div>
      <p class="note" data-i18n="totp_manual"></p>
      <div class="secret" id="totp-secret"></div>
      <div style="text-align:center"><button class="btn btn-outline btn-sm" style="width:auto;margin-top:8px" onclick="copyText(document.getElementById('totp-secret').dataset.raw,'totp-status')" data-i18n="copy_btn"></button></div>
      <label class="lbl" for="totp-code" style="margin-top:18px" data-i18n="totp_step2"></label>
      <input type="text" id="totp-code" class="otp-input" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="123456" onkeydown="if(event.key==='Enter')totpEnable()">
      <div class="btn-row">
        <button class="btn btn-outline" onclick="totpCancel()" data-i18n="cancel_btn"></button>
        <button class="btn btn-primary" id="btn-totp-enable" onclick="totpEnable()" data-i18n="totp_confirm_btn"></button>
      </div>
    </div>

    <!-- Backup codes, shown once -->
    <div id="totp-codes" style="display:none">
      <h3 style="color:var(--red-text);margin-top:4px" data-i18n="totp_codes_title"></h3>
      <p class="desc" data-i18n="totp_codes_desc"></p>
      <div class="codes" id="totp-codes-list"></div>
      <div class="btn-row">
        <button class="btn btn-outline" onclick="copyText(lastCodes.join('\n'),'totp-status')" data-i18n="copy_btn"></button>
        <button class="btn btn-outline" onclick="downloadCodes()" data-i18n="totp_codes_dl"></button>
      </div>
      <button class="btn btn-primary" onclick="codesSaved()" data-i18n="totp_codes_done"></button>
    </div>

    <!-- On: clock check, new codes, turn off -->
    <div id="totp-on" style="display:none">
      <div class="sub">
        <h3 data-i18n="totp_check_title"></h3>
        <p class="note" data-i18n="totp_check_desc"></p>
        <label class="lbl" for="totp-check-code" data-i18n="totp_app_code"></label>
        <input type="text" id="totp-check-code" class="otp-input" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="123456" onkeydown="if(event.key==='Enter')totpCheck()">
        <button class="btn btn-outline" onclick="totpCheck()" data-i18n="totp_check_btn"></button>
        <div id="totp-check-status" class="st"></div>
        <div id="totp-check-hint" class="hint-box"></div>
      </div>
      <div class="sub">
        <h3 data-i18n="totp_manage_title"></h3>
        <p class="note" data-i18n="totp_manage_desc"></p>
        <label class="lbl" for="totp-m-pw" data-i18n="pw_cur_label"></label>
        <input type="password" id="totp-m-pw" data-i18n-ph="pw_cur_ph" autocomplete="current-password">
        <label class="lbl" for="totp-m-code" data-i18n="totp_code_label"></label>
        <input type="text" id="totp-m-code" class="otp-input" autocomplete="one-time-code" autocapitalize="characters" autocorrect="off" spellcheck="false" maxlength="12" data-i18n-ph="otp_ph">
        <button class="btn btn-primary" onclick="totpNewCodes()" data-i18n="totp_newcodes_btn"></button>
        <button class="btn btn-danger" onclick="totpDisable()" data-i18n="totp_disable_btn"></button>
      </div>
      <p class="note" style="margin-top:16px" data-i18n="totp_emergency"></p>
    </div>

    <div id="totp-status" class="st"></div>
    <div id="totp-hint" class="hint-box"></div>
  </div>
  </section>
  <section class="sec" id="sec-key">
  <div class="card">
    <h2 data-i18n="key_title"></h2>
    <p class="desc" data-i18n="key_change_desc"></p>
    <label class="lbl" for="new-key" data-i18n="key_label"></label>
    <div class="key-row">
      <input type="text" id="new-key" class="mono" data-i18n-ph="key_ph" autocomplete="off" spellcheck="false" maxlength="64">
      <button class="btn btn-outline btn-sm" onclick="genKeyInto('new-key')" data-i18n="key_gen_btn"></button>
    </div>
    <p class="note" data-i18n="key_desc"></p>
    <label class="lbl" for="key-cur-pw" data-i18n="pw_cur_label"></label>
    <input type="password" id="key-cur-pw" data-i18n-ph="pw_cur_ph" autocomplete="current-password" onkeydown="if(event.key==='Enter')changeKey()">
    <button class="btn btn-primary" onclick="changeKey()" data-i18n="key_change_btn"></button>
    <div id="key-status" class="st"></div>
  </div>
  </section>
  <section class="sec" id="sec-pw">
  <div class="card">
    <h2 data-i18n="pw_title"></h2>
    <label class="lbl" for="cur-pw" data-i18n="pw_cur_label"></label>
    <input type="password" id="cur-pw" data-i18n-ph="pw_cur_ph" autocomplete="current-password">
    <label class="lbl" for="new-pw" data-i18n="pw_new_label"></label>
    <input type="password" id="new-pw" data-i18n-ph="pw_new_ph" autocomplete="new-password">
    <label class="lbl" for="new-pw2" data-i18n="pw_rep_label"></label>
    <input type="password" id="new-pw2" data-i18n-ph="pw_rep_ph" autocomplete="new-password" onkeydown="if(event.key==='Enter')changePw()">
    <button class="btn btn-primary" onclick="changePw()" data-i18n="pw_btn"></button>
    <div id="pw-status" class="st"></div>
  </div>
  </section>
  </div>
</div>
</div>

</div>
<script>
const API = 'api.php';

// ─── I18N (admin UI) ──────────────────────────────────────
const AL = {
en: {
page_title:"SmoldPaper – Admin",
admin_panel:"Administration panel",
theme_toggle:"Switch theme",
lang_label:"Language",
login_title:"🔐 Sign in",
login_pw_label:"Admin password",
login_pw_ph:"Enter the admin password",
login_btn:"Sign in",
login_code_title:"🛡️ First sign-in",
login_code_label:"Setup code",
login_code_ph:"XXXX-XXXX-XXXX-XXXX",
login_code_note:"Open the file data/setup-code.php on your hosting via FTP or the hosting file manager. The code is on its second line. After you set your own password, the file is deleted automatically. Lost the password later? Delete data/admin_hash.php – a new code will appear.",
wrong_setup_code:"Wrong setup code",
st_enter_pw:"Enter the password",
st_auth:"Checking...",
wrong_password:"Wrong password",
st_conn_err:"Connection error",
st_loading:"Loading...",
st_loaded:"Loaded ✓",
st_load_err:"Loading error",
st_saving:"Saving...",
st_saved:"Saved ✓",
st_save_err:"Saving error",
st_error:"Error",
err_unauthorized:"Session is no longer valid. Please sign in again.",
err_rate:"Too many requests. Wait a minute and try again.",
err_attempts:"Too many wrong passwords or codes in this session. Please sign in again.",
err_damaged_hash:"The password file data/admin_hash.php is damaged. Delete it via FTP: a new setup code will appear in data/setup-code.php.",
err_damaged_totp:"The two-factor file data/admin_totp.php is damaged, so sign-in is closed. Delete it via FTP – sign-in will need only the password – then turn two-factor protection on again.",
err_checks:"Too many clock checks in this session. Sign in again to check once more.",
err_server:"Server error. Try again in a minute.",
err_invalid:"Invalid value: {f}",
err_texts_size:"The texts are too long in total. Shorten them.",
texts_title:"📝 Interface texts",
texts_desc:"Site texts for each language. An empty field uses the built-in text. The <code>about_html</code> field accepts HTML.",
texts_save_btn:"💾 Save all texts",
texts_reload_btn:"↻ Reload",
default_ph:"(default)",
html_supported:"(HTML allowed)",
grp_general:"General: header, footer, buttons",
grp_note:"Note: creating",
grp_read:"Note: reading",
grp_quick:"Quick Chat",
grp_stealth:"Stealth Chat",
grp_chat:"Chat room",
grp_status:"Status messages and errors",
grp_donate:"Donations and sharing",
grp_htu_note:"How-to: Notes",
grp_htu_quick:"How-to: Quick Chat",
grp_htu_stealth:"How-to: Stealth Chat",
grp_video:"How-to videos (file name or YouTube / Vimeo link)",
grp_about:"About window",
pw_title:"🔑 Change password",
pw_cur_label:"Current password",
pw_cur_ph:"Current password",
pw_new_label:"New password (at least 8 characters)",
pw_new_ph:"New password",
pw_rep_label:"Repeat the new password",
pw_rep_ph:"New password once more",
pw_btn:"Change password",
st_enter_cur:"Enter the current password",
st_min8:"At least 8 characters",
st_pw_long:"At most 72 bytes (72 Latin letters or about 36 Cyrillic ones)",
st_mismatch:"Passwords do not match",
st_changing:"Changing...",
st_pw_changed:"Password changed ✓",
logout_btn:"🚪 Sign out",
st_pw_default:"The default password cannot be used",
st_pw_relogin:"Password changed ✓ Sign in with the new password.",
err_locked:"Too many wrong attempts. Sign-in is closed for {n} min. If this is you and you cannot wait, delete data/admin_lock.php via FTP.",
err_setup:"Finish the first-time setup first.",
setup_title:"🛡️ First-time setup",
setup_desc:"Before you can use the admin panel, finish securing it. Until then all other settings are locked.",
setup_pw_note:"You signed in with the one-time setup code. Now set your own admin password – the code file will be deleted.",
setup_btn:"Save and continue",
key_label:"Secret address key",
key_ph:"At least 12 characters",
key_gen_btn:"🎲 Generate",
key_desc:"The admin panel will open only at admin.php?k=YOUR_KEY. Any other address shows “not found”, and sign-in attempts without the key are rejected. Lost the address? Delete data/admin_key.php via FTP – the old address admin.php?manage=1 will work again until you set a new key.",
st_key_rule:"Key: 12 to 64 characters – Latin letters, digits, - and _",
st_key_weak:"The key is too simple. Press “🎲 Generate” to get a strong one.",
done_title:"✅ Your new admin address",
done_desc:"Save this address in your bookmarks or password manager right now. Any other address to the admin panel no longer works.",
done_btn:"Go to the new address",
copy_btn:"📋 Copy",
st_copied:"Copied ✓",
key_title:"🧭 Admin address",
key_change_desc:"Set a new secret key for the admin panel address. The current address will stop working right away.",
key_change_btn:"Change address",
nav_menu:"Menu",
nav_grp_security:"Security",
otp_title:"🔐 Confirm sign-in",
otp_desc:"Two-factor protection is on. Enter the 6-digit code from the authenticator app on your phone.",
otp_label:"Code from the app or backup code",
otp_ph:"123456 or K7M2-QX9P",
otp_backup_note:"No access to the app? Type one of your backup codes (like K7M2-QX9P) into the same field. Each backup code works only once.",
otp_clock_note:"The code is fresh but still not accepted? Sign in with a backup code, open “Two-factor protection” and press “Check clock” – the panel will tell you what to fix.",
otp_btn:"Confirm",
cancel_btn:"Cancel",
st_enter_code:"Enter the code",
st_enter_code6:"Enter the 6-digit code from the app",
wrong_code:"Wrong code",
st_backup_used:"You signed in with a backup code. Backup codes left: {n}. If there are few left, create new ones below.",
st_pending_expired:"The setup took too long. Press “Turn on” again.",
err_already:"Two-factor protection is already on",
err_not_enabled:"Two-factor protection is off",
totp_title:"📱 Two-factor protection",
totp_desc:"After the password, the admin panel will also ask for a one-time code from an app on your phone: Google Authenticator, Aegis, 2FAS, Microsoft Authenticator or any other app for one-time codes (TOTP). Even if someone learns your password, they cannot sign in without your phone.",
totp_off_state:"Off",
totp_on_state:"✅ On · backup codes left: {n}",
totp_low_codes:"Only a few backup codes are left. Create new ones in “Backup codes and turning off” below.",
totp_enable_btn:"Turn on two-factor protection",
totp_step1:"1. Open the authenticator app on your phone, choose “Add” (+) and scan this QR code.",
totp_manual:"Cannot scan? In the app choose to enter a key by hand, type any name and this key (type: time-based):",
totp_step2:"2. Enter the 6-digit code the app now shows for this site:",
totp_confirm_btn:"Confirm and turn on",
totp_enabled_ok:"Two-factor protection is on ✓",
totp_codes_title:"🗝️ Your backup codes",
totp_codes_desc:"Each code lets you sign in once if your phone is not at hand. Save them right now – print them, put them in a password manager or download the file. They are shown only once.",
totp_codes_dl:"📥 Download .txt",
totp_codes_done:"I have saved the codes",
totp_file_head:"SmoldPaper – backup sign-in codes",
totp_file_note:"Each code works only once. Keep this file in a safe place.",
st_codes_new:"New backup codes are ready ✓ The old ones no longer work.",
totp_check_title:"Check clock",
totp_check_desc:"If codes from the app stopped working, enter a fresh code here. The panel compares the clock of the server, your phone and this computer and tells you whose clock is wrong and what to do.",
totp_app_code:"Code from the app",
totp_check_btn:"🕒 Check clock",
totp_manage_title:"Backup codes and turning off",
totp_manage_desc:"Both actions need the admin password and a code. A code from the app or a backup code both work (a backup code is used up).",
totp_code_label:"Code from the app or backup code",
totp_newcodes_btn:"🔄 New backup codes",
totp_disable_btn:"Turn off two-factor protection",
totp_disabled:"Two-factor protection is off ✓",
totp_emergency:"Lost your phone and all backup codes? Delete the file data/admin_totp.php via FTP – sign-in will again need only the password. Then turn the protection on again here.",
hint_ok:"✅ Clock is fine: the code matched, the difference is under 30 seconds. After this check the same code no longer works for signing in or turning off – wait for the next one (60–90 seconds).",
hint_wrong:"The code did not match. Check that you took the code of THIS site in the app (the entry name is shown under the QR code) and enter a fresh code before it changes – codes change every 30 seconds.",
hint_phone:"🕒 The clock on your phone is off by about {n} seconds, while the server and this computer agree.\n\nTurn on automatic time on the phone:\niPhone: Settings → General → Date & Time → “Set Automatically”.\nAndroid: Settings → System → Date & time → “Set time automatically” (on Samsung: General management → Date and time).\n\nThen enter a new code.",
hint_server:"🕒 The server clock is {dir} by about {n} seconds compared with this computer.\n\nThis is fixed by the hosting company: write to their support and ask them to turn on time synchronisation (NTP). The button below copies a ready message.\n\nDo not turn on two-factor protection until the clock is fixed – otherwise the codes will not work and you will not be able to sign in.",
hint_both:"🕒 The time differs on the phone and on the server, and on this computer too. First turn on automatic time on the phone and on this computer, reload the page and check again.",
dir_behind:"behind",
dir_ahead:"ahead",
support_copy_btn:"📋 Copy a message for hosting support",
support_text:"Hello! On my hosting (site {host}) the system clock of the server is {dir} by about {n} seconds. Because of this, one-time codes of two-factor protection do not work. Please turn on time synchronisation via NTP. Thank you!",
},
ru: {
page_title:"SmoldPaper – Админка",
admin_panel:"Панель управления",
theme_toggle:"Сменить тему",
lang_label:"Язык",
login_title:"🔐 Вход",
login_pw_label:"Пароль администратора",
login_pw_ph:"Введите пароль администратора",
login_btn:"Войти",
login_code_title:"🛡️ Первый вход",
login_code_label:"Код установки",
login_code_ph:"XXXX-XXXX-XXXX-XXXX",
login_code_note:"Откройте на хостинге по FTP или в файловом менеджере хостинга файл data/setup-code.php – код записан во второй строке. После того как вы зададите свой пароль, файл удалится сам. Забыли пароль потом? Удалите data/admin_hash.php – появится новый код.",
wrong_setup_code:"Неверный код установки",
st_enter_pw:"Введите пароль",
st_auth:"Проверяем...",
wrong_password:"Неверный пароль",
st_conn_err:"Ошибка соединения",
st_loading:"Загрузка...",
st_loaded:"Загружено ✓",
st_load_err:"Ошибка загрузки",
st_saving:"Сохраняем...",
st_saved:"Сохранено ✓",
st_save_err:"Ошибка сохранения",
st_error:"Ошибка",
err_unauthorized:"Сессия недействительна. Войдите заново.",
err_rate:"Слишком много запросов. Подождите минуту и повторите.",
err_attempts:"Слишком много неверных паролей или кодов в этой сессии. Войдите заново.",
err_damaged_hash:"Файл пароля data/admin_hash.php повреждён. Удалите его по FTP – в data/setup-code.php появится новый код установки.",
err_damaged_totp:"Файл двухфакторной защиты data/admin_totp.php повреждён, поэтому вход закрыт. Удалите его по FTP – вход снова будет только по паролю – и затем включите защиту заново.",
err_checks:"Слишком много проверок часов в этой сессии. Чтобы проверить ещё раз, войдите заново.",
err_server:"Ошибка на сервере. Попробуйте через минуту.",
err_invalid:"Недопустимое значение: {f}",
err_texts_size:"Тексты в сумме слишком длинные. Сократите их.",
texts_title:"📝 Тексты интерфейса",
texts_desc:"Тексты сайта для каждого языка. Пустое поле – используется встроенный текст. В поле <code>about_html</code> можно писать HTML.",
texts_save_btn:"💾 Сохранить все тексты",
texts_reload_btn:"↻ Обновить",
default_ph:"(по умолчанию)",
html_supported:"(можно HTML)",
grp_general:"Общее: шапка, подвал, кнопки",
grp_note:"Записка: создание",
grp_read:"Записка: чтение",
grp_quick:"Быстрый чат",
grp_stealth:"Стелс-связь",
grp_chat:"Комната чата",
grp_status:"Статусы и ошибки",
grp_donate:"Пожертвования и «Поделиться»",
grp_htu_note:"Инструкция: Записки",
grp_htu_quick:"Инструкция: Быстрый чат",
grp_htu_stealth:"Инструкция: Стелс-связь",
grp_video:"Видео в инструкциях (имя файла или ссылка YouTube / Vimeo)",
grp_about:"Окно «О проекте»",
pw_title:"🔑 Смена пароля",
pw_cur_label:"Текущий пароль",
pw_cur_ph:"Текущий пароль",
pw_new_label:"Новый пароль (не короче 8 символов)",
pw_new_ph:"Новый пароль",
pw_rep_label:"Повторите новый пароль",
pw_rep_ph:"Новый пароль ещё раз",
pw_btn:"Сменить пароль",
st_enter_cur:"Введите текущий пароль",
st_min8:"Не короче 8 символов",
st_pw_long:"Не длиннее 72 байт (72 латинские буквы или около 36 русских)",
st_mismatch:"Пароли не совпадают",
st_changing:"Меняем...",
st_pw_changed:"Пароль изменён ✓",
logout_btn:"🚪 Выйти",
st_pw_default:"Стандартный пароль использовать нельзя",
st_pw_relogin:"Пароль изменён ✓ Войдите с новым паролем.",
err_locked:"Слишком много неверных попыток. Вход закрыт на {n} мин. Если это вы и ждать нельзя – удалите по FTP файл data/admin_lock.php.",
err_setup:"Сначала завершите первоначальную настройку.",
setup_title:"🛡️ Первоначальная настройка",
setup_desc:"Прежде чем пользоваться админкой, завершите её защиту. До этого остальные настройки закрыты.",
setup_pw_note:"Вы вошли по одноразовому коду установки. Теперь задайте свой пароль администратора – файл с кодом удалится.",
setup_btn:"Сохранить и продолжить",
key_label:"Секретный ключ адреса",
key_ph:"Не короче 12 символов",
key_gen_btn:"🎲 Сгенерировать",
key_desc:"Админка будет открываться только по адресу admin.php?k=ВАШ_КЛЮЧ. По любому другому адресу – «не найдено», а попытки входа без ключа отклоняются. Потеряли адрес? Удалите файл data/admin_key.php по FTP – снова заработает старый адрес admin.php?manage=1, пока вы не зададите новый ключ.",
st_key_rule:"Ключ: от 12 до 64 символов – латинские буквы, цифры, - и _",
st_key_weak:"Ключ слишком простой. Нажмите «🎲 Сгенерировать» – получится надёжный.",
done_title:"✅ Новый адрес админки",
done_desc:"Сохраните этот адрес в закладки или менеджер паролей прямо сейчас. Другие адреса админки больше не работают.",
done_btn:"Перейти по новому адресу",
copy_btn:"📋 Копировать",
st_copied:"Скопировано ✓",
key_title:"🧭 Адрес админки",
key_change_desc:"Задайте новый секретный ключ для адреса админки. Текущий адрес сразу перестанет работать.",
key_change_btn:"Сменить адрес",
nav_menu:"Меню",
nav_grp_security:"Безопасность",
otp_title:"🔐 Подтверждение входа",
otp_desc:"Включена двухфакторная защита. Введите 6-значный код из приложения-аутентификатора на телефоне.",
otp_label:"Код из приложения или запасной код",
otp_ph:"123456 или K7M2-QX9P",
otp_backup_note:"Нет доступа к приложению? Введите в это же поле один из запасных кодов (вида K7M2-QX9P). Каждый запасной код срабатывает только один раз.",
otp_clock_note:"Код свежий, но не подходит? Войдите запасным кодом, откройте раздел «Двухфакторная защита» и нажмите «Проверить часы» – админка подскажет, что исправить.",
otp_btn:"Подтвердить",
cancel_btn:"Отмена",
st_enter_code:"Введите код",
st_enter_code6:"Введите 6-значный код из приложения",
wrong_code:"Неверный код",
st_backup_used:"Вы вошли запасным кодом. Запасных кодов осталось: {n}. Если их мало – выпустите новые ниже.",
st_pending_expired:"Подключение заняло слишком много времени. Нажмите «Включить» ещё раз.",
err_already:"Двухфакторная защита уже включена",
err_not_enabled:"Двухфакторная защита выключена",
totp_title:"📱 Двухфакторная защита",
totp_desc:"После пароля админка будет спрашивать ещё и одноразовый код из приложения на телефоне: Google Authenticator, Aegis, 2FAS, Microsoft Authenticator или любого другого приложения для одноразовых кодов (TOTP). Даже если кто-то узнает пароль, без вашего телефона он не войдёт.",
totp_off_state:"Выключена",
totp_on_state:"✅ Включена · запасных кодов осталось: {n}",
totp_low_codes:"Запасных кодов осталось мало. Выпустите новые ниже, в блоке «Запасные коды и отключение».",
totp_enable_btn:"Включить двухфакторную защиту",
totp_step1:"1. Откройте на телефоне приложение-аутентификатор, нажмите «Добавить» (+) и отсканируйте этот QR-код.",
totp_manual:"Не получается отсканировать? Выберите в приложении ввод ключа вручную, укажите любое название и этот ключ (тип – по времени):",
totp_step2:"2. Введите 6-значный код, который приложение показывает для этого сайта:",
totp_confirm_btn:"Подтвердить и включить",
totp_enabled_ok:"Двухфакторная защита включена ✓",
totp_codes_title:"🗝️ Ваши запасные коды",
totp_codes_desc:"Каждый код один раз пустит вас в админку, если телефона нет под рукой. Сохраните их прямо сейчас – распечатайте, положите в менеджер паролей или скачайте файл. Они показываются только один раз.",
totp_codes_dl:"📥 Скачать .txt",
totp_codes_done:"Я сохранил коды",
totp_file_head:"SmoldPaper – запасные коды для входа в админку",
totp_file_note:"Каждый код срабатывает только один раз. Храните этот файл в надёжном месте.",
st_codes_new:"Новые запасные коды готовы ✓ Старые больше не работают.",
totp_check_title:"Проверка часов",
totp_check_desc:"Если коды из приложения перестали подходить, введите здесь свежий код. Админка сравнит часы сервера, телефона и этого компьютера и подскажет, у кого время сбито и что делать.",
totp_app_code:"Код из приложения",
totp_check_btn:"🕒 Проверить часы",
totp_manage_title:"Запасные коды и отключение",
totp_manage_desc:"Для обоих действий нужны пароль админки и код. Подойдёт код из приложения или запасной код (запасной при этом сгорит).",
totp_code_label:"Код из приложения или запасной код",
totp_newcodes_btn:"🔄 Новые запасные коды",
totp_disable_btn:"Отключить двухфакторную защиту",
totp_disabled:"Двухфакторная защита отключена ✓",
totp_emergency:"Потеряли телефон и все запасные коды? Удалите по FTP файл data/admin_totp.php – вход снова будет только по паролю. После этого включите защиту здесь заново.",
hint_ok:"✅ Часы в порядке: код подошёл, расхождение меньше 30 секунд. После проверки этот же код уже не подойдёт для входа или отключения – дождитесь следующего (60–90 секунд).",
hint_wrong:"Код не подошёл. Проверьте, что в приложении взят код именно ЭТОГО сайта (название записи показано под QR-кодом), и введите свежий код, пока он не сменился – коды меняются каждые 30 секунд.",
hint_phone:"🕒 Время на телефоне сбито примерно на {n} секунд, а у сервера и этого компьютера оно совпадает.\n\nВключите на телефоне автоматическую установку времени:\niPhone: Настройки → Основные → Дата и время → «Автоматически».\nAndroid: Настройки → Система → Дата и время → «Автоматически» (на Samsung: Общие настройки → Дата и время).\n\nПотом введите новый код.",
hint_server:"🕒 Часы сервера {dir} примерно на {n} секунд по сравнению с этим компьютером.\n\nЭто исправляет хостинг: напишите в его поддержку и попросите включить синхронизацию времени (NTP). Кнопка ниже скопирует готовое письмо.\n\nПока часы не исправлены, двухфакторную защиту не включайте – коды не будут подходить, и вы не сможете войти.",
hint_both:"🕒 Время расходится и на телефоне, и на сервере, и на этом компьютере. Сначала включите автоматическое время на телефоне и на этом компьютере, обновите страницу и проверьте ещё раз.",
dir_behind:"отстают",
dir_ahead:"спешат",
support_copy_btn:"📋 Скопировать письмо для поддержки хостинга",
support_text:"Здравствуйте! На моём хостинге (сайт {host}) системные часы сервера {dir} примерно на {n} секунд. Из-за этого не работают одноразовые коды двухфакторной защиты. Пожалуйста, включите синхронизацию времени по NTP. Спасибо!",
},
de: {
page_title:"SmoldPaper – Verwaltung",
admin_panel:"Verwaltungsbereich",
theme_toggle:"Design wechseln",
lang_label:"Sprache",
login_title:"🔐 Anmeldung",
login_pw_label:"Admin-Passwort",
login_pw_ph:"Admin-Passwort eingeben",
login_btn:"Anmelden",
login_code_title:"🛡️ Erste Anmeldung",
login_code_label:"Einrichtungscode",
login_code_ph:"XXXX-XXXX-XXXX-XXXX",
login_code_note:"Öffnen Sie auf Ihrem Hosting per FTP oder im Dateimanager die Datei data/setup-code.php – der Code steht in der zweiten Zeile. Sobald Sie ein eigenes Passwort festgelegt haben, wird die Datei automatisch gelöscht. Passwort später vergessen? Löschen Sie data/admin_hash.php – dann erscheint ein neuer Code.",
wrong_setup_code:"Falscher Einrichtungscode",
st_enter_pw:"Passwort eingeben",
st_auth:"Wird geprüft...",
wrong_password:"Falsches Passwort",
st_conn_err:"Verbindungsfehler",
st_loading:"Wird geladen...",
st_loaded:"Geladen ✓",
st_load_err:"Fehler beim Laden",
st_saving:"Wird gespeichert...",
st_saved:"Gespeichert ✓",
st_save_err:"Fehler beim Speichern",
st_error:"Fehler",
err_unauthorized:"Sitzung ungültig. Bitte erneut anmelden.",
err_rate:"Zu viele Anfragen. Bitte eine Minute warten und erneut versuchen.",
err_attempts:"Zu viele falsche Passwörter oder Codes in dieser Sitzung. Bitte melden Sie sich erneut an.",
err_damaged_hash:"Die Passwortdatei data/admin_hash.php ist beschädigt. Löschen Sie sie per FTP: In data/setup-code.php erscheint ein neuer Einrichtungscode.",
err_damaged_totp:"Die Zwei-Faktor-Datei data/admin_totp.php ist beschädigt, deshalb ist die Anmeldung gesperrt. Löschen Sie sie per FTP – dann reicht das Passwort – und aktivieren Sie die Absicherung danach erneut.",
err_checks:"Zu viele Uhrprüfungen in dieser Sitzung. Melden Sie sich erneut an, um noch einmal zu prüfen.",
err_server:"Serverfehler. Bitte in einer Minute erneut versuchen.",
err_invalid:"Ungültiger Wert: {f}",
err_texts_size:"Die Texte sind insgesamt zu lang. Bitte kürzen.",
texts_title:"📝 Oberflächentexte",
texts_desc:"Texte der Website für jede Sprache. Ein leeres Feld verwendet den eingebauten Text. Das Feld <code>about_html</code> erlaubt HTML.",
texts_save_btn:"💾 Alle Texte speichern",
texts_reload_btn:"↻ Neu laden",
default_ph:"(Standard)",
html_supported:"(HTML erlaubt)",
grp_general:"Allgemein: Kopfzeile, Fußzeile, Schaltflächen",
grp_note:"Notiz: erstellen",
grp_read:"Notiz: lesen",
grp_quick:"Quick Chat",
grp_stealth:"Stealth-Chat",
grp_chat:"Chatraum",
grp_status:"Statusmeldungen und Fehler",
grp_donate:"Spenden und Teilen",
grp_htu_note:"Anleitung: Notizen",
grp_htu_quick:"Anleitung: Quick Chat",
grp_htu_stealth:"Anleitung: Stealth-Chat",
grp_video:"Anleitungsvideos (Dateiname oder YouTube-/Vimeo-Link)",
grp_about:"Fenster „Über uns“",
pw_title:"🔑 Passwort ändern",
pw_cur_label:"Aktuelles Passwort",
pw_cur_ph:"Aktuelles Passwort",
pw_new_label:"Neues Passwort (mindestens 8 Zeichen)",
pw_new_ph:"Neues Passwort",
pw_rep_label:"Neues Passwort wiederholen",
pw_rep_ph:"Neues Passwort noch einmal",
pw_btn:"Passwort ändern",
st_enter_cur:"Aktuelles Passwort eingeben",
st_min8:"Mindestens 8 Zeichen",
st_pw_long:"Höchstens 72 Byte (72 lateinische Buchstaben oder etwa 36 kyrillische)",
st_mismatch:"Passwörter stimmen nicht überein",
st_changing:"Wird geändert...",
st_pw_changed:"Passwort geändert ✓",
logout_btn:"🚪 Abmelden",
st_pw_default:"Das Standardpasswort ist nicht erlaubt",
st_pw_relogin:"Passwort geändert ✓ Melden Sie sich mit dem neuen Passwort an.",
err_locked:"Zu viele Fehlversuche. Die Anmeldung ist für {n} Min. gesperrt. Wenn Sie es sind und nicht warten können, löschen Sie data/admin_lock.php per FTP.",
err_setup:"Schließen Sie zuerst die Ersteinrichtung ab.",
setup_title:"🛡️ Ersteinrichtung",
setup_desc:"Bevor Sie den Verwaltungsbereich nutzen, schließen Sie seine Absicherung ab. Bis dahin sind alle anderen Einstellungen gesperrt.",
setup_pw_note:"Sie haben sich mit dem einmaligen Einrichtungscode angemeldet. Legen Sie jetzt Ihr eigenes Admin-Passwort fest – die Datei mit dem Code wird gelöscht.",
setup_btn:"Speichern und fortfahren",
key_label:"Geheimer Adressschlüssel",
key_ph:"Mindestens 12 Zeichen",
key_gen_btn:"🎲 Erzeugen",
key_desc:"Der Verwaltungsbereich öffnet sich nur unter admin.php?k=IHR_SCHLÜSSEL. Jede andere Adresse zeigt „nicht gefunden“, und Anmeldeversuche ohne Schlüssel werden abgelehnt. Adresse verloren? Löschen Sie data/admin_key.php per FTP – dann funktioniert die alte Adresse admin.php?manage=1 wieder, bis Sie einen neuen Schlüssel festlegen.",
st_key_rule:"Schlüssel: 12 bis 64 Zeichen – lateinische Buchstaben, Ziffern, - und _",
st_key_weak:"Der Schlüssel ist zu einfach. Drücken Sie „🎲 Erzeugen“ für einen sicheren.",
done_title:"✅ Ihre neue Verwaltungsadresse",
done_desc:"Speichern Sie diese Adresse jetzt als Lesezeichen oder im Passwortmanager. Andere Adressen zum Verwaltungsbereich funktionieren nicht mehr.",
done_btn:"Zur neuen Adresse",
copy_btn:"📋 Kopieren",
st_copied:"Kopiert ✓",
key_title:"🧭 Verwaltungsadresse",
key_change_desc:"Legen Sie einen neuen geheimen Schlüssel für die Adresse des Verwaltungsbereichs fest. Die aktuelle Adresse funktioniert sofort nicht mehr.",
key_change_btn:"Adresse ändern",
nav_menu:"Menü",
nav_grp_security:"Sicherheit",
otp_title:"🔐 Anmeldung bestätigen",
otp_desc:"Die Zwei-Faktor-Absicherung ist aktiv. Geben Sie den 6-stelligen Code aus der Authenticator-App auf Ihrem Telefon ein.",
otp_label:"Code aus der App oder Notfallcode",
otp_ph:"123456 oder K7M2-QX9P",
otp_backup_note:"Kein Zugriff auf die App? Geben Sie in dasselbe Feld einen Ihrer Notfallcodes ein (z. B. K7M2-QX9P). Jeder Notfallcode funktioniert nur einmal.",
otp_clock_note:"Der Code ist frisch, wird aber nicht angenommen? Melden Sie sich mit einem Notfallcode an, öffnen Sie „Zwei-Faktor-Absicherung“ und drücken Sie „Uhr prüfen“ – der Verwaltungsbereich sagt Ihnen, was zu tun ist.",
otp_btn:"Bestätigen",
cancel_btn:"Abbrechen",
st_enter_code:"Code eingeben",
st_enter_code6:"6-stelligen Code aus der App eingeben",
wrong_code:"Falscher Code",
st_backup_used:"Sie haben sich mit einem Notfallcode angemeldet. Verbleibende Notfallcodes: {n}. Wenn es nur noch wenige sind, erzeugen Sie unten neue.",
st_pending_expired:"Die Einrichtung hat zu lange gedauert. Drücken Sie erneut „Aktivieren“.",
err_already:"Die Zwei-Faktor-Absicherung ist bereits aktiv",
err_not_enabled:"Die Zwei-Faktor-Absicherung ist aus",
totp_title:"📱 Zwei-Faktor-Absicherung",
totp_desc:"Nach dem Passwort fragt der Verwaltungsbereich zusätzlich nach einem Einmalcode aus einer App auf Ihrem Telefon: Google Authenticator, Aegis, 2FAS, Microsoft Authenticator oder jede andere App für Einmalcodes (TOTP). Selbst wer Ihr Passwort kennt, kommt ohne Ihr Telefon nicht hinein.",
totp_off_state:"Aus",
totp_on_state:"✅ Aktiv · verbleibende Notfallcodes: {n}",
totp_low_codes:"Es sind nur noch wenige Notfallcodes übrig. Erzeugen Sie unten unter „Notfallcodes und Abschalten“ neue.",
totp_enable_btn:"Zwei-Faktor-Absicherung aktivieren",
totp_step1:"1. Öffnen Sie die Authenticator-App auf dem Telefon, wählen Sie „Hinzufügen“ (+) und scannen Sie diesen QR-Code.",
totp_manual:"Scannen klappt nicht? Wählen Sie in der App die manuelle Eingabe, einen beliebigen Namen und diesen Schlüssel (Typ: zeitbasiert):",
totp_step2:"2. Geben Sie den 6-stelligen Code ein, den die App jetzt für diese Website zeigt:",
totp_confirm_btn:"Bestätigen und aktivieren",
totp_enabled_ok:"Zwei-Faktor-Absicherung aktiv ✓",
totp_codes_title:"🗝️ Ihre Notfallcodes",
totp_codes_desc:"Jeder Code erlaubt eine Anmeldung, wenn das Telefon nicht zur Hand ist. Sichern Sie sie jetzt – drucken Sie sie aus, legen Sie sie im Passwortmanager ab oder laden Sie die Datei herunter. Sie werden nur einmal angezeigt.",
totp_codes_dl:"📥 Als .txt herunterladen",
totp_codes_done:"Ich habe die Codes gesichert",
totp_file_head:"SmoldPaper – Notfallcodes für die Anmeldung",
totp_file_note:"Jeder Code funktioniert nur einmal. Bewahren Sie diese Datei sicher auf.",
st_codes_new:"Neue Notfallcodes sind bereit ✓ Die alten funktionieren nicht mehr.",
totp_check_title:"Uhr prüfen",
totp_check_desc:"Wenn Codes aus der App nicht mehr funktionieren, geben Sie hier einen frischen Code ein. Der Verwaltungsbereich vergleicht die Uhren von Server, Telefon und diesem Computer und sagt Ihnen, welche Uhr falsch geht und was zu tun ist.",
totp_app_code:"Code aus der App",
totp_check_btn:"🕒 Uhr prüfen",
totp_manage_title:"Notfallcodes und Abschalten",
totp_manage_desc:"Für beide Aktionen braucht es das Admin-Passwort und einen Code. Ein Code aus der App oder ein Notfallcode funktioniert (der Notfallcode wird dabei verbraucht).",
totp_code_label:"Code aus der App oder Notfallcode",
totp_newcodes_btn:"🔄 Neue Notfallcodes",
totp_disable_btn:"Zwei-Faktor-Absicherung abschalten",
totp_disabled:"Zwei-Faktor-Absicherung abgeschaltet ✓",
totp_emergency:"Telefon und alle Notfallcodes verloren? Löschen Sie die Datei data/admin_totp.php per FTP – dann reicht für die Anmeldung wieder das Passwort. Aktivieren Sie die Absicherung danach hier neu.",
hint_ok:"✅ Die Uhr stimmt: Der Code passt, die Abweichung liegt unter 30 Sekunden. Nach dieser Prüfung gilt derselbe Code nicht mehr für Anmeldung oder Abschalten – warten Sie auf den nächsten (60–90 Sekunden).",
hint_wrong:"Der Code passt nicht. Prüfen Sie, ob Sie in der App den Code genau DIESER Website genommen haben (der Eintragsname steht unter dem QR-Code), und geben Sie einen frischen Code ein, bevor er wechselt – Codes wechseln alle 30 Sekunden.",
hint_phone:"🕒 Die Uhr Ihres Telefons weicht um etwa {n} Sekunden ab, Server und dieser Computer stimmen überein.\n\nAktivieren Sie die automatische Zeit auf dem Telefon:\niPhone: Einstellungen → Allgemein → Datum & Uhrzeit → „Automatisch einstellen“.\nAndroid: Einstellungen → System → Datum und Uhrzeit → „Uhrzeit automatisch einstellen“ (bei Samsung: Allgemeine Verwaltung → Datum und Uhrzeit).\n\nGeben Sie danach einen neuen Code ein.",
hint_server:"🕒 Die Serveruhr geht im Vergleich zu diesem Computer etwa {n} Sekunden {dir}.\n\nDas behebt der Hoster: Schreiben Sie dem Support und bitten Sie, die Zeitsynchronisation (NTP) einzuschalten. Die Schaltfläche unten kopiert eine fertige Nachricht.\n\nAktivieren Sie die Zwei-Faktor-Absicherung erst, wenn die Uhr stimmt – sonst passen die Codes nicht und Sie können sich nicht anmelden.",
hint_both:"🕒 Die Zeit weicht auf dem Telefon, auf dem Server und auch auf diesem Computer ab. Aktivieren Sie zuerst die automatische Zeit auf Telefon und Computer, laden Sie die Seite neu und prüfen Sie erneut.",
dir_behind:"nach",
dir_ahead:"vor",
support_copy_btn:"📋 Nachricht für den Hoster-Support kopieren",
support_text:"Guten Tag! Auf meinem Hosting (Website {host}) geht die Systemuhr des Servers etwa {n} Sekunden {dir}. Deshalb funktionieren die Einmalcodes der Zwei-Faktor-Absicherung nicht. Bitte schalten Sie die Zeitsynchronisation per NTP ein. Vielen Dank!",
},
fr: {
page_title:"SmoldPaper – Administration",
admin_panel:"Panneau d’administration",
theme_toggle:"Changer de thème",
lang_label:"Langue",
login_title:"🔐 Connexion",
login_pw_label:"Mot de passe administrateur",
login_pw_ph:"Saisissez le mot de passe administrateur",
login_btn:"Se connecter",
login_code_title:"🛡️ Première connexion",
login_code_label:"Code d’installation",
login_code_ph:"XXXX-XXXX-XXXX-XXXX",
login_code_note:"Ouvrez sur votre hébergement, par FTP ou dans le gestionnaire de fichiers, le fichier data/setup-code.php – le code se trouve sur sa deuxième ligne. Dès que vous aurez défini votre propre mot de passe, le fichier sera supprimé automatiquement. Mot de passe oublié plus tard ? Supprimez data/admin_hash.php – un nouveau code apparaîtra.",
wrong_setup_code:"Code d’installation incorrect",
st_enter_pw:"Saisissez le mot de passe",
st_auth:"Vérification...",
wrong_password:"Mot de passe incorrect",
st_conn_err:"Erreur de connexion",
st_loading:"Chargement...",
st_loaded:"Chargé ✓",
st_load_err:"Erreur de chargement",
st_saving:"Enregistrement...",
st_saved:"Enregistré ✓",
st_save_err:"Erreur d’enregistrement",
st_error:"Erreur",
err_unauthorized:"Session invalide. Veuillez vous reconnecter.",
err_rate:"Trop de requêtes. Patientez une minute puis réessayez.",
err_attempts:"Trop de mots de passe ou de codes incorrects dans cette session. Veuillez vous reconnecter.",
err_damaged_hash:"Le fichier du mot de passe data/admin_hash.php est endommagé. Supprimez-le par FTP : un nouveau code d’installation apparaîtra dans data/setup-code.php.",
err_damaged_totp:"Le fichier de la protection à deux facteurs data/admin_totp.php est endommagé, la connexion est donc bloquée. Supprimez-le par FTP – seul le mot de passe sera demandé – puis réactivez la protection.",
err_checks:"Trop de vérifications de l’horloge dans cette session. Reconnectez-vous pour vérifier à nouveau.",
err_server:"Erreur du serveur. Réessayez dans une minute.",
err_invalid:"Valeur non valide : {f}",
err_texts_size:"Les textes sont trop longs au total. Raccourcissez-les.",
texts_title:"📝 Textes de l’interface",
texts_desc:"Textes du site pour chaque langue. Un champ vide utilise le texte intégré. Le champ <code>about_html</code> accepte le HTML.",
texts_save_btn:"💾 Enregistrer tous les textes",
texts_reload_btn:"↻ Recharger",
default_ph:"(par défaut)",
html_supported:"(HTML accepté)",
grp_general:"Général : en-tête, pied de page, boutons",
grp_note:"Note : création",
grp_read:"Note : lecture",
grp_quick:"Chat Rapide",
grp_stealth:"Chat furtif",
grp_chat:"Salle de chat",
grp_status:"Messages d’état et erreurs",
grp_donate:"Dons et partage",
grp_htu_note:"Mode d’emploi : Notes",
grp_htu_quick:"Mode d’emploi : Chat Rapide",
grp_htu_stealth:"Mode d’emploi : Chat furtif",
grp_video:"Vidéos du mode d’emploi (nom de fichier ou lien YouTube / Vimeo)",
grp_about:"Fenêtre « À propos »",
pw_title:"🔑 Changer le mot de passe",
pw_cur_label:"Mot de passe actuel",
pw_cur_ph:"Mot de passe actuel",
pw_new_label:"Nouveau mot de passe (8 caractères minimum)",
pw_new_ph:"Nouveau mot de passe",
pw_rep_label:"Confirmez le nouveau mot de passe",
pw_rep_ph:"Nouveau mot de passe, encore une fois",
pw_btn:"Changer le mot de passe",
st_enter_cur:"Saisissez le mot de passe actuel",
st_min8:"8 caractères minimum",
st_pw_long:"72 octets maximum (72 lettres latines ou environ 36 cyrilliques)",
st_mismatch:"Les mots de passe ne correspondent pas",
st_changing:"Modification...",
st_pw_changed:"Mot de passe modifié ✓",
logout_btn:"🚪 Se déconnecter",
st_pw_default:"Le mot de passe par défaut est interdit",
st_pw_relogin:"Mot de passe modifié ✓ Connectez-vous avec le nouveau mot de passe.",
err_locked:"Trop de tentatives incorrectes. Connexion bloquée pendant {n} min. Si c’est vous et que vous ne pouvez pas attendre, supprimez data/admin_lock.php par FTP.",
err_setup:"Terminez d’abord la configuration initiale.",
setup_title:"🛡️ Configuration initiale",
setup_desc:"Avant d’utiliser le panneau d’administration, terminez sa sécurisation. D’ici là, tous les autres réglages sont verrouillés.",
setup_pw_note:"Vous vous êtes connecté avec le code d’installation à usage unique. Définissez maintenant votre propre mot de passe administrateur – le fichier du code sera supprimé.",
setup_btn:"Enregistrer et continuer",
key_label:"Clé secrète de l’adresse",
key_ph:"12 caractères minimum",
key_gen_btn:"🎲 Générer",
key_desc:"Le panneau d’administration ne s’ouvrira qu’à l’adresse admin.php?k=VOTRE_CLÉ. Toute autre adresse affiche « introuvable », et les tentatives de connexion sans la clé sont refusées. Adresse perdue ? Supprimez data/admin_key.php par FTP : l’ancienne adresse admin.php?manage=1 fonctionnera de nouveau jusqu’à ce que vous définissiez une nouvelle clé.",
st_key_rule:"Clé : de 12 à 64 caractères – lettres latines, chiffres, - et _",
st_key_weak:"La clé est trop simple. Cliquez sur « 🎲 Générer » pour en obtenir une solide.",
done_title:"✅ Votre nouvelle adresse d’administration",
done_desc:"Enregistrez cette adresse dès maintenant dans vos favoris ou votre gestionnaire de mots de passe. Les autres adresses du panneau ne fonctionnent plus.",
done_btn:"Aller à la nouvelle adresse",
copy_btn:"📋 Copier",
st_copied:"Copié ✓",
key_title:"🧭 Adresse d’administration",
key_change_desc:"Définissez une nouvelle clé secrète pour l’adresse du panneau d’administration. L’adresse actuelle cessera aussitôt de fonctionner.",
key_change_btn:"Changer l’adresse",
nav_menu:"Menu",
nav_grp_security:"Sécurité",
otp_title:"🔐 Confirmer la connexion",
otp_desc:"La protection à deux facteurs est activée. Saisissez le code à 6 chiffres de l’application d’authentification de votre téléphone.",
otp_label:"Code de l’application ou code de secours",
otp_ph:"123456 ou K7M2-QX9P",
otp_backup_note:"Pas d’accès à l’application ? Saisissez dans ce même champ l’un de vos codes de secours (par ex. K7M2-QX9P). Chaque code de secours ne fonctionne qu’une fois.",
otp_clock_note:"Le code est récent mais refusé ? Connectez-vous avec un code de secours, ouvrez « Protection à deux facteurs » et cliquez sur « Vérifier l’horloge » – le panneau vous dira quoi corriger.",
otp_btn:"Confirmer",
cancel_btn:"Annuler",
st_enter_code:"Saisissez le code",
st_enter_code6:"Saisissez le code à 6 chiffres de l’application",
wrong_code:"Code incorrect",
st_backup_used:"Vous êtes connecté avec un code de secours. Codes de secours restants : {n}. S’il en reste peu, créez-en de nouveaux ci-dessous.",
st_pending_expired:"La configuration a pris trop de temps. Cliquez à nouveau sur « Activer ».",
err_already:"La protection à deux facteurs est déjà activée",
err_not_enabled:"La protection à deux facteurs est désactivée",
totp_title:"📱 Protection à deux facteurs",
totp_desc:"Après le mot de passe, le panneau demandera aussi un code à usage unique provenant d’une application sur votre téléphone : Google Authenticator, Aegis, 2FAS, Microsoft Authenticator ou toute autre application de codes à usage unique (TOTP). Même si quelqu’un connaît votre mot de passe, il ne pourra pas se connecter sans votre téléphone.",
totp_off_state:"Désactivée",
totp_on_state:"✅ Activée · codes de secours restants : {n}",
totp_low_codes:"Il reste peu de codes de secours. Créez-en de nouveaux ci-dessous, dans « Codes de secours et désactivation ».",
totp_enable_btn:"Activer la protection à deux facteurs",
totp_step1:"1. Ouvrez l’application d’authentification sur votre téléphone, choisissez « Ajouter » (+) et scannez ce code QR.",
totp_manual:"Impossible de scanner ? Choisissez la saisie manuelle dans l’application, un nom quelconque et cette clé (type : basée sur le temps) :",
totp_step2:"2. Saisissez le code à 6 chiffres que l’application affiche maintenant pour ce site :",
totp_confirm_btn:"Confirmer et activer",
totp_enabled_ok:"Protection à deux facteurs activée ✓",
totp_codes_title:"🗝️ Vos codes de secours",
totp_codes_desc:"Chaque code permet une connexion si votre téléphone n’est pas à portée de main. Conservez-les dès maintenant – imprimez-les, placez-les dans un gestionnaire de mots de passe ou téléchargez le fichier. Ils ne s’affichent qu’une seule fois.",
totp_codes_dl:"📥 Télécharger en .txt",
totp_codes_done:"J’ai conservé les codes",
totp_file_head:"SmoldPaper – codes de secours pour la connexion",
totp_file_note:"Chaque code ne fonctionne qu’une fois. Conservez ce fichier en lieu sûr.",
st_codes_new:"Les nouveaux codes de secours sont prêts ✓ Les anciens ne fonctionnent plus.",
totp_check_title:"Vérifier l’horloge",
totp_check_desc:"Si les codes de l’application ne fonctionnent plus, saisissez ici un code récent. Le panneau compare les horloges du serveur, du téléphone et de cet ordinateur et vous indique laquelle est déréglée et quoi faire.",
totp_app_code:"Code de l’application",
totp_check_btn:"🕒 Vérifier l’horloge",
totp_manage_title:"Codes de secours et désactivation",
totp_manage_desc:"Les deux actions demandent le mot de passe administrateur et un code. Un code de l’application ou un code de secours convient (le code de secours est alors consommé).",
totp_code_label:"Code de l’application ou code de secours",
totp_newcodes_btn:"🔄 Nouveaux codes de secours",
totp_disable_btn:"Désactiver la protection à deux facteurs",
totp_disabled:"Protection à deux facteurs désactivée ✓",
totp_emergency:"Téléphone et tous les codes de secours perdus ? Supprimez le fichier data/admin_totp.php par FTP – la connexion ne demandera plus que le mot de passe. Réactivez ensuite la protection ici.",
hint_ok:"✅ L’horloge est correcte : le code correspond, l’écart est inférieur à 30 secondes. Après cette vérification, le même code ne sert plus pour la connexion ou la désactivation – attendez le suivant (60–90 secondes).",
hint_wrong:"Le code ne correspond pas. Vérifiez que vous avez pris dans l’application le code de CE site (le nom de l’entrée est affiché sous le code QR) et saisissez un code récent avant qu’il ne change – les codes changent toutes les 30 secondes.",
hint_phone:"🕒 L’horloge de votre téléphone est décalée d’environ {n} secondes, alors que le serveur et cet ordinateur sont d’accord.\n\nActivez l’heure automatique sur le téléphone :\niPhone : Réglages → Général → Date et heure → « Réglage automatique ».\nAndroid : Paramètres → Système → Date et heure → « Régler l’heure automatiquement » (sur Samsung : Gestion globale → Date et heure).\n\nSaisissez ensuite un nouveau code.",
hint_server:"🕒 L’horloge du serveur {dir} d’environ {n} secondes par rapport à cet ordinateur.\n\nC’est à l’hébergeur de corriger cela : écrivez à son support et demandez d’activer la synchronisation de l’heure (NTP). Le bouton ci-dessous copie un message tout prêt.\n\nN’activez pas la protection à deux facteurs tant que l’horloge n’est pas corrigée – sinon les codes ne fonctionneront pas et vous ne pourrez pas vous connecter.",
hint_both:"🕒 L’heure diffère sur le téléphone, sur le serveur et aussi sur cet ordinateur. Activez d’abord l’heure automatique sur le téléphone et sur cet ordinateur, rechargez la page et vérifiez à nouveau.",
dir_behind:"retarde",
dir_ahead:"avance",
support_copy_btn:"📋 Copier un message pour le support de l’hébergeur",
support_text:"Bonjour ! Sur mon hébergement (site {host}), l’horloge système du serveur {dir} d’environ {n} secondes. À cause de cela, les codes à usage unique de la protection à deux facteurs ne fonctionnent pas. Merci d’activer la synchronisation de l’heure par NTP !",
},
es: {
page_title:"SmoldPaper – Administración",
admin_panel:"Panel de administración",
theme_toggle:"Cambiar tema",
lang_label:"Idioma",
login_title:"🔐 Acceso",
login_pw_label:"Contraseña de administrador",
login_pw_ph:"Introduce la contraseña de administrador",
login_btn:"Entrar",
login_code_title:"🛡️ Primer acceso",
login_code_label:"Código de instalación",
login_code_ph:"XXXX-XXXX-XXXX-XXXX",
login_code_note:"Abre en tu hosting, por FTP o con el gestor de archivos, el archivo data/setup-code.php: el código está en su segunda línea. Cuando definas tu propia contraseña, el archivo se borrará automáticamente. ¿Olvidaste la contraseña más adelante? Borra data/admin_hash.php y aparecerá un código nuevo.",
wrong_setup_code:"Código de instalación incorrecto",
st_enter_pw:"Introduce la contraseña",
st_auth:"Comprobando...",
wrong_password:"Contraseña incorrecta",
st_conn_err:"Error de conexión",
st_loading:"Cargando...",
st_loaded:"Cargado ✓",
st_load_err:"Error al cargar",
st_saving:"Guardando...",
st_saved:"Guardado ✓",
st_save_err:"Error al guardar",
st_error:"Error",
err_unauthorized:"La sesión no es válida. Vuelve a entrar.",
err_rate:"Demasiadas solicitudes. Espera un minuto y vuelve a intentarlo.",
err_attempts:"Demasiadas contraseñas o códigos incorrectos en esta sesión. Vuelve a entrar.",
err_damaged_hash:"El archivo de la contraseña data/admin_hash.php está dañado. Bórralo por FTP: aparecerá un código de instalación nuevo en data/setup-code.php.",
err_damaged_totp:"El archivo de la protección en dos pasos data/admin_totp.php está dañado, por eso el acceso está cerrado. Bórralo por FTP – bastará la contraseña – y luego vuelve a activar la protección.",
err_checks:"Demasiadas comprobaciones del reloj en esta sesión. Vuelve a entrar para comprobar de nuevo.",
err_server:"Error del servidor. Inténtalo de nuevo en un minuto.",
err_invalid:"Valor no válido: {f}",
err_texts_size:"Los textos son demasiado largos en total. Acórtalos.",
texts_title:"📝 Textos de la interfaz",
texts_desc:"Textos del sitio para cada idioma. Un campo vacío usa el texto integrado. El campo <code>about_html</code> admite HTML.",
texts_save_btn:"💾 Guardar todos los textos",
texts_reload_btn:"↻ Recargar",
default_ph:"(por defecto)",
html_supported:"(admite HTML)",
grp_general:"General: cabecera, pie de página, botones",
grp_note:"Nota: creación",
grp_read:"Nota: lectura",
grp_quick:"Chat Rápido",
grp_stealth:"Chat sigiloso",
grp_chat:"Sala de chat",
grp_status:"Mensajes de estado y errores",
grp_donate:"Donaciones y compartir",
grp_htu_note:"Instrucciones: Notas",
grp_htu_quick:"Instrucciones: Chat Rápido",
grp_htu_stealth:"Instrucciones: Chat sigiloso",
grp_video:"Vídeos de instrucciones (nombre de archivo o enlace de YouTube / Vimeo)",
grp_about:"Ventana «Acerca de»",
pw_title:"🔑 Cambiar contraseña",
pw_cur_label:"Contraseña actual",
pw_cur_ph:"Contraseña actual",
pw_new_label:"Nueva contraseña (mínimo 8 caracteres)",
pw_new_ph:"Nueva contraseña",
pw_rep_label:"Repite la nueva contraseña",
pw_rep_ph:"Nueva contraseña otra vez",
pw_btn:"Cambiar contraseña",
st_enter_cur:"Introduce la contraseña actual",
st_min8:"Mínimo 8 caracteres",
st_pw_long:"Como máximo 72 bytes (72 letras latinas o unas 36 cirílicas)",
st_mismatch:"Las contraseñas no coinciden",
st_changing:"Cambiando...",
st_pw_changed:"Contraseña cambiada ✓",
logout_btn:"🚪 Salir",
st_pw_default:"No se puede usar la contraseña por defecto",
st_pw_relogin:"Contraseña cambiada ✓ Entra con la nueva contraseña.",
err_locked:"Demasiados intentos incorrectos. El acceso está bloqueado durante {n} min. Si eres tú y no puedes esperar, borra data/admin_lock.php por FTP.",
err_setup:"Termina primero la configuración inicial.",
setup_title:"🛡️ Configuración inicial",
setup_desc:"Antes de usar el panel de administración, termina de protegerlo. Hasta entonces, los demás ajustes están bloqueados.",
setup_pw_note:"Has entrado con el código de instalación de un solo uso. Ahora define tu propia contraseña de administrador: el archivo del código se borrará.",
setup_btn:"Guardar y continuar",
key_label:"Clave secreta de la dirección",
key_ph:"Mínimo 12 caracteres",
key_gen_btn:"🎲 Generar",
key_desc:"El panel de administración solo se abrirá en admin.php?k=TU_CLAVE. Cualquier otra dirección mostrará «no encontrado», y los intentos de acceso sin la clave se rechazan. ¿Perdiste la dirección? Borra data/admin_key.php por FTP: la antigua dirección admin.php?manage=1 volverá a funcionar hasta que definas una clave nueva.",
st_key_rule:"Clave: de 12 a 64 caracteres – letras latinas, cifras, - y _",
st_key_weak:"La clave es demasiado simple. Pulsa «🎲 Generar» para obtener una segura.",
done_title:"✅ Tu nueva dirección de administración",
done_desc:"Guarda esta dirección ahora mismo en marcadores o en tu gestor de contraseñas. Las demás direcciones del panel ya no funcionan.",
done_btn:"Ir a la nueva dirección",
copy_btn:"📋 Copiar",
st_copied:"Copiado ✓",
key_title:"🧭 Dirección de administración",
key_change_desc:"Define una nueva clave secreta para la dirección del panel de administración. La dirección actual dejará de funcionar al instante.",
key_change_btn:"Cambiar dirección",
nav_menu:"Menú",
nav_grp_security:"Seguridad",
otp_title:"🔐 Confirmar acceso",
otp_desc:"La protección en dos pasos está activada. Introduce el código de 6 dígitos de la aplicación de autenticación de tu teléfono.",
otp_label:"Código de la aplicación o código de respaldo",
otp_ph:"123456 o K7M2-QX9P",
otp_backup_note:"¿No tienes acceso a la aplicación? Escribe en este mismo campo uno de tus códigos de respaldo (como K7M2-QX9P). Cada código de respaldo funciona solo una vez.",
otp_clock_note:"¿El código es reciente pero no se acepta? Entra con un código de respaldo, abre «Protección en dos pasos» y pulsa «Comprobar reloj»: el panel te dirá qué corregir.",
otp_btn:"Confirmar",
cancel_btn:"Cancelar",
st_enter_code:"Introduce el código",
st_enter_code6:"Introduce el código de 6 dígitos de la aplicación",
wrong_code:"Código incorrecto",
st_backup_used:"Has entrado con un código de respaldo. Códigos de respaldo restantes: {n}. Si quedan pocos, crea otros nuevos abajo.",
st_pending_expired:"La configuración tardó demasiado. Pulsa «Activar» de nuevo.",
err_already:"La protección en dos pasos ya está activada",
err_not_enabled:"La protección en dos pasos está desactivada",
totp_title:"📱 Protección en dos pasos",
totp_desc:"Después de la contraseña, el panel pedirá también un código de un solo uso de una aplicación en tu teléfono: Google Authenticator, Aegis, 2FAS, Microsoft Authenticator o cualquier otra aplicación de códigos de un solo uso (TOTP). Aunque alguien conozca tu contraseña, no podrá entrar sin tu teléfono.",
totp_off_state:"Desactivada",
totp_on_state:"✅ Activada · códigos de respaldo restantes: {n}",
totp_low_codes:"Quedan pocos códigos de respaldo. Crea otros nuevos abajo, en «Códigos de respaldo y desactivación».",
totp_enable_btn:"Activar la protección en dos pasos",
totp_step1:"1. Abre la aplicación de autenticación en tu teléfono, elige «Añadir» (+) y escanea este código QR.",
totp_manual:"¿No puedes escanearlo? Elige en la aplicación la introducción manual, cualquier nombre y esta clave (tipo: basada en tiempo):",
totp_step2:"2. Introduce el código de 6 dígitos que la aplicación muestra ahora para este sitio:",
totp_confirm_btn:"Confirmar y activar",
totp_enabled_ok:"Protección en dos pasos activada ✓",
totp_codes_title:"🗝️ Tus códigos de respaldo",
totp_codes_desc:"Cada código te permite entrar una vez si no tienes el teléfono a mano. Guárdalos ahora mismo: imprímelos, ponlos en un gestor de contraseñas o descarga el archivo. Solo se muestran una vez.",
totp_codes_dl:"📥 Descargar .txt",
totp_codes_done:"Ya he guardado los códigos",
totp_file_head:"SmoldPaper – códigos de respaldo para entrar",
totp_file_note:"Cada código funciona solo una vez. Guarda este archivo en un lugar seguro.",
st_codes_new:"Los nuevos códigos de respaldo están listos ✓ Los antiguos ya no funcionan.",
totp_check_title:"Comprobar reloj",
totp_check_desc:"Si los códigos de la aplicación dejaron de funcionar, introduce aquí un código reciente. El panel compara los relojes del servidor, del teléfono y de este ordenador y te dice cuál va mal y qué hacer.",
totp_app_code:"Código de la aplicación",
totp_check_btn:"🕒 Comprobar reloj",
totp_manage_title:"Códigos de respaldo y desactivación",
totp_manage_desc:"Ambas acciones requieren la contraseña de administrador y un código. Sirve un código de la aplicación o uno de respaldo (el de respaldo se gasta).",
totp_code_label:"Código de la aplicación o código de respaldo",
totp_newcodes_btn:"🔄 Nuevos códigos de respaldo",
totp_disable_btn:"Desactivar la protección en dos pasos",
totp_disabled:"Protección en dos pasos desactivada ✓",
totp_emergency:"¿Perdiste el teléfono y todos los códigos de respaldo? Borra el archivo data/admin_totp.php por FTP: para entrar bastará de nuevo la contraseña. Después vuelve a activar la protección aquí.",
hint_ok:"✅ El reloj está bien: el código coincide, la diferencia es menor de 30 segundos. Después de esta comprobación el mismo código ya no sirve para entrar ni para desactivar: espera al siguiente (60–90 segundos).",
hint_wrong:"El código no coincide. Comprueba que tomaste en la aplicación el código de ESTE sitio (el nombre de la entrada aparece bajo el código QR) e introduce un código reciente antes de que cambie: los códigos cambian cada 30 segundos.",
hint_phone:"🕒 El reloj de tu teléfono va desfasado unos {n} segundos, mientras que el servidor y este ordenador coinciden.\n\nActiva la hora automática en el teléfono:\niPhone: Ajustes → General → Fecha y hora → «Ajuste automático».\nAndroid: Ajustes → Sistema → Fecha y hora → «Establecer hora automáticamente» (en Samsung: Administración general → Fecha y hora).\n\nDespués introduce un código nuevo.",
hint_server:"🕒 El reloj del servidor se {dir} unos {n} segundos respecto a este ordenador.\n\nEsto lo corrige el hosting: escribe a su soporte y pide que activen la sincronización de hora (NTP). El botón de abajo copia un mensaje listo.\n\nNo actives la protección en dos pasos hasta que el reloj esté corregido: si no, los códigos no funcionarán y no podrás entrar.",
hint_both:"🕒 La hora es distinta en el teléfono, en el servidor y también en este ordenador. Primero activa la hora automática en el teléfono y en este ordenador, recarga la página y vuelve a comprobar.",
dir_behind:"atrasa",
dir_ahead:"adelanta",
support_copy_btn:"📋 Copiar un mensaje para el soporte del hosting",
support_text:"¡Hola! En mi hosting (sitio {host}) el reloj del sistema del servidor se {dir} unos {n} segundos. Por eso no funcionan los códigos de un solo uso de la protección en dos pasos. Por favor, activad la sincronización de hora por NTP. ¡Gracias!",
}
};

const UI_LANGS = ['en', 'ru', 'de', 'fr', 'es'];
let uiLang = 'en';
let theme = 'dark';

function t(k) { return (AL[uiLang] && AL[uiLang][k]) || AL.en[k] || k; }

function esc(s) { const d = document.createElement('div'); d.textContent = String(s); return d.innerHTML; }

function applyI18n() {
    document.querySelectorAll('[data-i18n]').forEach(e => { e.innerHTML = t(e.dataset.i18n); });
    document.querySelectorAll('[data-i18n-ph]').forEach(e => { e.placeholder = t(e.dataset.i18nPh); });
    document.querySelectorAll('[data-i18n-title]').forEach(e => { const v = t(e.dataset.i18nTitle); e.title = v; e.setAttribute('aria-label', v); });
    document.documentElement.lang = uiLang;
    document.title = t('page_title');
    updMenuCur();
    if (totpState) renderTotpState();
    if (lastHint) showHint(lastHint);
}

function setLang(l) {
    if (!AL[l]) l = 'en';
    uiLang = l;
    document.getElementById('sel-lang').value = l;
    try { localStorage.setItem('sp_l', l); } catch (e) {}
    applyI18n();
}

function detectLang() {
    let s = null;
    try { s = localStorage.getItem('sp_l'); } catch (e) {}
    if (s && AL[s]) return s;
    const n = (navigator.language || '').substring(0, 2).toLowerCase();
    return AL[n] ? n : 'en';
}

// ─── THEME (shared with the main site via localStorage 'sp_t') ──

function applyTheme() {
    document.documentElement.setAttribute('data-theme', theme);
    document.querySelector('#btn-theme svg use').setAttribute('href', theme === 'dark' ? '#i-sun' : '#i-moon');
}

function toggleTheme() {
    theme = theme === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem('sp_t', theme); } catch (e) {}
    applyTheme();
}

// ─── API ──────────────────────────────────────────────────

// Secret key from the page address (admin.php?k=...), sent at sign-in and with the session check
const URL_KEY = (function () { try { return new URLSearchParams(location.search).get('k') || ''; } catch (e) { return ''; } })();
// CSRF token of the current admin session (the session itself is an HttpOnly cookie)
let csrf = '';

async function apiPost(action, body) {
    const headers = { 'Content-Type': 'application/json' };
    if (csrf) headers['X-SP-CSRF'] = csrf;
    const res = await fetch(`${API}?action=${action}`, {
        method: 'POST',
        headers: headers,
        body: JSON.stringify(body || {}),
        credentials: 'same-origin',
        cache: 'no-store'
    });
    const data = await res.json();
    // Session expired, closed after wrong attempts or ended elsewhere: back to the sign-in screen
    if (data && data.error === 'Unauthorized' && action !== 'admin_status' && action !== 'admin_login') sessionLost(data.reason);
    return data;
}

async function apiGet(action) {
    const res = await fetch(`${API}?action=${action}`, { cache: 'no-store' });
    return res.json();
}

// Names of settings fields in server messages "Invalid value: <field>"
const FIELD_LABELS = {
};

// Translate known server error messages; unknown ones are shown as is
function srvErr(msg) {
    const map = {
        'Unauthorized': 'err_unauthorized',
        'Wrong password': 'wrong_password',
        'Wrong setup code': 'wrong_setup_code',
        'Min 8 chars': 'st_min8',
        'Max 72 bytes': 'st_pw_long',
        'Default password not allowed': 'st_pw_default',
        'Invalid key': 'st_key_rule',
        'Key too simple': 'st_key_weak',
        'Setup required': 'err_setup',
        'Unknown': 'st_error',
        'Too many requests. Try again later.': 'err_rate',
        'Wrong code': 'wrong_code',
        'Setup expired': 'st_pending_expired',
        'Already enabled': 'err_already',
        'Not enabled': 'err_not_enabled',
        'Too many checks': 'err_checks',
        'Password file damaged': 'err_damaged_hash',
        'Two-factor file damaged': 'err_damaged_totp',
        'Server error': 'err_server',
        'Server busy': 'err_server',
        'Texts too large': 'err_texts_size'
    };
    const s = String(msg || '');
    if (map[s]) return t(map[s]);
    if (s.indexOf('Invalid value: ') === 0) {
        const f = s.substring(15);
        const base = f.split('.')[0];
        return t('err_invalid').replace('{f}', FIELD_LABELS[base] ? t(FIELD_LABELS[base]) : f);
    }
    return s;
}

function lockedMsg(sec) {
    const n = Math.max(1, Math.ceil((Number(sec) || 60) / 60));
    return t('err_locked').replace('{n}', n);
}

function setStatus(id, text, type) {
    const el = document.getElementById(id);
    if (el) { el.textContent = text || ''; el.className = 'st' + (type ? ' ' + type : ''); }
}

function clearStatusLater(id, ms) { setTimeout(() => setStatus(id, ''), ms || 2000); }

// Disables a button while its request runs (no double submit)
async function busy(btnId, fn) {
    const b = btnId ? document.getElementById(btnId) : null;
    if (b && b.disabled) return;
    if (b) b.disabled = true;
    try { await fn(); } finally { if (b) b.disabled = false; }
}

function utf8Len(s) { return new TextEncoder().encode(s).length; }

// ─── VIEWS ────────────────────────────────────────────────

function showView(name) {
    ['boot', 'login', 'otp', 'setup', 'done', 'admin'].forEach(v => {
        document.getElementById(v + '-view').classList.toggle('active', v === name);
    });
    document.body.classList.toggle('wide', name === 'admin');
    if (name !== 'admin') closeMenu();
}

// ─── SIDE MENU (one section at a time, remembered in the address: #totp) ──

function secIds() { return Array.from(document.querySelectorAll('.nav-item[data-sec]')).map(b => b.dataset.sec); }
let curSec = '';

function showSec(id, keepHash) {
    const ids = secIds();
    if (!ids.includes(id)) id = ids[0];
    curSec = id;
    document.querySelectorAll('.sec').forEach(el => el.classList.toggle('active', el.id === 'sec-' + id));
    document.querySelectorAll('.nav-item[data-sec]').forEach(b => b.classList.toggle('active', b.dataset.sec === id));
    updMenuCur();
    closeMenu();
    if (!keepHash) { try { history.replaceState(null, '', location.pathname + location.search + '#' + id); } catch (e) {} }
    window.scrollTo(0, 0);
}

function updMenuCur() {
    const b = document.querySelector('.nav-item.active');
    const el = document.getElementById('menu-cur');
    if (el) el.innerHTML = b ? b.innerHTML : '';
}

function openMenu() {
    document.getElementById('side').classList.add('open');
    document.getElementById('side-shade').classList.add('open');
}

function closeMenu() {
    const sd = document.getElementById('side'), sh = document.getElementById('side-shade');
    if (sd) sd.classList.remove('open');
    if (sh) sh.classList.remove('open');
}

function hashSec() { return (location.hash || '').replace('#', ''); }

const SECRET_FIELDS = ['login-pw', 'otp-code', 'setup-pw', 'setup-pw2', 'setup-key', 'new-key', 'key-cur-pw', 'cur-pw', 'new-pw', 'new-pw2',
    'totp-pw', 'totp-code', 'totp-check-code', 'totp-m-pw', 'totp-m-code'];

// After sign-out nothing of the previous session stays in the page (site password, texts, codes)
function clearAdminState() {
    csrf = '';
    textsData = {};
    totpState = null;
    lastCodes = [];
    lastHint = null;
    hideHint();
    SECRET_FIELDS.forEach(id => { const el = document.getElementById(id); if (el) el.value = ''; });
    document.getElementById('lang-editors').innerHTML = '';
    document.getElementById('totp-codes-list').innerHTML = '';
}

// Sign-in screen in one of two modes: admin password, or the one-time setup code (first run)
let loginCode = false;

function setLoginMode(code) {
    loginCode = !!code;
    const inp = document.getElementById('login-pw');
    document.getElementById('login-title').dataset.i18n = loginCode ? 'login_code_title' : 'login_title';
    document.getElementById('login-label').dataset.i18n = loginCode ? 'login_code_label' : 'login_pw_label';
    inp.dataset.i18nPh = loginCode ? 'login_code_ph' : 'login_pw_ph';
    inp.type = loginCode ? 'text' : 'password';
    inp.autocomplete = loginCode ? 'off' : 'current-password';
    inp.setAttribute('autocapitalize', loginCode ? 'characters' : 'off');
    inp.classList.toggle('mono', loginCode);
    document.getElementById('login-code-note').style.display = loginCode ? '' : 'none';
    applyI18n();
}

function toLogin(msg, type) {
    clearAdminState();
    showView('login');
    setStatus('login-status', msg || '', type);
    document.getElementById('login-pw').focus();
}

function sessionLost(reason) { toLogin(t(reason === 'attempts' ? 'err_attempts' : 'err_unauthorized'), 'err'); }

// ─── LOGIN ────────────────────────────────────────────────

function doLogin() { return busy('btn-login', doLoginInner); }

async function doLoginInner() {
    const pw = document.getElementById('login-pw').value.trim() === '' ? '' : document.getElementById('login-pw').value;
    if (!pw) return setStatus('login-status', t(loginCode ? 'st_enter_code' : 'st_enter_pw'), 'err');

    setStatus('login-status', t('st_auth'), 'wait');
    try {
        const res = await apiPost('admin_login', { password: pw, k: URL_KEY });
        if (res.error === 'Locked') { setStatus('login-status', lockedMsg(res.retry), 'err'); return; }
        if (res.error) { setStatus('login-status', srvErr(res.error), 'err'); return; }
        document.getElementById('login-pw').value = '';
        setStatus('login-status', '');
        csrf = res.csrf || '';
        if (res.status === 'otp') { showOtp(); return; }
        enterSession(res.setup);
    } catch (e) {
        setStatus('login-status', t('st_conn_err'), 'err');
    }
}

// ─── SECOND STEP: ONE-TIME CODE ───────────────────────────

function showOtp(msg, type) {
    document.getElementById('otp-code').value = '';
    setStatus('otp-status', msg || '', type);
    showView('otp');
    document.getElementById('otp-code').focus();
}

async function doOtp() {
    const code = document.getElementById('otp-code').value.trim();
    if (!code) return setStatus('otp-status', t('st_enter_code'), 'err');
    setStatus('otp-status', t('st_auth'), 'wait');
    try {
        const res = await apiPost('admin_otp', { code: code });
        if (res.error === 'Locked') { setStatus('otp-status', lockedMsg(res.retry), 'err'); return; }
        if (res.error === 'Unauthorized') return; // half-session expired: apiPost already went back to sign-in
        if (res.error) { document.getElementById('otp-code').value = ''; setStatus('otp-status', srvErr(res.error), 'err'); return; }
        document.getElementById('otp-code').value = '';
        csrf = res.csrf || '';
        if (res.used_backup) backupNotice = t('st_backup_used').replace('{n}', res.backup_left);
        enterSession(res.setup);
    } catch (e) {
        setStatus('otp-status', t('st_conn_err'), 'err');
    }
}

// After sign-in (or a page reload with a live session): setup first, then the panel
let setupState = { need_pw: false, need_key: false };

function enterSession(setup) {
    setupState = { need_pw: !!(setup && setup.need_pw), need_key: !!(setup && setup.need_key) };
    if (setupState.need_pw || setupState.need_key) {
        document.getElementById('setup-pw-box').style.display = setupState.need_pw ? '' : 'none';
        document.getElementById('setup-key-box').style.display = setupState.need_key ? '' : 'none';
        setStatus('setup-status', '');
        showView('setup');
        return;
    }
    openAdmin();
}

let backupNotice = '';

function openAdmin() {
    setLoginMode(false);
    showView('admin');
    // After signing in with a backup code go straight to the two-factor section
    showSec(backupNotice ? 'totp' : hashSec(), !backupNotice && hashSec() !== '');
    buildEditor();
    loadTexts();
    loadTotp();
}

async function doLogout() {
    try { await apiPost('admin_logout', {}); } catch (e) {}
    toLogin('', '');
}

// ─── SECRET ADDRESS KEY ───────────────────────────────────

const KEY_RE = /^[A-Za-z0-9_-]{12,64}$/;
function keyWeak(k) { return new Set(k.split('')).size < 8; }

function genKeyInto(id) {
    const abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let out = '';
    while (out.length < 24) {
        const b = new Uint8Array(32);
        crypto.getRandomValues(b);
        for (const x of b) {
            if (x < 248 && out.length < 24) out += abc[x % 62]; // 248 = 4 * 62, no modulo bias
        }
    }
    document.getElementById(id).value = out;
}

function adminUrl(k) { return location.origin + location.pathname + '?k=' + encodeURIComponent(k); }

let doneUrl = '';

function showDone(k) {
    doneUrl = adminUrl(k);
    document.getElementById('done-url').textContent = doneUrl;
    setStatus('done-status', '');
    showView('done');
}

async function copyDoneUrl() {
    try {
        await navigator.clipboard.writeText(doneUrl);
        setStatus('done-status', t('st_copied'), 'ok');
    } catch (e) {
        // Clipboard API unavailable: select the text so it can be copied by hand
        const r = document.createRange();
        r.selectNodeContents(document.getElementById('done-url'));
        const sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(r);
    }
}

// The session cookie stays valid, so the panel opens right away at the new address
function goDoneUrl() { location.replace(doneUrl); }

// Checks a new admin password on this side before sending; returns an error text or ''
function newPwProblem(np, np2) {
    if (np.length < 8) return t('st_min8');
    if (utf8Len(np) > 72) return t('st_pw_long');
    if (np === 'smoldpaper') return t('st_pw_default');
    if (np !== np2) return t('st_mismatch');
    return '';
}

function keyProblem(key) {
    if (!KEY_RE.test(key)) return t('st_key_rule');
    if (keyWeak(key)) return t('st_key_weak');
    return '';
}

// ─── FIRST-TIME SETUP ─────────────────────────────────────

async function doSetup() {
    const body = {};
    if (setupState.need_pw) {
        const np = document.getElementById('setup-pw').value;
        const bad = newPwProblem(np, document.getElementById('setup-pw2').value);
        if (bad) return setStatus('setup-status', bad, 'err');
        body.new_password = np;
    }
    let key = '';
    if (setupState.need_key) {
        key = document.getElementById('setup-key').value.trim();
        const bad = keyProblem(key);
        if (bad) return setStatus('setup-status', bad, 'err');
        body.key = key;
    }

    setStatus('setup-status', t('st_saving'), 'wait');
    try {
        const res = await apiPost('admin_setup', body);
        if (res.error) { setStatus('setup-status', srvErr(res.error), 'err'); return; }
        ['setup-pw', 'setup-pw2', 'setup-key'].forEach(id => { document.getElementById(id).value = ''; });
        if (res.key_set) showDone(key); else openAdmin();
    } catch (e) {
        setStatus('setup-status', t('st_save_err'), 'err');
    }
}

// ─── CHANGE ADDRESS KEY ───────────────────────────────────

async function changeKey() {
    const key = document.getElementById('new-key').value.trim();
    const pw = document.getElementById('key-cur-pw').value;
    const bad = keyProblem(key);
    if (bad) return setStatus('key-status', bad, 'err');
    if (!pw) return setStatus('key-status', t('st_enter_cur'), 'err');

    setStatus('key-status', t('st_saving'), 'wait');
    try {
        const res = await apiPost('change_key', { admin_password: pw, key: key });
        if (res.error) { document.getElementById('key-cur-pw').value = ''; setStatus('key-status', srvErr(res.error), 'err'); return; }
        ['new-key', 'key-cur-pw'].forEach(id => { document.getElementById(id).value = ''; });
        setStatus('key-status', '');
        showDone(key);
    } catch (e) {
        setStatus('key-status', t('st_save_err'), 'err');
    }
}

// ─── TWO-FACTOR (TOTP) ────────────────────────────────────

let totpState = null;   // {enabled, backup_left} from the server
let totpMode = '';      // which block is shown: off | setup | codes | on
let lastCodes = [];     // backup codes, kept only until "I saved the codes"
let lastHint = null;    // clock diagnostics shown under the card
let lastSupport = '';   // ready text for the hosting support

async function copyText(text, statusId) {
    try {
        await navigator.clipboard.writeText(text);
    } catch (e) {
        // Clipboard API unavailable (e.g. plain http): old-style copy through a hidden field
        const ta = document.createElement('textarea');
        ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); } catch (e2) {}
        document.body.removeChild(ta);
    }
    setStatus(statusId, t('st_copied'), 'ok');
    clearStatusLater(statusId);
}

function totpShow(mode) {
    totpMode = mode;
    ['off', 'setup', 'codes', 'on'].forEach(m => { document.getElementById('totp-' + m).style.display = m === mode ? '' : 'none'; });
}

function renderTotpState() {
    if (!totpState) return;
    const b = document.getElementById('totp-badge');
    const low = totpState.enabled && totpState.backup_left <= 2;
    b.className = 'badge ' + (totpState.enabled ? (low ? 'low' : 'on') : 'off');
    b.textContent = totpState.enabled ? t('totp_on_state').replace('{n}', totpState.backup_left) : t('totp_off_state');
    document.getElementById('totp-low').style.display = low && totpMode !== 'codes' ? '' : 'none';
    if (totpMode !== 'setup' && totpMode !== 'codes') totpShow(totpState.enabled ? 'on' : 'off');
}

async function loadTotp() {
    try {
        const res = await apiPost('totp_status', {});
        if (res.error) return;
        totpState = { enabled: !!res.enabled, backup_left: Number(res.backup_left) || 0 };
        totpMode = '';
        renderTotpState();
        if (backupNotice) {
            setStatus('totp-status', backupNotice, totpState.backup_left <= 2 ? 'err' : 'ok');
            backupNotice = '';
        }
    } catch (e) {}
}

function groupSecret(s) { return String(s).replace(/(.{4})/g, '$1 ').trim(); }

function totpBegin() { return busy('btn-totp-begin', totpBeginInner); }

async function totpBeginInner() {
    const pw = document.getElementById('totp-pw').value;
    if (!pw) return setStatus('totp-status', t('st_enter_cur'), 'err');
    setStatus('totp-status', t('st_auth'), 'wait');
    hideHint();
    try {
        const res = await apiPost('totp_begin', { admin_password: pw });
        if (res.error) { document.getElementById('totp-pw').value = ''; setStatus('totp-status', srvErr(res.error), 'err'); return; }
        document.getElementById('totp-pw').value = '';
        setStatus('totp-status', '');
        renderQRTo(document.getElementById('totp-qr'), res.uri);
        const sec = document.getElementById('totp-secret');
        sec.textContent = groupSecret(res.secret);
        sec.dataset.raw = res.secret;
        document.getElementById('totp-label').textContent = res.label || '';
        document.getElementById('totp-code').value = '';
        totpShow('setup');
        document.getElementById('totp-code').focus();
    } catch (e) {
        setStatus('totp-status', t('st_conn_err'), 'err');
    }
}

function totpCancel() {
    document.getElementById('totp-secret').dataset.raw = '';
    document.getElementById('totp-secret').textContent = '';
    const cv = document.getElementById('totp-qr'); cv.width = 0; cv.height = 0;
    hideHint();
    setStatus('totp-status', '');
    totpMode = '';
    renderTotpState();
}

// The button stays disabled while the request runs: a double Enter cannot send the code twice
function totpEnable() { return busy('btn-totp-enable', totpEnableInner); }

async function totpEnableInner() {
    const code = document.getElementById('totp-code').value.replace(/\s+/g, '');
    if (!/^\d{6}$/.test(code)) return setStatus('totp-status', t('st_enter_code6'), 'err');
    setStatus('totp-status', t('st_auth'), 'wait');
    hideHint();
    try {
        const res = await apiPost('totp_enable', { code: code });
        if (res.error === 'Wrong code') {
            document.getElementById('totp-code').value = '';
            setStatus('totp-status', t('wrong_code'), 'err');
            diagnose(res);
            return;
        }
        if (res.error) { setStatus('totp-status', srvErr(res.error), 'err'); return; }
        totpCancel();
        totpState = { enabled: true, backup_left: (res.codes || []).length };
        showCodes(res.codes || []);
        setStatus('totp-status', t('totp_enabled_ok'), 'ok');
    } catch (e) {
        setStatus('totp-status', t('st_conn_err'), 'err');
    }
}

function showCodes(codes) {
    lastCodes = codes.slice();
    document.getElementById('totp-codes-list').innerHTML = lastCodes.map(c => '<div>' + esc(c) + '</div>').join('');
    totpShow('codes');
    renderTotpState();
}

function codesSaved() {
    lastCodes = [];
    document.getElementById('totp-codes-list').innerHTML = '';
    setStatus('totp-status', '');
    totpMode = '';
    renderTotpState();
}

function downloadCodes() {
    if (!lastCodes.length) return;
    const d = new Date();
    const txt = t('totp_file_head') + '\n' + location.host + ' – ' + d.toLocaleString(uiLang) + '\n\n'
        + lastCodes.join('\n') + '\n\n' + t('totp_file_note') + '\n';
    const url = URL.createObjectURL(new Blob([txt], { type: 'text/plain;charset=utf-8' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = 'smoldpaper-backup-codes-' + location.hostname + '.txt';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

async function totpCheck() {
    const code = document.getElementById('totp-check-code').value.replace(/\s+/g, '');
    if (!/^\d{6}$/.test(code)) return setStatus('totp-check-status', t('st_enter_code6'), 'err');
    hideHint();
    setStatus('totp-status', '');
    setStatus('totp-check-status', t('st_auth'), 'wait');
    try {
        const res = await apiPost('totp_check', { code: code });
        document.getElementById('totp-check-code').value = '';
        if (res.status === 'ok') { setStatus('totp-check-status', ''); showHint({ kind: 'ok', box: 'totp-check-hint' }); return; }
        if (res.error === 'Wrong code') { setStatus('totp-check-status', t('wrong_code'), 'err'); diagnose(res, 'totp-check-hint'); return; }
        setStatus('totp-check-status', srvErr(res.error || 'Unknown'), 'err');
    } catch (e) {
        setStatus('totp-check-status', t('st_conn_err'), 'err');
    }
}

function manageFields() {
    const pw = document.getElementById('totp-m-pw').value;
    const code = document.getElementById('totp-m-code').value.trim();
    if (!pw) { setStatus('totp-status', t('st_enter_cur'), 'err'); return null; }
    if (!code) { setStatus('totp-status', t('st_enter_code'), 'err'); return null; }
    return { admin_password: pw, code: code };
}

function clearManageFields() {
    document.getElementById('totp-m-pw').value = '';
    document.getElementById('totp-m-code').value = '';
}

async function totpNewCodes() {
    const body = manageFields(); if (!body) return;
    setStatus('totp-status', t('st_auth'), 'wait');
    hideHint();
    try {
        const res = await apiPost('totp_new_codes', body);
        if (res.error) { clearManageFields(); setStatus('totp-status', srvErr(res.error), 'err'); return; }
        clearManageFields();
        totpState = { enabled: true, backup_left: (res.codes || []).length };
        showCodes(res.codes || []);
        setStatus('totp-status', t('st_codes_new'), 'ok');
    } catch (e) {
        setStatus('totp-status', t('st_conn_err'), 'err');
    }
}

async function totpDisable() {
    const body = manageFields(); if (!body) return;
    setStatus('totp-status', t('st_auth'), 'wait');
    hideHint();
    try {
        const res = await apiPost('totp_disable', body);
        if (res.error) { clearManageFields(); setStatus('totp-status', srvErr(res.error), 'err'); return; }
        clearManageFields();
        totpState = { enabled: false, backup_left: 0 };
        totpMode = '';
        renderTotpState();
        setStatus('totp-status', t('totp_disabled'), 'ok');
    } catch (e) {
        setStatus('totp-status', t('st_conn_err'), 'err');
    }
}

// Whose clock is wrong? Three clocks: server, phone (drift from the server) and this computer.
// drift = phone minus server in seconds (null: the code did not match even within +-5 minutes)
function diagnose(res, box) {
    const drift = (res.drift === null || res.drift === undefined) ? null : Number(res.drift);
    const comp = Date.now() / 1000 - Number(res.server_time || 0); // this computer minus server
    if (drift === null) {
        if (res.server_time && Math.abs(comp) > 90) showHint({ kind: 'server', n: Math.round(Math.abs(comp)), ahead: comp < 0, box: box });
        else showHint({ kind: 'wrong', box: box });
        return;
    }
    if (Math.abs(comp) <= 45) showHint({ kind: 'phone', n: Math.abs(drift), box: box });
    else if (Math.abs(drift - comp) <= 60) showHint({ kind: 'server', n: Math.round(Math.abs(comp)), ahead: comp < 0, box: box });
    else showHint({ kind: 'both', box: box });
}

function showHint(h) {
    lastHint = h;
    hintBoxes().forEach(b => { if (b.id !== (h.box || 'totp-hint')) { b.className = 'hint-box'; b.innerHTML = ''; } });
    const box = document.getElementById(h.box || 'totp-hint');
    const nl = s => esc(s).replace(/\n/g, '<br>');
    let html = '';
    if (h.kind === 'ok') html = nl(t('hint_ok'));
    else if (h.kind === 'wrong') html = nl(t('hint_wrong'));
    else if (h.kind === 'phone') html = nl(t('hint_phone').replace('{n}', h.n));
    else if (h.kind === 'both') html = nl(t('hint_both'));
    else if (h.kind === 'server') {
        const dir = t(h.ahead ? 'dir_ahead' : 'dir_behind');
        lastSupport = t('support_text').replace('{host}', location.host).replace('{dir}', dir).replace('{n}', h.n);
        html = nl(t('hint_server').replace('{dir}', dir).replace('{n}', h.n))
            + '<button class="btn btn-outline btn-sm" onclick="copyText(lastSupport,\'' + (h.box === 'totp-check-hint' ? 'totp-check-status' : 'totp-status') + '\')">' + esc(t('support_copy_btn')) + '</button>';
    }
    box.innerHTML = html;
    box.className = 'hint-box show ' + (h.kind === 'ok' ? 'ok' : 'warn');
}

function hintBoxes() { return ['totp-hint', 'totp-check-hint'].map(id => document.getElementById(id)).filter(Boolean); }

function hideHint() {
    lastHint = null;
    hintBoxes().forEach(b => { b.className = 'hint-box'; b.innerHTML = ''; });
    setStatus('totp-check-status', '');
}

// ─── QR CODE (copy of the generator from index.html: ISO/IEC 18004, byte mode, ECC M) ──

function renderQRTo(canvas, text) {
    const mods = generateQR(text);
    const n = mods.length, quiet = 4, total = n + quiet * 2;
    const scale = Math.max(4, Math.ceil(440 / total));
    canvas.width = total * scale; canvas.height = total * scale;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#000';
    for (let y = 0; y < n; y++) for (let x = 0; x < n; x++) if (mods[y][x]) ctx.fillRect((x + quiet) * scale, (y + quiet) * scale, scale, scale);
}

function generateQR(text){
  // ECC level M tables, index = version (1..40)
  const ECC_PER_BLOCK=[-1,10,16,26,18,24,16,18,22,22,26,30,22,22,24,24,28,28,26,26,26,26,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28,28];
  const NUM_BLOCKS=[-1,1,1,1,2,2,4,4,4,5,5,5,8,9,9,10,10,11,13,14,16,17,17,18,20,21,23,25,26,28,29,31,33,35,37,38,40,43,45,47,49];
  const FORMAT_ECL_BITS=0; // M
  const data=Array.from(new TextEncoder().encode(text));
  function rawModules(v){let r=(16*v+128)*v+64;if(v>=2){const na=Math.floor(v/7)+2;r-=(25*na-10)*na-55;if(v>=7)r-=36}return r}
  function dataCodewords(v){return Math.floor(rawModules(v)/8)-ECC_PER_BLOCK[v]*NUM_BLOCKS[v]}
  // Pick the smallest version that fits
  let ver=0;for(let v=1;v<=40;v++){const cc=v<10?8:16;if(4+cc+data.length*8<=dataCodewords(v)*8){ver=v;break}}
  if(!ver)throw new Error('Data too long for QR');
  const size=ver*4+17;
  // Build the bit stream
  const bits=[];const put=(val,len)=>{for(let i=len-1;i>=0;i--)bits.push((val>>>i)&1)};
  put(4,4);put(data.length,ver<10?8:16);data.forEach(b=>put(b,8));
  const cap=dataCodewords(ver)*8;
  put(0,Math.min(4,cap-bits.length));
  while(bits.length%8)bits.push(0);
  for(let pad=0xEC;bits.length<cap;pad^=0xEC^0x11)put(pad,8);
  const dataCw=[];for(let i=0;i<bits.length;i+=8){let b=0;for(let j=0;j<8;j++)b=(b<<1)|bits[i+j];dataCw.push(b)}
  // Reed-Solomon over GF(256), polynomial 0x11D
  function gfMul(x,y){let z=0;for(let i=7;i>=0;i--){z=(z<<1)^((z>>>7)*0x11D);z^=((y>>>i)&1)*x}return z&0xFF}
  function rsDivisor(deg){const r=new Array(deg).fill(0);r[deg-1]=1;let root=1;for(let i=0;i<deg;i++){for(let j=0;j<deg;j++){r[j]=gfMul(r[j],root);if(j+1<deg)r[j]^=r[j+1]}root=gfMul(root,0x02)}return r}
  function rsRemainder(dat,div){const r=new Array(div.length).fill(0);for(const b of dat){const f=b^r.shift();r.push(0);for(let i=0;i<div.length;i++)r[i]^=gfMul(div[i],f)}return r}
  // Split into blocks, add ECC, interleave
  const nb=NUM_BLOCKS[ver],eccLen=ECC_PER_BLOCK[ver],rawCw=Math.floor(rawModules(ver)/8);
  const numShort=nb-rawCw%nb,shortLen=Math.floor(rawCw/nb),div=rsDivisor(eccLen);
  const blocks=[];for(let i=0,k=0;i<nb;i++){const dat=dataCw.slice(k,k+shortLen-eccLen+(i<numShort?0:1));k+=dat.length;const ecc=rsRemainder(dat,div);if(i<numShort)dat.push(0);blocks.push(dat.concat(ecc))}
  const allCw=[];for(let i=0;i<blocks[0].length;i++)for(let j=0;j<blocks.length;j++)if(i!==shortLen-eccLen||j>=numShort)allCw.push(blocks[j][i]);
  // Module grid and function-pattern map
  const M=[],F=[];for(let y=0;y<size;y++){M.push(new Array(size).fill(false));F.push(new Array(size).fill(false))}
  const setF=(x,y,v)=>{M[y][x]=v;F[y][x]=true};
  for(let i=0;i<size;i++){setF(6,i,i%2===0);setF(i,6,i%2===0)}
  const finder=(cx,cy)=>{for(let dy=-4;dy<=4;dy++)for(let dx=-4;dx<=4;dx++){const x=cx+dx,y=cy+dy;if(x<0||x>=size||y<0||y>=size)continue;const d=Math.max(Math.abs(dx),Math.abs(dy));setF(x,y,d!==2&&d!==4)}};
  finder(3,3);finder(size-4,3);finder(3,size-4);
  if(ver>1){const na=Math.floor(ver/7)+2;const step=ver===32?26:Math.ceil((ver*4+4)/(na*2-2))*2;const pos=[6];for(let p=size-7;pos.length<na;p-=step)pos.splice(1,0,p);
    for(let i=0;i<na;i++)for(let j=0;j<na;j++){if((i===0&&j===0)||(i===0&&j===na-1)||(i===na-1&&j===0))continue;for(let dy=-2;dy<=2;dy++)for(let dx=-2;dx<=2;dx++)setF(pos[i]+dx,pos[j]+dy,Math.max(Math.abs(dx),Math.abs(dy))!==1)}}
  function drawFormat(mask){const d=(FORMAT_ECL_BITS<<3)|mask;let r=d;for(let i=0;i<10;i++)r=(r<<1)^((r>>>9)*0x537);const b=((d<<10)|r)^0x5412;const g=i=>((b>>>i)&1)!==0;
    for(let i=0;i<=5;i++)setF(8,i,g(i));setF(8,7,g(6));setF(8,8,g(7));setF(7,8,g(8));for(let i=9;i<15;i++)setF(14-i,8,g(i));
    for(let i=0;i<8;i++)setF(size-1-i,8,g(i));for(let i=8;i<15;i++)setF(8,size-15+i,g(i));setF(8,size-8,true)}
  drawFormat(0);
  if(ver>=7){let r=ver;for(let i=0;i<12;i++)r=(r<<1)^((r>>>11)*0x1F25);const b=(ver<<12)|r;for(let i=0;i<18;i++){const v=((b>>>i)&1)!==0,a=size-11+i%3,c=Math.floor(i/3);setF(a,c,v);setF(c,a,v)}}
  // Place data bits in zigzag order
  let bi=0;for(let right=size-1;right>=1;right-=2){if(right===6)right=5;for(let vert=0;vert<size;vert++)for(let j=0;j<2;j++){const x=right-j,up=((right+1)&2)===0,y=up?size-1-vert:vert;if(!F[y][x]&&bi<allCw.length*8){M[y][x]=((allCw[bi>>>3]>>>(7-(bi&7)))&1)!==0;bi++}}}
  // Masking
  const maskFn=[(x,y)=>(x+y)%2===0,(x,y)=>y%2===0,(x,y)=>x%3===0,(x,y)=>(x+y)%3===0,(x,y)=>(Math.floor(x/3)+Math.floor(y/2))%2===0,(x,y)=>x*y%2+x*y%3===0,(x,y)=>(x*y%2+x*y%3)%2===0,(x,y)=>((x+y)%2+x*y%3)%2===0];
  const applyMask=m=>{for(let y=0;y<size;y++)for(let x=0;x<size;x++)if(!F[y][x]&&maskFn[m](x,y))M[y][x]=!M[y][x]};
  function penalty(){let p=0;const line=get=>{let run=1;for(let i=1;i<size;i++){if(get(i)===get(i-1)){run++;if(run===5)p+=3;else if(run>5)p++}else run=1}
      const pat=[1,0,1,1,1,0,1];for(let i=0;i+7<=size;i++){let ok=true;for(let k=0;k<7;k++)if((get(i+k)?1:0)!==pat[k]){ok=false;break}if(!ok)continue;let before=true,after=true;for(let k=1;k<=4;k++){if(i-k>=0&&get(i-k))before=false;if(i+6+k<size&&get(i+6+k))after=false}if(before||after)p+=40}};
    for(let y=0;y<size;y++)line(i=>M[y][i]);for(let x=0;x<size;x++)line(i=>M[i][x]);
    for(let y=0;y<size-1;y++)for(let x=0;x<size-1;x++){const c=M[y][x];if(c===M[y][x+1]&&c===M[y+1][x]&&c===M[y+1][x+1])p+=3}
    let dark=0;for(let y=0;y<size;y++)for(let x=0;x<size;x++)if(M[y][x])dark++;const tot=size*size;p+=(Math.ceil(Math.abs(dark*20-tot*10)/tot)-1)*10;return p}
  let best=0,bestP=Infinity;for(let m=0;m<8;m++){applyMask(m);drawFormat(m);const p=penalty();if(p<bestP){bestP=p;best=m}applyMask(m)}
  applyMask(best);drawFormat(best);
  return M}

// ─── INTERFACE TEXTS EDITOR ───────────────────────────────
// Keys of the main site (index.html) grouped by screen

const TEXT_GROUPS = [
    { g: 'grp_general', keys: ['subtitle', 'about_btn', 'close', 'back', 'copied', 'footer_text', 'limit_reached'] },
    { g: 'grp_note', keys: ['mode_note', 'note_label', 'note_placeholder', 'mode_preview', 'mode_edit',
        
        'note_pw_label', 'note_pw_placeholder', 'burn_label', 'burn_read', 'ttl_label',
        'burn_hint', 'note_encrypt_btn', 'link_label',
        'burn_info_read', 'burn_info_time', 'create_another',
    ] },
    { g: 'grp_read', keys: ['read_pw_label', 'read_pw_placeholder', 'attempts_remaining', 'decrypt_btn', 'decrypted_label',
        'burn_notice', 'note_destroyed_attempts', 'note_expired',
    ] },
    { g: 'grp_quick', keys: ['mode_quick', 'quick_nick_label', 'quick_pw_label', 'quick_pw_placeholder', 'quick_create_btn',
        'quick_link_info', 'qjoin_title', 'qjoin_desc', 'qjoin_btn', 'qjoin_expired', 'wrong_chat_pw'] },
    { g: 'grp_stealth', keys: ['mode_chat', 'stealth_label', 'stealth_placeholder', 'identicon_hint', 'nickname_label',
        'nickname_placeholder', 'stealth_btn', 'identicon_verify', 'chat_pw_label', 'chat_pw_placeholder',
        'enter_chat_btn', 'phrase_busy', 'wrong_stealth_pw', 'chat_locked', 'min_chars', 'counter_min'] },
    { g: 'grp_chat', keys: ['waiting_partner', 'both_connected', 'all_connected', 'connected_of', 'chat_welcome',
        'chat_input_placeholder', 'send_btn', 'typing_text', 'idle_timer', 'export_btn', 'shred_btn',
        'shred_confirm', 'room_destroyed', 'send_error', 'no_messages', 'partner_left',
        'rejoin_title', 'rejoin_desc_q', 'rejoin_desc_s', 'rejoin_btn', 'rejoin_leave', 'rejoin_phrase_wrong', 'rejoin_new_room'] },
    { g: 'grp_status', keys: ['encrypting', 'connecting', 'generating_key', 'decrypting', 'loading', 'wrong_password',
        'network_error', 'enter_password', 'write_something', 'decrypt_fail'] },
    { g: 'grp_donate', keys: ['donate_text', 'donate_text2', 'donate_text3', 'show_wallets', 'hide_wallets',
        'share_title', 'share_text', 'share_btn'] },
    { g: 'grp_htu_note', keys: ['htu_note_title', 'htu_n_sub', 'htu_n_1_t', 'htu_n_1_d', 'htu_n_2_t', 'htu_n_2_d',
        'htu_n_3_t', 'htu_n_3_d', 'htu_n_4_t', 'htu_n_4_d'] },
    { g: 'grp_htu_quick', keys: ['htu_q_title', 'htu_q_sub', 'htu_q_1_t', 'htu_q_1_d', 'htu_q_2_t', 'htu_q_2_d',
        'htu_q_3_t', 'htu_q_3_d', 'htu_q_4_t', 'htu_q_4_d'] },
    { g: 'grp_htu_stealth', keys: ['htu_title', 'htu_sub', 'htu_1_t', 'htu_1_d', 'htu_2_t', 'htu_2_d',
        'htu_3_t', 'htu_3_d', 'htu_4_t', 'htu_4_d'] },
    { g: 'grp_video', keys: ['htu_n_video', 'htu_q_video', 'htu_s_video'] },
    { g: 'grp_about', keys: ['about_html'] }
];

const TEXT_LANGS = ['en', 'ru', 'de', 'fr', 'es'];
const LANG_NAMES = { en: 'English', ru: 'Русский', de: 'Deutsch', fr: 'Français', es: 'Español' };
// Keys with long texts get a multi-line field
const LONG_KEY = /(_\d_d|^htu_sub|^htu_[nq]_sub|^donate_text\d?|^note_placeholder|^qjoin_desc|^burn_notice|^chat_welcome|^footer_text|^rejoin_desc_[qs])$/;

let textsData = {};
let editLang = 'en';

function allTextKeys() { return TEXT_GROUPS.flatMap(g => g.keys); }

function buildEditor() {
    document.getElementById('lang-tabs').innerHTML = TEXT_LANGS.map(l =>
        `<button class="lang-tab ${l === editLang ? 'active' : ''}" data-lang="${l}" onclick="switchEditLang('${l}')">${l.toUpperCase()} – ${LANG_NAMES[l]}</button>`
    ).join('');

    document.getElementById('lang-editors').innerHTML = TEXT_LANGS.map(l => {
        let html = `<div class="lang-group ${l === editLang ? 'active' : ''}" data-lang="${l}">`;
        for (const grp of TEXT_GROUPS) {
            html += `<details class="grp"><summary><span data-i18n="${grp.g}"></span><span class="grp-n">${grp.keys.length}</span></summary><div class="grp-body">`;
            for (const key of grp.keys) {
                const id = `txt-${l}-${key}`;
                html += `<div class="key-field">`;
                if (key === 'about_html') {
                    html += `<div class="key-name">${key} <span class="hint" data-i18n="html_supported"></span></div>`;
                    html += `<textarea id="${id}" class="mono" rows="8" data-i18n-ph="default_ph"></textarea>`;
                } else if (LONG_KEY.test(key)) {
                    html += `<div class="key-name">${key}</div><textarea id="${id}" rows="3" data-i18n-ph="default_ph"></textarea>`;
                } else {
                    html += `<div class="key-name">${key}</div><input type="text" id="${id}" data-i18n-ph="default_ph">`;
                }
                html += `</div>`;
            }
            html += `</div></details>`;
        }
        return html + `</div>`;
    }).join('');

    applyI18n();
}

function switchEditLang(lang) {
    editLang = lang;
    document.querySelectorAll('.lang-tab').forEach(b => b.classList.toggle('active', b.dataset.lang === lang));
    document.querySelectorAll('.lang-group').forEach(g => g.classList.toggle('active', g.dataset.lang === lang));
}

// PHP may return an empty object as [] – normalize to a plain object
function asObj(v) { return (v && typeof v === 'object' && !Array.isArray(v)) ? v : {}; }

async function loadTexts() {
    setStatus('texts-status', t('st_loading'), 'wait');
    try {
        const res = await apiGet('get_texts');
        textsData = asObj(res.texts);
        for (const l of TEXT_LANGS) {
            const langData = asObj(textsData[l]);
            for (const key of allTextKeys()) {
                const el = document.getElementById(`txt-${l}-${key}`);
                if (el) el.value = langData[key] || '';
            }
        }
        setStatus('texts-status', t('st_loaded'), 'ok');
        clearStatusLater('texts-status');
    } catch (e) {
        setStatus('texts-status', t('st_load_err'), 'err');
    }
}

async function saveTexts() {
    setStatus('texts-status', t('st_saving'), 'wait');

    // Start from saved data so keys not shown in the editor are kept (only the five site languages)
    const data = {};
    for (const [l, v] of Object.entries(asObj(textsData))) if (TEXT_LANGS.includes(l)) data[l] = Object.assign({}, asObj(v));
    for (const l of TEXT_LANGS) {
        const langData = data[l] || {};
        for (const key of allTextKeys()) {
            const el = document.getElementById(`txt-${l}-${key}`);
            if (!el) continue;
            const v = el.value.trim();
            if (v) langData[key] = v; else delete langData[key];
        }
        if (Object.keys(langData).length) data[l] = langData; else delete data[l];
    }

    try {
        const res = await apiPost('save_texts', { texts: data });
        if (res.error) { setStatus('texts-status', srvErr(res.error), 'err'); return; }
        textsData = data;
        setStatus('texts-status', t('st_saved'), 'ok');
        clearStatusLater('texts-status');
    } catch (e) {
        setStatus('texts-status', t('st_save_err'), 'err');
    }
}

// ─── CHANGE PASSWORD ──────────────────────────────────────

async function changePw() {
    const cur = document.getElementById('cur-pw').value;
    const np  = document.getElementById('new-pw').value;
    if (!cur) return setStatus('pw-status', t('st_enter_cur'), 'err');
    const bad = newPwProblem(np, document.getElementById('new-pw2').value);
    if (bad) return setStatus('pw-status', bad, 'err');

    setStatus('pw-status', t('st_changing'), 'wait');
    try {
        const res = await apiPost('change_password', { admin_password: cur, new_password: np });
        if (res.error) { document.getElementById('cur-pw').value = ''; setStatus('pw-status', srvErr(res.error), 'err'); return; }
        // The server ends the session after a password change
        toLogin(t('st_pw_relogin'), 'ok');
    } catch (e) {
        setStatus('pw-status', t('st_error'), 'err');
    }
}


// ─── INIT ─────────────────────────────────────────────────

(async function () {
    try { theme = localStorage.getItem('sp_t') === 'light' ? 'light' : 'dark'; } catch (e) { theme = 'dark'; }
    applyTheme();
    uiLang = detectLang();
    document.getElementById('sel-lang').value = uiLang;
    applyI18n();
    document.querySelectorAll('.nav-item[data-sec]').forEach(b => b.addEventListener('click', () => showSec(b.dataset.sec)));
    window.addEventListener('hashchange', () => { if (document.getElementById('admin-view').classList.contains('active')) showSec(hashSec(), true); });
    // Nothing is shown until the server says which screen is needed (no sign-in flash on reload)
    let msg = '';
    try {
        const res = await apiPost('admin_status', { k: URL_KEY });
        if (res && res.status === 'otp') { csrf = res.csrf || ''; showOtp(); return; }
        if (res && res.status === 'ok') { csrf = res.csrf || ''; enterSession(res.setup); return; }
        setLoginMode(!!(res && res.need_code));
        if (res && res.damaged === 'hash') msg = t('err_damaged_hash');
        else if (res && res.damaged === 'totp') msg = t('err_damaged_totp');
    } catch (e) {}
    toLogin(msg, msg ? 'err' : '');
})();
</script>
</body>
</html>
