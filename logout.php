<?php
ob_start();
session_start();
ini_set('display_errors', 0);
session_destroy();
header('Location: login.php');
exit;
ob_end_flush();
?>