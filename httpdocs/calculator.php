
<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$desired_score = $_POST['desired_score'] ?? 90;
$recommendations = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    
    // Get all subjects and current grades
    $stmt = $pdo->prepare("
        SELECT s.name, g.rk1, g.rk2, g.exam_max
        FROM subjects s
        LEFT JOIN grades g ON s.id = g.subject_id AND g.user_id = ?
        ORDER BY s.name
    ");
    $stmt->execute([$user_id]);
    $subjects = $stmt->fetchAll();
    
    foreach ($subjects as $subject) {
        $rk1 = $subject['rk1'] ?? 0;
        $rk2 = $subject['rk2'] ?? 0;
        $exam_max = $subject['exam_max'] ?? 100;
        
        // Formula: итог = (РК1 + РК2) * 0.3 + (Балл_экзамена/Макс_балл * 100) * 0.4
        
        if ($rk1 > 0) {
            // Calculate needed RK2 and exam for target score
            $rk2_needed = 100;
            $current_rk = ($rk1 + $rk2_needed) * 0.3;
            $exam_percent_needed = ($desired_score - $current_rk) / 0.4;
            
            if ($exam_percent_needed > 100) {
                $exam_percent_needed = 100;
                $rk2_needed = ($desired_score - 40) / 0.3 - $rk1;
            }
            
            $advice = '';
            if ($rk2_needed < 0) {
                $advice = "✅ Вы уже достигли целевого балла!";
            } elseif ($rk2_needed > 100 || $exam_percent_needed > 100) {
                $advice = "⚠️ Целевой балл недостижим с текущим РК1";
            } else {
                $advice = sprintf(
                    "📝 РК2: %.1f балла | 📄 Экзамен: %.1f%%",
                    max(0, $rk2_needed),
                    max(0, $exam_percent_needed)
                );
            }
            
            $recommendations[] = [
                'subject' => $subject['name'],
                'current_rk1' => $rk1,
                'current_rk2' => $rk2,
                'rk2_needed' => max(0, min(100, $rk2_needed)),
                'exam_needed' => max(0, min(100, $exam_percent_needed)),
                'advice' => $advice
            ];
        }
    }
}

$pageTitle = 'Калькулятор - Student Dark Notebook';
include 'includes/header.php';
?>

<div class="page-content">
    <h2 class="page-title">🧮 Калькулятор оценок</h2>
    
    <div class="calculator-info">
        <p>Формула расчета: <strong>(РК1 + РК2) × 0.3 + (Балл_экзамена/Макс_балл × 100) × 0.4</strong></p>
    </div>
    
    <form method="POST" class="calculator-form">
        <?php echo csrf_field(); ?>
        <div class="form-group">
            <label for="desired_score">Желаемый итоговый балл:</label>
            <input type="number" id="desired_score" name="desired_score" 
                   min="0" max="100" step="0.1" value="<?php echo $desired_score; ?>" required>
        </div>
        <button type="submit" class="btn-primary">Рассчитать</button>
    </form>

    <?php if (!empty($recommendations)): ?>
    <div class="recommendations">
        <h3 class="section-title">Рекомендации по предметам</h3>
        <div class="recommendations-grid">
            <?php foreach ($recommendations as $index => $rec): ?>
                <div class="recommendation-card">
                    <h4><?php echo htmlspecialchars($rec['subject']); ?></h4>
                    <div class="current-scores">
                        <span>РК1: <?php echo $rec['current_rk1']; ?></span>
                        <span>РК2: <?php echo $rec['current_rk2']; ?></span>
                    </div>
                    <div class="advice"><?php echo $rec['advice']; ?></div>
                    
                    <div class="interactive-calculator">
                        <div class="slider-group">
                            <label>РК1: <span id="rk1-value-<?php echo $index; ?>"><?php echo number_format($rec['current_rk1'], 1); ?></span></label>
                            <input type="range" class="score-slider" 
                                   data-index="<?php echo $index; ?>"
                                   data-type="rk1"
                                   min="0" max="100" step="0.1" 
                                   value="<?php echo $rec['current_rk1']; ?>">
                        </div>
                        
                        <div class="slider-group">
                            <label>РК2: <span id="rk2-value-<?php echo $index; ?>"><?php echo number_format($rec['rk2_needed'], 1); ?></span></label>
                            <input type="range" class="score-slider" 
                                   data-index="<?php echo $index; ?>"
                                   data-type="rk2"
                                   min="0" max="100" step="0.1" 
                                   value="<?php echo $rec['rk2_needed']; ?>">
                        </div>
                        
                        <div class="slider-group">
                            <label>Экзамен: <span id="exam-value-<?php echo $index; ?>"><?php echo number_format($rec['exam_needed'], 1); ?>%</span></label>
                            <input type="range" class="score-slider" 
                                   data-index="<?php echo $index; ?>"
                                   data-type="exam"
                                   min="0" max="100" step="0.1" 
                                   value="<?php echo $rec['exam_needed']; ?>">
                        </div>
                        
                        <div class="calculated-result">
                            Итоговый балл: <strong id="total-<?php echo $index; ?>">0</strong>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sliders = document.querySelectorAll('.score-slider');
    
    sliders.forEach(slider => {
        slider.addEventListener('input', function() {
            const index = this.dataset.index;
            
            // Get current values from all sliders
            const rk1Slider = document.querySelector(`[data-index="${index}"][data-type="rk1"]`);
            const rk2Slider = document.querySelector(`[data-index="${index}"][data-type="rk2"]`);
            const examSlider = document.querySelector(`[data-index="${index}"][data-type="exam"]`);
            
            const rk1 = parseFloat(rk1Slider.value);
            const rk2 = parseFloat(rk2Slider.value);
            const exam = parseFloat(examSlider.value);
            
            // Calculate total
            const total = (rk1 + rk2) * 0.3 + exam * 0.4;
            
            // Update displays
            document.getElementById(`rk1-value-${index}`).textContent = rk1.toFixed(1);
            document.getElementById(`rk2-value-${index}`).textContent = rk2.toFixed(1);
            document.getElementById(`exam-value-${index}`).textContent = exam.toFixed(1);
            document.getElementById(`total-${index}`).textContent = total.toFixed(1);
        });
        
        // Trigger initial calculation
        slider.dispatchEvent(new Event('input'));
    });
});
</script>

<?php include 'includes/footer.php'; ?>
