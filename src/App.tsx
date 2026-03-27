import React, { useState, useEffect, useRef } from 'react';
import { 
  Key, Lock, Unlock, Eye, EyeOff, Clock, Trash2, Volume2, VolumeX, Volume1,
  Send, Copy, Check, Info, Settings, LogOut, ShieldAlert, Zap, AlertTriangle, 
  Dices, Flame, Globe, X, Shield, Save, Share2, MessageSquare, UserPlus,
  Sun, Moon, ChevronDown, ChevronUp, Wallet
} from 'lucide-react';

/**
 * Environment detection and API configuration
 */
const isPreviewEnv = typeof window !== 'undefined' && (
  window.location.protocol === 'blob:' || 
  window.location.protocol === 'data:' || 
  window.location.origin === 'null' ||
  window.location.hostname.includes('localhost') ||
  window.location.hostname.includes('127.0.0.1') ||
  window.location.hostname.includes('webcontainer')
);
const API_URL = 'api.php'; 

// ==========================================
// LOCALIZATION AND DICTIONARIES
// ==========================================
const FALLBACK_DICT: Record<string, any> = {
  ru: {
    appTitle: "SmoldPaper", tagline: "Open-Source Zero-Knowledge Box", loginTitle: "Доступ к тайнику",
    loginDesc: "Сгенерируйте или введите сложную уникальную сид-фразу для создания или входа в защищенную комнату. Сервер её не сохраняет.",
    seedPlaceholder: "Сид-фраза от 8 до 255 символов", btnEnter: "Открыть тайник",
    loginEmbedHtml: "", 
    encryptAndSend: "Зашифровать и оставить", pubPhraseLabel: "Публичная фраза (Ключ из чата)",
    pubPhrasePlaceholder: "Например: Дружище, как дела?", 
    publicLabel: "Открытый ярлык",
    publicLabelPh: "Например: Для Анны / Отчет",
    publicLabelWarn: "Текст в этом поле не шифруется. Вы можете сюда написать имя получателя, если используете один Тайник для переписки со множеством людей или что-то ещё, что позволит получателю понять, что именно это сообщение из списка нужно расшифровывать ему.",
    secretLabel: "Секретное сообщение",
    secretPlaceholder: "Этот текст будет зашифрован локально перед отправкой...", settingsLabel: "Параметры сгорания",
    delAfter: "Сжечь через", maxReads: "Макс. прочтений", btnSending: "Шифруем...", btnSent: "Оставлено в тайнике",
    waitInBox: "Тлеют в тайнике", boxEmpty: "Тайник пуст", msgAutoAppear: "Новые сообщения появятся здесь автоматически.",
    cipherPrefix: "Шифровка #", errAttempts: "Ошибок", needDecrypt: "Требуется дешифровка",
    decryptPhrasePlaceholder: "Введите публичную фразу...", btnDecrypt: "Дешифровать", readsLeft: "Осталось чтений",
    warnDelete5: "Внимание: 5 неверных попыток навсегда сожгут сообщение.",
    decryptedLocal: "Расшифровано локально", copyWarn: "Скопируйте нужную информацию. При закрытии окна этот текст превратится в пепел и будет утерян.",
    infoHowItWorks: "Магия тлеющей бумаги", 
    infoP1: "В эпоху, когда каждое написанное слово навсегда оседает на серверах корпораций, мы вернули магию тайных писем, сгорающих после прочтения. <br/><br/><strong>SmoldPaper (Тлеющая бумага)</strong> — это <strong>бесплатный проект с открытым исходным кодом (Open Source)</strong>, превращающий вашу переписку в цифровой пепел. Текст шифруется прямо на вашем устройстве, передается как криптографический шум и безвозвратно уничтожается (сгорает) сразу после расшифровки. Никаких следов. Только вы и ваш собеседник.",
    
    infoAlgTitle: "Как пользоваться?",
    step1Title: "Тайник",
    infoAlg1: "Договоритесь с собеседником о секретной сид-фразе для доступа к общей комнате в SmoldPaper.",
    step2Title: "Условный сигнал",
    infoAlg2: "Придумайте кодовое слово для обычных чатов. Например: <em>«Если сообщение начинается со слова <strong>Дружище</strong>, значит это ключ»</em>.",
    step3Title: "Передача",
    infoAlg3: "Общайтесь как обычно. Заметив кодовое слово, просто скопируйте это сообщение в SmoldPaper, чтобы расшифровать тайну.",
    step4Title: "Чтение и пепел",
    infoAlg4: "Как только вы прочитаете секретное сообщение, оно мгновенно сгорит 🔥",
    infoAlgOutro: "🕵️‍♂️ <em>Переписывайтесь у всех на виду, не вызывая подозрений. Обменивайтесь секретами в SmoldPaper!</em>",

    infoTechTitle: "Как работает шифрование",
    infoTechText: "Все данные шифруются прямо в вашем браузере по военному стандарту <strong>AES-GCM 256-bit</strong>. Публичная фраза усложняется алгоритмом PBKDF2 (10,000 итераций), превращаясь в сверхнадежный ключ. <br/><br/>Сервер работает по принципу <strong>Zero-Knowledge</strong> (Нулевое разглашение) — он физически не имеет ключей. Даже если сервер будет взломан или изъят, внутри базы данных найдут лишь криптографический шум. Аппаратное удаление выполняется строгой командой <code>DELETE</code> — восстановить текст с диска невозможно.",
    infoRisksTitle: "Векторы атак и риски",
    infoRisksText: "Несмотря на совершенную криптографию, помните о человеческом факторе:<br/>• <strong>Подглядывание (Shoulder Surfing):</strong> Убедитесь, что за вашей спиной нет камер или посторонних глаз.<br/>• <strong>Слабый пароль:</strong> Использование коротких фраз в качестве ключа делает шифр уязвимым. Генерируйте сложные фразы встроенным инструментом (🎲).<br/>• <strong>Социальная инженерия:</strong> Никогда не передавайте расшифрованный текст третьим лицам. Сообщение превращается в пепел, но скриншот экрана остается на вашей совести.",
    infoHardwareDelTitle: "Аппаратное сожжение", infoHardwareDelText: "Как только истекает таймер или исчерпывается лимит прочтений, запись навсегда удаляется. Восстановить её невозможно.",
    infoBruteTitle: "Защита от подбора", infoBruteText: "Ввод неверной \"Публичной фразы\" запускает счетчик. После 5 неверных попыток контейнер самоуничтожается.",
    timeExpired: "Сгорело. Удаляем...", timeDelIn: "Сгорит через", days: "д", hours: "ч", mins: "м", secs: "с",
    errLastRead: "ВНИМАНИЕ: Это было последнее прочтение. Информация полностью сожжена на сервере.",
    errDestroyedLimit: "Сообщение СОЖЖЕНО: превышен лимит в 5 неверных попыток ввода ключа.",
    errWrongPhrase: "Неверная публичная фраза. Осталось попыток:", btnBack: "Назад",
    time1m: "1 минуту", time10m: "10 минут", time1h: "1 час", time10h: "10 часов", time1d: "1 день", time7d: "7 дней",
    read1: "1 раз", read2: "2 раза", read3: "3 раза", read5: "5 раз", read10: "10 раз", read20: "20 раз",
    errNetwork: "Сетевая ошибка.", copied: "Скопировано в буфер",
    disclaimerTitle: "Отказ от ответственности",
    disclaimerText: "Данный сервис предоставляется «как есть» (as is). Владелец сервера, хостинг-провайдер и разработчики не несут никакой ответственности за содержимое передаваемых сообщений и последствия их использования. Инструмент создан исключительно с целью защиты приватности, свободы общения и личных данных пользователей. Использование сервиса для организации незаконной деятельности категорически запрещено.",
    donateMessage: "❤️ Мы решили бесплатно поделиться этим приложением со всеми людьми в мире, потому что каждый человек имеет право на приватность.<br/><br/>Это приложение защитит многих от преследования и, возможно, даже <strong>сохранит кому-то жизнь!</strong><br/><br/>Поддержка серверов и развитие проекта требуют ресурсов. Пожалуйста, рассмотрите возможность помочь проекту на любой из этих кошельков:",
    shareTitle: "Поддержите развитие проекта",
    shareText: "Только от вас зависит развитие проекта! Поделитесь ссылкой на этот проект с близкими и со всем миром, чтобы люди всегда имели возможность общаться конфиденциально даже там, где это на первый взгляд невозможно!",
    btnShare: "Скопировать ссылку на проект",
    
    btnAbout: "О проекте",
    btnShowWallets: "Показать кошельки",
    btnHideWallets: "Скрыть кошельки",
    btnInviteRoom: "Пригласить в тайник",
    btnHowItWorks: "Как это работает",
    errSeedShort: "Сид-фраза должна содержать не менее 8 символов.",
    errNoHttps: "Внимание: нет HTTPS или Crypto API недоступно. Шифрование невозможно.",
  },
  en: {
    appTitle: "SmoldPaper", tagline: "Open-Source Zero-Knowledge Box", loginTitle: "Access the Stash",
    loginDesc: "Generate or enter a complex unique seed phrase to access a secure room. The server never saves it.",
    seedPlaceholder: "Seed phrase (8 to 255 chars)", btnEnter: "Open Stash", loginEmbedHtml: "",
    encryptAndSend: "Encrypt & Leave", pubPhraseLabel: "Public Phrase (Chat Key)",
    pubPhrasePlaceholder: "E.g.: Hey buddy, how are you?", 
    publicLabel: "Open Label", publicLabelPh: "E.g.: For Anna / Report",
    publicLabelWarn: "The text in this field is not encrypted. You can write the recipient's name here if you use a single Stash to communicate with multiple people, or anything else that will help the recipient understand that this specific message from the list is meant for them to decrypt.",
    secretLabel: "Secret Message",
    secretPlaceholder: "This text will be encrypted locally before sending...", settingsLabel: "Burn Parameters",
    delAfter: "Burn after", maxReads: "Max reads", btnSending: "Encrypting...", btnSent: "Left in the stash",
    waitInBox: "Smoldering in stash", boxEmpty: "Stash is empty", msgAutoAppear: "New messages will appear here automatically.",
    cipherPrefix: "Cipher #", errAttempts: "Errors", needDecrypt: "Decryption Required",
    decryptPhrasePlaceholder: "Enter the public phrase...", btnDecrypt: "Decrypt", readsLeft: "Reads left",
    warnDelete5: "Warning: 5 incorrect attempts will permanently burn the message.",
    decryptedLocal: "Decrypted Locally", copyWarn: "Copy what you need. When you close this window, the text turns to ash and is lost forever.",
    infoHowItWorks: "The Magic of Smoldering Paper", 
    infoP1: "In an era where every written word settles forever on corporate servers, we brought back the magic of secret letters that burn after reading. <br/><br/><strong>SmoldPaper</strong> is a <strong>free and open-source</strong> digital ash. Your messages are encrypted right on your device, transmitted as cryptographic noise, and irretrievably destroyed immediately after decryption. No traces. Just you and your contact.",
    
    infoAlgTitle: "How to use it?",
    step1Title: "The Stash",
    infoAlg1: "Agree on a secret seed phrase with your contact to access a shared SmoldPaper room.",
    step2Title: "The Signal",
    infoAlg2: "Agree on a trigger word for regular chats. Example: <em>«If a message starts with <strong>Buddy</strong>, it's actually a hidden key»</em>.",
    step3Title: "The Drop",
    infoAlg3: "Chat normally. When you spot the trigger word, copy that entire message into SmoldPaper to unlock the secret.",
    step4Title: "Read & Burn",
    infoAlg4: "The moment you read the secret message, it instantly turns to ash 🔥",
    infoAlgOutro: "🕵️‍♂️ <em>Chat in plain sight without raising suspicion. Exchange secrets in SmoldPaper!</em>",

    infoTechTitle: "How Encryption Works",
    infoTechText: "All data is encrypted directly in your browser using the military-grade <strong>AES-GCM 256-bit</strong> standard. The server operates on a <strong>Zero-Knowledge</strong> principle—it physically holds no keys. Even if the server is seized or hacked, attackers will only find cryptographic noise. Hardware deletion is performed via strict DELETE commands.",
    infoRisksTitle: "Attack Vectors & Risks",
    infoRisksText: "Despite perfect cryptography, human factors remain:<br/>• <strong>Shoulder Surfing:</strong> Ensure no cameras or prying eyes are behind you.<br/>• <strong>Weak Passwords:</strong> Using simple phrases makes the cipher vulnerable.<br/>• <strong>Social Engineering:</strong> Never share decrypted text with third parties.",
    infoHardwareDelTitle: "Hardware Burning", infoHardwareDelText: "Once the timer expires or read limit is reached, the record is permanently deleted.",
    infoBruteTitle: "Hack Protection", infoBruteText: "After 5 failed decryption attempts, the container self-destructs.",
    timeExpired: "Burned. Deleting...", timeDelIn: "Burns in", days: "d", hours: "h", mins: "m", secs: "s",
    errLastRead: "WARNING: This was the last read. Information is completely burned on the server.",
    errDestroyedLimit: "Message BURNED: exceeded the limit of 5 incorrect key attempts.",
    errWrongPhrase: "Incorrect public phrase. Attempts left:", btnBack: "Back",
    time1m: "1 minute", time10m: "10 minutes", time1h: "1 hour", time10h: "10 hours", time1d: "1 day", time7d: "7 days",
    read1: "1 time", read2: "2 times", read3: "3 times", read5: "5 times", read10: "10 times", read20: "20 times",
    errNetwork: "Network error.", copied: "Copied to clipboard",
    disclaimerTitle: "Disclaimer", disclaimerText: "This service is provided \"as is\". The owner assumes no liability. Using this service for illegal activities is strictly prohibited.",
    donateMessage: "❤️ We decided to share this app for free with everyone in the world because everyone has the right to privacy.<br/><br/>This app will protect many from persecution and might even <strong>save someone's life!</strong><br/><br/>Maintaining servers and developing the project requires funding. Please consider supporting the project at any of these wallets:",
    shareTitle: "Help the project grow",
    shareText: "The development of this project depends entirely on you! Share the link to this project with your friends and the whole world so people always have the ability to communicate confidentially, even where it seems impossible at first glance!",
    btnShare: "Copy link to service",
    
    btnAbout: "About",
    btnShowWallets: "Show wallets",
    btnHideWallets: "Hide wallets",
    btnInviteRoom: "Invite to stash",
    btnHowItWorks: "How it works",
    errSeedShort: "Seed phrase must be at least 8 characters long.",
    errNoHttps: "Warning: No HTTPS or Crypto API unavailable. Encryption disabled.",
  },
  de: {
    appTitle: "SmoldPaper", tagline: "Open-Source Zero-Knowledge Box", loginTitle: "Zugang zum Versteck",
    loginDesc: "Generieren oder geben Sie eine komplexe, einzigartige Seed-Phrase ein. Der Server speichert sie nicht.",
    seedPlaceholder: "Seed-Phrase (8 bis 255 Z.)", btnEnter: "Versteck öffnen", loginEmbedHtml: "",
    encryptAndSend: "Verschlüsseln & Hinterlassen", pubPhraseLabel: "Öffentlicher Satz (Chat-Schlüssel)",
    pubPhrasePlaceholder: "Z.B.: Hallo Kumpel, wie geht's?", 
    publicLabel: "Offenes Etikett", publicLabelPh: "Z.B.: Für Anna / Bericht",
    publicLabelWarn: "Der Text in diesem Feld ist nicht verschlüsselt. Sie können hier den Namen des Empfängers eintragen, wenn Sie ein einziges Versteck für die Kommunikation mit mehreren Personen verwenden, oder etwas anderes, das dem Empfänger hilft zu verstehen, dass diese bestimmte Nachricht aus der Liste für ihn zum Entschlüsseln bestimmt ist.",
    secretLabel: "Geheime Nachricht",
    secretPlaceholder: "Dieser Text wird vor dem Senden lokal verschlüsselt...", settingsLabel: "Verbrennungsparameter",
    delAfter: "Verbrennen nach", maxReads: "Max. Aufrufe", btnSending: "Verschlüsseln...", btnSent: "Im Versteck hinterlassen",
    waitInBox: "Schwelt im Versteck", boxEmpty: "Das Versteck ist leer", msgAutoAppear: "Neue Nachrichten erscheinen hier automatisch.",
    cipherPrefix: "Chiffre #", errAttempts: "Fehler", needDecrypt: "Entschlüsselung erforderlich",
    decryptPhrasePlaceholder: "Öffentlichen Satz eingeben...", btnDecrypt: "Entschlüsseln", readsLeft: "Verbleibende Aufrufe",
    warnDelete5: "Achtung: 5 falsche Versuche verbrennen die Nachricht endgültig.",
    decryptedLocal: "Lokal entschlüsselt", copyWarn: "Kopieren Sie die Infos. Beim Schließen zerfällt der Text zu Asche.",
    infoHowItWorks: "Die Magie des schwelenden Papiers", 
    infoP1: "In einer Ära, in der jedes geschriebene Wort für immer auf Firmenservern landet, haben wir die Magie geheimer Briefe zurückgebracht, die nach dem Lesen verbrennen. <br/><br/><strong>SmoldPaper</strong> ist ein <strong>kostenloses Open-Source-Projekt</strong> für digitale Asche. Ihre Nachrichten werden direkt auf Ihrem Gerät verschlüsselt, als kryptografisches Rauschen übertragen und sofort nach der Entschlüsselung unwiderruflich zerstört. Keine Spuren. Nur Sie und Ihr Kontakt.",
    
    infoAlgTitle: "Wie benutzt man es?",
    step1Title: "Das Versteck",
    infoAlg1: "Vereinbaren Sie eine geheime Seed-Phrase mit Ihrem Kontakt für den Zugang zum gemeinsamen Raum.",
    step2Title: "Das Signal",
    infoAlg2: "Legen Sie ein Signalwort fest. Beispiel: <em>«Beginnt eine Nachricht mit <strong>Kumpel</strong>, ist sie der Schlüssel»</em>.",
    step3Title: "Die Übergabe",
    infoAlg3: "Chatten Sie normal. Wenn Sie das Signalwort sehen, kopieren Sie die Nachricht in SmoldPaper, um das Geheimnis zu lüften.",
    step4Title: "Lesen & Verbrennen",
    infoAlg4: "Sobald Sie die Nachricht lesen, verbrennt sie sofort 🔥",
    infoAlgOutro: "🕵️‍♂️ <em>Chatten Sie offen und ohne Verdacht zu erregen. Tauschen Sie Geheimnisse in SmoldPaper aus!</em>",

    infoTechTitle: "Wie die Verschlüsselung funktioniert", infoTechText: "Die Daten werden lokal mit AES-GCM 256-bit verschlüsselt. Der Server arbeitet nach dem Zero-Knowledge-Prinzip.",
    infoRisksTitle: "Angriffsvektoren & Risiken", infoRisksText: "Achten Sie auf Shoulder Surfing, schwache Passwörter und Social Engineering.",
    infoHardwareDelTitle: "Hardware-Verbrennung", infoHardwareDelText: "Die Datenbank löscht den Datensatz nach Ablauf dauerhaft.",
    infoBruteTitle: "Schutz vor Hacks", infoBruteText: "Nach 5 falschen Versuchen zerstört sich der Container selbst.",
    infoFooter: "Es werden keine IPs, Cookies oder Analytics gesammelt.", timeExpired: "Verbrannt. Löschen...",
    timeDelIn: "Verbrennt in", days: "t", hours: "s", mins: "m", secs: "s",
    errLastRead: "WARNUNG: Dies war der letzte Aufruf. Informationen auf dem Server verbrannt.",
    errDestroyedLimit: "Nachricht VERBRANNT: Limit von 5 falschen Versuchen überschritten.",
    errWrongPhrase: "Falscher Satz. Verbleibende Versuche:", btnBack: "Zurück",
    time1m: "1 Minute", time10m: "10 Minuten", time1h: "1 Stunde", time10h: "10 Stunden", time1d: "1 Tag", time7d: "7 Tage",
    read1: "1 Mal", read2: "2 Mal", read3: "3 Mal", read5: "5 Mal", read10: "10 Mal", read20: "20 Mal",
    errNetwork: "Netzwerkfehler.", copied: "Kopiert",
    disclaimerTitle: "Haftungsausschluss", disclaimerText: "Dieser Dienst wird \"wie besehen\" bereitgestellt. Die Nutzung für illegale Aktivitäten ist strengstens untersagt.",
    donateMessage: "❤️ Wir haben beschlossen, diese App kostenlos mit allen Menschen auf der Welt zu teilen, da jeder das Recht auf Privatsphäre hat.<br/><br/>Diese App wird viele vor Verfolgung schützen und vielleicht sogar <strong>jemandem das Leben retten!</strong><br/><br/>Der Betrieb der Server und die Weiterentwicklung des Projekts kosten Geld. Bitte ziehen Sie in Betracht, uns zu unterstützen:",
    shareTitle: "Helfen Sie dem Projekt zu wachsen",
    shareText: "Die Entwicklung dieses Projekts hängt ganz von Ihnen ab! Teilen Sie den Link mit Freunden und der ganzen Welt, damit Menschen immer vertraulich kommunizieren können, auch wenn es auf den ersten Blick unmöglich erscheint!",
    btnShare: "Link zum Service kopieren",
    
    btnAbout: "Über das Projekt",
    btnShowWallets: "Wallets anzeigen",
    btnHideWallets: "Wallets verbergen",
    btnInviteRoom: "Ins Versteck einladen",
    btnHowItWorks: "Wie es funktioniert",
    errSeedShort: "Die Seed-Phrase muss mindestens 8 Zeichen lang sein.",
    errNoHttps: "Warnung: Kein HTTPS oder Crypto-API nicht verfügbar. Verschlüsselung deaktiviert.",
  },
  fr: {
    appTitle: "SmoldPaper", tagline: "Boîte Zero-Knowledge Open-Source", loginTitle: "Accès à la Cachette",
    loginDesc: "Générez ou entrez une phrase seed unique et complexe. Le serveur ne la stocke pas.",
    seedPlaceholder: "Phrase seed (8 à 255 car.)", btnEnter: "Ouvrir la cachette", loginEmbedHtml: "",
    encryptAndSend: "Chiffrer et Laisser", pubPhraseLabel: "Phrase publique (Clé du chat)",
    pubPhrasePlaceholder: "Ex: Salut mon pote, ça va?", 
    publicLabel: "Étiquette ouverte", publicLabelPh: "Ex: Pour Anna / Rapport",
    publicLabelWarn: "Le texte de ce champ n'est pas chiffré. Vous pouvez écrire le nom du destinataire ici si vous utilisez une seule cachette pour communiquer avec plusieurs personnes, ou toute autre chose qui aidera le destinataire à comprendre que ce message spécifique de la liste est à déchiffrer pour lui.",
    secretLabel: "Message Secret",
    secretPlaceholder: "Ce texte sera chiffré localement...", settingsLabel: "Paramètres de combustion",
    delAfter: "Brûler après", maxReads: "Lectures max", btnSending: "Chiffrement...", btnSent: "Laissé dans la cachette",
    waitInBox: "Se consume dans la cachette", boxEmpty: "La cachette est vide", msgAutoAppear: "Les messages apparaîtront ici.",
    cipherPrefix: "Chiffre #", errAttempts: "Erreurs", needDecrypt: "Déchiffrement requis",
    decryptPhrasePlaceholder: "Entrez la phrase publique...", btnDecrypt: "Déchiffrer", readsLeft: "Lectures restantes",
    warnDelete5: "Attention: 5 tentatives échouées brûleront le message.",
    decryptedLocal: "Déchiffré localement", copyWarn: "Copiez l'information. À la fermeture, le texte se transforme en cendres.",
    infoHowItWorks: "La magie du papier qui se consume", 
    infoP1: "À une époque où chaque mot écrit s'installe pour toujours sur les serveurs des entreprises, nous avons ramené la magie des lettres secrètes qui brûlent après lecture. <br/><br/><strong>SmoldPaper</strong> est une cendre numérique <strong>gratuite et open source</strong>. Vos messages sont chiffrés directement sur votre appareil, transmis sous forme de bruit cryptographique et irréversiblement détruits immédiatement après le déchiffrement. Aucune trace. Juste vous et votre contact.",
    
    infoAlgTitle: "Comment l'utiliser ?",
    step1Title: "La Cachette",
    infoAlg1: "Convenez d'une phrase seed secrète avec votre contact pour accéder à une cachette commune.",
    step2Title: "Le Signal",
    infoAlg2: "Choisissez un mot-clé. Exemple : <em>« Si un message commence par <strong>Pote</strong>, c'est la clé cachée »</em>.",
    step3Title: "La Transmission",
    infoAlg3: "Discutez normalement. Si vous voyez le mot-clé, copiez le message dans SmoldPaper pour révéler le secret.",
    step4Title: "Lecture et Cendres",
    infoAlg4: "Dès que vous lisez le message secret, il brûle instantanément 🔥",
    infoAlgOutro: "🕵️‍♂️ <em>Discutez à la vue de tous sans éveiller les soupçons. Échangez des secrets dans SmoldPaper !</em>",

    infoTechTitle: "Comment fonctionne le chiffrement", infoTechText: "Les données sont chiffrées localement avec AES-GCM 256-bit. Le serveur fonctionne selon le principe Zero-Knowledge.",
    infoRisksTitle: "Vecteurs d'attaque et risques", infoRisksText: "Méfiez-vous du Shoulder Surfing, des mots de passe faibles et de l'ingénierie sociale.",
    infoHardwareDelTitle: "Combustion matérielle", infoHardwareDelText: "La base de données supprime définitivement l'enregistrement.",
    infoBruteTitle: "Protection", infoBruteText: "Après 5 tentatives échouées, le conteneur s'autodétruit.",
    infoFooter: "Aucune IP, ni cookie n'est collecté.", timeExpired: "Brûlé. Suppression...",
    timeDelIn: "Brûle dans", days: "j", hours: "h", mins: "m", secs: "s",
    errLastRead: "AVERTISSEMENT: C'était la dernière lecture. Informations brûlées sur le serveur.",
    errDestroyedLimit: "Message BRÛLÉ: limite de 5 tentatives dépassée.",
    errWrongPhrase: "Phrase incorrecte. Tentatives restantes:", btnBack: "Retour",
    time1m: "1 minute", time10m: "10 minutes", time1h: "1 heure", time10h: "10 heures", time1d: "1 jour", time7d: "7 jours",
    read1: "1 fois", read2: "2 fois", read3: "3 fois", read5: "5 fois", read10: "10 fois", read20: "20 fois",
    errNetwork: "Erreur réseau.", copied: "Copié",
    disclaimerTitle: "Clause de non-responsabilité", disclaimerText: "Ce service est fourni \"tel quel\". L'utilisation pour des activités illégales est strictement interdite.",
    donateMessage: "❤️ Nous avons décidé de partager cette application gratuitement avec le monde entier, car chacun a droit à la vie privée.<br/><br/>Cette application sauvera beaucoup de gens de la persécution et pourrait même <strong>sauver une vie !</strong><br/><br/>La maintenance des serveurs et le développement du projet nécessitent des fonds. Merci d'envisager de nous soutenir :",
    shareTitle: "Aidez le projet à grandir",
    shareText: "Le développement de ce projet dépend entièrement de vous ! Partagez le lien avec vos proches et avec le monde entier afin que les gens aient toujours la possibilité de communiquer en toute confidentialité, même là où cela semble impossible à première vue !",
    btnShare: "Copier le lien du service",
    
    btnAbout: "À propos",
    btnShowWallets: "Afficher les portefeuilles",
    btnHideWallets: "Masquer les portefeuilles",
    btnInviteRoom: "Inviter dans la cachette",
    btnHowItWorks: "Comment ça marche",
    errSeedShort: "La phrase seed doit comporter au moins 8 caractères.",
    errNoHttps: "Avertissement: pas de HTTPS ou API Crypto indisponible. Chiffrement désactivé.",
  },
  es: {
    appTitle: "SmoldPaper", tagline: "Caja Zero-Knowledge Open-Source", loginTitle: "Acceso al Escondite",
    loginDesc: "Genera o introduce una frase semilla compleja y única. El servidor no la guarda.",
    seedPlaceholder: "Frase semilla (8 a 255 car.)", btnEnter: "Abrir escondite", loginEmbedHtml: "",
    encryptAndSend: "Cifrar y Dejar", pubPhraseLabel: "Frase pública (Clave del chat)",
    pubPhrasePlaceholder: "Ej: Hola amigo, ¿cómo estás?", 
    publicLabel: "Etiqueta abierta", publicLabelPh: "Ej: Para Anna / Informe",
    publicLabelWarn: "El texto en este campo no está cifrado. Puedes escribir aquí el nombre del destinatario si utilizas un único escondite para comunicarte con varias personas, o cualquier otra cosa que ayude al destinatario a entender que este mensaje específico de la lista es para que lo descifre él.",
    secretLabel: "Mensaje Secreto",
    secretPlaceholder: "Este texto se cifrará localmente...", settingsLabel: "Parámetros de combustión",
    delAfter: "Quemar después", maxReads: "Lecturas máx", btnSending: "Cifrando...", btnSent: "Dejado en el escondite",
    waitInBox: "Ardiendo en el escondite", boxEmpty: "El escondite está vacío", msgAutoAppear: "Los mensajes aparecerán aquí.",
    cipherPrefix: "Cifrado #", errAttempts: "Errores", needDecrypt: "Se requiere descifrado",
    decryptPhrasePlaceholder: "Introduce la frase pública...", btnDecrypt: "Descifrar", readsLeft: "Lectures restantes",
    warnDelete5: "Atención: 5 intentos fallidos quemarán el mensaje.",
    decryptedLocal: "Descifrado localmente", copyWarn: "Copia la información. Al cerrar, el texto se convierte en cenizas.",
    infoHowItWorks: "La magia del papel ardiente", 
    infoP1: "En una era donde cada palabra escrita se asienta para siempre en servidores corporativos, trajimos de vuelta la magia de las cartas secretas que se queman después de leerse. <br/><br/><strong>SmoldPaper</strong> es ceniza digital <strong>gratuita y de código abierto (Open Source)</strong>. Tus mensajes se cifran directamente en tu dispositivo, se transmiten como ruido criptográfico y se destruyen irremediablemente inmediatamente después de descifrarlos. Sin rastros. Solo tú y tu contacto.",
    
    infoAlgTitle: "¿Cómo se usa?",
    step1Title: "El Escondite",
    infoAlg1: "Acuerda una frase semilla secreta con tu contacto para acceder a un escondite común.",
    step2Title: "La Señal",
    infoAlg2: "Elige una palabra clave. Ejemplo: <em>«Si un mensaje empieza por <strong>Amigo</strong>, es la llave oculta»</em>.",
    step3Title: "La Transmisión",
    infoAlg3: "Chatea normalmente. Cuando veas la palabra clave, copia el mensaje en SmoldPaper para revelar el secreto.",
    step4Title: "Leer y Quemar",
    infoAlg4: "En cuanto leas el mensaje secreto, se quemará al instante 🔥",
    infoAlgOutro: "🕵️‍♂️ <em>Chatea a la vista de todos sin levantar sospechas. ¡Intercambien secretos en SmoldPaper!</em>",

    infoTechTitle: "Cómo funciona el cifrado", infoTechText: "Los datos se cifran localmente con AES-GCM 256-bit. El servidor funciona según el principio Zero-Knowledge.",
    infoRisksTitle: "Vectores de ataque y riesgos", infoRisksText: "Tenga cuidado con el Shoulder Surfing, contraseñas débiles y la ingeniería social.",
    infoHardwareDelTitle: "Combustión física", infoHardwareDelText: "La base de datos elimina el registro permanentemente.",
    infoBruteTitle: "Protección", infoBruteText: "Tras 5 intentos fallidos, el contenedor se autodestruye.",
    infoFooter: "No se recopilan IPs ni cookies.", timeExpired: "Quemado. Eliminando...",
    timeDelIn: "Se quema en", days: "d", hours: "h", mins: "m", secs: "s",
    errLastRead: "AVISO: Esta fue la última lectura. Información quemada en el servidor.",
    errDestroyedLimit: "Mensaje QUEMADO: límite de 5 intentos superado.",
    errWrongPhrase: "Frase incorrecta. Intentos restantes:", btnBack: "Atrás",
    time1m: "1 minuto", time10m: "10 minutos", time1h: "1 hora", time10h: "10 horas", time1d: "1 día", time7d: "7 días",
    read1: "1 vez", read2: "2 veces", read3: "3 veces", read5: "5 veces", read10: "10 veces", read20: "20 veces",
    errNetwork: "Error de red.", copied: "Copiado",
    disclaimerTitle: "Descargo de responsabilidad", disclaimerText: "Este servicio se proporciona \"tal cual\". El uso para actividades ilegales está estrictamente prohibido.",
    donateMessage: "❤️ Hemos decidido compartir esta aplicación de forma gratuita con todo el mundo porque cada persona tiene derecho a la privacidad.<br/><br/>¡Esta aplicación protegerá a muchos de la persecución e incluso podría <strong>salvar la vida de alguien!</strong><br/><br/>Mantener los servidores y desarrollar el proyecto requiere recursos. Por favor, considera apoyarnos:",
    shareTitle: "Ayuda a que el proyecto crezca",
    shareText: "¡El desarrollo de este proyecto depende completamente de ti! ¡Comparte el enlace con tus seres queridos y con todo el mundo para que las personas siempre tengan la capacidad de comunicarse de manera confidencial, incluso donde a primera vista parece imposible!",
    btnShare: "Copiar enlace al servicio",
    
    btnAbout: "Acerca de",
    btnShowWallets: "Mostrar billeteras",
    btnHideWallets: "Ocultar billeteras",
    btnInviteRoom: "Invitar al escondite",
    btnHowItWorks: "Cómo funciona",
    errSeedShort: "La frase semilla debe tener al menos 8 caracteres.",
    errNoHttps: "Advertencia: no hay HTTPS o la API Crypto no está disponible. Cifrado deshabilitado.",
  }
};

