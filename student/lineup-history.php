<?php
/**
 * Student Line-up Attendance History & Statistics
 * ประวัติและสถิติการเข้าแถวสำหรับนักเรียน (เฉพาะตนเอง)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('student');

$pageTitle = 'ประวัติการเข้าแถว';
$currentUser = getCurrentUser();
$studentId   = (int)($currentUser['role_id'] ?? 0);

// 1. ดึงจำนวน Session การเข้าแถวทั้งหมดที่มีในระบบ
$totalSessionsCount = (int)$pdo->query("SELECT COUNT(*) FROM lineup_sessions")->fetchColumn();

// 2. ดึงสถิติการเช็กชื่อของตนเอง
$stmtStats = $pdo->prepare("
    SELECT 
        COUNT(*) as attended_count,
        SUM(CASE WHEN status = 'on_time' THEN 1 ELSE 0 END) as on_time_count,
        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count
    FROM lineup_attendance
    WHERE student_id = ?
");
$stmtStats->execute([$studentId]);
$statsRow = $stmtStats->fetch();

$attendedCount = (int)($statsRow['attended_count'] ?? 0);
$onTimeCount   = (int)($statsRow['on_time_count'] ?? 0);
$lateCount     = (int)($statsRow['late_count'] ?? 0);
$absentCount   = max(0, $totalSessionsCount - $attendedCount);
$attendancePct = ($totalSessionsCount > 0) ? round(($attendedCount / $totalSessionsCount) * 100, 2) : 0;

// 3. ดึงรายการประวัติการเข้าแถวทั้งหมด
$stmtHistory = $pdo->prepare("
    SELECT 
        ls.session_date,
        ls.start_time,
        ls.end_time,
        la.check_in_time,
        la.status as attendance_status,
        la.created_at
    FROM lineup_sessions ls
    LEFT JOIN lineup_attendance la ON ls.id = la.lineup_session_id AND la.student_id = ?
    ORDER BY ls.session_date DESC
    LIMIT 60
");
$stmtHistory->execute([$studentId]);
$historyList = $stmtHistory->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1"><i class="bi bi-clock-history text-primary me-2"></i> ประวัติและสถิติการเข้าแถว</h3>
        <p class="text-muted mb-0">รายงานประวัติการเข้าร่วมแถวตอนเช้าของ <?= htmlspecialchars($currentUser['full_name']) ?></p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>student/lineup-attendance.php" class="btn btn-primary rounded-pill">
            <i class="bi bi-qr-code-scan me-1"></i> เช็กชื่อวันนี้
        </a>
    </div>
</div>

<?= displayFlashMessages() ?>

<!-- Summary Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-value"><?= number_format($totalSessionsCount) ?> วัน</div>
                <div class="stat-label">เข้าแถวทั้งหมด</div>
            </div>
            <div class="stat-icon primary">
                <i class="bi bi-calendar3"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-value text-success"><?= number_format($onTimeCount) ?> วัน</div>
                <div class="stat-label">ตรงเวลา</div>
            </div>
            <div class="stat-icon success">
                <i class="bi bi-check-circle-fill"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-value text-warning-emphasis"><?= number_format($lateCount) ?> วัน</div>
                <div class="stat-label">มาสาย</div>
            </div>
            <div class="stat-icon warning">
                <i class="bi bi-clock-history"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-value text-danger"><?= number_format($absentCount) ?> วัน</div>
                <div class="stat-label">ขาดเข้าแถว</div>
            </div>
            <div class="stat-icon danger">
                <i class="bi bi-x-circle-fill"></i>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Percentage Progress Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-bold text-dark fs-5"><i class="bi bi-pie-chart-fill me-2 text-primary"></i> อัตราการเข้าแถวสะสม</span>
            <span class="fs-4 fw-bold text-primary"><?= $attendancePct ?>%</span>
        </div>
        <div class="progress mb-3" style="height: 16px; border-radius: 10px;">
            <div class="progress-bar bg-success" role="progressbar" style="width: <?= ($totalSessionsCount > 0) ? round(($onTimeCount/$totalSessionsCount)*100, 1) : 0 ?>%" title="ตรงเวลา"></div>
            <div class="progress-bar bg-warning" role="progressbar" style="width: <?= ($totalSessionsCount > 0) ? round(($lateCount/$totalSessionsCount)*100, 1) : 0 ?>%" title="สาย"></div>
            <div class="progress-bar bg-danger" role="progressbar" style="width: <?= ($totalSessionsCount > 0) ? round(($absentCount/$totalSessionsCount)*100, 1) : 0 ?>%" title="ขาด"></div>
        </div>
        <div class="d-flex gap-4 small text-muted">
            <div class="d-flex align-items-center gap-1.5"><span class="badge bg-success p-1 rounded-circle"></span> ตรงเวลา (<?= $onTimeCount ?>)</div>
            <div class="d-flex align-items-center gap-1.5"><span class="badge bg-warning p-1 rounded-circle"></span> มาสาย (<?= $lateCount ?>)</div>
            <div class="d-flex align-items-center gap-1.5"><span class="badge bg-danger p-1 rounded-circle"></span> ขาดเข้าแถว (<?= $absentCount ?>)</div>
        </div>
    </div>
</div>

<!-- History Table Card -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white py-3">
        <h5 class="card-title mb-0 fw-bold"><i class="bi bi-journal-check me-2 text-primary"></i> รายการประวัติการเข้าแถวย้อนหลัง</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 70px;">ลำดับ</th>
                    <th>วันที่เข้าแถว</th>
                    <th>เวลาเปิด - ปิด</th>
                    <th>เวลาที่เช็กชื่อ</th>
                    <th>สถานะ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($historyList)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">ยังไม่มีประวัติการเข้าแถว</td>
                    </tr>
                <?php else: ?>
                    <?php $seq = 1; foreach ($historyList as $item): ?>
                        <tr>
                            <td><?= sprintf('%02d', $seq++) ?></td>
                            <td class="fw-medium text-dark"><?= formatThaiDate($item['session_date']) ?></td>
                            <td class="small text-muted"><?= formatTime($item['start_time']) ?> - <?= formatTime($item['end_time']) ?></td>
                            <td>
                                <?php if (!empty($item['check_in_time'])): ?>
                                    <span class="fw-bold text-dark"><?= date('H:i:s', strtotime($item['check_in_time'])) ?> น.</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                if (empty($item['attendance_status'])) {
                                    echo '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 rounded-pill"><i class="bi bi-x-circle me-1"></i>ขาดเข้าแถว</span>';
                                } elseif ($item['attendance_status'] === 'on_time') {
                                    echo '<span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill"><i class="bi bi-check-circle me-1"></i>ตรงเวลา</span>';
                                } else {
                                    echo '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1 rounded-pill"><i class="bi bi-clock-history me-1"></i>สาย</span>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
