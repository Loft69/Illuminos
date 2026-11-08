<?php
// =========================================================
// Личный кабинет (доступен только авторизованным):
//   - история просмотра (+ очистка)
//   - подписка на сервис
//   - избранное (сохранение фильмов/сериалов)
//   - история платежей
// Также обрабатывает AJAX-переключение избранного.
// =========================================================
require_once __DIR__ . '/backend/config.php';
require_once __DIR__ . '/src/components/helpers.php';
session_start();

// AJAX: добавить/убрать из избранного
if (($_GET['action'] ?? '') === 'toggle_fav' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['user'])) { http_response_code(403); echo json_encode(['ok' => false]); exit; }
    $uid = (int)$_SESSION['user']['id'];
    $cid = (int)($_POST['content_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT id FROM favorites WHERE user_id = ? AND content_id = ?');
    $stmt->execute([$uid, $cid]);
    if ($row = $stmt->fetch()) {
        $pdo->prepare('DELETE FROM favorites WHERE id = ?')->execute([$row['id']]);
        echo json_encode(['ok' => true, 'in_favorites' => false]);
    } else {
        $pdo->prepare('INSERT IGNORE INTO favorites (user_id, content_id) VALUES (?, ?)')->execute([$uid, $cid]);
        echo json_encode(['ok' => true, 'in_favorites' => true]);
    }
    exit;
}

// AJAX: очистить историю просмотра
if (($_GET['action'] ?? '') === 'clear_history' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['user'])) { http_response_code(403); echo json_encode(['ok' => false]); exit; }
    $pdo->prepare('DELETE FROM watch_history WHERE user_id = ?')->execute([(int)$_SESSION['user']['id']]);
    echo json_encode(['ok' => true]);
    exit;
}

// Вход в личный кабинет — только для авторизованных (Задание 5)
if (empty($_SESSION['user'])) { header('Location: /login.php'); exit; }
$uid = (int)$_SESSION['user']['id'];

// Данные пользователя
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$uid]);
$user = $stmt->fetch();