const DICT: any = typeof window !== 'undefined' && (window as any).SMOLDPAPER_DICT ? (window as any).SMOLDPAPER_DICT : FALLBACK_DICT;

const defaultFooter = `End-to-End Encrypted. We don't store keys, read messages, and we delete them permanently.
<div style="margin-top: 12px; display: flex; justify-content: center;">
  <a href="https://github.com/0xSmold/smoldpaper" target="_blank" rel="noopener noreferrer" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 14px; background: rgba(150, 150, 150, 0.1); border-radius: 10px; text-decoration: none; color: inherit; font-weight: bold; font-size: 13px; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">
    <svg height="18" viewBox="0 0 16 16" width="18" style="fill: currentColor;">
      <path d="M8 0c4.42 0 8 3.58 8 8a8.013 8.013 0 0 1-5.45 7.59c-.4.08-.55-.17-.55-.38 0-.27.01-1.13.01-2.2 0-.75-.25-1.23-.54-1.48 1.78-.2 3.65-.88 3.65-3.95 0-.88-.31-1.59-.82-2.15.08-.2.36-1.02-.08-2.12 0 0-.67-.22-2.2.82-.64-.18-1.32-.27-2-.27-.68 0-1.36.09-2 .27-1.53-1.03-2.2-.82-2.2-.82-.44 1.1-.16 1.92-.08 2.12-.51.56-.82 1.28-.82 2.15 0 3.06 1.86 3.75 3.64 3.95-.23.2-.44.55-.51 1.07-.46.21-1.61.55-2.33-.66-.15-.24-.6-.83-1.23-.82-.67.01-.27.38.01.53.34.19.73.9.82 1.13.16.45.68 1.31 2.69.94 0 .67.01 1.3.01 1.49 0 .21-.15.45-.55.38A7.995 7.995 0 0 1 0 8c0-4.42 3.58-8 8-8Z"></path>
    </svg>
    GitHub Open Source
  </a>
</div>`;

