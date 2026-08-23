<?php
/**
 * Teacher - Classroom & Subject Reports
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('teacher');

$pageTitle = 'สรุปรายงานประจำวิชา';
$currentUser = getCurrentUser();
$teacherId = $currentUser['role_id'] ?? 0;

// ดึงวิชาที่ครูสอน
$stmtSubs = $pdo->prepare("
    SELECT s.*, c.name as classroom_name 
    FROM subjects s 
    JOIN classrooms c ON s.classroom_id = c.id 
    WHERE s.teacher_id = ? 
    ORDER BY s.subject_code ASC
");
$stmtSubs->execute([$teacherId]);
$subjects = $stmtSubs->fetchAll();

$reportType = trim($_GET['type'] ?? 'attendance');
$selectedSubject = !empty($_GET['subject_id']) ? (int)$_GET['subject_id'] : ($subjects[0]['id'] ?? 0);
$startDate = trim($_GET['start_date'] ?? date('Y-m-01'));
$endDate = trim($_GET['end_date'] ?? date('Y-m-d'));

$reportData = [];
$reportTitle = '';

try {
    if ($reportType === 'grades') {
        $reportTitle = 'รายงานผลคะแนนและตัดเกรดรายวิชา';
        $sql = "
            SELECT sc.*, s.student_code, u.full_name, c.name as classroom_name, sub.subject_code, sub.name_th as subject_name
            FROM scores sc
            JOIN students s ON sc.student_id = s.id
            JOIN users u ON s.user_id = u.id
            JOIN classrooms c ON s.classroom_id = c.id
            JOIN subjects sub ON sc.subject_id = sub.id
            WHERE sub.teacher_id = ?
        ";
        $params = [$teacherId];
        if ($selectedSubject > 0) {
            $sql .= " AND sc.subject_id = ?";
            $params[] = $selectedSubject;
        }
        $sql .= " ORDER BY sub.subject_code ASC, s.student_code ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();

    } else {
        $reportType = 'attendance';
        $reportTitle = 'รายงานสถิติการเข้าเรียนประจำวิชา';
        $sql = "
            SELECT 
                s.id as student_id, s.student_code, u.full_name, c.name as classroom_name,
                COUNT(a.id) as total_checks,
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN a.status = 'leave' THEN 1 ELSE 0 END) as leave_count
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN classrooms c ON s.classroom_id = c.id
            JOIN subjects sub ON sub.classroom_id = c.id
            LEFT JOIN attendance a ON s.id = a.student_id 
                 AND a.subject_id = sub.id
                 AND (a.attendance_date BETWEEN ? AND ?)
            WHERE sub.teacher_id = ?
        ";
        $params = [$startDate, $endDate, $teacherId];
        if ($selectedSubject > 0) {
            $sql .= " AND sub.id = ?";
            $params[] = $selectedSubject;
        }
        $sql .= " GROUP BY s.id ORDER BY s.student_code ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $dbError = $e->getMessage();
    $reportData = [];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<!-- Print-Only Header -->
<div class="print-header">
    <img src="<?= BASE_URL ?>assets/images/logo.png" style="height: 60px; margin-bottom: 8px;">
    <h3 style="margin: 0; font-size: 16pt;"><?= APP_NAME ?></h3>
    <h4 style="margin: 5px 0 15px 0; font-size: 13pt;"><?= htmlspecialchars($reportTitle) ?></h4>
    <div style="font-size: 10pt; color: #555;">
        อาจารย์ผู้สอน: <?= htmlspecialchars($currentUser['full_name']) ?> | ออกรายงานเมื่อ: <?= formatThaiDateTime(date('Y-m-d H:i:s')) ?>
    </div>
    <hr style="margin: 15px 0;">
</div>

<div class="card no-print">
    <div class="card-header border-bottom-0 pb-0">
        <ul class="nav nav-tabs card-header-tabs">
            <li class="nav-item">
                <a class="nav-link <?= $reportType === 'attendance' ? 'active fw-bold' : '' ?>" href="<?= BASE_URL ?>teacher/reports.php?type=attendance&subject_id=<?= $selectedSubject ?>">
                    <i class="bi bi-calendar-check me-1"></i> รายงานการเข้าเรียน
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $reportType === 'grades' ? 'active fw-bold' : '' ?>" href="<?= BASE_URL ?>teacher/reports.php?type=grades&subject_id=<?= $selectedSubject ?>">
                    <i class="bi bi-award me-1"></i> รายงานผลคะแนนและเกรด
                </a>
            </li>
        </ul>
    </div>

    <!-- Filter Bar -->
    <div class="card-body bg-light bg-opacity-50 border-bottom py-3">
        <form action="<?= BASE_URL ?>teacher/reports.php" method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="type" value="<?= htmlspecialchars($reportType) ?>">

            <div class="col-md-4 col-12">
                <label class="form-label small mb-1">เลือกรายวิชา</label>
                <select name="subject_id" class="form-select form-select-sm">
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $selectedSubject == $s['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['subject_code']) ?> <?= htmlspecialchars($s['name_th']) ?> (<?= htmlspecialchars($s['classroom_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($reportType === 'attendance'): ?>
                <div class="col-md-3 col-6">
                    <label class="form-label small mb-1">ตั้งแต่วันที่</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div class="col-md-3 col-6">
                    <label class="form-label small mb-1">ถึงวันที่</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($endDate) ?>">
                </div>
            <?php endif; ?>

            <div class="col-md-2 col-12">
                <button type="submit" class="btn btn-sm btn-primary w-100">ดึงรายงาน</button>
            </div>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0"><i class="bi bi-file-earmark-bar-graph text-primary"></i> <?= htmlspecialchars($reportTitle) ?></h5>
            <div class="small text-muted">พบข้อมูลทั้งหมด <?= count($reportData) ?> รายการ</div>
        </div>
        <div class="d-flex gap-2 no-print">
            <button type="button" class="btn btn-outline-success btn-sm" onclick="exportTableToCSV('tReportTable', 'teacher_report_<?= $reportType ?>.csv')">
                <i class="bi bi-file-earmark-excel me-1"></i> ส่งออก Excel
            </button>
            <button type="button" class="btn btn-dark btn-sm" onclick="printReport()">
                <i class="bi bi-printer me-1"></i> พิมพ์
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0" id="tReportTable">
            <?php if ($reportType === 'grades'): ?>
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>รหัสนักศึกษา</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>วิชา</th>
                        <th class="text-center">งาน (40)</th>
                        <th class="text-center">กลางภาค (30)</th>
                        <th class="text-center">ปลายภาค (30)</th>
                        <th class="text-center fw-bold">รวม (100)</th>
                        <th class="text-center">เกรด</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">ไม่พบข้อมูลคะแนนตามเงื่อนไข</td></tr>
                    <?php else: ?>
                        <?php foreach ($reportData as $idx => $r): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($r['student_code']) ?></td>
                                <td><?= htmlspecialchars($r['full_name']) ?></td>
                                <td><?= htmlspecialchars($r['subject_code']) ?> <?= htmlspecialchars($r['subject_name']) ?></td>
                                <td class="text-center"><?= number_format($r['assignment_score'], 1) ?></td>
                                <td class="text-center"><?= number_format($r['midterm_score'], 1) ?></td>
                                <td class="text-center"><?= number_format($r['final_score'], 1) ?></td>
                                <td class="text-center fw-bold text-primary"><?= number_format($r['total_score'], 1) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-dark px-2.5 py-1"><?= htmlspecialchars($r['grade']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            <?php else: ?>
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>รหัสนักศึกษา</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>ห้องเรียน</th>
                        <th class="text-center text-success">มาเรียน</th>
                        <th class="text-center text-warning-emphasis">สาย</th>
                        <th class="text-center text-info">ลา</th>
                        <th class="text-center text-danger">ขาดเรียน</th>
                        <th class="text-center">รวมครั้ง</th>
                        <th class="text-center">% การมาเรียน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData)): ?>
                        <tr><td colspan="10" class="text-center py-4 text-muted">ไม่พบข้อมูลการเข้าเรียนตามเงื่อนไข</td></tr>
                    <?php else: ?>
                        <?php foreach ($reportData as $idx => $r): 
                            $total = (int)$r['total_checks'];
                            $present = (int)$r['present_count'];
                            $pct = $total > 0 ? round(($present / $total) * 100, 1) : 0;
                        ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($r['student_code']) ?></td>
                                <td><?= htmlspecialchars($r['full_name']) ?></td>
                                <td><?= htmlspecialchars($r['classroom_name']) ?></td>
                                <td class="text-center fw-bold text-success"><?= $present ?></td>
                                <td class="text-center fw-bold text-warning"><?= $r['late_count'] ?></td>
                                <td class="text-center fw-bold text-info"><?= $r['leave_count'] ?></td>
                                <td class="text-center fw-bold text-danger"><?= $r['absent_count'] ?></td>
                                <td class="text-center"><?= $total ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $pct >= 80 ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' ?>">
                                        <?= $pct ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
