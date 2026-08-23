<?php
/**
 * Student - Personal Attendance History
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('student');

$pageTitle = 'ประวัติการเข้าเรียนของฉัน';
$currentUser = getCurrentUser();
$studentId = $currentUser['role_id'] ?? 0;

try {
    // ดึงสถิติรวม
    $stmtStats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
            SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_days
        FROM attendance
        WHERE student_id = ?
    ");
    $stmtStats->execute([$studentId]);
    $stats = $stmtStats->fetch();

    $totalDays = (int)($stats['total_days'] ?? 0);
    $presentDays = (int)($stats['present_days'] ?? 0);
    $presentRate = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : 100;

    // ดึงประวัติทั้งหมด
    $stmtLogs = $pdo->prepare("
        SELECT a.*, s.subject_code, s.name_th as subject_name, u.full_name as teacher_name
        FROM attendance a
        JOIN subjects s ON a.subject_id = s.id
        JOIN users u ON a.recorded_by = u.id
        WHERE a.student_id = ?
        ORDER BY a.attendance_date DESC
    ");
    $stmtLogs->execute([$studentId]);
    $logs = $stmtLogs->fetchAll();

} catch (PDOException $e) {
    $dbError = $e->getMessage();
    $logs = [];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<!-- Attendance KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-value text-success"><?= $presentRate ?>%</div>
                <div class="stat-label">อัตราการมาเรียน</div>
            </div>
            <div class="stat-icon success">
                <i class="bi bi-check-circle-fill"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-value text-success"><?= $stats['present_days'] ?? 0 ?> วัน</div>
                <div class="stat-label">มาเรียนตรงเวลา</div>
            </div>
            <div class="stat-icon success">
                <i class="bi bi-person-check-fill"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-value text-danger"><?= $stats['absent_days'] ?? 0 ?> วัน</div>
                <div class="stat-label">ขาดเรียน</div>
            </div>
            <div class="stat-icon danger">
                <i class="bi bi-x-circle-fill"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-value text-warning-emphasis"><?= $stats['late_days'] ?? 0 ?> วัน</div>
                <div class="stat-label">มาสาย</div>
            </div>
            <div class="stat-icon warning">
                <i class="bi bi-clock-history"></i>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header flex-column flex-md-row gap-3 align-items-start align-items-md-center">
        <div>
            <h5 class="card-title mb-0"><i class="bi bi-calendar-check-fill text-primary"></i> บันทึกเวลาเรียนย้อนหลัง (<?= count($logs) ?> วัน)</h5>
            <div class="small text-muted">ตรวจสอบประวัติการเช็กชื่อรายวันและหมายเหตุจากอาจารย์ผู้สอน</div>
        </div>
        <div class="no-print">
            <button type="button" class="btn btn-dark btn-sm" onclick="printReport()">
                <i class="bi bi-printer me-1"></i> พิมพ์ประวัติเวลาเรียน
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>วันที่</th>
                    <th>รายวิชา</th>
                    <th>อาจารย์ผู้สอน</th>
                    <th class="text-center">สถานะ</th>
                    <th>หมายเหตุ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            ยังไม่มีประวัติการเช็กชื่อในระบบ
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $idx => $log): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td class="fw-semibold text-dark"><?= formatThaiDate($log['attendance_date']) ?></td>
                            <td>
                                <span class="badge bg-secondary-subtle text-dark"><?= htmlspecialchars($log['subject_code']) ?></span>
                                <span class="ms-1"><?= htmlspecialchars($log['subject_name']) ?></span>
                            </td>
                            <td class="small"><?= htmlspecialchars($log['teacher_name']) ?></td>
                            <td class="text-center"><?= getAttendanceBadge($log['status']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($log['remark'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
