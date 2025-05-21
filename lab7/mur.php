<?php
declare(strict_types=1);
session_start();

require 'db.php';

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit();
}

// Проверка CSRF-токена
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $_SESSION['error_message'] = 'Недействительный CSRF-токен';
    header('Location: index.php');
    exit();
}

// Валидация данных
$validation_rules = [
    'name' => [
        'filter' => FILTER_VALIDATE_REGEXP,
        'options' => ['regexp' => '/^[a-zA-Zа-яА-ЯёЁ\s]{1,150}$/u'],
        'message' => 'ФИО должно содержать только буквы'
    ],
    'phone' => [
        'filter' => FILTER_VALIDATE_REGEXP,
        'options' => ['regexp' => '/^\+?\d[\d\s\-\(\)]{6,}\d$/'],
        'message' => 'Номер телефона должен содержать только цифры и допустимые символы'
    ],
    'email' => [
        'filter' => FILTER_VALIDATE_EMAIL,
        'message' => 'Введите корректный email'
    ],
    'birthdate' => [
        'filter' => FILTER_VALIDATE_REGEXP,
        'options' => ['regexp' => '/^\d{4}-\d{2}-\d{2}$/'],
        'message' => 'Введите корректную дату рождения'
    ],
    'gender' => [
        'filter' => FILTER_VALIDATE_REGEXP,
        'options' => ['regexp' => '/^(male|female)$/'],
        'message' => 'Выберите пол'
    ],
    'bio' => [
        'filter' => FILTER_VALIDATE_REGEXP,
        'options' => ['regexp' => '/^[\s\S]{10,2000}$/'],
        'message' => 'Биография должна содержать от 10 до 2000 символов'
    ],
    'agreement' => [
        'filter' => FILTER_VALIDATE_BOOLEAN,
        'message' => 'Необходимо согласие с обработкой данных'
    ]
];

$errors = [];
$data = [];

// Валидация основных полей
foreach ($validation_rules as $field => $rule) {
    $value = $_POST[$field] ?? '';
    
    if ($field === 'agreement') {
        $value = isset($_POST['agreement']) ? true : false;
    }

    if (!filter_var($value, $rule['filter'], $rule['options'] ?? [])) {
        $errors[$field] = $rule['message'];
    } else {
        $data[$field] = $value;
    }
}

// Валидация языков программирования
if (empty($_POST['languages'])) {
    $errors['languages'] = 'Выберите хотя бы один язык программирования';
} else {
    $data['languages'] = $_POST['languages'];
    // Дополнительная проверка допустимых языков
    $validLanguages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala'];
    foreach ($data['languages'] as $lang) {
        if (!in_array($lang, $validLanguages)) {
            $errors['languages'] = 'Выбран недопустимый язык программирования';
            break;
        }
    }
}

// Если есть ошибки - возвращаем на форму
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_values'] = array_map(function($value) {
        return is_string($value) ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value;
    }, $data);
    header('Location: index.php');
    exit();
}

try {
    $pdo->beginTransaction();

    if (!empty($_SESSION['login'])) {
        // Обновление существующей записи
        $stmt = $pdo->prepare("UPDATE applications SET
            name = :name, phone = :phone, email = :email,
            birthdate = :birthdate, gender = :gender,
            bio = :bio, agreement = :agreement
            WHERE id = :id");
        
        $stmt->execute([
            ':name' => $data['name'],
            ':phone' => $data['phone'],
            ':email' => $data['email'],
            ':birthdate' => $data['birthdate'],
            ':gender' => $data['gender'],
            ':bio' => $data['bio'],
            ':agreement' => $data['agreement'] ? 1 : 0,
            ':id' => $_SESSION['uid']
        ]);

        // Удаляем старые языки
        $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")
            ->execute([$_SESSION['uid']]);
    } else {
        // Создание новой записи
        $login = uniqid('user_', true);
        $pass = bin2hex(random_bytes(8));
        $pass_hash = password_hash($pass, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("INSERT INTO applications
            (name, phone, email, birthdate, gender, bio, agreement, login, pass_hash)
            VALUES (:name, :phone, :email, :birthdate, :gender, :bio, :agreement, :login, :pass_hash)");
        
        $stmt->execute([
            ':name' => $data['name'],
            ':phone' => $data['phone'],
            ':email' => $data['email'],
            ':birthdate' => $data['birthdate'],
            ':gender' => $data['gender'],
            ':bio' => $data['bio'],
            ':agreement' => $data['agreement'] ? 1 : 0,
            ':login' => $login,
            ':pass_hash' => $pass_hash
        ]);
        
        $app_id = $pdo->lastInsertId();
        $_SESSION['login'] = $login;
        $_SESSION['uid'] = $app_id;
    }

    // Добавление языков программирования
    $app_id = $_SESSION['uid'] ?? $app_id;
    $lang_stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id)
        SELECT ?, id FROM languages WHERE name = ?");

    foreach ($data['languages'] as $lang) {
        $lang_stmt->execute([$app_id, $lang]);
    }

    $pdo->commit();
    $_SESSION['success_message'] = 'Данные успешно сохранены';
    
    // Если это новая регистрация - показываем логин и пароль
    if (empty($_SESSION['login_before_save'])) {
        $_SESSION['login_info'] = [
            'login' => $login,
            'password' => $pass
        ];
    }
    
    header('Location: index.php');
    exit();

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error_message'] = 'Ошибка сохранения данных. Пожалуйста, попробуйте позже.';
    header('Location: index.php');
    exit();
}
