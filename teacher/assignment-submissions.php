<?php
/**
 * Teacher - Assignment Submissions Review & Grading
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('teacher');

$assignmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$currentUser = getCurrentUser();
$teacherId = $currentUser['role_id'] ?? 0;

// ดึงข้อมูลงาน
$stmtAssign = $pdo->prepare("
    SELECT a.*, s.subject_code, s.name_th as subject_name, c.name as classroom_name
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    JOIN classrooms c ON a.classroom_id = c.id
    WHERE a.id = ? AND a.teacher_id = ?
");
$stmtAssign->execute([$assignmentId, $teacherId]);
$assignment = $stmtAssign->fetch();

if (!$assignment) {
    setFlash('error', 'ไม่พบข้อมูลงานที่ต้องการตรวจ');
    header('Location: ' . BASE_URL . 'teacher/assignments.php');
    exit;
}

$pageTitle = 'ตรวจงาน: ' . $assignment['title'];

// ประมวลผลการให้คะแนน (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grade_submission') {
    verifyCsrfOrDie();

    $submissionId = (int)$_POST['submission_id'];
    $studentId = (int)$_POST['student_id'];
    $score = (float)$_POST['score'];
    $feedback = trim($_POST['feedback'] ?? '');

    if ($score < 0 || $score > (float)$assignment['max_score']) {
        setFlash('error', 'คะแนนต้องอยู่ระหว่าง 0 ถึง ' . $assignment['max_score']);
    } else {
        try {
            if ($submissionId > 0) {
                // Update existing submission
                $stmtGrade = $pdo->prepare("
                    UPDATE submissions 
                    SET score = ?, feedback = ?, status = 'graded', graded_at = NOW() 
                    WHERE id = ?
                ");
                $stmtGrade->execute([$score, $feedback, $submissionId]);
            } else {
                // Insert new submission directly by teacher
                $stmtGrade = $pdo->prepare("
                    INSERT INTO submissions (assignment_id, student_id, score, feedback, status, graded_at, submitted_at) 
                    VALUES (?, ?, ?, ?, 'graded', NOW(), NOW())
                ");
                $stmtGrade->execute([$assignmentId, $studentId, $score, $feedback]);
            }

            // ส่งแจ้งเตือนให้นักศึกษา
            $stmtStdUser = $pdo->prepare("SELECT user_id FROM students WHERE id = ?");
            $stmtStdUser->execute([$studentId]);
            $stdUserId = (int)$stmtStdUser->fetchColumn();

            if ($stdUserId > 0) {
                createNotification(
                    $pdo, 
                    $stdUserId, 
                    'อาจารย์ได้ตรวจงานของคุณแล้ว', 
                    "งาน \"{$assignment['title']}\" ได้รับคะแนน {$score}/{$assignment['max_score']}", 
                    'student/assignments.php'
                );
            }

            setFlash('success', 'บันทึกคะแนนและส่งข้อเสนอแนะเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            setFlash('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . 'teacher/assignment-submissions.php?id=' . $assignmentId);
    exit;
}

// ดึงรายชื่อนักศึกษาในห้องทุกคน พร้อมสถานะการส่งงาน
$stmtRoster = $pdo->prepare("
    SELECT s.id as student_id, s.student_code, u.full_name, u.avatar,
           sm.id as submission_id, sm.submission_file, sm.note, sm.submitted_at, sm.score, sm.feedback, sm.graded_at, sm.status as submission_status
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN submissions sm ON sm.assignment_id = ? AND sm.student_id = s.id
    WHERE s.classroom_id = ? AND u.status = 'active'
    ORDER BY s.student_code ASC
");
$stmtRoster->execute([$assignmentId, $assignment['classroom_id']]);
$roster = $stmtRoster->fetchAll();

// คำนวณสรุป
$totalStudents = count($roster);
$submittedCount = 0;
$gradedCount = 0;
foreach ($roster as $r) {
    if (!empty($r['submission_id'])) {
        $submittedCount++;
        if ($r['submission_status'] === 'graded') {
            $gradedCount++;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<!-- Header Card: Assignment Info -->
<div class="card mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-3">
            <div>
                <a href="<?= BASE_URL ?>teacher/assignments.php" class="btn btn-sm btn-outline-secondary mb-2">
                    <i class="bi bi-arrow-left me-1"></i> กลับหน้ารายการงาน
                </a>
                <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($assignment['title']) ?></h4>
                <div class="small text-muted">
                    วิชา: <strong><?= htmlspecialchars($assignment['subject_code']) ?> <?= htmlspecialchars($assignment['subject_name']) ?></strong> 
                    &bull; ห้องเรียน: <strong><?= htmlspecialchars($assignment['classroom_name']) ?></strong>
                </div>
            </div>
            <div class="text-md-end">
                <div class="badge bg-primary fs-6 px-3 py-2 rounded-pill">
                    คะแนนเต็ม: <?= number_format($assignment['max_score'], 0) ?> คะแนน
                </div>
                <div class="small text-muted mt-1">
                    กำหนดส่ง: <?= formatThaiDate($assignment['due_date'], true, true) ?>
                </div>
            </div>
        </div>

        <?php if (!empty($assignment['description'])): ?>
            <div class="p-3 bg-light rounded-3 mb-3 small text-dark">
                <strong>คำอธิบาย/โจทย์:</strong><br>
                <?= nl2br(htmlspecialchars($assignment['description'])) ?>
            </div>
        <?php endif; ?>

        <!-- Stats Bar -->
        <div class="row g-2 text-center small border-top pt-3">
            <div class="col-4">
                <div class="p-2 bg-light rounded">
                    <span class="text-muted">นักศึกษาทั้งหมด:</span> <strong><?= $totalStudents ?> คน</strong>
                </div>
            </div>
            <div class="col-4">
                <div class="p-2 bg-info-subtle text-info-emphasis rounded">
                    <span>ส่งแล้ว:</span> <strong><?= $submittedCount ?> คน</strong>
                </div>
            </div>
            <div class="col-4">
                <div class="p-2 bg-success-subtle text-success rounded">
                    <span>ตรวจแล้ว:</span> <strong><?= $gradedCount ?> คน</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Submissions Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0"><i class="bi bi-person-lines-fill text-primary"></i> รายชื่อการส่งงานและการให้คะแนน</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>นักศึกษา</th>
                    <th>ไฟล์ที่ส่ง / หมายเหตุ</th>
                    <th>เวลาที่ส่งงาน</th>
                    <th>สถานะ</th>
                    <th>คะแนนที่ได้</th>
                    <th class="text-end" style="width: 120px;">ให้คะแนน</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($roster)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">ไม่พบนักศึกษาในกลุ่มเรียนนี้</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($roster as $idx => $r): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?= getUserAvatarUrl($r['avatar'], $r['full_name']) ?>" class="rounded-circle" width="32" height="32" alt="Avatar">
                                    <div>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($r['full_name']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($r['student_code']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($r['submission_file'])): ?>
                                    <div>
                                        <a href="<?= BASE_URL ?>assets/uploads/submissions/<?= $r['submission_file'] ?>" target="_blank" class="btn btn-sm btn-outline-primary py-0.5 px-2">
                                            <i class="bi bi-download me-1"></i> ดาวน์โหลดไฟล์ส่งงาน
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($r['note'])): ?>
                                    <div class="small text-muted mt-1 fst-italic">
                                        "<?= htmlspecialchars($r['note']) ?>"
                                    </div>
                                <?php endif; ?>
                                <?php if (empty($r['submission_file']) && empty($r['note'])): ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted">
                                <?= !empty($r['submitted_at']) ? formatThaiDate($r['submitted_at'], true, true) : '-' ?>
                            </td>
                            <td><?= getSubmissionBadge($r['submission_status'], $assignment['due_date']) ?></td>
                            <td class="fw-bold fs-6">
                                <?php if ($r['score'] !== null): ?>
                                    <span class="text-success"><?= number_format($r['score'], 1) ?></span> <span class="small text-muted">/ <?= number_format($assignment['max_score'], 0) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-primary grade-btn" 
                                        data-bs-toggle="modal" data-bs-target="#gradeModal"
                                        data-submission-id="<?= $r['submission_id'] ?? 0 ?>"
                                        data-student-id="<?= $r['student_id'] ?>"
                                        data-student-name="<?= htmlspecialchars($r['full_name']) ?>"
                                        data-current-score="<?= $r['score'] ?? '' ?>"
                                        data-feedback="<?= htmlspecialchars($r['feedback'] ?? '') ?>">
                                    <i class="bi bi-pencil-square me-1"></i> <?= $r['score'] !== null ? 'แก้คะแนน' : 'ตรวจงาน' ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Grade Submission -->
<div class="modal fade" id="gradeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="<?= BASE_URL ?>teacher/assignment-submissions.php?id=<?= $assignmentId ?>" method="POST" class="modal-content">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="grade_submission">
            <input type="hidden" name="submission_id" id="modal_sub_id">
            <input type="hidden" name="student_id" id="modal_student_id">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-award text-primary me-1"></i> ให้คะแนนและข้อเสนอแนะ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label text-muted small">นักศึกษา:</label>
                    <div class="fw-bold fs-6 text-dark" id="modal_student_name">-</div>
                </div>
                <div class="mb-3">
                    <label class="form-label required">คะแนนที่ได้ (เต็ม <?= number_format($assignment['max_score'], 0) ?> คะแนน)</label>
                    <input type="number" step="0.5" min="0" max="<?= (float)$assignment['max_score'] ?>" name="score" id="modal_score" class="form-control form-control-lg fw-bold text-primary" required>
                </div>
                <div class="mb-0">
                    <label class="form-label">ความคิดเห็น / ข้อเสนอแนะจากอาจารย์</label>
                    <textarea name="feedback" id="modal_feedback" class="form-control" rows="3" placeholder="ระบุคำแนะนำ หรือจุดที่ต้องปรับปรุง..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary px-4">บันทึกผลการตรวจ</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const gradeBtns = document.querySelectorAll('.grade-btn');
    gradeBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('modal_sub_id').value = this.getAttribute('data-submission-id');
            document.getElementById('modal_student_id').value = this.getAttribute('data-student-id');
            document.getElementById('modal_student_name').innerText = this.getAttribute('data-student-name');
            document.getElementById('modal_score').value = this.getAttribute('data-current-score');
            document.getElementById('modal_feedback').value = this.getAttribute('data-feedback');
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
