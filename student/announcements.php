<?php
/**
 * Student - Announcements View
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('student');

$pageTitle = 'ประกาศและข่าวสาร';

try {
    $announcements = $pdo->query("
        SELECT a.*, u.full_name as author_name 
        FROM announcements a 
        LEFT JOIN users u ON a.author_id = u.id 
        WHERE a.target_role IN ('all', 'student')
        ORDER BY a.is_pinned DESC, a.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $dbError = $e->getMessage();
    $announcements = [];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0"><i class="bi bi-bell-fill text-primary"></i> ข่าวสารและประกาศจากสถาบันและอาจารย์ (<?= count($announcements) ?> รายการ)</h5>
    </div>
    <div class="card-body p-4">
        <?php if (empty($announcements)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                ยังไม่มีประกาศข่าวสารในขณะนี้
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($announcements as $ann): ?>
                    <div class="col-12">
                        <div class="p-4 rounded-3 border bg-white shadow-sm position-relative" style="<?= $ann['is_pinned'] ? 'border-left: 5px solid var(--danger) !important;' : 'border-left: 5px solid var(--primary) !important;' ?>">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-2">
                                <h5 class="fw-bold text-dark mb-0">
                                    <?php if ($ann['is_pinned']): ?>
                                        <span class="badge bg-danger-subtle text-danger me-1"><i class="bi bi-pin-angle-fill"></i> ปักหมุด</span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($ann['title']) ?>
                                </h5>
                                <div class="small text-muted">
                                    <i class="bi bi-clock me-1"></i><?= formatThaiDate($ann['created_at'], true, true) ?>
                                </div>
                            </div>

                            <div class="small text-muted mb-3">
                                <span>ผู้ประกาศ: <strong><?= htmlspecialchars($ann['author_name'] ?? 'ผู้ดูแลระบบ') ?></strong></span>
                            </div>

                            <div class="text-dark mb-3" style="line-height: 1.7;">
                                <?= nl2br(htmlspecialchars($ann['content'])) ?>
                            </div>

                            <!-- Image Attachment -->
                            <?php if (!empty($ann['image_attachment'])): ?>
                                <div class="mb-3">
                                    <img src="<?= BASE_URL ?>assets/uploads/announcements/<?= $ann['image_attachment'] ?>" alt="Announcement Image" class="img-fluid rounded-3 border" style="max-height: 350px;">
                                </div>
                            <?php endif; ?>

                            <!-- File Attachment -->
                            <?php if (!empty($ann['file_attachment'])): ?>
                                <div class="d-inline-block">
                                    <a href="<?= BASE_URL ?>assets/uploads/announcements/<?= $ann['file_attachment'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download me-1"></i> ดาวน์โหลดเอกสารแนบ (<?= htmlspecialchars($ann['file_attachment']) ?>)
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
