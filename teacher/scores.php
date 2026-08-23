<?php
/**
 * Teacher - Gradebook & Scores Management
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('teacher');

$pageTitle = 'บันทึกคะแนนและตัดเกรด';
$currentUser = getCurrentUser();
$teacherId = $currentUser['role_id'] ?? 0;

// ดึงวิชาที่ครูสอน
$stmtSubs = $pdo->prepare("
    SELECT s.*, c.name as classroom_name 
    FROM subjects s 
    JOIN classrooms c ON s.classroom_id = c.id 
    WHERE s.teacher_id = ? 
    ORDER BY s.subject_code ASC
");
$stmtSubs->execute([$teacherId]);
$subjects = $stmtSubs->fetchAll();

$selectedSubjectId = !empty($_GET['subject_id']) ? (int)$_GET['subject_id'] : ($subjects[0]['id'] ?? 0);

$currentSubject = null;
foreach ($subjects as $s) {
    if ($s['id'] === $selectedSubjectId) {
        $currentSubject = $s;
        break;
    }
}

// ประมวลผลบันทึกคะแนน (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_scores') {
    verifyCsrfOrDie();

    $postSubjectId = (int)$_POST['subject_id'];
    $term = trim($_POST['term'] ?? '1');
    $academic_year = trim($_POST['academic_year'] ?? '2567');
    $assignmentScores = $_POST['assignment_score'] ?? [];
    $midtermScores = $_POST['midterm_score'] ?? [];
    $finalScores = $_POST['final_score'] ?? [];

    try {
        $pdo->beginTransaction();

        $stmtSaveScore = $pdo->prepare("
            INSERT INTO scores (student_id, subject_id, term, academic_year, assignment_score, midterm_score, final_score, total_score, grade, recorded_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                assignment_score = VALUES(assignment_score),
                midterm_score = VALUES(midterm_score),
                final_score = VALUES(final_score),
                total_score = VALUES(total_score),
                grade = VALUES(grade),
                recorded_by = VALUES(recorded_by),
                updated_at = NOW()
        ");

        $countSaved = 0;
        foreach ($assignmentScores as $studentId => $assignScore) {
            $studentId = (int)$studentId;
            $aScore = (float)$assignScore;
            $mScore = (float)($midtermScores[$studentId] ?? 0);
            $fScore = (float)($finalScores[$studentId] ?? 0);
            $totalScore = $aScore + $mScore + $fScore;
            $grade = calcGrade($totalScore);

            $stmtSaveScore->execute([
                $studentId,
                $postSubjectId,
                $term,
                $academic_year,
                $aScore,
                $mScore,
                $fScore,
                $totalScore,
                $grade,
                $currentUser['id']
            ]);
            $countSaved++;
        }

        $pdo->commit();
        setFlash('success', "บันทึกผลคะแนนและตัดเกรดเรียบร้อยแล้ว (จำนวน {$countSaved} คน)");
        header("Location: " . BASE_URL . "teacher/scores.php?subject_id={$postSubjectId}");
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        setFlash('error', 'เกิดข้อผิดพลาดในการบันทึกคะแนน: ' . $e->getMessage());
    }
}

// ดึงรายชื่อนักเรียนพร้อมคะแนน
$students = [];
if ($currentSubject) {
    $stmtStudents = $pdo->prepare("
        SELECT s.id as student_id, s.student_code, u.full_name, u.avatar,
               sc.assignment_score, sc.midterm_score, sc.final_score, sc.total_score, sc.grade
        FROM students s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN scores sc ON sc.student_id = s.id 
             AND sc.subject_id = ? 
             AND sc.term = ? 
             AND sc.academic_year = ?
        WHERE s.classroom_id = ? AND u.status = 'active'
        ORDER BY s.student_code ASC
    ");
    $stmtStudents->execute([
        $selectedSubjectId, 
        $currentSubject['term'], 
        $currentSubject['academic_year'], 
        $currentSubject['classroom_id']
    ]);
    $students = $stmtStudents->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="card">
    <div class="card-header flex-column flex-md-row gap-3 align-items-start align-items-md-center">
        <div>
            <h5 class="card-title mb-0"><i class="bi bi-award-fill text-primary"></i> ระบบบันทึกคะแนนและตัดเกรด (Gradebook)</h5>
            <div class="small text-muted">บันทึกคะแนนเก็บ กลางภาค ปลายภาค รวม 100 คะแนน พร้อมตัดเกรด A - F อัตโนมัติ</div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-success" onclick="exportTableToCSV('scoresTable', 'scores_<?= date('Ymd') ?>.csv')">
                <i class="bi bi-file-earmark-excel me-1"></i> ส่งออก Excel
            </button>
            <button type="button" class="btn btn-sm btn-dark" onclick="printReport()">
                <i class="bi bi-printer me-1"></i> พิมพ์
            </button>
        </div>
    </div>

    <!-- Subject Selector -->
    <div class="card-body bg-light bg-opacity-50 border-bottom py-3 no-print">
        <form action="<?= BASE_URL ?>teacher/scores.php" method="GET" class="row g-2 align-items-end">
            <div class="col-md-6 col-12">
                <label class="form-label small mb-1">เลือกรายวิชาและห้องเรียน</label>
                <select name="subject_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $selectedSubjectId === $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['subject_code']) ?> <?= htmlspecialchars($s['name_th']) ?> (<?= htmlspecialchars($s['classroom_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-6">
                <label class="form-label small mb-1">ภาคเรียน / ปีการศึกษา</label>
                <input type="text" class="form-control bg-white" readonly value="ภาคเรียนที่ <?= htmlspecialchars($currentSubject['term'] ?? '1') ?>/<?= htmlspecialchars($currentSubject['academic_year'] ?? '2567') ?>">
            </div>
            <div class="col-md-3 col-6">
                <button type="submit" class="btn btn-dark w-100">โหลดข้อมูล</button>
            </div>
        </form>
    </div>

    <?php if (!$currentSubject): ?>
        <div class="card-body py-5 text-center text-muted">
            <i class="bi bi-journal-x fs-1 d-block mb-2"></i>
            กรุณาเลือกรายวิชาเพื่อบันทึกคะแนน
        </div>
    <?php else: ?>
        <form action="<?= BASE_URL ?>teacher/scores.php" method="POST" id="scoreForm">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="save_scores">
            <input type="hidden" name="subject_id" value="<?= $currentSubject['id'] ?>">
            <input type="hidden" name="term" value="<?= htmlspecialchars($currentSubject['term']) ?>">
            <input type="hidden" name="academic_year" value="<?= htmlspecialchars($currentSubject['academic_year']) ?>">

            <!-- Grading Scale Alert -->
            <div class="card-body py-2.5 bg-light border-bottom small text-muted d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    เกณฑ์ตัดเกรด: <strong>80-100 (A)</strong> | <strong>75-79 (B+)</strong> | <strong>70-74 (B)</strong> | <strong>65-69 (C+)</strong> | <strong>60-64 (C)</strong> | <strong>55-59 (D+)</strong> | <strong>50-54 (D)</strong> | <strong>0-49 (F)</strong>
                </div>
            </div>

            <!-- Scores Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0" id="scoresTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>รหัสนักศึกษา</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th style="width: 140px;" class="text-center">งาน/เก็บ (40)</th>
                            <th style="width: 140px;" class="text-center">กลางภาค (30)</th>
                            <th style="width: 140px;" class="text-center">ปลายภาค (30)</th>
                            <th style="width: 130px;" class="text-center bg-primary-subtle text-primary fw-bold">รวม (100)</th>
                            <th style="width: 100px;" class="text-center bg-dark text-white fw-bold">เกรด</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">ไม่พบนักศึกษาในกลุ่มเรียนนี้</td></tr>
                        <?php else: ?>
                            <?php foreach ($students as $idx => $std): 
                                $aScore = $std['assignment_score'] !== null ? (float)$std['assignment_score'] : 0.0;
                                $mScore = $std['midterm_score'] !== null ? (float)$std['midterm_score'] : 0.0;
                                $fScore = $std['final_score'] !== null ? (float)$std['final_score'] : 0.0;
                                $tScore = $aScore + $mScore + $fScore;
                                $grade = !empty($std['grade']) ? $std['grade'] : calcGrade($tScore);
                            ?>
                                <tr class="student-score-row" data-id="<?= $std['student_id'] ?>">
                                    <td><?= $idx + 1 ?></td>
                                    <td><span class="badge bg-secondary-subtle text-dark fw-bold"><?= htmlspecialchars($std['student_code']) ?></span></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="<?= getUserAvatarUrl($std['avatar'], $std['full_name']) ?>" class="rounded-circle" width="30" height="30" alt="Avatar">
                                            <span class="fw-semibold text-dark"><?= htmlspecialchars($std['full_name']) ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" step="0.5" min="0" max="40" name="assignment_score[<?= $std['student_id'] ?>]" class="form-control form-control-sm text-center input-score input-assign" value="<?= $aScore ?>">
                                    </td>
                                    <td>
                                        <input type="number" step="0.5" min="0" max="30" name="midterm_score[<?= $std['student_id'] ?>]" class="form-control form-control-sm text-center input-score input-mid" value="<?= $mScore ?>">
                                    </td>
                                    <td>
                                        <input type="number" step="0.5" min="0" max="30" name="final_score[<?= $std['student_id'] ?>]" class="form-control form-control-sm text-center input-score input-final" value="<?= $fScore ?>">
                                    </td>
                                    <td class="text-center fw-bold fs-6 text-primary bg-primary-subtle cell-total">
                                        <?= number_format($tScore, 1) ?>
                                    </td>
                                    <td class="text-center fw-bold fs-6 cell-grade">
                                        <span class="badge bg-dark px-2.5 py-1"><?= $grade ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($students)): ?>
                <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center no-print">
                    <div class="small text-muted">จำนวนนักศึกษาทั้งหมด <?= count($students) ?> คน</div>
                    <button type="submit" class="btn btn-primary px-4 py-2">
                        <i class="bi bi-save2 me-1"></i> บันทึกผลคะแนนและตัดเกรดทั้งหมด
                    </button>
                </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

<script>
// Live Grade & Total Score Calculation
document.addEventListener('DOMContentLoaded', function() {
    function calculateGrade(score) {
        if (score >= 80) return 'A';
        if (score >= 75) return 'B+';
        if (score >= 70) return 'B';
        if (score >= 65) return 'C+';
        if (score >= 60) return 'C';
        if (score >= 55) return 'D+';
        if (score >= 50) return 'D';
        return 'F';
    }

    const rows = document.querySelectorAll('.student-score-row');
    rows.forEach(row => {
        const assignInput = row.querySelector('.input-assign');
        const midInput = row.querySelector('.input-mid');
        const finalInput = row.querySelector('.input-final');
        const totalCell = row.querySelector('.cell-total');
        const gradeCell = row.querySelector('.cell-grade');

        function updateRow() {
            const a = parseFloat(assignInput.value) || 0;
            const m = parseFloat(midInput.value) || 0;
            const f = parseFloat(finalInput.value) || 0;
            const total = a + m + f;
            const grade = calculateGrade(total);

            totalCell.innerText = total.toFixed(1);
            gradeCell.innerHTML = `<span class="badge bg-dark px-2.5 py-1">${grade}</span>`;
        }

        assignInput.addEventListener('input', updateRow);
        midInput.addEventListener('input', updateRow);
        finalInput.addEventListener('input', updateRow);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
