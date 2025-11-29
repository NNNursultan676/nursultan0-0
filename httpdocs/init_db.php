<?php
// Initialize SQLite database for Student Dark Notebook

// Create private directory if it doesn't exist
$private_dir = __DIR__ . '/private';
if (!is_dir($private_dir)) {
    mkdir($private_dir, 0755, true);
}

$db_path = $private_dir . '/database.sqlite';
$db = new PDO('sqlite:' . $db_path);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "Creating database tables...\n";

// Users table
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    full_name TEXT DEFAULT '',
	avatar TEXT DEFAULT '',
    role TEXT DEFAULT 'student' CHECK(role IN ('student', 'manager', 'admin')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Subjects table
$db->exec("CREATE TABLE IF NOT EXISTS subjects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    teacher TEXT DEFAULT '',
    teacher_phone TEXT DEFAULT '',
    max_points INTEGER DEFAULT 100,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Grades table
$db->exec("CREATE TABLE IF NOT EXISTS grades (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    subject_id INTEGER NOT NULL,
    rk1 REAL DEFAULT 0,
    rk2 REAL DEFAULT 0,
    exam_score REAL DEFAULT 0,
    exam_max REAL DEFAULT 100,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE(user_id, subject_id)
)");

// Tasks table
$db->exec("CREATE TABLE IF NOT EXISTS tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    subject_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    description TEXT,
    due_date DATE NOT NULL,
    points REAL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
)");

// Task completions
$db->exec("CREATE TABLE IF NOT EXISTS task_completions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    task_id INTEGER NOT NULL,
    is_done INTEGER DEFAULT 0,
    completed_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    UNIQUE(user_id, task_id)
)");

// Schedule table
$db->exec("CREATE TABLE IF NOT EXISTS schedule (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    date DATE NOT NULL,
    subject_id INTEGER NOT NULL,
    time TEXT NOT NULL,
    room TEXT DEFAULT '',
    teacher TEXT DEFAULT '',
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
)");
$db->exec("CREATE INDEX IF NOT EXISTS idx_schedule_date ON schedule(date)");

// Debts table
$db->exec("CREATE TABLE IF NOT EXISTS debts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    subject_id INTEGER NOT NULL,
    description TEXT NOT NULL,
    due_date DATE NOT NULL,
    room TEXT DEFAULT '',
    is_completed INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
)");

echo "Tables created successfully!\n";

// Check if admin user exists
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE username = 'Nurs'");
if ($stmt->fetchColumn() == 0) {
    echo "Creating admin user...\n";
    // Insert admin user (username: Nurs, password: 9506)
    $password_hash = password_hash('9506', PASSWORD_DEFAULT);
    $db->exec("INSERT INTO users (username, password, full_name, role) VALUES 
        ('Nurs', '$password_hash', 'Администратор', 'admin')");
    echo "Admin user created! (Login: Nurs, Password: 9506)\n";
}

// Check if subjects exist
$stmt = $db->query("SELECT COUNT(*) FROM subjects");
if ($stmt->fetchColumn() == 0) {
    echo "Adding sample subjects...\n";
    $db->exec("INSERT INTO subjects (name, teacher, max_points) VALUES
        ('Математика', 'Иванов И.И.', 100),
        ('Программирование', 'Петров П.П.', 100),
        ('Физика', 'Сидоров С.С.', 100),
        ('Английский язык', 'Смирнова А.А.', 100)");
    echo "Sample subjects added!\n";
}

echo "\n✅ Database initialized successfully!\n";
echo "📂 Database file: $db_path\n";
echo "👤 Admin login: Nurs / 9506\n";
