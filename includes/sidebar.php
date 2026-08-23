<?php
/**
 * Layout Sidebar Component
 * แถบเมนูด้านข้างระบบ ปรับตามบทบาท (Role) ของผู้ใช้งาน
 */
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config.php';
}
$currentUser = getCurrentUser();
$role = $currentUser['role'] ?? 'student';
$currentScript = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar Navigation -->
<aside class="app-sidebar">
    <div class="sidebar-brand">
        <img src="<?= BASE_URL ?>assets/images/logo.png" alt="College Logo">
        <div class="sidebar-brand-text">
            <span class="title"><?= APP_NAME ?></span>
            <span class="subtitle">ระบบจัดการชั้นเรียน ปวส.</span>
        </div>
    </div>

    <div class="sidebar-menu">
        <?php if ($role === 'admin'): ?>
            <!-- ADMIN MENU -->
            <div class="menu-heading">เมนูหลัก (Administrator)</div>
            <a href="<?= BASE_URL ?>admin/dashboard.php" class="sidebar-nav-link <?= ($currentScript === 'dashboard.php') ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i>
                <span>แดชบอร์ดภาพรวม</span>
            </a>
            <a href="<?= BASE_URL ?>admin/users.php" class="sidebar-nav-link <?= in_array($currentScript, ['users.php', 'user-form.php']) ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i>
                <span>จัดการผู้ใช้งาน</span>
            </a>
            <a href="<?= BASE_URL ?>admin/classrooms.php" class="sidebar-nav-link <?= ($currentScript === 'classrooms.php') ? 'active' : '' ?>">
                <i class="bi bi-door-open-fill"></i>
                <span>จัดการห้องเรียน</span>
            </a>
            <a href="<?= BASE_URL ?>admin/subjects.php" class="sidebar-nav-link <?= ($currentScript === 'subjects.php') ? 'active' : '' ?>">
                <i class="bi bi-journal-bookmark-fill"></i>
                <span>จัดการรายวิชา</span>
            </a>
            <a href="<?= BASE_URL ?>admin/schedules.php" class="sidebar-nav-link <?= ($currentScript === 'schedules.php') ? 'active' : '' ?>">
                <i class="bi bi-calendar3"></i>
                <span>ตารางเรียน/ตารางสอน</span>
            </a>
            <a href="<?= BASE_URL ?>admin/announcements.php" class="sidebar-nav-link <?= ($currentScript === 'announcements.php') ? 'active' : '' ?>">
                <i class="bi bi-megaphone-fill"></i>
                <span>ประกาศข่าวสาร</span>
            </a>
            <a href="<?= BASE_URL ?>admin/reports.php" class="sidebar-nav-link <?= ($currentScript === 'reports.php' && ($_GET['type'] ?? '') !== 'lineup') ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph-fill"></i>
                <span>ศูนย์รายงานรวม</span>
            </a>
            <a href="<?= BASE_URL ?>admin/reports.php?type=lineup" class="sidebar-nav-link <?= ($currentScript === 'reports.php' && ($_GET['type'] ?? '') === 'lineup') ? 'active' : '' ?>">
                <i class="bi bi-qr-code-scan"></i>
                <span>รายงานการเข้าแถว</span>
            </a>
            <a href="<?= BASE_URL ?>admin/settings.php" class="sidebar-nav-link <?= ($currentScript === 'settings.php') ? 'active' : '' ?>">
                <i class="bi bi-gear-wide-connected"></i>
                <span>การตั้งค่าระบบ</span>
            </a>

        <?php elseif ($role === 'teacher'): ?>
            <!-- TEACHER MENU -->
            <div class="menu-heading">การจัดการการสอน (Teacher)</div>
            <a href="<?= BASE_URL ?>teacher/dashboard.php" class="sidebar-nav-link <?= ($currentScript === 'dashboard.php') ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i>
                <span>แดชบอร์ด</span>
            </a>
            <a href="<?= BASE_URL ?>teacher/schedule.php" class="sidebar-nav-link <?= ($currentScript === 'schedule.php') ? 'active' : '' ?>">
                <i class="bi bi-calendar-week-fill"></i>
                <span>ตารางสอนของฉัน</span>
            </a>
            <a href="<?= BASE_URL ?>teacher/students.php" class="sidebar-nav-link <?= in_array($currentScript, ['students.php', 'student-detail.php']) ? 'active' : '' ?>">
                <i class="bi bi-person-lines-fill"></i>
                <span>รายชื่อนักศึกษา</span>
            </a>
            <a href="<?= BASE_URL ?>teacher/lineup-attendance.php" class="sidebar-nav-link <?= ($currentScript === 'lineup-attendance.php') ? 'active' : '' ?>">
                <i class="bi bi-qr-code-scan"></i>
                <span>เช็กชื่อเข้าแถว</span>
            </a>
            <a href="<?= BASE_URL ?>teacher/attendance.php" class="sidebar-nav-link <?= in_array($currentScript, ['attendance.php', 'attendance-history.php']) ? 'active' : '' ?>">
                <i class="bi bi-calendar-check-fill"></i>
                <span>เช็กชื่อเข้าเรียน</span>
            </a>
            <a href="<?= BASE_URL ?>teacher/assignments.php" class="sidebar-nav-link <?= in_array($currentScript, ['assignments.php', 'assignment-submissions.php']) ? 'active' : '' ?>">
                <i class="bi bi-clipboard-check-fill"></i>
                <span>งานและการบ้าน</span>
            </a>
            <a href="<?= BASE_URL ?>teacher/scores.php" class="sidebar-nav-link <?= ($currentScript === 'scores.php') ? 'active' : '' ?>">
                <i class="bi bi-award-fill"></i>
                <span>บันทึกคะแนน/เกรด</span>
            </a>
            <a href="<?= BASE_URL ?>teacher/announcements.php" class="sidebar-nav-link <?= ($currentScript === 'announcements.php') ? 'active' : '' ?>">
                <i class="bi bi-megaphone-fill"></i>
                <span>ประกาศข่าวสาร</span>
            </a>
            <a href="<?= BASE_URL ?>teacher/reports.php" class="sidebar-nav-link <?= ($currentScript === 'reports.php') ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-text-fill"></i>
                <span>สรุปรายงาน</span>
            </a>
            <a href="<?= BASE_URL ?>teacher/lineup-reports.php" class="sidebar-nav-link <?= ($currentScript === 'lineup-reports.php') ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-line-fill"></i>
                <span>รายงานการเข้าแถว</span>
            </a>

        <?php elseif ($role === 'student'): ?>
            <!-- STUDENT MENU -->
            <div class="menu-heading">ระบบการเรียน (Student)</div>
            <a href="<?= BASE_URL ?>student/dashboard.php" class="sidebar-nav-link <?= ($currentScript === 'dashboard.php') ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i>
                <span>แดชบอร์ด</span>
            </a>
            <a href="<?= BASE_URL ?>student/schedule.php" class="sidebar-nav-link <?= ($currentScript === 'schedule.php') ? 'active' : '' ?>">
                <i class="bi bi-calendar-week-fill"></i>
                <span>ตารางเรียน</span>
            </a>
            <a href="<?= BASE_URL ?>student/lineup-attendance.php" class="sidebar-nav-link <?= in_array($currentScript, ['lineup-attendance.php', 'lineup-scan.php']) ? 'active' : '' ?>">
                <i class="bi bi-qr-code-scan"></i>
                <span>เช็กชื่อเข้าแถว</span>
            </a>
            <a href="<?= BASE_URL ?>student/lineup-history.php" class="sidebar-nav-link <?= ($currentScript === 'lineup-history.php') ? 'active' : '' ?>">
                <i class="bi bi-clock-history"></i>
                <span>ประวัติการเข้าแถว</span>
            </a>
            <a href="<?= BASE_URL ?>student/assignments.php" class="sidebar-nav-link <?= in_array($currentScript, ['assignments.php', 'assignment-detail.php']) ? 'active' : '' ?>">
                <i class="bi bi-journal-text"></i>
                <span>งานและการบ้าน</span>
            </a>
            <a href="<?= BASE_URL ?>student/scores.php" class="sidebar-nav-link <?= ($currentScript === 'scores.php') ? 'active' : '' ?>">
                <i class="bi bi-trophy-fill"></i>
                <span>คะแนนและผลการเรียน</span>
            </a>
            <a href="<?= BASE_URL ?>student/attendance.php" class="sidebar-nav-link <?= ($currentScript === 'attendance.php') ? 'active' : '' ?>">
                <i class="bi bi-check-circle-fill"></i>
                <span>ประวัติการเข้าเรียน</span>
            </a>
            <a href="<?= BASE_URL ?>student/announcements.php" class="sidebar-nav-link <?= ($currentScript === 'announcements.php') ? 'active' : '' ?>">
                <i class="bi bi-bell-fill"></i>
                <span>ประกาศข่าวสาร</span>
            </a>
        <?php endif; ?>

        <div class="menu-heading mt-3">บัญชีผู้ใช้</div>
        <a href="<?= BASE_URL ?>profile/profile.php" class="sidebar-nav-link <?= ($currentScript === 'profile.php') ? 'active' : '' ?>">
            <i class="bi bi-person-circle"></i>
            <span>โปรไฟล์ของฉัน</span>
        </a>
        <a href="<?= BASE_URL ?>notifications/notifications.php" class="sidebar-nav-link <?= ($currentScript === 'notifications.php') ? 'active' : '' ?>">
            <i class="bi bi-envelope-fill"></i>
            <span>การแจ้งเตือน</span>
        </a>
    </div>

    <div class="sidebar-footer">
        <a href="<?= BASE_URL ?>logout.php" class="sidebar-nav-link text-danger-emphasis hover-danger" onclick="return confirm('ยืนยันออกจากระบบ?');">
            <i class="bi bi-box-arrow-right text-danger"></i>
            <span class="text-white">ออกจากระบบ</span>
        </a>
    </div>
</aside>

<!-- Backdrop for mobile drawer -->
<div class="sidebar-backdrop"></div>
