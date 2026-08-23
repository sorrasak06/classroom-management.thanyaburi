<?php
/**
 * Student - Assignment Detail & Submission
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('student');

$assignmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$currentUser = getCurrentUser();
$studentId = $currentUser['role_id'] ?? 0;
$classroomId = $currentUser['classroom_id'] ?? 0;

// ดึงข้อมูลงาน
$stmtAssign = $pdo->prepare("
    SELECT a.*, 
           s.subject_code, s.name_th as subject_name,
           t.user_id as teacher_user_id, u.full_name as teacher_name
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    JOIN teachers t ON a.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    WHERE a.id = ? AND a.classroom_id = ?
");
$stmtAssign->execute([$assignmentId, $classroomId]);
$assignment = $stmtAssign->fetch();

if (!$assignment) {
    setFlash('error', 'ไม่พบข้อมูลงานที่ระบุ');
    header('Location: ' . BASE_URL . 'student/assignments.php');
    exit;
}

$pageTitle = 'ส่งงาน: ' . $assignment['title'];

// ดึงข้อมูลการส่งงานของนักเรียนคนนี้ (ถ้ามี)
$stmtSub = $pdo->prepare("SELECT * FROM submissions WHERE assignment_id = ? AND student_id = ?");
$stmtSub->execute([$assignmentId, $studentId]);
$submission = $stmtSub->fetch();

// ตรวจสอบว่าเกินกำหนดส่งหรือไม่
$isOverdue = strtotime($assignment['due_date']) < time();

// ประมวลผลการส่งงาน / แก้ไขงาน (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_assignment') {
    verifyCsrfOrDie();

    $note = trim($_POST['note'] ?? '');
    $submissionFile = $submission['submission_file'] ?? null;

    // ตรวจสอบการอัปโหลดไฟล์
    if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
        $up = uploadFile($_FILES['file'], 'submissions', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', 'jpg', 'png', 'txt'], 20);
        if ($up['success']) {
            if (!empty($submission['submission_file'])) {
                deleteUploadedFile('submissions', $submission['submission_file']);
            }
            $submissionFile = $up['filename'];
        } else {
            setFlash('error', $up['error']);
            header("Location: " . BASE_URL . "student/assignment-detail.php?id=" . $assignmentId);
            exit;
        }
    }

    if (empty($submissionFile) && empty($note)) {
        setFlash('error', 'กรุณาแนบไฟล์งานหรือกรอกข้อความส่งงาน');
    } else {
        $status = $isOverdue ? 'late' : 'submitted';

        try {
            if ($submission) {
                // อัปเดตงานเดิม
                $stmtSave = $pdo->prepare("
                    UPDATE submissions 
                    SET submission_file = ?, note = ?, status = ?, submitted_at = NOW() 
                    WHERE id = ?
                ");
                $stmtSave->execute([$submissionFile, $note, $status, $submission['id']]);
            } else {
                // ส่งงานครั้งแรก
                $stmtSave = $pdo->prepare("
                    INSERT INTO submissions (assignment_id, student_id, submission_file, note, submitted_at, status) 
                    VALUES (?, ?, ?, ?, NOW(), ?)
                ");
                $stmtSave->execute([$assignmentId, $studentId, $submissionFile, $note, $status]);
            }

            // แจ้งเตือนครูผู้สอน
            createNotification(
                $pdo, 
                (int)$assignment['teacher_user_id'], 
                'นักเรียนส่งงานใหม่', 
                "{$currentUser['full_name']} ได้ส่งงาน \"{$assignment['title']}\"", 
                "teacher/assignment-submissions.php?id={$assignmentId}"
            );

            setFlash('success', 'ส่งงานเรียบร้อยแล้ว!');
            header("Location: " . BASE_URL . "student/assignment-detail.php?id=" . $assignmentId);
            exit;

        } catch (PDOException $e) {
            setFlash('error', 'เกิดข้อผิดพลาดในการส่งงาน: ' . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="mb-3">
    <a href="<?= BASE_URL ?>student/assignments.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> กลับหน้ารายการงาน
    </a>
</div>

<div class="row g-4">
    <!-- Left Column: Assignment Information -->
    <div class="col-lg-7">
        <div class="card mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge bg-primary-subtle text-primary fw-bold">
                        <?= htmlspecialchars($assignment['subject_code']) ?> <?= htmlspecialchars($assignment['subject_name']) ?>
                    </span>
                    <span class="badge bg-dark-subtle text-dark fs-6 px-3 py-1 rounded-pill">
                        คะแนนเต็ม: <?= number_format($assignment['max_score'], 0) ?> คะแนน
                    </span>
                </div>

                <h4 class="fw-bold text-dark mt-2 mb-3"><?= htmlspecialchars($assignment['title']) ?></h4>

                <div class="p-3 bg-light rounded-3 mb-4 small text-muted">
                    <div class="d-flex justify-content-between mb-1">
                        <span>อาจารย์ผู้สอน:</span>
                        <strong class="text-dark"><?= htmlspecialchars($assignment['teacher_name']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>วันที่มอบหมาย:</span>
                        <strong class="text-dark"><?= formatThaiDate($assignment['created_at'], true, true) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>กำหนดส่ง (Deadline):</span>
                        <strong class="<?= $isOverdue ? 'text-danger' : 'text-primary' ?>">
                            <?= formatThaiDate($assignment['due_date'], true, true) ?>
                        </strong>
                    </div>
                </div>

                <h6 class="fw-bold text-dark mb-2">รายละเอียดและข้อกำหนดของงาน:</h6>
                <div class="p-3 border rounded-3 bg-white mb-4 text-dark" style="line-height: 1.6;">
                    <?= !empty($assignment['description']) ? nl2br(htmlspecialchars($assignment['description'])) : '<span class="text-muted fst-italic">ไม่มีคำอธิบายเพิ่มเติม</span>' ?>
                </div>

                <?php if (!empty($assignment['file_attachment'])): ?>
                    <h6 class="fw-bold text-dark mb-2">เอกสารแนบจากอาจารย์:</h6>
                    <div class="p-3 bg-light rounded-3 border d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-file-earmark-arrow-down fs-3 text-primary"></i>
                            <div>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($assignment['file_attachment']) ?></div>
                                <small class="text-muted">ไฟล์โจทย์หรือเอกสารประกอบการทำ</small>
                            </div>
                        </div>
                        <a href="<?= BASE_URL ?>assets/uploads/assignments/<?= $assignment['file_attachment'] ?>" target="_blank" class="btn btn-sm btn-primary">
                            <i class="bi bi-download me-1"></i> ดาวน์โหลด
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Submission Form & Grading Result -->
    <div class="col-lg-5">
        <!-- If Graded: Show Score and Feedback -->
        <?php if ($submission && $submission['status'] === 'graded'): ?>
            <div class="card mb-4 border-success shadow-sm">
                <div class="card-header bg-success text-white py-3">
                    <h5 class="card-title mb-0 text-white"><i class="bi bi-award-fill me-1"></i> ผลการตรวจงาน</h5>
                </div>
                <div class="card-body p-4 text-center">
                    <div class="display-5 fw-bold text-success mb-1">
                        <?= number_format($submission['score'], 1) ?>
                    </div>
                    <div class="small text-muted mb-3">จากคะแนนเต็ม <?= number_format($assignment['max_score'], 0) ?> คะแนน</div>

                    <?php if (!empty($submission['feedback'])): ?>
                        <div class="p-3 bg-success-subtle text-dark rounded-3 text-start small border border-success-subtle mb-3">
                            <strong class="text-success d-block mb-1"><i class="bi bi-chat-left-quote-fill me-1"></i> ความเห็นจากอาจารย์:</strong>
                            <?= nl2br(htmlspecialchars($submission['feedback'])) ?>
                        </div>
                    <?php endif; ?>

                    <div class="small text-muted">
                        ตรวจเมื่อ: <?= formatThaiDate($submission['graded_at'], true, true) ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Submission Box -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-upload text-primary"></i> 
                    <?= $submission ? 'ข้อมูลการส่งงานของคุณ' : 'ส่งงาน / แนบไฟล์' ?>
                </h5>
            </div>
            <div class="card-body p-4">
                <?php if ($submission): ?>
                    <div class="alert alert-info py-2.5 px-3 mb-3 small d-flex align-items-center">
                        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                        <div>
                            คุณได้ส่งงานนี้แล้วเมื่อ: <strong><?= formatThaiDate($submission['submitted_at'], true, true) ?></strong>
                            <div class="mt-0.5">สถานะ: <?= getSubmissionBadge($submission['status'], $assignment['due_date']) ?></div>
                        </div>
                    </div>

                    <?php if (!empty($submission['submission_file'])): ?>
                        <div class="p-2.5 bg-light rounded-3 border mb-3 d-flex justify-content-between align-items-center">
                            <div class="small text-truncate me-2">
                                <i class="bi bi-file-earmark-check text-success me-1"></i>
                                <?= htmlspecialchars($submission['submission_file']) ?>
                            </div>
                            <a href="<?= BASE_URL ?>assets/uploads/submissions/<?= $submission['submission_file'] ?>" target="_blank" class="btn btn-sm btn-outline-primary py-0 px-2" title="ดาวน์โหลด">
                                <i class="bi bi-download"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($submission && $submission['status'] === 'graded'): ?>
                    <div class="alert alert-secondary py-2 text-center small mb-0">
                        <i class="bi bi-lock-fill me-1"></i> งานนี้ได้รับการตรวจและบันทึกคะแนนแล้ว ไม่สามารถแก้ไขได้
                    </div>
                <?php else: ?>
                    <form action="<?= BASE_URL ?>student/assignment-detail.php?id=<?= $assignmentId ?>" method="POST" enctype="multipart/form-data">
                        <?= getCsrfField() ?>
                        <input type="hidden" name="action" value="submit_assignment">

                        <div class="mb-3">
                            <label class="form-label required">อัปโหลดไฟล์งาน (PDF, Word, ZIP, RAR, รูปภาพ)</label>
                            <input type="file" name="file" class="form-control" <?= empty($submission) ? 'required' : '' ?> accept=".pdf,.doc,.docx,.xls,.xlsx,.zip,.rar,.png,.jpg,.txt">
                            <div class="form-text small text-muted">ขนาดไฟล์สูงสุดไม่เกิน 20 MB</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">หมายเหตุหรือข้อความถึงอาจารย์</label>
                            <textarea name="note" class="form-control" rows="3" placeholder="ระบุรายละเอียดเพิ่มเติม หรือลิงก์โครงงาน (ถ้ามี)..."><?= htmlspecialchars($submission['note'] ?? '') ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2.5">
                            <i class="bi bi-send-check me-1"></i> <?= $submission ? 'บันทึกการแก้ไขการส่งงาน' : 'ยืนยันการส่งงาน' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
