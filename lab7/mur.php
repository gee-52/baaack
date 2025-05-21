<?php
session_start();
require 'db.php';

// Проверка CSRF-токена
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Недействительный CSRF-токен");
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

    foreach ($validation_rules as $field => $rule) {
        if ($field === 'languages') {
            if (empty($_POST['languages'])) {
                $errors[$field] = 'Выберите хотя бы один язык программирования';
            } else {
                $data[$field] = array_map('htmlspecialchars', $_POST['languages']);
            }
            continue;
        }

        $value = $_POST[$field] ?? '';
        if ($field === 'agreement') {
            $value = isset($_POST['agreement']) ? true : false;
        }

        if (!filter_var($value, $rule['filter'], $rule['options'] ?? [])) {
            $errors[$field] = $rule['message'];
        } else {
            $data[$field] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
    }

    if (!empty($errors)) {
        $_SESSION['form_errors'] = $errors;
        $_SESSION['form_values'] = $data;
        header('Location: index.php');
        exit();
    }

    try {
        $pdo->beginTransaction();

        if (!empty($_SESSION['login'])) {
            // Обновление существующей записи
            $stmt = $pdo->prepare("UPDATE applications SET
                name = ?, phone = ?, email = ?,
                birthdate = ?, gender = ?,
                bio = ?, agreement = ?
                WHERE id = ?");
            $stmt->execute([
                $data['name'],
                $data['phone'],
                $data['email'],
                $data['birthdate'],
                $data['gender'],
                $data['bio'],
                $data['agreement'],
                $_SESSION['uid']
            ]);

            $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")
                ->execute([$_SESSION['uid']]);
        } else {
            // Создание новой записи
            $login = uniqid('user_');
            $pass = bin2hex(random_bytes(8));
            $pass_hash = password_hash($pass, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("INSERT INTO applications
                (name, phone, email, birthdate, gender, bio, agreement, login, pass_hash)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['name'],
                $data['phone'],
                $data['email'],
                $data['birthdate'],
                $data['gender'],
                $data['bio'],
                $data['agreement'],
                $login,
                $pass_hash
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
        header('Location: index.php');
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Database error: " . $e->getMessage());
        $_SESSION['error_message'] = 'Ошибка сохранения данных. Пожалуйста, попробуйте позже.';
        header('Location: index.php');
        exit();
    }
}
?>