// ==========================================
// SANDBOX EMULATION BLOCK (FOR PREVIEW)
// ==========================================
let mockDB: any = { 
  messages: [], 
  settings: { 
    footer_html: defaultFooter, 
    custom_dict_ru: '{}', custom_dict_en: '{}', custom_dict_de: '{}', custom_dict_fr: '{}', custom_dict_es: '{}', 
    admin_password: 'admin123' 
  } 
};

const apiCall = async (payload: any) => {
  const runMock = () => new Promise((resolve) => {
    setTimeout(() => {
      const { action, room_hash, id } = payload;
      if (action === 'get_settings') resolve({ success: true, data: mockDB.settings });
      else if (action === 'admin_login') resolve({ success: payload.password === mockDB.settings.admin_password });
      else if (action === 'save_settings') {
         if (payload.admin_password !== mockDB.settings.admin_password) {
           resolve({success: false});
         } else {
           mockDB.settings[payload.key] = payload.value;
           resolve({success: true});
         }
      } else if (action === 'get_messages') {
        const msgs = mockDB.messages.filter((m: any) => m.room_hash === room_hash).map((m: any) => ({ ...m, payload: undefined }));
        resolve({ success: true, messages: msgs.sort((a: any, b: any) => b.created_at - a.created_at) });
      } else if (action === 'post_message') {
        const newMsg = {
          id: Math.random().toString(36).substring(2, 10), room_hash, payload: payload.payload, public_label: payload.public_label,
          created_at: Date.now(), expires_at: new Date(Date.now() + (payload.expires_minutes * 60000)).toISOString().replace('T', ' ').substring(0, 19),
          max_reads: payload.max_reads, current_reads: 0, failed_attempts: 0
        };
        mockDB.messages.push(newMsg); resolve({ success: true, id: newMsg.id });
      } else if (action === 'get_payload') {
        const msg = mockDB.messages.find((m: any) => m.id === id && m.room_hash === room_hash);
        if (msg) resolve({ success: true, payload: msg.payload }); else resolve({ success: false, message: 'Not found' });
      } else if (action === 'mark_read') {
        const msgIndex = mockDB.messages.findIndex((m: any) => m.id === id);
        if (msgIndex !== -1) {
          mockDB.messages[msgIndex].current_reads++;
          if (mockDB.messages[msgIndex].current_reads >= mockDB.messages[msgIndex].max_reads) {
            mockDB.messages.splice(msgIndex, 1); resolve({ success: true, deleted: true });
          } else resolve({ success: true, deleted: false });
        } else resolve({ success: false });
      } else if (action === 'register_fail') {
        const msgIndex = mockDB.messages.findIndex((m: any) => m.id === id);
        if (msgIndex !== -1) {
          mockDB.messages[msgIndex].failed_attempts++;
          if (mockDB.messages[msgIndex].failed_attempts >= 5) {
            mockDB.messages.splice(msgIndex, 1); resolve({ success: true, deleted: true });
          } else resolve({ success: true, deleted: false, attempts_left: 5 - mockDB.messages[msgIndex].failed_attempts });
        } else resolve({ success: false });
      }
    }, 300);
  });

  if (isPreviewEnv) {
    return runMock();
  }

  try {
    const res = await fetch(API_URL, { 
      method: 'POST', 
      headers: { 'Content-Type': 'application/json' }, 
      body: JSON.stringify(payload) 
    });
    if (!res.ok) throw new Error('Network error');
    return await res.json();
  } catch (e: any) { 
    console.warn("API Error, falling back to Sandbox Mock DB:", e); 
    return runMock(); 
  }
};

