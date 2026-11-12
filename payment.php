<?php
// =========================================================
// Страница оплаты (Задание 6):
//   - перечень тарифов с описанием возможностей
//   - таблица сравнения тарифов
//   - выбор способа оплаты (платёжная система)
// После оплаты пользователь получает роль subscriber.
// =========================================================
require_once __DIR__ . '/backend/config.php';
require_once __DIR__ . '/src/components/helpers.php';
session_start();

$uid = $_SESSION['user']['id'] ?? 0;

// ---------- Оформление оплаты ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tariff_id'])) {
    if ($uid === 0) { header('Location: /login.php'); exit; }

    $tid = (int)$_POST['tariff_id'];
    $method = (string)($_POST['payment_method'] ?? '');
    $stmt = $pdo->prepare('SELECT * FROM tariffs WHERE id = ?');
    $stmt->execute([$tid]);
    $tariff = $stmt->fetch();
    $validMethods = ['card', 'sbp', 'yoomoney'];
    $valid = $tariff && in_array($method, $validMethods, true);

    if ($valid && $method === 'card') {
        $cardNumber = preg_replace('/\D+/', '', (string)($_POST['card_number'] ?? ''));
        $cardName = trim((string)($_POST['card_name'] ?? ''));
        $expiry = trim((string)($_POST['card_expiry'] ?? ''));
        $cvv = preg_replace('/\D+/', '', (string)($_POST['card_cvv'] ?? ''));
        $valid = strlen($cardNumber) >= 13 && strlen($cardNumber) <= 19
            && $cardName !== '' && preg_match('/^(0[1-9]|1[0-2])\/([0-9]{2})$/', $expiry)
            && strlen($cvv) >= 3 && strlen($cvv) <= 4;
    } elseif ($valid && $method === 'sbp') {
        $valid = trim((string)($_POST['sbp_bank'] ?? '')) !== '';
    }

    if ($valid) {
        // Данные карты используются только для проверки формы и не сохраняются.
        $paid = payTariff($pdo, $uid, $tariff);
        $_SESSION['flash_payment'] = $paid
            ? 'Оплата прошла успешно. Подписка активирована.'
            : 'Не удалось выполнить оплату. Попробуйте ещё раз.';
    } else {
        $_SESSION['flash_payment'] = 'Проверьте выбранный способ оплаты и заполненные данные.';
    }
    header('Location: /payment.php');
    exit;
}

$message = $_SESSION['flash_payment'] ?? '';
unset($_SESSION['flash_payment']);

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
        if ($pdo->inTransaction()) $pdo->rollBack();
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
// Выделяем предпоследний тариф (Стандарт) как популярный
$featuredIdx = count($tariffs) >= 2 ? count($tariffs) - 2 : -1;

$selectedTariff = null;
if (isset($_GET['tariff_id']) && $uid > 0) {
    $stmt = $pdo->prepare('SELECT * FROM tariffs WHERE id = ?');
    $stmt->execute([(int)$_GET['tariff_id']]);
    $selectedTariff = $stmt->fetch() ?: null;
}

