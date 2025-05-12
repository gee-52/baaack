<?php

$errors = [];
$oldValues = [];
$savedValues = [];

if (isset($_COOKIE['form_errors'])) {
    $errors = json_decode($_COOKIE['form_errors'], true);
    $oldValues = json_decode($_COOKIE['old_values'], true);
}

foreach ($_COOKIE as $name => $value) {
    if (strpos($name, 'saved_') === 0) {
        $field = substr($name, 6);
        $savedValues[$field] = $value;
    }
}


function getFieldValue($field, $default = '') {
    global $oldValues, $savedValues;

    if (isset($oldValues[$field])) {
        return $oldValues[$field];
    }

    if (isset($savedValues[$field])) {
        return $savedValues[$field];
    }

    return $default;
}


function isSelected($field, $value) {
    global $oldValues, $savedValues;

    $currentValues = [];
    if (isset($oldValues[$field])) {
        if ($field === 'languages') {
            $currentValues = explode(',', $oldValues[$field]);
        } else {
            return $oldValues[$field] === $value ? 'checked' : '';
        }
    } elseif (isset($savedValues[$field])) {
        if ($field === 'languages') {
            $currentValues = explode(',', $savedValues[$field]);
        } else {
            return $savedValues[$field] === $value ? 'checked' : '';
        }
    }

    return in_array($value, $currentValues) ? 'selected' : '';
}


function isChecked($field) {
    global $oldValues, $savedValues;

    if (isset($oldValues[$field])) {
        return $oldValues[$field] ? 'checked' : '';
    }

    if (isset($savedValues[$field])) {
        return $savedValues[$field] ? 'checked' : '';
    }

    return '';
}
?>

<!DOCTYPE html>
<html lang="ru-RU">
<head>
    <meta charset="UTF-8">
    <title>Анкета программиста</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .form-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-top: 30px;
            margin-bottom: 30px;
        }
        .form-title {
            color: #343a40;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 600;
        }
        .form-label {
            font-weight: 500;
            margin-bottom: 15px;
            display: block;
        }
        .error-field {
            border-color: #dc3545 !important;
        }
        .error-message {
            color: #dc3545;
            font-size: 0.875em;
            margin-top: 5px;
        }
        .error-list {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            text-align: center;
        }
        .btn-submit {
            background-color: #007bff;
            border: none;
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-submit:hover {
            background-color: #0069d9;
            transform: translateY(-2px);
        }
        .radio-group, .checkbox-group {
            margin-top: 8px;
        }
        .form-check-label {
            margin-right: 15px;
            font-weight: normal;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="form-container">
                    <h2 class="form-title">Анкета программиста</h2>
                    
                    <?php if (isset($_GET['success'])): ?>
                        <div class="success-message">
                            <i class="bi bi-check-circle-fill"></i> Данные успешно сохранены!
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($errors)): ?>
                        <div class="error-list">
                            <h5>Обнаружены ошибки:</h5>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form action="sub.php" method="POST" id="form">
                        <div class="mb-3">
                            <label for="name" class="form-label">1) ФИО:</label>
                            <input type="text" class="form-control <?php echo isset($errors['name']) ? 'error-field' : ''; ?>" 
                                   id="name" name="name" placeholder="Иванов Иван Иванович" 
                                   value="<?php echo htmlspecialchars(getFieldValue('name')); ?>" required>
                            <?php if (isset($errors['name'])): ?>
                                <div class="error-message"><?php echo htmlspecialchars($errors['name']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">2) Телефон:</label>
                            <input type="tel" class="form-control <?php echo isset($errors['phone']) ? 'error-field' : ''; ?>" 
                                   id="phone" name="phone" placeholder="+7 (123) 456-78-90" 
                                   value="<?php echo htmlspecialchars(getFieldValue('phone')); ?>" required>
                            <?php if (isset($errors['phone'])): ?>
                                <div class="error-message"><?php echo htmlspecialchars($errors['phone']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">3) Email:</label>
                            <input type="email" class="form-control <?php echo isset($errors['email']) ? 'error-field' : ''; ?>" 
                                   id="email" name="email" placeholder="example@domain.com" 
                                   value="<?php echo htmlspecialchars(getFieldValue('email')); ?>" required>
                            <?php if (isset($errors['email'])): ?>
                                <div class="error-message"><?php echo htmlspecialchars($errors['email']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="birthdate" class="form-label">4) Дата рождения:</label>
                            <input type="date" class="form-control <?php echo isset($errors['birthdate']) ? 'error-field' : ''; ?>" 
                                   id="birthdate" name="birthdate" 
                                   value="<?php echo htmlspecialchars(getFieldValue('birthdate', '2000-07-15')); ?>" required>
                            <?php if (isset($errors['birthdate'])): ?>
                                <div class="error-message"><?php echo htmlspecialchars($errors['birthdate']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">5) Пол:</label>
                            <div class="radio-group">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input <?php echo isset($errors['gender']) ? 'error-field' : ''; ?>" 
                                           type="radio" name="gender" id="male" value="male" 
                                           <?php echo isSelected('gender', 'male'); ?> required>
                                    <label class="form-check-label" for="male">Мужской</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input <?php echo isset($errors['gender']) ? 'error-field' : ''; ?>" 
                                           type="radio" name="gender" id="female" value="female" 
                                           <?php echo isSelected('gender', 'female'); ?>>
                                    <label class="form-check-label" for="female">Женский</label>
                                </div>
                            </div>
                            <?php if (isset($errors['gender'])): ?>
                                <div class="error-message"><?php echo htmlspecialchars($errors['gender']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="languages" class="form-label">6) Любимый язык программирования:</label>
                            <select class="form-select <?php echo isset($errors['languages']) ? 'error-field' : ''; ?>" 
                                    id="languages" name="languages[]" multiple size="5" required>
                                <?php
                                $allLanguages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python', 'Java', 'Haskell', 'Clojure', 'Prolog', 'Scala'];
                                foreach ($allLanguages as $lang): ?>
                                    <option value="<?php echo htmlspecialchars($lang); ?>" 
                                        <?php echo isSelected('languages', $lang); ?>>
                                        <?php echo htmlspecialchars($lang); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Для выбора нескольких вариантов удерживайте Ctrl</small>
                            <?php if (isset($errors['languages'])): ?>
                                <div class="error-message"><?php echo htmlspecialchars($errors['languages']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bio" class="form-label">7) Биография:</label>
                            <textarea class="form-control <?php echo isset($errors['bio']) ? 'error-field' : ''; ?>" 
                                      id="bio" name="bio" rows="3" required><?php 
                                      echo htmlspecialchars(getFieldValue('bio')); ?></textarea>
                            <?php if (isset($errors['bio'])): ?>
                                <div class="error-message"><?php echo htmlspecialchars($errors['bio']); ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input <?php echo isset($errors['agreement']) ? 'error-field' : ''; ?>" 
                                       type="checkbox" id="agreement" name="agreement" value="1" 
                                       <?php echo isChecked('agreement'); ?> required>
                                <label class="form-check-label" for="agreement">
                                    8) С контрактом ознакомлен(а)
                                </label>
                                <?php if (isset($errors['agreement'])): ?>
                                    <div class="error-message"><?php echo htmlspecialchars($errors['agreement']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" name="save" class="btn btn-primary btn-submit">
                                9) Отправить анкету
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