// ==========================================
// CRYPTOGRAPHIC CORE (Web Crypto API)
// ==========================================
const checkCryptoAPI = () => {
    return !!(window.crypto && window.crypto.subtle);
};

const generateKey = async (password: string, salt: Uint8Array) => {
  const enc = new TextEncoder();
  const keyMaterial = await window.crypto.subtle.importKey("raw", enc.encode(password), { name: "PBKDF2" }, false, ["deriveBits", "deriveKey"]);
  return window.crypto.subtle.deriveKey(
    { name: "PBKDF2", salt: salt, iterations: 10000, hash: "SHA-256" },
    keyMaterial, { name: "AES-GCM", length: 256 }, true, ["encrypt", "decrypt"]
  );
};
const encryptMessage = async (text: string, password: string) => {
  if (!checkCryptoAPI()) throw new Error('NO_HTTPS');
  const salt = window.crypto.getRandomValues(new Uint8Array(16));
  const iv = window.crypto.getRandomValues(new Uint8Array(12));
  const key = await generateKey(password, salt);
  const encodedText = new TextEncoder().encode(text);
  const ciphertext = await window.crypto.subtle.encrypt({ name: "AES-GCM", iv: iv }, key, encodedText);
  const combined = new Uint8Array(salt.length + iv.length + ciphertext.byteLength);
  combined.set(salt, 0); combined.set(iv, salt.length); combined.set(new Uint8Array(ciphertext), salt.length + iv.length);
  return btoa(String.fromCharCode.apply(null, Array.from(combined)));
};
const decryptMessage = async (base64Payload: string, password: string) => {
  if (!checkCryptoAPI()) throw new Error('NO_HTTPS');
  try {
    const rawData = atob(base64Payload);
    const combined = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; i++) combined[i] = rawData.charCodeAt(i);
    const salt = combined.slice(0, 16); const iv = combined.slice(16, 28); const ciphertext = combined.slice(28);
    const key = await generateKey(password, salt);
    const decrypted = await window.crypto.subtle.decrypt({ name: "AES-GCM", iv: iv }, key, ciphertext);
    return new TextDecoder().decode(decrypted);
  } catch (e: any) { throw new Error('ERR_DECRYPT'); }
};
const hashString = async (str: string) => {
  if (!checkCryptoAPI()) return 'INSECURE_CONTEXT';
  const buf = await window.crypto.subtle.digest("SHA-256", new TextEncoder().encode(str));
  return Array.from(new Uint8Array(buf)).map(b => b.toString(16).padStart(2, '0')).join('');
};

// ==========================================
// AUDIO MODULE
// ==========================================
const playTone = (type: string) => {
  try {
    const AudioContext = window.AudioContext || (window as any).webkitAudioContext;
    if (!AudioContext) return;
    const ctx = new AudioContext();
    const osc = ctx.createOscillator(); const gain = ctx.createGain();
    
    if (type === 'whisper') {
      osc.type = 'sine'; osc.frequency.setValueAtTime(300, ctx.currentTime);
      gain.gain.setValueAtTime(0.02, ctx.currentTime); gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.2);
    } else if (type === 'soft') { 
      osc.type = 'sine'; osc.frequency.setValueAtTime(440, ctx.currentTime); 
      gain.gain.setValueAtTime(0.05, ctx.currentTime); gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5); 
    } else if (type === 'loud') { 
      osc.type = 'square'; osc.frequency.setValueAtTime(880, ctx.currentTime); 
      gain.gain.setValueAtTime(0.1, ctx.currentTime); gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.8); 
    } else { return; }
    
    osc.connect(gain); gain.connect(ctx.destination); osc.start(); osc.stop(ctx.currentTime + 1);
  } catch (e: any) { console.error("Audio error:", e); }
};

// ==========================================
// SUPPORT AND VIRALITY COMPONENT
// ==========================================
function SupportBlock({ t, themeClasses, showToast }: any) {
  const [copiedShare, setCopiedShare] = useState(false);
  const [showWallets, setShowWallets] = useState(false);

  const wallets = [
    { name: 'Bitcoin (BTC)', address: 'bc1qxdnfjakd89qrz59cr702pt70n0wtapkcrmtnyk' },
    { name: 'USDT (TRC-20) / TRX', address: 'TYqAdNNvvwzNT7LUkGCh8sZLjNQNza3NDd' },
    { name: 'Monero (XMR)', address: '87ZQda7hirZWdmrTCBSF8GVewZ4eh8mKeRdADLDTvmZbSPe8W7zukVZKf2UEWCxBveXh8zGGyDVJBdVugY1T8LA9PvXD3CF' },
    { name: 'ETH / BNB (BSC)', address: '0xeAe930F5B6863Aec4a98b25e346beE20723A7F96' },
    { name: 'Litecoin (LTC)', address: 'ltc1qr62jmk9h5wnc0ptvvqcjmw8zxfuthhlqu6h30r' },
    { name: 'TON', address: 'UQApKqtdQ2vlab-CvRGJve_jlNVqkf_g-mngpKMS34Ga85RT' },
    { name: 'Dogecoin (DOGE)', address: 'D9j8yZyiNztiLZMZ8SSQmqgd53VfEcyJFp' }
  ];

  const copyToClipboard = (text: string, isShareBtn: boolean = false) => {
    const ta = document.createElement("textarea"); ta.value = text; document.body.appendChild(ta); ta.select();
    try { 
      document.execCommand('copy'); 
      if (isShareBtn) {
        setCopiedShare(true);
        setTimeout(() => setCopiedShare(false), 3000);
      } else {
        showToast(t('copied'), 'success');
      }
    } catch(e: any) {
      showToast("Copy error (try selecting text manually)", 'error');
    } 
    document.body.removeChild(ta);
  };

  const handleShare = () => {
    if (navigator.share && /mobile|android|iphone/i.test(navigator.userAgent)) {
      navigator.share({ title: t('appTitle'), text: t('shareText'), url: window.location.href.split('#')[0] }).catch((e: any) => console.log('Share canceled'));
    } else {
      copyToClipboard(window.location.href.split('#')[0], true);
    }
  };

  return (
    <div className={`rounded-2xl border overflow-hidden w-full ${themeClasses.bgCard}`}>
      <div className={`p-5 sm:p-6 border-b border-[#EAE0D0]/70 dark:border-[#3E3832]`}>
        <p className="text-sm mb-4 leading-relaxed font-medium text-left" dangerouslySetInnerHTML={{ __html: t('donateMessage') }}></p>
        
        <button 
          onClick={() => setShowWallets(!showWallets)} 
          className={`w-full px-6 py-3 rounded-xl font-bold flex items-center justify-center transition-all active:scale-95 ${themeClasses.hoverBtn}`}
        >
          <Wallet size={18} className={`mr-2 ${themeClasses.accentText}`} />
          {showWallets ? t('btnHideWallets') : t('btnShowWallets')}
          {showWallets ? <ChevronUp size={18} className="ml-2 opacity-50"/> : <ChevronDown size={18} className="ml-2 opacity-50"/>}
        </button>

        {showWallets && (
          <div className="space-y-2 mt-4 animate-in fade-in slide-in-from-top-2">
            {wallets.map(w => (
              <div key={w.name} className={`flex items-center justify-between p-2.5 sm:p-3 rounded-xl border bg-[#FDF8EC] dark:bg-[#110F0E] border-[#EAE0D0] dark:border-[#3E3832]`}>
                <div className="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 overflow-hidden pr-2">
                  <span className={`font-bold text-[11px] sm:text-xs w-36 shrink-0 ${themeClasses.accentText}`}>{w.name}</span>
                  <span className="font-mono text-xs sm:text-sm truncate opacity-70 cursor-text select-all">{w.address}</span>
                </div>
                <button onClick={() => copyToClipboard(w.address)} className={`p-2 shrink-0 rounded-lg transition-colors ${themeClasses.hoverBtn} ${themeClasses.accentText}`} title="Copy">
                  <Copy size={16} />
                </button>
              </div>
            ))}
          </div>
        )}
      </div>

      <div className={`p-5 sm:p-6 bg-[#F3EBE0] dark:bg-[#110F0E]`}>
        <h3 className="font-bold flex items-center mb-3 text-lg"><Share2 size={18} className={`mr-2 ${themeClasses.accentText}`}/> {t('shareTitle')}</h3>
        <p className={`text-sm mb-4 leading-relaxed text-left ${themeClasses.textMuted}`}>{t('shareText')}</p>
        <button onClick={handleShare} className={`w-full px-6 py-3.5 rounded-xl font-bold flex items-center justify-center transition-all active:scale-95 ${themeClasses.hoverBtn}`}>
          {copiedShare ? <Check size={18} className={`mr-2 text-emerald-600 dark:text-emerald-500`}/> : <Copy size={18} className={`mr-2 ${themeClasses.accentText}`}/>}
          {copiedShare ? t('copied') : t('btnShare')}
        </button>
      </div>
    </div>
  );
}

// ==========================================
// INFOGRAPHIC COMPONENT
// ==========================================
function HowItWorksInfographic({ t }: any) {
  const steps = [
    { icon: <Shield size={24} />, title: t('step1Title'), desc: t('infoAlg1'), color: "text-blue-500", bg: "bg-blue-50 dark:bg-blue-500/10", border: "border-blue-100 dark:border-blue-500/30" },
    { icon: <MessageSquare size={24} />, title: t('step2Title'), desc: t('infoAlg2'), color: "text-purple-500", bg: "bg-purple-50 dark:bg-purple-500/10", border: "border-purple-100 dark:border-purple-500/30" },
    { icon: <Send size={24} />, title: t('step3Title'), desc: t('infoAlg3'), color: "text-emerald-500", bg: "bg-emerald-50 dark:bg-emerald-500/10", border: "border-emerald-100 dark:border-emerald-500/30" },
    { icon: <Flame size={24} />, title: t('step4Title'), desc: t('infoAlg4'), color: "text-red-500", bg: "bg-red-50 dark:bg-red-500/10", border: "border-red-100 dark:border-red-500/30" },
  ];

  return (
    <div className="w-full">
       <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
         {steps.map((step, i) => (
            <div key={i} className={`p-5 rounded-2xl border bg-[#FDF8EC] dark:bg-[#1A1816] ${step.border} relative overflow-hidden shadow-sm transition-transform hover:-translate-y-1`}>
               <div className={`w-12 h-12 rounded-xl ${step.bg} ${step.color} flex items-center justify-center mb-4 relative z-10`}>
                  {step.icon}
               </div>
               <div className={`absolute -top-4 -right-2 font-black text-8xl opacity-5 ${step.color}`}>{i+1}</div>
               <h4 className="font-bold text-sm mb-2 relative z-10">{step.title}</h4>
               <p className="text-xs opacity-80 leading-relaxed relative z-10" dangerouslySetInnerHTML={{__html: step.desc}} />
            </div>
         ))}
       </div>
    </div>
  );
}