// Подписка на сервис (Задание 5)
$stmt = $pdo->prepare(
    'SELECT us.*, t.name AS tariff_name, t.price FROM user_subscriptions us
     JOIN tariffs t ON t.id = us.tariff_id
     WHERE us.user_id = ? AND us.end_date >= CURDATE()
     ORDER BY us.end_date DESC LIMIT 1');
$stmt->execute([$uid]);
$subscription = $stmt->fetch();

// Сколько дней осталось и какая доля срока прошла
$daysLeft = 0; $leftPct = 0;
if ($subscription) {
    $dEnd   = new DateTime($subscription['end_date']);
    $dStart = new DateTime($subscription['start_date']);
    $dNow   = new DateTime('today');
    $daysLeft = max(0, (int)$dNow->diff($dEnd)->format('%r%a'));
    $total    = max(1, (int)$dStart->diff($dEnd)->days);
    $leftPct  = round(min(100, max(0, $daysLeft / $total * 100)), 1);
}

// История просмотра (Задание 5)
$stmt = $pdo->prepare(
    'SELECT h.watched_at, c.id, c.title, c.type FROM watch_history h
     JOIN content c ON c.id = h.content_id
     WHERE h.user_id = ? ORDER BY h.watched_at DESC LIMIT 20');
$stmt->execute([$uid]);
$history = $stmt->fetchAll();

// Избранное (Задание 5)
$stmt = $pdo->prepare(
    'SELECT c.id, c.title, c.type, c.year, c.rating, c.genre_id, g.name AS genre_name
     FROM favorites f
     JOIN content c ON c.id = f.content_id
     JOIN genres g ON g.id = c.genre_id
     WHERE f.user_id = ? ORDER BY f.created_at DESC');
$stmt->execute([$uid]);
$favorites = $stmt->fetchAll();

// История платежей
$stmt = $pdo->prepare(
    'SELECT p.amount, p.status, p.created_at, t.name AS tariff_name
     FROM payments p JOIN tariffs t ON t.id = p.tariff_id
     WHERE p.user_id = ? ORDER BY p.created_at DESC LIMIT 10');
$stmt->execute([$uid]);
$payments = $stmt->fetchAll();
$payLabels = ['paid' => 'Оплачен', 'new' => 'Ожидает оплаты', 'cancelled' => 'Отменён'];

$fio = trim($user['last_name'] . ' ' . $user['first_name'] . ' ' . $user['patronymic']);
$initials = io_initial((string)$user['first_name']) . io_initial((string)$user['last_name']);
$role = (string)$user['role'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Личный кабинет — Illuminos</title>
    <?php require __DIR__ . '/src/components/head-assets.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
<?php require __DIR__ . '/src/components/header.php'; ?>

<main class="container flex-grow-1" id="main">
    <h1 class="h3 mt-4 mb-3 io-form-title">Личный кабинет</h1>

    <div class="row g-4">
        <!-- Левая колонка: профиль и подписка -->
        <div class="col-lg-4">
            <!-- Профиль -->
            <div class="io-card p-3 mb-4">
                <h2 class="h6 mb-3">Профиль</h2>
                <div class="d-flex align-items-center gap-3 mb-2">
                    <div class="io-avatar" aria-hidden="true"><?= io_e($initials) ?></div>
                    <div style="min-width:0">
                        <p class="mb-1"><?= htmlspecialchars($fio) ?></p>
                        <p class="mb-0">Роль: <span class="io-role io-role--<?= io_e($role) ?>"><?= htmlspecialchars($role) ?></span></p>
                    </div>
                </div>
                <p class="mb-0 text-secondary small" style="overflow-wrap:anywhere">Логин: <?= htmlspecialchars($user['login']) ?> · E-mail: <?= htmlspecialchars($user['email']) ?></p>
                <div class="io-stats">
                    <div><div class="io-stat-num"><?= count($favorites) ?></div><div class="io-stat-label">в избранном</div></div>
                    <div><div class="io-stat-num" data-stat-history><?= count($history) ?></div><div class="io-stat-label">в истории</div></div>
                </div>
            </div>

            <!-- Подписка на сервис (Задание 5) -->
            <div class="io-card p-3 mb-4">
                <h2 class="h6">Подписка</h2>
                <?php if ($subscription): ?>
                    <p class="mb-2">Тариф «<?= htmlspecialchars($subscription['tariff_name']) ?>» активен
                        до <?= htmlspecialchars(date('d.m.Y', strtotime($subscription['end_date']))) ?>.</p>
                    <div class="io-progress" role="progressbar" aria-label="Оставшийся срок подписки"
                         aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $leftPct ?>"><span style="--w:<?= $leftPct ?>%"></span></div>
                    <p class="text-secondary small mt-2 mb-0">Осталось <?= $daysLeft ?> <?= io_plural($daysLeft, ['день', 'дня', 'дней']) ?>.</p>
                <?php else: ?>
                    <p class="mb-0">Подписка не оформлена.
                        <a href="/payment.php" class="text-decoration-none">Оформить →</a></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Правая колонка: избранное, история, платежи -->
        <div class="col-lg-8">
            <!-- Избранное (Задание 5) -->
            <div class="io-card p-3 mb-4">
                <h2 class="h6 mb-3">Избранное</h2>
                <div class="row g-3" data-fav-grid>
                    <?php foreach ($favorites as $f): ?>
                    <div class="col-6 col-sm-4 col-md-3 io-fav-col">
                        <article class="io-card">
                            <a href="<?= io_link($f) ?>" class="io-card-link text-decoration-none text-reset">
                                <?= io_poster($f) ?>
                                <div class="io-card-body">
                                    <div class="io-card-title"><?= htmlspecialchars($f['title']) ?></div>
                                    <div class="io-card-meta"><?= $f['year'] ?> · <?= htmlspecialchars($f['genre_name']) ?></div>
                                </div>
                            </a>
                            <?= io_fav_button((int)$f['id'], true, true, 'Убрать из избранного') ?>
                        </article>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (!$favorites) echo io_empty('В избранном пока пусто.', 'Нажмите ☆ на карточке фильма или сериала, чтобы сохранить его здесь.', '<a class="btn btn-io btn-sm" href="/index.php">Выбрать что посмотреть</a>'); ?>
            </div>

            <!-- История просмотра (Задание 5) -->
            <div class="io-card p-3 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 mb-0">История просмотра</h2>
                    <?php if ($history): ?><button type="button" class="io-link-danger" data-clear-history>Очистить историю</button><?php endif; ?>
                </div>
                <div data-history>
                <?php if ($history): ?>
                    <ul class="list-group list-group-flush io-history">
                        <?php foreach ($history as $h): ?>
                            <li class="list-group-item bg-transparent text-light d-flex justify-content-between gap-3">
                                <span>
                                    <a href="/<?= $h['type'] === 'serial' ? 'serial' : 'movie' ?>.php?id=<?= $h['id'] ?>"
                                       class="text-decoration-none"><?= htmlspecialchars($h['title']) ?></a>
                                    <span class="io-history-type"><?= io_e(io_type_label($h['type'])) ?></span>
                                </span>
                                <span class="text-secondary text-nowrap" title="<?= htmlspecialchars($h['watched_at']) ?>"><?= htmlspecialchars(date('d.m.Y, H:i', strtotime($h['watched_at']))) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?><p class="text-secondary mb-0">История пуста.</p><?php endif; ?>
                </div>
            </div>

            <!-- Платежи -->
            <?php if ($payments): ?>
            <div class="io-card p-3 mb-4">
                <h2 class="h6 mb-2">Платежи</h2>
                <ul class="list-group list-group-flush">
                    <?php foreach ($payments as $p): ?>
                        <li class="list-group-item bg-transparent text-light d-flex justify-content-between align-items-center gap-3">
                            <span>Тариф «<?= htmlspecialchars($p['tariff_name']) ?>»
                                <span class="text-secondary small d-block"><?= htmlspecialchars(date('d.m.Y, H:i', strtotime($p['created_at']))) ?></span></span>
                            <span class="text-end text-nowrap">
                                <?= number_format((float)$p['amount'], 0, '', ' ') ?> ₽
                                <span class="io-pay-status io-pay-status--<?= io_e($p['status']) ?> ms-2"><?= io_e($payLabels[$p['status']] ?? $p['status']) ?></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require __DIR__ . '/src/components/footer.php'; ?>
