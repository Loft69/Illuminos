<?php
// =========================================================
// Страница сериала (типовая страница):
// описание и оценки, сезоны и эпизоды, актёры и режиссёры,
// трейлер, список похожих сериалов.
// =========================================================
require_once __DIR__ . '/backend/config.php';
require_once __DIR__ . '/src/components/helpers.php';
session_start();

$id = (int)($_GET['id'] ?? 0);
$uid = $_SESSION['user']['id'] ?? 0;

$stmt = $pdo->prepare(
    "SELECT c.*, g.name AS genre_name FROM content c
     JOIN genres g ON g.id = c.genre_id
     WHERE c.id = ? AND c.type = 'serial'");
$stmt->execute([$id]);
$serial = $stmt->fetch();
if (!$serial) { http_response_code(404); exit('Сериал не найден.'); }

io_log_view($pdo, (int)$uid, $id);

// Сезоны и эпизоды (Задание 4)
$stmt = $pdo->prepare('SELECT * FROM seasons WHERE content_id = ? ORDER BY number');
$stmt->execute([$id]);
$seasons = $stmt->fetchAll();
foreach ($seasons as &$s) {
    $st = $pdo->prepare('SELECT * FROM episodes WHERE season_id = ? ORDER BY number');
    $st->execute([$s['id']]);
    $s['episodes'] = $st->fetchAll();
}
unset($s);

// Похожие сериалы
$stmt = $pdo->prepare(
    "SELECT c.*, g.name AS genre_name FROM content c
     JOIN genres g ON g.id = c.genre_id
     WHERE c.genre_id = ? AND c.id != ? AND c.type = 'serial'
     ORDER BY c.rating DESC LIMIT 4");
$stmt->execute([$serial['genre_id'], $id]);
$similar = $stmt->fetchAll();

$favIds = io_fav_ids($pdo, (int)$uid);
$chev = '<svg class="io-chev" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($serial['title']) ?> — Illuminos</title>
    <?php require __DIR__ . '/src/components/head-assets.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
<?php require __DIR__ . '/src/components/header.php'; ?>

<main class="container flex-grow-1" id="main">
    <?php render_detail($serial, in_array($id, $favIds, true)); ?>

    <!-- Список сезонов и эпизодов (Задание 4) -->
    <section class="io-section">
        <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-3">
            <h2 class="io-section-title h4 mb-0">Сезоны и эпизоды</h2>
            <?php if (count($seasons) > 1): ?>
                <button type="button" class="btn btn-outline-light btn-sm" data-seasons-toggle>Развернуть все</button>
            <?php endif; ?>
        </div>
        <?php foreach ($seasons as $i => $s):
            $n = count($s['episodes']);
            $mins = array_sum(array_map(fn($e) => (int)$e['duration_min'], $s['episodes']));
            $dur = $mins >= 60 ? intdiv($mins, 60) . ' ч ' . ($mins % 60) . ' мин' : $mins . ' мин';
            $cid = 'season-' . (int)$s['id'];
        ?>
            <div class="io-season">
                <h3 class="h6 m-0">
                    <button type="button" class="io-season-head" data-bs-toggle="collapse" data-bs-target="#<?= $cid ?>"
                            aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="<?= $cid ?>">
                        <?= $chev ?>
                        <span>Сезон <?= (int)$s['number'] ?></span>
                        <span class="io-season-sub"><?= $n ?> <?= io_plural($n, ['эпизод', 'эпизода', 'эпизодов']) ?> · <?= $dur ?></span>
                    </button>
                </h3>
                <div id="<?= $cid ?>" class="collapse<?= $i === 0 ? ' show' : '' ?>">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($s['episodes'] as $e): ?>
                            <li class="list-group-item bg-transparent text-light d-flex justify-content-between">
                                <span>Эпизод <?= (int)$e['number'] ?>. <?= htmlspecialchars($e['title']) ?></span>
                                <span class="text-secondary"><?= (int)$e['duration_min'] ?> мин</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$seasons) echo io_empty('Информация о сезонах появится позже.'); ?>
    </section>

    <!-- Трейлер сериала (Задание 4) -->
    <?php render_trailer($serial); ?>

    <!-- Похожие сериалы (Задание 4) -->
    <section class="io-section">
        <h2 class="io-section-title h4">Похожие сериалы</h2>
        <?php if ($similar) render_cards($similar, $favIds);
              else echo io_empty('Похожих сериалов пока нет.'); ?>
    </section>
</main>

<?php require __DIR__ . '/src/components/footer.php'; ?>