// ==========================================
// MAIN APP COMPONENT (Router)
// ==========================================
export default function App() {
  const [view, setView] = useState('login'); 
  const [isDarkMode, setIsDarkMode] = useState(() => {
    if (typeof window !== 'undefined') {
      const savedTheme = localStorage.getItem('smoldpaper_theme');
      if (savedTheme) return savedTheme === 'dark';
      return window.matchMedia('(prefers-color-scheme: dark)').matches;
    }
    return false;
  });
  
  // TOAST NOTIFICATIONS
  const [toast, setToast] = useState<any>(null);
  const showToast = (message: string, type: string = 'success') => {
    setToast({ message, type });
    setTimeout(() => setToast(null), 3000);
  };

  const [isLoginInfoOpen, setIsLoginInfoOpen] = useState(true); 
  const [serverDict, setServerDict] = useState<any>({ ru: {}, en: {}, de: {}, fr: {}, es: {} });
  const [footerHtml, setFooterHtml] = useState(defaultFooter);

  const [roomSeed, setRoomSeed] = useState('');
  const [roomHash, setRoomHash] = useState('');
  const [showSeed, setShowSeed] = useState(false);
  const [copiedSeed, setCopiedSeed] = useState(false);
  const [adminPass, setAdminPass] = useState('');
  const [isEntering, setIsEntering] = useState(false);
  
  const [settings, setSettings] = useState<any>(() => {
    if (typeof window === 'undefined') return { expireMinutes: 600, maxReads: 1, soundType: 'whisper' };
    const saved = localStorage.getItem('smoldpaper_settings');
    return saved ? JSON.parse(saved) : { expireMinutes: 600, maxReads: 1, soundType: 'whisper' };
  });

  const [lang, setLang] = useState(() => {
    if (typeof window === 'undefined') return 'en';
    const saved = localStorage.getItem('smoldpaper_lang');
    if (saved && FALLBACK_DICT[saved]) return saved;
    const browserLang = (navigator.language || '').split('-')[0];
    return FALLBACK_DICT[browserLang] ? browserLang : 'en';
  });

  const t = (key: string) => {
    if (serverDict[lang] && serverDict[lang][key]) return serverDict[lang][key];
    return FALLBACK_DICT[lang]?.[key] || FALLBACK_DICT['en'][key] || key;
  };

  useEffect(() => {
    if (!checkCryptoAPI() && !isPreviewEnv) {
        showToast(t('errNoHttps'), 'error');
    }
    const initUrlLogin = async () => {
      if (typeof window === 'undefined') return;
      
      let code = '';
      if (window.location.hash.length > 1) {
        code = decodeURIComponent(window.location.hash.substring(1));
      }
      
      if (code) {
        if (code.trim().length < 8) {
          showToast(t('errSeedShort') || "Seed phrase must be at least 8 characters.", 'error');
          window.history.replaceState({}, document.title, window.location.pathname);
          return;
        }

        if (!checkCryptoAPI() && !isPreviewEnv) return;

        setRoomSeed(code);
        const resAdmin: any = await apiCall({ action: 'admin_login', password: code.trim() });
        if (resAdmin.success) {
          setAdminPass(code.trim());
          setView('admin');
        } else {
          const hash = await hashString(code.trim());
          if (hash === 'INSECURE_CONTEXT') return;
          setRoomHash(hash); 
          setView('room');
        }
        window.history.replaceState({}, document.title, window.location.pathname);
      }
    };
    initUrlLogin();
  }, [lang]); 

  useEffect(() => {
    const initSettings = async () => {
      try {
        const res: any = await apiCall({ action: 'get_settings' });
        if (res.success && res.data) {
          if (res.data.footer_html) setFooterHtml(res.data.footer_html);
          const newDict: any = { ru: {}, en: {}, de: {}, fr: {}, es: {} };
          ['ru', 'en', 'de', 'fr', 'es'].forEach(l => { 
            if (res.data[`custom_dict_${l}`]) newDict[l] = JSON.parse(res.data[`custom_dict_${l}`]); 
          });
          setServerDict(newDict);
        }
      } catch (e: any) {}
    };
    initSettings();
  }, []);

  useEffect(() => { 
    if (typeof window !== 'undefined') {
      localStorage.setItem('smoldpaper_theme', isDarkMode ? 'dark' : 'light'); 
      document.documentElement.classList.toggle('dark', isDarkMode); 
    }
  }, [isDarkMode]);
  
  useEffect(() => { 
    if (typeof window !== 'undefined') {
      localStorage.setItem('smoldpaper_lang', lang); 
    }
  }, [lang]);
  
  useEffect(() => { 
    if (typeof window !== 'undefined') {
      localStorage.setItem('smoldpaper_settings', JSON.stringify(settings)); 
    }
  }, [settings]);

  const updateSetting = (key: string, val: any) => setSettings((p: any) => ({ ...p, [key]: val }));

  const cycleSound = () => {
    const order = ['whisper', 'soft', 'loud', 'off'];
    const nextIdx = (order.indexOf(settings.soundType) + 1) % order.length;
    updateSetting('soundType', order[nextIdx]);
    if (order[nextIdx] !== 'off') playTone(order[nextIdx]);
  };

  const getSoundIcon = () => {
    switch(settings.soundType) {
      case 'whisper': return <Volume1 size={20} className={themeClasses.accentText} />;
      case 'soft': return <Volume2 size={20} className={themeClasses.accentText} />;
      case 'loud': return <Volume2 size={20} className="text-red-500" />;
      default: return <VolumeX size={20} className={themeClasses.textMuted} />;
    }
  };

  const handleEnterRoom = async (e: any) => {
    e.preventDefault(); if (!roomSeed.trim() || roomSeed.trim().length < 8) return;
    
    if (!checkCryptoAPI() && !isPreviewEnv) {
        showToast(t('errNoHttps'), 'error');
        return;
    }

    setIsEntering(true);
    try {
      const resAdmin: any = await apiCall({ action: 'admin_login', password: roomSeed.trim() });
      if (resAdmin.success) {
        setAdminPass(roomSeed.trim());
        setView('admin');
        return;
      }

      const hash = await hashString(roomSeed.trim());
      if (hash === 'INSECURE_CONTEXT') return;
      setRoomHash(hash); setView('room');
    } finally {
      setIsEntering(false);
    }
  };

  const logout = () => { setRoomHash(''); setRoomSeed(''); setAdminPass(''); setView('login'); };

  const generateStrongSeed = () => {
    const words = ["alpha", "bravo", "delta", "echo", "fox", "golf", "hotel", "kilo", "lima", "mike", "oscar", "papa", "tango", "victor", "whiskey", "xray", "yankee", "zulu", "cyber", "neon", "matrix", "nexus", "quantum", "cipher", "pulse", "vertex"];
    let seed = "";
    for(let i=0; i<4; i++) seed += words[Math.floor(Math.random() * words.length)] + "-";
    seed += Math.floor(1000 + Math.random() * 9000);
    setRoomSeed(seed); setShowSeed(true);
  };

  const handleCopySeed = () => {
    if (!roomSeed) return;
    const ta = document.createElement("textarea"); ta.value = roomSeed; document.body.appendChild(ta); ta.select();
    try { document.execCommand('copy'); setCopiedSeed(true); setTimeout(() => setCopiedSeed(false), 2000); } catch(e: any){}
    document.body.removeChild(ta);
  };

  // ==========================================
  // GLOBAL STYLE CLASSES (Moleskine / Paper Style)
  // ==========================================
  const themeClasses = {
    bgApp: 'bg-[#E5E7EB] dark:bg-[#0F0E0D] text-[#2c241b] dark:text-[#D6C8B3]',
    bgCard: 'bg-[#FDF8EC] dark:bg-[#1A1816] border-[#EAE0D0] dark:border-[#3E3832] shadow-[0_10px_40px_rgba(0,0,0,0.06)] dark:shadow-[0_8px_30px_rgba(0,0,0,0.6)]',
    inputBg: 'bg-[#F3EBE0] dark:bg-[#110F0E] border-[#EAE0D0] dark:border-[#3E3832] focus:bg-[#FDF8EC] focus:border-red-400 dark:focus:border-red-800 focus:ring-4 focus:ring-red-500/10 dark:focus:ring-red-900/30 text-slate-900 dark:text-[#EBE1D1] transition-all outline-none',
    textMuted: 'text-[#8c8273] dark:text-[#9E9383]',
    accentText: 'text-red-600 dark:text-red-500',
    btnPrimary: 'bg-gradient-to-br from-red-600 to-rose-800 hover:from-red-500 hover:to-rose-700 dark:from-red-800 dark:to-rose-950 dark:hover:from-red-700 dark:hover:to-rose-900 text-white dark:text-[#EBE1D1] shadow-lg shadow-red-700/20 dark:shadow-[0_0_15px_rgba(225,29,72,0.2)] border border-red-500/50 dark:border-red-900/50 transition-all outline-none focus-visible:ring-2 focus-visible:ring-red-500/50',
    headerBg: 'bg-[#FDF8EC]/90 dark:bg-[#0F0E0D]/90 border-[#EAE0D0] dark:border-[#3E3832] shadow-sm',
    hoverBtn: 'bg-[#F3EBE0] dark:bg-[#1A1816] hover:bg-[#EAE0D0] dark:hover:bg-[#2B2622] text-[#5c5448] dark:text-[#D6C8B3] dark:hover:text-[#EBE1D1] border border-transparent dark:border-[#3E3832] font-medium transition-colors outline-none focus-visible:ring-2 focus-visible:ring-red-500/50'
  };

  return (
    <div className={`min-h-screen font-sans flex flex-col transition-colors duration-300 ${themeClasses.bgApp}`}>

      {toast && (
        <div className="fixed top-6 left-1/2 -translate-x-1/2 z-[100] animate-in slide-in-from-top-4 fade-in duration-300">
          <div className={`flex items-center gap-2 px-5 py-3 rounded-2xl shadow-2xl border font-bold text-sm backdrop-blur-xl ${
            toast.type === 'error' 
              ? 'bg-red-500/10 border-red-500/20 text-red-600 dark:text-red-400' 
              : 'bg-emerald-500/10 border-emerald-500/20 text-emerald-600 dark:text-emerald-400'
          }`}>
            {toast.type === 'error' ? <AlertTriangle size={18} /> : <Check size={18} />}
            {toast.message}
          </div>
        </div>
      )}

      <header className={`p-3 sm:p-6 shrink-0 border-b flex justify-between items-center z-10 ${themeClasses.headerBg} backdrop-blur-md`}>
        <div className="flex items-center gap-2 sm:gap-3">
          <div className="w-8 h-8 sm:w-10 sm:h-10 rounded-xl bg-gradient-to-br from-rose-600 to-red-800 flex items-center justify-center text-[#FCFBF8] shadow-lg"><Flame size={20} strokeWidth={2.5} /></div>
          <div>
            <h1 className="text-lg sm:text-xl font-black tracking-tight uppercase bg-clip-text text-transparent bg-gradient-to-r from-rose-600 to-red-800 leading-tight">{t('appTitle')}</h1>
            <span className={`hidden sm:block text-[10px] font-bold uppercase tracking-widest opacity-60`}>{t('tagline')}</span>
          </div>
        </div>
        
        <div className="flex items-center gap-1.5 sm:gap-2 pr-1 sm:pr-4">
          {view !== 'admin' && (
            <div className="relative group flex items-center">
              <Globe size={16} className={`mr-1 hidden sm:block ${themeClasses.textMuted}`} />
              <select value={lang} onChange={e => setLang(e.target.value)} className={`appearance-none bg-transparent outline-none text-xs sm:text-sm font-bold uppercase cursor-pointer py-1 px-2 rounded-lg ${themeClasses.textMuted} ${themeClasses.hoverBtn}`}>
                 <option value="en">EN</option><option value="ru">RU</option><option value="de">DE</option><option value="fr">FR</option><option value="es">ES</option>
              </select>
            </div>
          )}
          {view === 'room' && (
            <button onClick={cycleSound} className={`p-2 rounded-xl transition-colors ${themeClasses.hoverBtn}`} title="Sound">
              {getSoundIcon()}
            </button>
          )}
          
          {view !== 'admin' && (
            <button onClick={() => setView(view === 'info' ? (roomHash ? 'room' : 'login') : 'info')} className={`p-2 sm:px-4 rounded-xl transition-colors font-bold text-sm flex items-center ${themeClasses.hoverBtn}`}>
              <Info size={18} className="sm:mr-1.5 shrink-0" />
              <span className="hidden sm:inline">{t('btnAbout')}</span>
            </button>
          )}

          <button onClick={() => setIsDarkMode(!isDarkMode)} className={`p-2 rounded-xl transition-colors ${themeClasses.hoverBtn}`}>
            {isDarkMode ? <Sun size={18} className="text-amber-400" /> : <Moon size={18} className="text-indigo-500" />}
          </button>
          
          {(view === 'room' || view === 'admin') && (
            <button onClick={logout} className="p-2 rounded-xl text-red-500 hover:bg-red-500/10 hover:text-red-600 border border-transparent transition-colors ml-1 outline-none focus-visible:ring-2 focus-visible:ring-red-500/50">
              <LogOut size={18} />
            </button>
          )}
        </div>
      </header>

      <main className="flex-1 overflow-y-auto p-3 sm:p-6 flex flex-col items-center w-full">
        {view === 'login' && (
          <div className="flex flex-col items-center justify-center w-full max-w-5xl animate-in fade-in zoom-in-95 my-4 sm:my-8 gap-8 sm:gap-12">
            
            {/* COLLAPSIBLE INSTRUCTION BLOCK */}
            <div className="w-full flex flex-col items-center">
              <div 
                className={`text-center mb-4 sm:mb-6 cursor-pointer select-none group flex flex-col items-center`}
                onClick={() => setIsLoginInfoOpen(!isLoginInfoOpen)}
              >
                <div className="flex items-center justify-center gap-2 mb-2 sm:mb-3">
                  <h2 className={`text-2xl sm:text-3xl font-black ${themeClasses.accentText}`}>{t('infoAlgTitle')}</h2>
                  <div className={`p-1 rounded-full ${themeClasses.hoverBtn} opacity-50 group-hover:opacity-100 transition-opacity`}>
                    {isLoginInfoOpen ? <ChevronUp size={24} /> : <ChevronDown size={24} />}
                  </div>
                </div>
                {isLoginInfoOpen && t('infoAlgOutro') && (
                  <p className={`font-medium opacity-80 max-w-2xl mx-auto px-4 animate-in fade-in ${themeClasses.textMuted}`} dangerouslySetInnerHTML={{__html: t('infoAlgOutro')}} />
                )}
              </div>
              
              {isLoginInfoOpen && (
                <div className="w-full animate-in slide-in-from-top-2 duration-300">
                  <HowItWorksInfographic t={t} />
                </div>
              )}
            </div>

            <div className="w-full max-w-lg flex flex-col gap-6 px-2 sm:px-0">
              <div className={`p-6 sm:p-8 rounded-2xl border w-full ${themeClasses.bgCard}`}>
                <div className={`w-16 h-16 rounded-xl flex items-center justify-center mx-auto mb-6 bg-[#F3EBE0] dark:bg-[#1A1816] text-red-600 dark:text-red-500`}><Key size={32} /></div>
                <h2 className="text-2xl font-black text-center mb-2">{t('loginTitle')}</h2>
                <p className={`text-center text-sm mb-8 px-2 ${themeClasses.textMuted}`}>{t('loginDesc')}</p>
                
                {t('loginEmbedHtml') && t('loginEmbedHtml') !== 'loginEmbedHtml' && (
                  <div className="w-full mb-8 rounded-xl overflow-hidden flex flex-col justify-center items-center [&_iframe]:w-full [&_iframe]:aspect-video [&_iframe]:rounded-xl" dangerouslySetInnerHTML={{ __html: t('loginEmbedHtml') }} />
                )}

                {!checkCryptoAPI() && !isPreviewEnv && (
                  <div className="mb-6 p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-600 dark:text-red-400 text-sm font-bold text-center animate-pulse">
                    {t('errNoHttps')}
                  </div>
                )}
                
                <form onSubmit={handleEnterRoom}>
                  <div className="relative mb-6">
                    <Lock className={`absolute left-3 sm:left-4 top-1/2 -translate-y-1/2 ${themeClasses.textMuted}`} size={20} />
                    <input 
                      type={showSeed ? "text" : "password"} 
                      value={roomSeed} 
                      onChange={e => setRoomSeed(e.target.value)} 
                      placeholder={t('seedPlaceholder')} 
                      maxLength={255}
                      className={`w-full pl-10 sm:pl-12 pr-28 py-4 rounded-xl border font-medium text-xs sm:text-sm md:text-base truncate ${themeClasses.inputBg}`} 
                      required minLength={8}
                    />
                    <div className="absolute right-1.5 top-1/2 -translate-y-1/2 flex items-center">
                      <button type="button" onClick={() => setShowSeed(!showSeed)} className={`p-1.5 sm:p-2 rounded-lg transition-colors ${themeClasses.hoverBtn} ${themeClasses.textMuted}`} title="Toggle">
                        {showSeed ? <EyeOff size={18} /> : <Eye size={18} />}
                      </button>
                      <button type="button" onClick={handleCopySeed} className={`p-1.5 sm:p-2 rounded-lg transition-colors ${themeClasses.hoverBtn} ${themeClasses.textMuted}`} title="Copy">
                        {copiedSeed ? <Check size={18} /> : <Copy size={18} />}
                      </button>
                      <button type="button" onClick={generateStrongSeed} className={`p-1.5 sm:p-2 rounded-lg transition-colors ${themeClasses.hoverBtn} ${themeClasses.accentText}`} title="Generate">
                        <Dices size={18} />
                      </button>
                    </div>
                  </div>
                  {/* ИНДИКАТОР ЗАГРУЗКИ ПРИ ВХОДЕ */}
                  <button type="submit" disabled={isEntering} className={`w-full font-bold py-4 rounded-xl transition-all active:scale-95 flex items-center justify-center disabled:opacity-50 ${themeClasses.btnPrimary}`}>
                    {isEntering ? <div className="w-6 h-6 border-2 border-[#EBE1D1]/30 border-t-[#EBE1D1] rounded-full animate-spin"></div> : <><Unlock size={20} className="mr-2"/> {t('btnEnter')}</>}
                  </button>
                </form>
              </div>
              
              <div className="w-full">
                 <SupportBlock t={t} themeClasses={themeClasses} showToast={showToast} />
              </div>
            </div>
          </div>
        )}

        {view === 'admin' && adminPass && (
           <AdminScreen adminPass={adminPass} serverDict={serverDict} setServerDict={setServerDict} footerHtml={footerHtml} setFooterHtml={setFooterHtml} themeClasses={themeClasses} t={t} showToast={showToast} />
        )}

        {view === 'info' && <InfoScreen onBack={() => setView(roomHash ? 'room' : 'login')} themeClasses={themeClasses} t={t} />}

        {view === 'room' && roomHash && (
           <RoomScreen roomHash={roomHash} roomSeed={roomSeed} settings={settings} updateSetting={updateSetting} themeClasses={themeClasses} soundType={settings.soundType} t={t} showToast={showToast} />
        )}
      </main>

      {view !== 'admin' && (
        <footer className={`shrink-0 text-center py-4 px-6 text-xs sm:text-sm font-medium ${themeClasses.textMuted}`}>
          <div dangerouslySetInnerHTML={{ __html: footerHtml }} className={`max-w-3xl mx-auto leading-relaxed [&_a]:${themeClasses.accentText} hover:[&_a]:opacity-80`} />
        </footer>
      )}
    </div>
  );
}

