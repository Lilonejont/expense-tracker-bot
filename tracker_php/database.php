<?php
// database.php - SQLite database management with PDO
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/categorizer.php';

function getDb() {
    static $db = null;
    if ($db === null) {
        $db = new PDO('sqlite:' . DB_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->exec("PRAGMA journal_mode = WAL;");
        $db->exec("PRAGMA synchronous = NORMAL;");
        $db->exec("PRAGMA foreign_keys = ON;");
        $db->exec("PRAGMA busy_timeout = 5000;");
        initTables($db);
    }
    return $db;
}

function initTables($db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tg_id INTEGER UNIQUE NOT NULL,
            first_name TEXT NOT NULL DEFAULT '',
            username TEXT DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            emoji TEXT NOT NULL DEFAULT '❓',
            keywords TEXT NOT NULL DEFAULT '[]'
        );

        CREATE TABLE IF NOT EXISTS expenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            amount REAL NOT NULL CHECK(amount > 0),
            description TEXT NOT NULL DEFAULT '',
            category_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (category_id) REFERENCES categories(id)
        );

        CREATE TABLE IF NOT EXISTS users_settings (
            user_id INTEGER PRIMARY KEY,
            group_id INTEGER,
            currency TEXT DEFAULT '₽'
        );

        CREATE TABLE IF NOT EXISTS category_limits (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            category_id INTEGER NOT NULL,
            limit_amount REAL NOT NULL,
            UNIQUE(user_id, category_id)
        );

        CREATE INDEX IF NOT EXISTS idx_expenses_user_date ON expenses(user_id, created_at DESC);
        CREATE INDEX IF NOT EXISTS idx_expenses_user_cat ON expenses(user_id, category_id);
    ");

    seedCategories($db);
}

