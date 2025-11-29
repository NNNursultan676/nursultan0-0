<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Обработка уведомления об успешном обновлении
if (isset($_GET['success']) && $_GET['success'] === 'updated') {
    $success = 'Оценки обновлены!';
}

// Обновление оценок
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_grade'])) {
    check_csrf();
    
    $grade_id = $_POST['grade_id'] ?? 0;
    $rk1 = floatval($_POST['rk1'] ?? 0);
    $rk2 = floatval($_POST['rk2'] ?? 0);
    $exam_score = floatval($_POST['exam_score'] ?? 0);
    $exam_max = floatval($_POST['exam_max'] ?? 100);
    
    $stmt = $pdo->prepare("
        UPDATE grades 
        SET rk1 = ?, rk2 = ?, exam_score = ?, exam_max = ? 
        WHERE id = ? AND user_id = ?
    ");
    if ($stmt->execute([$rk1, $rk2, $exam_score, $exam_max, $grade_id, $user_id])) {
        regenerate_csrf_token();
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Location: grades.php?success=updated&nocache=' . uniqid());
        exit;
    } else {
        $error = 'Ошибка обновления';
    }
}

// Получение всех предметов и оценок пользователя
$stmt = $pdo->prepare("
    SELECT s.id AS subject_id, s.name, s.teacher, s.max_points,
           g.id AS grade_id, g.rk1, g.rk2, g.exam_score, g.exam_max
    FROM subjects s
    LEFT JOIN grades g ON s.id = g.subject_id AND g.user_id = ?
    ORDER BY s.name
");
$stmt->execute([$user_id]);
$subjects = $stmt->fetchAll();

// ✅ Проверка и создание недостающих записей без дублей
foreach ($subjects as &$subject) {
    if (!$subject['grade_id']) {
        // Проверяем, нет ли уже такой записи
        $check = $pdo->prepare("SELECT id, rk1, rk2, exam_score, exam_max FROM grades WHERE user_id = ? AND subject_id = ?");
        $check->execute([$user_id, $subject['subject_id']]);
        $existing = $check->fetch();

        if (!$existing) {
            // Создаем новую запись только если её точно нет
            $stmt = $pdo->prepare("INSERT OR IGNORE INTO grades (user_id, subject_id, rk1, rk2, exam_score, exam_max) VALUES (?, ?, 0, 0, 0, 100)");
            $stmt->execute([$user_id, $subject['subject_id']]);
            
            // Получаем созданную запись
            $check->execute([$user_id, $subject['subject_id']]);
            $existing = $check->fetch();
        }
        
        if ($existing) {
            $subject['grade_id'] = $existing['id'];
            $subject['rk1'] = $existing['rk1'] ?? 0;
            $subject['rk2'] = $existing['rk2'] ?? 0;
            $subject['exam_score'] = $existing['exam_score'] ?? 0;
            $subject['exam_max'] = $existing['exam_max'] ?? 100;
        }
    }
}
unset($subject);

$pageTitle = 'Оценки - Student Dark Notebook';
include 'includes/header.php';
?>

<div class="page-content">
    <h2 class="page-title">📓 Мои оценки</h2>
    
    <?php if ($success): ?>
        <div class="success-message"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="error-message"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="grades-container">
        <?php foreach ($subjects as $subject): ?>
            <div class="grade-card">
                <h3 class="grade-subject"><?= htmlspecialchars($subject['name']) ?></h3>
                <p class="grade-teacher">👨‍🏫 <?= htmlspecialchars($subject['teacher']) ?></p>
                
                <form method="POST" action="grades.php" class="grade-form">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="grade_id" value="<?= $subject['grade_id']; ?>">
                    <input type="hidden" name="update_grade" value="1">
                    
                    <div class="grade-inputs">
                        <div class="grade-input-group">
                            <label>РК1</label>
                            <input type="number" name="rk1" step="0.1" min="0" max="100" 
                                   value="<?= $subject['rk1']; ?>">
                        </div>
                        
                        <div class="grade-input-group">
                            <label>РК2</label>
                            <input type="number" name="rk2" step="0.1" min="0" max="100" 
                                   value="<?= $subject['rk2']; ?>">
                        </div>
                        
                        <div class="grade-input-group">
                            <label>Балл экзамена</label>
                            <input type="number" name="exam_score" step="0.1" min="0" max="<?= $subject['exam_max']; ?>" 
                                   value="<?= $subject['exam_score']; ?>">
                        </div>
                        
                        <div class="grade-input-group">
                            <label>Макс балл экзамена</label>
                            <input type="number" name="exam_max" step="0.1" min="0" 
                                   value="<?= $subject['exam_max']; ?>">
                        </div>
                    </div>
                    
                    <?php 
                    $exam_percent = $subject['exam_max'] > 0 ? ($subject['exam_score'] / $subject['exam_max']) : 0;
                    $total = ($subject['rk1'] + $subject['rk2']) * 0.3 + ($exam_percent * 100) * 0.4;
                    ?>
                    <div class="grade-total">
                        <strong>Итог:</strong> <?= number_format($total, 1); ?>
                    </div>
                    
                    <button type="submit" class="btn-primary btn-sm">Сохранить</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
