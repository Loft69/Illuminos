<?php
// =========================================================
// Страница оплаты: перечень тарифов, описание возможностей,
// оплата через платёжную систему (точка интеграции — payTariff).
// После оплаты пользователь получает роль subscriber.
// =========================================================
require_once __DIR__ . '/backend/config.php';
require_once __DIR__ . '/src/components/helpers.php';
session_start();

$uid = $_SESSION['user']['id'] ?? 0;

// ---------- Оплата тарифа ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tariff_id'])) {
    if ($uid === 0) { header('Location: /login.php'); exit; }
    $tid = (int)$_POST['tariff_id'];
    $stmt = $pdo->prepare('SELECT * FROM tariffs WHERE id = ?');
    $stmt->execute([$tid]);
    $tariff = $stmt->fetch();

    if ($tariff) {
        // Точка интеграции платёжной системы (ЮKassa / Robokassa):
        // здесь создаётся платёж на стороне платёжного шлюза, после
        // webhook-уведомления об успехе заказ помечается оплаченным.
        $paid = payTariff($pdo, $uid, $tariff);
        $_SESSION['flash_payment'] = $paid ? 'Оплата прошла успешно. Приятного просмотра!' : 'Ошибка оплаты. Попробуйте позже.';
    }
    // Post/Redirect/Get: обновление страницы не повторит платёж
    header('Location: /payment.php');
    exit;
}

// Сообщение о результате оплаты (показывается один раз)
$message = $_SESSION['flash_payment'] ?? '';
unset($_SESSION['flash_payment']);

/**
 * Оплата тарифа: запись платежа, выдача роли subscriber, активация подписки.
 * В учебном проекте платёж считается успешным; в боевом варианте здесь
 * проверяется подпись webhook платёжной системы (ЮKassa, Robokassa и т.п.).
 */
function payTariff(PDO $pdo, int $uid, array $tariff): bool {
    try {
        $pdo->beginTransaction();
        $pdo->prepare("INSERT INTO payments (user_id, tariff_id, amount, status) VALUES (?, ?, ?, 'paid')")
            ->execute([$uid, $tariff['id'], $tariff['price']]);
        $pdo->prepare("UPDATE users SET role = 'subscriber' WHERE id = ?")->execute([$uid]);
        $pdo->prepare(
            'INSERT INTO user_subscriptions (user_id, tariff_id, start_date, end_date)
             VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY))')
            ->execute([$uid, $tariff['id'], $tariff['duration_days']]);
        $pdo->commit();
        $_SESSION['user']['role'] = 'subscriber';
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        return false;
    }
}

$tariffs = $pdo->query('SELECT * FROM tariffs ORDER BY price')->fetchAll();

// Текущая подписка (чтобы отметить «Ваш тариф»)
$current = null;
if ($uid > 0) {
    $stmt = $pdo->prepare(
        'SELECT us.tariff_id, us.end_date, t.name AS tariff_name FROM user_subscriptions us
         JOIN tariffs t ON t.id = us.tariff_id
         WHERE us.user_id = ? AND us.end_date >= CURDATE()
         ORDER BY us.end_date DESC LIMIT 1');
    $stmt->execute([$uid]);
    $current = $stmt->fetch() ?: null;
}
// Выделяем средний тариф как популярный (если тарифов три и больше)
$featuredIdx = count($tariffs) >= 3 ? intdiv(count($tariffs), 2) : -1;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Оплата подписки — Illuminos</title>
    <?php require __DIR__ . '/src/components/head-assets.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
<?php require __DIR__ . '/src/components/header.php'; ?>

<main class="container flex-grow-1" id="main">
    <h1 class="h3 mt-4 mb-1 io-form-title">Выберите тариф</h1>
    <p class="text-secondary">Оплата через платёжную систему (ЮKassa / Robokassa).</p>

    <?php if ($message): $ok = str_starts_with($message, 'Оплата'); ?>
        <div class="alert <?= $ok ? 'alert-success io-alert-ok' : 'alert-danger' ?>" role="alert">
            <?php if ($ok): ?>
                <span class="io-check" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#06260f" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
            <?php endif; ?>
            <span><?= htmlspecialchars($message) ?>
                <?php if ($ok): ?><a href="/cabinet.php" class="alert-link ms-1">Открыть кабинет</a><?php endif; ?></span>
        </div>
    <?php endif; ?>

    <?php if ($current): ?>
        <p class="text-secondary">Сейчас активен тариф «<?= htmlspecialchars($current['tariff_name']) ?>»
            до <?= htmlspecialchars(date('d.m.Y', strtotime($current['end_date']))) ?>.</p>
    <?php endif; ?>

    <!-- Перечень тарифов и описание возможностей (Задание 6) -->
    <div class="row g-4 mt-1">
        <?php foreach ($tariffs as $i => $t):
            $isFeatured = ($i === $featuredIdx);
            $isOwn = $current && (int)$current['tariff_id'] === (int)$t['id'];
            $features = array_values(array_filter(array_map('trim', explode(',', (string)$t['description'])), 'strlen'));
            $priceText = number_format((float)$t['price'], 0, '', ' ');
        ?>
        <div class="col-md-4">
            <div class="io-card io-tariff p-4 text-center h-100 d-flex flex-column<?= $isFeatured ? ' is-featured' : '' ?>">
                <?php if ($isOwn): ?><span class="io-tariff-flag io-tariff-flag--own">Ваш тариф</span>
                <?php elseif ($isFeatured): ?><span class="io-tariff-flag">Популярный</span><?php endif; ?>
                <h2 class="h5"><?= htmlspecialchars($t['name']) ?></h2>
                <p class="display-6 io-tariff-price mb-1"><?= $priceText ?> ₽
                    <small class="fs-6 text-secondary">/ мес</small></p>
                <?php if ($features): ?>
                    <ul class="io-tariff-list">
                        <?php foreach ($features as $f): ?><li><?= htmlspecialchars($f) ?></li><?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-secondary flex-grow-1"><?= htmlspecialchars($t['description']) ?></p>
                <?php endif; ?>
                <?php if ($uid > 0): ?>
                    <form method="post" class="js-pay-form" data-name="<?= htmlspecialchars($t['name']) ?>" data-price="<?= $priceText ?> ₽">
                        <input type="hidden" name="tariff_id" value="<?= $t['id'] ?>">
                        <button class="btn btn-io w-100" type="submit">Купить</button>
                    </form>
                <?php else: ?>
                    <a href="/login.php" class="btn btn-outline-light w-100">Войти для покупки</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</main>

<!-- Подтверждение покупки -->
<div class="modal fade io-modal" id="payModal" tabindex="-1" aria-labelledby="payModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="payModalLabel">Подтвердите покупку</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1">Тариф «<span data-pay-name></span>»</p>
                <p class="io-pay-sum mb-2" data-pay-price></p>
                <p class="text-secondary small mb-0">Подписка будет активирована сразу после оплаты.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-io" data-pay-confirm>Подтвердить и оплатить</button>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/src/components/footer.php'; ?>
