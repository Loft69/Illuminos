<?php
// =========================================================
// Форма регистрации: имя, фамилия, отчество, логин, e-mail,
// пароль, повтор пароля, согласие с правилами.
// Валидация — на JS без перезагрузки страницы.
// =========================================================
session_start();
if (isset($_SESSION['user'])) { header('Location: /index.php'); exit; }

$eye = '<svg class="i-on" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>'
     . '<svg class="i-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.9 17.9A10.9 10.9 0 0112 19c-7 0-11-7-11-7a19 19 0 015.1-5.9M9.9 5.1A10.4 10.4 0 0112 5c7 0 11 7 11 7a19 19 0 01-2.2 3.2M1 1l22 22"/></svg>';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Регистрация — Illuminos</title>
    <?php require __DIR__ . '/src/components/head-assets.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
<?php require __DIR__ . '/src/components/header.php'; ?>

<main class="container flex-grow-1" id="main">
    <form id="register-form" class="io-form" novalidate>
        <h1 class="io-form-title mb-3 text-center">Регистрация</h1>
        <div id="form-errors" class="io-error mb-2" role="alert"></div>
        <div class="row g-2">
            <div class="col-md-6">
                <input class="form-control" name="first_name" placeholder="Имя *" aria-label="Имя" autocomplete="given-name" required>
            </div>
            <div class="col-md-6">
                <input class="form-control" name="last_name" placeholder="Фамилия *" aria-label="Фамилия" autocomplete="family-name" required>
            </div>
        </div>
        <input class="form-control mt-2" name="patronymic" placeholder="Отчество" aria-label="Отчество" autocomplete="additional-name">
        <input class="form-control mt-2" name="login" placeholder="Логин *" aria-label="Логин" autocomplete="username" required>
        <input class="form-control mt-2" type="email" name="email" placeholder="E-mail *" aria-label="E-mail" autocomplete="email" required>
        <div class="io-pass mt-2">
            <input class="form-control" type="password" name="password" placeholder="Пароль (мин. 6 символов) *" aria-label="Пароль" autocomplete="new-password" required>
            <button type="button" class="io-pass-toggle" data-toggle-pass aria-pressed="false" aria-label="Показать пароль"><?= $eye ?></button>
        </div>
        <div class="io-strength" data-strength data-level="0" aria-hidden="true"><i></i><i></i><i></i><i></i><span></span></div>
        <div class="io-pass mt-2">
            <input class="form-control" type="password" name="password2" placeholder="Повторите пароль *" aria-label="Повторите пароль" autocomplete="new-password" required>
            <button type="button" class="io-pass-toggle" data-toggle-pass aria-pressed="false" aria-label="Показать пароль"><?= $eye ?></button>
        </div>
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="agree" id="agree">
            <label class="form-check-label" for="agree">Согласен с правилами регистрации *</label>
        </div>
        <button class="btn btn-io w-100 mt-3" type="submit">Зарегистрироваться</button>
        <p class="text-center mt-3 mb-0"><a href="/login.php" class="text-secondary">Уже есть аккаунт? Войти</a></p>
    </form>
</main>

<?php require __DIR__ . '/src/components/footer.php'; ?>
