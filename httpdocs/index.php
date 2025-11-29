
<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get schedule for all days of week
$stmt = $pdo->prepare("
    SELECT s.*, sub.name as subject_name, sub.teacher_phone
    FROM schedule s
    JOIN subjects sub ON s.subject_id = sub.id
    ORDER BY s.day_of_week, s.time
");
$stmt->execute();
$all_schedule = $stmt->fetchAll();

// Group schedule by day of week
$schedule_by_day = [];
foreach ($all_schedule as $class) {
    $schedule_by_day[$class['day_of_week']][] = $class;
}

// Get tasks for each subject
$tasks_by_subject = [];
$subject_ids = array_unique(array_column($all_schedule, 'subject_id'));
if (!empty($subject_ids)) {
    $placeholders = str_repeat('?,', count($subject_ids) - 1) . '?';
    
    $tasks_stmt = $pdo->prepare("
        SELECT t.*, s.id as subject_id, s.name as subject_name,
               tc.is_done,
               CASE WHEN t.due_date < DATE('now') AND (tc.is_done = 0 OR tc.is_done IS NULL) THEN 1 ELSE 0 END as is_overdue
        FROM tasks t
        JOIN subjects s ON t.subject_id = s.id
        LEFT JOIN task_completions tc ON t.id = tc.task_id AND tc.user_id = ?
        WHERE s.id IN ($placeholders) AND t.due_date >= DATE('now')
        ORDER BY t.due_date
    ");
    $tasks_stmt->execute(array_merge([$user_id], $subject_ids));
    $all_tasks = $tasks_stmt->fetchAll();
    
    foreach ($all_tasks as $task) {
        $tasks_by_subject[$task['subject_id']][] = $task;
    }
}

// Get debts for each subject
$debts_by_subject = [];
if (!empty($subject_ids)) {
    $placeholders = str_repeat('?,', count($subject_ids) - 1) . '?';
    
    $debts_stmt = $pdo->prepare("
        SELECT d.*, s.id as subject_id,
               CASE WHEN d.due_date < DATE('now') THEN 1 ELSE 0 END as is_overdue
        FROM debts d
        JOIN subjects s ON d.subject_id = s.id
        WHERE d.user_id = ? AND d.is_completed = 0 AND s.id IN ($placeholders)
        ORDER BY d.due_date
    ");
    $debts_stmt->execute(array_merge([$user_id], $subject_ids));
    $all_debts = $debts_stmt->fetchAll();
    
    foreach ($all_debts as $debt) {
        $debts_by_subject[$debt['subject_id']][] = $debt;
    }
}

$weekdays = ['Воскресенье', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота'];

// Get dates for current week
$today = new DateTime();
$current_day = $today->format('N'); // 1-7 (Monday-Sunday)
$week_start = clone $today;
$week_start->modify('-' . ($current_day - 1) . ' days');

$pageTitle = 'Главная - Student Dark Notebook';
include 'includes/header.php';
?>

<div class="page-content">
    <h2 class="page-title">📅 Расписание на неделю</h2>

    <?php if (empty($all_schedule)): ?>
        <div class="empty-state">
            <p>📅 Расписание не добавлено</p>
        </div>
    <?php else: ?>
        <!-- Desktop View - Horizontal Layout -->
        <div class="schedule-desktop">
            <div class="week-grid">
                <?php for ($day = 1; $day <= 6; $day++): ?>
                    <?php 
                    $day_date = clone $week_start;
                    $day_date->modify('+' . ($day - 1) . ' days');
                    $formatted_date = $day_date->format('d.m');
                    ?>
                    <div class="day-column">
                        <div class="day-header">
                            <div class="day-name"><?php echo $weekdays[$day]; ?></div>
                            <div class="day-date"><?php echo $formatted_date; ?></div>
                        </div>
                        
                        <div class="day-lessons">
                            <?php if (empty($schedule_by_day[$day])): ?>
                                <div class="empty-lesson-slot">
                                    <div class="empty-icon">📭</div>
                                    <div class="empty-text">Нет занятий</div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($schedule_by_day[$day] as $class): ?>
                                    <div class="lesson-item">
                                        <div class="lesson-time-badge"><?php echo htmlspecialchars($class['time']); ?></div>
                                        <div class="lesson-content">
                                            <div class="lesson-subject-name"><?php echo htmlspecialchars($class['subject_name']); ?></div>
                                            <div class="lesson-meta">
                                                <div class="meta-item">🚪 <?php echo htmlspecialchars($class['room']); ?></div>
                                                <div class="meta-item">👨‍🏫 <?php echo htmlspecialchars($class['teacher']); ?></div>
                                            </div>
                                            
                                            <?php if (!empty($tasks_by_subject[$class['subject_id']]) || !empty($debts_by_subject[$class['subject_id']])): ?>
                                                <div class="lesson-extras">
                                                    <?php if (!empty($tasks_by_subject[$class['subject_id']])): ?>
                                                        <div class="extras-badge tasks-badge" title="Задания">
                                                            📝 <?php echo count($tasks_by_subject[$class['subject_id']]); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($debts_by_subject[$class['subject_id']])): ?>
                                                        <div class="extras-badge debts-badge" title="Долги">
                                                            🧾 <?php echo count($debts_by_subject[$class['subject_id']]); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Mobile View -->
        <div class="schedule-mobile">
            <?php for ($day = 1; $day <= 6; $day++): ?>
                <?php 
                $day_date = clone $week_start;
                $day_date->modify('+' . ($day - 1) . ' days');
                $formatted_date = $day_date->format('d.m.Y');
                ?>
                <div class="mobile-day-section">
                    <h3 class="mobile-day-title"><?php echo $weekdays[$day] . ' • ' . $formatted_date; ?></h3>
                    
                    <?php if (empty($schedule_by_day[$day])): ?>
                        <div class="empty-day-notice">Нет занятий</div>
                    <?php else: ?>
                        
                        <?php foreach ($schedule_by_day[$day] as $class): ?>
                            <div class="schedule-mobile-item">
                                <div class="mobile-lesson-header">
                                    <div class="mobile-lesson-time"><?php echo htmlspecialchars($class['time']); ?></div>
                                    <div class="mobile-lesson-subject"><?php echo htmlspecialchars($class['subject_name']); ?></div>
                                </div>
                                
                                <div class="mobile-lesson-details">
                                    <div>🚪 <?php echo htmlspecialchars($class['room']); ?></div>
                                    <div>👨‍🏫 <?php echo htmlspecialchars($class['teacher']); ?></div>
                                    <?php if (!empty($class['teacher_phone'])): ?>
                                        <div>📱 <?php echo htmlspecialchars($class['teacher_phone']); ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (!empty($tasks_by_subject[$class['subject_id']]) || !empty($debts_by_subject[$class['subject_id']])): ?>
                                    <div class="mobile-lesson-extras">
                                        <?php if (!empty($tasks_by_subject[$class['subject_id']])): ?>
                                            <div class="mobile-tasks">
                                                <div class="mobile-section-label">📝 Задания</div>
                                                <?php foreach ($tasks_by_subject[$class['subject_id']] as $task): ?>
                                                    <div class="mobile-mini-item <?php echo $task['is_done'] ? 'completed' : ''; ?> <?php echo $task['is_overdue'] ? 'overdue' : ''; ?>">
                                                        <?php echo $task['is_done'] ? '✅' : '📌'; ?> 
                                                        <?php echo htmlspecialchars($task['title']); ?>
                                                        <span class="mobile-mini-date">📅 <?php echo date('d.m', strtotime($task['due_date'])); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($debts_by_subject[$class['subject_id']])): ?>
                                            <div class="mobile-debts">
                                                <div class="mobile-section-label">🧾 Долги</div>
                                                <?php foreach ($debts_by_subject[$class['subject_id']] as $debt): ?>
                                                    <div class="mobile-mini-item <?php echo $debt['is_overdue'] ? 'overdue' : ''; ?>">
                                                        ⚠️ <?php echo htmlspecialchars($debt['description']); ?>
                                                        <span class="mobile-mini-date">📅 <?php echo date('d.m', strtotime($debt['due_date'])); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
