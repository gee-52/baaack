<?php
$formErrors = [];
$previousValues = [];
$storedData = [];

// Загрузка ошибок и предыдущих значений из кук
if (!empty($_COOKIE['form_errors'])) {
    $formErrors = json_decode($_COOKIE['form_errors'], true);
    $previousValues = json_decode($_COOKIE['old_values'], true);
}

// Загрузка сохраненных значений из кук
foreach ($_COOKIE as $cookieName => $cookieValue) {
    if (str_starts_with($cookieName, 'saved_')) {
        $fieldName = substr($cookieName, 6);
        $storedData[$fieldName] = $cookieValue;
    }
}

/**
 * Получает значение поля формы
 */
function getFormValue($fieldName, $default = '') {
    global $previousValues, $storedData;
    
    return $previousValues[$fieldName] ?? $storedData[$fieldName] ?? $default;
}

/**
 * Проверяет, выбрано ли значение в select/radio
 */
function isOptionSelected($fieldName, $optionValue) {
    global $previousValues, $storedData;
    
    $currentValue = $previousValues[$fieldName] ?? $storedData[$fieldName] ?? null;
    
    if ($fieldName === 'languages') {
        $selectedLanguages = explode(',', $currentValue ?? '');
        return in_array($optionValue, $selectedLanguages) ? 'selected' : '';
    }
    
    return ($currentValue === $optionValue) ? 'checked' : '';
}

/**
 * Проверяет, отмечен ли чекбокс
 */
function isCheckboxChecked($fieldName) {
    global $previousValues, $storedData;
    
    return isset($previousValues[$fieldName]) || isset($storedData[$fieldName]) ? 'checked' : '';
}

/**
 * Возвращает класс для поля с ошибкой
 */
function getErrorClass($fieldName) {
    global $formErrors;
    return isset($formErrors[$fieldName]) ? 'is-invalid' : '';
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Форма регистрации</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .success-alert { color: green; margin: 15px 0; }
        .error-alert { color: #dc3545; margin-bottom: 20px; }
        .error-list { background-color: #f8d7da; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .invalid-feedback { color: #dc3545; }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h2 class="card-title text-center mb-4">Анкета разработчика</h2>
                        
                        <?php if (isset($_GET['success'])): ?>
                            <div class="alert alert-success">Данные успешно сохранены!</div>
                        <?php endif; ?>
                        
                        <?php if (!empty($formErrors)): ?>
                            <div class="alert alert-danger">
                                <h5 class="alert-heading">Обнаружены ошибки:</h5>
                                <ul class="mb-0">
                                    <?php foreach ($formErrors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <form action="mur.php" method="POST" novalidate>
                            <!-- Личные данные -->
                            <div class="mb-3">
                                <label for="name" class="form-label">ФИО</label>
                                <input type="text" class="form-control <?= getErrorClass('name') ?>" 
                                       id="name" name="name" placeholder="Иванов Иван Иванович" 
                                       value="<?= htmlspecialchars(getFormValue('name')) ?>" required>
                                <?php if (isset($formErrors['name'])): ?>
                                    <div class="invalid-feedback"><?= htmlspecialchars($formErrors['name']) ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Контактная информация -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Телефон</label>
                                    <input type="tel" class="form-control <?= getErrorClass('phone') ?>" 
                                           id="phone" name="phone" placeholder="+71234567890" 
                                           value="<?= htmlspecialchars(getFormValue('phone')) ?>" required>
                                    <?php if (isset($formErrors['phone'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($formErrors['phone']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control <?= getErrorClass('email') ?>" 
                                           id="email" name="email" placeholder="example@mail.com" 
                                           value="<?= htmlspecialchars(getFormValue('email')) ?>" required>
                                    <?php if (isset($formErrors['email'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($formErrors['email']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Дата рождения и пол -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="birthdate" class="form-label">Дата рождения</label>
                                    <input type="date" class="form-control <?= getErrorClass('birthdate') ?>" 
                                           id="birthdate" name="birthdate" 
                                           value="<?= htmlspecialchars(getFormValue('birthdate', '2000-07-15')) ?>" required>
                                    <?php if (isset($formErrors['birthdate'])): ?>
                                        <div class="invalid-feedback"><?= htmlspecialchars($formErrors['birthdate']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Пол</label>
                                    <div class="form-control <?= getErrorClass('gender') ?> border-0">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" id="male" 
                                                   name="gender" value="male" 
                                                   <?= isOptionSelected('gender', 'male') ?> required>
                                            <label class="form-check-label" for="male">Мужской</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" id="female" 
                                                   name="gender" value="female" 
                                                   <?= isOptionSelected('gender', 'female') ?>>
                                            <label class="form-check-label" for="female">Женский</label>
                                        </div>
                                    </div>
                                    <?php if (isset($formErrors['gender'])): ?>
                                        <div class="invalid-feedback d-block"><?= htmlspecialchars($formErrors['gender']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Языки программирования -->
                            <div class="mb-3">
                                <label for="languages" class="form-label">Любимые языки программирования</label>
                                <select class="form-select <?= getErrorClass('languages') ?>" 
                                        id="languages" name="languages[]" multiple size="5" required>
                                    <?php
                                    $programmingLanguages = [
                                        'Pascal', 'C', 'C++', 'JavaScript', 
                                        'PHP', 'Python', 'Java', 'Haskell', 
                                        'Clojure', 'Prolog', 'Scala'
                                    ];
                                    
                                    foreach ($programmingLanguages as $lang): ?>
                                        <option value="<?= htmlspecialchars($lang) ?>" 
                                            <?= isOptionSelected('languages', $lang) ?>>
                                            <?= htmlspecialchars($lang) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($formErrors['languages'])): ?>
                                    <div class="invalid-feedback"><?= htmlspecialchars($formErrors['languages']) ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Биография -->
                            <div class="mb-3">
                                <label for="bio" class="form-label">Биография</label>
                                <textarea class="form-control <?= getErrorClass('bio') ?>" 
                                          id="bio" name="bio" rows="3" required><?= 
                                          htmlspecialchars(getFormValue('bio')) ?></textarea>
                                <?php if (isset($formErrors['bio'])): ?>
                                    <div class="invalid-feedback"><?= htmlspecialchars($formErrors['bio']) ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Соглашение -->
                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input <?= getErrorClass('agreement') ?>" 
                                           type="checkbox" id="agreement" name="agreement" 
                                           value="1" <?= isCheckboxChecked('agreement') ?> required>
                                    <label class="form-check-label" for="agreement">
                                        С контрактом ознакомлен(а)
                                    </label>
                                    <?php if (isset($formErrors['agreement'])): ?>
                                        <div class="invalid-feedback d-block"><?= htmlspecialchars($formErrors['agreement']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Кнопка отправки -->
                            <div class="d-grid">
                                <button type="submit" name="save" class="btn btn-primary btn-lg">
                                    Отправить анкету
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
