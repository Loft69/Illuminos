<?php
// =========================================================
// Общие функции представления: постеры, карточки, ленты.
// Подключается на страницах, где выводится контент.
// =========================================================

/** Экранирование вывода */
function io_e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/** Длина строки в символах (UTF-8) */
function io_strlen(string $s): int {
    return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : (int)preg_match_all('/./us', $s);
}

/** Ссылка на страницу фильма или сериала */
function io_link(array $c): string {
    return ($c['type'] === 'serial' ? '/serial.php?id=' : '/movie.php?id=') . (int)$c['id'];
}

function io_type_label(string $type): string {
    return $type === 'serial' ? 'Сериал' : 'Фильм';
}

/** Склонение: io_plural(3, ['эпизод','эпизода','эпизодов']) */
function io_plural(int $n, array $forms): string {
    $n = abs($n) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) return $forms[2];
    if ($n1 > 1 && $n1 < 5) return $forms[1];
    if ($n1 === 1) return $forms[0];
    return $forms[2];
}

/** Пара оттенков постера: зависит от жанра, чуть сдвигается по id */
function io_poster_hues(array $c): array {
    static $pairs = [[262, 228], [318, 262], [8, 336], [196, 250], [28, 352], [292, 236], [158, 206], [46, 14]];
    $pair  = $pairs[(int)($c['genre_id'] ?? 0) % count($pairs)];
    $shift = (((int)($c['id'] ?? 0)) * 11) % 28 - 14;
    return [($pair[0] + $shift + 360) % 360, $pair[1]];
}

/** Inline-стиль с CSS-переменными оттенков постера */
function io_poster_style(array $c): string {
    [$h1, $h2] = io_poster_hues($c);
    return '--h1:' . $h1 . ';--h2:' . $h2 . ';';
}

/** Первая буква названия — крупный знак на постере */
function io_initial(string $title): string {
    if (preg_match('/[\p{L}\p{N}]/u', $title, $m)) {
        return function_exists('mb_strtoupper') ? mb_strtoupper($m[0], 'UTF-8') : $m[0];
    }
    return '★';
}