// ==========================================
// ADMIN PANEL (SETTINGS AND TEXTS)
// ==========================================
function AdminScreen({ adminPass, serverDict, setServerDict, footerHtml, setFooterHtml, themeClasses, t, showToast }: any) {
  const [activeTab, setActiveTab] = useState('texts');
  const [editLang, setEditLang] = useState('ru');
  const [localDict, setLocalDict] = useState<any>(JSON.parse(JSON.stringify(serverDict)));
  const [localFooter, setLocalFooter] = useState(footerHtml);
  const [newPass, setNewPass] = useState('');
  const [saveStatus, setSaveStatus] = useState('idle');
  const [secretClicks, setSecretClicks] = useState(0);

  const handleSaveTexts = async () => {
    setSaveStatus('saving');
    const res: any = await apiCall({ action: 'save_settings', key: `custom_dict_${editLang}`, value: JSON.stringify(localDict[editLang] || {}), admin_password: adminPass });
    if (res.success) { 
      setServerDict((prev: any) => ({...prev, [editLang]: localDict[editLang]})); 
      setSaveStatus('saved'); 
    } else { setSaveStatus('error'); }
    setTimeout(() => setSaveStatus('idle'), 3000);
  };

  const handleSaveFooter = async () => {
    setSaveStatus('saving');
    const res: any = await apiCall({ action: 'save_settings', key: 'footer_html', value: localFooter, admin_password: adminPass });
    if (res.success) { setFooterHtml(localFooter); setSaveStatus('saved'); } else { setSaveStatus('error'); }
    setTimeout(() => setSaveStatus('idle'), 3000);
  };

  const handleSavePass = async () => {
    const trimmedPass = newPass.trim();
    if (trimmedPass.length < 6) return showToast("Password must be at least 6 characters", 'error');
    setSaveStatus('saving');
    const res: any = await apiCall({ action: 'save_settings', key: 'admin_password', value: trimmedPass, admin_password: adminPass });
    if (res.success) { 
      setSaveStatus('saved'); 
      setNewPass(''); 
      showToast("Password updated! Please re-login.", 'success'); 
      setTimeout(() => window.location.reload(), 1500); 
    } else { 
      setSaveStatus('error'); 
      showToast("Server error during password update", 'error');
    }
  };

  return (
    <div className="w-full max-w-4xl animate-in fade-in my-8">
      <div className="flex items-center justify-between mb-6 px-2">
        <h2 className="text-2xl font-bold flex items-center select-none">
          <Shield 
            className={`mr-2 cursor-pointer transition-transform active:scale-75 ${themeClasses.accentText}`} 
            onClick={() => setSecretClicks(c => c + 1)}
          /> 
          Admin Panel
        </h2>
      </div>

      <div className={`flex gap-2 mb-6 p-1 rounded-xl overflow-x-auto mx-2 ${themeClasses.bgCard} shadow-sm dark:shadow-none`}>
        <button onClick={() => setActiveTab('texts')} className={`flex-1 min-w-[100px] py-2 px-4 rounded-lg font-bold text-sm transition-all outline-none focus-visible:ring-2 focus-visible:ring-red-500/50 ${activeTab === 'texts' ? themeClasses.btnPrimary : themeClasses.hoverBtn}`}>Texts</button>
        <button onClick={() => setActiveTab('footer')} className={`flex-1 min-w-[100px] py-2 px-4 rounded-lg font-bold text-sm transition-all outline-none focus-visible:ring-2 focus-visible:ring-red-500/50 ${activeTab === 'footer' ? themeClasses.btnPrimary : themeClasses.hoverBtn}`}>Footer HTML</button>
        <button onClick={() => setActiveTab('settings')} className={`flex-1 min-w-[100px] py-2 px-4 rounded-lg font-bold text-sm transition-all outline-none focus-visible:ring-2 focus-visible:ring-red-500/50 ${activeTab === 'settings' ? themeClasses.btnPrimary : themeClasses.hoverBtn}`}>Settings</button>
      </div>

      <div className={`p-4 sm:p-6 rounded-2xl border ${themeClasses.bgCard}`}>
        {activeTab === 'texts' && (
          <div className="space-y-6">
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
              <div>
                <p className={`text-sm mb-2 ${themeClasses.textMuted}`}>Language for editing. Leave blank for fallback.</p>
                <div className={`flex gap-1 p-1 rounded-lg inline-flex border bg-[#F3EBE0] dark:bg-[#0F0E0D] border-[#EAE0D0] dark:border-[#3E3832]`}>
                  {['ru', 'en', 'de', 'fr', 'es'].map(l => (
                    <button key={l} onClick={() => setEditLang(l)} className={`px-4 py-1.5 rounded-md text-xs font-bold uppercase transition-all outline-none focus-visible:ring-2 focus-visible:ring-red-500/50 ${editLang === l ? themeClasses.btnPrimary : themeClasses.hoverBtn}`}>{l}</button>
                  ))}
                </div>
              </div>
              <button onClick={handleSaveTexts} disabled={saveStatus === 'saving'} className={`px-6 py-2.5 rounded-xl font-bold text-sm flex items-center transition-all outline-none focus-visible:ring-2 focus-visible:ring-red-500/50 ${saveStatus === 'saved' ? 'bg-emerald-600 text-white dark:bg-emerald-700' : themeClasses.btnPrimary}`}><Save size={16} className="mr-2"/> {saveStatus === 'saving' ? 'Saving...' : saveStatus === 'saved' ? 'Saved!' : 'Save Texts'}</button>
            </div>
            <div className="grid grid-cols-1 gap-4 max-h-[60vh] overflow-y-auto pr-2">
              {Object.keys(FALLBACK_DICT[editLang] || FALLBACK_DICT.en)
                .filter(key => key !== 'donateMessage' || secretClicks >= 5)
                .map(key => (
                <div key={key} className={`p-4 rounded-xl border bg-[#F3EBE0] dark:bg-[#110F0E] border-[#EAE0D0] dark:border-[#3E3832]`}>
                  <label className={`block text-xs font-mono mb-2 ${themeClasses.accentText}`}>{key}</label>
                  <textarea 
                    value={localDict[editLang]?.[key] !== undefined ? localDict[editLang][key] : (FALLBACK_DICT[editLang]?.[key] || '')} 
                    onChange={e => setLocalDict((prev: any) => ({...prev, [editLang]: {...(prev[editLang]||{}), [key]: e.target.value}}))} 
                    className={`w-full min-h-[3rem] p-3 rounded-lg border text-sm resize-y ${themeClasses.inputBg}`} 
                  />
                </div>
              ))}
            </div>
          </div>
        )}

        {activeTab === 'footer' && (
          <div className="space-y-4">
            <p className={`text-sm ${themeClasses.textMuted}`}>Text at the bottom of the page. Supports HTML.</p>
            <textarea value={localFooter} onChange={e => setLocalFooter(e.target.value)} className={`w-full h-32 p-4 rounded-xl border text-sm resize-y ${themeClasses.inputBg}`} />
            <button onClick={handleSaveFooter} disabled={saveStatus === 'saving'} className={`px-6 py-2.5 rounded-xl font-bold text-sm flex items-center transition-all outline-none focus-visible:ring-2 focus-visible:ring-red-500/50 ${saveStatus === 'saved' ? 'bg-emerald-600 text-white dark:bg-emerald-700' : themeClasses.btnPrimary}`}><Save size={16} className="mr-2"/> Save Footer</button>
          </div>
        )}

        {activeTab === 'settings' && (
          <div className="space-y-4">
            <p className={`text-sm ${themeClasses.textMuted}`}>Change Admin Panel password.</p>
            <input type="text" value={newPass} onChange={e => setNewPass(e.target.value)} placeholder="New Password" className={`w-full max-w-sm px-4 py-3 rounded-xl border font-medium ${themeClasses.inputBg}`} />
            <br/>
            <button onClick={handleSavePass} disabled={saveStatus === 'saving'} className={`px-6 py-2.5 rounded-xl font-bold text-sm flex items-center transition-all outline-none focus-visible:ring-2 focus-visible:ring-red-500/50 ${saveStatus === 'saved' ? 'bg-emerald-600 text-white dark:bg-emerald-700' : themeClasses.btnPrimary}`}><Save size={16} className="mr-2"/> Save Password</button>
          </div>
        )}
      </div>
    </div>
  );
}

