<?php
function get_db_connection() {
    $dsn = 'mysql:host=localhost;dbname=u68804';
    $username = 'u68804';
    $password = '9073124';
    try {
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        ini_set('display_errors', 0);
        error_log($e->getMessage(), 3, 'errors.log');
        die('Произошла ошибка. Обратитесь к администратору.');
    }
}
?>