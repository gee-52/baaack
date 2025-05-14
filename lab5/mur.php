<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS applications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                login VARCHAR(50) UNIQUE,
                pass_hash VARCHAR(255),
                name VARCHAR(150) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                email VARCHAR(100) NOT NULL,
                birthdate DATE NOT NULL,
                gender ENUM('male','female','other') NOT NULL,
                bio TEXT,
                agreement BOOLEAN NOT NULL DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB
        ");
    } catch (PDOException $e) {
        die("пу пу пу...таблица неверная " . $e->getMessage());
    }

    $validation_rules = [
        'name' => [
            'pattern' => '/^[a-zA-Zа-яА-ЯёЁ\s]{1,150}$/u',
            'message' => 'если что в фио только буквф должны быть'
        ],
        'phone' => [
            'pattern' => '/^\+?\d[\d\s\-\(\)]{6,}\d$/',
            'message' => 'в номере только цифры ага да'
        ],
        'email' => [
            'pattern' => '/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i',
            'message' => 'нормально почту ввести надо'
        ],
        'birthdate' => [
            'pattern' => '/^\d{4}-\d{2}-\d{2}$/',
            'message' => 'ну в дате у нас только числа'
        ],
        'gender' => [
            'pattern' => '/^(male|female|other)$/',
            'message' => 'пол выберите'
        ],
        'languages' => [
            'message' => 'ну хоть какой то язык выбери блин'
        ],
        'bio' => [
            'pattern' => '/^[\s\S]{10,2000}$/',
            'message' => 'в биографии хотя бы 10 символов напиши плиз'
        ],
        'agreement' => [
            'pattern' => '/^1$/',
            'message' => 'галочку поставь'
        ]
    ];

    $errors = [];
    $data = [];

    foreach ($validation_rules as $field => $rule) {
        if ($field === 'languages') {
            if (empty($_POST['languages'])) {
                $errors[$field] = $rule['message'];
                setcookie($field.'_error', $rule['message'], time() + 24 * 60 * 60);
            } else {
                $data[$field] = $_POST['languages'];
            }
            continue;
        }

        $value = $_POST[$field] ?? '';

        if ($field === 'agreement') {
            $value = isset($_POST['agreement']) ? '1' : '0';
        }

        $data[$field] = $value;

        if ($field !== 'languages' && !preg_match($rule['pattern'], $value)) {
            $errors[$field] = $rule['message'];
            setcookie($field.'_error', $rule['message'], time() + 24 * 60 * 60);
        } else {
            setcookie($field.'_value', $value, time() + 30 * 24 * 60 * 60);
        }
    }

    if (!empty($errors)) {
        setcookie('form_errors', json_encode($errors), time() + 24 * 60 * 60, '/');
        header('Location: index.php');
        exit();
    }

    try {
        $db_data = [
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'birthdate' => $data['birthdate'],
            'gender' => $data['gender'],
            'bio' => $data['bio'],
            'agreement' => $data['agreement']
        ];

        if (!empty($_SESSION['login'])) {

            $db_data['id'] = $_SESSION['uid'];
            $stmt = $pdo->prepare("UPDATE applications SET
                name = :name, phone = :phone, email = :email,
                birthdate = :birthdate, gender = :gender,
                bio = :bio, agreement = :agreement
                WHERE id = :id");
            $stmt->execute($db_data);


            $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")
                ->execute([$_SESSION['uid']]);
        } else {

            $login = uniqid('user_');
            $pass = bin2hex(random_bytes(4));
            $pass_hash = password_hash($pass, PASSWORD_DEFAULT);

            $db_data['login'] = $login;
            $db_data['pass_hash'] = $pass_hash;

            $stmt = $pdo->prepare("INSERT INTO applications
                (name, phone, email, birthdate, gender, bio, agreement, login, pass_hash)
                VALUES (:name, :phone, :email, :birthdate, :gender, :bio, :agreement, :login, :pass_hash)");
            $stmt->execute($db_data);
            $app_id = $pdo->lastInsertId();

            setcookie('login', $login, time() + 24 * 60 * 60);
            setcookie('pass', $pass, time() + 24 * 60 * 60);
        }


        $app_id = $_SESSION['uid'] ?? $app_id;
        $lang_stmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id)
            SELECT ?, id FROM languages WHERE name = ?");

        foreach ($data['languages'] as $lang) {
            $lang_stmt->execute([$app_id, $lang]);
        }

        setcookie('save', '1', time() + 24 * 60 * 60);
        header('Location: index.php?success=1');
        exit();
    } catch (PDOException $e) {
        die("Ошибка сохранения данных: " . $e->getMessage());
    }
}
?>