// ==========================================
// STASH ROOM COMPONENT
// ==========================================
function RoomScreen({ roomHash, roomSeed, settings, updateSetting, themeClasses, soundType, t, showToast }: any) {
  const [messages, setMessages] = useState<any[]>([]);
  const [isSending, setIsSending] = useState(false);
  const [sendSuccess, setSendSuccess] = useState(false);
  
  const knownMsgIdsRef = useRef(new Set());
  const isFirstFetch = useRef(true);
  const mySentMessagesRef = useRef(new Set());
  
  const soundTypeRef = useRef(soundType);
  useEffect(() => {
    soundTypeRef.current = soundType;
  }, [soundType]);

  const [pubPhrase, setPubPhrase] = useState('');
  const [secretText, setSecretText] = useState('');
  const [publicLabel, setPublicLabel] = useState('');
  const MAX_PHRASE = 255;
  const MAX_SECRET = 10000;
  const MAX_LABEL = 50;

  const [readingMsg, setReadingMsg] = useState<any>(null); 
  const [readPubPhrase, setReadPubPhrase] = useState('');
  const [decryptedText, setDecryptedText] = useState('');
  const [decryptError, setDecryptError] = useState('');
  const [isDecrypting, setIsDecrypting] = useState(false);
  
  const [showInfo, setShowInfo] = useState(false);
  const [copiedInvite, setCopiedInvite] = useState(false);
  const [isComposeOpen, setIsComposeOpen] = useState(true);

  useEffect(() => {
    let isMounted = true;
    let timerId: any;

    const fetchLoop = async () => {
      if (!isMounted) return;
      await fetchMessages();
      // Опрашиваем сервер раз в 10 секунд для снижения нагрузки на хостинг
      if (isMounted) timerId = setTimeout(fetchLoop, 10000); 
    };

    fetchLoop();
    return () => { isMounted = false; clearTimeout(timerId); };
  }, [roomHash]);

  const fetchMessages = async () => {
    try {
      const res: any = await apiCall({ action: 'get_messages', room_hash: roomHash });
      if (res.success) {
        setMessages(res.messages);
        const currentIds = new Set(res.messages.map((m: any) => m.id));
        
        if (!isFirstFetch.current) {
          let hasNew = false;
          currentIds.forEach(id => { 
            if (!knownMsgIdsRef.current.has(id) && !mySentMessagesRef.current.has(id)) {
              hasNew = true; 
            }
          });
          if (hasNew) playTone(soundTypeRef.current);
        } else {
          isFirstFetch.current = false;
        }

        knownMsgIdsRef.current = currentIds as any;
      }
    } catch (e: any) {}
  };

  const handleSend = async (e: any) => {
    e.preventDefault();
    if (!pubPhrase || !secretText || pubPhrase.length > MAX_PHRASE || secretText.length > MAX_SECRET || publicLabel.length > MAX_LABEL) return;
    setIsSending(true);

    try {
      const encryptedPayload = await encryptMessage(secretText, pubPhrase);
      const res: any = await apiCall({ action: 'post_message', room_hash: roomHash, payload: encryptedPayload, public_label: publicLabel.trim(), expires_minutes: settings.expireMinutes, max_reads: settings.maxReads });

      if (res.success) {
        mySentMessagesRef.current.add(res.id); 
        setSecretText(''); setPubPhrase(''); setPublicLabel(''); setSendSuccess(true); setTimeout(() => setSendSuccess(false), 3000);
        fetchMessages();
        if (window.innerWidth < 1024) setIsComposeOpen(false);
      } else { showToast(t('errNetwork'), 'error'); }
    } catch (e: any) { showToast('Crypto Error: ' + e.message, 'error'); }
    setIsSending(false);
  };

  const handleOpenReader = (msg: any) => { setReadingMsg(msg); setReadPubPhrase(''); setDecryptedText(''); setDecryptError(''); };

  const attemptDecrypt = async (e: any) => {
    e.preventDefault();
    if (!readPubPhrase) return;
    setIsDecrypting(true); setDecryptError('');

    try {
      const resPayload: any = await apiCall({ action: 'get_payload', id: readingMsg.id, room_hash: roomHash });
      if (!resPayload.success) { setDecryptError(t('timeExpired')); setIsDecrypting(false); fetchMessages(); return; }

      try {
        const text = await decryptMessage(resPayload.payload, readPubPhrase);
        setDecryptedText(text);
        
        const resRead: any = await apiCall({ action: 'mark_read', id: readingMsg.id });
        if (resRead.deleted) setDecryptError(t('errLastRead'));
        fetchMessages();
      } catch (cryptoErr: any) {
        const resFail: any = await apiCall({ action: 'register_fail', id: readingMsg.id });
        if (resFail.deleted) {
           setDecryptError(t('errDestroyedLimit'));
           setReadingMsg(null); fetchMessages();
        } else {
           setDecryptError(`${t('errWrongPhrase')} ${resFail.attempts_left}`);
        }
      }
    } catch (err: any) { setDecryptError(t('errNetwork')); }
    setIsDecrypting(false);
  };

  const copyToClipboard = (text: string) => {
    const ta = document.createElement("textarea"); ta.value = text; document.body.appendChild(ta); ta.select();
    try { 
      document.execCommand('copy'); 
      showToast(t('copied'), 'success');
    } catch(e: any) {
      showToast("Copy error (try manually)", 'error');
    } 
    document.body.removeChild(ta);
  };

  const handleShareRoomInvite = () => {
    if (!roomSeed) return;
    const url = `${window.location.origin}${window.location.pathname}#${encodeURIComponent(roomSeed)}`;
    const ta = document.createElement("textarea"); ta.value = url; document.body.appendChild(ta); ta.select();
    try { document.execCommand('copy'); setCopiedInvite(true); setTimeout(() => setCopiedInvite(false), 3000); } catch(e: any){}
    document.body.removeChild(ta);
  };

  return (
    <div className="w-full max-w-5xl flex flex-col gap-4 sm:gap-6 my-2 sm:my-4 px-2 sm:px-0">
      
      {/* INVITE AND INFO BAR */}
      <div className={`p-4 sm:p-6 rounded-2xl border flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 sm:gap-4 ${themeClasses.bgCard}`}>
         <button onClick={handleShareRoomInvite} className={`w-full sm:w-auto px-6 py-3.5 sm:py-3 rounded-xl font-bold flex items-center justify-start sm:justify-center transition-all active:scale-95 ${themeClasses.hoverBtn}`}>
           {copiedInvite ? <Check size={20} className="mr-3 sm:mr-2 text-emerald-500"/> : <UserPlus size={20} className={`mr-3 sm:mr-2 ${themeClasses.accentText}`}/>}
           <span className="flex-1 text-left sm:flex-none">{copiedInvite ? t('copied') : t('btnInviteRoom')}</span>
         </button>
         <button onClick={() => setShowInfo(!showInfo)} className={`w-full sm:w-auto px-6 py-3.5 sm:py-3 rounded-xl font-bold flex items-center justify-start sm:justify-center transition-all active:scale-95 ${themeClasses.hoverBtn}`}>
           <Info size={20} className={`mr-3 sm:mr-2 ${themeClasses.accentText}`}/> 
           <span className="flex-1 text-left sm:flex-none">{t('btnHowItWorks')}</span>
           {showInfo ? <ChevronUp size={20} className="ml-2 opacity-50"/> : <ChevronDown size={20} className="ml-2 opacity-50"/>}
         </button>
      </div>

      {showInfo && (
         <div className="animate-in slide-in-from-top-4 fade-in duration-300">
           <HowItWorksInfographic t={t} />
         </div>
      )}

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 items-start">
        {/* COMPOSE SECTION */}
        <div className={`rounded-2xl border overflow-hidden ${themeClasses.bgCard}`}>
          <div 
            className={`p-4 sm:p-6 sm:pb-4 flex justify-between items-center cursor-pointer select-none transition-colors hover:bg-[#EAE0D0]/50 dark:hover:bg-white/5 border-b border-transparent dark:border-[#3E3832]`}
            onClick={() => setIsComposeOpen(!isComposeOpen)}
          >
            <h2 className="text-xl font-bold flex items-center"><Zap className={`mr-2 ${themeClasses.accentText}`}/> {t('encryptAndSend')}</h2>
            <div className={`p-1 rounded-md ${themeClasses.hoverBtn}`}>
              {isComposeOpen ? <ChevronUp size={24} className="opacity-50" /> : <ChevronDown size={24} className="opacity-50" />}
            </div>
          </div>
          
          {isComposeOpen && (
            <div className="p-4 sm:p-6 pt-0 sm:pt-0 animate-in fade-in slide-in-from-top-2">
              <form onSubmit={handleSend} className="space-y-5">
                <div>
                  <div className="flex justify-between items-end mb-2">
                    <label className={`block text-xs font-bold uppercase tracking-wider ${themeClasses.textMuted}`}>{t('pubPhraseLabel')}</label>
                    <span className={`text-xs font-mono font-bold ${pubPhrase.length > MAX_PHRASE * 0.9 ? 'text-red-500' : themeClasses.textMuted}`}>{pubPhrase.length} / {MAX_PHRASE}</span>
                  </div>
                  <input type="text" value={pubPhrase} onChange={e => setPubPhrase(e.target.value)} maxLength={MAX_PHRASE} placeholder={t('pubPhrasePlaceholder')} className={`w-full px-4 py-3 rounded-xl border font-medium text-sm sm:text-base ${themeClasses.inputBg}`} required/>
                </div>

                <div>
                  <div className="flex justify-between items-end mb-2">
                    <label className={`block text-xs font-bold uppercase tracking-wider ${themeClasses.textMuted}`}>
                      {t('publicLabel')}
                    </label>
                    <span className={`text-xs font-mono font-bold ${publicLabel.length > MAX_LABEL * 0.9 ? 'text-red-500' : themeClasses.textMuted}`}>{publicLabel.length} / {MAX_LABEL}</span>
                  </div>
                  <input type="text" value={publicLabel} onChange={e => setPublicLabel(e.target.value)} maxLength={MAX_LABEL} placeholder={t('publicLabelPh')} className={`w-full px-4 py-3 rounded-xl border font-medium text-sm sm:text-base ${themeClasses.inputBg}`} />
                  <p className={`text-[11px] sm:text-xs mt-2 mb-2 leading-relaxed text-orange-700/90 dark:text-[#D6C8B3]/60 font-medium`}>
                    {t('publicLabelWarn')}
                  </p>
                </div>

                <div>
                  <div className="flex justify-between items-end mb-2">
                    <label className={`block text-xs font-bold uppercase tracking-wider ${themeClasses.textMuted}`}>{t('secretLabel')}</label>
                    <span className={`text-xs font-mono font-bold ${secretText.length > MAX_SECRET * 0.9 ? 'text-red-500' : themeClasses.textMuted}`}>{secretText.length} / {MAX_SECRET}</span>
                  </div>
                  <textarea value={secretText} onChange={e => setSecretText(e.target.value)} maxLength={MAX_SECRET} placeholder={t('secretPlaceholder')} className={`w-full min-h-[16rem] px-4 py-3 rounded-xl border resize-y text-sm sm:text-base ${themeClasses.inputBg}`} required/>
                </div>

                <div className={`p-3 sm:p-4 rounded-xl border transition-all bg-[#F3EBE0] dark:bg-[#1A1816] border-[#EAE0D0] dark:border-[#3E3832]`}>
                   <div className="text-sm font-bold flex items-center mb-3"><Settings size={16} className="mr-2 opacity-70"/> {t('settingsLabel')}</div>
                   <div className="grid grid-cols-2 gap-3 sm:gap-4">
                     <div>
                       <label className={`block text-[10px] sm:text-xs uppercase font-bold mb-1 opacity-70`}>{t('delAfter')}</label>
                       <select value={settings.expireMinutes} onChange={e => updateSetting('expireMinutes', Number(e.target.value))} className={`w-full p-2 text-xs sm:text-sm rounded-lg border ${themeClasses.inputBg}`}>
                         <option value={1}>{t('time1m')}</option><option value={10}>{t('time10m')}</option><option value={60}>{t('time1h')}</option>
                         <option value={600}>{t('time10h')}</option><option value={1440}>{t('time1d')}</option><option value={10080}>{t('time7d')}</option>
                       </select>
                     </div>
                     <div>
                       <label className={`block text-[10px] sm:text-xs uppercase font-bold mb-1 opacity-70`}>{t('maxReads')}</label>
                       <select value={settings.maxReads} onChange={e => updateSetting('maxReads', Number(e.target.value))} className={`w-full p-2 text-xs sm:text-sm rounded-lg border ${themeClasses.inputBg}`}>
                         <option value={1}>{t('read1')}</option><option value={2}>{t('read2')}</option><option value={3}>{t('read3')}</option>
                         <option value={5}>{t('read5')}</option><option value={10}>{t('read10')}</option><option value={20}>{t('read20')}</option>
                       </select>
                     </div>
                   </div>
                </div>

                <button type="submit" disabled={isSending || !pubPhrase || !secretText} className={`w-full font-bold py-4 rounded-xl transition-all active:scale-95 flex items-center justify-center disabled:opacity-50 ${themeClasses.btnPrimary}`}>
                  {isSending ? <div className="w-6 h-6 border-2 border-[#EBE1D1]/30 border-t-[#EBE1D1] rounded-full animate-spin"></div> : sendSuccess ? <><Check size={20} className="mr-2"/> {t('btnSent')}</> : <><Send size={20} className="mr-2"/> {t('encryptAndSend')}</>}
                </button>
              </form>
            </div>
          )}
        </div>

        {/* LIST AND READ SECTION */}
        <div className={`flex flex-col min-h-[450px] sm:min-h-[600px] h-full rounded-2xl border overflow-hidden relative ${themeClasses.bgCard}`}>
          
          {readingMsg ? (
            <div className="absolute inset-0 z-20 bg-inherit flex flex-col animate-in slide-in-from-right">
              <div className="p-3 sm:p-4 border-b border-inherit flex items-center justify-between bg-inherit z-10 shrink-0">
                 <button onClick={() => setReadingMsg(null)} className={`text-sm font-bold flex items-center px-3 py-1.5 rounded-lg transition-colors ${themeClasses.hoverBtn}`}>
                   &larr; {t('btnBack')}
                 </button>
                 <span className="font-mono text-xs opacity-50 hidden sm:block">ID: {readingMsg.id.substring(0,8)}</span>
                 <button onClick={() => setReadingMsg(null)} className={`p-1.5 rounded-lg transition-colors ${themeClasses.hoverBtn}`} title="Close">
                   <X size={20} />
                 </button>
              </div>

              <div className="p-4 sm:p-6 flex-1 overflow-y-auto">
                {!decryptedText ? (
                  <div className="max-w-sm mx-auto mt-6 sm:mt-10">
                    <div className={`w-14 h-14 sm:w-16 sm:h-16 rounded-2xl flex items-center justify-center mx-auto mb-4 sm:mb-6 bg-[#F3EBE0] dark:bg-[#110F0E]`}><Lock size={24} className="opacity-50" /></div>
                    <h3 className="text-lg sm:text-xl font-bold text-center mb-6">{t('needDecrypt')}</h3>
                    <form onSubmit={attemptDecrypt}>
                      <input type="text" value={readPubPhrase} onChange={e => setReadPubPhrase(e.target.value)} placeholder={t('decryptPhrasePlaceholder')} className={`w-full px-4 py-3 rounded-xl border font-medium mb-4 text-center text-sm sm:text-base ${themeClasses.inputBg}`} required/>
                      {decryptError && <div className="mb-4 p-3 rounded-xl bg-red-500/10 text-red-600 dark:text-red-400 text-sm font-medium text-center border border-red-500/20 flex items-start gap-2"><AlertTriangle size={16} className="shrink-0 mt-0.5"/> <span>{decryptError}</span></div>}
                      <button type="submit" disabled={isDecrypting || !readPubPhrase} className={`w-full font-bold py-3 rounded-xl transition-transform active:scale-95 flex items-center justify-center disabled:opacity-50 ${themeClasses.btnPrimary}`}>
                        {isDecrypting ? <div className="w-5 h-5 border-2 border-[#EBE1D1]/30 border-t-[#EBE1D1] rounded-full animate-spin"></div> : t('btnDecrypt')}
                      </button>
                    </form>
                    <p className="text-center text-xs opacity-50 mt-6">{t('readsLeft')}: {readingMsg.max_reads - readingMsg.current_reads}. <br className="hidden sm:block"/>{t('warnDelete5')}</p>
                  </div>
                ) : (
                  <div className="animate-in fade-in">
                     <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                       <span className="inline-flex items-center px-3 py-1.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-xs font-bold uppercase self-start"><Unlock size={14} className="mr-1.5"/> {t('decryptedLocal')}</span>
                       <button onClick={() => { copyToClipboard(decryptedText); }} className={`p-2 rounded-lg flex items-center justify-center gap-2 font-bold text-sm ${themeClasses.hoverBtn} ${themeClasses.accentText}`}><Copy size={18}/> Copy</button>
                     </div>
                     <div className={`p-4 rounded-xl border whitespace-pre-wrap break-words text-sm sm:text-base leading-relaxed bg-[#F3EBE0] dark:bg-[#110F0E] border-[#EAE0D0] dark:border-[#3E3832] text-[#2c241b] dark:text-[#EBE1D1]`}>
                       {decryptedText}
                     </div>
                     {decryptError && <div className="mt-4 p-3 rounded-xl bg-orange-500/10 text-orange-600 dark:text-orange-400 text-sm font-medium">{decryptError}</div>}
                     <div className="mt-6 p-4 rounded-xl border border-dashed border-red-500/30 bg-red-500/5">
                       <p className="text-xs text-red-600 dark:text-red-400 font-medium text-center">{t('copyWarn')}</p>
                     </div>
                  </div>
                )}
              </div>
            </div>
          ) : (
            <>
              <div className="p-4 sm:p-6 border-b border-inherit shrink-0 flex items-center justify-between">
                <h2 className="text-lg sm:text-xl font-bold flex items-center"><Clock className={`mr-2 ${themeClasses.accentText}`}/> {t('waitInBox')}</h2>
                <span className={`px-2.5 py-1 font-bold rounded-lg text-sm bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-500`}>{messages.length}</span>
              </div>
              <div className="flex-1 overflow-y-auto p-2 sm:p-3">
                {messages.length === 0 ? (
                  <div className="h-full flex flex-col items-center justify-center text-center p-6 opacity-50">
                     <Flame size={48} className="mb-4 opacity-20" />
                     <p className="font-medium">{t('boxEmpty')}</p>
                     <p className="text-sm mt-2 max-w-[200px]">{t('msgAutoAppear')}</p>
                  </div>
                ) : (
                  <div className="space-y-2">
                    {messages.map(msg => (
                      <div key={msg.id} onClick={() => handleOpenReader(msg)} className={`p-3 sm:p-4 rounded-xl border cursor-pointer transition-all hover:-translate-y-0.5 bg-[#F3EBE0] dark:bg-[#110F0E] border-[#EAE0D0] dark:border-[#3E3832] hover:border-red-400 dark:hover:border-red-500/50 hover:shadow-md dark:hover:shadow-[0_4px_15px_rgba(225,29,72,0.1)]`}>
                        <div className="flex items-center justify-between mb-2">
                           <div className="flex items-center gap-2">
                              <Lock size={16} className={`${themeClasses.accentText} shrink-0`} />
                              <span className="font-bold text-sm truncate max-w-[200px] sm:max-w-[300px]">
                                {msg.public_label ? msg.public_label : `${t('cipherPrefix')}${msg.id.substring(0,6)}`}
                              </span>
                           </div>
                           <div className="flex gap-1 flex-wrap justify-end max-w-[100px]">
                             {Array.from({length: Math.min(msg.max_reads, 20)}).map((_, i) => (
                                <div key={i} className={`w-1.5 h-1.5 sm:w-2 sm:h-2 rounded-full ${i < msg.current_reads ? 'bg-red-500' : 'bg-emerald-500'}`} title=""/>
                             ))}
                             {msg.max_reads > 20 && <span className="text-[8px] opacity-50">+{msg.max_reads - 20}</span>}
                           </div>
                        </div>
                        <div className="flex items-center justify-between text-xs opacity-60">
                           <CountdownTimer expiresAt={msg.expires_at} t={t} />
                           {msg.failed_attempts > 0 && <span className="text-red-500 font-bold">{t('errAttempts')}: {msg.failed_attempts}/5</span>}
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </>
          )}
        </div>
      </div>

      <div className="w-full">
         <SupportBlock t={t} themeClasses={themeClasses} showToast={showToast} />
      </div>
    </div>
  );
}

function CountdownTimer({ expiresAt, t }: any) {
  const [timeLeft, setTimeLeft] = useState('');
  const [isUrgent, setIsUrgent] = useState(false);
  const [isBurned, setIsBurned] = useState(false);

  useEffect(() => {
    const calc = () => {
      const exp = new Date(expiresAt + 'Z').getTime(); 
      const now = new Date().getTime();
      const diff = exp - now;
      
      if (diff <= 0) { 
        setTimeLeft(t('timeExpired')); 
        setIsBurned(true);
        setIsUrgent(false);
        return; 
      }
      
      setIsBurned(false);
      setIsUrgent(diff < 60000); 
      
      const d = Math.floor(diff / (1000 * 60 * 60 * 24));
      const h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
      const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
      const s = Math.floor((diff % (1000 * 60)) / 1000);
      
      if (d > 0) setTimeLeft(`${t('timeDelIn')} ${d}${t('days')} ${h}${t('hours')}`);
      else if (h > 0) setTimeLeft(`${t('timeDelIn')} ${h}${t('hours')} ${m}${t('mins')}`);
      else setTimeLeft(`${t('timeDelIn')} ${m}${t('mins')} ${s}${t('secs')}`);
    };
    calc(); const int = setInterval(calc, 1000); return () => clearInterval(int);
  }, [expiresAt, t]);

  let classes = "";
  if (isBurned) classes = "text-red-600 dark:text-red-500 font-bold animate-pulse";
  else if (isUrgent) classes = "text-orange-600 dark:text-orange-400 font-bold animate-pulse";

  return <span className={classes}>{timeLeft}</span>;
}

function InfoScreen({ onBack, themeClasses, t }: any) {
  return (
    <div className="w-full max-w-4xl animate-in slide-in-from-bottom-4 my-8 px-2 sm:px-0">
      <button onClick={onBack} className={`mb-6 px-4 py-2 rounded-xl font-bold text-sm transition-colors border bg-[#FDF8EC] dark:bg-[#1A1816] shadow-sm dark:shadow-none border-[#EAE0D0] dark:border-[#3E3832] hover:bg-[#F3EBE0] dark:hover:bg-[#2B2622] outline-none focus-visible:ring-2 focus-visible:ring-red-500/50`}>&larr; {t('btnBack')}</button>
      <div className={`p-6 sm:p-10 rounded-2xl border shadow-xl ${themeClasses.bgCard}`}>
        <h2 className="text-2xl sm:text-3xl font-black mb-6 flex items-center"><Flame className={`mr-3 shrink-0 ${themeClasses.accentText}`} size={32}/> {t('infoHowItWorks')}</h2>
        
        <div className="space-y-6 text-sm sm:text-base leading-relaxed opacity-90">
          <p dangerouslySetInnerHTML={{__html: t('infoP1')}} />

          <div className="p-5 rounded-2xl border bg-indigo-50/50 dark:bg-indigo-950/10 border-indigo-200 dark:border-indigo-900/30">
            <h3 className={`font-bold text-lg mb-2 text-indigo-600 dark:text-indigo-400`}>{t('infoTechTitle')}</h3>
            <p dangerouslySetInnerHTML={{__html: t('infoTechText')}} />
          </div>

          <div className="p-5 rounded-2xl border bg-orange-50/50 dark:bg-orange-950/10 border-orange-200 dark:border-orange-900/30">
            <h3 className={`font-bold text-lg mb-2 text-orange-700 dark:text-orange-400`}>{t('infoRisksTitle')}</h3>
            <p dangerouslySetInnerHTML={{__html: t('infoRisksText')}} />
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
            <div className="p-5 rounded-xl border bg-[#F3EBE0] dark:bg-[#1A1816] border-[#EAE0D0] dark:border-[#3E3832]">
              <Trash2 className="text-rose-500 mb-3" size={24}/>
              <h4 className="font-bold mb-2">{t('infoHardwareDelTitle')}</h4>
              <p className="text-sm opacity-80">{t('infoHardwareDelText')}</p>
            </div>
            <div className="p-5 rounded-xl border bg-[#F3EBE0] dark:bg-[#1A1816] border-[#EAE0D0] dark:border-[#3E3832]">
              <ShieldAlert className="text-emerald-500 mb-3" size={24}/>
              <h4 className="font-bold mb-2">{t('infoBruteTitle')}</h4>
              <p className="text-sm opacity-80">{t('infoBruteText')}</p>
            </div>
          </div>

          <div className="mt-8 p-4 sm:p-5 rounded-xl border border-dashed bg-red-50 dark:bg-red-950/20 border-red-200 dark:border-red-900/50 text-red-900/80 dark:text-red-200/80">
            <h4 className="font-bold flex items-center mb-2"><AlertTriangle size={18} className="mr-2"/> {t('disclaimerTitle')}</h4>
            <p className="text-xs text-justify">{t('disclaimerText')}</p>
          </div>

          <p className="text-xs opacity-50 mt-8 pt-6 border-t border-inherit text-center">
             {t('infoFooter') || 'No IP addresses, logs or cookies are collected.'}
          </p>
        </div>
      </div>
    </div>
  );
}
