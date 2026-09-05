<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Telegram-Init-Data');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

try {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['ok' => false, 'error' => 'DB connection failed']);
    exit;
}

function sendJson($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function authenticateTelegramUser() {
    $initData = $_SERVER['HTTP_X_TELEGRAM_INIT_DATA'] ?? $_GET['init_data'] ?? '';
    if (empty($initData)) {
        if (DEBUG_MODE) {
            return ['id' => 123456789, 'first_name' => 'Test User'];
        }
        sendJson(['ok' => false, 'error' => 'No init data']);
    }

    parse_str($initData, $parsed);
    if (!isset($parsed['hash'])) {
        if (DEBUG_MODE) {
            return ['id' => 123456789, 'first_name' => 'Test User'];
        }
        sendJson(['ok' => false, 'error' => 'Invalid init data']);
    }

    $hash = $parsed['hash'];
    unset($parsed['hash']);
    ksort($parsed);

    $dataCheckString = [];
    foreach ($parsed as $key => $value) {
        $dataCheckString[] = "$key=$value";
    }
    $dataCheckString = implode("\n", $dataCheckString);
    $secretKey = hash_hmac('sha256', BOT_TOKEN, 'WebAppData', true);
    $calculatedHash = bin2hex(hash_hmac('sha256', $dataCheckString, $secretKey, true));

    if (!hash_equals($calculatedHash, $hash)) {
        if (!DEBUG_MODE) {
            sendJson(['ok' => false, 'error' => 'Data is NOT from Telegram']);
        }
    }

    return json_decode($parsed['user'] ?? '{}', true) ?: ['id' => 123456789, 'first_name' => 'Test User'];
}

$user = authenticateTelegramUser();
$userId = $user['id'] ?? 123456789;

function getUserSettings($userId) {
    global $db;
    $stmt = $db->prepare("SELECT group_id, currency FROM users_settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($res) {
        return [
            'group_id' => $res['group_id'] ?: $userId,
            'currency' => $res['currency'] ?: '₽'
        ];
    }
    $stmt = $db->prepare("INSERT INTO users_settings (user_id, group_id, currency) VALUES (?, ?, '₽')");
    $stmt->execute([$userId, $userId]);
    return ['group_id' => $userId, 'currency' => '₽'];
}

$settings = getUserSettings($userId);
$groupId = $settings['group_id'];
$currency = $settings['currency'];

$action = $_GET['action'] ?? '';
$period = $_GET['period'] ?? 'month';

function getPeriodCondition($period) {
    switch ($period) {
        case 'day':
        case 'today':
            return "AND date(e.created_at) = date('now', 'localtime')";
        case 'week':
            return "AND date(e.created_at) >= date('now', 'weekday 0', '-7 days', 'localtime')";
        case 'last_month':
            return "AND strftime('%Y-%m', e.created_at) = strftime('%Y-%m', 'now', '-1 month', 'localtime')";
        case 'year':
            return "AND strftime('%Y', e.created_at) = strftime('%Y', 'now', 'localtime')";
        case 'all':
            return "";
        case 'month':
        default:
            return "AND strftime('%Y-%m', e.created_at) = strftime('%Y-%m', 'now', 'localtime')";
    }
}

$dateCondition = getPeriodCondition($period);

// Read JSON input for POST/PUT
$jsonInput = json_decode(file_get_contents("php://input"), true) ?: [];

switch ($action) {
    /* ── 1. SUMMARY ─────────────────────────────────────────────────── */
    case 'summary':
        $stmtStats = $db->prepare("SELECT SUM(amount) as total, COUNT(id) as count 
                                   FROM expenses e 
                                   WHERE user_id IN (SELECT user_id FROM users_settings WHERE group_id = ? OR user_id = ?) $dateCondition");
        $stmtStats->execute([$groupId, $groupId]);
        $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
        $total = (float)($stats['total'] ?? 0);
        $count = (int)($stats['count'] ?? 0);

        // Days in period for average
        $days = 30;
        if ($period === 'day' || $period === 'today') $days = 1;
        elseif ($period === 'week') $days = 7;
        elseif ($period === 'year') $days = 365;
        $avgPerDay = $total / max(1, $days);

        // Category limits
        $stmtLimits = $db->prepare("SELECT category_id, limit_amount FROM category_limits WHERE user_id IN (SELECT user_id FROM users_settings WHERE group_id = ? OR user_id = ?)");
        $stmtLimits->execute([$groupId, $groupId]);
        $limits = [];
        while ($row = $stmtLimits->fetch(PDO::FETCH_ASSOC)) {
            $limits[$row['category_id']] = (float)$row['limit_amount'];
        }

        sendJson([
            'ok' => true,
            'data' => [
                'total' => $total,
                'count' => $count,
                'avg' => $count > 0 ? $total / $count : 0,
                'average_per_day' => $avgPerDay,
                'currency' => $currency,
                'limits' => $limits
            ]
        ]);
        break;

    /* ── 2. CATEGORIES BREAKDOWN (FOR STATS & HOME) ─────────────────── */
    case 'categories':
        $stmtStats = $db->prepare("SELECT SUM(amount) as total FROM expenses e WHERE user_id IN (SELECT user_id FROM users_settings WHERE group_id = ? OR user_id = ?) $dateCondition");
        $stmtStats->execute([$groupId, $groupId]);
        $grandTotal = (float)($stmtStats->fetchColumn() ?: 0);

        $stmt = $db->prepare("SELECT c.id, c.name, c.emoji, SUM(e.amount) as total, COUNT(e.id) as count 
                              FROM expenses e 
                              JOIN categories c ON e.category_id = c.id 
                              WHERE e.user_id IN (SELECT user_id FROM users_settings WHERE group_id = ? OR user_id = ?) $dateCondition 
                              GROUP BY c.id 
                              ORDER BY total DESC");
        $stmt->execute([$groupId, $groupId]);
        $cats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($cats as &$c) {
            $c['total'] = (float)$c['total'];
            $c['count'] = (int)$c['count'];
            $c['percent'] = $grandTotal > 0 ? round(($c['total'] / $grandTotal) * 100, 1) : 0;
        }

        sendJson(['ok' => true, 'data' => $cats]);
        break;

    /* ── 3. DAILY BREAKDOWN (FOR STATS CHART) ────────────────────────── */
    case 'daily':
        $stmt = $db->prepare("SELECT date(e.created_at) as date, SUM(e.amount) as total 
                              FROM expenses e 
                              WHERE e.user_id IN (SELECT user_id FROM users_settings WHERE group_id = ? OR user_id = ?) $dateCondition 
                              GROUP BY date 
                              ORDER BY date ASC");
        $stmt->execute([$groupId, $groupId]);
        $daily = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($daily as &$d) {
            $d['total'] = (float)$d['total'];
        }
        sendJson(['ok' => true, 'data' => $daily]);
        break;

    /* ── 4. CATEGORY LIST (FOR ADD/EDIT CHIPS) ───────────────────────── */
    case 'category_list':
    case 'category-list':
        $stmt = $db->query("SELECT id, name, emoji FROM categories ORDER BY id ASC");
        sendJson(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    /* ── 5. EXPENSES LIST & SEARCH ──────────────────────────────────── */
    case 'expenses':
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $search = trim($_GET['search'] ?? '');
        $searchCondition = '';
        $params = [$groupId, $groupId];

        if (!empty($search)) {
            $searchCondition = "AND (e.description LIKE ? OR c.name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $stmt = $db->prepare("
            SELECT e.id, e.amount, e.description, e.created_at, e.created_at as date, e.category_id, c.name as category_name, c.emoji 
            FROM expenses e 
            JOIN categories c ON e.category_id = c.id 
            WHERE e.user_id IN (SELECT user_id FROM users_settings WHERE group_id = ? OR user_id = ?) $dateCondition $searchCondition 
            ORDER BY e.created_at DESC 
            LIMIT $limit
        ");
        $stmt->execute($params);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($expenses as &$exp) {
            $exp['amount'] = (float)$exp['amount'];
        }
        sendJson(['ok' => true, 'data' => $expenses]);
        break;

    /* ── 6. ADD EXPENSE ─────────────────────────────────────────────── */
    case 'add_expense':
        $amount = (float)($jsonInput['amount'] ?? $_POST['amount'] ?? 0);
        $catId = (int)($jsonInput['category_id'] ?? $_POST['category_id'] ?? 11);
        $desc = trim($jsonInput['description'] ?? $_POST['description'] ?? '');

        if ($amount <= 0) {
            sendJson(['ok' => false, 'error' => 'Invalid amount']);
        }

        $stmt = $db->prepare("INSERT INTO expenses (user_id, amount, description, category_id, created_at, updated_at) VALUES (?, ?, ?, ?, datetime('now', 'localtime'), datetime('now', 'localtime'))");
        $stmt->execute([$userId, $amount, $desc, $catId]);
        $newId = (int)$db->lastInsertId();

        sendJson(['ok' => true, 'data' => ['id' => $newId, 'amount' => $amount, 'description' => $desc, 'category_id' => $catId]]);
        break;

    /* ── 7. EDIT EXPENSE ────────────────────────────────────────────── */
    case 'edit_expense':
    case 'expenses-edit':
        $id = (int)($jsonInput['id'] ?? $_GET['id'] ?? 0);
        $amount = (float)($jsonInput['amount'] ?? 0);
        $desc = trim($jsonInput['description'] ?? '');
        $catId = (int)($jsonInput['category_id'] ?? 11);

        if ($id <= 0 || $amount <= 0) {
            sendJson(['ok' => false, 'error' => 'Invalid parameters']);
        }

        $stmt = $db->prepare("UPDATE expenses SET amount = ?, description = ?, category_id = ? WHERE id = ? AND user_id IN (SELECT user_id FROM users_settings WHERE group_id = ? OR user_id = ?)");
        $stmt->execute([$amount, $desc, $catId, $id, $groupId, $groupId]);
        sendJson(['ok' => true]);
        break;

    /* ── 8. DELETE EXPENSE ──────────────────────────────────────────── */
    case 'delete_expense':
    case 'expenses-delete':
        $id = (int)($jsonInput['id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            sendJson(['ok' => false, 'error' => 'Invalid ID']);
        }

        $stmt = $db->prepare("DELETE FROM expenses WHERE id = ? AND user_id IN (SELECT user_id FROM users_settings WHERE group_id = ? OR user_id = ?)");
        $stmt->execute([$id, $groupId, $groupId]);
        sendJson(['ok' => true]);
        break;

    /* ── 9. SET CURRENCY ────────────────────────────────────────────── */
    case 'set-currency':
        $curr = $jsonInput['curr'] ?? $_GET['curr'] ?? '₽';
        $stmt = $db->prepare("UPDATE users_settings SET currency = ? WHERE user_id = ?");
        $stmt->execute([$curr, $userId]);
        sendJson(['ok' => true, 'currency' => $curr]);
        break;

    /* ── 10. SEND INVITE ────────────────────────────────────────────── */
    case 'send-invite':
        $botUser = json_decode(@file_get_contents("https://api.telegram.org/bot" . BOT_TOKEN . "/getMe"), true);
        $botUsername = $botUser['result']['username'] ?? 'TajUC_bot';
        $inviteLink = "https://t.me/$botUsername?start=invite_$userId";
        
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
        
        $premiumParty = '<tg-emoji emoji-id="5436040291507247633">🤝</tg-emoji>';

        $data = [
            'chat_id' => $userId,
            'text' => "$premiumParty <b>Общий бюджет на двоих</b>\n\nПерешлите эту ссылку партнеру:\n\n👉 $inviteLink\n\nКогда он перейдет по ней, ваш бюджет объединится!",
            'parse_mode' => 'HTML'
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_exec($ch);
        curl_close($ch);
        
        sendJson(['ok' => true, 'invite_link' => $inviteLink]);
        break;

    /* ── 10.1 SETTINGS & UNLINK PARTNER ────────────────────────────────── */
    case 'settings':
    case 'get_settings':
        $partnerId = null;
        if ((int)$groupId !== (int)$userId && $groupId > 0) {
            $partnerId = (int)$groupId;
        } else {
            $stmtOther = $db->prepare("SELECT user_id FROM users_settings WHERE group_id = ? AND user_id != ? LIMIT 1");
            $stmtOther->execute([$userId, $userId]);
            $otherRow = $stmtOther->fetch(PDO::FETCH_ASSOC);
            if ($otherRow) {
                $partnerId = (int)$otherRow['user_id'];
            }
        }

        $isShared = ($partnerId !== null && $partnerId > 0);
        $partnerInfo = null;

        if ($isShared) {
            $stmtP = $db->prepare("SELECT tg_id, first_name, username FROM users WHERE tg_id = ?");
            $stmtP->execute([$partnerId]);
            $pUser = $stmtP->fetch(PDO::FETCH_ASSOC);
            if ($pUser) {
                $partnerInfo = [
                    'id' => (int)$pUser['tg_id'],
                    'first_name' => $pUser['first_name'],
                    'username' => $pUser['username'] ? '@' . $pUser['username'] : ''
                ];
            } else {
                $partnerInfo = [
                    'id' => $partnerId,
                    'first_name' => 'Партнер (ID: ' . $partnerId . ')',
                    'username' => ''
                ];
            }
        }

        sendJson([
            'ok' => true,
            'currency' => $currency,
            'group_id' => (int)$groupId,
            'is_shared' => $isShared,
            'partner' => $partnerInfo
        ]);
        break;

    case 'unlink_partner':
    case 'remove_partner':
        $stmt1 = $db->prepare("
            UPDATE users_settings
            SET group_id = user_id
            WHERE user_id = :uid
               OR group_id = :uid
               OR group_id = (SELECT group_id FROM users_settings WHERE user_id = :uid)
        ");
        $stmt1->execute([':uid' => $userId]);
        sendJson(['ok' => true]);
        break;

    /* ── 11. EXPORT CSV ─────────────────────────────────────────────── */
    case 'export_chat':
    case 'export_csv_chat':
        $stmt = $db->prepare("
            SELECT e.created_at, c.name, e.amount, e.description 
            FROM expenses e 
            JOIN categories c ON e.category_id = c.id 
            WHERE e.user_id IN (SELECT user_id FROM users_settings WHERE group_id = ? OR user_id = ?) $dateCondition 
            ORDER BY e.created_at DESC
        ");
        $stmt->execute([$groupId, $groupId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tempFile = tempnam(sys_get_temp_dir(), 'csv_') . '.csv';
        $output = fopen($tempFile, 'w');
        // UTF-8 BOM helps Excel recognize encoding
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Дата', 'Категория', 'Сумма', 'Валюта', 'Описание'], ';');
        foreach ($rows as $row) {
            fputcsv($output, [
                date('d.m.Y', strtotime($row['created_at'])), 
                $row['name'], 
                str_replace('.', ',', $row['amount']), 
                $currency, 
                $row['description']
            ], ';');
        }
        fclose($output);

        // Send file to user chat via Telegram Bot API
        $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendDocument";
        $cfile = new CURLFile($tempFile, 'text/csv', 'expenses_' . date('Y-m-d') . '.csv');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'chat_id' => $userId,
            'caption' => "📊 <b>Экспорт расходов</b> (" . date('d.m.Y') . ")\nВсего записей: " . count($rows),
            'parse_mode' => 'HTML',
            'document' => $cfile
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        @unlink($tempFile);

        sendJson(['ok' => true, 'count' => count($rows)]);
        break;

    case 'export':
    case 'export_csv':
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=expenses_' . date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        // UTF-8 BOM helps Excel recognize encoding
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, ['Дата', 'Категория', 'Сумма', 'Валюта', 'Описание'], ';');
        
        $stmt = $db->prepare("
            SELECT e.created_at, c.name, e.amount, e.description 
            FROM expenses e 
            JOIN categories c ON e.category_id = c.id 
            WHERE e.user_id IN (SELECT user_id FROM users_settings WHERE group_id = ? OR user_id = ?) $dateCondition 
            ORDER BY e.created_at DESC
        ");
        $stmt->execute([$groupId, $groupId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                date('d.m.Y', strtotime($row['created_at'])), 
                $row['name'], 
                str_replace('.', ',', $row['amount']), 
                $currency, 
                $row['description']
            ], ';');
        }
        fclose($output);
        exit;

    default:
        sendJson(['ok' => false, 'error' => 'Unknown action']);
}
