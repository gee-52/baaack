<?php
declare(strict_types=1);
session_start();

require 'db.php';

// 1. Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    $_SESSION['error_message'] = 'Недопустимый метод запроса';
    header('Location: index.php');
    exit();
}

// 2. Валидация CSRF-токена
if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    $_SESSION['error_message'] = 'Ошибка безопасности (CSRF)';
    header('Location: index.php');
    exit();
}

// 3. Определение правил валидации
$validation_rules = [
    'name' => [
        'filter' => FILTER_VALIDATE_REGEXP,
        'options' => ['regexp' => '/^[a-zA-Zа-яА-ЯёЁ\s\-]{1,150}$/u'],
        'message' => 'ФИО должно содержать только буквы и дефисы (макс. 150 символов)'
    ],
    'phone' => [
        'filter' => FILTER_VALIDATE_REGEXP,
        'options' => ['regexp' => '/^\+?[\d\s\-\(\)]{7,20}$/'],
        'message' => 'Номер телефона должен содержать 7-20 цифр и допустимые символы'
    ],
    'email' => [
        'filter' => FILTER_VALIDATE_EMAIL,
        'message' => 'Введите корректный email'
    ],
    'birthdate' => [
        'filter' => FILTER_CALLBACK,
        'options' => function($value) {
            $date = DateTime::createFromFormat('Y-m-d', $value);
            return $date && $date->format('Y-m-d') === $value;
        },
        'message' => 'Введите корректную дату в формате ГГГГ-ММ-ДД'
    ],
    'gender' => [
        'filter' => FILTER_VALIDATE_REGEXP,
        'options' => ['regexp' => '/^(male|female)$/'],
        'message' => 'Выберите пол из предложенных вариантов'
    ],
    'bio' => [
        'filter' => FILTER_VALIDATE_REGEXP,
        'options' => ['regexp' => '/^[\s\S]{10,2000}$/'],
        'message' => 'Биография должна содержать 10-2000 символов'
    ],
    'agreement' => [
        'filter' => FILTER_VALIDATE_BOOLEAN,
        'message' => 'Необходимо согласие с обработкой данных'
    ]
];

// 4. Валидация и фильтрация данных
$errors = [];
$data = [];

foreach ($validation_rules as $field => $rule) {
    $value = $_POST[$field] ?? '';
    
    // Особый случай для чекбокса agreement
    if ($field === 'agreement') {
        $value = isset($_POST['agreement']) ? '1' : '0';
    }

    // Валидация с помощью filter_var
    $options = $rule['options'] ?? [];
    if ($rule['filter'] === FILTER_CALLBACK && is_callable($options)) {
        $valid = $options($value);
    } else {
        $valid = filter_var($value, $rule['filter'], ['options' => $options]);
    }

    if ($valid === false || $valid === null) {
        $errors[$field] = $rule['message'];
    } else {
        $data[$field] = is_string($value) ? htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8') : $value;
    }
}

// 5. Специальная валидация для языков программирования
$validLanguages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala'];
if (empty($_POST['languages']) || !is_array($_POST['languages'])) {
    $errors['languages'] = 'Выберите хотя бы один язык программирования';
} else {
    $filteredLanguages = [];
    foreach ($_POST['languages'] as $lang) {
        $lang = htmlspecialchars(trim($lang), ENT_QUOTES, 'UTF-8');
        if (in_array($lang, $validLanguages)) {
            $filteredLanguages[] = $lang;
        }
    }
    
    if (empty($filteredLanguages)) {
        $errors['languages'] = 'Выбран недопустимый язык программирования';
    } else {
        $data['languages'] = $filteredLanguages;
    }
}

// 6. Обработка ошибок валидации
if (!empty($errors)) {
    $_SESSION['form_errors'] = $errors;
    $_SESSION['form_values'] = $data;
    header('Location: index.php');
    exit();
}

// 7. Сохранение в базу данных
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
        $login = 'user_' . bin2hex(random_bytes(8));
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
        $_SESSION['new_user_credentials'] = ['login' => $login, 'password' => $pass];
    }

    // Добавление языков программирования
    $app_id = $_SESSION['uid'] ?? $pdo->lastInsertId();
    $lang_stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id)
        SELECT ?, id FROM languages WHERE name = ?");

    foreach ($data['languages'] as $lang) {
        $lang_stmt->execute([$app_id, $lang]);
    }

    $pdo->commit();
    $_SESSION['success_message'] = 'Данные успешно сохранены!';
    header('Location: index.php');
    exit();

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error_message'] = 'Ошибка при сохранении данных. Пожалуйста, попробуйте позже.';
    header('Location: index.php');
    exit();
}
?>
