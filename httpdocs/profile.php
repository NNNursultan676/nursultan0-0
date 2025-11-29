
<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Handle profile update
if (isset($_POST['update_profile'])) {
    check_csrf();
    
    $new_username = trim($_POST['username'] ?? '');
    $new_full_name = trim($_POST['full_name'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_username)) {
        $error = 'Логин не может быть пустым';
    } else {
        try {
            // Check if username is taken by another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$new_username, $user_id]);
            if ($stmt->fetch()) {
                $error = 'Этот логин уже занят';
            } else {
                // Update username and full name
                $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ? WHERE id = ?");
                $stmt->execute([$new_username, $new_full_name, $user_id]);
                
                $_SESSION['username'] = $new_username;
                $_SESSION['full_name'] = $new_full_name;
                
                // Update password if provided
                if (!empty($new_password)) {
                    if ($new_password !== $confirm_password) {
                        $error = 'Пароли не совпадают';
                    } else {
                        // Verify current password
                        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $user = $stmt->fetch();
                        
                        if (password_verify($current_password, $user['password'])) {
                            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                            $stmt->execute([$hashed, $user_id]);
                            $success = 'Профиль и пароль обновлены!';
                        } else {
                            $error = 'Неверный текущий пароль';
                        }
                    }
                } else {
                    $success = 'Профиль обновлен!';
                }
                
                if (!$error) {
                    regenerate_csrf_token();
                    header('Location: profile.php?success=updated');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $error = 'Ошибка базы данных: ' . $e->getMessage();
        }
    }
}

// Handle avatar upload
if (isset($_POST['upload_avatar'])) {
    check_csrf();
    
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['avatar']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $upload_dir = __DIR__ . '/assets/avatars/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $new_filename = 'user_' . $user_id . '.' . $ext;
            $destination = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destination)) {
                $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->execute([$new_filename, $user_id]);
                regenerate_csrf_token();
                header('Location: profile.php?success=avatar_updated');
                exit;
            } else {
                $error = 'Ошибка загрузки файла';
            }
        } else {
            $error = 'Разрешены только изображения (jpg, png, gif)';
        }
    }
}

if (isset($_GET['success'])) {
    $messages = [
        'updated' => 'Профиль успешно обновлен!',
        'avatar_updated' => 'Аватар обновлен!'
    ];
    $success = $messages[$_GET['success']] ?? 'Операция выполнена!';
}

// Get current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$pageTitle = 'Мой профиль - Student Dark Notebook';
include 'includes/header.php';
?>

<div class="page-content">
    <h2 class="page-title">👤 Мой профиль</h2>
    
    <?php if ($success): ?>
        <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <div class="admin-sections">
        <!-- Avatar Section -->
        <div class="admin-section">
            <h3>🖼️ Аватар</h3>
            <div style="text-align: center; margin-bottom: 20px;">
                <?php 
                $avatar_path = 'assets/avatars/' . $user['avatar'];
                $avatar_exists = !empty($user['avatar']) && file_exists(__DIR__ . '/' . $avatar_path);
                ?>
                <?php if ($avatar_exists): ?>
                    <img src="<?php echo htmlspecialchars($avatar_path); ?>?v=<?php echo time(); ?>" 
                         alt="Avatar" 
                         style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent-primary);">
                <?php else: ?>
                    <div style="width: 150px; height: 150px; border-radius: 50%; background: var(--bg-dark); border: 3px solid var(--border-sketch); display: flex; align-items: center; justify-content: center; margin: 0 auto; font-size: 60px;">
                        👤
                    </div>
                <?php endif; ?>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="admin-form">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label for="avatar">Выберите новый аватар</label>
                    <input type="file" name="avatar" id="avatar" accept="image/*" required>
                </div>
                <input type="hidden" name="upload_avatar" value="1">
                <button type="submit" class="btn-primary">Загрузить аватар</button>
            </form>
        </div>
        
        <!-- Profile Info Section -->
        <div class="admin-section">
            <h3>📝 Информация профиля</h3>
            
            <form method="POST" class="admin-form">
                <?php echo csrf_field(); ?>
                
                <div class="form-group">
                    <label for="username">Логин *</label>
                    <input type="text" name="username" id="username" 
                           value="<?php echo htmlspecialchars($user['username']); ?>" 
                           required minlength="3" maxlength="50">
                </div>
                
                <div class="form-group">
                    <label for="full_name">Полное имя</label>
                    <input type="text" name="full_name" id="full_name" 
                           value="<?php echo htmlspecialchars($user['full_name']); ?>" 
                           maxlength="100">
                </div>
                
                <div class="form-group">
                    <label>Роль</label>
                    <input type="text" value="<?php echo $user['role'] === 'admin' ? 'Администратор' : ($user['role'] === 'manager' ? 'Менеджер' : 'Студент'); ?>" disabled>
                </div>
                
                <h4 style="margin-top: 30px; margin-bottom: 15px;">Изменить пароль</h4>
                <p style="color: var(--text-secondary); margin-bottom: 15px;">Оставьте поля пустыми, если не хотите менять пароль</p>
                
                <div class="form-group">
                    <label for="current_password">Текущий пароль</label>
                    <input type="password" name="current_password" id="current_password">
                </div>
                
                <div class="form-group">
                    <label for="new_password">Новый пароль</label>
                    <input type="password" name="new_password" id="new_password" minlength="4">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Подтвердите новый пароль</label>
                    <input type="password" name="confirm_password" id="confirm_password" minlength="4">
                </div>
                
                <input type="hidden" name="update_profile" value="1">
                <button type="submit" class="btn-primary">Сохранить изменения</button>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
