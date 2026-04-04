🔥 SmoldPaper: Zero-Knowledge Secure Drop Box & Stealth Chat

In an era where every written word settles forever on corporate servers, we brought back the magic of secret letters that burn after reading.

SmoldPaper is an open-source, client-side encrypted secure drop box and stealth chat. It allows two parties to exchange highly sensitive information across compromised or monitored channels without leaving a single trace on the server.

🚀 The 3-File Revolution

We completely destroyed the old, complex Node.js/NPM architecture. SmoldPaper is now an incredibly elegant 3-file solution (index.html, api.php, admin.php).

No databases to configure, no build tools, no dependency hell. Just drop it on any standard PHP server and you are fully protected.

✨ The Magic of "Digital Ash"

SmoldPaper operates on a strict Zero-Knowledge architecture.

Client-Side Encryption: The message is encrypted locally in your browser using military-grade AES-GCM 256-bit.

PBKDF2 Key Derivation: The encryption key is derived locally using PBKDF2 (SHA-256) with 50,000 iterations, rendering brute-force attacks mathematically useless.

Blind Server: The server only receives cryptographic noise. It has no access to your keys or passwords. Even if the server is seized, the database contains only random bytes.

True Deletion: Upon successful decryption by the recipient, or after a set timer expires, the server hardware-deletes the record using a strict DELETE SQL command. No soft-deletes. No recovery.

🛠️ Features & Protocol

1. Stealth Chat (Real-time E2E)

The Stash: You and your contact agree on a complex Seed Phrase to access a shared room.

The Secret Signal: Use a decoy word in a standard messenger (WhatsApp, Telegram) to signal your contact.

The Ash: Chat securely. The room self-destructs entirely from the server hardware after 1 hour of inactivity.

2. Burn-on-Read Notes

The Secret: Write a highly sensitive note and encrypt it with a password.

The Burn: Send the secure link. The note is permanently wiped from the server the exact millisecond it is opened.

3. Multi-Language & Anti-MITM

Built-in English, Russian, German, French, and Spanish interfaces. A unique visual hash system (Color + Animal + Object) ensures no one is intercepting your connection.

⚙️ Installation (Takes 10 seconds)

SmoldPaper is designed to be ridiculously easy to self-host. You need a basic web host with PHP 7.4 or 8.x, and the PDO SQLite extension enabled (which is standard on 99% of hosts).

Download the 3 files: index.html, api.php, and admin.php.

Upload them to the public HTML directory of your web server (e.g., public_html or www).

Open your website in a browser.

That's it! On the first run, the api.php file will automatically create a secure /data folder, initialize a smoldpaper.sqlite database, and configure an .htaccess file to prevent direct downloads of the database.

🔐 Administration

To customize the UI texts, languages, or change the admin password, access the hidden Admin Panel:

Navigate to: yourdomain.com/admin.php?manage=1

The default password is: smoldpaper

Important: Change this password immediately after your first login!

❤️ Support the Project

We believe privacy is a fundamental human right. SmoldPaper is free and open-source. If this tool helps you stay safe, consider supporting its development:

BTC: bc1qxdnfjakd89qrz59cr702pt70n0wtapkcrmtnyk

ETH / BNB (BSC): 0xeAe930F5B6863Aec4a98b25e346beE20723A7F96

USDT (TRC-20) / TRX: TYqAdNNvvwzNT7LUkGCh8sZLjNQNza3NDd

Monero (XMR): 87ZQda7hirZWdmrTCBSF8GVewZ4eh8mKeRdADLDTvmZbSPe8W7zukVZKf2UEWCxBveXh8zGGyDVJBdVugY1T8LA9PvXD3CF

Litecoin (LTC): ltc1qr62jmk9h5wnc0ptvvqcjmw8zxfuthhlqu6h30r

TON: UQApKqtdQ2vlab-CvRGJve_jlNVqkf_g-mngpKMS34Ga85RT

Dogecoin (DOGE): D9j8yZyiNztiLZMZ8SSQmqgd53VfEcyJFp

⚖️ Disclaimer

This service is provided "as is". The repository owner, server host, and developers assume no liability for the content of messages transmitted through this tool. SmoldPaper is designed exclusively to protect user privacy, freedom of speech, and personal data. Using this service to facilitate illegal activities is strictly prohibited.
