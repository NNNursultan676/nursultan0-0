
<?php
session_start();
require_once 'db.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// ======= ОБРАБОТКА POST =======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    if (isset($_POST['add_debt'])) {
        $subject_id = (int)($_POST['subject_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $due_date = $_POST['due_date'] ?? '';
        $room = trim($_POST['room'] ?? '');

        if (!$subject_id || !$description || !$due_date) {
            $error = 'Заполните все обязательные поля';
        } else {
            $stmt = $pdo->prepare("INSERT INTO debts (user_id, subject_id, description, due_date, room) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$user_id, $subject_id, $description, $due_date, $room])) {
                regenerate_csrf_token();
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
                header('Location: debts.php?success=added&nocache=' . uniqid());
                exit;
            }
        }
    }

    if (isset($_POST['complete_debt'])) {
        $debt_id = (int)($_POST['debt_id'] ?? 0);
        if ($debt_id) {
            $stmt = $pdo->prepare("UPDATE debts SET is_completed = 1 WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$debt_id, $user_id])) {
                regenerate_csrf_token();
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
                header('Location: debts.php?success=completed&nocache=' . uniqid());
                exit;
            }
        }
    }

    if (isset($_POST['uncomplete_debt'])) {
        $debt_id = (int)($_POST['debt_id'] ?? 0);
        if ($debt_id) {
            $stmt = $pdo->prepare("UPDATE debts SET is_completed = 0 WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$debt_id, $user_id])) {
                regenerate_csrf_token();
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
                header('Location: debts.php?nocache=' . uniqid());
                exit;
            }
        }
    }

    if (isset($_POST['delete_debt'])) {
        $debt_id = (int)($_POST['debt_id'] ?? 0);
        if ($debt_id) {
            $stmt = $pdo->prepare("DELETE FROM debts WHERE id = ? AND user_id = ?");
            if ($stmt->execute([$debt_id, $user_id])) {
                regenerate_csrf_token();
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Pragma: no-cache');
                header('Location: debts.php?success=deleted&nocache=' . uniqid());
                exit;
            }
        }
    }
}

// Handle success messages
if (isset($_GET['success'])) {
    $messages = [
        'added' => 'Долг добавлен!',
        'completed' => 'Долг отмечен как выполненный!',
        'deleted' => 'Долг удален!'
    ];
    $success = $messages[$_GET['success']] ?? 'Операция выполнена!';
}

// ======= ПОЛУЧЕНИЕ ДАННЫХ =======
$subjects = $pdo->query("SELECT id, name FROM subjects ORDER BY name")->fetchAll();

