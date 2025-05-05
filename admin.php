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

// Обработка удаления
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $app_id = $_GET['delete'];
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('DELETE FROM application_languages WHERE application_id = ?');
        $stmt->execute([$app_id]);
        $stmt = $pdo->prepare('DELETE FROM users WHERE application_id = ?');
        $stmt->execute([$app_id]);
        $stmt = $pdo->prepare('DELETE FROM applications WHERE id = ?');
        $stmt->execute([$app_id]);
        $pdo->commit();
        $_SESSION['success_message'] = 'Запись успешно удалена.';
        header('Location: admin.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = 'Ошибка удаления: ' . $e->getMessage();
    }
}

// Получение всех заявок
$stmt = $pdo->query('SELECT * FROM applications');
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Получение языков для каждой заявки
$languages = [];
foreach ($applications as $app) {
    $stmt = $pdo->prepare('SELECT pl.name 
                           FROM application_languages al 
                           JOIN programming_languages pl ON al.language_id = pl.id 
                           WHERE al.application_id = ?');
    $stmt->execute([$app['id']]);
    $langs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $languages[$app['id']] = $langs;
}

// Получение статистики
$stmt = $pdo->query('SELECT pl.name, COUNT(al.application_id) as count
                     FROM programming_languages pl
                     LEFT JOIN application_languages al ON pl.id = al.language_id
                     GROUP BY pl.id, pl.name');
$stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>Панель администратора</h1>
    <?php if (isset($_SESSION['success_message'])): ?>
        <p style="color: green;"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></p>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <p style="color: red;"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></p>
    <?php endif; ?>

    <h2>Все заявки</h2>
    <table class="admin-table">
        <tr>
            <th>ID</th>
            <th>ФИО</th>
            <th>Телефон</th>
            <th>Email</th>
            <th>Дата рождения</th>
            <th>Пол</th>
            <th>Языки</th>
            <th>Биография</th>
            <th>Действия</th>
        </tr>
        <?php foreach ($applications as $app): ?>
            <tr>
                <td><?php echo htmlspecialchars($app['id']); ?></td>
                <td><?php echo htmlspecialchars($app['fio']); ?></td>
                <td><?php echo htmlspecialchars($app['phone']); ?></td>
                <td><?php echo htmlspecialchars($app['email']); ?></td>
                <td><?php echo htmlspecialchars($app['birthdate']); ?></td>
                <td><?php echo htmlspecialchars($app['gender'] === 'male' ? 'Мужской' : 'Женский'); ?></td>
                <td><?php echo htmlspecialchars(implode(', ', $languages[$app['id']])); ?></td>
                <td><?php echo htmlspecialchars(substr($app['bio'], 0, 50)) . (strlen($app['bio']) > 50 ? '...' : ''); ?></td>
                <td>
                    <a href="admin_edit.php?id=<?php echo htmlspecialchars($app['id']); ?>">Редактировать</a> |
                    <a href="admin.php?delete=<?php echo htmlspecialchars($app['id']); ?>" onclick="return confirm('Вы уверены, что хотите удалить эту запись?');">Удалить</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

    <h2>Статистика по языкам программирования</h2>
    <table class="admin-table">
        <tr>
            <th>Язык</th>
            <th>Количество пользователей</th>
        </tr>
        <?php foreach ($stats as $stat): ?>
            <tr>
                <td><?php echo htmlspecialchars($stat['name']); ?></td>
                <td><?php echo htmlspecialchars($stat['count']); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <p><a href="logout.php">Выйти</a></p>
</body>
</html>
<?php ob_end_flush(); ?>