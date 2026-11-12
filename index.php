<?php
// =========================================================
// Главная страница Illuminos:
//   - Топ новинки и популярные фильмы/сериалы
//   - Категории контента (фильтрация)
//   - Рекомендации по персональному выбору
//   - Поиск по фильмам/сериалам (+ подсказки при вводе)
// =========================================================
require_once __DIR__ . '/backend/config.php';
require_once __DIR__ . '/src/components/helpers.php';
session_start();

$type    = $_GET['type'] ?? '';                       // film | serial | ''
$genreId = (int)($_GET['genre'] ?? 0);                // фильтр по категории
$q       = trim((string)($_GET['q'] ?? ''));          // строка поиска
$uid     = $_SESSION['user']['id'] ?? 0;
if (!in_array($type, ['film', 'serial'], true)) $type = '';

// Сортировка каталога (только значения из белого списка)
$sortMap = [
    'rating' => 'c.rating DESC',
    'year'   => 'c.year DESC, c.rating DESC',
    'title'  => 'c.title ASC',
];
$sort = $_GET['sort'] ?? 'rating';
if (!isset($sortMap[$sort])) $sort = 'rating';

/** Экранирование спецсимволов LIKE в строке поиска */
function like_escape(string $s): string {
    return '%' . addcslashes($s, '%_\\') . '%';
}