/** Постер: если есть картинка poster_url, показываем её, иначе стильный градиент */
function io_poster(array $c, string $class = ''): string {
    $hasImage = !empty($c['poster_url']);
    ob_start(); ?>
    <div class="io-poster<?= $hasImage ? ' has-image' : '' ?><?= $class !== '' ? ' ' . io_e($class) : '' ?>" style="<?= io_poster_style($c) ?>">
        <?php if ($hasImage): ?>
            <img class="io-poster-img" src="<?= io_e($c['poster_url']) ?>" alt="<?= io_e($c['title']) ?>" loading="lazy">
        <?php else: ?>
            <span class="io-poster-glyph" aria-hidden="true"><?= io_e(io_initial((string)$c['title'])) ?></span>
        <?php endif; ?>
        <?php if (($c['type'] ?? '') === 'serial'): ?><span class="io-poster-type">Сериал</span><?php endif; ?>
        <span class="io-poster-title"><?= io_e($c['title']) ?></span>
        <span class="io-rating"><?= io_e($c['rating']) ?></span>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Кнопка «В избранное». Обработчик — public/js/main.js (делегирование).
 * Для гостей кнопка тоже выводится: клик предложит войти.
 */
function io_fav_button(int $id, bool $inFav, bool $loggedIn, ?string $title = null, string $class = '', bool $withLabel = false): string {
    $title = $title ?? ($inFav ? 'Убрать из избранного' : 'В избранное');
    ob_start(); ?>
    <button type="button" class="io-fav-btn<?= $inFav ? ' active' : '' ?><?= $class !== '' ? ' ' . io_e($class) : '' ?>"
            data-id="<?= $id ?>"<?= $loggedIn ? '' : ' data-guest="1"' ?>
            title="<?= io_e($title) ?>" aria-pressed="<?= $inFav ? 'true' : 'false' ?>"><?php
        if ($withLabel): ?><span data-fav-icon><?= $inFav ? '♥' : '♡' ?></span> <span data-fav-label><?= $inFav ? 'В избранном' : 'В избранное' ?></span><?php
        else: ?><?= $inFav ? '♥' : '♡' ?><?php endif; ?></button>
    <?php
    return ob_get_clean();
}

/** Карточка контента (постер, название, год · жанр, избранное) */
function io_card(array $c, bool $inFav = false): string {
    $loggedIn = !empty($_SESSION['user']);
    ob_start(); ?>
    <article class="io-card">
        <a href="<?= io_link($c) ?>" class="io-card-link text-decoration-none text-reset">
            <?= io_poster($c) ?>
            <div class="io-card-body">
                <div class="io-card-title"><?= io_e($c['title']) ?></div>
                <div class="io-card-meta"><?= io_e($c['year']) ?> · <?= io_e($c['genre_name'] ?? '') ?></div>
            </div>
        </a>
        <?= io_fav_button((int)$c['id'], $inFav, $loggedIn) ?>
    </article>
    <?php
    return ob_get_clean();
}

/** Пустое состояние: что произошло и что можно сделать */
function io_empty(string $text, string $hint = '', string $actionHtml = ''): string {
    $html = '<div class="io-empty"><p class="mb-1">' . io_e($text) . '</p>';
    if ($hint !== '') $html .= '<p class="text-secondary small mb-0">' . io_e($hint) . '</p>';
    if ($actionHtml !== '') $html .= '<div class="mt-3">' . $actionHtml . '</div>';
    return $html . '</div>';
}

/** Сетка карточек (каталог, избранное, похожие) */
function render_cards(array $items, array $favIds, string $emptyHint = '', string $emptyAction = ''): void {
    if (!$items) { echo io_empty('Ничего не найдено.', $emptyHint, $emptyAction); return; }
    $favSet = array_flip(array_map('intval', $favIds));
    echo '<div class="row g-3">';
    foreach ($items as $c) {
        echo '<div class="col-6 col-sm-4 col-md-3 col-xl-2">', io_card($c, isset($favSet[(int)$c['id']])), '</div>';
    }
    echo '</div>';
}

/** Горизонтальная лента карточек со стрелками (прототип: «лента карточек») */
function render_rail(array $items, array $favIds, string $label, string $emptyHint = ''): void {
    if (!$items) { echo io_empty('Ничего не найдено.', $emptyHint); return; }
    $favSet = array_flip(array_map('intval', $favIds));
    $chev = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>';
    ?>
    <div class="io-rail" data-rail>
        <button type="button" class="io-rail-btn io-rail-btn--prev" data-rail-prev aria-label="Назад" disabled><?= $chev ?></button>
        <div class="io-rail-track" role="list" aria-label="<?= io_e($label) ?>">
            <?php foreach ($items as $c): ?>
                <div class="io-rail-item" role="listitem"><?= io_card($c, isset($favSet[(int)$c['id']])) ?></div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="io-rail-btn io-rail-btn--next" data-rail-next aria-label="Вперёд"><?= $chev ?></button>
    </div>
    <?php
}

/** ID избранного пользователя */
function io_fav_ids(PDO $pdo, int $uid): array {
    if ($uid <= 0) return [];
    $st = $pdo->prepare('SELECT content_id FROM favorites WHERE user_id = ?');
    $st->execute([$uid]);
    return array_map('intval', array_column($st->fetchAll(), 'content_id'));
}

/**
 * Запись просмотра в историю. Повторное открытие страницы в течение
 * 10 минут не создаёт дубль (раньше каждое обновление добавляло строку).
 */
function io_log_view(PDO $pdo, int $uid, int $contentId): void {
    if ($uid <= 0) return;
    $chk = $pdo->prepare('SELECT 1 FROM watch_history
                          WHERE user_id = ? AND content_id = ? AND watched_at > (NOW() - INTERVAL 10 MINUTE) LIMIT 1');
    $chk->execute([$uid, $contentId]);
    if (!$chk->fetch()) {
        $pdo->prepare('INSERT INTO watch_history (user_id, content_id) VALUES (?, ?)')->execute([$uid, $contentId]);
    }
}

/** Шапка страницы фильма/сериала: постер, описание, режиссёр, актёры, действия */
function render_detail(array $c, bool $inFav): void {
    $loggedIn = !empty($_SESSION['user']);
    $actors   = array_values(array_filter(array_map('trim', explode(',', (string)$c['actors'])), 'strlen'));
    $pct      = round(max(0, min(100, (float)$c['rating'] * 10)), 1);
    ?>
    <div class="io-detail mt-4" style="<?= io_poster_style($c) ?>">
        <div class="row g-4 position-relative">
            <div class="col-md-4">
                <?= io_poster($c, 'io-poster--lg') ?>
            </div>
            <div class="col-md-8">
                <h1 class="io-detail-title"><?= io_e($c['title']) ?></h1>
                <p class="text-secondary mb-2"><?= io_e($c['year']) ?> · <?= io_e($c['genre_name']) ?> ·
                    <strong class="text-warning"><?= io_e($c['rating']) ?></strong> / 10</p>
                <div class="io-meter" role="img" aria-label="Рейтинг <?= io_e($c['rating']) ?> из 10"><span style="--w:<?= $pct ?>%"></span></div>
                <p class="io-detail-text"><?= nl2br(htmlspecialchars((string)$c['description'])) ?></p>
                <p class="mb-2"><strong>Режиссёр:</strong> <?= io_e($c['director']) ?></p>
                <p class="mb-3"><strong>В ролях:</strong>
                    <?php foreach ($actors as $a): ?><span class="io-tag"><?= io_e($a) ?></span> <?php endforeach; ?>
                    <?php if (!$actors): ?><?= io_e($c['actors']) ?><?php endif; ?>
                </p>
                <div class="io-actions">
                    <?php if (!empty($c['trailer_url'])): ?>
                        <a href="#trailer" class="btn btn-io">Смотреть трейлер</a>
                    <?php endif; ?>
                    <?= io_fav_button((int)$c['id'], $inFav, $loggedIn, null, 'io-fav-btn--inline', true) ?>
                    <button type="button" class="btn btn-outline-light" data-share>Поделиться</button>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/** Блок трейлера */
function render_trailer(array $c): void {
    if (empty($c['trailer_url'])) return; ?>
    <section class="io-section" id="trailer">
        <h2 class="io-section-title h4">Трейлер</h2>
        <div class="ratio ratio-16x9 io-trailer" style="max-width: 800px;">
            <iframe src="<?= io_e($c['trailer_url']) ?>" allowfullscreen loading="lazy"
                    title="Трейлер: <?= io_e($c['title']) ?>"></iframe>
        </div>
    </section>
    <?php
}
