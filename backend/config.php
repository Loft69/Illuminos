<?php
// =========================================================
// Подключение к базе данных Illuminos (MySQL через PDO)
// =========================================================
$DB_HOST = 'localhost';
$DB_NAME = 'illuminos';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // ошибки — исключения
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // ассоциативные массивы
            PDO::ATTR_EMULATE_PREPARES   => false,                  // защита от SQL-инъекций
        ]
    );
} catch (PDOException $e) {
    die('Ошибка подключения к базе данных: ' . htmlspecialchars($e->getMessage()));
}
?>