function getUserSettings($userId) {
    $db = getDb();
    $stmt = $db->prepare("SELECT group_id, currency FROM users_settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $res = $stmt->fetch();
    if ($res) {
        return [
            'group_id' => $res['group_id'] ?: $userId,
            'currency' => $res['currency'] ?: '₽'
        ];
    }
    $stmt = $db->prepare("INSERT OR REPLACE INTO users_settings (user_id, group_id, currency) VALUES (?, ?, '₽')");
    $stmt->execute([$userId, $userId]);
    return ['group_id' => $userId, 'currency' => '₽'];
}

function seedCategories($db) {
    $insert = $db->prepare("
        INSERT INTO categories (name, emoji, keywords) VALUES (:name, :emoji, :keywords)
        ON CONFLICT(name) DO UPDATE SET
            emoji = excluded.emoji,
            keywords = excluded.keywords
    ");
    $categories = getDefaultCategories();

    $db->beginTransaction();
    foreach ($categories as $cat) {
        $insert->execute([
            ':name' => $cat['name'],
            ':emoji' => $cat['emoji'],
            ':keywords' => json_encode($cat['keywords'], JSON_UNESCAPED_UNICODE)
        ]);
    }
    $db->commit();
}

function ensureUser($tgId, $firstName, $username = '') {
    $db = getDb();
    $stmt = $db->prepare("
        INSERT INTO users (tg_id, first_name, username)
        VALUES (:tg_id, :first_name, :username)
        ON CONFLICT(tg_id) DO UPDATE SET
            first_name = excluded.first_name,
            username = excluded.username
    ");
    $stmt->execute([
        ':tg_id' => $tgId,
        ':first_name' => $firstName,
        ':username' => $username ?? ''
    ]);
}

function getCategories() {
    $db = getDb();
    $stmt = $db->query("SELECT id, name, emoji, keywords FROM categories ORDER BY id ASC");
    $rows = $stmt->fetchAll();
    $categories = [];
    foreach ($rows as $r) {
        $categories[] = [
            'id' => (int)$r['id'],
            'name' => $r['name'],
            'emoji' => $r['emoji'],
            'keywords' => json_decode($r['keywords'], true) ?: []
        ];
    }
    return $categories;
}

function getCategoryById($id) {
    $db = getDb();
    $stmt = $db->prepare("SELECT id, name, emoji, keywords FROM categories WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) return null;
    return [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'emoji' => $row['emoji'],
        'keywords' => json_decode($row['keywords'], true) ?: []
    ];
}

function addExpense($tgId, $amount, $description, $categoryId) {
    $db = getDb();
    $stmt = $db->prepare("
        INSERT INTO expenses (user_id, amount, description, category_id, created_at, updated_at)
        VALUES (:user_id, :amount, :description, :category_id, datetime('now', 'localtime'), datetime('now', 'localtime'))
    ");
    $stmt->execute([
        ':user_id' => $tgId,
        ':amount' => $amount,
        ':description' => $description,
        ':category_id' => $categoryId
    ]);
    $id = $db->lastInsertId();
    return getExpenseById($id, $tgId);
}

function getExpenseById($id, $tgId) {
    $db = getDb();
    $stmt = $db->prepare("
        SELECT e.id, e.user_id, e.amount, e.description, e.category_id,
               c.name as category_name, c.emoji as category_emoji,
               e.created_at, e.updated_at
        FROM expenses e
        JOIN categories c ON e.category_id = c.id
        WHERE e.id = :id AND e.user_id = :user_id
    ");
    $stmt->execute([':id' => $id, ':user_id' => $tgId]);
    $row = $stmt->fetch();
    if (!$row) return null;
    return [
        'id' => (int)$row['id'],
        'user_id' => (int)$row['user_id'],
        'amount' => (float)$row['amount'],
        'description' => $row['description'],
        'category_id' => (int)$row['category_id'],
        'category_name' => $row['category_name'],
        'category_emoji' => $row['category_emoji'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at']
    ];
}

function updateExpense($id, $tgId, $updates) {
    $db = getDb();
    $fields = ["updated_at = datetime('now', 'localtime')"];
    $params = [':id' => $id, ':user_id' => $tgId];

    if (isset($updates['amount'])) {
        $fields[] = "amount = :amount";
        $params[':amount'] = (float)$updates['amount'];
    }
    if (isset($updates['description'])) {
        $fields[] = "description = :description";
        $params[':description'] = $updates['description'];
    }
    if (isset($updates['category_id'])) {
        $fields[] = "category_id = :category_id";
        $params[':category_id'] = (int)$updates['category_id'];
    }

    $sql = "UPDATE expenses SET " . implode(', ', $fields) . " WHERE id = :id AND user_id = :user_id";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return getExpenseById($id, $tgId);
}

function deleteExpense($id, $tgId) {
    $db = getDb();
    $stmt = $db->prepare("DELETE FROM expenses WHERE id = :id AND user_id = :user_id");
    $stmt->execute([':id' => $id, ':user_id' => $tgId]);
    return $stmt->rowCount() > 0;
}

function buildPeriodSql($period) {
    switch ($period) {
        case 'today':
            return "AND date(e.created_at) = date('now', 'localtime')";
        case 'week':
            return "AND e.created_at >= datetime('now', 'localtime', '-7 days')";
        case 'month':
            return "AND e.created_at >= datetime('now', 'localtime', 'start of month')";
        case 'last_month':
            return "AND e.created_at >= datetime('now', 'localtime', 'start of month', '-1 month') AND e.created_at < datetime('now', 'localtime', 'start of month')";
        case 'year':
            return "AND e.created_at >= datetime('now', 'localtime', 'start of year')";
        case 'all':
        default:
            return "";
    }
}

function getExpenses($tgId, $period = 'month', $search = '') {
    $db = getDb();
    $periodClause = buildPeriodSql($period);
    $searchClause = "";
    $params = [':user_id' => $tgId];

    if (!empty($search)) {
        $searchClause = "AND (e.description LIKE :search OR c.name LIKE :search)";
        $params[':search'] = '%' . trim($search) . '%';
    }

    $sql = "
        SELECT e.id, e.user_id, e.amount, e.description, e.category_id,
               c.name as category_name, c.emoji as category_emoji,
               e.created_at, e.updated_at
        FROM expenses e
        JOIN categories c ON e.category_id = c.id
        WHERE e.user_id = :user_id $periodClause $searchClause
        ORDER BY e.created_at DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $result = [];
    foreach ($rows as $r) {
        $result[] = [
            'id' => (int)$r['id'],
            'user_id' => (int)$r['user_id'],
            'amount' => (float)$r['amount'],
            'description' => $r['description'],
            'category_id' => (int)$r['category_id'],
            'category_name' => $r['category_name'],
            'category_emoji' => $r['category_emoji'],
            'created_at' => $r['created_at'],
            'updated_at' => $r['updated_at']
        ];
    }
    return $result;
}

function getRecentExpenses($tgId, $limit = 5) {
    $db = getDb();
    $stmt = $db->prepare("
        SELECT e.id, e.user_id, e.amount, e.description, e.category_id,
               c.name as category_name, c.emoji as category_emoji,
               e.created_at, e.updated_at
        FROM expenses e
        JOIN categories c ON e.category_id = c.id
        WHERE e.user_id = :user_id
        ORDER BY e.created_at DESC
        LIMIT :lim
    ");
    $stmt->bindValue(':user_id', $tgId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getSummary($tgId, $period = 'month') {
    $db = getDb();
    $periodClause = buildPeriodSql($period);
    $sql = "
        SELECT COALESCE(SUM(e.amount), 0) as total, COUNT(e.id) as count
        FROM expenses e
        WHERE e.user_id = :user_id $periodClause
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $tgId]);
    $row = $stmt->fetch();

    $total = (float)$row['total'];
    $count = (int)$row['count'];

    $days = 1;
    if ($period === 'today') {
        $days = 1;
    } elseif ($period === 'week') {
        $days = 7;
    } elseif ($period === 'month') {
        $days = (int)date('j');
    } else {
        $days = max(1, $count > 0 ? 30 : 1);
    }

    $avg = $days > 0 ? round($total / $days, 2) : 0;

    return [
        'total' => $total,
        'count' => $count,
        'avg' => $avg,
        'average_per_day' => $avg
    ];
}

function getCategoryTotals($tgId, $period = 'month') {
    $db = getDb();
    $periodClause = buildPeriodSql($period);
    $sql = "
        SELECT c.id as category_id, c.name, c.emoji,
               SUM(e.amount) as total, COUNT(e.id) as count
        FROM expenses e
        JOIN categories c ON e.category_id = c.id
        WHERE e.user_id = :user_id $periodClause
        GROUP BY c.id
        ORDER BY total DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $tgId]);
    $rows = $stmt->fetchAll();

    $totals = [];
    foreach ($rows as $r) {
        $totals[] = [
            'category_id' => (int)$r['category_id'],
            'name' => $r['name'],
            'emoji' => $r['emoji'],
            'total' => (float)$r['total'],
            'count' => (int)$r['count']
        ];
    }
    return $totals;
}

function getDailyTotals($tgId, $period = 'month') {
    $db = getDb();
    $periodClause = buildPeriodSql($period);
    $sql = "
        SELECT date(e.created_at) as date, SUM(e.amount) as total
        FROM expenses e
        WHERE e.user_id = :user_id $periodClause
        GROUP BY date(e.created_at)
        ORDER BY date ASC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute([':user_id' => $tgId]);
    $rows = $stmt->fetchAll();

    $daily = [];
    foreach ($rows as $r) {
        $daily[] = [
            'date' => $r['date'],
            'total' => (float)$r['total']
        ];
    }
    return $daily;
}

function getExpensesCsv($tgId, $period = 'month') {
    $expenses = getExpenses($tgId, $period);
    $settings = getUserSettings($tgId);
    $currency = $settings['currency'] ?? '₽';
    
    $fp = fopen('php://temp', 'r+');
    fwrite($fp, "\xEF\xBB\xBF"); // UTF-8 BOM
    fputcsv($fp, ['Дата', 'Категория', 'Сумма', 'Валюта', 'Описание'], ';');
    
    foreach ($expenses as $e) {
        $catName = $e['category_emoji'] . ' ' . $e['category_name'];
        fputcsv($fp, [
            date('d.m.Y', strtotime($e['created_at'])), 
            $catName, 
            str_replace('.', ',', $e['amount']), 
            $currency, 
            $e['description']
        ], ';');
    }
    
    rewind($fp);
    $output = stream_get_contents($fp);
    fclose($fp);
    
    return $output;
}
