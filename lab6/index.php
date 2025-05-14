<?php
require 'data_storage.php';

// Инициализация системы контроля доступа
try {
    $storageSystem->getConnection()->exec("
        CREATE TABLE IF NOT EXISTS system_operators (
            operator_id MEDIUMINT UNSIGNED AUTO_INCREMENT,
            operator_login VARCHAR(60) UNIQUE NOT NULL,
            access_key CHAR(128) NOT NULL,
            PRIMARY KEY (operator_id)
        ) ENGINE=ARIA
    ");

    // Добавление оператора по умолчанию
    $checkOp = $storageSystem->getConnection()->query("SELECT operator_login FROM system_operators LIMIT 1");
    if ($checkOp->rowCount() === 0) {
        $defaultKey = password_hash('secure123', PASSWORD_BCRYPT);
        $storageSystem->getConnection()->prepare(
            "INSERT INTO system_operators (operator_login, access_key) VALUES (?, ?)"
        )->execute(['main_operator', $defaultKey]);
    }
} catch (PDOException $e) {
    error_log("Operator system error: " . $e->getMessage());
    exit("Access control system unavailable");
}

// Проверка доступа оператора
if (empty($_SERVER['PHP_AUTH_USER'])) {
    header('HTTP/1.1 401 Access Denied');
    header('WWW-Authenticate: Basic realm="Operator Control Center"');
    exit('Operator authentication required');
}

try {
    $authCheck = $storageSystem->getConnection()->prepare(
        "SELECT access_key FROM system_operators WHERE operator_login = ?"
    );
    $authCheck->execute([$_SERVER['PHP_AUTH_USER']]);
    $operator = $authCheck->fetch();

    if (!$operator || !password_verify($_SERVER['PHP_AUTH_PW'], $operator['access_key'])) {
        header('HTTP/1.1 403 Forbidden');
        header('WWW-Authenticate: Basic realm="Operator Control Center"');
        exit('Invalid operator credentials');
    }
} catch (PDOException $e) {
    error_log("Auth error: " . $e->getMessage());
    exit("Authentication service unavailable");
}

// Обработка команд оператора
$command = $_GET['cmd'] ?? '';
$recordId = $_GET['rec'] ?? 0;

try {
    // Удаление записи
    if ($command === 'remove' && $recordId) {
        $storageSystem->getConnection()->prepare(
            "DELETE FROM user_profiles WHERE profile_id = ?"
        )->execute([$recordId]);
        header("Location: control_panel.php");
        exit();
    }

    // Обновление данных
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rec_id'])) {
        $updateStmt = $storageSystem->getConnection()->prepare(
            "UPDATE user_profiles SET
            full_name = ?, contact_number = ?, electronic_address = ?, 
            date_of_birth = ?, sex = ?, personal_description = ?, 
            terms_accepted = ?
            WHERE profile_id = ?"
        );

        $updateStmt->execute([
            $_POST['person_name'],
            $_POST['contact_phone'],
            $_POST['email_addr'],
            $_POST['birth_date'],
            $_POST['gender_type'],
            $_POST['user_bio'],
            isset($_POST['terms_agree']) ? 1 : 0,
            $_POST['rec_id']
        ]);

        // Обновление навыков
        $storageSystem->getConnection()->prepare(
            "DELETE FROM profile_skills WHERE user_ref = ?"
        )->execute([$_POST['rec_id']]);

        $skillLinkStmt = $storageSystem->getConnection()->prepare(
            "INSERT INTO profile_skills (user_ref, skill_ref)
            SELECT ?, skill_id FROM tech_skills WHERE skill_name = ?"
        );

        foreach ($_POST['tech_skills'] as $skill) {
            $skillLinkStmt->execute([$_POST['rec_id'], $skill]);
        }

        header("Location: control_panel.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Command processing error: " . $e->getMessage());
    exit("Operation failed. Please try again.");
}

// Получение данных для панели управления
try {
    $profileData = $storageSystem->getConnection()->query("
        SELECT p.*, GROUP_CONCAT(t.skill_name) as skills
        FROM user_profiles p
        LEFT JOIN profile_skills ps ON p.profile_id = ps.user_ref
        LEFT JOIN tech_skills t ON ps.skill_ref = t.skill_id
        GROUP BY p.profile_id
    ")->fetchAll();

    $skillStats = $storageSystem->getConnection()->query("
        SELECT t.skill_name, COUNT(ps.user_ref) as usage_count
        FROM tech_skills t
        LEFT JOIN profile_skills ps ON t.skill_id = ps.skill_ref
        GROUP BY t.skill_id
        ORDER BY usage_count DESC
    ")->fetchAll();

    $allSkills = $storageSystem->getConnection()->query(
        "SELECT skill_name FROM tech_skills"
    )->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Data retrieval error: " . $e->getMessage());
    exit("Data loading failed. Contact system administrator.");
}

// Редактирование записи
$editRecord = null;
if ($command === 'modify' && $recordId) {
    foreach ($profileData as $profile) {
        if ($profile['profile_id'] == $recordId) {
            $editRecord = $profile;
            $editRecord['skills'] = explode(',', $profile['skills']);
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operator Control Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #ecf0f1;
            --accent-color: #3498db;
            --warning-color: #e74c3c;
            --success-color: #2ecc71;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--secondary-color);
            color: #34495e;
            padding: 2rem;
        }
        
        .control-panel {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 2rem;
        }
        
        .panel-header {
            border-bottom: 2px solid var(--secondary-color);
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-title {
            color: var(--primary-color);
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .stat-value {
            color: var(--accent-color);
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .data-table th {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem;
            text-align: left;
        }
        
        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--secondary-color);
            vertical-align: middle;
        }
        
        .skill-badge {
            background-color: var(--secondary-color);
            color: var(--primary-color);
            padding: 0.3rem 0.6rem;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-right: 0.3rem;
            display: inline-block;
            margin-bottom: 0.3rem;
        }
        
        .action-btn {
            border-radius: 6px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            border: none;
            transition: all 0.2s;
        }
        
        .edit-btn {
            background-color: var(--accent-color);
            color: white;
        }
        
        .delete-btn {
            background-color: var(--warning-color);
            color: white;
        }
        
        .form-panel {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.08);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .form-section {
            margin-bottom: 1.5rem;
        }
        
        .form-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <div class="control-panel">
        <div class="panel-header">
            <h1>Operator Control Panel</h1>
            <p class="text-muted">Manage user profiles and system data</p>
        </div>

        <!-- Statistics Overview -->
        <div class="row">
            <?php foreach ($skillStats as $stat): ?>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-title"><?= htmlspecialchars($stat['skill_name']) ?></div>
                        <div class="stat-value"><?= $stat['usage_count'] ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Edit Form -->
        <?php if ($editRecord): ?>
            <div class="form-panel">
                <h2>Editing Profile #<?= $editRecord['profile_id'] ?></h2>
                <form method="POST">
                    <input type="hidden" name="rec_id" value="<?= $editRecord['profile_id'] ?>">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-section">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="person_name" class="form-control" 
                                       value="<?= htmlspecialchars($editRecord['full_name']) ?>" required>
                            </div>

                            <div class="form-section">
                                <label class="form-label">Contact Number</label>
                                <input type="text" name="contact_phone" class="form-control" 
                                       value="<?= htmlspecialchars($editRecord['contact_number']) ?>" required>
                            </div>

                            <div class="form-section">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email_addr" class="form-control" 
                                       value="<?= htmlspecialchars($editRecord['electronic_address']) ?>" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-section">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" name="birth_date" class="form-control" 
                                       value="<?= htmlspecialchars($editRecord['date_of_birth']) ?>" required>
                            </div>

                            <div class="form-section">
                                <label class="form-label">Gender</label>
                                <select name="gender_type" class="form-control" required>
                                    <option value="m" <?= $editRecord['sex'] === 'm' ? 'selected' : '' ?>>Male</option>
                                    <option value="f" <?= $editRecord['sex'] === 'f' ? 'selected' : '' ?>>Female</option>
                                    <option value="o" <?= $editRecord['sex'] === 'o' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>

                            <div class="form-section">
                                <label class="form-label">Terms Accepted</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="terms_agree" 
                                           id="termsCheck" <?= $editRecord['terms_accepted'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="termsCheck">Confirmed</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <label class="form-label">Technical Skills</label>
                        <select name="tech_skills[]" class="form-control" multiple size="5" required>
                            <?php foreach ($allSkills as $skill): ?>
                                <option value="<?= htmlspecialchars($skill) ?>"
                                    <?= in_array($skill, $editRecord['skills']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($skill) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-section">
                        <label class="form-label">Profile Description</label>
                        <textarea name="user_bio" class="form-control" rows="4" required><?= 
                            htmlspecialchars($editRecord['personal_description']) ?></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <a href="control_panel.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Profiles Table -->
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Skills</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($profileData as $profile): ?>
                        <tr>
                            <td><?= $profile['profile_id'] ?></td>
                            <td><?= htmlspecialchars($profile['full_name']) ?></td>
                            <td><?= htmlspecialchars($profile['electronic_address']) ?></td>
                            <td><?= htmlspecialchars($profile['contact_number']) ?></td>
                            <td>
                                <?php
                                    $skills = explode(',', $profile['skills']);
                                    foreach ($skills as $skill) {
                                        echo '<span class="skill-badge">'.htmlspecialchars($skill).'</span>';
                                    }
                                ?>
                            </td>
                            <td>
                                <div class="d-flex gap-2">
                                    <a href="control_panel.php?cmd=modify&rec=<?= $profile['profile_id'] ?>" 
                                       class="action-btn edit-btn">Edit</a>
                                    <a href="control_panel.php?cmd=remove&rec=<?= $profile['profile_id'] ?>" 
                                       class="action-btn delete-btn"
                                       onclick="return confirm('Confirm profile deletion?')">Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>