<?php
// Router for PHP built-in server - blocks access to sensitive files

$request_uri = $_SERVER['REQUEST_URI'];
$parsed_url = parse_url($request_uri);
$path = $parsed_url['path'] ?? '/';

// Block access to sensitive files and directories
$blocked_patterns = [
    '/private/',           // Private directory
    '/\.git',              // Git directory
    '/\.ht',               // .htaccess, .htpasswd
    '/\.sqlite',           // SQLite files
    '/init_db\.php',       // Database initialization script
    '/router\.php',        // This router file itself
];

foreach ($blocked_patterns as $pattern) {
    if (strpos($path, $pattern) !== false || preg_match('#' . $pattern . '#', $path)) {
        http_response_code(403);
        header('Content-Type: text/plain');
        echo '403 Forbidden';
        exit;
    }
}

// Allow the request to continue to the actual file
return false;
?>
