<?php
require_once 'src/Auth/AuthController.php';
$auth = new AuthController(null); // Database connection not needed for logout
$auth->logout();
header("Location: users/login.php");
exit();
?>
