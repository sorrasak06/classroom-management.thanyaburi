<?php
/**
 * Admin - Subject Management
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$pageTitle = 'จัดการรายวิชา';

// ดึงรายชื่อครูและห้องเรียนสำหรับ Dropdown
$teachers = $pdo->query("
    SELECT t.id, t.teacher_code, u.full_name 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    ORDER BY u.full_name ASC
")->fetchAll();

$classrooms = $pdo->query("SELECT id, name, level FROM classrooms ORDER BY level ASC, name ASC")->fetchAll();

// 1. เพิ่มรายวิชา
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    verifyCsrfOrDie();

    $code = trim($_POST['subject_code'] ?? '');
    $name_th = trim($_POST['name_th'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $credits = (float)($_POST['credits'] ?? 3.0);
    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    $classroom_id = (int)($_POST['classroom_id'] ?? 0);
    $term = trim($_POST['term'] ?? '1');
    $academic_year = trim($_POST['academic_year'] ?? '2567');
    $description = trim($_POST['description'] ?? '');

    if (empty($code) || empty($name_th) || $teacher_id <= 0 || $classroom_id <= 0) {
        setFlash('error', 'กรุณากรอกรหัสวิชา, ชื่อวิชาภาษาไทย, ครูผู้สอน และห้องเรียนให้ครบถ้วน');
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO subjects (subject_code, name_th, name_en, credits, description, teacher_id, classroom_id, term, academic_year, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$code, $name_th, $name_en, $credits, $description, $teacher_id, $classroom_id, $term, $academic_year]);
            setFlash('success', 'เพิ่มรายวิชา "' . $name_th . '" เรียบร้อยแล้ว');
        } catch (PDOException $e) {
            setFlash('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . 'admin/subjects.php');
    exit;
}

// 2. แก้ไขรายวิชา
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    verifyCsrfOrDie();

    $id = (int)$_POST['id'];
    $code = trim($_POST['subject_code'] ?? '');
    $name_th = trim($_POST['name_th'] ?? '');
    $name_en = trim($_POST['name_en'] ?? '');
    $credits = (float)($_POST['credits'] ?? 3.0);
    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    $classroom_id = (int)($_POST['classroom_id'] ?? 0);
    $term = trim($_POST['term'] ?? '1');
    $academic_year = trim($_POST['academic_year'] ?? '2567');
    $description = trim($_POST['description'] ?? '');

    if (empty($code) || empty($name_th) || $teacher_id <= 0 || $classroom_id <= 0) {
        setFlash('error', 'กรุณากรอกข้อมูลให้ครบถ้วน');
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE subjects 
                SET subject_code = ?, name_th = ?, name_en = ?, credits = ?, description = ?, teacher_id = ?, classroom_id = ?, term = ?, academic_year = ? 
                WHERE id = ?
            ");
            $stmt->execute([$code, $name_th, $name_en, $credits, $description, $teacher_id, $classroom_id, $term, $academic_year, $id]);
            setFlash('success', 'แก้ไขข้อมูลรายวิชาเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            setFlash('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . 'admin/subjects.php');
    exit;
}

// 3. ลบรายวิชา
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (!validateCsrfToken($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'คำขอไม่ถูกต้องหรือหมดอายุ');
    } else {
        $id = (int)$_GET['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('success', 'ลบรายวิชาเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            setFlash('error', 'ไม่สามารถลบรายวิชาได้: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . 'admin/subjects.php');
    exit;
}

// ดึงรายการวิชาทั้งหมด
$sql = "
    SELECT s.*, 
           u.full_name as teacher_name, t.teacher_code,
           c.name as classroom_name, c.level
    FROM subjects s
    JOIN teachers t ON s.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    JOIN classrooms c ON s.classroom_id = c.id
    ORDER BY s.academic_year DESC, s.term ASC, s.subject_code ASC
";
$subjects = $pdo->query($sql)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="card">
    <div class="card-header flex-column flex-md-row gap-3 align-items-start align-items-md-center">
        <div>
            <h5 class="card-title mb-0"><i class="bi bi-journal-bookmark-fill text-primary"></i> รายการรายวิชาที่เปิดสอน (<?= count($subjects) ?> วิชา)</h5>
            <div class="small text-muted">จัดการหลักสูตร มอบหมายอาจารย์ผู้สอน และห้องเรียนประจำวิชา</div>
        </div>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                <i class="bi bi-plus-circle me-1"></i> เพิ่มรายวิชาใหม่
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>รหัสวิชา</th>
                    <th>ชื่อรายวิชา</th>
                    <th>หน่วยกิต</th>
                    <th>อาจารย์ผู้สอน</th>
                    <th>ห้องเรียน</th>
                    <th>ภาคเรียน/ปี</th>
                    <th class="text-end" style="width: 120px;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($subjects)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-journal-x fs-1 d-block mb-2"></i>
                            ยังไม่มีข้อมูลรายวิชาในระบบ
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($subjects as $idx => $sub): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td>
                                <span class="badge bg-dark-subtle text-dark fw-bold"><?= htmlspecialchars($sub['subject_code']) ?></span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($sub['name_th']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($sub['name_en'] ?? '-') ?></div>
                            </td>
                            <td>
                                <span class="badge bg-info-subtle text-info-emphasis"><?= number_format($sub['credits'], 1) ?> นก.</span>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-1.5">
                                    <i class="bi bi-person text-muted"></i>
                                    <span><?= htmlspecialchars($sub['teacher_name']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($sub['classroom_name']) ?></span>
                            </td>
                            <td class="small text-muted">เทอม <?= htmlspecialchars($sub['term']) ?>/<?= htmlspecialchars($sub['academic_year']) ?></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary edit-sub-btn" 
                                            data-bs-toggle="modal" data-bs-target="#editSubjectModal"
                                            data-id="<?= $sub['id'] ?>"
                                            data-code="<?= htmlspecialchars($sub['subject_code']) ?>"
                                            data-nameth="<?= htmlspecialchars($sub['name_th']) ?>"
                                            data-nameen="<?= htmlspecialchars($sub['name_en'] ?? '') ?>"
                                            data-credits="<?= $sub['credits'] ?>"
                                            data-teacher="<?= $sub['teacher_id'] ?>"
                                            data-classroom="<?= $sub['classroom_id'] ?>"
                                            data-term="<?= htmlspecialchars($sub['term']) ?>"
                                            data-year="<?= htmlspecialchars($sub['academic_year']) ?>"
                                            data-desc="<?= htmlspecialchars($sub['description'] ?? '') ?>">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" 
                                            onclick="confirmDelete('<?= BASE_URL ?>admin/subjects.php?action=delete&id=<?= $sub['id'] ?>&csrf_token=<?= generateCsrfToken() ?>', 'ยืนยันลบวิชา <?= addslashes($sub['name_th']) ?>?')">
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

<!-- Modal: Add Subject -->
<div class="modal fade" id="addSubjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="<?= BASE_URL ?>admin/subjects.php" method="POST" class="modal-content">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="create">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary me-1"></i> เพิ่มรายวิชาใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label required">รหัสวิชา</label>
                        <input type="text" name="subject_code" class="form-control" placeholder="เช่น 30901-2001" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label required">ชื่อรายวิชา (ภาษาไทย)</label>
                        <input type="text" name="name_th" class="form-control" placeholder="เช่น การพัฒนาโปรแกรมบนเว็บ" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">ชื่อรายวิชา (English)</label>
                        <input type="text" name="name_en" class="form-control" placeholder="เช่น Web Programming Development">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">จำนวนหน่วยกิต</label>
                        <input type="number" step="0.5" min="1" max="6" name="credits" class="form-control" value="3.0" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">อาจารย์ผู้สอน</label>
                        <select name="teacher_id" class="form-select" required>
                            <option value="">-- เลือกอาจารย์ผู้สอน --</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?> (<?= htmlspecialchars($t['teacher_code']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">ห้องเรียนที่เปิดสอน</label>
                        <select name="classroom_id" class="form-select" required>
                            <option value="">-- เลือกห้องเรียน --</option>
                            <?php foreach ($classrooms as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">ภาคเรียน</label>
                        <select name="term" class="form-select" required>
                            <option value="1" selected>ภาคเรียนที่ 1</option>
                            <option value="2">ภาคเรียนที่ 2</option>
                            <option value="ฤดูร้อน">ภาคฤดูร้อน</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">ปีการศึกษา</label>
                        <input type="text" name="academic_year" class="form-control" value="2567" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">คำอธิบายรายวิชา (Course Description)</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="ระบุจุดประสงค์รายวิชา สมรรถนะรายวิชา และคำอธิบายรายวิชา..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">บันทึกรายวิชา</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Subject -->
<div class="modal fade" id="editSubjectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="<?= BASE_URL ?>admin/subjects.php" method="POST" class="modal-content">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_sub_id">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-warning me-1"></i> แก้ไขข้อมูลรายวิชา</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label required">รหัสวิชา</label>
                        <input type="text" name="subject_code" id="edit_sub_code" class="form-control" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label required">ชื่อรายวิชา (ภาษาไทย)</label>
                        <input type="text" name="name_th" id="edit_sub_nameth" class="form-control" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">ชื่อรายวิชา (English)</label>
                        <input type="text" name="name_en" id="edit_sub_nameen" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label required">จำนวนหน่วยกิต</label>
                        <input type="number" step="0.5" min="1" max="6" name="credits" id="edit_sub_credits" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">อาจารย์ผู้สอน</label>
                        <select name="teacher_id" id="edit_sub_teacher" class="form-select" required>
                            <option value="">-- เลือกอาจารย์ผู้สอน --</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">ห้องเรียนที่เปิดสอน</label>
                        <select name="classroom_id" id="edit_sub_classroom" class="form-select" required>
                            <option value="">-- เลือกห้องเรียน --</option>
                            <?php foreach ($classrooms as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">ภาคเรียน</label>
                        <select name="term" id="edit_sub_term" class="form-select" required>
                            <option value="1">ภาคเรียนที่ 1</option>
                            <option value="2">ภาคเรียนที่ 2</option>
                            <option value="ฤดูร้อน">ภาคฤดูร้อน</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label required">ปีการศึกษา</label>
                        <input type="text" name="academic_year" id="edit_sub_year" class="form-control" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">คำอธิบายรายวิชา (Course Description)</label>
                        <textarea name="description" id="edit_sub_desc" class="form-control" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const editBtns = document.querySelectorAll('.edit-sub-btn');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_sub_id').value = this.getAttribute('data-id');
            document.getElementById('edit_sub_code').value = this.getAttribute('data-code');
            document.getElementById('edit_sub_nameth').value = this.getAttribute('data-nameth');
            document.getElementById('edit_sub_nameen').value = this.getAttribute('data-nameen');
            document.getElementById('edit_sub_credits').value = this.getAttribute('data-credits');
            document.getElementById('edit_sub_teacher').value = this.getAttribute('data-teacher');
            document.getElementById('edit_sub_classroom').value = this.getAttribute('data-classroom');
            document.getElementById('edit_sub_term').value = this.getAttribute('data-term');
            document.getElementById('edit_sub_year').value = this.getAttribute('data-year');
            document.getElementById('edit_sub_desc').value = this.getAttribute('data-desc');
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
