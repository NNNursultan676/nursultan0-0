
<?php
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$success = '';
$error = '';

// Handle avatar upload
if (isset($_POST['upload_avatar'])) {
    check_csrf();
    
    $target_user_id = intval($_POST['target_user_id'] ?? 0);
    
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['avatar']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $max_size = 2 * 1024 * 1024; // 2MB
            if ($_FILES['avatar']['size'] <= $max_size) {
                $upload_dir = __DIR__ . '/assets/avatars/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $new_filename = 'user_' . $target_user_id . '.' . $ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                    // Удаляем старый аватар если он другой
                    $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
                    $stmt->execute([$target_user_id]);
                    $old_avatar = $stmt->fetchColumn();
                    if ($old_avatar && $old_avatar !== $new_filename && file_exists(__DIR__ . '/assets/avatars/' . $old_avatar)) {
                        unlink(__DIR__ . '/assets/avatars/' . $old_avatar);
                    }
                    
                    $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                    $stmt->execute([$new_filename, $target_user_id]);
                    
                    regenerate_csrf_token();
                    header('Location: admin_panel.php?edit_user=' . $target_user_id . '&success=avatar_updated&t=' . time());
                    exit;
                } else {
                    $error = 'Ошибка при загрузке файла';
                }
            } else {
                $error = 'Файл слишком большой (максимум 2MB)';
            }
        } else {
            $error = 'Недопустимый формат файла (разрешены: jpg, jpeg, png, gif)';
        }
    }
}

// Handle profile update
if (isset($_POST['update_profile'])) {
    check_csrf();
    
    $target_user_id = intval($_POST['target_user_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    
    if (empty($username)) {
        $error = 'Логин не может быть пустым';
    } else {
        // Проверяем уникальность логина
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $target_user_id]);
        if ($stmt->fetch()) {
            $error = 'Пользователь с таким логином уже существует';
        } else {
            $update_parts = ["username = ?", "full_name = ?"];
            $params = [$username, $full_name];
            
            // Если указан новый пароль
            if (!empty($new_password)) {
                if (strlen($new_password) < 4) {
                    $error = 'Пароль должен быть не менее 4 символов';
                } else {
                    $update_parts[] = "password = ?";
                    $params[] = password_hash($new_password, PASSWORD_DEFAULT);
                }
            }
            
            if (empty($error)) {
                $params[] = $target_user_id;
                $sql = "UPDATE users SET " . implode(", ", $update_parts) . " WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                
                if ($stmt->execute($params)) {
                    regenerate_csrf_token();
                    
                    // Обновляем сессию если админ изменил свой логин
                    if ($target_user_id === $_SESSION['user_id']) {
                        $_SESSION['username'] = $username;
                    }
                    
                    header('Location: admin_panel.php?edit_user=' . $target_user_id . '&success=profile_updated&t=' . time());
                    exit;
                } else {
                    $error = 'Ошибка при обновлении профиля';
                }
            }
        }
    }
}

// Handle success messages from redirects
if (isset($_GET['success'])) {
    $success_messages = [
        'user_added' => 'Пользователь добавлен!',
        'user_deleted' => 'Пользователь удален!',
        'profile_updated' => 'Профиль обновлен!',
        'avatar_updated' => 'Аватар обновлен!',
        'subject_added' => 'Предмет добавлен!',
        'subject_updated' => 'Предмет обновлен!',
        'subject_deleted' => 'Предмет удален!',
        'task_added' => 'Задание добавлено!',
        'task_deleted' => 'Задание удалено!',
        'schedule_added' => 'Занятие добавлено в расписание!',
        'schedule_deleted' => 'Занятие удалено!'
    ];
    $success = $success_messages[$_GET['success']] ?? 'Операция выполнена успешно!';
}

