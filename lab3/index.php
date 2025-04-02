<?php
// Устанавливаем соединение с базой данных
$host = 'localhost';
$dbname = 'u68663';
$username = 'u68663';
$password = '9960714';
$dsn = "mysql:host=$host;dbname=$dbname;charset=utf8";

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = [];
    if (!empty($errors)) {
        echo "<h2>Ошибки:</h2><ul>";
        foreach ($errors as $error) {
            echo "<li>$error</li>";
        }
        echo "</ul>";
        exit;
    }
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO applications (full_name, phone, email, birthdate, gender, biography, agreement)
                      VALUES (:name, :phone, :email, :birthdate, :gender, :bio, :contract)");
        $stmt->execute([
            ':name' => $_POST['name'],
            ':phone' => $_POST['phone'],
            ':email' => $_POST['email'],
            ':birthdate' => $_POST['birthdate'],
            ':gender' => $_POST['gender'],
            ':bio' => $_POST['bio'],
            ':contract' => isset($_POST['contract_accepted']) ? 1 : 0
        ]);
        $applicationId = $pdo->lastInsertId();
$validLanguages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala'];
$selectedLanguages = array_intersect($_POST['languages'] ?? [], $validLanguages);

if (!empty($selectedLanguages)) {
    $placeholders = rtrim(str_repeat('?,', count($selectedLanguages)), ',');
    $stmt = $pdo->prepare("SELECT id, name FROM languages WHERE name IN ($placeholders)");
    $stmt->execute($selectedLanguages);
    $existingLanguages = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $missingLanguages = array_diff($selectedLanguages, array_keys($existingLanguages));
    if (!empty($missingLanguages)) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO languages (name) VALUES (?)");
        foreach ($missingLanguages as $lang) {
            $stmt->execute([$lang]);
            if ($stmt->rowCount() > 0) {
                $existingLanguages[$lang] = $pdo->lastInsertId();
            } else {
                $stmtSelect = $pdo->prepare("SELECT id FROM languages WHERE name = ?");
                $stmtSelect->execute([$lang]);
                $existingLanguages[$lang] = $stmtSelect->fetchColumn();
            }
        }
    }

    $stmt = $pdo->prepare("INSERT IGNORE INTO application_languages (application_id, language_id) VALUES (?, ?)");
    foreach ($existingLanguages as $langId) {
        $stmt->execute([$applicationId, $langId]);
    }
}
        $pdo->commit();
        header("Location: index.html?success=1");
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Ошибка при сохранении данных: " . $e->getMessage());
    }
}
?>