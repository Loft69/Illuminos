<?php
// =========================================================
// Регистрация, авторизация (сессии), выход.
// Роли пользователей: admin, subscriber, user
// =========================================================
require_once __DIR__ . '/config.php';
session_start();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// --- Выход (GET-ссылка из шапки) ---
if ($action === 'logout') {
    session_destroy();
    header('Location: /index.php');
    exit;
}

header('Content-Type: application/json; charset=utf-8');

/** Вернуть JSON с ошибкой и кодом 422 */
function fail(string $msg): void {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

// =========================================================
// Проверка занятости логина (подсказка в форме регистрации)
// =========================================================
if ($action === 'check_login') {
    $login = trim($_GET['login'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $login)) {
        echo json_encode(['ok' => false]);
        exit;
    }
    $stmt = $pdo->prepare('SELECT id FROM users WHERE login = ?');
    $stmt->execute([$login]);
    echo json_encode(['ok' => true, 'available' => !$stmt->fetch()]);
    exit;
}

// =========================================================
// РЕГИСТРАЦИЯ (Задание 5: валидность всех полей)
// =========================================================
if ($action === 'register') {
    $first      = trim($_POST['first_name'] ?? '');
    $last       = trim($_POST['last_name'] ?? '');
    $patronymic = trim($_POST['patronymic'] ?? '');
    $login      = trim($_POST['login'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';
    $password2  = $_POST['password2'] ?? '';
    $agree      = isset($_POST['agree']);

    // --- Валидация полей ---
    if ($first === '' || $last === '') fail('Укажите имя и фамилию.');
    if ($login === '' || !preg_match('/^[a-zA-Z0-9_]{3,20}$/', $login)) fail('Логин: 3–20 символов (буквы, цифры, _).');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Некорректный e-mail.');
    if (strlen($password) < 6) fail('Пароль должен быть не короче 6 символов.');
    if ($password !== $password2) fail('Пароли не совпадают.');
    if (!$agree) fail('Необходимо согласие с правилами регистрации.');

    // --- Проверка уникальности логина и e-mail ---
    $stmt = $pdo->prepare('SELECT id FROM users WHERE login = ? OR email = ?');
    $stmt->execute([$login, $email]);
    if ($stmt->fetch()) fail('Пользователь с таким логином или e-mail уже существует.');

    // --- Создание пользователя (роль по умолчанию — user) ---
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        'INSERT INTO users (first_name, last_name, patronymic, login, email, password_hash, role)
         VALUES (?, ?, ?, ?, ?, ?, "user")'
    );
    $stmt->execute([$first, $last, $patronymic, $login, $email, $hash]);

    // Автоматический вход после регистрации
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'         => (int)$pdo->lastInsertId(),
        'login'      => $login,
        'first_name' => $first,
        'role'       => 'user',
    ];
    echo json_encode(['ok' => true, 'redirect' => '/index.php']);
    exit;
}

// =========================================================
// АВТОРИЗАЦИЯ (Задание 5: через сессии, несколько ролей)
// =========================================================
if ($action === 'login') {
    $login    = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') fail('Введите логин и пароль.');

    $stmt = $pdo->prepare('SELECT * FROM users WHERE login = ?');
    $stmt->execute([$login]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        fail('Неверный логин или пароль.');
    }

    // Сессия: авторизация нескольких пользователей с разными ролями
    session_regenerate_id(true); // защита от фиксации сессии
    $_SESSION['user'] = [
        'id'         => (int)$user['id'],
        'login'      => $user['login'],
        'first_name' => $user['first_name'],
        'role'       => $user['role'], // admin | subscriber | user
    ];
    echo json_encode(['ok' => true, 'redirect' => '/index.php']);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Неизвестное действие.']);
?>
