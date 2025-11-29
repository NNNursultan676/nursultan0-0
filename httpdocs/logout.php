<?php
require_once 'db.php';

session_destroy();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Location: login.php');
exit;
?>