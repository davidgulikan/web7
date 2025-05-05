<?php
ob_start();
session_start();
ini_set('display_errors', 0);

$file = 'db_connect.php';
if (file_exists($file)) {
    require_once $file;
} else {
    die('Файл не найден');
}

$file = 'validate_form.php';
if (file_exists($file)) {
    require_once $file;
} else {
    die('Файл не найден');
}

$pdo = get_db_connection();

// Генерация CSRF-токена
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка CSRF-токена
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Неверный CSRF-токен');
    }

    $errors = validate_form($_POST);

    if (!empty($errors)) {
        setcookie('form_errors', json_encode($errors), 0, '/');
        foreach ($_POST as $key => $value) {
            if ($key === 'languages') {
                setcookie($key, implode(',', $value), 0, '/');
            } else {
                setcookie($key, $value, 0, '/');
            }
        }
        header('Location: index.php');
        exit;
    } else {
        $stmt = $pdo->prepare('INSERT INTO applications (fio, phone, email, birthdate, gender, bio, contract) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $_POST['fio'],
            $_POST['phone'],
            $_POST['email'],
            $_POST['birthdate'],
            $_POST['gender'],
            $_POST['bio'],
            1
        ]);

        $application_id = $pdo->lastInsertId();
        $stmt = $pdo->prepare('INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)');
        foreach ($_POST['languages'] as $lang) {
            $stmt->execute([$application_id, $lang]);
        }

        $login = 'user_' . $application_id . '_' . rand(1000, 9999);
        $raw_password = bin2hex(random_bytes(4));
        $password_hash = password_hash($raw_password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('INSERT INTO users (login, password_hash, application_id) VALUES (?, ?, ?)');
        $stmt->execute([$login, $password_hash, $application_id]);

        $expire = time() + 365 * 24 * 60 * 60;
        foreach ($_POST as $key => $value) {
            if ($key === 'languages') {
                setcookie($key, implode(',', $value), $expire, '/');
            } else {
                setcookie($key, $value, $expire, '/');
            }
        }
        setcookie('form_errors', '', time() - 3600, '/');
        $_SESSION['success_message'] = "Данные успешно сохранены. Логин: $login, Пароль: $raw_password. Сохраните эти данные!";
        header('Location: index.php');
        exit;
    }
}
ob_end_flush();
?>