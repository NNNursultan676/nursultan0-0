<?php
require_once 'db.php';

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Заполните все поля';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password, role, full_name FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Устанавливаем сессию
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];

            // 💾 Принудительно сохраняем сессию, чтобы браузер точно получил cookie
            session_write_close();

            // Показываем уведомление и потом редирект
            $success = true;
        } else {
            $error = 'Неверный логин или пароль';
        }
    }
}

$pageTitle = 'Вход - Student Dark Notebook';
include 'includes/header.php';
?>

<?php if ($success): ?>
<div class="notification success" id="successNotification">
    <img src="/assets/success-image.png" alt="Success" class="notification-image">
    <div class="notification-text">Делай ДЗ и пользуйся с удовольствием</div>
    <div class="notification-subtext">А то запишу! 📖</div>
</div>

<script>
// Через 3 секунды — переход на главную страницу
setTimeout(function() {
    window.location.href = 'index.php';
}, 3000);
</script>
<?php endif; ?>

<div class="auth-container">
    <div class="auth-box">
        <h1 class="auth-title">Student Dark Notebook</h1>
        <p class="auth-subtitle">Вход в дневник</p>

        <?php if ($error): ?>
            <div class="error-message-with-image">
                <img src="/assets/error-image.png" alt="Error">
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="auth-form">
            <div class="form-group">
                <label for="username">Логин</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Пароль</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit" class="btn-primary">Войти</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
