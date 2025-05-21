<?php
declare(strict_types=1);
session_start();

require 'db.php';

// Безопасность: Генерация CSRF-токена
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Инициализация данных
$defaultValues = [
    'name' => '',
    'phone' => '',
    'email' => '',
    'birthdate' => '2000-07-15',
    'gender' => '',
    'languages' => [],
    'bio' => '',
    'agreement' => false
];

$messages = [];
$errors = [];
$values = $defaultValues;

// Обработка GET-запроса
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Обработка сообщений после сохранения
    if (!empty($_SESSION['success_message'])) {
        $messages[] = [
            'type' => 'success',
            'text' => $_SESSION['success_message']
        ];
        unset($_SESSION['success_message']);
    }

    if (!empty($_SESSION['error_message'])) {
        $messages[] = [
            'type' => 'danger',
            'text' => $_SESSION['error_message']
        ];
        unset($_SESSION['error_message']);
    }

    // Загрузка ошибок из сессии
    if (!empty($_SESSION['form_errors'])) {
        $errors = $_SESSION['form_errors'];
        unset($_SESSION['form_errors']);
    }

    // Загрузка сохраненных значений из сессии
    if (!empty($_SESSION['form_values'])) {
        $values = array_merge($defaultValues, $_SESSION['form_values']);
        unset($_SESSION['form_values']);
    }

    // Загрузка данных пользователя, если авторизован
    if (!empty($_SESSION['login'])) {
        try {
            $stmt = $pdo->prepare("SELECT a.*, GROUP_CONCAT(l.name) as languages
                FROM applications a
                LEFT JOIN application_languages al ON a.id = al.application_id
                LEFT JOIN languages l ON al.language_id = l.id
                WHERE a.login = ?
                GROUP BY a.id");
            $stmt->execute([$_SESSION['login']]);
            $user_data = $stmt->fetch();

            if ($user_data) {
                foreach ($user_data as $key => $value) {
                    if (array_key_exists($key, $values)) {
                        $values[$key] = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                    }
                }
                $values['languages'] = $user_data['languages'] ? explode(',', $user_data['languages']) : [];
                $values['agreement'] = true;
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $messages[] = [
                'type' => 'danger',
                'text' => 'Ошибка загрузки данных пользователя'
            ];
        }
    }
}

// Установка безопасных заголовков
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
?>
<!DOCTYPE html>
<html lang="ru-RU">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; img-src 'self' data:;">
    <title>Форма заявки на участие</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #f8f9fc;
            --accent-color: #2e59d9;
        }
        
        body {
            background-color: var(--secondary-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .form-container {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin: 2rem 0;
            padding: 2rem;
        }
        
        .form-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1.5rem;
            text-align: center;
            position: relative;
            padding-bottom: 0.5rem;
        }
        
        .form-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--accent-color);
        }
        
        .section-title {
            color: var(--primary-color);
            font-weight: 600;
            margin: 1.5rem 0 1rem;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 0.5rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.5rem 1.5rem;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
        
        .language-select {
            min-height: 150px;
        }
        
        .bio-textarea {
            min-height: 120px;
        }
        
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .form-container {
                padding: 1.5rem;
            }
            
            .btn-container {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-8">
                <div class="form-container">
                    <h2 class="form-title">
                        <i class="bi bi-person-vcard"></i> Форма заявки на участие
                    </h2>
                    
                    <!-- Вывод сообщений -->
                    <?php if (!empty($messages)): ?>
                        <div class="mb-4">
                            <?php foreach ($messages as $message): ?>
                                <div class="alert alert-<?= htmlspecialchars($message['type'], ENT_QUOTES, 'UTF-8') ?> alert-dismissible fade show">
                                    <?= htmlspecialchars($message['text'], ENT_QUOTES, 'UTF-8') ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Вывод ошибок -->
                    <?php if (!empty(array_filter($errors))): ?>
                        <div class="alert alert-danger alert-dismissible fade show mb-4">
                            <h4 class="alert-heading">
                                <i class="bi bi-exclamation-triangle-fill"></i> Обнаружены ошибки
                            </h4>
                            <ul class="mb-0">
                                <?php foreach ($errors as $field => $error): ?>
                                    <?php if (!empty($error)): ?>
                                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Форма -->
                    <form action="mur.php" method="POST" id="application-form" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        
                        <!-- Личные данные -->
                        <div class="mb-4">
                            <h5 class="section-title">
                                <i class="bi bi-person-lines-fill"></i> Личные данные
                            </h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="name" class="form-label">ФИО <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                                        <input type="text" class="form-control <?= !empty($errors['name']) ? 'is-invalid' : '' ?>" 
                                               placeholder="Иванов Иван Иванович" 
                                               name="name" id="name" required 
                                               value="<?= htmlspecialchars($values['name'], ENT_QUOTES, 'UTF-8') ?>"
                                               pattern="^[a-zA-Zа-яА-ЯёЁ\s]{1,150}$"
                                               maxlength="150">
                                        <?php if (!empty($errors['name'])): ?>
                                            <div class="invalid-feedback">
                                                <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="birthdate" class="form-label">Дата рождения <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-calendar-date-fill"></i></span>
                                        <input class="form-control <?= !empty($errors['birthdate']) ? 'is-invalid' : '' ?>" 
                                               type="date" name="birthdate" id="birthdate" required 
                                               value="<?= htmlspecialchars($values['birthdate'], ENT_QUOTES, 'UTF-8') ?>"
                                               min="1900-01-01" max="<?= date('Y-m-d') ?>">
                                        <?php if (!empty($errors['birthdate'])): ?>
                                            <div class="invalid-feedback">
                                                <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($errors['birthdate'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Контактная информация -->
                        <div class="mb-4">
                            <h5 class="section-title">
                                <i class="bi bi-telephone-fill"></i> Контактная информация
                            </h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Телефон <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-phone-fill"></i></span>
                                        <input class="form-control <?= !empty($errors['phone']) ? 'is-invalid' : '' ?>" 
                                               type="tel" placeholder="+7 (XXX) XXX-XX-XX" 
                                               name="phone" id="phone" required 
                                               value="<?= htmlspecialchars($values['phone'], ENT_QUOTES, 'UTF-8') ?>"
                                               pattern="\+?\d[\d\s\-\(\)]{6,}\d"
                                               maxlength="20">
                                        <?php if (!empty($errors['phone'])): ?>
                                            <div class="invalid-feedback">
                                                <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($errors['phone'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                                        <input class="form-control <?= !empty($errors['email']) ? 'is-invalid' : '' ?>" 
                                               type="email" placeholder="example@domain.com" 
                                               name="email" id="email" required 
                                               value="<?= htmlspecialchars($values['email'], ENT_QUOTES, 'UTF-8') ?>"
                                               maxlength="100">
                                        <?php if (!empty($errors['email'])): ?>
                                            <div class="invalid-feedback">
                                                <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Демографическая информация -->
                        <div class="mb-4">
                            <h5 class="section-title">
                                <i class="bi bi-gender-ambiguous"></i> Демографическая информация
                            </h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Пол <span class="text-danger">*</span></label>
                                    <div class="radio-group">
                                        <div class="form-check">
                                            <input class="form-check-input <?= !empty($errors['gender']) ? 'is-invalid' : '' ?>" 
                                                   type="radio" name="gender" id="male" 
                                                   value="male" <?= $values['gender'] === 'male' ? 'checked' : '' ?> required>
                                            <label class="form-check-label" for="male">
                                                <i class="bi bi-gender-male"></i> Мужской
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input <?= !empty($errors['gender']) ? 'is-invalid' : '' ?>" 
                                                   type="radio" name="gender" id="female" 
                                                   value="female" <?= $values['gender'] === 'female' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="female">
                                                <i class="bi bi-gender-female"></i> Женский
                                            </label>
                                        </div>
                                        <?php if (!empty($errors['gender'])): ?>
                                            <div class="invalid-feedback d-block">
                                                <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($errors['gender'], ENT_QUOTES, 'UTF-8') ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Профессиональная информация -->
                        <div class="mb-4">
                            <h5 class="section-title">
                                <i class="bi bi-code-slash"></i> Профессиональная информация
                            </h5>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="languages" class="form-label">Любимые языки программирования <span class="text-danger">*</span></label>
                                    <select class="form-select language-select <?= !empty($errors['languages']) ? 'is-invalid' : '' ?>" 
                                            id="languages" name="languages[]" multiple required>
                                        <?php
                                        $allLanguages = [
                                            'Pascal' => 'bi bi-filetype-pascal',
                                            'C' => 'bi bi-filetype-c',
                                            'C++' => 'bi bi-filetype-cpp',
                                            'JavaScript' => 'bi bi-filetype-js',
                                            'PHP' => 'bi bi-filetype-php',
                                            'Python' => 'bi bi-filetype-py',
                                            'Java' => 'bi bi-filetype-java',
                                            'Haskell' => 'bi bi-filetype-hs',
                                            'Clojure' => 'bi bi-filetype-clj',
                                            'Prolog' => 'bi bi-filetype-pro',
                                            'Scala' => 'bi bi-filetype-scala'
                                        ];
                                        
                                        foreach ($allLanguages as $lang => $icon): ?>
                                            <option value="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>"
                                                <?= in_array($lang, $values['languages']) ? 'selected' : '' ?>>
                                                <i class="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>"></i> <?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Для выбора нескольких вариантов удерживайте Ctrl (Windows) или Command (Mac)</small>
                                    <?php if (!empty($errors['languages'])): ?>
                                        <div class="invalid-feedback d-block">
                                            <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($errors['languages'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-12">
                                    <label for="bio" class="form-label">Биография/Опыт работы <span class="text-danger">*</span></label>
                                    <textarea class="form-control bio-textarea <?= !empty($errors['bio']) ? 'is-invalid' : '' ?>" 
                                              name="bio" id="bio" required
                                              minlength="10" maxlength="2000"><?= htmlspecialchars($values['bio'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                    <small class="text-muted">Опишите ваш опыт работы и навыки (10-2000 символов)</small>
                                    <?php if (!empty($errors['bio'])): ?>
                                        <div class="invalid-feedback">
                                            <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($errors['bio'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Соглашения -->
                        <div class="mb-4">
                            <h5 class="section-title">
                                <i class="bi bi-shield-check"></i> Соглашения
                            </h5>
                            <div class="form-check">
                                <input class="form-check-input <?= !empty($errors['agreement']) ? 'is-invalid' : '' ?>" 
                                       type="checkbox" name="agreement" id="agreement" 
                                       value="1" <?= $values['agreement'] ? 'checked' : '' ?> required>
                                <label class="form-check-label" for="agreement">
                                    Я согласен(а) с обработкой персональных данных и условиями участия <span class="text-danger">*</span>
                                </label>
                                <?php if (!empty($errors['agreement'])): ?>
                                    <div class="invalid-feedback d-block">
                                        <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($errors['agreement'], ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Кнопки отправки -->
                        <div class="d-flex justify-content-between align-items-center mt-5">
                            <small class="text-muted"><span class="text-danger">*</span> Обязательные поля</small>
                            <div class="btn-container">
                                <?php if (!empty($_SESSION['login'])): ?>
                                    <a href="log2.php" class="btn btn-outline-danger me-2">
                                        <i class="bi bi-box-arrow-right"></i> Выйти
                                    </a>
                                <?php endif; ?>
                                <button type="submit" name="save" class="btn btn-primary">
                                    <i class="bi bi-send-fill"></i> Отправить заявку
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script>
        // Валидация формы на клиентской стороне
        document.getElementById('application-form').addEventListener('submit', function(event) {
            let isValid = true;
            const form = this;
            
            // Проверка обязательных полей
            const requiredFields = form.querySelectorAll('[required]');
            requiredFields.forEach(function(field) {
                if (field.type === 'checkbox' && !field.checked) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else if ((field.type === 'text' || field.type === 'email' || field.type === 'tel' || field.tagName === 'TEXTAREA') && !field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else if (field.type === 'radio' && !form.querySelector(`[name="${field.name}"]:checked`)) {
                    const radioGroup = form.querySelectorAll(`[name="${field.name}"]`);
                    radioGroup.forEach(radio => radio.classList.add('is-invalid'));
                    isValid = false;
                } else if (field.id === 'languages' && field.selectedOptions.length === 0) {
                    field.classList.add('is-invalid');
                    isValid = false;
                }
            });
            
            if (!isValid) {
                event.preventDefault();
                // Прокрутка к первой ошибке
                const firstError = form.querySelector('.is-invalid');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
        
        // Убираем класс ошибки при изменении поля
        document.querySelectorAll('input, select, textarea').forEach(function(element) {
            element.addEventListener('input', function() {
                this.classList.remove('is-invalid');
                if (this.type === 'radio') {
                    document.querySelectorAll(`[name="${this.name}"]`).forEach(radio => {
                        radio.classList.remove('is-invalid');
                    });
                }
            });
        });
        
        // Для чекбокса соглашения
        document.getElementById('agreement').addEventListener('change', function() {
            this.classList.remove('is-invalid');
        });
    </script>
</body>
</html>
