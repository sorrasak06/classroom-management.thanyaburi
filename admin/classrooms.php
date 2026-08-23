<?php
/**
 * Admin - Classroom Management
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$pageTitle = 'จัดการห้องเรียน';

// ดึงครูทั้งหมดสำหรับเลือกเป็นครูที่ปรึกษา
$teachers = $pdo->query("
    SELECT t.id, t.teacher_code, u.full_name 
    FROM teachers t 
    JOIN users u ON t.user_id = u.id 
    ORDER BY u.full_name ASC
")->fetchAll();

// 1. จัดการการเพิ่มห้องเรียน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    verifyCsrfOrDie();

    $name = trim($_POST['name'] ?? '');
    $level = trim($_POST['level'] ?? '');
    $academic_year = trim($_POST['academic_year'] ?? '2567');
    $homeroom_teacher_id = !empty($_POST['homeroom_teacher_id']) ? (int)$_POST['homeroom_teacher_id'] : null;
    $description = trim($_POST['description'] ?? '');

    if (empty($name) || empty($level)) {
        setFlash('error', 'กรุณาระบุชื่อห้องเรียนและระดับชั้น');
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO classrooms (name, level, academic_year, homeroom_teacher_id, description, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $level, $academic_year, $homeroom_teacher_id, $description]);
            setFlash('success', 'เพิ่มห้องเรียน "' . $name . '" เรียบร้อยแล้ว');
        } catch (PDOException $e) {
            setFlash('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . 'admin/classrooms.php');
    exit;
}

// 2. จัดการการแก้ไขห้องเรียน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    verifyCsrfOrDie();

    $id = (int)$_POST['id'];
    $name = trim($_POST['name'] ?? '');
    $level = trim($_POST['level'] ?? '');
    $academic_year = trim($_POST['academic_year'] ?? '2567');
    $homeroom_teacher_id = !empty($_POST['homeroom_teacher_id']) ? (int)$_POST['homeroom_teacher_id'] : null;
    $description = trim($_POST['description'] ?? '');

    if (empty($name) || empty($level)) {
        setFlash('error', 'กรุณาระบุชื่อห้องเรียนและระดับชั้น');
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE classrooms 
                SET name = ?, level = ?, academic_year = ?, homeroom_teacher_id = ?, description = ? 
                WHERE id = ?
            ");
            $stmt->execute([$name, $level, $academic_year, $homeroom_teacher_id, $description, $id]);
            setFlash('success', 'แก้ไขข้อมูลห้องเรียนเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            setFlash('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . 'admin/classrooms.php');
    exit;
}

// 3. จัดการการลบห้องเรียน
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (!validateCsrfToken($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'คำขอไม่ถูกต้องหรือหมดอายุ');
    } else {
        $id = (int)$_GET['id'];
        try {
            // ตรวจสอบว่ามีนักเรียนอยู่ในห้องนี้หรือไม่
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM students WHERE classroom_id = ?");
            $stmtCount->execute([$id]);
            if ($stmtCount->fetchColumn() > 0) {
                setFlash('error', 'ไม่สามารถลบห้องเรียนนี้ได้ เนื่องจากยังมีนักศึกษาอยู่ในห้องเรียนนี้ กรุณาย้ายนักศึกษาก่อน');
            } else {
                $stmt = $pdo->prepare("DELETE FROM classrooms WHERE id = ?");
                $stmt->execute([$id]);
                setFlash('success', 'ลบห้องเรียนเรียบร้อยแล้ว');
            }
        } catch (PDOException $e) {
            setFlash('error', 'ไม่สามารถลบห้องเรียนได้: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . 'admin/classrooms.php');
    exit;
}

// ดึงรายการห้องเรียนทั้งหมดพร้อมจำนวนนักเรียน
$sql = "
    SELECT c.*, 
           u.full_name as teacher_name, t.teacher_code,
           COUNT(s.id) as student_count
    FROM classrooms c
    LEFT JOIN teachers t ON c.homeroom_teacher_id = t.id
    LEFT JOIN users u ON t.user_id = u.id
    LEFT JOIN students s ON c.id = s.classroom_id
    GROUP BY c.id
    ORDER BY c.level ASC, c.name ASC
";
$classrooms = $pdo->query($sql)->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="card">
    <div class="card-header flex-column flex-md-row gap-3 align-items-start align-items-md-center">
        <div>
            <h5 class="card-title mb-0"><i class="bi bi-door-open-fill text-primary"></i> รายการห้องเรียน (<?= count($classrooms) ?> ห้อง)</h5>
            <div class="small text-muted">จัดการกลุ่มเรียน ระดับชั้น และครูที่ปรึกษาประจำห้อง</div>
        </div>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClassModal">
                <i class="bi bi-plus-circle me-1"></i> เพิ่มห้องเรียนใหม่
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>ชื่อห้องเรียน / กลุ่ม</th>
                    <th>ระดับชั้น</th>
                    <th>ปีการศึกษา</th>
                    <th>ครูที่ปรึกษา</th>
                    <th>จำนวนนักศึกษา</th>
                    <th>คำอธิบาย</th>
                    <th class="text-end" style="width: 130px;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($classrooms)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-door-closed fs-1 d-block mb-2"></i>
                            ยังไม่มีข้อมูลห้องเรียนในระบบ
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($classrooms as $idx => $c): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td>
                                <span class="fw-semibold text-dark"><?= htmlspecialchars($c['name']) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><?= htmlspecialchars($c['level']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($c['academic_year']) ?></td>
                            <td>
                                <?php if (!empty($c['teacher_name'])): ?>
                                    <div class="d-flex align-items-center gap-1.5">
                                        <i class="bi bi-person-badge text-muted"></i>
                                        <span><?= htmlspecialchars($c['teacher_name']) ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted small">ยังไม่ได้ระบุ</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-dark fs-7 px-2 py-1">
                                    <i class="bi bi-people me-1"></i> <?= $c['student_count'] ?> คน
                                </span>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars($c['description'] ?? '-') ?></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary edit-classroom-btn" 
                                            data-bs-toggle="modal" data-bs-target="#editClassModal"
                                            data-id="<?= $c['id'] ?>"
                                            data-name="<?= htmlspecialchars($c['name']) ?>"
                                            data-level="<?= htmlspecialchars($c['level']) ?>"
                                            data-year="<?= htmlspecialchars($c['academic_year']) ?>"
                                            data-teacher="<?= $c['homeroom_teacher_id'] ?? '' ?>"
                                            data-desc="<?= htmlspecialchars($c['description'] ?? '') ?>">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" 
                                            onclick="confirmDelete('<?= BASE_URL ?>admin/classrooms.php?action=delete&id=<?= $c['id'] ?>&csrf_token=<?= generateCsrfToken() ?>', 'ยืนยันลบห้องเรียน <?= addslashes($c['name']) ?>?')">
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

<!-- Modal: Add Classroom -->
<div class="modal fade" id="addClassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="<?= BASE_URL ?>admin/classrooms.php" method="POST" class="modal-content">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="create">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary me-1"></i> เพิ่มห้องเรียนใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label required">ชื่อห้องเรียน / กลุ่มเรียน</label>
                    <input type="text" name="name" class="form-control" placeholder="เช่น ปวส.1 เทคโนโลยีสารสนเทศ 1/1" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label required">ระดับชั้น</label>
                        <select name="level" class="form-select" required>
                            <option value="ปวช.1">ปวช.1</option>
                            <option value="ปวช.2">ปวช.2</option>
                            <option value="ปวช.3">ปวช.3</option>
                            <option value="ปวส.1" selected>ปวส.1</option>
                            <option value="ปวส.2">ปวส.2</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label required">ปีการศึกษา</label>
                        <input type="text" name="academic_year" class="form-control" value="2567" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">ครูที่ปรึกษาประจำห้อง</label>
                    <select name="homeroom_teacher_id" class="form-select">
                        <option value="">-- ไม่ระบุ --</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?> (<?= htmlspecialchars($t['teacher_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-0">
                    <label class="form-label">คำอธิบายเพิ่มเติม</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="เช่น ห้องเรียนประจำตึก 4 ชั้น 3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Classroom -->
<div class="modal fade" id="editClassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="<?= BASE_URL ?>admin/classrooms.php" method="POST" class="modal-content">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_id">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-warning me-1"></i> แก้ไขข้อมูลห้องเรียน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label required">ชื่อห้องเรียน / กลุ่มเรียน</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label required">ระดับชั้น</label>
                        <select name="level" id="edit_level" class="form-select" required>
                            <option value="ปวช.1">ปวช.1</option>
                            <option value="ปวช.2">ปวช.2</option>
                            <option value="ปวช.3">ปวช.3</option>
                            <option value="ปวส.1">ปวส.1</option>
                            <option value="ปวส.2">ปวส.2</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label required">ปีการศึกษา</label>
                        <input type="text" name="academic_year" id="edit_year" class="form-control" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">ครูที่ปรึกษาประจำห้อง</label>
                    <select name="homeroom_teacher_id" id="edit_teacher" class="form-select">
                        <option value="">-- ไม่ระบุ --</option>
                        <?php foreach ($teachers as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?> (<?= htmlspecialchars($t['teacher_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-0">
                    <label class="form-label">คำอธิบายเพิ่มเติม</label>
                    <textarea name="description" id="edit_desc" class="form-control" rows="2"></textarea>
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
    const editBtns = document.querySelectorAll('.edit-classroom-btn');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.getAttribute('data-id');
            document.getElementById('edit_name').value = this.getAttribute('data-name');
            document.getElementById('edit_level').value = this.getAttribute('data-level');
            document.getElementById('edit_year').value = this.getAttribute('data-year');
            document.getElementById('edit_teacher').value = this.getAttribute('data-teacher');
            document.getElementById('edit_desc').value = this.getAttribute('data-desc');
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
