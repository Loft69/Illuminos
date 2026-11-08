<?php
// =========================================================
// Общие ресурсы <head>: шрифты, Bootstrap 5, стили Illuminos.
// Версия файла стилей меняется вместе с файлом (сброс кэша).
// =========================================================
$ioCssV = @filemtime(__DIR__ . '/../../public/css/style.css') ?: 1;
?>
<meta name="theme-color" content="#0D0D1A">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Onest:wght@400;500;600;700&family=Unbounded:wght@500;700&display=swap" rel="stylesheet">
<!-- Bootstrap 5 (сетка) + собственные стили -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="/public/css/style.css?v=<?= (int)$ioCssV ?>">
