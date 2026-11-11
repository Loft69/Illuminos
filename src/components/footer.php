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
            <div class="col-md-4">
                <div class="io-logo d-flex align-items-center gap-2 mb-2">
                    <span class="io-logo-icon">▶</span>
                    <span>Illuminos</span>
                </div>
                <p class="mb-0 small">Онлайн-кинотеатр нового поколения. Фильмы, сериалы, подписки и персональные рекомендации.</p>
            </div>
            <div class="col-6 col-md-2">
                <h2 class="io-footer-title">Разделы</h2>
                <ul class="list-unstyled mb-0">
                    <li><a href="/index.php">Главная</a></li>
                    <li><a href="/index.php?type=film">Фильмы</a></li>
                    <li><a href="/index.php?type=serial">Сериалы</a></li>
                </ul>
            </div>
            <div class="col-6 col-md-3">
                <h2 class="io-footer-title">Сервис</h2>
                <ul class="list-unstyled mb-0">
                    <li><a href="/payment.php">Подписка</a></li>
                    <?php if ($ioAuth): ?>
                        <li><a href="/cabinet.php">Личный кабинет</a></li>
                    <?php else: ?>
                        <li><a href="/login.php">Вход</a></li>
                        <li><a href="/register.php">Регистрация</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="col-md-3">
                <h2 class="io-footer-title">О проекте</h2>
                <p class="small mb-0" style="color: var(--io-muted);">Illuminos — учебный проект онлайн-кинотеатра. Все материалы используются в образовательных целях.</p>
            </div>
        </div>
        <div class="d-flex flex-wrap justify-content-between gap-2 io-footer-bottom">
            <span>© 2026 Illuminos</span>
            <span>Сделано с ❤️</span>
        </div>
    </div>
</footer>
<div class="io-toasts" id="io-toasts" aria-live="polite" aria-atomic="false"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/public/js/main.js?v=<?= (int)$ioJsV ?>"></script>
</body>
</html>
