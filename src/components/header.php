<?php
// =========================================================
// Шапка сайта: логотип, навигация, поиск, блок авторизации
// =========================================================
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../backend/config.php';

// Категории контента для выпадающего меню (Задание 3)
$genres = $pdo->query('SELECT id, name FROM genres ORDER BY name')->fetchAll();
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

// Подсветка активного пункта меню (переменные с префиксом, чтобы не затирать данные страницы)
$ioPath  = basename((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));
$ioOnIdx = ($ioPath === 'index.php' || $ioPath === '');
$ioType  = $_GET['type'] ?? '';
$ioGenre = (int)($_GET['genre'] ?? 0);
$ioHome  = $ioOnIdx && $ioType === '' && $ioGenre === 0 && $q === '';
?>
<a class="visually-hidden-focusable io-skip" href="#main">Перейти к содержимому</a>
<nav class="navbar navbar-expand-lg navbar-dark io-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand io-logo d-flex align-items-center gap-2" href="/index.php">
            <svg class="io-logo-mark" width="26" height="26" viewBox="0 0 32 32" aria-hidden="true">
                <path d="M16 16 L31 4 L31 28 Z" fill="currentColor" opacity=".28"/>
                <circle cx="11" cy="16" r="8" fill="none" stroke="currentColor" stroke-width="3"/>
                <circle cx="11" cy="16" r="2.6" fill="currentColor"/>
            </svg>
            <span>ILLUMINOS</span>
        </a>
        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#ioNav" aria-controls="ioNav" aria-expanded="false" aria-label="Меню">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="ioNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link<?= $ioHome ? ' active' : '' ?>" <?= $ioHome ? 'aria-current="page"' : '' ?> href="/index.php">Главная</a></li>
                <li class="nav-item"><a class="nav-link<?= ($ioOnIdx && $ioType === 'film') ? ' active' : '' ?>" href="/index.php?type=film">Фильмы</a></li>
                <li class="nav-item"><a class="nav-link<?= ($ioOnIdx && $ioType === 'serial') ? ' active' : '' ?>" href="/index.php?type=serial">Сериалы</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle<?= ($ioOnIdx && $ioGenre > 0) ? ' active' : '' ?>" href="#" data-bs-toggle="dropdown" aria-expanded="false">Категории</a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <?php foreach ($genres as $g): ?>
                            <li><a class="dropdown-item<?= ($ioOnIdx && $ioGenre === (int)$g['id']) ? ' active' : '' ?>" href="/index.php?genre=<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <?php if (isset($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link text-warning fw-bold" href="/admin.php">Админ-панель</a></li>
                <?php endif; ?>
            </ul>
            <!-- Поиск по фильмам/сериалам (Задание 3) + подсказки при вводе -->
            <form class="d-flex me-2 io-search-form" action="/index.php" method="get" role="search">
                <div class="io-search me-2">
                    <input class="form-control form-control-sm" type="search" name="q" autocomplete="off"
                           value="<?= htmlspecialchars($q) ?>" placeholder="Поиск по фильмам и сериалам…"
                           aria-label="Поиск по фильмам и сериалам" aria-autocomplete="list"
                           aria-controls="ioSuggest" aria-expanded="false">
                    <ul class="io-suggest" id="ioSuggest" role="listbox" hidden></ul>
                </div>
                <button class="btn btn-io btn-sm" type="submit">Найти</button>
            </form>
            <?php if (isset($_SESSION['user'])): ?>
                <a href="/cabinet.php" class="btn btn-outline-light btn-sm ms-1 mt-2 mt-lg-0">
                    <?= htmlspecialchars($_SESSION['user']['first_name']) ?>
                    (<?= htmlspecialchars($_SESSION['user']['role']) ?>)
                </a>
                <a href="/backend/auth.php?action=logout" class="btn btn-outline-danger btn-sm ms-1 mt-2 mt-lg-0">Выход</a>
            <?php else: ?>
                <a href="/login.php" class="btn btn-outline-light btn-sm ms-1 mt-2 mt-lg-0">Вход</a>
                <a href="/register.php" class="btn btn-io btn-sm ms-1 mt-2 mt-lg-0">Регистрация</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
