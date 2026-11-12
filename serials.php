<?php
// =========================================================
// Каталог сериалов (типовая страница «Сериалы»):
// полный список сериалов с фильтром по жанрам и сортировкой.
// =========================================================
require_once __DIR__ . '/backend/config.php';
require_once __DIR__ . '/src/components/helpers.php';
session_start();

$genreId = (int)($_GET['genre'] ?? 0);                // фильтр по категории
$q       = trim((string)($_GET['q'] ?? ''));          // поиск внутри каталога
$uid     = $_SESSION['user']['id'] ?? 0;

// Сортировка (только значения из белого списка)
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

// ---------- Категории ----------
$genres = $pdo->query('SELECT * FROM genres ORDER BY name')->fetchAll();

// ---------- Запрос каталога сериалов ----------
$sql = "SELECT c.*, g.name AS genre_name FROM content c
        JOIN genres g ON g.id = c.genre_id
        WHERE c.type = 'serial'";
$params = [];
if ($genreId > 0) { $sql .= ' AND c.genre_id = ?'; $params[] = $genreId; }
if ($q !== '')    { $sql .= ' AND c.title LIKE ?'; $params[] = like_escape($q); }

$stmt = $pdo->prepare($sql . ' ORDER BY ' . $sortMap[$sort]);
$stmt->execute($params);
$serials = $stmt->fetchAll();

$favIds = io_fav_ids($pdo, (int)$uid);
$hasFilters = ($genreId > 0 || $q !== '' || $sort !== 'rating');

/** Ссылка каталога с учётом текущих фильтров */
function serials_url(array $over = []): string {
    global $genreId, $q, $sort;
    $p = array_merge(['genre' => $genreId, 'q' => $q, 'sort' => $sort], $over);
    if (($p['sort'] ?? '') === 'rating') unset($p['sort']);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== 0 && $v !== null);
    return '/serials.php' . ($p ? '?' . http_build_query($p) : '');
}
$sortLabels = ['rating' => 'По рейтингу', 'year' => 'Сначала новые', 'title' => 'По названию'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Сериалы — Illuminos</title>
    <?php require __DIR__ . '/src/components/head-assets.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
<?php require __DIR__ . '/src/components/header.php'; ?>

<main class="container flex-grow-1" id="main">
    <div class="io-catalog-head mt-4 mb-2">
        <h1 class="io-catalog-title">Сериалы</h1>
        <p class="text-secondary mb-0"><?= count($serials) ?> <?= io_plural(count($serials), ['сериал', 'сериала', 'сериалов']) ?> в каталоге</p>
    </div>

    <!-- Фильтры: жанры + сортировка -->
    <div class="io-filters mt-3">
        <div class="io-chips d-flex flex-wrap gap-2" role="group" aria-label="Категории">
            <a class="btn btn-sm <?= $genreId === 0 ? 'btn-io' : 'btn-outline-light' ?>" href="<?= io_e(serials_url(['genre' => 0])) ?>"
               <?= $genreId === 0 ? 'aria-current="true"' : '' ?>>Все</a>
            <?php foreach ($genres as $g): ?>
                <a class="btn btn-sm <?= $genreId === (int)$g['id'] ? 'btn-io' : 'btn-outline-light' ?>"
                   href="<?= io_e(serials_url(['genre' => (int)$g['id']])) ?>"
                   <?= $genreId === (int)$g['id'] ? 'aria-current="true"' : '' ?>><?= htmlspecialchars($g['name']) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="io-filters-row">
            <div class="io-sort" role="group" aria-label="Сортировка">
                <span class="io-filter-label">Сортировка:</span>
                <?php foreach ($sortMap as $key => $_): ?>
                    <a class="btn btn-sm <?= $sort === $key ? 'btn-io' : 'btn-outline-light' ?>"
                       href="<?= io_e(serials_url(['sort' => $key])) ?>"><?= $sortLabels[$key] ?></a>
                <?php endforeach; ?>
            </div>
            <?php if ($hasFilters): ?>
                <a class="small" href="/serials.php">Сбросить фильтры</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($q !== ''): ?>
        <p class="text-secondary">Результаты поиска по запросу «<?= htmlspecialchars($q) ?>»:</p>
    <?php endif; ?>

    <?php render_cards($serials, $favIds,
        'Сериалов по таким условиям не найдено.',
        $hasFilters ? '<a class="btn btn-io btn-sm" href="/serials.php">Сбросить фильтры</a>' : ''); ?>
</main>

<?php require __DIR__ . '/src/components/footer.php'; ?>
