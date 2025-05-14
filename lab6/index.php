<?php
require 'db.php';

// Создаем таблицу администраторов, если ее нет
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");

    // Добавляем администратора по умолчанию, если таблица пуста
    $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
    if ($stmt->fetchColumn() == 0) {
        $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)")
            ->execute(['admin', $passwordHash]);
    }
} catch (PDOException $e) {
    die("Ошибка инициализации администраторов: " . $e->getMessage());
}

// HTTP-аутентификация
if (empty($_SERVER['PHP_AUTH_USER'])) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    die('Требуется авторизация');
}

// Проверка логина и пароля администратора
try {
    $stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE username = ?");
    $stmt->execute([$_SERVER['PHP_AUTH_USER']]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($_SERVER['PHP_AUTH_PW'], $admin['password_hash'])) {
        header('HTTP/1.1 401 Unauthorized');
        header('WWW-Authenticate: Basic realm="Admin Panel"');
        die('Неверные логин или пароль');
    }
} catch (PDOException $e) {
    die("Ошибка аутентификации: " . $e->getMessage());
}

// Обработка действий администратора
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

try {
    // Удаление заявки
    if ($action === 'delete' && $id) {
        $pdo->prepare("DELETE FROM applications WHERE id = ?")->execute([$id]);
        header("Location: index.php");
        exit();
    }

    // Обновление заявки
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("UPDATE applications SET
            name = ?, phone = ?, email = ?, birthdate = ?,
            gender = ?, bio = ?, contract_accepted = ?
            WHERE id = ?");

        $stmt->execute([
            $_POST['name'],
            $_POST['phone'],
            $_POST['email'],
            $_POST['birthdate'],
            $_POST['gender'],
            $_POST['bio'],
            isset($_POST['agreement']) ? 1 : 0,
            $_POST['id']
        ]);

        // Обновляем языки программирования
        $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")
            ->execute([$_POST['id']]);

        $lang_stmt = $pdo->prepare("INSERT INTO application_languages
            (application_id, language_id) SELECT ?, id FROM languages WHERE name = ?");

        foreach ($_POST['languages'] as $lang) {
            $lang_stmt->execute([$_POST['id'], $lang]);
        }

        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    die("Ошибка обработки действия: " . $e->getMessage());
}

// Получение данных для отображения
try {
    // Получаем все заявки
    $applications = $pdo->query("
        SELECT a.*, GROUP_CONCAT(l.name) as languages
        FROM applications a
        LEFT JOIN application_languages al ON a.id = al.application_id
        LEFT JOIN languages l ON al.language_id = l.id
        GROUP BY a.id
    ")->fetchAll();

    // Получаем статистику по языкам
    $stats = $pdo->query("
        SELECT l.name, COUNT(al.application_id) as count
        FROM languages l
        LEFT JOIN application_languages al ON l.id = al.language_id
        GROUP BY l.id
        ORDER BY count DESC
    ")->fetchAll();

    // Получаем список всех языков
    $all_languages = $pdo->query("SELECT name FROM languages")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    die("Ошибка получения данных: " . $e->getMessage());
}

// Форма редактирования
$edit_data = null;
if ($action === 'edit' && $id) {
    foreach ($applications as $app) {
        if ($app['id'] == $id) {
            $edit_data = $app;
            $edit_data['languages'] = explode(',', $app['languages']);
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: #f8f9fa;
            padding: 20px;
        }
        .stat-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            padding: 15px;
            margin-bottom: 15px;
        }
        .stat-title {
            font-size: 14px;
            color: #6c757d;
        }
        .stat-value {
            font-size: 24px;
            font-weight: 600;
            color: #0d6efd;
        }
        .table th {
            background-color: #f8f9fa;
        }
        .badge-language {
            background-color: #e9ecef;
            color: #495057;
            margin-right: 5px;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="my-4">Панель администратора</h1>

        <!-- Статистика -->
        <div class="row mb-4">
            <?php foreach ($stats as $stat): ?>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-title"><?= htmlspecialchars($stat['name']) ?></div>
                        <div class="stat-value"><?= $stat['count'] ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Форма редактирования -->
        <?php if ($edit_data): ?>
            <div class="card mb-4">
                <div class="card-header">
                    Редактирование заявки #<?= $edit_data['id'] ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">ФИО</label>
                                    <input type="text" name="name" class="form-control" 
                                           value="<?= htmlspecialchars($edit_data['name']) ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Телефон</label>
                                    <input type="text" name="phone" class="form-control" 
                                           value="<?= htmlspecialchars($edit_data['phone']) ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?= htmlspecialchars($edit_data['email']) ?>" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Дата рождения</label>
                                    <input type="date" name="birthdate" class="form-control" 
                                           value="<?= htmlspecialchars($edit_data['birthdate']) ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Пол</label>
                                    <select name="gender" class="form-select" required>
                                        <option value="male" <?= $edit_data['gender'] === 'male' ? 'selected' : '' ?>>Мужской</option>
                                        <option value="female" <?= $edit_data['gender'] === 'female' ? 'selected' : '' ?>>Женский</option>
                                        <option value="other" <?= $edit_data['gender'] === 'other' ? 'selected' : '' ?>>Другое</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="agreement" id="agreement"
                                            <?= $edit_data['contract_accepted'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="agreement">Согласие на обработку данных</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Языки программирования</label>
                            <select name="languages[]" class="form-select" multiple size="5" required>
                                <?php foreach ($all_languages as $lang): ?>
                                    <option value="<?= htmlspecialchars($lang) ?>"
                                        <?= in_array($lang, $edit_data['languages']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($lang) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Биография</label>
                            <textarea name="bio" class="form-control" rows="4" required><?= htmlspecialchars($edit_data['bio']) ?></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">Сохранить</button>
                            <a href="index.php" class="btn btn-secondary">Отмена</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Список заявок -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Список заявок</h2>
                <span class="badge bg-primary">Всего: <?= count($applications) ?></span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ФИО</th>
                                <th>Email</th>
                                <th>Телефон</th>
                                <th>Языки</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td><?= $app['id'] ?></td>
                                    <td><?= htmlspecialchars($app['name']) ?></td>
                                    <td><?= htmlspecialchars($app['email']) ?></td>
                                    <td><?= htmlspecialchars($app['phone']) ?></td>
                                    <td>
                                        <?php foreach (explode(',', $app['languages']) as $lang): ?>
                                            <span class="badge badge-language"><?= htmlspecialchars($lang) ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="index.php?action=edit&id=<?= $app['id'] ?>" class="btn btn-sm btn-warning">Редактировать</a>
                                            <a href="index.php?action=delete&id=<?= $app['id'] ?>" class="btn btn-sm btn-danger"
                                               onclick="return confirm('Удалить эту заявку?')">Удалить</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
