<?php
$host = 'localhost';
$dbname = 'u68663';
$username = 'u68663';
$password = '9960714';

// Установка безопасных настроек PDO
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password, $options);
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS applications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            login VARCHAR(50) UNIQUE,
            pass_hash VARCHAR(255),
            name VARCHAR(150) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            email VARCHAR(100) NOT NULL,
            birthdate DATE NOT NULL,
            gender ENUM('male','female') NOT NULL,
            bio TEXT,
            agreement BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS languages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE
        ) ENGINE=InnoDB
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS application_languages (
            application_id INT UNSIGNED NOT NULL,
            language_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (application_id, language_id),
            FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
            FOREIGN KEY (language_id) REFERENCES languages(id)
        ) ENGINE=InnoDB
    ");
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM languages");
    if ($stmt->fetchColumn() == 0) {
        $languages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala'];
        $insertStmt = $pdo->prepare("INSERT IGNORE INTO languages (name) VALUES (?)");
        foreach ($languages as $lang) {
            $insertStmt->execute([$lang]);
        }
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("Ошибка подключения к БД. Пожалуйста, попробуйте позже.");
}
?>