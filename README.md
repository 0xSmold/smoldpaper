<div align="center">

# 🔥 SmoldPaper v3.5.0

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
| `api.php` | The entire backend — API, database, cleanup, rate limiting |
| `admin.php` | Hidden admin panel for customization |

No Node.js. No Docker. No npm. No build tools. No dependency hell.
Just drop three files on any $2/month PHP hosting and you're protected.

---

## ✨ Features

### 🔐 Burn-on-Read Notes

Write a secret note, encrypt it with a password, get a link. The note is **physically erased** from the server the exact millisecond it's opened. Or set a timer: 1h, 6h, 12h, 24h.

https://github.com/0xSmold/smoldpaper/raw/main/demo/1en_smaller.mp4

### 💬 Quick Chat

Create an encrypted room, get an invite link, send it to someone. They enter the shared password — you both chat with E2E encryption. Once two people are in, the room is **sealed forever** — nobody else can join, even with the link. When you're done, it self-destructs. The old link becomes a dead end.

https://github.com/0xSmold/smoldpaper/raw/main/demo/2en_smaller.mp4

### 🕵️ Stealth Chat

The most advanced mode — designed for hostile environments. You agree on a password and a code word with your contact. Then you chat normally in any messenger. When the code word appears in a message — that entire message becomes the key to a hidden encrypted room in SmoldPaper. To any outside observer, nothing unusual happened.

https://github.com/0xSmold/smoldpaper/raw/main/demo/3en_smaller.mp4

### 🌍 5 Languages
Full localization: English, Русский, Deutsch, Français, Español. Every screen, every button, every instruction — professionally translated. Built-in demo videos switch automatically per language.

### 🎨 Themes & Accessibility
Warm dark theme, light theme, adjustable font sizes, notification sounds. Mobile-first responsive design. PWA support — install as an app on your phone.

### 🔑 Anti-MITM Visual Hash
A unique visual fingerprint (Color · Animal · Object) derived from the room hash. Both participants see the same code — if it doesn't match, someone is intercepting.

### 🛠️ Admin Panel
Hidden admin panel (`admin.php?manage=1`) to customize all UI texts, translations, video embeds, and the admin password. No database editing required.

### 📹 Built-in Video Guides
Each feature has an embedded video tutorial right in the interface. Videos switch automatically based on the selected language. Supports local MP4 files, YouTube (via privacy-enhanced `youtube-nocookie.com`), and Vimeo (with `dnt=1` — Do Not Track). Even the video embeds respect your privacy.

---

## 🎭 The Stealth Protocol

This is what makes SmoldPaper unique. It's not just a tool — it's a **communication protocol** designed for hostile environments.

### Step 1 — The Agreement
Agree with your contact on two things: a **shared password** and a **code word**. Do this in person or through a channel you trust. This only needs to happen once.

### Step 2 — The Signal
Chat normally on WhatsApp, Telegram, Signal — wherever. When you need to start a secret conversation, send a message containing the code word. For example:
> *"**Buddy**, take a look at the report when you can."*

The code word tells your contact: "this entire message is the key."

### Step 3 — The Entry
Your contact sees the code word → copies the **entire message** → opens SmoldPaper → pastes it as the stealth phrase → enters the shared password → both of you are in the same encrypted room.

### Step 4 — Read & Burn
Communicate securely. When you're done, the room **burns without a trace**. 🔥

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

### Quick Start

