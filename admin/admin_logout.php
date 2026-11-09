<?php
require_once '../connection/db.php';
app_session_start();
session_unset();
session_destroy();
header('Location: admin_login.php');
exit();
?>
