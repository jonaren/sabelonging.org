<?php
// logout.php - Simple logout script
session_start();
session_destroy();
header('Location: login.php');
exit;
?>