# 🔥 SmoldPaper: Zero-Knowledge Secret Stash

<p align="center">
  <b>A lightweight, self-hosted, end-to-end encrypted message stash that burns after reading.</b>
</p>

**🌐 Live Demo:** [smoldpaper.org](https://smoldpaper.org)

In an era where every written word settles forever on corporate servers, we brought back the magic of secret letters that turn to ash. 

SmoldPaper is an open-source, client-side encrypted secure drop box. It allows two parties to exchange sensitive information across compromised or monitored channels without leaving a single trace on the server.

## 🛡️ Why SmoldPaper?

SmoldPaper operates on a strict **Zero-Knowledge architecture**. The server never sees your passwords, seed phrases, or the contents of your messages. It only stores cryptographic noise.

* **Client-Side Encryption:** Military-grade `AES-GCM 256-bit` encryption happens entirely in your browser using the native Web Crypto API.
* **No Traces:** Messages are physically deleted from the database via strict `DELETE` SQL commands upon reading or expiration. No soft-deletes. No recovery.
* **Brute-Force Protection:** The container self-destructs after 5 incorrect decryption attempts.
* **Stupidly Simple Hosting:** No Node.js, no Docker, no complex DB setup. Just drop 2 files on any cheap PHP shared hosting, and you are good to go.

## 🎭 How It Works (The Protocol)

1. **The Stash:** Agree on a secret seed phrase with your contact to access a shared SmoldPaper room.
2. **The Signal:** Agree on a trigger word for regular chats. Example: *"If a message starts with **Buddy**, it's actually a hidden key."*
3. **The Drop:** Chat normally. When you spot the trigger word, copy that entire message into SmoldPaper to unlock the secret.
4. **Read & Burn:** The moment you read the secret message, it instantly turns to ash 🔥

## 🧠 How the Cryptography Works

1. **Key Derivation:** Your public phrase (password) is strengthened using `PBKDF2` with 100,000 iterations and a random salt to generate a 256-bit key.
2. **Encryption:** The message is encrypted locally using `AES-GCM` with a random Initialization Vector (IV).
3. **Storage:** The Salt, IV, and Ciphertext are concatenated, encoded in Base64, and sent to the server.
4. **Decryption:** Happens exclusively on the recipient's device. The server never receives the key.

## 🚀 Installation (Takes 1 Minute)

SmoldPaper is designed to be ridiculously easy to self-host. You literally only need a basic PHP server (Apache/Nginx).

1. Clone or download this repository.
2. Upload `index.html` (the compiled frontend) and `api.php` (the backend) to your web server's public directory.
3. Open your domain in the browser.

*Note: The PHP script will automatically generate a secure `database.sqlite` file and an `.htaccess` file to prevent direct downloads of the database upon the first request.*

## ⚙️ Administration

To customize the UI texts, languages, or add a YouTube/Vimeo embed to the login screen, access the hidden Admin Panel:

1. Enter `admin123` as the Seed Phrase on the main screen (**change this immediately**).
2. The Admin Panel allows you to edit all i18n dictionaries and the footer HTML directly from the browser.

## 🛠️ Stack

* **Frontend:** React (Standalone via CDN or compiled via Vite), Tailwind CSS, Lucide Icons.
* **Backend:** Single-file PHP + SQLite3.

## ❤️ Support the Project

❤️ We decided to share this app for free with everyone in the world because everyone has the right to privacy. Please consider supporting us at any of these wallets:

* **BTC:** `bc1qxdnfjakd89qrz59cr702pt70n0wtapkcrmtnyk`
* **USDT (TRC-20) / TRX:** `TYqAdNNvvwzNT7LUkGCh8sZLjNQNza3NDd`
* **Monero (XMR):** `87ZQda7hirZWdmrTCBSF8GVewZ4eh8mKeRdADLDTvmZbSPe8W7zukVZKf2UEWCxBveXh8zGGyDVJBdVugY1T8LA9PvXD3CF`
* **ETH / BNB (BSC):** `0xeAe930F5B6863Aec4a98b25e346beE20723A7F96`
* **Litecoin (LTC):** `ltc1qr62jmk9h5wnc0ptvvqcjmw8zxfuthhlqu6h30r`
* **TON:** `UQApKqtdQ2vlab-CvRGJve_jlNVqkf_g-mngpKMS34Ga85RT`
* **Dogecoin (DOGE):** `D9j8yZyiNztiLZMZ8SSQmqgd53VfEcyJFp`

## ⚖️ Disclaimer

This service is provided "as is". The repository owner, server host, and developers assume no liability for the content of messages transmitted through this tool. SmoldPaper is designed exclusively to protect user privacy, freedom of speech, and personal data. Using this service to facilitate illegal activities is strictly prohibited.

## 📝 License

**MIT License**

Copyright (c) 2024 SmoldPaper Contributors

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
