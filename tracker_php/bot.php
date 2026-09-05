<?php
// bot.php - Telegram webhook controller
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/parser.php';
require_once __DIR__ . '/categorizer.php';

// Telegram API Helper Functions
function tgRequest($method, $data = []) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function tgSendDocument($chatId, $content, $filename, $caption = '') {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendDocument";
    $boundary = '----TelegramFormBoundary' . md5(time());
    $eol = "\r\n";

    $body = '';
    // chat_id
    $body .= '--' . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="chat_id"' . $eol . $eol;
    $body .= $chatId . $eol;

    // caption
    if (!empty($caption)) {
        $body .= '--' . $boundary . $eol;
        $body .= 'Content-Disposition: form-data; name="caption"' . $eol . $eol;
        $body .= $caption . $eol;
        
        $body .= '--' . $boundary . $eol;
        $body .= 'Content-Disposition: form-data; name="parse_mode"' . $eol . $eol;
        $body .= 'HTML' . $eol;
    }

    // document
    $body .= '--' . $boundary . $eol;
    $body .= 'Content-Disposition: form-data; name="document"; filename="' . $filename . '"' . $eol;
    $body .= 'Content-Type: text/csv; charset=utf-8' . $eol . $eol;
    $body .= $content . $eol;
    $body .= '--' . $boundary . '--' . $eol;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: multipart/form-data; boundary=' . $boundary
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

function formatAmount($n, $currency = '₽') {
    return number_format($n, 2, '.', ' ') . ' ' . $currency;
}

// ── Premium Emoji Helper ──────────────────────────────────────────────
// Verified animated premium emoji IDs (sets: NewsEmoji, RestrictedEmoji, HandEmoji, Topics)
define('PE_LIGHTNING',   '5456140674028019486'); // ⚡ NewsEmoji
define('PE_FIRE',        '5420315771991497307'); // 🔥 RestrictedEmoji
define('PE_THUMBSUP',    '5368324170671202286'); // 👍 HandEmoji
define('PE_SPEECH',      '5443038326535759644'); // 💬 NewsEmoji
define('PE_WARNING',     '5447644880824181073'); // ⚠ NewsEmoji
define('PE_CHART_UP',    '5449683594425410231'); // 🔼 NewsEmoji
define('PE_COOL',        '5373141891321699086'); // 😎 RestrictedEmoji
define('PE_HOURGLASS',   '5386367538735104399'); // ⌛ NewsEmoji
define('PE_DIAMOND',     '5471952986970267163'); // 💎 RestrictedEmoji
define('PE_PARTY',       '5436040291507247633'); // 🎉 RestrictedEmoji
define('PE_PHONE',       '5407025283456835913'); // 📱 RestrictedEmoji
define('PE_SEARCH',      '5309965701241379366'); // 🔎 Topics
define('PE_CHART_DOWN',  '5447183459602669338'); // 🔽 NewsEmoji
define('PE_BOLT',        '5312016608254762256'); // ⚡ Topics
define('PE_SAD',         '5336827789917662785'); // 😢

/**
 * Premium emoji shorthand.
 * When PREMIUM_ENABLED is true: returns <tg-emoji> HTML tag with animated emoji.
 * When false: returns just the fallback Unicode emoji (safe for all bots).
 * Set to true only if the bot owner has Telegram Premium subscription.
 */
define('PREMIUM_ENABLED', true);

function pe($id, $fb) {
    if (PREMIUM_ENABLED) {
        return '<tg-emoji emoji-id="' . $id . '">' . $fb . '</tg-emoji>';
    }
    return $fb;
}

function getExpenseCardText($expense) {
    $settings = getUserSettings($expense['user_id']);
    $currency = $settings['currency'];
    $desc = !empty($expense['description']) ? $expense['description'] : 'без описания';
    return "{$expense['category_emoji']} <b>{$expense['category_name']}</b>\n"
        . pe(PE_DIAMOND, '💰') . " <b>" . formatAmount($expense['amount'], $currency) . "</b>\n"
        . pe(PE_SPEECH, '📝') . " " . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
}

function getLimitWarningText($userId, $categoryId) {
    $db = getDb();
    $settings = getUserSettings($userId);
    $groupId = $settings['group_id'];
    $currency = $settings['currency'];

    $stmt = $db->prepare("
        SELECT cl.limit_amount, c.name as category_name, c.emoji as category_emoji
        FROM category_limits cl
        JOIN categories c ON cl.category_id = c.id
        WHERE cl.user_id = ? AND cl.category_id = ?
    ");
    $stmt->execute([$groupId, $categoryId]);
    $limitRow = $stmt->fetch();
    if (!$limitRow) return '';

    $limitAmount = (float)$limitRow['limit_amount'];
    if ($limitAmount <= 0) return '';

    $categoryName = $limitRow['category_name'];
    $categoryEmoji = $limitRow['category_emoji'];

    $stmtSpent = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) as spent
        FROM expenses
        WHERE user_id IN (SELECT user_id FROM users_settings WHERE group_id = ?) 
          AND category_id = ?
          AND created_at >= datetime('now', 'localtime', 'start of month')
    ");
    $stmtSpent->execute([$groupId, $categoryId]);
    $spentRow = $stmtSpent->fetch();
    $spent = (float)$spentRow['spent'];


    $pct = round(($spent / $limitAmount) * 100);

    if ($spent >= $limitAmount) {
        return "\n\n" . pe(PE_WARNING, '⚠️') . " <b>Внимание! Лимит превышен!</b>\n"
             . "По категории {$categoryEmoji} <b>{$categoryName}</b> израсходовано:\n"
             . "<b>" . formatAmount($spent, $currency) . "</b> из <b>" . formatAmount($limitAmount, $currency) . "</b> (<i>{$pct}%</i>)";
    } else if ($pct >= 80) {
        return "\n\n" . pe(PE_WARNING, '⚠️') . " <b>Предупреждение: Лимит близко!</b>\n"
             . "По категории {$categoryEmoji} <b>{$categoryName}</b> израсходовано <i>{$pct}%</i> от лимита:\n"
             . "<b>" . formatAmount($spent, $currency) . "</b> из <b>" . formatAmount($limitAmount, $currency) . "</b>";
    }

    return '';
}

function getExpenseSuccessMessage($expense) {
    $text = getExpenseCardText($expense);
    $warning = getLimitWarningText($expense['user_id'], $expense['category_id']);
    return $text . $warning;
}

function getExpenseKeyboard($expenseId) {
    return [
        'inline_keyboard' => [
            [
                ['text' => 'Изменить категорию', 'callback_data' => "edit:{$expenseId}", 'icon_custom_emoji_id' => PE_SEARCH]
            ],
            [
                ['text' => 'Удалить запись', 'callback_data' => "del:{$expenseId}", 'icon_custom_emoji_id' => PE_WARNING]
            ]
        ]
    ];
}

function callGeminiApi($prompt, $imgBase64 = null, $mimeType = 'image/jpeg') {
    if (!defined('GEMINI_API_KEY') || empty(GEMINI_API_KEY)) return null;
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=" . GEMINI_API_KEY;
    
    $parts = [["text" => $prompt]];
    if ($imgBase64) {
        $parts[] = ["inline_data" => ["mime_type" => $mimeType, "data" => $imgBase64]];
    }
    
    $payload = ["contents" => [["parts" => $parts]]];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $res = curl_exec($ch);
    curl_close($ch);
    
    if ($res) {
        $data = json_decode($res, true);
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }
    return null;
}

// ── Read incoming update ───────────────────────────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error.log');
set_exception_handler(function($e) {
    error_log("UNCAUGHT: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(200); // Don't return 500 to Telegram
});

$rawInput = file_get_contents('php://input');
if (empty($rawInput)) {
    echo "Expense Tracker Bot is active.";
    exit;
}

$update = json_decode($rawInput, true);
if (!$update) {
    exit;
}

// ── Handle Callback Query (Buttons) ────────────────────────────────────
if (isset($update['callback_query'])) {
    $cb = $update['callback_query'];
    $cbId = $cb['id'];
    $data = $cb['data'] ?? '';
    $from = $cb['from'];
    $userId = $from['id'];
    $chatId = $cb['message']['chat']['id'] ?? $userId;
    $messageId = $cb['message']['message_id'] ?? null;

    ensureUser($userId, $from['first_name'] ?? '', $from['username'] ?? '');

    if (strpos($data, 'set_curr_') === 0) {
        $currency = str_replace('set_curr_', '', $data);
        $db = getDb();
        $stmt = $db->prepare("UPDATE users_settings SET currency = ? WHERE user_id = ?");
        $stmt->execute([$currency, $userId]);
        
        tgRequest('answerCallbackQuery', ['callback_query_id' => $cbId, 'text' => "Валюта изменена на $currency!"]);
        tgRequest('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => pe(PE_THUMBSUP, '✅') . " <b>Готово!</b>\nВаша основная валюта теперь: <b>$currency</b>",
            'parse_mode' => 'HTML'
        ]);
        exit;
    }

    // 1. Delete expense
    if (preg_match('/^del:(\d+)$/', $data, $m)) {
        $expId = (int)$m[1];
        $exp = getExpenseById($expId, $userId);
        if ($exp) {
            deleteExpense($expId, $userId);
            tgRequest('answerCallbackQuery', [
                'callback_query_id' => $cbId,
                'text' => 'Удалено!'
            ]);
            $undoKb = [
                'inline_keyboard' => [
                    [
                        ['text' => '↩ Восстановить', 'callback_data' => "undo:{$expId}:" . urlencode($exp['amount'] . '|' . $exp['description'] . '|' . $exp['category_id'])]
                    ]
                ]
            ];
            tgRequest('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => pe(PE_WARNING, '🗑') . " <i>Расход удалён: " . htmlspecialchars($exp['description'], ENT_QUOTES, 'UTF-8') . " (" . formatAmount($exp['amount']) . ")</i>",
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode($undoKb)
            ]);
        } else {
            tgRequest('answerCallbackQuery', [
                'callback_query_id' => $cbId,
                'text' => 'Расход не найден или уже удалён'
            ]);
        }
        exit;
    }

    // 2. Undo delete
    if (preg_match('/^undo:(\d+):(.*)$/', $data, $m)) {
        $parts = explode('|', urldecode($m[2]));
        if (count($parts) === 3) {
            $amount = (float)$parts[0];
            $desc = $parts[1];
            $catId = (int)$parts[2];
            $restored = addExpense($userId, $amount, $desc, $catId);

            tgRequest('answerCallbackQuery', [
                'callback_query_id' => $cbId,
                'text' => 'Восстановлено!'
            ]);

            tgRequest('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => getExpenseCardText($restored),
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(getExpenseKeyboard($restored['id']))
            ]);
        }
        exit;
    }

    // 3. Edit category (show category list)
    if (preg_match('/^edit:(\d+)$/', $data, $m)) {
        $expId = (int)$m[1];
        $exp = getExpenseById($expId, $userId);
        if (!$exp) {
            tgRequest('answerCallbackQuery', [
                'callback_query_id' => $cbId,
                'text' => 'Расход не найден'
            ]);
            exit;
        }

        $categories = getCategories();
        $buttons = [];
        $row = [];
        foreach ($categories as $idx => $c) {
            $row[] = [
                'text' => "{$c['emoji']} {$c['name']}",
                'callback_data' => "setcat:{$expId}:{$c['id']}"
            ];
            if (count($row) === 2) {
                $buttons[] = $row;
                $row = [];
            }
        }
        if (!empty($row)) {
            $buttons[] = $row;
        }
        $buttons[] = [
            ['text' => '◀ Назад', 'callback_data' => "back:{$expId}"]
        ];

        tgRequest('answerCallbackQuery', ['callback_query_id' => $cbId]);
        tgRequest('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => pe(PE_SEARCH, '✏') . " Выберите категорию для:\n<b>" . formatAmount($exp['amount']) . "</b> — <i>" . htmlspecialchars($exp['description'], ENT_QUOTES, 'UTF-8') . "</i>",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode(['inline_keyboard' => $buttons])
        ]);
        exit;
    }

    // 4. Set category
    if (preg_match('/^setcat:(\d+):(\d+)$/', $data, $m)) {
        $expId = (int)$m[1];
        $catId = (int)$m[2];
        $updated = updateExpense($expId, $userId, ['category_id' => $catId]);
        if ($updated) {
            tgRequest('answerCallbackQuery', [
                'callback_query_id' => $cbId,
                'text' => "Категория: {$updated['category_emoji']} {$updated['category_name']}"
            ]);
            tgRequest('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => getExpenseCardText($updated),
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(getExpenseKeyboard($expId))
            ]);
        }
        exit;
    }

    // 5. Back to card
    if (preg_match('/^back:(\d+)$/', $data, $m)) {
        $expId = (int)$m[1];
        $exp = getExpenseById($expId, $userId);
        if ($exp) {
            tgRequest('answerCallbackQuery', ['callback_query_id' => $cbId]);
            tgRequest('editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => getExpenseSuccessMessage($exp),
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(getExpenseKeyboard($expId))
            ]);
        }
        exit;
    }
}

