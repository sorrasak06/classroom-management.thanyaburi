<?php
/**
 * Layout Navbar / Topbar Component
 */
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/functions.php';

$currentUser = getCurrentUser();
$userId = $currentUser['id'] ?? 0;
$unreadCount = 0;

if (isset($pdo) && $userId > 0) {
    $unreadCount = getUnreadNotificationsCount($pdo, $userId);
}

$avatarUrl = getUserAvatarUrl($currentUser['avatar'] ?? null, $currentUser['full_name'] ?? 'User');
?>
<!-- Main Content Area Wrapper -->
<div class="app-main">
    <!-- Top Navigation Bar -->
    <header class="app-topbar">
        <div class="topbar-left">
            <button class="topbar-toggler" type="button" aria-label="Toggle navigation">
                <i class="bi bi-list"></i>
            </button>
            <div class="page-title-box">
                <h1><?= htmlspecialchars($pageTitle ?? APP_NAME) ?></h1>
            </div>
        </div>

        <div class="topbar-right">
            <!-- Notifications Dropdown -->
            <div class="dropdown">
                <a href="<?= BASE_URL ?>notifications/notifications.php" class="topbar-btn" title="การแจ้งเตือน">
                    <i class="bi bi-bell-fill"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span class="badge-dot"></span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- User Profile Dropdown -->
            <div class="dropdown">
                <button class="user-profile-dropdown dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <img src="<?= $avatarUrl ?>" alt="Avatar" class="user-avatar">
                    <div class="user-info d-none d-md-flex">
                        <span class="user-name"><?= htmlspecialchars($currentUser['full_name'] ?? 'ผู้ใช้งาน') ?></span>
                        <span class="user-role-text"><?= getRoleBadge($currentUser['role'] ?? '') ?></span>
                    </div>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 py-2" style="border-radius: 0.75rem; min-width: 220px;">
                    <li class="px-3 py-2 border-bottom d-md-none">
                        <div class="fw-bold text-dark"><?= htmlspecialchars($currentUser['full_name'] ?? '') ?></div>
                        <div class="small text-muted"><?= htmlspecialchars($currentUser['email'] ?? '') ?></div>
                    </li>
                    <li>
                        <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= BASE_URL ?>profile/profile.php">
                            <i class="bi bi-person-gear text-primary"></i>
                            <span>ข้อมูลส่วนตัว & รหัสผ่าน</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?= BASE_URL ?>notifications/notifications.php">
                            <i class="bi bi-bell text-warning"></i>
                            <span>การแจ้งเตือน</span>
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge bg-danger rounded-pill ms-auto"><?= $unreadCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <a class="dropdown-item py-2 d-flex align-items-center gap-2 text-danger" href="<?= BASE_URL ?>logout.php" onclick="return confirm('ยืนยันออกจากระบบ?');">
                            <i class="bi bi-box-arrow-right"></i>
                            <span>ออกจากระบบ</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Page Content Container -->
    <main class="app-content">
        <!-- Flash Messages Display -->
        <?= displayFlashMessages() ?>
