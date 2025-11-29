<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Calculate total points for all users (students and admins)
$stmt = $pdo->query("
    SELECT u.id, u.username, u.full_name, u.role,
           SUM((g.rk1 + g.rk2) * 0.3 + (g.exam_score / g.exam_max * 100) * 0.4) as total_points
    FROM users u
    LEFT JOIN grades g ON u.id = g.user_id
    GROUP BY u.id, u.username, u.full_name, u.role
    ORDER BY total_points DESC
");
$ratings = $stmt->fetchAll();

$pageTitle = 'Журнал - Student Dark Notebook';
include 'includes/header.php';
?>

<div class="page-content">
    <h2 class="page-title">📚 Журнал оценок</h2>
    
    <div class="rating-info">
        <p>Журнал показывает оценки всех пользователей системы</p>
    </div>

    <div class="rating-table">
        <table>
            <thead>
                <tr>
                    <th>Место</th>
                    <th>Пользователь</th>
                    <th>Роль</th>
                    <th>Всего баллов</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $position = 1;
                foreach ($ratings as $rating): 
                    $is_current_user = ($rating['id'] == $_SESSION['user_id']);
                ?>
                    <tr class="<?php echo $is_current_user ? 'current-user' : ''; ?>">
                        <td class="position">
                            <?php 
                            if ($position == 1) echo '🥇';
                            elseif ($position == 2) echo '🥈';
                            elseif ($position == 3) echo '🥉';
                            else echo $position;
                            ?>
                        </td>
                        <td class="student-name">
                            <?php echo htmlspecialchars($rating['full_name'] ?: $rating['username']); ?>
                            <?php if ($is_current_user): ?>
                                <span class="badge">Вы</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $role_labels = [
                                'student' => '👨‍🎓 Студент',
                                'manager' => '👔 Староста',
                                'admin' => '👨‍💼 Админ'
                            ];
                            echo $role_labels[$rating['role']] ?? $rating['role'];
                            ?>
                        </td>
                        <td class="points"><?php echo number_format($rating['total_points'] ?? 0, 1); ?></td>
                    </tr>
                <?php 
                    $position++;
                endforeach; 
                ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
