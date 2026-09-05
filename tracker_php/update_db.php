<?php
require_once __DIR__ . '/config.php';
try {
    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $db->exec("CREATE TABLE IF NOT EXISTS category_limits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        category_id INTEGER NOT NULL,
        limit_amount REAL NOT NULL,
        UNIQUE(user_id, category_id)
    )");
    echo "Table category_limits created or already exists.\n";

    $db->exec("CREATE TABLE IF NOT EXISTS users_settings (
        user_id INTEGER PRIMARY KEY,
        group_id INTEGER,
        currency TEXT DEFAULT '₽'
    )");
    echo "Table users_settings created or already exists.\n";
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
