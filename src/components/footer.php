<?php
// =========================================================
// Подвал сайта
// =========================================================
$ioJsV = @filemtime(__DIR__ . '/../../public/js/main.js') ?: 1;
$ioAuth = isset($_SESSION['user']);
?>
<footer class="io-footer mt-auto">
    <div class="container">
        <div class="row g-4 io-footer-top">
            <div class="col-md-5">
                <div class="io-logo d-flex align-items-center gap-2 mb-2">
                    <svg class="io-logo-mark" width="22" height="22" viewBox="0 0 32 32" aria-hidden="true">
                        <path d="M16 16 L31 4 L31 28 Z" fill="currentColor" opacity=".28"/>
                        <circle cx="11" cy="16" r="8" fill="none" stroke="currentColor" stroke-width="3"/>
                        <circle cx="11" cy="16" r="2.6" fill="currentColor"/>
                    </svg>
                    <span>ILLUMINOS</span>
                </div>
                <p class="mb-0 small">Фильмы и сериалы онлайн: подписка, избранное и история просмотров в одном месте.</p>
            </div>
            <div class="col-6 col-md-3">
                <h2 class="io-footer-title">Смотреть</h2>
                <ul class="list-unstyled mb-0">
                    <li><a href="/index.php?type=film">Фильмы</a></li>
                    <li><a href="/index.php?type=serial">Сериалы</a></li>
                    <li><a href="/payment.php">Тарифы</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-4">
                <h2 class="io-footer-title">Аккаунт</h2>
                <ul class="list-unstyled mb-0">
                    <?php if ($ioAuth): ?>
                        <li><a href="/cabinet.php">Личный кабинет</a></li>
                    <?php else: ?>
                        <li><a href="/login.php">Вход</a></li>
                        <li><a href="/register.php">Регистрация</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="d-flex flex-wrap justify-content-between gap-2 io-footer-bottom">
            <span>© 2026 Illuminos — онлайн-кинотеатр</span>
            <span>Смотри. Слушай. Чувствуй.</span>
        </div>
    </div>
</footer>
<div class="io-toasts" id="io-toasts" aria-live="polite" aria-atomic="false"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/public/js/main.js?v=<?= (int)$ioJsV ?>"></script>
</body>
</html>