// Add User
if (isset($_POST['add_user'])) {
    check_csrf();
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? 'student';
    
    if (empty($username)) {
        $error = 'Введите логин пользователя';
    } elseif (empty($password)) {
        $error = 'Введите пароль';
    } else {
        try {
            // Check if username exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Пользователь с таким логином уже существует';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
                
                if ($stmt->execute([$username, $hashed_password, $full_name, $role])) {
                    regenerate_csrf_token();
                    header('Location: admin_panel.php?success=user_added&t=' . time());
                    exit;
                } else {
                    $error = 'Ошибка при добавлении пользователя';
                }
            }
        } catch (PDOException $e) {
            $error = 'Ошибка базы данных: ' . $e->getMessage();
        }
    }
}

// Delete User
if (isset($_POST['delete_user'])) {
    check_csrf();
    
    $user_id = $_POST['user_id'] ?? 0;
    if ($user_id != $_SESSION['user_id']) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            regenerate_csrf_token();
            header('Location: admin_panel.php?success=user_deleted&t=' . time());
            exit;
        }
    } else {
        $error = 'Нельзя удалить себя!';
    }
}

// Add Subject
if (isset($_POST['add_subject'])) {
    check_csrf();
    
    $name = trim($_POST['subject_name'] ?? '');
    $teacher = trim($_POST['teacher'] ?? '');
    $teacher_phone = trim($_POST['teacher_phone'] ?? '');
    $max_points = intval($_POST['max_points'] ?? 100);
    
    if (!empty($name)) {
        $stmt = $pdo->prepare("INSERT INTO subjects (name, teacher, teacher_phone, max_points) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$name, $teacher, $teacher_phone, $max_points])) {
            regenerate_csrf_token();
            header('Location: admin_panel.php?success=subject_added&t=' . time());
            exit;
        }
    }
}

// Edit Subject
if (isset($_POST['edit_subject'])) {
    check_csrf();
    
    $subject_id = $_POST['subject_id'] ?? 0;
    $name = trim($_POST['subject_name'] ?? '');
    $teacher = trim($_POST['teacher'] ?? '');
    $teacher_phone = trim($_POST['teacher_phone'] ?? '');
    $max_points = intval($_POST['max_points'] ?? 100);
    
    if (!empty($name) && $subject_id > 0) {
        $stmt = $pdo->prepare("UPDATE subjects SET name = ?, teacher = ?, teacher_phone = ?, max_points = ? WHERE id = ?");
        if ($stmt->execute([$name, $teacher, $teacher_phone, $max_points, $subject_id])) {
            regenerate_csrf_token();
            header('Location: admin_panel.php?success=subject_updated&t=' . time());
            exit;
        }
    }
}

// Delete Subject
if (isset($_POST['delete_subject'])) {
    check_csrf();
    
    $subject_id = intval($_POST['subject_id'] ?? 0);
    
    if ($subject_id > 0) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Delete related data in correct order
            $stmt1 = $pdo->prepare("DELETE FROM task_completions WHERE task_id IN (SELECT id FROM tasks WHERE subject_id = ?)");
            $stmt1->execute([$subject_id]);
            
            $stmt2 = $pdo->prepare("DELETE FROM tasks WHERE subject_id = ?");
            $stmt2->execute([$subject_id]);
            
            $stmt3 = $pdo->prepare("DELETE FROM grades WHERE subject_id = ?");
            $stmt3->execute([$subject_id]);
            
            $stmt4 = $pdo->prepare("DELETE FROM schedule WHERE subject_id = ?");
            $stmt4->execute([$subject_id]);
            
            $stmt5 = $pdo->prepare("DELETE FROM debts WHERE subject_id = ?");
            $stmt5->execute([$subject_id]);
            
            // Delete the subject itself
            $stmt6 = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
            $stmt6->execute([$subject_id]);
            
            // Commit transaction
            $pdo->commit();
            
            regenerate_csrf_token();
            
            // Clear all possible caches
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Pragma: no-cache');
            header('Expires: 0');
            header('Location: admin_panel.php?success=subject_deleted&nocache=' . uniqid());
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Ошибка при удалении предмета: ' . $e->getMessage();
        }
    }
}

