<?php
/**
 * Teacher - Assignments Management
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('teacher');

$pageTitle = 'งานและการบ้าน';
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

// 1. เพิ่มงานใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    verifyCsrfOrDie();

    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $max_score = (float)($_POST['max_score'] ?? 10.0);
    $due_date = trim($_POST['due_date'] ?? '');

    if ($subject_id <= 0 || empty($title) || empty($due_date)) {
        setFlash('error', 'กรุณากรอกข้อมูลงานและวันครบกำหนดให้ครบถ้วน');
    } else {
        // หา classroom_id ของวิชานี้
        $stmtSubClass = $pdo->prepare("SELECT classroom_id FROM subjects WHERE id = ?");
        $stmtSubClass->execute([$subject_id]);
        $classroom_id = (int)$stmtSubClass->fetchColumn();

        $attachmentFile = null;
        if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $up = uploadFile($_FILES['file'], 'assignments', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', 'jpg', 'png'], 15);
            if ($up['success']) {
                $attachmentFile = $up['filename'];
            }
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO assignments (subject_id, teacher_id, classroom_id, title, description, max_score, file_attachment, due_date, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$subject_id, $teacherId, $classroom_id, $title, $description, $max_score, $attachmentFile, $due_date]);
            
            // ส่งแจ้งเตือนให้นักเรียนทุกคนในห้องเรียน
            $stmtStudents = $pdo->prepare("SELECT user_id FROM students WHERE classroom_id = ?");
            $stmtStudents->execute([$classroom_id]);
            $studentsInClass = $stmtStudents->fetchAll();
            foreach ($studentsInClass as $std) {
                createNotification($pdo, (int)$std['user_id'], 'มีการบ้านใหม่', "อาจารย์ได้มอบหมายงาน \"{$title}\"", 'student/assignments.php');
            }

            setFlash('success', 'สร้างงาน/การบ้าน "' . $title . '" เรียบร้อยแล้ว');
        } catch (PDOException $e) {
            setFlash('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . 'teacher/assignments.php');
    exit;
}

// 2. ลบงาน
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (!validateCsrfToken($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'คำขอไม่ถูกต้องหรือหมดอายุ');
    } else {
        $id = (int)$_GET['id'];
        try {
            // ดึงไฟล์เพื่อลบ
            $stmtOld = $pdo->prepare("SELECT file_attachment FROM assignments WHERE id = ? AND teacher_id = ?");
            $stmtOld->execute([$id, $teacherId]);
            $old = $stmtOld->fetch();
            if ($old) {
                deleteUploadedFile('assignments', $old['file_attachment']);
                $stmt = $pdo->prepare("DELETE FROM assignments WHERE id = ? AND teacher_id = ?");
                $stmt->execute([$id, $teacherId]);
                setFlash('success', 'ลบงานเรียบร้อยแล้ว');
            }
        } catch (PDOException $e) {
            setFlash('error', 'ไม่สามารถลบงานได้: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . 'teacher/assignments.php');
    exit;
}

// ดึงรายการงานทั้งหมดของครู
$sql = "
    SELECT a.*, 
           s.subject_code, s.name_th as subject_name,
           c.name as classroom_name,
           (SELECT COUNT(*) FROM submissions sm WHERE sm.assignment_id = a.id) as submitted_count,
           (SELECT COUNT(*) FROM submissions sm WHERE sm.assignment_id = a.id AND sm.status = 'submitted') as pending_count,
           (SELECT COUNT(*) FROM students st WHERE st.classroom_id = a.classroom_id) as total_students
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    JOIN classrooms c ON a.classroom_id = c.id
    WHERE a.teacher_id = ?
    ORDER BY a.created_at DESC
";
$stmtAssign = $pdo->prepare($sql);
$stmtAssign->execute([$teacherId]);
$assignments = $stmtAssign->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="card">
    <div class="card-header flex-column flex-md-row gap-3 align-items-start align-items-md-center">
        <div>
            <h5 class="card-title mb-0"><i class="bi bi-clipboard-check-fill text-primary"></i> จัดการงานและการบ้าน (<?= count($assignments) ?> รายการ)</h5>
            <div class="small text-muted">มอบหมายงาน แนบเอกสาร กำหนดคะแนนเต็ม และตรวจการบ้านนักศึกษา</div>
        </div>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAssignmentModal">
                <i class="bi bi-plus-circle me-1"></i> มอบหมายงานใหม่
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>ชื่องาน / การบ้าน</th>
                    <th>รายวิชา</th>
                    <th>ห้องเรียน</th>
                    <th>คะแนนเต็ม</th>
                    <th>วันครบกำหนด</th>
                    <th>สถานะการส่ง</th>
                    <th class="text-end" style="width: 140px;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($assignments)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            ยังไม่มีรายการงานที่มอบหมาย
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($assignments as $idx => $a): 
                        $isOverdue = strtotime($a['due_date']) < time();
                    ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td>
                                <div class="fw-semibold text-dark">
                                    <a href="<?= BASE_URL ?>teacher/assignment-submissions.php?id=<?= $a['id'] ?>" class="text-dark hover-primary">
                                        <?= htmlspecialchars($a['title']) ?>
                                    </a>
                                </div>
                                <?php if (!empty($a['file_attachment'])): ?>
                                    <div class="small mt-1">
                                        <a href="<?= BASE_URL ?>assets/uploads/assignments/<?= $a['file_attachment'] ?>" target="_blank" class="text-primary">
                                            <i class="bi bi-paperclip"></i> ดาวน์โหลดเอกสารแนบ
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-dark"><?= htmlspecialchars($a['subject_code']) ?></span>
                                <div class="small text-muted mt-0.5"><?= htmlspecialchars($a['subject_name']) ?></div>
                            </td>
                            <td><span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($a['classroom_name']) ?></span></td>
                            <td class="fw-semibold text-primary"><?= number_format($a['max_score'], 0) ?> คะแนน</td>
                            <td>
                                <div class="small <?= $isOverdue ? 'text-danger fw-semibold' : 'text-dark' ?>">
                                    <?= formatThaiDate($a['due_date'], true, true) ?>
                                </div>
                                <?php if ($isOverdue): ?>
                                    <span class="badge bg-danger-subtle text-danger" style="font-size: 0.68rem;">ครบกำหนดแล้ว</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex flex-column gap-1">
                                    <span class="badge bg-light text-dark border">
                                        ส่งแล้ว <?= $a['submitted_count'] ?>/<?= $a['total_students'] ?> คน
                                    </span>
                                    <?php if ($a['pending_count'] > 0): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis">
                                            รอตรวจ <?= $a['pending_count'] ?> คน
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= BASE_URL ?>teacher/assignment-submissions.php?id=<?= $a['id'] ?>" class="btn btn-primary" title="ตรวจงาน">
                                        <i class="bi bi-check2-square"></i> ตรวจงาน
                                    </a>
                                    <button type="button" class="btn btn-outline-danger" 
                                            onclick="confirmDelete('<?= BASE_URL ?>teacher/assignments.php?action=delete&id=<?= $a['id'] ?>&csrf_token=<?= generateCsrfToken() ?>', 'ยืนยันลบงาน <?= addslashes($a['title']) ?>?')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Assignment -->
<div class="modal fade" id="addAssignmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="<?= BASE_URL ?>teacher/assignments.php" method="POST" enctype="multipart/form-data" class="modal-content">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="create">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary me-1"></i> มอบหมายงานใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label required">รายวิชา / ห้องเรียน</label>
                        <select name="subject_id" class="form-select" required>
                            <option value="">-- เลือกรายวิชา --</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?= $s['id'] ?>">
                                    <?= htmlspecialchars($s['subject_code']) ?> <?= htmlspecialchars($s['name_th']) ?> (<?= htmlspecialchars($s['classroom_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">คะแนนเต็ม</label>
                        <input type="number" step="0.5" min="1" max="100" name="max_score" class="form-control" value="10.0" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">วันครบกำหนดส่ง</label>
                        <input type="datetime-local" name="due_date" class="form-control" required value="<?= date('Y-m-d\T23:59', strtotime('+7 days')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label required">หัวข้องาน / ชื่องาน</label>
                        <input type="text" name="title" class="form-control" placeholder="เช่น ใบงานที่ 3: การเขียนโปรแกรมเชื่อมต่อฐานข้อมูล" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">คำอธิบายและข้อกำหนดในการส่งงาน</label>
                        <textarea name="description" class="form-control" rows="4" placeholder="ระบุสิ่งที่นักศึกษาต้องทำ รูปแบบไฟล์ที่ต้องการส่ง..."></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">เอกสารแนบ / โจทย์การบ้าน (PDF, DOCX, ZIP, รูปภาพ)</label>
                        <input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.zip,.rar,.png,.jpg">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">บันทึกและมอบหมายงาน</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
