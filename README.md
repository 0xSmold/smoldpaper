<div align="center">

# 🔥 SmoldPaper v3.3.0

### *Privacy is a Right.*

**Open-Source Zero-Knowledge Secure Drop Box & Stealth Chat**

[![License: MIT](https://img.shields.io/badge/License-MIT-red.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://php.net)
[![No Dependencies](https://img.shields.io/badge/Dependencies-Zero-brightgreen.svg)](#)
[![Self-Hosted](https://img.shields.io/badge/Self--Hosted-3%20Files-orange.svg)](#-installation-10-seconds)

[**🌐 Live Demo**](https://smoldpaper.org) · [**🚀 Install**](#-installation-10-seconds) · [**🛡️ Security**](#-how-the-cryptography-works) · [**🎭 Protocol**](#-the-stealth-protocol)

---

*In an era where every written word settles forever on corporate servers,*
*we brought back the magic of secret letters that turn to ash.*

</div>

---

## 💡 What is SmoldPaper?

SmoldPaper is a free, self-hosted, client-side encrypted platform for exchanging sensitive information. It allows two parties to communicate across **compromised or monitored channels** — like Telegram, WhatsApp, or Discord — without leaving a single trace on the server.

**No accounts. No logs. No cookies. No trace.**

The server is mathematically blind. Even if seized by authorities, it contains only cryptographic noise indistinguishable from random data.

---

## 🏗️ The 3-File Revolution

We destroyed the old complex architecture. SmoldPaper is now an elegantly simple **3-file solution**:

| File | Purpose |
|------|---------|
| `index.html` | The entire frontend — UI, encryption engine, 5 languages |
| `api.php` | The entire backend — API, database, cleanup |
| `admin.php` | Hidden admin panel for customization |

No Node.js. No Docker. No npm. No build tools. No dependency hell.
Just drop three files on any $2/month PHP hosting and you're protected.

---

## ✨ Features

### 🔐 Burn-on-Read Notes
Write a secret note, encrypt it with a password, get a link. The note is **physically erased** from the server the exact millisecond it's opened. Or set a timer: 1h, 6h, 12h, 24h.

### 💬 Stealth Chat (Real-Time E2E)
A real-time encrypted chat room that two people access via a shared secret phrase. All messages are encrypted client-side with AES-256-GCM. The room **self-destructs** from the server after 1 hour of inactivity.

### 🌍 5 Languages
Full localization: English, Русский, Deutsch, Français, Español. Every screen, every button, every instruction — professionally translated.

### 🎨 Themes & Accessibility
OLED dark theme, light theme, adjustable font sizes, notification sounds. Mobile-first responsive design.

### 🔑 Anti-MITM Visual Hash
A unique visual fingerprint (Color · Animal · Object) derived from the room hash. Both participants see the same code — if it doesn't match, someone is intercepting.

### 🛠️ Admin Panel
Hidden admin panel (`admin.php?manage=1`) to customize all UI texts, translations, and the admin password. No database editing required.

---

## 🎭 The Stealth Protocol

This is what makes SmoldPaper unique. It's not just a tool — it's a **communication protocol** designed for hostile environments.

### Step 1 — The Stash
Agree with your contact on a **secret seed phrase** (25+ characters) for SmoldPaper. Do this in person, or through a channel you trust.

### Step 2 — The Signal
Agree on a **trigger word** for regular messengers. Example:
> *"If I start any message with the word **Buddy**, it means this exact message is the key to a secret."*

### Step 3 — The Drop
Chat normally on WhatsApp, Telegram, Signal — wherever. When you spot the trigger word, **copy that entire message**.

### Step 4 — Read & Burn
Open SmoldPaper → "Stealth Chat" → paste the message as the seed phrase → enter the shared password → communicate securely. When you're done, the chat **burns without a trace**. 🔥

> To any outside observer, you were just chatting about everyday things. The secret conversation happened in a place that no longer exists.

---

## 🛡️ How the Cryptography Works

```
Your Password
     │
     ▼
┌─────────────────────────┐
│  PBKDF2 (SHA-256)       │
│  50,000 iterations      │
│  + random 128-bit salt  │
│         │               │
│         ▼               │
│  256-bit AES Key        │
└─────────────────────────┘
     │
     ▼
┌─────────────────────────┐
│  AES-256-GCM Encrypt    │
│  + random 96-bit IV     │
│         │               │
│         ▼               │
│  Salt + IV + Ciphertext │
│  (Base64 encoded)       │
└─────────────────────────┘
     │
     ▼
  Server receives ONLY this
  (indistinguishable from noise)
```

**Key points:**
- **50,000 PBKDF2 iterations** — even a supercomputer needs centuries to brute-force a decent password
- **Random salt per message** — identical passwords produce different ciphertexts
- **AES-GCM authenticated encryption** — detects any tampering
- **The server never sees the password** — decryption happens exclusively in the recipient's browser
- **Web Crypto API** — uses the browser's native, audited cryptographic engine (no JavaScript crypto libraries)

---

## 🚀 Installation (10 seconds)

### Requirements
- Any web server with **PHP 7.4+** (Apache, Nginx, LiteSpeed)
- **PDO SQLite** extension (enabled by default on 99% of hosts)
- That's it. Seriously.

### For Users

1. Download the [latest release](https://github.com/0xSmold/smoldpaper/releases)
2. Upload `index.html`, `api.php`, `admin.php` to your web server
3. Open your domain in the browser
4. **Done.** 🎉

On first run, `api.php` automatically:
- Creates a secure `/data` directory
- Initializes the `smoldpaper.sqlite` database (WAL mode, optimized)
- Configures `.htaccess` to block direct database access

### For Developers

```bash
git clone https://github.com/0xSmold/smoldpaper.git
cd smoldpaper
# Just open index.html in a browser with a local PHP server:
php -S localhost:8000
```

---

## ⚙️ Administration

Access the hidden Admin Panel:

1. Navigate to `yourdomain.com/admin.php?manage=1`
2. Default password: `smoldpaper`
3. **⚠️ Change this immediately after first login!**

The panel allows you to:
- Edit all interface texts in all 5 languages
- Change the admin password
- Customize the About page content

Without `?manage=1`, the admin page returns a convincing 404.

---

## 🔒 Security Architecture

| Layer | Implementation |
|-------|---------------|
| Encryption | AES-256-GCM (Web Crypto API) |
| Key Derivation | PBKDF2-SHA256, 50,000 iterations |
| Data at Rest | Only ciphertext stored (zero-knowledge) |
| Deletion | Hard `DELETE` SQL — no soft-deletes, no recovery |
| Brute-force | Notes auto-expire; no retry counters needed since server can't decrypt |
| Transport | HTTPS (your server's TLS) |
| MITM Detection | Visual hash fingerprinting (Color · Animal · Object) |
| XSS Protection | DOM-based sanitization of all rendered markdown |
| Race Conditions | Atomic SQLite transactions (`BEGIN IMMEDIATE`) |
| Admin Access | bcrypt password hashing, hidden endpoint |

### What SmoldPaper does NOT do:
- ❌ Store passwords or encryption keys
- ❌ Use cookies or tracking
- ❌ Log IP addresses
- ❌ Keep any record of destroyed messages
- ❌ Phone home to any external server
- ❌ Load external resources (except the optional `marked.js` CDN for Markdown)

---

## 📋 Changelog

### v3.3 — Security & Localization Update
- 🛡️ **XSS fix**: DOM-based markdown sanitization replaces vulnerable regex
- 🔒 **Race condition fix**: Atomic room creation with `BEGIN IMMEDIATE`
- ✅ **Sender validation**: Server verifies sender identity against room state
- 🌍 **Full translations**: All 5 languages now have complete, professional-quality texts
- ✨ **Improved "About" page**: More compelling content across all languages
- 🏷️ **New tagline**: "Privacy is a Right." across all languages

### v3.2 — The 3-File Revolution
- Eliminated entire Node.js/NPM build system
- Reduced to 3 files: `index.html`, `api.php`, `admin.php`
- Added Stealth Chat with real-time E2E encryption
- Added admin panel with multi-language text editor
- Added visual hash anti-MITM system

### v2.0.0 "Phoenix" — Performance Update
- Removed 3MB external CSS runtime
- SQLite WAL mode for concurrency
- OLED dark theme
- Embedded audio engine

---

## ❤️ Support the Project

We share this app for free with everyone in the world because **every person has the right to privacy**.

This app will protect many from persecution and may even save someone's life.

Maintaining servers and development requires funding:

| Currency | Address |
|----------|---------|
| **Bitcoin** | `bc1qxdnfjakd89qrz59cr702pt70n0wtapkcrmtnyk` |
| **USDT (TRC-20) / TRX** | `TYqAdNNvvwzNT7LUkGCh8sZLjNQNza3NDd` |
| **Monero** | `87ZQda7hirZWdmrTCBSF8GVewZ4eh8mKeRdADLDTvmZbSPe8W7zukVZKf2UEWCxBveXh8zGGyDVJBdVugY1T8LA9PvXD3CF` |
| **ETH / BNB (BSC)** | `0xeAe930F5B6863Aec4a98b25e346beE20723A7F96` |
| **Litecoin** | `ltc1qr62jmk9h5wnc0ptvvqcjmw8zxfuthhlqu6h30r` |
| **TON** | `UQApKqtdQ2vlab-CvRGJve_jlNVqkf_g-mngpKMS34Ga85RT` |
| **Dogecoin** | `D9j8yZyiNztiLZMZ8SSQmqgd53VfEcyJFp` |

---

## 🔗 Help the Project Grow

The development of this project depends entirely on **you**!

Share the link with your friends and the whole world so people always have the ability to communicate confidentially — even where it seems impossible at first glance.

**⭐ Star this repo** — it costs nothing and helps others discover SmoldPaper.

---

## ⚖️ Disclaimer

This service is provided "as is". The owner assumes no liability. Using this service for illegal activities is strictly prohibited. Designed exclusively for **privacy and free speech**.

---

## 📝 License

MIT License. Copyright (c) 2025–2026 SmoldPaper Contributors.

---

<div align="center">

*Built with paranoia and love.*

**[smoldpaper.org](https://smoldpaper.org)**

</div>