// Add Task
if (isset($_POST['add_task'])) {
    check_csrf();
    
    $subject_id = $_POST['task_subject_id'] ?? 0;
    $title = trim($_POST['task_title'] ?? '');
    $description = trim($_POST['task_description'] ?? '');
    $due_date = $_POST['task_due_date'] ?? '';
    $due_time = $_POST['task_due_time'] ?? '';
    $points = floatval($_POST['task_points'] ?? 0);
    
    if (!empty($title) && !empty($due_date)) {
        $full_due = $due_date . ($due_time ? ' ' . $due_time : ' 23:59:59');
        $stmt = $pdo->prepare("INSERT INTO tasks (subject_id, title, description, due_date, points) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$subject_id, $title, $description, $full_due, $points])) {
            regenerate_csrf_token();
            header('Location: admin_panel.php?success=task_added&t=' . time());
            exit;
        }
    }
}

// Delete Task
if (isset($_POST['delete_task'])) {
    check_csrf();
    
    $task_id = $_POST['task_id'] ?? 0;
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
    if ($stmt->execute([$task_id])) {
        regenerate_csrf_token();
        header('Location: admin_panel.php?success=task_deleted&t=' . time());
        exit;
    }
}

// Add Schedule
if (isset($_POST['add_schedule'])) {
    check_csrf();
    
    $day_of_week = $_POST['schedule_day'] ?? '';
    $subject_id = $_POST['schedule_subject_id'] ?? 0;
    $time = trim($_POST['schedule_time'] ?? '');
    $room = trim($_POST['schedule_room'] ?? '');
    $teacher = trim($_POST['schedule_teacher'] ?? '');
    
    if ($day_of_week !== '' && !empty($time)) {
        $stmt = $pdo->prepare("INSERT INTO schedule (day_of_week, subject_id, time, room, teacher) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$day_of_week, $subject_id, $time, $room, $teacher])) {
            regenerate_csrf_token();
            header('Location: admin_panel.php?success=schedule_added&t=' . time());
            exit;
        }
    }
}

// Delete Schedule
if (isset($_POST['delete_schedule'])) {
    check_csrf();
    
    $schedule_id = $_POST['schedule_id'] ?? 0;
    $stmt = $pdo->prepare("DELETE FROM schedule WHERE id = ?");
    if ($stmt->execute([$schedule_id])) {
        regenerate_csrf_token();
        header('Location: admin_panel.php?success=schedule_deleted&t=' . time());
        exit;
    }
}

// Set headers to prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

// Get all data with fresh queries
$users = $pdo->query("SELECT * FROM users ORDER BY role, username")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll();
$tasks = $pdo->query("SELECT t.*, s.name as subject_name FROM tasks t JOIN subjects s ON t.subject_id = s.id ORDER BY t.due_date DESC LIMIT 20")->fetchAll();
$schedules = $pdo->query("SELECT sc.*, s.name as subject_name FROM schedule sc JOIN subjects s ON sc.subject_id = s.id ORDER BY sc.day_of_week, sc.time")->fetchAll();

$pageTitle = 'Панель администратора - Student Dark Notebook';
include 'includes/header.php';
?>

