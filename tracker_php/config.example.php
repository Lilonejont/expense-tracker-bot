<?php
// Пример файла конфигурации для GitHub
// Скопируйте этот файл в config.php и укажите свои данные

// Токен бота, полученный у @BotFather
define('BOT_TOKEN', 'YOUR_BOT_TOKEN_HERE');

// Ссылка на ваш index.html (обязательно HTTPS)
define('WEBAPP_URL', 'https://your-domain.com/path/index.html?v=1');

// Ссылка на bot.php для установки вебхука (обязательно HTTPS)
define('WEBHOOK_URL', 'https://your-domain.com/path/bot.php');

// Ключ API для Gemini (опционально, для распознавания чеков)
define('GEMINI_API_KEY', 'YOUR_GEMINI_API_KEY_HERE');

// Путь к файлу базы данных SQLite
define('DB_FILE', __DIR__ . '/expenses.sqlite');

// Режим отладки (true для вывода ошибок, false для продакшена)
define('DEBUG_MODE', false);
