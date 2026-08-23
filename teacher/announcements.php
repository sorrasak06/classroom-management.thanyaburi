<?php
/**
 * Teacher - Announcements Management
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('teacher');

$pageTitle = 'ประกาศข่าวสารประจำวิชา';
$currentUser = getCurrentUser();

// 1. เพิ่มประกาศ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    verifyCsrfOrDie();

    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $target_role = trim($_POST['target_role'] ?? 'student');
    $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;

    if (empty($title) || empty($content)) {
        setFlash('error', 'กรุณากรอกหัวข้อประกาศและเนื้อหา');
    } else {
        $imageFile = null;
        $attachmentFile = null;

        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upImg = uploadFile($_FILES['image'], 'announcements', ['jpg', 'jpeg', 'png', 'gif'], 5);
            if ($upImg['success']) $imageFile = $upImg['filename'];
        }

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
    header('Location: ' . BASE_URL . 'teacher/announcements.php');
    exit;
}

// 2. ลบประกาศ
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (!validateCsrfToken($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'คำขอไม่ถูกต้องหรือหมดอายุ');
    } else {
        $id = (int)$_GET['id'];
        try {
            $stmtOld = $pdo->prepare("SELECT image_attachment, file_attachment FROM announcements WHERE id = ? AND author_id = ?");
            $stmtOld->execute([$id, $currentUser['id']]);
            $old = $stmtOld->fetch();
            if ($old) {
                deleteUploadedFile('announcements', $old['image_attachment']);
                deleteUploadedFile('announcements', $old['file_attachment']);
                $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ? AND author_id = ?");
                $stmt->execute([$id, $currentUser['id']]);
                setFlash('success', 'ลบประกาศเรียบร้อยแล้ว');
            }
        } catch (PDOException $e) {
            setFlash('error', 'ไม่สามารถลบประกาศได้: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . 'teacher/announcements.php');
    exit;
}

// ดึงรายการประกาศของครูคนนี้ และประกาศของระบบ
$announcements = $pdo->prepare("
    SELECT a.*, u.full_name as author_name 
    FROM announcements a 
    LEFT JOIN users u ON a.author_id = u.id 
    WHERE a.author_id = ? OR a.target_role IN ('all', 'teacher')
    ORDER BY a.is_pinned DESC, a.created_at DESC
");
$announcements->execute([$currentUser['id']]);
$annList = $announcements->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="card">
    <div class="card-header flex-column flex-md-row gap-3 align-items-start align-items-md-center">
        <div>
            <h5 class="card-title mb-0"><i class="bi bi-megaphone-fill text-primary"></i> ประกาศข่าวสารประจำวิชา (<?= count($annList) ?> รายการ)</h5>
            <div class="small text-muted">สร้างและจัดการประกาศข่าวสาร กำหนดการ และเนื้อหาให้นักศึกษา</div>
        </div>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTeacherAnnModal">
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
                    <th class="text-end" style="width: 100px;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($annList)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">ยังไม่มีรายการประกาศ</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($annList as $idx => $ann): ?>
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
                                    <span class="badge bg-secondary-subtle text-secondary">ทุกคน</span>
                                <?php elseif ($ann['target_role'] === 'teacher'): ?>
                                    <span class="badge bg-primary-subtle text-primary">เฉพาะครู</span>
                                <?php else: ?>
                                    <span class="badge bg-success-subtle text-success">เฉพาะนักเรียน</span>
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
                                        <a href="<?= BASE_URL ?>assets/uploads/announcements/<?= $ann['file_attachment'] ?>" target="_blank" class="btn btn-sm btn-light border py-0 px-1.5" title="ดาวน์โหลดไฟล์">
                                            <i class="bi bi-paperclip text-success"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (empty($ann['image_attachment']) && empty($ann['file_attachment'])): ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="small"><?= htmlspecialchars($ann['author_name']) ?></td>
                            <td class="small text-muted"><?= formatThaiDate($ann['created_at'], false, true) ?></td>
                            <td class="text-end">
                                <?php if ($ann['author_id'] === $currentUser['id']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="confirmDelete('<?= BASE_URL ?>teacher/announcements.php?action=delete&id=<?= $ann['id'] ?>&csrf_token=<?= generateCsrfToken() ?>', 'ยืนยันลบประกาศนี้?')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted small">ระบบ</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Announcement -->
<div class="modal fade" id="addTeacherAnnModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form action="<?= BASE_URL ?>teacher/announcements.php" method="POST" enctype="multipart/form-data" class="modal-content">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="create">

            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary me-1"></i> สร้างประกาศใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label required">หัวข้อประกาศ</label>
                    <input type="text" name="title" class="form-control" placeholder="เช่น นัดหมายส่งโครงงานสัปดาห์หน้า" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label required">กลุ่มเป้าหมาย</label>
                        <select name="target_role" class="form-select" required>
                            <option value="student" selected>เฉพาะนักศึกษาในห้องเรียน</option>
                            <option value="all">ทุกคนในระบบ</option>
                        </select>
                    </div>
                    <div class="col-md-6 d-flex align-items-center pt-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_pinned" id="t_pinned" value="1">
                            <label class="form-check-label fw-semibold text-danger" for="t_pinned">
                                <i class="bi bi-pin-angle-fill me-1"></i> ปักหมุดประกาศนี้
                            </label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label required">เนื้อหาประกาศ</label>
                    <textarea name="content" class="form-control" rows="5" placeholder="ระบุรายละเอียด..." required></textarea>
                </div>
                <div class="row g-2 mb-0">
                    <div class="col-md-6">
                        <label class="form-label">รูปภาพประกอบ</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">เอกสารแนบ</label>
                        <input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.zip">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary">บันทึกและประกาศ</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
