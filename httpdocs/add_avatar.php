<?php
$pdo = new PDO('sqlite:private/database.sqlite');
$pdo->exec('ALTER TABLE users ADD COLUMN avatar TEXT DEFAULT ""');
echo "✅ Avatar column added successfully!";
?>