// Активные долги
$active_debts_stmt = $pdo->prepare("
    SELECT d.*, s.name as subject_name,
           CASE WHEN d.due_date < DATE('now') THEN 1 ELSE 0 END as is_overdue
    FROM debts d
    JOIN subjects s ON d.subject_id = s.id
    WHERE d.user_id = ? AND d.is_completed = 0
    ORDER BY d.due_date, s.name
");
$active_debts_stmt->execute([$user_id]);
$active_debts = $active_debts_stmt->fetchAll();

// Выполненные долги
$completed_debts_stmt = $pdo->prepare("
    SELECT d.*, s.name as subject_name
    FROM debts d
    JOIN subjects s ON d.subject_id = s.id
    WHERE d.user_id = ? AND d.is_completed = 1
    ORDER BY d.due_date DESC
    LIMIT 10
");
$completed_debts_stmt->execute([$user_id]);
$completed_debts = $completed_debts_stmt->fetchAll();

$pageTitle = 'Долги - Student Dark Notebook';
include __DIR__ . '/includes/header.php';
?>

<div class="page-content">
    <h2 class="page-title">🧾 Мои долги</h2>

    <?php if ($success): ?><div class="success-message"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error-message"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card add-debt-form" style="margin-bottom: 30px;">
        <h3 style="margin-bottom: 20px; color: var(--accent-primary);">➕ Добавить долг</h3>
        <form method="POST" class="grade-form">
            <?php echo csrf_field(); ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Предмет</label>
                    <select name="subject_id" required>
                        <option value="">Выберите предмет</option>
                        <?php foreach ($subjects as $sub): ?>
                            <option value="<?= $sub['id'] ?>"><?= htmlspecialchars($sub['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Что нужно сдать</label>
                    <input type="text" name="description" placeholder="Описание задолженности" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Срок сдачи</label>
                    <input type="date" name="due_date" required>
                </div>
                <div class="form-group">
                    <label>Кабинет</label>
                    <input type="text" name="room" placeholder="Номер кабинета">
                </div>
            </div>
            <input type="hidden" name="add_debt" value="1">
            <button type="submit" class="btn-primary btn-sm">Добавить долг</button>
        </form>
    </div>

    <?php if (empty($active_debts) && empty($completed_debts)): ?>
        <div class="empty-state">
            <p>✅ У вас нет долгов!</p>
        </div>
    <?php else: ?>
        
        <!-- Активные долги -->
        <?php if (!empty($active_debts)): ?>
            <div class="tasks-list" style="margin-bottom: 30px;">
                <h3 style="margin-bottom: 20px; color: var(--text-primary);">⚠️ Активные долги</h3>
                <?php foreach ($active_debts as $d): ?>
                    <div class="task-card <?= $d['is_overdue'] ? 'overdue' : '' ?>">
                        <div class="task-header">
                            <form method="POST" class="task-checkbox-form">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="debt_id" value="<?= $d['id'] ?>">
                                <input type="checkbox" name="complete_debt" onchange="this.form.submit()">
                            </form>
                            <div class="task-info">
                                <h4 class="task-subject"><?= htmlspecialchars($d['subject_name']) ?></h4>
                                <p class="task-title"><?= htmlspecialchars($d['description']) ?></p>
                            </div>
                        </div>
                        
                        <div class="task-footer">
                            <span class="task-due">📅 Сдать до: <?= date('d.m.Y', strtotime($d['due_date'])) ?></span>
                            <?php if ($d['room']): ?>
                                <span class="task-points">🚪 Кабинет: <?= htmlspecialchars($d['room']) ?></span>
                            <?php endif; ?>
                            <?php if ($d['is_overdue']): ?>
                                <span style="color: var(--accent-danger); font-weight: 600;">⏰ Просрочено!</span>
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" style="margin-top: 10px;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="debt_id" value="<?= $d['id'] ?>">
                            <input type="hidden" name="delete_debt" value="1">
                            <button type="submit" class="btn-danger btn-sm" onclick="return confirm('Удалить долг?')">🗑️ Удалить</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Выполненные долги -->
        <?php if (!empty($completed_debts)): ?>
            <div class="tasks-list">
                <h3 style="margin-bottom: 20px; color: var(--text-secondary);">✅ Выполненные долги</h3>
                <?php foreach ($completed_debts as $d): ?>
                    <div class="task-card completed" style="opacity: 0.6;">
                        <div class="task-header">
                            <form method="POST" class="task-checkbox-form">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="debt_id" value="<?= $d['id'] ?>">
                                <input type="checkbox" name="uncomplete_debt" checked onchange="this.form.submit()">
                            </form>
                            <div class="task-info">
                                <h4 class="task-subject" style="text-decoration: line-through;"><?= htmlspecialchars($d['subject_name']) ?></h4>
                                <p class="task-title" style="text-decoration: line-through;"><?= htmlspecialchars($d['description']) ?></p>
                            </div>
                        </div>
                        
                        <div class="task-footer">
                            <span class="task-due">📅 Было до: <?= date('d.m.Y', strtotime($d['due_date'])) ?></span>
                            <?php if ($d['room']): ?>
                                <span class="task-points">🚪 <?= htmlspecialchars($d['room']) ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" style="margin-top: 10px;">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="debt_id" value="<?= $d['id'] ?>">
                            <input type="hidden" name="delete_debt" value="1">
                            <button type="submit" class="btn-danger btn-sm" onclick="return confirm('Удалить долг?')">🗑️ Удалить</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
