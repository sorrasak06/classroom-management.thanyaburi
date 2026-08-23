<?php
/**
 * Notifications Center
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();

$currentUser = getCurrentUser();
$userId = (int)$currentUser['id'];
$pageTitle = 'การแจ้งเตือนทั้งหมด';

// 1. อ่านทั้งหมด (Mark All As Read)
if (isset($_GET['action']) && $_GET['action'] === 'read_all') {
    if (!validateCsrfToken($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'คำขอไม่ถูกต้องหรือหมดอายุ');
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$userId]);
            setFlash('success', 'ทำเครื่องหมายว่าอ่านทั้งหมดแล้ว');
        } catch (PDOException $e) {
            setFlash('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . 'notifications/notifications.php');
    exit;
}

// 2. ลบการแจ้งเตือนเดี่ยว
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (!validateCsrfToken($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'คำขอไม่ถูกต้องหรือหมดอายุ');
    } else {
        $id = (int)$_GET['id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $userId]);
            setFlash('success', 'ลบการแจ้งเตือนเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            setFlash('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . 'notifications/notifications.php');
    exit;
}

// 3. อ่านการแจ้งเตือนเดี่ยว
if (isset($_GET['action']) && $_GET['action'] === 'read' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $userId]);
        
        // ถ้ามีลิงก์ให้ redirect ไปยังหน้านั้น
        if (!empty($_GET['redirect'])) {
            header('Location: ' . BASE_URL . ltrim($_GET['redirect'], '/'));
            exit;
        }
    } catch (PDOException $e) {}
    header('Location: ' . BASE_URL . 'notifications/notifications.php');
    exit;
}

// ดึงรายการแจ้งเตือนทั้งหมดของผู้ใช้
try {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    $notifications = [];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="card">
    <div class="card-header flex-column flex-md-row gap-3 align-items-start align-items-md-center">
        <div>
            <h5 class="card-title mb-0"><i class="bi bi-bell-fill text-primary"></i> ศูนย์การแจ้งเตือน (<?= count($notifications) ?> รายการ)</h5>
            <div class="small text-muted">ติดตามข่าวสาร งานใหม่ และผลคะแนนที่ส่งถึงคุณโดยเฉพาะ</div>
        </div>
        <div>
            <?php if (!empty($notifications)): ?>
                <a href="<?= BASE_URL ?>notifications/notifications.php?action=read_all&csrf_token=<?= generateCsrfToken() ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-check2-all me-1"></i> ทำเครื่องหมายว่าอ่านทั้งหมดแล้ว
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-body p-0">
        <?php if (empty($notifications)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-bell-slash fs-1 d-block mb-2 text-secondary"></i>
                ไม่มีรายการแจ้งเตือนในขณะนี้
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notifications as $n): ?>
                    <div class="list-group-item p-3.5 <?= !$n['is_read'] ? 'bg-primary-subtle bg-opacity-25' : '' ?> d-flex justify-content-between align-items-start gap-3">
                        <div class="d-flex align-items-start gap-3">
                            <div class="mt-1">
                                <?php if (!$n['is_read']): ?>
                                    <span class="badge bg-primary p-1.5 rounded-circle d-inline-block"></span>
                                <?php else: ?>
                                    <i class="bi bi-bell text-muted"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <h6 class="fw-bold text-dark mb-1">
                                    <?php if (!empty($n['link'])): ?>
                                        <a href="<?= BASE_URL ?>notifications/notifications.php?action=read&id=<?= $n['id'] ?>&redirect=<?= urlencode($n['link']) ?>" class="text-dark hover-primary">
                                            <?= htmlspecialchars($n['title']) ?> <i class="bi bi-box-arrow-up-right small ms-1 text-primary"></i>
                                        </a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($n['title']) ?>
                                    <?php endif; ?>
                                </h6>
                                <p class="text-muted small mb-1"><?= htmlspecialchars($n['message']) ?></p>
                                <small class="text-muted"><i class="bi bi-clock me-1"></i><?= formatThaiDate($n['created_at'], true, true) ?></small>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-1">
                            <?php if (!$n['is_read']): ?>
                                <a href="<?= BASE_URL ?>notifications/notifications.php?action=read&id=<?= $n['id'] ?>" class="btn btn-sm btn-light border py-1 px-2" title="ทำเครื่องหมายว่าอ่านแล้ว">
                                    <i class="bi bi-check2 text-primary"></i>
                                </a>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-light border py-1 px-2 text-danger" title="ลบ" 
                                    onclick="confirmDelete('<?= BASE_URL ?>notifications/notifications.php?action=delete&id=<?= $n['id'] ?>&csrf_token=<?= generateCsrfToken() ?>', 'ยืนยันลบการแจ้งเตือนนี้?')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
