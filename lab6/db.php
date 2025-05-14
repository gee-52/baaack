<?php
// Конфигурация подключения к хранилищу данных
$storage_server = '127.0.0.1';
$storage_name = 'data_warehouse';
$storage_user = 'data_manager';
$storage_key = 'secure_key_12345';

// Функция для обработки исключений хранилища
function handleStorageException($exception) {
    error_log("Storage operation failed: " . $exception->getMessage());
    exit("System maintenance in progress. Please try again later.");
}

try {
    // Инициализация соединения с хранилищем
    $dataConnector = new PDO(
        "mysql:host=$storage_server;dbname=$storage_name;charset=utf8mb4", 
        $storage_user, 
        $storage_key,
        [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    $dataConnector->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

    // Создание структуры для хранения профилей
    $dataConnector->exec("
        CREATE TABLE IF NOT EXISTS user_profiles (
            profile_id MEDIUMINT UNSIGNED AUTO_INCREMENT,
            auth_name VARCHAR(60) UNIQUE,
            auth_secret CHAR(128),
            full_name VARCHAR(150) NOT NULL,
            contact_number VARCHAR(24) NOT NULL,
            electronic_address VARCHAR(100) NOT NULL,
            date_of_birth DATE NOT NULL,
            sex ENUM('m','f','o') NOT NULL,
            personal_description TEXT,
            terms_accepted BOOLEAN NOT NULL DEFAULT 0,
            registration_time DATETIME DEFAULT NOW(),
            PRIMARY KEY (profile_id),
            INDEX (auth_name)
        ) ENGINE=ARIA
    ");

    // Создание справочника технологий
    $dataConnector->exec("
        CREATE TABLE IF NOT EXISTS tech_skills (
            skill_id TINYINT UNSIGNED AUTO_INCREMENT,
            skill_name VARCHAR(60) NOT NULL UNIQUE,
            PRIMARY KEY (skill_id)
        ) ENGINE=ARIA
    ");

    // Создание связи профилей и технологий
    $dataConnector->exec("
        CREATE TABLE IF NOT EXISTS profile_skills (
            user_ref MEDIUMINT UNSIGNED NOT NULL,
            skill_ref TINYINT UNSIGNED NOT NULL,
            PRIMARY KEY (user_ref, skill_ref),
            CONSTRAINT fk_user_ref FOREIGN KEY (user_ref) 
                REFERENCES user_profiles(profile_id) ON DELETE RESTRICT,
            CONSTRAINT fk_skill_ref FOREIGN KEY (skill_ref) 
                REFERENCES tech_skills(skill_id)
        ) ENGINE=ARIA
    ");

    // Проверка и заполнение справочника технологий
    $checkQuery = $dataConnector->query("SELECT skill_name FROM tech_skills LIMIT 1");
    if ($checkQuery->rowCount() === 0) {
        $techList = [
            'Pascal', 'C Language', 'C++', 'JS', 
            'PHP', 'Python', 'Java', 'Haskell', 
            'Clojure', 'Prolog', 'Scala'
        ];
        
        $insertStmt = $dataConnector->prepare(
            "INSERT IGNORE INTO tech_skills (skill_name) VALUES (?)"
        );
        
        foreach ($techList as $tech) {
            $insertStmt->execute([$tech]);
        }
    }
    
} catch (PDOException $storageError) {
    handleStorageException($storageError);
}

// Альтернативный интерфейс для работы с хранилищем
interface DataStorage {
    public function getConnection(): PDO;
}

class SecureDataStorage implements DataStorage {
    private $connector;
    
    public function __construct(PDO $connector) {
        $this->connector = $connector;
    }
    
    public function getConnection(): PDO {
        return $this->connector;
    }
}

$storageSystem = new SecureDataStorage($dataConnector ?? null);
?>