<div class="page-content">
    <h2 class="page-title">⚙️ Панель администратора</h2>
    
    <?php if ($success): ?>
        <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="admin-sections">
        
        <!-- User Profile Management -->
        <div class="admin-section">
            <h3>👤 Управление профилями пользователей</h3>
            
            <?php if (isset($_GET['edit_user'])): 
                $edit_user_id = intval($_GET['edit_user']);
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$edit_user_id]);
                $edit_user = $stmt->fetch();
                
                if ($edit_user):
            ?>
                <div style="background: var(--bg-dark); padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <h4>✏️ Редактирование профиля: <?php echo htmlspecialchars($edit_user['username']); ?></h4>
                    
                    <!-- Avatar Section -->
                    <div style="text-align: center; margin: 20px 0;">
                        <?php 
                        $avatar_path = 'assets/avatars/' . $edit_user['avatar'];
                        $avatar_exists = !empty($edit_user['avatar']) && file_exists(__DIR__ . '/' . $avatar_path);
                        ?>
                        <?php if ($avatar_exists): ?>
                            <img src="<?php echo htmlspecialchars($avatar_path); ?>?v=<?php echo time(); ?>" 
                                 alt="Avatar" 
                                 style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent-primary);">
                        <?php else: ?>
                            <div style="width: 120px; height: 120px; border-radius: 50%; background: var(--bg-card); border: 3px solid var(--border-sketch); display: flex; align-items: center; justify-content: center; margin: 0 auto; font-size: 50px;">
                                👤
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" style="margin-bottom: 20px;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="target_user_id" value="<?php echo $edit_user_id; ?>">
                        <div class="form-group">
                            <label for="avatar">Загрузить новый аватар (jpg, png, gif, максимум 2MB)</label>
                            <input type="file" name="avatar" id="avatar" accept="image/*">
                        </div>
                        <input type="hidden" name="upload_avatar" value="1">
                        <button type="submit" class="btn-primary btn-sm">📷 Обновить аватар</button>
                    </form>
                    
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="target_user_id" value="<?php echo $edit_user_id; ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_username">Логин *</label>
                                <input type="text" name="username" id="edit_username" value="<?php echo htmlspecialchars($edit_user['username']); ?>" required minlength="3" maxlength="50">
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_full_name">Полное имя</label>
                                <input type="text" name="full_name" id="edit_full_name" value="<?php echo htmlspecialchars($edit_user['full_name'] ?? ''); ?>" maxlength="100">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_new_password">Новый пароль (оставьте пустым, чтобы не менять)</label>
                            <input type="password" name="new_password" id="edit_new_password" minlength="4">
                        </div>
                        
                        <div style="background: rgba(124, 179, 66, 0.1); padding: 10px; border-radius: 6px; margin: 15px 0; font-size: 14px;">
                            <strong>Информация:</strong><br>
                            Роль: <?php echo $edit_user['role'] === 'admin' ? 'Администратор' : 'Студент'; ?><br>
                            Зарегистрирован: <?php echo date('d.m.Y H:i', strtotime($edit_user['created_at'])); ?>
                        </div>
                        
                        <input type="hidden" name="update_profile" value="1">
                        <button type="submit" class="btn-primary">💾 Сохранить изменения</button>
                        <a href="admin_panel.php" class="btn-secondary" style="margin-left: 10px;">◀ Назад к списку</a>
                    </form>
                </div>
            <?php 
                endif;
            else: 
            ?>
                <div class="users-grid">
                    <?php 
                    $all_users = $pdo->query("SELECT id, username, full_name, role, avatar, created_at FROM users ORDER BY username")->fetchAll();
                    foreach ($all_users as $user): 
                    ?>
                        <div class="user-card">
                            <a href="?edit_user=<?php echo $user['id']; ?>" style="text-decoration: none; color: inherit;">
                                <div class="user-card-avatar">
                                    <?php 
                                    $user_avatar_path = 'assets/avatars/' . $user['avatar'];
                                    $user_avatar_exists = !empty($user['avatar']) && file_exists(__DIR__ . '/' . $user_avatar_path);
                                    ?>
                                    <?php if ($user_avatar_exists): ?>
                                        <img src="<?php echo htmlspecialchars($user_avatar_path); ?>?v=<?php echo time(); ?>" alt="Avatar">
                                    <?php else: ?>
                                        <div class="avatar-placeholder">
                                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="user-card-info">
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                    <?php if ($user['full_name']): ?>
                                        <div class="text-secondary"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                    <?php endif; ?>
                                    <div class="user-role">
                                        <?php echo $user['role'] === 'admin' ? '👑 Администратор' : '📚 Студент'; ?>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- User Management -->
        <div class="admin-section">
            <h3>👥 Управление пользователями</h3>
            
            <form method="POST" class="admin-form">
                <?php echo csrf_field(); ?>
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Логин *</label>
                        <input type="text" name="username" id="username" required minlength="3" maxlength="50">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Пароль *</label>
                        <input type="password" name="password" id="password" required minlength="4">
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name">Полное имя</label>
                        <input type="text" name="full_name" id="full_name" maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Роль</label>
                        <select name="role" id="role" required>
                            <option value="student">Студент</option>
                            <option value="admin">Администратор</option>
                        </select>
                    </div>
                </div>
                
                <input type="hidden" name="add_user" value="1">
                <button type="submit" class="btn-primary">Добавить пользователя</button>
            </form>
            
            <h4 style="margin-top: 30px; margin-bottom: 15px;">Список пользователей</h4>
            <div class="users-list">
                <?php foreach ($users as $user): ?>
                    <div class="user-item" style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: var(--bg-dark); border: 1px solid var(--border-sketch); border-radius: 6px; margin-bottom: 10px;">
                        <div>
                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                            <?php if ($user['full_name']): ?>
                                - <?php echo htmlspecialchars($user['full_name']); ?>
                            <?php endif; ?>
                            <span style="color: var(--text-secondary); margin-left: 10px;">
                                (<?php echo $user['role'] === 'admin' ? 'Администратор' : 'Студент'; ?>)
                            </span>
                        </div>
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <form method="POST" style="margin: 0;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <input type="hidden" name="delete_user" value="1">
                                <button type="submit" class="btn-icon btn-danger" onclick="return confirm('Удалить пользователя?')">🗑️</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Subject Management -->
        <div class="admin-section">
            <h3>📚 Управление предметами</h3>
            
            <form method="POST" class="admin-form">
                <?php echo csrf_field(); ?>
                <div class="form-row">
                    <div class="form-group">
                        <label for="subject_name">Название предмета</label>
                        <input type="text" name="subject_name" id="subject_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="teacher">Преподаватель</label>
                        <input type="text" name="teacher" id="teacher">
                    </div>
                    
                    <div class="form-group">
                        <label for="teacher_phone">Телефон преподавателя</label>
                        <input type="text" name="teacher_phone" id="teacher_phone" placeholder="+7 (___) ___-__-__">
                    </div>
                    
                    <div class="form-group">
                        <label for="max_points">Максимум баллов</label>
                        <input type="number" name="max_points" id="max_points" value="100">
                    </div>
                </div>
                
                <input type="hidden" name="add_subject" value="1">
                <button type="submit" class="btn-primary">Добавить предмет</button>
            </form>
            
            <h4 style="margin-top: 30px; margin-bottom: 15px;">Список предметов</h4>
            <div class="subjects-list">
                <?php foreach ($subjects as $subject): ?>
                    <div class="subject-item" style="padding: 15px; background: var(--bg-dark); border: 1px solid var(--border-sketch); border-radius: 6px; margin-bottom: 10px;">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div style="flex: 1;">
                                <strong style="font-size: 16px;"><?php echo htmlspecialchars($subject['name']); ?></strong>
                                <?php if ($subject['teacher']): ?>
                                    <div style="color: var(--text-secondary); margin-top: 5px;">
                                        👨‍🏫 <?php echo htmlspecialchars($subject['teacher']); ?>
                                        <?php if ($subject['teacher_phone']): ?>
                                            - <?php echo htmlspecialchars($subject['teacher_phone']); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <div style="color: var(--accent-primary); margin-top: 5px;">
                                    Макс. баллов: <?php echo $subject['max_points']; ?>
                                </div>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button onclick="editSubject(<?php echo htmlspecialchars(json_encode($subject)); ?>)" class="btn-icon">✏️</button>
                                <form method="POST" style="margin: 0;">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                    <input type="hidden" name="delete_subject" value="1">
                                    <button type="submit" class="btn-icon btn-danger" onclick="return confirm('Удалить предмет? Это удалит все связанные данные!')">🗑️</button>
                                </form>
                            </div>
                        </div>
                        
                        <div id="edit-form-<?php echo $subject['id']; ?>" style="display: none; margin-top: 15px; padding-top: 15px; border-top: 1px dashed var(--border-sketch);">
                            <form method="POST">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Название</label>
                                        <input type="text" name="subject_name" value="<?php echo htmlspecialchars($subject['name']); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Преподаватель</label>
                                        <input type="text" name="teacher" value="<?php echo htmlspecialchars($subject['teacher']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Телефон</label>
                                        <input type="text" name="teacher_phone" value="<?php echo htmlspecialchars($subject['teacher_phone']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Макс. баллов</label>
                                        <input type="number" name="max_points" value="<?php echo $subject['max_points']; ?>">
                                    </div>
                                </div>
                                <input type="hidden" name="edit_subject" value="1">
                                <button type="submit" class="btn-primary btn-sm">Сохранить изменения</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Task Management -->
        <div class="admin-section">
            <h3>📝 Управление заданиями</h3>
            
            <form method="POST" class="admin-form">
                <?php echo csrf_field(); ?>
                <div class="form-row">
                    <div class="form-group">
                        <label for="task_subject_id">Предмет</label>
                        <select name="task_subject_id" id="task_subject_id" required>
                            <option value="">Выберите предмет</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="task_title">Название задания</label>
                        <input type="text" name="task_title" id="task_title" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="task_description">Описание (что нужно сделать)</label>
                    <textarea name="task_description" id="task_description" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="task_due_date">Срок сдачи (дата)</label>
                        <input type="date" name="task_due_date" id="task_due_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="task_due_time">Время сдачи</label>
                        <input type="time" name="task_due_time" id="task_due_time">
                    </div>
                    
                    <div class="form-group">
                        <label for="task_points">Ценность в баллах</label>
                        <input type="number" name="task_points" id="task_points" step="0.1" value="0" min="0">
                    </div>
                </div>
                
                <input type="hidden" name="add_task" value="1">
                <button type="submit" class="btn-primary">Добавить задание</button>
            </form>
            
            <h4 style="margin-top: 30px; margin-bottom: 15px;">Недавние задания</h4>
            <div class="tasks-list-admin">
                <?php foreach ($tasks as $task): ?>
                    <div class="task-item-admin" style="padding: 15px; background: var(--bg-dark); border: 1px solid var(--border-sketch); border-radius: 6px; margin-bottom: 10px;">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div style="flex: 1;">
                                <div style="color: var(--accent-primary); font-size: 14px;"><?php echo htmlspecialchars($task['subject_name']); ?></div>
                                <strong style="font-size: 16px;"><?php echo htmlspecialchars($task['title']); ?></strong>
                                <?php if ($task['description']): ?>
                                    <div style="color: var(--text-secondary); margin-top: 5px;">
                                        <?php echo htmlspecialchars($task['description']); ?>
                                    </div>
                                <?php endif; ?>
                                <div style="margin-top: 8px; color: var(--text-secondary); font-size: 14px;">
                                    📅 <?php echo date('d.m.Y H:i', strtotime($task['due_date'])); ?>
                                    <?php if ($task['points'] > 0): ?>
                                        | ⭐ <?php echo $task['points']; ?> баллов
                                    <?php endif; ?>
                                </div>
                            </div>
                            <form method="POST" style="margin: 0;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <input type="hidden" name="delete_task" value="1">
                                <button type="submit" class="btn-icon btn-danger" onclick="return confirm('Удалить задание?')">🗑️</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Schedule Management -->
        <div class="admin-section">
            <h3>📅 Управление расписанием</h3>
            
            <form method="POST" class="admin-form">
                <?php echo csrf_field(); ?>
                <div class="form-row">
                    <div class="form-group">
                        <label for="schedule_day">День недели</label>
                        <select name="schedule_day" id="schedule_day" required>
                            <option value="1">Понедельник</option>
                            <option value="2">Вторник</option>
                            <option value="3">Среда</option>
                            <option value="4">Четверг</option>
                            <option value="5">Пятница</option>
                            <option value="6">Суббота</option>
                            <option value="0">Воскресенье</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="schedule_subject_id">Предмет</label>
                        <select name="schedule_subject_id" id="schedule_subject_id" required>
                            <option value="">Выберите предмет</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>"><?php echo htmlspecialchars($subject['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="schedule_time">Время</label>
                        <input type="time" name="schedule_time" id="schedule_time" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="schedule_room">Кабинет</label>
                        <input type="text" name="schedule_room" id="schedule_room">
                    </div>
                    
                    <div class="form-group">
                        <label for="schedule_teacher">Преподаватель</label>
                        <input type="text" name="schedule_teacher" id="schedule_teacher">
                    </div>
                </div>
                
                <input type="hidden" name="add_schedule" value="1">
                <button type="submit" class="btn-primary">Добавить в расписание</button>
            </form>
            
            <h4 style="margin-top: 30px; margin-bottom: 15px;">Расписание по дням недели</h4>
            <div class="schedule-list-admin">
                <?php foreach ($schedules as $schedule): ?>
                    <div class="schedule-item-admin" style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: var(--bg-dark); border: 1px solid var(--border-sketch); border-radius: 6px; margin-bottom: 10px;">
                        <div>
                            <strong><?php 
                                $days = ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'];
                                echo $days[$schedule['day_of_week']]; 
                            ?></strong> в 
                            <span style="color: var(--accent-primary);"><?php echo htmlspecialchars($schedule['time']); ?></span> -
                            <?php echo htmlspecialchars($schedule['subject_name']); ?>
                            <?php if ($schedule['room']): ?>
                                | Каб. <?php echo htmlspecialchars($schedule['room']); ?>
                            <?php endif; ?>
                            <?php if ($schedule['teacher']): ?>
                                | <?php echo htmlspecialchars($schedule['teacher']); ?>
                            <?php endif; ?>
                        </div>
                        <form method="POST" style="margin: 0;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="schedule_id" value="<?php echo $schedule['id']; ?>">
                            <input type="hidden" name="delete_schedule" value="1">
                            <button type="submit" class="btn-icon btn-danger" onclick="return confirm('Удалить занятие?')">🗑️</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
    </div>
