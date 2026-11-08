<?php
// =========================================================
// Панель администратора: добавление, редактирование, удаление
// контента (фильмы и сериалы). Доступ только для role = admin.
// =========================================================
require_once __DIR__ . '/backend/config.php';
require_once __DIR__ . '/src/components/helpers.php';
session_start();

$user = $_SESSION['user'] ?? null;
if (!$user || ($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die('Доступ запрещён. Требуются права администратора.');
}

$genres = $pdo->query('SELECT * FROM genres ORDER BY name')->fetchAll();
$genreMap = [];
foreach ($genres as $g) { $genreMap[$g['id']] = $g['name']; }

$action = $_GET['action'] ?? '';
$error = '';
$success = '';

// ---------- Удаление ----------
if ($action === 'delete' && isset($_GET['id'])) {
    $delId = (int)$_GET['id'];
    try {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM watch_history WHERE content_id = ?')->execute([$delId]);
        $pdo->prepare('DELETE FROM favorites WHERE content_id = ?')->execute([$delId]);
        $pdo->prepare('DELETE FROM episodes WHERE season_id IN (SELECT id FROM seasons WHERE content_id = ?)')->execute([$delId]);
        $pdo->prepare('DELETE FROM seasons WHERE content_id = ?')->execute([$delId]);
        $pdo->prepare('DELETE FROM content WHERE id = ?')->execute([$delId]);
        $pdo->commit();
        header('Location: /admin.php?msg=deleted');
        exit;
    } catch (Throwable $e) {
        $pdo->rollBack();
        $error = 'Ошибка при удалении: ' . $e->getMessage();
    }
}

// ---------- Добавление / Редактирование ----------
$editId = (int)($_GET['edit'] ?? 0);
$editItem = null;
if ($editId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM content WHERE id = ?');
    $stmt->execute([$editId]);
    $editItem = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $type = in_array($_POST['type'] ?? '', ['film', 'serial'], true) ? $_POST['type'] : 'film';
    $title = trim((string)($_POST['title'] ?? ''));
    $desc = trim((string)($_POST['description'] ?? ''));
    $year = (int)($_POST['year'] ?? 0);
    $rating = (float)($_POST['rating'] ?? 0);
    $director = trim((string)($_POST['director'] ?? ''));
    $actors = trim((string)($_POST['actors'] ?? ''));
    $poster = trim((string)($_POST['poster_url'] ?? ''));
    $trailer = trim((string)($_POST['trailer_url'] ?? ''));
    $is_new = isset($_POST['is_new']) ? 1 : 0;
    $is_pop = isset($_POST['is_popular']) ? 1 : 0;
    $genre_id = (int)($_POST['genre_id'] ?? 0);

    if ($title === '') {
        $error = 'Укажите название фильма или сериала.';
    } else {
        if ($id > 0) {
            // Обновление
            $stmt = $pdo->prepare('UPDATE content SET type=?, title=?, description=?, year=?, rating=?, director=?, actors=?, poster_url=?, trailer_url=?, is_new=?, is_popular=?, genre_id=? WHERE id=?');
            $stmt->execute([$type, $title, $desc, $year, $rating, $director, $actors, $poster, $trailer, $is_new, $is_pop, $genre_id, $id]);
            header('Location: /admin.php?msg=updated');
            exit;
        } else {
            // Добавление
            $stmt = $pdo->prepare('INSERT INTO content (type, title, description, year, rating, director, actors, poster_url, trailer_url, is_new, is_popular, genre_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$type, $title, $desc, $year, $rating, $director, $actors, $poster, $trailer, $is_new, $is_pop, $genre_id]);
            header('Location: /admin.php?msg=created');
            exit;
        }
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deleted') $success = 'Запись успешно удалена.';
    if ($_GET['msg'] === 'updated') $success = 'Данные успешно обновлены.';
    if ($_GET['msg'] === 'created') $success = 'Новый контент успешно добавлен.';
}

$items = $pdo->query('SELECT c.*, g.name AS genre_name FROM content c LEFT JOIN genres g ON g.id = c.genre_id ORDER BY c.id DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Панель администратора — Illuminos</title>
    <?php require __DIR__ . '/src/components/head-assets.php'; ?>
</head>
<body class="d-flex flex-column min-vh-100">
<?php require __DIR__ . '/src/components/header.php'; ?>

<main class="container flex-grow-1 py-4" id="main">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Панель администратора</h1>
        <a href="/cabinet.php" class="btn btn-outline-light btn-sm">В личный кабинет</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success io-alert-ok" role="alert"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Форма добавления / редактирования -->
    <div class="card bg-dark text-light border-secondary mb-5">
        <div class="card-header border-secondary d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0"><?= $editItem ? 'Редактировать: ' . htmlspecialchars($editItem['title']) : 'Добавить фильм или сериал' ?></h2>
            <?php if ($editItem): ?>
                <a href="/admin.php" class="btn btn-sm btn-outline-secondary">Отменить редактирование</a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form method="post" action="/admin.php">
                <input type="hidden" name="id" value="<?= (int)($editItem['id'] ?? 0) ?>">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Тип</label>
                        <select name="type" class="form-select bg-dark text-light border-secondary">
                            <option value="film" <?= ($editItem['type'] ?? '') === 'film' ? 'selected' : '' ?>>Фильм</option>
                            <option value="serial" <?= ($editItem['type'] ?? '') === 'serial' ? 'selected' : '' ?>>Сериал</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Название *</label>
                        <input type="text" name="title" class="form-control bg-dark text-light border-secondary" required value="<?= htmlspecialchars($editItem['title'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Жанр</label>
                        <select name="genre_id" class="form-select bg-dark text-light border-secondary">
                            <?php foreach ($genres as $g): ?>
                                <option value="<?= $g['id'] ?>" <?= ((int)($editItem['genre_id'] ?? 0) === (int)$g['id']) ? 'selected' : '' ?>><?= htmlspecialchars($g['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Год</label>
                        <input type="number" name="year" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars($editItem['year'] ?? 2024) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Рейтинг</label>
                        <input type="number" step="0.1" name="rating" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars($editItem['rating'] ?? '8.0') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Режиссёр</label>
                        <input type="text" name="director" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars($editItem['director'] ?? '') ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Актёры (через запятую)</label>
                        <input type="text" name="actors" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars($editItem['actors'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Ссылка на обложку (poster_url)</label>
                        <input type="text" name="poster_url" class="form-control bg-dark text-light border-secondary" placeholder="/public/img/posters/..." value="<?= htmlspecialchars($editItem['poster_url'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Ссылка на трейлер (YouTube embed)</label>
                        <input type="text" name="trailer_url" class="form-control bg-dark text-light border-secondary" placeholder="https://www.youtube.com/embed/..." value="<?= htmlspecialchars($editItem['trailer_url'] ?? '') ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Описание</label>
                        <textarea name="description" rows="3" class="form-control bg-dark text-light border-secondary"><?= htmlspecialchars($editItem['description'] ?? '') ?></textarea>
                    </div>

                    <div class="col-12 d-flex gap-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_new" id="chkNew" <?= !empty($editItem['is_new']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="chkNew">Новинка</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_popular" id="chkPop" <?= !empty($editItem['is_popular']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="chkPop">Популярное</label>
                        </div>
                    </div>

                    <div class="col-12 mt-3">
                        <button type="submit" class="btn btn-io"><?= $editItem ? 'Сохранить изменения' : 'Добавить' ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Список контента -->
    <h2 class="h5 mb-3">Список всех фильмов и сериалов (<?= count($items) ?>)</h2>
    <div class="table-responsive">
        <table class="table table-dark table-striped align-middle border-secondary">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Обложка</th>
                    <th>Название</th>
                    <th>Тип</th>
                    <th>Жанр</th>
                    <th>Год</th>
                    <th>Рейтинг</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $row): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td style="width: 50px;">
                        <?php if (!empty($row['poster_url'])): ?>
                            <img src="<?= htmlspecialchars($row['poster_url']) ?>" alt="" style="width: 45px; height: 60px; object-fit: cover; border-radius: 4px;">
                        <?php else: ?>
                            <div style="width: 45px; height: 60px; background: #333; border-radius: 4px;"></div>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= htmlspecialchars($row['title']) ?></strong></td>
                    <td><span class="badge bg-secondary"><?= $row['type'] === 'serial' ? 'Сериал' : 'Фильм' ?></span></td>
                    <td><?= htmlspecialchars($row['genre_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['year']) ?></td>
                    <td>★ <?= htmlspecialchars($row['rating']) ?></td>
                    <td>
                        <a href="/admin.php?edit=<?= $row['id'] ?>" class="btn btn-sm btn-outline-info">Редактировать</a>
                        <a href="/admin.php?action=delete&id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Удалить «<?= htmlspecialchars($row['title']) ?>»?');">Удалить</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<?php require __DIR__ . '/src/components/footer.php'; ?>
</body>
</html>
