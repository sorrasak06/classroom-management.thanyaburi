<?php
/**
 * Teacher - Attendance History
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('teacher');

$pageTitle = 'ประวัติการเช็กชื่อย้อนหลัง';
$currentUser = getCurrentUser();
$teacherId = $currentUser['role_id'] ?? 0;

// ดึงวิชาทั้งหมดที่ครูสอน
$stmtSubs = $pdo->prepare("
    SELECT s.*, c.name as classroom_name 
    FROM subjects s 
    JOIN classrooms c ON s.classroom_id = c.id 
    WHERE s.teacher_id = ? 
    ORDER BY s.subject_code ASC
");
$stmtSubs->execute([$teacherId]);
$subjects = $stmtSubs->fetchAll();

$selectedSubject = !empty($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$selectedStatus = trim($_GET['status'] ?? 'all');
$startDate = trim($_GET['start_date'] ?? date('Y-m-01'));
$endDate = trim($_GET['end_date'] ?? date('Y-m-d'));

$sql = "
    SELECT a.*, s.student_code, u.full_name, sub.subject_code, sub.name_th as subject_name, c.name as classroom_name
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN subjects sub ON a.subject_id = sub.id
    JOIN classrooms c ON a.classroom_id = c.id
    WHERE sub.teacher_id = ?
      AND (a.attendance_date BETWEEN ? AND ?)
";
$params = [$teacherId, $startDate, $endDate];

if ($selectedSubject > 0) {
    $sql .= " AND a.subject_id = ?";
    $params[] = $selectedSubject;
}

if ($selectedStatus !== 'all' && in_array($selectedStatus, ['present', 'absent', 'late', 'leave'])) {
    $sql .= " AND a.status = ?";
    $params[] = $selectedStatus;
}

$sql .= " ORDER BY a.attendance_date DESC, s.student_code ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
} catch (PDOException $e) {
    $dbError = $e->getMessage();
    $logs = [];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="card">
    <div class="card-header flex-column flex-md-row gap-3 align-items-start align-items-md-center">
        <div>
            <h5 class="card-title mb-0"><i class="bi bi-clock-history text-primary"></i> ประวัติการเช็กชื่อย้อนหลัง</h5>
            <div class="small text-muted">ตรวจสอบและส่งออกข้อมูลเวลาเรียนย้อนหลังของนักศึกษา</div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>teacher/attendance.php" class="btn btn-sm btn-primary">
                <i class="bi bi-calendar-check me-1"></i> ไปหน้าเช็กชื่อวันนี้
            </a>
            <button type="button" class="btn btn-sm btn-outline-success" onclick="exportTableToCSV('historyTable', 'attendance_history.csv')">
                <i class="bi bi-file-earmark-excel me-1"></i> ส่งออก Excel
            </button>
            <button type="button" class="btn btn-sm btn-dark" onclick="printReport()">
                <i class="bi bi-printer me-1"></i> พิมพ์
            </button>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card-body bg-light bg-opacity-50 border-bottom py-3 no-print">
        <form action="<?= BASE_URL ?>teacher/attendance-history.php" method="GET" class="row g-2 align-items-end">
            <div class="col-md-3 col-12">
                <label class="form-label small mb-1">รายวิชา</label>
                <select name="subject_id" class="form-select form-select-sm">
                    <option value="0">-- ทุกรายวิชาที่สอน --</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $selectedSubject === $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['subject_code']) ?> <?= htmlspecialchars($s['name_th']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small mb-1">สถานะ</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="all" <?= $selectedStatus === 'all' ? 'selected' : '' ?>>ทุกสถานะ</option>
                    <option value="present" <?= $selectedStatus === 'present' ? 'selected' : '' ?>>มาเรียน</option>
                    <option value="absent" <?= $selectedStatus === 'absent' ? 'selected' : '' ?>>ขาดเรียน</option>
                    <option value="late" <?= $selectedStatus === 'late' ? 'selected' : '' ?>>สาย</option>
                    <option value="leave" <?= $selectedStatus === 'leave' ? 'selected' : '' ?>>ลา</option>
                </select>
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small mb-1">ตั้งแต่วันที่</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($startDate) ?>">
            </div>
            <div class="col-md-2 col-6">
                <label class="form-label small mb-1">ถึงวันที่</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($endDate) ?>">
            </div>
            <div class="col-md-3 col-6 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-dark flex-grow-1">กรองข้อมูล</button>
                <a href="<?= BASE_URL ?>teacher/attendance-history.php" class="btn btn-sm btn-outline-secondary" title="ล้างตัวกรอง"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="historyTable">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>วันที่</th>
                    <th>รหัสนักศึกษา</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>วิชา</th>
                    <th>ห้องเรียน</th>
                    <th>สถานะ</th>
                    <th>หมายเหตุ</th>
                    <th class="text-end no-print" style="width: 80px;">แก้ไข</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            ไม่พบประวัติการเช็กชื่อตามเงื่อนไข
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $idx => $row): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td class="small fw-semibold"><?= formatThaiDate($row['attendance_date']) ?></td>
                            <td><span class="badge bg-secondary-subtle text-dark"><?= htmlspecialchars($row['student_code']) ?></span></td>
                            <td><?= htmlspecialchars($row['full_name']) ?></td>
                            <td class="small"><?= htmlspecialchars($row['subject_code']) ?> <?= htmlspecialchars($row['subject_name']) ?></td>
                            <td class="small"><?= htmlspecialchars($row['classroom_name']) ?></td>
                            <td><?= getAttendanceBadge($row['status']) ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($row['remark'] ?? '-') ?></td>
                            <td class="text-end no-print">
                                <a href="<?= BASE_URL ?>teacher/attendance.php?subject_id=<?= $row['subject_id'] ?>&date=<?= $row['attendance_date'] ?>" class="btn btn-sm btn-outline-primary" title="เปิดหน้าเช็กชื่อของวันนี้เพื่อแก้ไข">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
