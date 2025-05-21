<?php
session_start();
require 'db.php';

if (isset($_SESSION['login'])) {
    header('Location: index.php');
    exit();
}

// Генерация CSRF-токена
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка CSRF-токена
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Недействительный CSRF-токен");
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    try {
        $query = "SELECT id, login, pass_hash FROM applications WHERE login = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['pass_hash'])) {
            $_SESSION['login'] = $user['login'];
            $_SESSION['uid'] = $user['id'];
            
            header('Location: index.php');
            exit();
        } else {
            $error_message = 'Неверные учетные данные';
        }
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        $error_message = 'Ошибка системы. Пожалуйста, попробуйте позже.';
    }
}
?>

<!-- В форме входа добавьте CSRF-токен -->
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">