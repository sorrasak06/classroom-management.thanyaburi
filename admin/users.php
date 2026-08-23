<?php
/**
 * Admin - User Management
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$pageTitle = 'จัดการผู้ใช้งานระบบ';

// จัดการการลบผู้ใช้ (Delete User)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $deleteId = (int)$_GET['id'];
    $currentUser = getCurrentUser();

    // ป้องกันไม่ให้ลบตัวเอง
    if ($deleteId === (int)$currentUser['id']) {
        setFlash('error', 'ไม่สามารถลบบัญชีของตนเองที่กำลังเข้าสู่ระบบอยู่ได้');
    } else {
        // ตรวจสอบ CSRF Token จาก URL parameter
        if (!validateCsrfToken($_GET['csrf_token'] ?? '')) {
            setFlash('error', 'คำขอไม่ถูกต้องหรือหมดอายุ');
        } else {
            try {
                // ดึงข้อมูลผู้ใช้เพื่อลบรูป avatar (ถ้ามี)
                $stmtCheck = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
                $stmtCheck->execute([$deleteId]);
                $userToDelete = $stmtCheck->fetch();

                if ($userToDelete) {
                    if (!empty($userToDelete['avatar'])) {
                        deleteUploadedFile('avatars', $userToDelete['avatar']);
                    }
                    $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmtDel->execute([$deleteId]);
                    setFlash('success', 'ลบข้อมูลผู้ใช้งานเรียบร้อยแล้ว');
                }
            } catch (PDOException $e) {
                setFlash('error', 'ไม่สามารถลบข้อมูลได้: ' . $e->getMessage());
            }
        }
    }
    header('Location: ' . BASE_URL . 'admin/users.php');
    exit;
}

// ตัวแปรค้นหาและตัวกรอง
$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? 'all');
$statusFilter = trim($_GET['status'] ?? 'all');

// สร้าง SQL Query
$sql = "
    SELECT u.*, 
           s.student_code, c.name as classroom_name,
           t.teacher_code, t.department
    FROM users u
    LEFT JOIN students s ON u.id = s.user_id
    LEFT JOIN classrooms c ON s.classroom_id = c.id
    LEFT JOIN teachers t ON u.id = t.user_id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR s.student_code LIKE ? OR t.teacher_code LIKE ?)";
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

if ($roleFilter !== 'all' && in_array($roleFilter, ['admin', 'teacher', 'student'])) {
    $sql .= " AND u.role = ?";
    $params[] = $roleFilter;
}

if ($statusFilter !== 'all' && in_array($statusFilter, ['active', 'inactive'])) {
    $sql .= " AND u.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY u.id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $dbError = $e->getMessage();
    $users = [];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="card">
    <div class="card-header flex-column flex-md-row gap-3 align-items-start align-items-md-center">
        <div>
            <h5 class="card-title mb-0"><i class="bi bi-people-fill text-primary"></i> รายการผู้ใช้งานในระบบ (<?= count($users) ?> คน)</h5>
            <div class="small text-muted">จัดการข้อมูล แอดมิน ครู และนักศึกษา</div>
        </div>
        <div class="d-flex gap-2 w-100 w-md-auto justify-content-md-end">
            <a href="<?= BASE_URL ?>admin/user-form.php" class="btn btn-primary">
                <i class="bi bi-person-plus-fill"></i> เพิ่มผู้ใช้งานใหม่
            </a>
        </div>
    </div>

    <!-- Filter & Search Form -->
    <div class="card-body border-bottom bg-light bg-opacity-50 py-3">
        <form action="<?= BASE_URL ?>admin/users.php" method="GET" class="row g-2 align-items-center">
            <div class="col-md-4 col-12">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="ค้นหาชื่อ, รหัส, อีเมล..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-3 col-6">
                <select name="role" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>ทุกบทบาท (All Roles)</option>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>ผู้ดูแลระบบ (Admin)</option>
                    <option value="teacher" <?= $roleFilter === 'teacher' ? 'selected' : '' ?>>ครูผู้สอน (Teacher)</option>
                    <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>นักศึกษา (Student)</option>
                </select>
            </div>
            <div class="col-md-3 col-6">
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>ทุกสถานะ (All Status)</option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>ใช้งาน (Active)</option>
                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>ปิดใช้งาน (Inactive)</option>
                </select>
            </div>
            <div class="col-md-2 col-12 d-flex gap-2">
                <button type="submit" class="btn btn-dark w-100">กรอง</button>
                <?php if (!empty($search) || $roleFilter !== 'all' || $statusFilter !== 'all'): ?>
                    <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-outline-secondary" title="ล้างค่าตัวกรอง"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="usersTable">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>ผู้ใช้งาน</th>
                    <th>รหัสประจำตัว / สังกัด</th>
                    <th>บทบาท</th>
                    <th>เบอร์โทรศัพท์</th>
                    <th>สถานะ</th>
                    <th>วันที่สร้าง</th>
                    <th class="text-end" style="width: 120px;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary"></i>
                            ไม่พบข้อมูลผู้ใช้งานที่ตรงกับเงื่อนไข
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $index => $u): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2.5">
                                    <img src="<?= getUserAvatarUrl($u['avatar'], $u['full_name']) ?>" class="user-avatar" alt="Avatar">
                                    <div>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($u['full_name']) ?></div>
                                        <div class="small text-muted">@<?= htmlspecialchars($u['username']) ?> &bull; <?= htmlspecialchars($u['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php if ($u['role'] === 'student'): ?>
                                    <span class="badge bg-secondary-subtle text-dark"><?= htmlspecialchars($u['student_code'] ?? '-') ?></span>
                                    <div class="small text-muted mt-0.5"><?= htmlspecialchars($u['classroom_name'] ?? 'ไม่มีห้องเรียน') ?></div>
                                <?php elseif ($u['role'] === 'teacher'): ?>
                                    <span class="badge bg-secondary-subtle text-dark"><?= htmlspecialchars($u['teacher_code'] ?? '-') ?></span>
                                    <div class="small text-muted mt-0.5"><?= htmlspecialchars($u['department'] ?? '-') ?></div>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= getRoleBadge($u['role']) ?></td>
                            <td class="small"><?= htmlspecialchars($u['phone'] ?? '-') ?></td>
                            <td>
                                <?php if ($u['status'] === 'active'): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">ใช้งาน</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">ปิดใช้งาน</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= formatThaiDate($u['created_at'], false, true) ?></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= BASE_URL ?>admin/user-form.php?id=<?= $u['id'] ?>" class="btn btn-outline-primary" title="แก้ไข">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <?php if ($u['id'] !== $currentUser['id']): ?>
                                        <button type="button" class="btn btn-outline-danger" title="ลบ" onclick="confirmDelete('<?= BASE_URL ?>admin/users.php?action=delete&id=<?= $u['id'] ?>&csrf_token=<?= generateCsrfToken() ?>', 'คุณแน่ใจหรือไม่ว่าต้องการลบผู้ใช้ <?= addslashes($u['full_name']) ?>?')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