// Способы оплаты (платёжные системы Задания 6)
$payMethods = [
    ['id' => 'card',   'name' => 'Банковская карта', 'hint' => 'Visa, Mastercard, МИР',
     'svg' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>'],
    ['id' => 'sbp',    'name' => 'СБП', 'hint' => 'Оплата по QR-коду из приложения банка',
     'svg' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM18 18h3v3h-3z"/>'],
    ['id' => 'yoomoney', 'name' => 'ЮMoney', 'hint' => 'Оплата электронным кошельком',
     'svg' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v10M9 10h6"/>'],
];

// Ответы на частые вопросы о подписке
$faq = [
    ['Можно ли сменить тариф?', 'Да. При оплате нового тарифа подписка продлевается на его срок — дни не сгорают, доступ расширяется сразу.'],
    ['Как списываются деньги?', 'Один раз при покупке подписки. Автосписания нет: по окончании срока подписка просто завершается.'],
    ['На каких устройствах работает подписка?', 'На всех: браузер, смартфон, планшет, Smart TV. Число одновременно работающих экранов зависит от тарифа.'],
    ['Можно ли вернуть деньги?', 'Если вы ещё ничего не успели посмотреть — да, в течение 14 дней по закону о защите прав потребителей.'],
];
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
    <h1 class="h3 mt-4 mb-1 io-form-title">Подписка Illuminos</h1>
    <p class="text-secondary mb-3">Все фильмы и сериалы без ограничений. Отменить можно в любой момент в личном кабинете.</p>

    <?php if ($message): $ok = str_starts_with($message, 'Оплата прошла успешно'); ?>
        <div class="alert <?= $ok ? 'alert-success io-alert-ok' : 'alert-danger' ?>" role="alert">
            <?php if ($ok): ?>
                <span class="io-check" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#06260f" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
            <?php endif; ?>
            <span><?= htmlspecialchars($message) ?>
                <?php if ($ok): ?><a href="/cabinet.php" class="alert-link ms-1">Открыть кабинет</a><?php endif; ?></span>
        </div>
    <?php endif; ?>

    <?php if ($current): ?>
        <div class="io-current-sub" role="status">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 11.5l2 2 4-4.5"/><circle cx="12" cy="12" r="9"/></svg>
            <span>Активен тариф «<?= htmlspecialchars($current['tariff_name']) ?>»
                до <?= htmlspecialchars(date('d.m.Y', strtotime($current['end_date']))) ?>.
                Покупка нового тарифа продлит подписку.</span>
            <a href="/cabinet.php" class="io-current-sub-link">Кабинет</a>
        </div>
    <?php endif; ?>

    <!-- Перечень тарифов и описание возможностей (Задание 6) -->
    <div class="row g-4 mt-1">
        <?php foreach ($tariffs as $i => $t):
            $isFeatured = ($i === $featuredIdx);
            $isOwn = $current && (int)$current['tariff_id'] === (int)$t['id'];
            $features = array_values(array_filter(array_map('trim', explode(',', (string)$t['description'])), 'strlen'));
            $priceText = number_format((float)$t['price'], 0, '', ' ');
        ?>
        <div class="col-md-6 col-xl-3">
            <div class="io-card io-tariff p-4 text-center h-100 d-flex flex-column<?= $isFeatured ? ' is-featured' : '' ?>">
                <?php if ($isOwn): ?><span class="io-tariff-flag io-tariff-flag--own">Ваш тариф</span>
                <?php elseif ($isFeatured): ?><span class="io-tariff-flag">Выбор зрителей</span><?php endif; ?>
                <h2 class="h5"><?= htmlspecialchars($t['name']) ?></h2>                <p class="display-6 io-tariff-price mb-1"><?= $priceText ?> ₽
                    <small class="fs-6 text-secondary">/ <?= (int)$t['duration_days'] ?> дней</small></p>
                <?php if ($features): ?>
                    <ul class="io-tariff-list">
                        <?php foreach ($features as $f): ?><li><?= htmlspecialchars($f) ?></li><?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-secondary flex-grow-1"><?= htmlspecialchars($t['description']) ?></p>
                <?php endif; ?>
                <?php if ($isOwn): ?>
                    <a href="/cabinet.php" class="btn btn-outline-light w-100">В кабинете</a>
                <?php elseif ($uid > 0): ?>
                    <a class="btn <?= $isFeatured ? 'btn-io' : 'btn-outline-light' ?> w-100" href="/payment.php?tariff_id=<?= (int)$t['id'] ?>#checkout">Оформить за <?= $priceText ?> ₽</a>
                <?php else: ?>
                    <a href="/login.php" class="btn <?= $isFeatured ? 'btn-io' : 'btn-outline-light' ?> w-100">Войти и оформить</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Сравнение тарифов -->
    <section class="io-section" id="compare" aria-labelledby="compare-title">
        <h2 class="io-section-title h4" id="compare-title">Сравнение тарифов</h2>
        <div class="io-compare">
            <div class="table-responsive">
                <table class="table io-compare-table align-middle">
                    <caption class="visually-hidden">Возможности тарифов подписки Illuminos</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="io-compare-first">Возможность</th>
                            <?php foreach ($tariffs as $t): ?>
                                <th scope="col"><?= htmlspecialchars($t['name']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Разбор описаний по ключам для строк сравнения
                        $rowDefs = [
                            ['Качество видео',    ['480p', '720p', '1080p', '4K']],
                            ['Экраны одновременно', ['1 экран', '1 устройство', '2 устройства', '4 устройства']],
                            ['Реклама',            ['реклама', 'без рекламы']],
                            ['Скачивание',         ['скачивание']],
                            ['Новинки раньше всех', ['ранний доступ']],
                            ['Звук',               ['Dolby Atmos', 'пространственный']],
                        ];
                        foreach ($rowDefs as [$label, $needles]):
                            // считается, что возможность есть, если в описании найдено любое слово-маркер строки
                            $cells = [];
                            foreach ($tariffs as $t) {
                                $d = mb_strtolower((string)$t['description']);
                                $hit = false;
                                foreach ($needles as $n) { if (mb_stripos($d, $n) !== false) { $hit = true; break; } }
                                $cells[] = $hit;
                            }
                            if (!array_filter($cells)) continue; // строка неактуальна — пропускаем
                        ?>
                        <tr>
                            <th scope="row" class="io-compare-first"><?= $label ?></th>
                            <?php foreach ($cells as $hit): ?>
                                <?php if ($hit): ?>
                                    <td><span class="io-yes" role="img" aria-label="Да"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span></td>
                                <?php else: ?>
                                    <td><span class="io-no" aria-hidden="true">—</span></td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="io-compare-price">
                            <th scope="row" class="io-compare-first">Цена за <?= (int)$tariffs[0]['duration_days'] ?> дней</th>
                            <?php foreach ($tariffs as $t): ?>
                                <td><?= number_format((float)$t['price'], 0, '', ' ') ?> ₽</td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- Способы оплаты (платёжные системы) -->
    <section class="io-section" aria-labelledby="pay-methods-title">
        <h2 class="io-section-title h4" id="pay-methods-title">Способы оплаты</h2>
        <div class="row g-3">
            <?php foreach ($payMethods as $m): ?>
            <div class="col-md-4">
                <div class="io-pay-method">
                    <span class="io-pay-method-icon" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $m['svg'] ?></svg>
                    </span>
                    <span>
                        <strong><?= htmlspecialchars($m['name']) ?></strong>
                        <span class="d-block text-secondary small"><?= htmlspecialchars($m['hint']) ?></span>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="text-secondary small mt-3 mb-0">Выберите удобный способ оплаты при оформлении подписки.</p>
    </section>

    <!-- Частые вопросы -->
    <section class="io-section" aria-labelledby="faq-title">
        <h2 class="io-section-title h4" id="faq-title">Частые вопросы</h2>
        <div class="io-faq">
            <?php foreach ($faq as $j => $f): ?>
            <div class="io-faq-item">
                <h3 class="io-faq-q">
                    <button type="button" class="io-faq-btn" data-bs-toggle="collapse" data-bs-target="#faq<?= $j ?>"
                            aria-expanded="false" aria-controls="faq<?= $j ?>">
                        <?= htmlspecialchars($f[0]) ?>
                        <span class="io-faq-chev" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg></span>
                    </button>
                </h3>
                <div class="collapse" id="faq<?= $j ?>"><p class="io-faq-a mb-0"><?= htmlspecialchars($f[1]) ?></p></div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<?php if ($selectedTariff): ?>
<section class="io-section" id="checkout" aria-labelledby="checkout-title">
    <div class="io-checkout-card">
        <div class="io-checkout-heading">
            <div><span class="text-secondary small">Оформление заказа</span><h2 class="h4 mb-1" id="checkout-title" tabindex="-1">Оплата подписки</h2><p class="text-secondary mb-0">Тариф «<?= htmlspecialchars($selectedTariff['name']) ?>» · <?= (int)$selectedTariff['duration_days'] ?> дней</p></div>
            <div class="io-checkout-total"><span>К оплате</span><strong><?= number_format((float)$selectedTariff['price'], 0, '', ' ') ?> ₽</strong></div>
        </div>
        <form method="post" id="checkoutForm" autocomplete="on">
            <input type="hidden" name="tariff_id" value="<?= (int)$selectedTariff['id'] ?>">
            <h3 class="h6 mb-3">Выберите способ оплаты</h3>
            <div class="io-checkout-methods">
                <label class="io-checkout-method"><input type="radio" name="payment_method" value="card" checked><span class="io-checkout-method-logo">▰</span><span><strong>Банковская карта</strong><small>МИР, Visa, Mastercard</small></span></label>
                <label class="io-checkout-method"><input type="radio" name="payment_method" value="sbp"><span class="io-checkout-method-logo">⌗</span><span><strong>СБП</strong><small>Оплата через приложение банка</small></span></label>
                <label class="io-checkout-method"><input type="radio" name="payment_method" value="yoomoney"><span class="io-checkout-method-logo">Ю</span><span><strong>ЮMoney</strong><small>Электронный кошелёк</small></span></label>
            </div>

            <div class="io-payment-fields mt-4" data-payment-fields="card">
                <h3 class="h6 mb-3">Данные карты</h3>
                <div class="row g-3">
                    <div class="col-12"><label class="form-label" for="cardNumber">Номер карты</label><input class="form-control" id="cardNumber" name="card_number" inputmode="numeric" autocomplete="cc-number" placeholder="0000 0000 0000 0000" maxlength="23" required></div>
                    <div class="col-md-6"><label class="form-label" for="cardName">Имя владельца</label><input class="form-control text-uppercase" id="cardName" name="card_name" autocomplete="cc-name" placeholder="IVAN IVANOV" maxlength="80" required></div>
                    <div class="col-6 col-md-3"><label class="form-label" for="cardExpiry">Срок действия</label><input class="form-control" id="cardExpiry" name="card_expiry" inputmode="numeric" autocomplete="cc-exp" placeholder="ММ/ГГ" maxlength="5" required></div>
                    <div class="col-6 col-md-3"><label class="form-label" for="cardCvv">CVC / CVV</label><input class="form-control" id="cardCvv" name="card_cvv" type="password" inputmode="numeric" autocomplete="cc-csc" placeholder="•••" maxlength="4" required></div>
                </div>
            </div>

            <div class="io-payment-fields mt-4" data-payment-fields="sbp" hidden>
                <h3 class="h6 mb-3">Выберите банк</h3>
                <select class="form-select" name="sbp_bank" id="sbpBank" disabled><option value="">Выберите ваш банк</option><option>СберБанк</option><option>Т-Банк</option><option>ВТБ</option><option>Альфа-Банк</option><option>Газпромбанк</option><option>Другой банк</option></select>
                <p class="text-secondary small mt-3 mb-0">Подтвердите оплату в приложении выбранного банка.</p>
            </div>

            <div class="io-payment-fields mt-4" data-payment-fields="yoomoney" hidden>
                <h3 class="h6 mb-2">Оплата через ЮMoney</h3>
                <p class="text-secondary mb-0">Подтвердите платёж выбранным способом.</p>
            </div>
            <div class="io-payment-secure mt-4"><span aria-hidden="true">✓</span> Данные банковской карты не сохраняются</div>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4">
                <a href="/payment.php" class="btn btn-outline-light">Изменить тариф</a>
                <button type="submit" class="btn btn-io px-4" id="checkoutSubmit">Оплатить <?= number_format((float)$selectedTariff['price'], 0, '', ' ') ?> ₽</button>
            </div>
        </form>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/src/components/footer.php'; ?>