// ---------- AJAX: подсказки поиска ----------
if (($_GET['action'] ?? '') === 'suggest') {
    header('Content-Type: application/json; charset=utf-8');
    $out = [];
    if (io_strlen($q) >= 2) {
        $stmt = $pdo->prepare('SELECT id, type, title, year, rating, genre_id FROM content
                               WHERE title LIKE ? ORDER BY rating DESC LIMIT 6');
        $stmt->execute([like_escape($q)]);
        foreach ($stmt->fetchAll() as $row) {
            [$h1, $h2] = io_poster_hues($row);
            $out[] = [
                'id' => (int)$row['id'], 'title' => $row['title'], 'year' => $row['year'],
                'rating' => $row['rating'], 'type_label' => io_type_label($row['type']),
                'url' => io_link($row), 'h1' => $h1, 'h2' => $h2,
            ];
        }
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- Категории ----------
$genres = $pdo->query('SELECT * FROM genres ORDER BY name')->fetchAll();

// ---------- Заготовка запроса контента ----------
$baseSql = 'SELECT c.*, g.name AS genre_name
            FROM content c JOIN genres g ON g.id = c.genre_id
            WHERE 1=1';
$params = [];
if ($type !== '') { $baseSql .= ' AND c.type = ?'; $params[] = $type; }
if ($genreId > 0) { $baseSql .= ' AND c.genre_id = ?'; $params[] = $genreId; }
if ($q !== '') { $baseSql .= ' AND c.title LIKE ?'; $params[] = like_escape($q); }

// Топ новинки (Задание 3, п.5)
$news = $pdo->query("SELECT c.*, g.name AS genre_name FROM content c
                     JOIN genres g ON g.id = c.genre_id
                     WHERE c.is_new = 1 ORDER BY c.year DESC, c.rating DESC LIMIT 8")->fetchAll();

// Популярные фильмы/сериалы (Задание 3, п.5)
$popular = $pdo->query("SELECT c.*, g.name AS genre_name FROM content c
                        JOIN genres g ON g.id = c.genre_id
                        WHERE c.is_popular = 1 ORDER BY c.rating DESC LIMIT 8")->fetchAll();

// Рекомендации по персональному выбору (Задание 3, п.7):
// жанры из избранного авторизованного пользователя, иначе — высокий рейтинг
if ($uid > 0) {
    $stmt = $pdo->prepare(
        'SELECT DISTINCT c.*, g.name AS genre_name FROM content c
         JOIN genres g ON g.id = c.genre_id
         WHERE c.genre_id IN (SELECT genre_id FROM favorites f
                              JOIN content c2 ON c2.id = f.content_id
                              WHERE f.user_id = ?)
           AND c.id NOT IN (SELECT content_id FROM favorites WHERE user_id = ?)
         ORDER BY c.rating DESC LIMIT 8');
    $stmt->execute([$uid, $uid]);
    $recommended = $stmt->fetchAll();
} else {
    $recommended = $pdo->query(
        'SELECT c.*, g.name AS genre_name FROM content c
         JOIN genres g ON g.id = c.genre_id
         ORDER BY c.rating DESC LIMIT 8')->fetchAll();
}

// Результат поиска / фильтра (Задания 3, п.6, п.8)
$stmt = $pdo->prepare($baseSql . ' ORDER BY ' . $sortMap[$sort] . ' LIMIT 24');
$stmt->execute($params);
$filtered = $stmt->fetchAll();

// Избранное текущего пользователя (для подсветки кнопок)
$favIds = io_fav_ids($pdo, (int)$uid);

// Герой: самые свежие новинки.
// Фоновые трейлеры — локальные файлы (public/video), соответствуют content.id:
// скачиваются один раз (скриптом), грузятся мгновенно, YouTube-iframe не нужен.
$heroVideoMap = [
    1 => '/public/video/trailer-01.mp4',
    2 => '/public/video/trailer-02.mp4',
    3 => '/public/video/trailer-03.mp4',
    4 => '/public/video/trailer-04.mp4',
    5 => '/public/video/trailer-05.mp4',
    6 => '/public/video/trailer-06.mp4',
    7 => '/public/video/trailer-07.mp4',
    8 => '/public/video/trailer-08.mp4',
    9 => '/public/video/trailer-09.mp4',
    10 => '/public/video/trailer-10.mp4',
    11 => '/public/video/trailer-11.mp4',
    12 => '/public/video/trailer-12.mp4',
    13 => '/public/video/trailer-13.mp4',
    14 => '/public/video/trailer-14.mp4',
    15 => '/public/video/trailer-15.mp4',
    16 => '/public/video/trailer-16.mp4',
];
$heroItems = array_slice($news, 0, 5);
foreach ($heroItems as &$h) {
    $h['hero_video'] = $heroVideoMap[(int)$h['id']] ?? null;
}
unset($h);
// Подписчик ли текущий пользователь: в герое меняется текст главной кнопки
$canWatchHero = io_can_watch($pdo, $_SESSION['user'] ?? []);

/** Ссылка на каталог с учётом текущих фильтров (изменяем только переданные параметры) */
function catalog_url(array $over = []): string {
    global $type, $genreId, $q, $sort;
    $p = array_merge(['type' => $type, 'genre' => $genreId, 'q' => $q, 'sort' => $sort], $over);
    if (($p['sort'] ?? '') === 'rating') unset($p['sort']);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== 0 && $v !== null);
    return '/index.php' . ($p ? '?' . http_build_query($p) : '') . '#catalog';
}
$hasFilters = ($type !== '' || $genreId > 0 || $q !== '' || $sort !== 'rating');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Illuminos — онлайн-кинотеатр</title>
    <?php require __DIR__ . '/src/components/head-assets.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
<?php require __DIR__ . '/src/components/header.php'; ?>

<main class="container flex-grow-1" id="main">
    <h1 class="visually-hidden">Illuminos — онлайн-кинотеатр</h1>

    <?php if ($heroItems): ?>
    <!-- Герой: новинки с автосменой, паузой и переключением по миниатюрам -->
    <section class="io-hero is-playing" data-hero aria-roledescription="карусель" aria-label="Новинки">
        <div class="io-hero-slides">
            <?php foreach ($heroItems as $i => $h): $inFavH = in_array((int)$h['id'], $favIds, true); ?>
            <article class="io-hero-slide<?= $i === 0 ? ' is-active' : '' ?>" style="<?= io_poster_style($h) ?>"
                     <?= $i === 0 ? '' : 'aria-hidden="true"' ?>>
                <?php if (!empty($h['hero_video'])): ?>
                    <!-- Фон: локальный трейлер без звука, звук — кнопкой (main.js) -->
                    <video class="io-hero-video" data-hero-video muted loop playsinline preload="none" data-src="<?= io_e($h['hero_video']) ?>"></video>
                <?php endif; ?>
                <div class="io-hero-copy">
                    <div class="io-hero-tags">
                        <span class="io-tag io-tag--gold"><?= io_e($h['rating']) ?></span>
                        <span class="io-tag"><?= io_e(io_type_label($h['type'])) ?></span>
                        <span class="io-tag"><?= io_e($h['genre_name']) ?></span>
                        <span class="io-tag"><?= io_e($h['year']) ?></span>
                    </div>
                    <h2 class="io-hero-title"><?= io_e($h['title']) ?></h2>
                    <p class="io-hero-desc"><?= io_e($h['description']) ?></p>
                    <div class="io-actions">
                        <a class="btn btn-io" href="<?= io_link($h) ?>">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4l13 8-13 8z"/></svg>
                            <?= $canWatchHero ? 'Смотреть' : 'Подробнее' ?>
                        </a>
                        <?php if (!$canWatchHero): ?>
                            <a class="btn btn-outline-light" href="/payment.php">Подписка</a>
                        <?php endif; ?>
                        <?= io_fav_button((int)$h['id'], $inFavH, !empty($_SESSION['user']), null, 'io-fav-btn--inline', true) ?>
                    </div>
                </div>
                <a class="io-hero-poster" href="<?= io_link($h) ?>" tabindex="-1" aria-hidden="true">
                    <?= io_poster($h) ?>
                </a>
            </article>
            <?php endforeach; ?>
        </div>
        <?php if (count($heroItems) > 1): ?>
        <div class="io-hero-nav">
            <div class="io-hero-dots" role="group" aria-label="Новинки">
                <?php foreach ($heroItems as $i => $h): ?>
                <button type="button" class="io-hero-dot<?= $i === 0 ? ' is-active' : '' ?>"
                        aria-label="<?= io_e($h['title']) ?>" aria-current="<?= $i === 0 ? 'true' : 'false' ?>"></button>
                <?php endforeach; ?>
            </div>
            <div class="io-hero-controls">
                <button type="button" class="io-hero-toggle io-hero-sound" data-hero-sound aria-label="Включить звук трейлера" aria-pressed="false" title="Звук трейлера">
                    <svg class="i-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M23 9l-6 6M17 9l6 6"/></svg>
                    <svg class="i-loud" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5L6 9H2v6h4l5 4V5z"/><path d="M15.5 8.5a5 5 0 011 3.5 5 5 0 01-1 3.5"/><path d="M19 5a9 9 0 010 14"/></svg>
                </button>
                <button type="button" class="io-hero-toggle" aria-label="Остановить автосмену">
                    <svg class="i-pause" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="5" y="4" width="5" height="16" rx="1"/><rect x="14" y="4" width="5" height="16" rx="1"/></svg>
                    <svg class="i-play" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4l13 8-13 8z"/></svg>
                </button>
            </div>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- Топ новинки -->
    <section class="io-section">
        <h2 class="io-section-title h4">Топ новинки</h2>
        <?php render_rail($news, $favIds, 'Топ новинки'); ?>
    </section>

    <!-- Популярное -->
    <section class="io-section">
        <h2 class="io-section-title h4">Популярные фильмы и сериалы</h2>
        <?php render_rail($popular, $favIds, 'Популярные фильмы и сериалы'); ?>
    </section>

    <!-- Категории контента: фильтрация по жанрам (Задание 3, п.6) -->
    <section class="io-section" id="catalog">
        <h2 class="io-section-title h4">Категории</h2>        <div class="io-filters">
            <div class="io-chips d-flex flex-wrap gap-2" role="group" aria-label="Категории">
                <a class="btn btn-sm <?= $genreId === 0 ? 'btn-io' : 'btn-outline-light' ?>" href="<?= io_e(catalog_url(['genre' => 0])) ?>"
                   <?= $genreId === 0 ? 'aria-current="true"' : '' ?>>Все</a>
                <?php foreach ($genres as $g): ?>
                    <a class="btn btn-sm <?= $genreId === (int)$g['id'] ? 'btn-io' : 'btn-outline-light' ?>"
                       href="<?= io_e(catalog_url(['genre' => (int)$g['id']])) ?>"
                       <?= $genreId === (int)$g['id'] ? 'aria-current="true"' : '' ?>><?= htmlspecialchars($g['name']) ?></a>
                <?php endforeach; ?>
            </div>
            <div class="io-filters-row">
                <div class="io-segment" role="group" aria-label="Тип контента">
                    <span class="io-filter-label">Показать:</span>
                    <a class="btn btn-sm <?= $type === '' ? 'btn-io' : 'btn-outline-light' ?>" href="<?= io_e(catalog_url(['type' => ''])) ?>">Всё</a>
                    <a class="btn btn-sm <?= $type === 'film' ? 'btn-io' : 'btn-outline-light' ?>" href="/films.php">Фильмы</a>
                    <a class="btn btn-sm <?= $type === 'serial' ? 'btn-io' : 'btn-outline-light' ?>" href="/serials.php">Сериалы</a>
                </div>
                <div class="io-sort" role="group" aria-label="Сортировка">
                    <span class="io-filter-label">Сортировка:</span>
                    <a class="btn btn-sm <?= $sort === 'rating' ? 'btn-io' : 'btn-outline-light' ?>" href="<?= io_e(catalog_url(['sort' => 'rating'])) ?>">По рейтингу</a>
                    <a class="btn btn-sm <?= $sort === 'year' ? 'btn-io' : 'btn-outline-light' ?>" href="<?= io_e(catalog_url(['sort' => 'year'])) ?>">Сначала новые</a>
                    <a class="btn btn-sm <?= $sort === 'title' ? 'btn-io' : 'btn-outline-light' ?>" href="<?= io_e(catalog_url(['sort' => 'title'])) ?>">По названию</a>
                </div>
                <?php if ($hasFilters): ?>
                    <a class="small" href="/index.php#catalog">Сбросить фильтры</a>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($q !== ''): ?>
            <p class="text-secondary">Результаты поиска по запросу «<?= htmlspecialchars($q) ?>»:</p>
        <?php endif; ?>
        <?php if ($filtered): ?>
            <p class="io-results-meta" aria-live="polite">Показано: <?= count($filtered) ?></p>
        <?php endif; ?>
        <?php render_cards($filtered, $favIds,
            'Измените запрос или выберите другую категорию.',
            $hasFilters ? '<a class="btn btn-io btn-sm" href="/index.php#catalog">Сбросить фильтры</a>' : ''); ?>
    </section>

    <!-- Рекомендации по персональному выбору (Задание 3, п.7) -->
    <section class="io-section">
        <h2 class="io-section-title h4">
            <?= $uid > 0 ? 'Рекомендуем вам' : 'Высоко оцененные зрителями' ?>
        </h2>
        <?php render_rail($recommended, $favIds, $uid > 0 ? 'Рекомендуем вам' : 'Высоко оцененные зрителями',
            $uid > 0 ? 'Добавьте несколько фильмов в избранное — мы подберём похожие.' : ''); ?>
    </section>

    <?php if (!$hasFilters): ?>
    <!-- Преимущества подписки: зачем оформлять (наполнение главной) -->
    <section class="io-section" aria-labelledby="benefits-title">
        <h2 class="io-section-title h4" id="benefits-title">Почему зрители выбирают Illuminos</h2>
        <div class="row g-3">
            <?php
            $benefits = [
                ['Подписка вместо аренды', 'Одна подписка — весь каталог. Никаких отдельных покупок фильмов.', '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0114 0v4"/>'],
                ['Рекомендации под вкус', 'Система подстраивается под избранное: чем больше смотрите, тем точнее подборки.', '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>'],
                ['На любом устройстве', 'Браузер, телефон, планшет, телевизор — прогресс просмотра синхронизируется.', '<rect x="2" y="4" width="14" height="12" rx="2"/><path d="M16 8h6v8a2 2 0 01-2 2h-4"/>'],
                ['Без рекламы на старте', 'Со второго тарифа — без рекламы: только кино, заставки и титры.', '<path d="M5 3l14 9-14 9V3z"/>'],
            ];
            foreach ($benefits as $b): ?>
            <div class="col-md-6 col-xl-3">
                <div class="io-benefit h-100">
                    <span class="io-benefit-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $b[2] ?></svg>
                    </span>
                    <h3 class="io-benefit-title"><?= io_e($b[0]) ?></h3>
                    <p class="io-benefit-text mb-0"><?= io_e($b[1]) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="io-sub-cta">
            <p class="mb-2">Подписка — от 199 ₽ в месяц. Отменить можно в любой момент.</p>
            <a class="btn btn-io" href="/payment.php">Выбрать тариф</a>
        </div>
    </section>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/src/components/footer.php'; ?>
