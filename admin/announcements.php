<?php
/**
 * Admin - Announcements Management
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$pageTitle = 'จัดการประกาศข่าวสาร';
$currentUser = getCurrentUser();

// 1. เพิ่มประกาศ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    verifyCsrfOrDie();

    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $target_role = trim($_POST['target_role'] ?? 'all');
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;

    if (empty($title) || empty($content)) {
        setFlash('error', 'กรุณากรอกหัวข้อประกาศและเนื้อหา');
    } else {
        $imageFile = null;
        $attachmentFile = null;

        // อัปโหลดรูปภาพ
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upImg = uploadFile($_FILES['image'], 'announcements', ['jpg', 'jpeg', 'png', 'gif'], 5);
            if ($upImg['success']) $imageFile = $upImg['filename'];
        }

        // อัปโหลดไฟล์แนบ
        if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upDoc = uploadFile($_FILES['file'], 'announcements', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip'], 10);
            if ($upDoc['success']) $attachmentFile = $upDoc['filename'];
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO announcements (title, content, target_role, image_attachment, file_attachment, author_id, is_pinned, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$title, $content, $target_role, $imageFile, $attachmentFile, $currentUser['id'], $is_pinned]);
            setFlash('success', 'สร้างประกาศข่าวสารเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            setFlash('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . 'admin/announcements.php');
    exit;
}

// 2. แก้ไขประกาศ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    verifyCsrfOrDie();

    $id = (int)$_POST['id'];
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $target_role = trim($_POST['target_role'] ?? 'all');
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;

    if (empty($title) || empty($content)) {
        setFlash('error', 'กรุณากรอกข้อมูลให้ครบถ้วน');
    } else {
        try {
            // ดึงไฟล์เดิม
            $stmtOld = $pdo->prepare("SELECT image_attachment, file_attachment FROM announcements WHERE id = ?");
            $stmtOld->execute([$id]);
            $old = $stmtOld->fetch();
            $imageFile = $old['image_attachment'] ?? null;
            $attachmentFile = $old['file_attachment'] ?? null;

            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upImg = uploadFile($_FILES['image'], 'announcements', ['jpg', 'jpeg', 'png', 'gif'], 5);
                if ($upImg['success']) {
                    deleteUploadedFile('announcements', $old['image_attachment']);
                    $imageFile = $upImg['filename'];
                }
            }

            if (isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $upDoc = uploadFile($_FILES['file'], 'announcements', ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip'], 10);
                if ($upDoc['success']) {
                    deleteUploadedFile('announcements', $old['file_attachment']);
                    $attachmentFile = $upDoc['filename'];
                }
            }

            $stmt = $pdo->prepare("
                UPDATE announcements 
                SET title = ?, content = ?, target_role = ?, image_attachment = ?, file_attachment = ?, is_pinned = ? 
                WHERE id = ?
            ");
            $stmt->execute([$title, $content, $target_role, $imageFile, $attachmentFile, $is_pinned, $id]);
            setFlash('success', 'แก้ไขประกาศข่าวสารเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            setFlash('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . 'admin/announcements.php');
    exit;
}

// 3. ลบประกาศ
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (!validateCsrfToken($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'คำขอไม่ถูกต้องหรือหมดอายุ');
    } else {
        $id = (int)$_GET['id'];
        try {
            $stmtOld = $pdo->prepare("SELECT image_attachment, file_attachment FROM announcements WHERE id = ?");
            $stmtOld->execute([$id]);
            $old = $stmtOld->fetch();
            if ($old) {
                deleteUploadedFile('announcements', $old['image_attachment']);
                deleteUploadedFile('announcements', $old['file_attachment']);
            }
            $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('success', 'ลบประกาศเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            setFlash('error', 'ไม่สามารถลบประกาศได้: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . 'admin/announcements.php');
    exit;
}

// ดึงรายการประกาศทั้งหมด
$announcements = $pdo->query("
    SELECT a.*, u.full_name as author_name 
    FROM announcements a 
    LEFT JOIN users u ON a.author_id = u.id 
    ORDER BY a.is_pinned DESC, a.created_at DESC
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="card">
    <div class="card-header flex-column flex-md-row gap-3 align-items-start align-items-md-center">
        <div>
            <h5 class="card-title mb-0"><i class="bi bi-megaphone-fill text-danger"></i> ประกาศข่าวสารของระบบ (<?= count($announcements) ?> รายการ)</h5>
            <div class="small text-muted">เผยแพร่ข่าวสาร กิจกรรม และกำหนดการสำคัญแก่อาจารย์และนักศึกษา</div>
        </div>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAnnModal">
                <i class="bi bi-plus-circle me-1"></i> สร้างประกาศใหม่
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>หัวข้อประกาศ</th>
                    <th>กลุ่มเป้าหมาย</th>
                    <th>ไฟล์แนบ/รูปภาพ</th>
                    <th>ผู้ประกาศ</th>
                    <th>วันที่ประกาศ</th>
                    <th class="text-end" style="width: 120px;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($announcements)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            ยังไม่มีรายการประกาศในระบบ
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($announcements as $idx => $ann): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td>
                                <div>
                                    <?php if ($ann['is_pinned']): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle me-1"><i class="bi bi-pin-angle-fill"></i> ปักหมุด</span>
                                    <?php endif; ?>
                                    <span class="fw-semibold text-dark"><?= htmlspecialchars($ann['title']) ?></span>
                                </div>
                                <div class="small text-muted text-truncate" style="max-width: 400px;">
                                    <?= htmlspecialchars(mb_substr($ann['content'], 0, 100)) ?>...
                                </div>
                            </td>
                            <td>
                                <?php if ($ann['target_role'] === 'all'): ?>
                                    <span class="badge bg-secondary-subtle text-secondary rounded-pill">ทุกคน</span>
                                <?php elseif ($ann['target_role'] === 'teacher'): ?>
                                    <span class="badge bg-primary-subtle text-primary rounded-pill">เฉพาะครู</span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success rounded-pill">เฉพาะนักเรียน</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <?php if (!empty($ann['image_attachment'])): ?>
                                        <a href="<?= BASE_URL ?>assets/uploads/announcements/<?= $ann['image_attachment'] ?>" target="_blank" class="btn btn-sm btn-light border py-0 px-1.5" title="ดูรูปภาพ">
                                            <i class="bi bi-image text-primary"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($ann['file_attachment'])): ?>
                                        <a href="<?= BASE_URL ?>assets/uploads/announcements/<?= $ann['file_attachment'] ?>" target="_blank" class="btn btn-sm btn-light border py-0 px-1.5" title="ดาวน์โหลดไฟล์แนบ">
                                            <i class="bi bi-paperclip text-success"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (empty($ann['image_attachment']) && empty($ann['file_attachment'])): ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="small"><?= htmlspecialchars($ann['author_name'] ?? 'แอดมิน') ?></td>
                            <td class="small text-muted"><?= formatThaiDate($ann['created_at'], false, true) ?></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-primary edit-ann-btn" 
                                            data-bs-toggle="modal" data-bs-target="#editAnnModal"
                                            data-id="<?= $ann['id'] ?>"
                                            data-title="<?= htmlspecialchars($ann['title']) ?>"
                                            data-content="<?= htmlspecialchars($ann['content']) ?>"
                                            data-target="<?= $ann['target_role'] ?>"
                                            data-pinned="<?= $ann['is_pinned'] ?>">
                                        <i class="bi bi-pencil-square"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-danger" 
                                            onclick="confirmDelete('<?= BASE_URL ?>admin/announcements.php?action=delete&id=<?= $ann['id'] ?>&csrf_token=<?= generateCsrfToken() ?>', 'ยืนยันลบประกาศนี้?')">
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

<!-- Modal: Add Announcement -->
<div class="modal fade" id="addAnnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="<?= BASE_URL ?>admin/announcements.php" method="POST" enctype="multipart/form-data" class="modal-content">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="create">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary me-1"></i> สร้างประกาศข่าวสารใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label required">หัวข้อประกาศ</label>
                    <input type="text" name="title" class="form-control" placeholder="เช่น แจ้งกำหนดการสอบปลายภาค 1/2567" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label required">กลุ่มเป้าหมาย</label>
                        <select name="target_role" class="form-select" required>
                            <option value="all" selected>ทุกคนในระบบ (All Users)</option>
                            <option value="teacher">เฉพาะครูผู้สอน (Teachers Only)</option>
                            <option value="student">เฉพาะนักศึกษา (Students Only)</option>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-center pt-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_pinned" id="add_pinned" value="1">
                            <label class="form-check-label fw-semibold text-danger" for="add_pinned">
                                <i class="bi bi-pin-angle-fill me-1"></i> ปักหมุดประกาศนี้ไว้ด้านบนสุด
                            </label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label required">เนื้อหาประกาศ</label>
                    <textarea name="content" class="form-control" rows="5" placeholder="ระบุรายละเอียดประกาศ..." required></textarea>
                </div>
                <div class="row g-2 mb-0">
                    <div class="col-md-6">
                        <label class="form-label">รูปภาพประกอบ (Image)</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">เอกสารแนบ (PDF / Word / ZIP)</label>
                        <input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.zip">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">บันทึกและเผยแพร่</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Announcement -->
<div class="modal fade" id="editAnnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="<?= BASE_URL ?>admin/announcements.php" method="POST" enctype="multipart/form-data" class="modal-content">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_ann_id">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-warning me-1"></i> แก้ไขประกาศ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label required">หัวข้อประกาศ</label>
                    <input type="text" name="title" id="edit_ann_title" class="form-control" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label required">กลุ่มเป้าหมาย</label>
                        <select name="target_role" id="edit_ann_target" class="form-select" required>
                            <option value="all">ทุกคนในระบบ (All Users)</option>
                            <option value="teacher">เฉพาะครูผู้สอน (Teachers Only)</option>
                            <option value="student">เฉพาะนักศึกษา (Students Only)</option>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-center pt-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_pinned" id="edit_ann_pinned" value="1">
                            <label class="form-check-label fw-semibold text-danger" for="edit_ann_pinned">
                                <i class="bi bi-pin-angle-fill me-1"></i> ปักหมุดประกาศนี้ไว้ด้านบนสุด
                            </label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label required">เนื้อหาประกาศ</label>
                    <textarea name="content" id="edit_ann_content" class="form-control" rows="5" required></textarea>
                </div>
                <div class="row g-2 mb-0">
                    <div class="col-md-6">
                        <label class="form-label">เปลี่ยนรูปภาพ (เว้นว่างไว้หากไม่เปลี่ยน)</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">เปลี่ยนไฟล์แนบ (เว้นว่างไว้หากไม่เปลี่ยน)</label>
                        <input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.zip">
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
    const editBtns = document.querySelectorAll('.edit-ann-btn');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_ann_id').value = this.getAttribute('data-id');
            document.getElementById('edit_ann_title').value = this.getAttribute('data-title');
            document.getElementById('edit_ann_content').value = this.getAttribute('data-content');
            document.getElementById('edit_ann_target').value = this.getAttribute('data-target');
            document.getElementById('edit_ann_pinned').checked = (this.getAttribute('data-pinned') == '1');
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