1. Download the [latest release](https://github.com/0xSmold/smoldpaper/releases)
2. Upload `index.html`, `api.php`, `admin.php`, `manifest.json`, and `favicon.svg` to your web server
3. Open your domain in the browser
4. **Done.** 🎉

On first run, `api.php` automatically:
- Creates a secure `/data` directory
- Initializes the `smoldpaper.sqlite` database (WAL mode, optimized)
- Configures `.htaccess` to block direct database access
- Sets up rate limiting storage

### Optional: Video Guides

SmoldPaper includes built-in video tutorials that appear inside the "How to use" sections. To enable them:

1. Download the video pack from the [latest release](https://github.com/0xSmold/smoldpaper/releases) assets
2. Place the MP4 files in the **same directory** as `index.html`
3. Videos are available in English and Russian. The app automatically shows the correct language version

File names expected by default:
- English: `1 en_smaller.mp4`, `2 en_smaller.mp4`, `3 en_smaller.mp4`
- Russian: `1 ru_smaller.mp4`, `2 ru_smaller.mp4`, `3 ru_smaller.mp4`

You can change video URLs per language via the Admin Panel, including YouTube and Vimeo links.

---

## ⚙️ Administration

Access the hidden Admin Panel:

1. Navigate to `yourdomain.com/admin.php?manage=1`
2. Default password: `smoldpaper`
3. **⚠️ Change this immediately after first login!**

The panel allows you to:
- Edit all interface texts in all 5 languages
- Set video/GIF URLs for each language and feature
- Change the admin password

Without `?manage=1`, the admin page returns a convincing 404.

---

## 🔒 Security Architecture

| Layer | Implementation |
|-------|---------------|
| Encryption | AES-256-GCM (Web Crypto API) |
| Key Derivation | PBKDF2-SHA256, 50,000 iterations |
| Data at Rest | Only ciphertext stored (zero-knowledge) |
| Deletion | Hard `DELETE` SQL — no soft-deletes, no recovery |
| Transport | HTTPS (your server's TLS) |
| MITM Detection | Visual hash fingerprinting (Color · Animal · Object) |
| XSS Protection | DOM-based sanitization of all rendered Markdown |
| Race Conditions | Atomic SQLite transactions (`BEGIN IMMEDIATE`) |
| Rate Limiting | 120 req/min per IP, file-based, no Redis needed |
| Admin Access | bcrypt password hashing, hidden endpoint |
| Session Recovery | sessionStorage for chat key (survives page refresh, cleared on tab close) |
| Video Embeds | YouTube via `youtube-nocookie.com`, Vimeo with `dnt=1` |
| External Calls | Zero. Markdown parser (marked.js) is bundled inline |

### What SmoldPaper does NOT do:
- ❌ Store passwords or encryption keys
- ❌ Use cookies or tracking
- ❌ Log IP addresses
- ❌ Keep any record of destroyed messages
- ❌ Phone home to any external server
- ❌ Load any external resources

---

## 📋 Changelog

### v3.5.0 — "Full Autonomy"
- 🎬 **Built-in video guides**: MP4, YouTube (nocookie), Vimeo (DNT) support per language
- 🛡️ **Rate limiting**: 120 req/min per IP, file-based
- 🌐 **OG meta tags & Twitter Cards**: rich link previews when sharing
- 📱 **PWA manifest**: installable as a mobile app
- 📦 **Marked.js bundled inline**: zero external CDN calls, 100% autonomous
- 🔄 **Session recovery**: chat key survives page refresh via sessionStorage
- 📏 **Character counter** for notes with size warning
- 🎨 **Refined light theme**: warm cream palette with proper contrast
- 📝 **Note textarea**: 300px min height, auto-expand, resizable
- 🔥 **Styled destruction message**: highlighted yellow banner, impossible to miss
- 🗣️ **All code comments in English** for international contributors

### v3.4.0 — "Quick Chat"
- 💬 **Quick Chat**: invite-link-based E2E encrypted chat rooms
- 🔒 Rooms sealed at 2 participants — third person sees "expired"
- 🪦 Dead rooms persist 24h as decoys, then auto-cleanup
- 🆔 Visual hash verification for Quick Chat rooms
- 🌍 Quick Chat translations for all 5 languages
- 📱 Mobile-optimized tabs for 3-tab layout

### v3.3.0 — "Hardened Ash"
- 🛡️ DOM-based XSS sanitization for Markdown
- 🔒 Atomic room creation with `BEGIN IMMEDIATE`
- ✅ Server-side sender validation
- 🌍 Full professional translations for all 5 languages
- ✨ Rewritten "About" page across all languages
- 🏷️ New tagline: "Privacy is a Right."

### v3.2 — "The 3-File Revolution"
- Eliminated entire Node.js/NPM build system
- Reduced to 3 files: `index.html`, `api.php`, `admin.php`
- Added Stealth Chat with real-time E2E encryption
- Added admin panel with multi-language text editor
- Added visual hash anti-MITM system

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
