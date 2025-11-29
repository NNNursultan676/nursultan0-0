<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle clearing completed tasks from previous week
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_old_tasks'])) {
    check_csrf();
    
    $week_start = date('Y-m-d', strtotime('monday this week'));
    
    $stmt = $pdo->prepare("
        DELETE FROM task_completions 
        WHERE user_id = ? 
        AND is_done = 1 
        AND task_id IN (SELECT id FROM tasks WHERE due_date < ?)
    ");
    
    if ($stmt->execute([$user_id, $week_start])) {
        regenerate_csrf_token();
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Location: tasks.php?nocache=' . uniqid());
        exit;
    }
}

// Handle task completion toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_task'])) {
    check_csrf();
    
    $task_id = $_POST['task_id'] ?? 0;
    
    // Check if completion exists
    $stmt = $pdo->prepare("SELECT id, is_done FROM task_completions WHERE user_id = ? AND task_id = ?");
    $stmt->execute([$user_id, $task_id]);
    $completion = $stmt->fetch();
    
    if ($completion) {
        $new_status = !$completion['is_done'];
        $stmt = $pdo->prepare("UPDATE task_completions SET is_done = ?, completed_at = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $new_status ? date('Y-m-d H:i:s') : null, $completion['id']])) {
            regenerate_csrf_token();
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Location: tasks.php?nocache=' . uniqid());
            exit;
        }
    } else {
        $stmt = $pdo->prepare("INSERT INTO task_completions (user_id, task_id, is_done, completed_at) VALUES (?, ?, 1, ?)");
        if ($stmt->execute([$user_id, $task_id, date('Y-m-d H:i:s')])) {
            regenerate_csrf_token();
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Location: tasks.php?nocache=' . uniqid());
            exit;
        }
    }
}

// Get all tasks for the current week
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));

$stmt = $pdo->prepare("
    SELECT t.*, s.name as subject_name,
           tc.is_done,
           CASE WHEN t.due_date < DATE('now') AND (tc.is_done = 0 OR tc.is_done IS NULL) THEN 1 ELSE 0 END as is_overdue
    FROM tasks t
    JOIN subjects s ON t.subject_id = s.id
    LEFT JOIN task_completions tc ON t.id = tc.task_id AND tc.user_id = ?
    WHERE t.due_date BETWEEN ? AND ?
    ORDER BY t.due_date, s.name
");
$stmt->execute([$user_id, $week_start, $week_end]);
$tasks = $stmt->fetchAll();

$pageTitle = 'Задания - Student Dark Notebook';
include 'includes/header.php';
?>

<div class="page-content">
    <h2 class="page-title">📚 Задания и домашка</h2>
    
    <div class="week-info" style="display: flex; justify-content: space-between; align-items: center;">
        <p>Неделя: <?php echo date('d.m', strtotime($week_start)); ?> - <?php echo date('d.m.Y', strtotime($week_end)); ?></p>
        <form method="POST" style="margin: 0;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="clear_old_tasks" value="1">
            <button type="submit" class="btn-primary btn-sm" onclick="return confirm('Очистить выполненные задания прошлой недели?')">
                🗑️ Очистить старые
            </button>
        </form>
    </div>

    <?php if (empty($tasks)): ?>
        <div class="empty-state">
            <p>📭 На эту неделю заданий нет</p>
        </div>
    <?php else: ?>
        <?php
        // Разделяем задания на невыполненные и выполненные
        $incomplete_tasks = array_filter($tasks, function($task) { return !$task['is_done']; });
        $completed_tasks = array_filter($tasks, function($task) { return $task['is_done']; });
        ?>
        
        <div class="tasks-list">
            <?php foreach ($incomplete_tasks as $task): ?>
                <div class="task-card <?php echo $task['is_overdue'] ? 'overdue' : ''; ?>">
                    <div class="task-header">
                        <form method="POST" class="task-checkbox-form">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                            <input type="checkbox" name="toggle_task" 
                                   <?php echo $task['is_done'] ? 'checked' : ''; ?>
                                   onchange="this.form.submit()">
                        </form>
                        <div class="task-info">
                            <h4 class="task-subject"><?php echo htmlspecialchars($task['subject_name']); ?></h4>
                            <p class="task-title"><?php echo htmlspecialchars($task['title']); ?></p>
                        </div>
                    </div>
                    
                    <?php if ($task['description']): ?>
                        <p class="task-description"><?php echo htmlspecialchars($task['description']); ?></p>
                    <?php endif; ?>
                    
                    <div class="task-footer">
                        <span class="task-due">📅 <?php echo date('d.m.Y', strtotime($task['due_date'])); ?></span>
                        <?php if ($task['points'] > 0): ?>
                            <span class="task-points">⭐ <?php echo $task['points']; ?> баллов</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (!empty($completed_tasks)): ?>
                <h3 style="margin-top: 30px; margin-bottom: 15px; color: var(--text-secondary);">✅ Выполненные</h3>
                <?php foreach ($completed_tasks as $task): ?>
                    <div class="task-card completed" style="text-decoration: line-through; opacity: 0.6;">
                        <div class="task-header">
                            <form method="POST" class="task-checkbox-form">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <input type="checkbox" name="toggle_task" checked onchange="this.form.submit()">
                            </form>
                            <div class="task-info">
                                <h4 class="task-subject"><?php echo htmlspecialchars($task['subject_name']); ?></h4>
                                <p class="task-title"><?php echo htmlspecialchars($task['title']); ?></p>
                            </div>
                        </div>
                        
                        <?php if ($task['description']): ?>
                            <p class="task-description"><?php echo htmlspecialchars($task['description']); ?></p>
                        <?php endif; ?>
                        
                        <div class="task-footer">
                            <span class="task-due">📅 <?php echo date('d.m.Y', strtotime($task['due_date'])); ?></span>
                            <?php if ($task['points'] > 0): ?>
                                <span class="task-points">⭐ <?php echo $task['points']; ?> баллов</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
