<?php
// =========================================================
// Шапка сайта: логотип, навигация, поиск, блок авторизации
// =========================================================
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../backend/config.php';
require_once __DIR__ . '/helpers.php';

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
            <span class="io-logo-icon">▶</span>
            <span>Illuminos</span>
        </a>
        <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#ioNav" aria-controls="ioNav" aria-expanded="false" aria-label="Меню">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="ioNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link<?= $ioHome ? ' active' : '' ?>" <?= $ioHome ? 'aria-current="page"' : '' ?> href="/index.php">Главная</a></li>
                <li class="nav-item"><a class="nav-link<?= ($ioPath === 'films.php') ? ' active' : '' ?>" href="/films.php">Фильмы</a></li>
                <li class="nav-item"><a class="nav-link<?= ($ioPath === 'serials.php') ? ' active' : '' ?>" href="/serials.php">Сериалы</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle<?= ($ioOnIdx && $ioGenre > 0) ? ' active' : '' ?>" href="#" data-bs-toggle="dropdown" aria-expanded="false">Категории</a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <?php foreach ($genres as $g): ?>
                            <li><a class="dropdown-item<?= ($ioOnIdx && $ioGenre === (int)$g['id']) ? ' active' : '' ?>" href="/index.php?genre=<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            </ul>
            <!-- Поиск по фильмам/сериалам (Задание 3) + подсказки при вводе -->
            <form class="io-search-form me-lg-2" action="/index.php" method="get" role="search">
                <div class="io-search">
                    <input class="form-control" type="search" name="q" autocomplete="off"
                           value="<?= htmlspecialchars($q) ?>" placeholder="Фильмы, сериалы, персоны…"
                           aria-label="Поиск по фильмам и сериалам" aria-autocomplete="list"
                           aria-controls="ioSuggest" aria-expanded="false">
                    <ul class="io-suggest" id="ioSuggest" role="listbox" hidden></ul>
                </div>
                <button class="btn btn-io io-search-btn" type="submit">Найти</button>
            </form>
            <?php if (isset($_SESSION['user'])): ?>
                <?php
                    $ioUserName = (string)$_SESSION['user']['first_name'];
                    $ioUserRole = (string)($_SESSION['user']['role'] ?? '');
                    $ioHasSub   = io_can_watch($pdo, $_SESSION['user']);
                ?>
                <div class="dropdown io-user-drop ms-1 mt-2 mt-lg-0">
                    <button type="button" class="io-user" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                            aria-expanded="false" aria-haspopup="true" title="Личный кабинет">
                        <span class="io-user-avatar<?= $ioHasSub ? ' is-sub' : '' ?>" aria-hidden="true"><?= io_initial($ioUserName) ?></span>
                        <span class="io-user-name d-none d-xl-inline"><?= htmlspecialchars($ioUserName) ?></span>
                        <svg class="io-user-chev" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end io-user-menu">
                        <li class="io-user-menu-head">
                            <span class="io-user-menu-name"><?= htmlspecialchars($ioUserName) ?></span>
                            <span class="io-role io-role--<?= htmlspecialchars($ioUserRole) ?>"><?= htmlspecialchars($ioUserRole) ?></span>
                        </li>
                        <?php if ($ioHasSub): ?>
                            <li class="io-user-menu-sub"><span aria-hidden="true">★</span> Подписка активна</li>
                        <?php else: ?>
                            <li class="io-user-menu-sub"><a href="/payment.php">Оформить подписку</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="/cabinet.php">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                Личный кабинет
                            </a>
                        </li>
                        <?php if ($ioUserRole === 'admin'): ?>
                            <li><a class="dropdown-item" href="/admin.php">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 15a3 3 0 100-6 3 3 0 000 6z"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                                Админ-панель
                            </a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item io-menu-logout" href="/backend/auth.php?action=logout">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
                            Выйти
                        </a></li>
                    </ul>
                </div>
            <?php else: ?>
                <a href="/login.php" class="btn btn-outline-light btn-sm io-auth-btn ms-1 mt-2 mt-lg-0">Войти</a>
                <a href="/register.php" class="btn btn-io btn-sm io-auth-btn ms-1 mt-2 mt-lg-0">Регистрация</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
