<?php
// =========================================================
// Форма авторизации: логин + пароль (сессии, роли)
// =========================================================
session_start();
if (isset($_SESSION['user'])) { header('Location: /index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход — Illuminos</title>
    <?php require __DIR__ . '/src/components/head-assets.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
<?php require __DIR__ . '/src/components/header.php'; ?>

<main class="container flex-grow-1" id="main">
    <form id="login-form" class="io-form" novalidate>
        <h1 class="io-form-title mb-3 text-center">Вход</h1>
        <div id="form-errors" class="io-error mb-2" role="alert"></div>
        <input class="form-control" name="login" placeholder="Логин" aria-label="Логин" autocomplete="username" required autofocus>
        <div class="io-pass mt-2">
            <input class="form-control" type="password" name="password" placeholder="Пароль" aria-label="Пароль" autocomplete="current-password" required>
            <button type="button" class="io-pass-toggle" data-toggle-pass aria-pressed="false" aria-label="Показать пароль">
                <svg class="i-on" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="i-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.9 17.9A10.9 10.9 0 0112 19c-7 0-11-7-11-7a19 19 0 015.1-5.9M9.9 5.1A10.4 10.4 0 0112 5c7 0 11 7 11 7a19 19 0 01-2.2 3.2M1 1l22 22"/></svg>
            </button>
        </div>
        <button class="btn btn-io w-100 mt-3" type="submit">Войти</button>
        <p class="text-center mt-3 mb-0"><a href="/register.php" class="text-secondary">Нет аккаунта? Зарегистрироваться</a></p>
    </form>
</main>

<?php require __DIR__ . '/src/components/footer.php'; ?>
