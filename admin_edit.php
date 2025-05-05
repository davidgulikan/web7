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

// HTTP-авторизация
$admin_login = $_SERVER['PHP_AUTH_USER'] ?? '';
$admin_password = $_SERVER['PHP_AUTH_PW'] ?? '';

if (!$admin_login) {
    header('WWW-Authenticate: Basic realm="Зона администратора"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Требуется авторизация';
    exit;
}

$stmt = $pdo->prepare('SELECT password_hash FROM admins WHERE login = ?');
$stmt->execute([$admin_login]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin || !password_verify($admin_password, $admin['password_hash'])) {
    header('WWW-Authenticate: Basic realm="Зона администратора"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Неверные учетные данные';
    exit;
}

// Получение ID заявки
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin.php');
    exit;
}
$app_id = $_GET['id'];

// Получение данных заявки
$stmt = $pdo->prepare('SELECT * FROM applications WHERE id = ?');
$stmt->execute([$app_id]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$app) {
    header('Location: admin.php');
    exit;
}

// Получение текущих языков
$stmt = $pdo->prepare('SELECT language_id FROM application_languages WHERE application_id = ?');
$stmt->execute([$app_id]);
$current_languages = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Генерация CSRF-токена
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Проверка CSRF-токена
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Неверный CSRF-токен');
    }

    $errors = validate_form($_POST);

    if (empty($errors)) {
        $stmt = $pdo->prepare('UPDATE applications SET fio = ?, phone = ?, email = ?, birthdate = ?, gender = ?, bio = ?, contract = ? WHERE id = ?');
        $stmt->execute([
            $_POST['fio'],
            $_POST['phone'],
            $_POST['email'],
            $_POST['birthdate'],
            $_POST['gender'],
            $_POST['bio'],
            1,
            $app_id
        ]);

        $stmt = $pdo->prepare('DELETE FROM application_languages WHERE application_id = ?');
        $stmt->execute([$app_id]);

        $stmt = $pdo->prepare('INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)');
        foreach ($_POST['languages'] as $lang) {
            $stmt->execute([$app_id, $lang]);
        }

        $_SESSION['success_message'] = 'Данные успешно обновлены.';
        header('Location: admin.php');
        exit;
    } else {
        setcookie('form_errors', json_encode($errors), 0, '/');
        foreach ($_POST as $key => $value) {
            if ($key === 'languages') {
                setcookie($key, implode(',', $value), 0, '/');
            } else {
                setcookie($key, $value, 0, '/');
            }
        }
    }
}

// Получение списка языков для формы
$stmt = $pdo->query('SELECT id, name FROM programming_languages ORDER BY id');
$languages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование заявки</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>Редактирование заявки #<?php echo htmlspecialchars($app_id); ?></h1>
    <?php if (isset($_SESSION['success_message'])): ?>
        <p style="color: green;"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></p>
    <?php endif; ?>
    <?php
    $errors = isset($_COOKIE['form_errors']) ? json_decode($_COOKIE['form_errors'], true) : [];
    ?>
    <?php if (!empty($errors)): ?>
        <div class="errors">
            <?php foreach ($errors as $message): ?>
                <p><?php echo htmlspecialchars($message); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form action="admin_edit.php?id=<?php echo htmlspecialchars($app_id); ?>" method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
        <label for="fio">ФИО:</label>
        <input type="text" id="fio" name="fio" value="<?php echo htmlspecialchars($app['fio']); ?>" required><br>

        <label for="phone">Телефон:</label>
        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($app['phone']); ?>" required><br>

        <label for="email">E-mail:</label>
        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($app['email']); ?>" required><br>

        <label for="birthdate">Дата рождения:</label>
        <input type="date" id="birthdate" name="birthdate" value="<?php echo htmlspecialchars($app['birthdate']); ?>" required><br>

        <label>Пол:</label>
        <input type="radio" id="male" name="gender" value="male" <?php echo $app['gender'] === 'male' ? 'checked' : ''; ?> required>
        <label for="male">Мужской</label>
        <input type="radio" id="female" name="gender" value="female" <?php echo $app['gender'] === 'female' ? 'checked' : ''; ?>>
        <label for="female">Женский</label><br>

        <label for="languages">Любимый язык программирования:</label>
        <select id="languages" name="languages[]" multiple required>
            <?php foreach ($languages as $lang): ?>
                <option value="<?php echo htmlspecialchars($lang['id']); ?>" <?php echo in_array($lang['id'], $current_languages) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($lang['name']); ?>
                </option>
            <?php endforeach; ?>
        </select><br>

        <label for="bio">Биография:</label>
        <textarea id="bio" name="bio" rows="4" required><?php echo htmlspecialchars($app['bio']); ?></textarea><br>

        <input type="checkbox" id="contract" name="contract" checked required>
        <label for="contract">С контрактом ознакомлен(а)</label><br>

        <input type="submit" value="Сохранить изменения">
    </form>
    <p><a href="admin.php">Вернуться к списку</a></p>
</body>
</html>
<?php ob_end_flush(); ?>