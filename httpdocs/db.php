<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- MySQL connection settings ---
$host = 'localhost:3306';
$dbname = 'nursulta_db';      // имя базы из phpMyAdmin
$username = 'nursulta';       // имя пользователя MySQL (из Plesk/phpMyAdmin)
$password = '72416810';       // пароль из Plesk

// --- Load CSRF protection ---
$csrfPaths = [
    __DIR__ . '/includes/csrf.php',
    __DIR__ . '/../includes/csrf.php',
    __DIR__ . '/app/includes/csrf.php',
];

foreach ($csrfPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

try {
    // --- Подключение к MySQL ---
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("❌ Database connection error: " . $e->getMessage());
}

// --- Start session ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
