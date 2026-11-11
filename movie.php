<?php
// =========================================================
// Страница фильма (типовая страница):
// описание и оценки, режиссёры и актёры, трейлер,
// список похожих фильмов. Просмотр фиксируется в истории.
// =========================================================
require_once __DIR__ . '/backend/config.php';
require_once __DIR__ . '/src/components/helpers.php';
session_start();

$id = (int)($_GET['id'] ?? 0);
$uid = $_SESSION['user']['id'] ?? 0;

// Защита от SQL-инъекций — только подготовленные выражения
$stmt = $pdo->prepare(
    "SELECT c.*, g.name AS genre_name FROM content c
     JOIN genres g ON g.id = c.genre_id
     WHERE c.id = ? AND c.type = 'film'");
$stmt->execute([$id]);
$film = $stmt->fetch();
if (!$film) { http_response_code(404); exit('Фильм не найден.'); }

// Право просмотра: активная подписка или администратор (ТЗ: просмотр по подписке)
$canWatch = io_can_watch($pdo, $_SESSION['user'] ?? []);

// Запись в историю просмотра (Задание 5: история просмотра)
io_log_view($pdo, (int)$uid, $id);

// Похожие фильмы — тот же жанр (Задание 4)
$stmt = $pdo->prepare(
    "SELECT c.*, g.name AS genre_name FROM content c
     JOIN genres g ON g.id = c.genre_id
     WHERE c.genre_id = ? AND c.id != ? AND c.type = 'film'
     ORDER BY c.rating DESC LIMIT 4");
$stmt->execute([$film['genre_id'], $id]);
$similar = $stmt->fetchAll();

$favIds = io_fav_ids($pdo, (int)$uid);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($film['title']) ?> — Illuminos</title>
    <?php require __DIR__ . '/src/components/head-assets.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
<?php require __DIR__ . '/src/components/header.php'; ?>

<main class="container flex-grow-1" id="main">
    <!-- Постер-заглушка, описание и оценки, режиссёр, актёры (Задание 4) -->
    <?php render_detail($film, in_array($id, $favIds, true), $canWatch); ?>

    <!-- Просмотр: по подписке — плеер, без подписки — заглушка с предложением -->
    <?php render_watch_block($film, $canWatch); ?>

    <!-- Трейлер фильма (Задание 4) -->
    <?php render_trailer($film); ?>

    <!-- Похожие фильмы (Задание 4) -->
    <section class="io-section">
        <h2 class="io-section-title h4">Похожие фильмы</h2>
        <?php if ($similar) render_cards($similar, $favIds);
              else echo io_empty('Похожих фильмов пока нет.'); ?>
    </section>
</main>

<?php require __DIR__ . '/src/components/footer.php'; ?>