// ── Handle Message ─────────────────────────────────────────────────────
if (isset($update['message'])) {
    $msg = $update['message'];
    $chatId = $msg['chat']['id'];
    $text = trim($msg['text'] ?? '');
    $from = $msg['from'];
    $userId = $from['id'];

    ensureUser($userId, $from['first_name'] ?? '', $from['username'] ?? '');

    // /start command
    if (preg_match('/^\/start\s+invite_(\d+)$/', $text, $match)) {
        $inviterId = (int)$match[1];
        if ($inviterId !== $userId) {
            $db = getDb();
            $stmt = $db->prepare("INSERT OR REPLACE INTO users_settings (user_id, group_id, currency) VALUES (?, ?, (SELECT currency FROM users_settings WHERE user_id = ?))");
            $stmt->execute([$userId, $inviterId, $inviterId]);
            tgRequest('sendMessage', [
                'chat_id' => $chatId,
                'text' => pe(PE_PARTY, '🤝') . " <b>Бюджет объединен!</b>\n\nТеперь вы и пригласивший вас пользователь ведете общий учет расходов. Все графики и лимиты стали общими.",
                'parse_mode' => 'HTML'
            ]);
            exit;
        }
    }

    if (preg_match('/^\/start($|@|\s)/i', $text)) {
        // Set persistent chat menu button to web app
        tgRequest('setChatMenuButton', [
            'chat_id' => $chatId,
            'menu_button' => json_encode([
                'type' => 'web_app',
                'text' => '📊 Панель',
                'web_app' => ['url' => WEBAPP_URL]
            ])
        ]);

        $welcome = pe(PE_DIAMOND, '✨') . " <b>Трекер Расходов</b>\n"
            . "Ваш простой финансовый помощник.\n\n"
            . pe(PE_LIGHTNING, '⚡') . " <b>Как записать трату?</b>\n"
            . "Просто отправьте мне сообщение:\n"
            . pe(PE_FIRE, '☕') . " <code>кофе 350</code>\n"
            . pe(PE_CHART_UP, '🧾') . " <i>Или отправьте фото/PDF чека</i>\n\n"
            . pe(PE_COOL, '🤖') . " <i>Я сам определю категорию!</i>\n\n"
            . pe(PE_FIRE, '📌') . " <b>Команды:</b>\n"
            . "• /today — За сегодня\n"
            . "• /week — За неделю\n"
            . "• /month — За месяц\n"
            . "• /limit [кат] [сумма] — Лимит\n"
            . "• /currency — Валюта\n"
            . "• /invite — Общий бюджет\n"
            . "• /export — Скачать Excel файл\n\n"
            . pe(PE_CHART_DOWN, '👇') . " <b>Нажмите кнопку ниже</b>, чтобы открыть панель с графиками.";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'Открыть панель', 'web_app' => ['url' => WEBAPP_URL], 'icon_custom_emoji_id' => PE_CHART_UP]
                ]
            ]
        ];

        tgRequest('sendMessage', [
            'chat_id' => $chatId,
            'text' => $welcome,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($keyboard)
        ]);
        exit;
    }
    
    // /invite command
    if ($text === '/invite') {
        $settings = getUserSettings($userId);
        $groupId = $settings['group_id'];
        
        $botUser = tgRequest('getMe', []);
        $botUsername = $botUser['result']['username'] ?? 'TajUC_bot';
        
        $inviteLink = "https://t.me/$botUsername?start=invite_$groupId";
        tgRequest('sendMessage', [
            'chat_id' => $chatId,
            'text' => pe(PE_THUMBSUP, '🤝') . " <b>Общий бюджет на двоих</b>\n\nПерешлите это сообщение вашему партнеру. Когда он перейдет по ссылке, ваши расходы и статистика объединятся:\n\n👉 $inviteLink",
            'parse_mode' => 'HTML'
        ]);
        exit;
    }

    // /currency command
    if ($text === '/currency') {
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🇷🇺 RUB (₽)', 'callback_data' => 'set_curr_₽'],
                    ['text' => '🇹🇯 TJS (смн)', 'callback_data' => 'set_curr_смн']
                ],
                [
                    ['text' => '🇺🇸 USD ($)', 'callback_data' => 'set_curr_$'],
                    ['text' => '🇪🇺 EUR (€)', 'callback_data' => 'set_curr_€']
                ]
            ]
        ];
        tgRequest('sendMessage', [
            'chat_id' => $chatId,
            'text' => pe(PE_DIAMOND, '💱') . " <b>Выберите основную валюту:</b>\nВсе ваши расходы, лимиты и графики будут отображаться в этой валюте.",
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($keyboard)
        ]);
        exit;
    }
    
    // /limit command
    if (preg_match('/^\/limit\s+(.+?)\s+(\d+(?:[\.,]\d+)?)$/iu', $text, $matches)) {
        $catName = mb_strtolower(trim($matches[1]));
        $limitAmount = (float)str_replace(',', '.', $matches[2]);
        
        $categories = getCategories();
        $catId = null;
        $foundCatName = "";
        foreach ($categories as $c) {
            if (mb_strtolower($c['name']) === $catName || strpos(mb_strtolower($c['name']), $catName) !== false) {
                $catId = $c['id'];
                $foundCatName = $c['name'];
                break;
            }
        }
        
        if ($catId) {
            $db = getDb();
            $settings = getUserSettings($userId);
            $groupId = $settings['group_id'];
            $stmt = $db->prepare("INSERT OR REPLACE INTO category_limits (id, user_id, category_id, limit_amount) 
                VALUES ((SELECT id FROM category_limits WHERE user_id = ? AND category_id = ?), ?, ?, ?)");
            $stmt->execute([$groupId, $catId, $groupId, $catId, $limitAmount]);
            
            tgRequest('sendMessage', [
                'chat_id' => $chatId,
                'text' => pe(PE_FIRE, '🎯') . " <b>Лимит установлен!</b>\n\nКатегория: <b>$foundCatName</b>\nЛимит в месяц: <b>" . number_format($limitAmount, 0, '.', ' ') . " ₽</b>",
                'parse_mode' => 'HTML'
            ]);
        } else {
            tgRequest('sendMessage', [
                'chat_id' => $chatId,
                'text' => pe(PE_WARNING, '❌') . " <b>Категория не найдена.</b>\n\nДоступные категории: Еда, Транспорт, Дом, Связь, Развлечения, Одежда, Здоровье, Подарки, Образование, Семья, Другое.",
                'parse_mode' => 'HTML'
            ]);
        }
        exit;
    } elseif (preg_match('/^\/limit/i', $text)) {
        tgRequest('sendMessage', [
            'chat_id' => $chatId,
            'text' => pe(PE_FIRE, '🎯') . " <b>Установка лимита по категории</b>\n\nОтправьте команду в формате:\n<code>/limit [название категории] [сумма]</code>\n\nПример:\n<code>/limit еда 15000</code>",
            'parse_mode' => 'HTML'
        ]);
        exit;
    }

    // /help command
    if ($text === '/help') {
        $help = pe(PE_SEARCH, '📖') . " <b>Справка по трекеру расходов</b>\n\n"
            . "<b>Как записывать траты:</b>\n"
            . "Пиши текстом сумму и описание:\n"
            . "• <code>кофе 350</code> — сумма 350 ₽, категория Еда\n"
            . "• <code>350 кофе</code> — порядок слов не имеет значения\n"
            . "• <code>такси 900 работа</code> — категория определится по ключевым словам\n"
            . "• <code>обед 450.50</code> — поддерживаются копейки\n\n"
            . "<b>Управление тратами:</b>\n"
            . "После записи траты появятся кнопки: " . pe(PE_SEARCH, '✏') . " изменить категорию или " . pe(PE_WARNING, '🗑') . " удалить.\n\n"
            . "<b>Отчёты:</b>\n"
            . "/today — за сегодня\n"
            . "/week — за неделю\n"
            . "/month — за месяц\n"
            . "/export — выгрузить CSV таблицу\n\n"
            . "Все данные синхронизируются с твоей личной веб-панелью!";

        tgRequest('sendMessage', [
            'chat_id' => $chatId,
            'text' => $help,
            'parse_mode' => 'HTML'
        ]);
        exit;
    }

    // Summary commands: /today, /week, /month
    if ($text === '/today' || $text === '/week' || $text === '/month') {
        $period = substr($text, 1);
        $periodLabels = [
            'today' => 'сегодня',
            'week' => 'неделю (7 дней)',
            'month' => 'текущий месяц'
        ];
        $label = $periodLabels[$period] ?? $period;

        $summary = getSummary($userId, $period);
        $cats = getCategoryTotals($userId, $period);

        if ($summary['count'] === 0) {
            $msgEmpty = pe(PE_SPEECH, '📭') . " За <b>{$label}</b> расходов пока нет.\nНапиши, например: <code>кофе 350</code>";
            tgRequest('sendMessage', [
                'chat_id' => $chatId,
                'text' => $msgEmpty,
                'parse_mode' => 'HTML'
            ]);
            exit;
        }

        $report = pe(PE_CHART_UP, '📊') . " <b>Расходы за {$label}:</b>\n\n"
            . pe(PE_DIAMOND, '💰') . " Всего: <b>" . formatAmount($summary['total']) . "</b>\n"
            . pe(PE_SPEECH, '📝') . " Операций: <b>{$summary['count']}</b>\n"
            . pe(PE_HOURGLASS, '📅') . " В среднем: <b>" . formatAmount($summary['avg']) . "</b>/день\n\n"
            . "<b>По категориям:</b>\n";

        foreach ($cats as $c) {
            $pct = $summary['total'] > 0 ? round(($c['total'] / $summary['total']) * 100) : 0;
            $report .= "{$c['emoji']} <b>{$c['name']}</b>: " . formatAmount($c['total']) . " (<i>{$pct}%</i>)\n";
        }

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => 'Открыть графики в панели', 'web_app' => ['url' => WEBAPP_URL], 'icon_custom_emoji_id' => PE_CHART_UP]
                ]
            ]
        ];

        tgRequest('sendMessage', [
            'chat_id' => $chatId,
            'text' => $report,
            'parse_mode' => 'HTML',
            'reply_markup' => json_encode($keyboard)
        ]);
        exit;
    }

    // /recent command
    if ($text === '/recent') {
        $recent = getRecentExpenses($userId, 5);
        if (empty($recent)) {
            tgRequest('sendMessage', [
                'chat_id' => $chatId,
                'text' => pe(PE_SPEECH, '📭') . " У вас пока нет записанных трат.",
                'parse_mode' => 'HTML'
            ]);
            exit;
        }

        $resp = pe(PE_CHART_UP, '📋') . " <b>Последние 5 трат:</b>\n\n";
        foreach ($recent as $r) {
            $date = date('d.m H:i', strtotime($r['created_at']));
            $desc = !empty($r['description']) ? $r['description'] : 'Расход';
            $resp .= "{$r['category_emoji']} <b>" . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . "</b> — <b>" . formatAmount($r['amount']) . "</b>\n   <i>{$date}</i>\n";
        }

        tgRequest('sendMessage', [
            'chat_id' => $chatId,
            'text' => $resp,
            'parse_mode' => 'HTML'
        ]);
        exit;
    }

    // /export command
    if ($text === '/export') {
        $csv = getExpensesCsv($userId, 'all');
        $filename = "expenses_" . date('Y_m_d') . ".csv";
        tgSendDocument($chatId, $csv, $filename, pe(PE_CHART_UP, '📁') . " Ваши расходы в формате CSV (Excel)");
        exit;
    }

    // Handle Photo or Document (Receipt scanning)
    if (isset($msg['photo']) || isset($msg['document'])) {
        $fileId = null;
        $fileName = 'receipt.jpg';
        $mimeType = 'image/jpeg';

        if (isset($msg['photo'])) {
            $photo = end($msg['photo']); // Get highest resolution
            $fileId = $photo['file_id'];
            $mimeType = 'image/jpeg';
        } elseif (isset($msg['document'])) {
            $doc = $msg['document'];
            $mime = $doc['mime_type'] ?? '';
            // Only process PDFs or Images
            if (strpos($mime, 'image/') === 0 || $mime === 'application/pdf') {
                $fileId = $doc['file_id'];
                $mimeType = $mime;
                if ($mime === 'application/pdf') $fileName = 'receipt.pdf';
            }
        }

        if ($fileId) {
            tgRequest('sendMessage', [
                'chat_id' => $chatId,
                'text' => pe(PE_HOURGLASS, '⏳') . " <i>Сканирую файл...</i>",
                'parse_mode' => 'HTML'
            ]);

            $fileRes = tgRequest('getFile', ['file_id' => $fileId]);
            if (isset($fileRes['result']['file_path'])) {
                $filePath = $fileRes['result']['file_path'];
                $fileUrl = "https://api.telegram.org/file/bot" . BOT_TOKEN . "/" . $filePath;
                
                // Download image using cURL
                $chImg = curl_init($fileUrl);
                curl_setopt($chImg, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($chImg, CURLOPT_TIMEOUT, 15);
                $imgData = curl_exec($chImg);
                curl_close($chImg);

                if (!$imgData) {
                    tgRequest('sendMessage', [
                        'chat_id' => $chatId,
                        'text' => "Ошибка загрузки файла.",
                    ]);
                    exit;
                }

                // --- 1. GEMINI AI VISION PARSING ---
                if (defined('GEMINI_API_KEY') && !empty(GEMINI_API_KEY)) {
                    $base64 = base64_encode($imgData);
                    $prompt = "Ты умный финансовый ИИ. Проанализируй этот чек или скриншот банковского перевода (например Алиф, Dushanbe City, Сбербанк, Т-Банк). "
                            . "Найди итоговую сумму, название получателя/магазина и выбери категорию. "
                            . "Верни ТОЛЬКО валидный JSON объект (без markdown, без лишних слов) в таком формате: "
                            . "{\"amount\": 150.50, \"description\": \"Супермаркет Пайкар\", \"category_id\": 1}. "
                            . "Категории: 1:Еда/Продукты, 2:Транспорт/Такси, 3:Дом, 4:Связь/Интернет, 5:Развлечения, 6:Одежда, 7:Здоровье, 8:Подарки, 9:Образование, 10:Семья, 11:Другое. "
                            . "Если сумму найти не удалось, amount = 0.";
                    
                    $jsonText = callGeminiApi($prompt, $base64, $mimeType);
                    
                    if ($jsonText) {
                        
                        // Clean Markdown if AI returned ```json ... ```
                        $jsonText = preg_replace('/```json\s*/i', '', $jsonText);
                        $jsonText = str_replace('```', '', $jsonText);
                        
                        $parsed = json_decode(trim($jsonText), true);
                        
                        if ($parsed && isset($parsed['amount']) && $parsed['amount'] > 0) {
                            $amt = (float)$parsed['amount'];
                            $desc = !empty($parsed['description']) ? $parsed['description'] : 'Чек/Перевод';
                            $catId = (int)($parsed['category_id'] ?? 11);
                            
                            $expense = addExpense($userId, $amt, $desc, $catId);
                            
                            tgRequest('sendMessage', [
                                'chat_id' => $chatId,
                                'text' => pe(PE_COOL, '✨') . " <b>Чек распознан через AI!</b>\n\n" . getExpenseSuccessMessage($expense),
                                'parse_mode' => 'HTML',
                                'reply_markup' => json_encode(getExpenseKeyboard($expense['id']))
                            ]);
                            exit;
                        }
                    }
                }

                tgRequest('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => pe(PE_SAD, '😔') . " <b>Не удалось найти сумму в чеке или фото нечеткое.</b>\n\nПожалуйста, введите сумму сообщением вручную (например: <code>кофе 250</code>).",
                    'parse_mode' => 'HTML'
                ]);
                exit;
            }
        }
    }

    // Natural text message: parse expense
    $parsed = parseExpenseMessage($text);
    if ($parsed) {
        $categories = getCategories();
        $matchedCat = categorizeExpense($parsed['description'], $categories, $parsed['tag']);

        // IF local matching failed (returned "Другое"), use Gemini
        if ($matchedCat['name'] === 'Другое' && defined('GEMINI_API_KEY') && !empty(GEMINI_API_KEY)) {
            $analyzingMsgId = null;
            $resMsg = tgRequest('sendMessage', [
                'chat_id' => $chatId,
                'text' => pe(PE_THINKING, '🤔') . " <i>Уточняю категорию...</i>",
                'parse_mode' => 'HTML'
            ]);
            if (isset($resMsg['result']['message_id'])) {
                $analyzingMsgId = $resMsg['result']['message_id'];
            }

            $catsList = [];
            foreach ($categories as $c) {
                $catsList[] = $c['id'] . ":" . $c['name'];
            }
            $catsStr = implode(", ", $catsList);
            $prompt = "Выбери наиболее подходящую категорию для этой траты: '{$parsed['description']}'. "
                    . "Список категорий (ID:Название): {$catsStr}. "
                    . "Если текст бессмысленный (набор букв) или вообще не является покупкой/тратой, верни 0. "
                    . "В противном случае выбери наиболее подходящую. Если ничего не подходит, верни ID категории 'Другое'. "
                    . "В ответе напиши ТОЛЬКО одно число, без текста.";
            $geminiCatId = callGeminiApi($prompt);
            
            if ($analyzingMsgId) {
                tgRequest('deleteMessage', [
                    'chat_id' => $chatId,
                    'message_id' => $analyzingMsgId
                ]);
            }

            if ($geminiCatId !== null) {
                $geminiCatId = (int)trim($geminiCatId);
                if ($geminiCatId === 0) {
                    $parsed = false; // Mark as invalid to trigger the unrecognized format message
                } else {
                    foreach ($categories as $c) {
                        if ($c['id'] == $geminiCatId) {
                            $matchedCat = $c;
                            break;
                        }
                    }
                }
            }
        }

        if ($parsed) {
            $expense = addExpense($userId, $parsed['amount'], $parsed['description'], $matchedCat['id']);

            tgRequest('sendMessage', [
                'chat_id' => $chatId,
                'text' => pe(PE_PARTY, '✅') . " <b>Записано!</b>

" . getExpenseSuccessMessage($expense),
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode(getExpenseKeyboard($expense['id']))
            ]);
            exit;
        }
    }

    // Fallback: unrecognized format
    if (!empty($text)) {
        $fallback = pe(PE_SEARCH, '🤔') . " <b>Не удалось распознать трату.</b>\n\n"
            . "Напишите, например:\n"
            . "• <code>кофе 350</code>\n"
            . "• <code>такси 900 работа</code>\n"
            . "• <code>1200 продукты</code>\n"
            . "Или отправьте фото чека " . pe(PE_CHART_UP, '🧾');

        tgRequest('sendMessage', [
            'chat_id' => $chatId,
            'text' => $fallback,
            'parse_mode' => 'HTML'
        ]);
    }
}
