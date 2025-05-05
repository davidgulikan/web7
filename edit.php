<?php
ob_start();
session_start();
ini_set('display_errors', 0);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

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

$stmt = $pdo->prepare('SELECT * FROM applications WHERE id = ?');
$stmt->execute([$_SESSION['application_id']]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT language_id FROM application_languages WHERE application_id = ?');
$stmt->execute([$_SESSION['application_id']]);
$current_languages = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Генерация CSRF-токена
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

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
            $_SESSION['application_id']
        ]);

        $stmt = $pdo->prepare('DELETE FROM application_languages WHERE application_id = ?');
        $stmt->execute([$_SESSION['application_id']]);

        $stmt = $pdo->prepare('INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)');
        foreach ($_POST['languages'] as $lang) {
            $stmt->execute([$_SESSION['application_id'], $lang]);
        }

        $_SESSION['success_message'] = 'Данные успешно обновлены.';
        header('Location: edit.php');
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

$stmt = $pdo->query('SELECT id, name FROM programming_languages ORDER BY id');
$languages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование данных</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>Редактирование данных</h1>
    <?php if (isset($_SESSION['success_message'])): ?>
        <p style="color: green;"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></p>
    <?php endif; ?>
    <?php if (isset($errors) && !empty($errors)): ?>
        <div class="errors">
            <?php foreach ($errors as $message): ?>
                <p><?php echo htmlspecialchars($message); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <form action="edit.php" method="post">
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
    <p><a href="logout.php">Выйти</a></p>
</body>
</html>
<?php ob_end_flush(); ?>