</div>

<style>
.users-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.user-card {
    background: var(--bg-dark);
    border: 2px solid var(--border-sketch);
    border-radius: 8px;
    padding: 15px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.user-card:hover {
    border-color: var(--accent-primary);
    transform: translateY(-2px);
}

.user-card-avatar {
    text-align: center;
    margin-bottom: 10px;
}

.user-card-avatar img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--accent-primary);
}

.avatar-placeholder {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--accent-primary);
    color: white;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    font-weight: bold;
}

.user-card-info {
    text-align: center;
}

.user-role {
    margin-top: 5px;
    font-size: 12px;
    color: var(--text-secondary);
}

.text-secondary {
    color: var(--text-secondary);
    font-size: 14px;
}

.btn-secondary {
    display: inline-block;
    background: var(--bg-dark);
    color: var(--text-primary);
    padding: 10px 20px;
    border: 2px solid var(--border-sketch);
    border-radius: 6px;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-secondary:hover {
    border-color: var(--accent-primary);
    background: var(--bg-card);
}
</style>

<script>
function editSubject(subject) {
    const formId = 'edit-form-' + subject.id;
    const form = document.getElementById(formId);
    if (form) {
        const isVisible = form.style.display !== 'none';
        // Hide all edit forms first
        document.querySelectorAll('[id^="edit-form-"]').forEach(f => f.style.display = 'none');
        // Toggle current form
        form.style.display = isVisible ? 'none' : 'block';
    }
}
</script>

<?php include 'includes/footer.php'; ?>
