<?php
/**
 * Admin - Comprehensive Reports
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$pageTitle = 'ศูนย์รายงานภาพรวม';

// ดึงตัวเลือกสำหรับ Filter
$classrooms = $pdo->query("SELECT id, name, level FROM classrooms ORDER BY level ASC, name ASC")->fetchAll();
$subjects = $pdo->query("SELECT id, subject_code, name_th FROM subjects ORDER BY subject_code ASC")->fetchAll();

// พารามิเตอร์ Filter
$reportType = trim($_GET['type'] ?? 'attendance');
$selectedClassroom = !empty($_GET['classroom_id']) ? (int)$_GET['classroom_id'] : 0;
$selectedSubject = !empty($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$startDate = trim($_GET['start_date'] ?? date('Y-m-01'));
$endDate = trim($_GET['end_date'] ?? date('Y-m-d'));

$reportData = [];
$reportTitle = '';

try {
    if ($reportType === 'lineup') {
        // รายงานการเข้าแถว QR Code
        $reportTitle = 'รายงานการเข้าแถวด้วย QR Code (Line-up Attendance)';
        $sql = "
            SELECT 
                ls.session_date,
                st.student_code,
                u.full_name,
                c.name as classroom_name,
                la.check_in_time,
                la.status as lineup_status
            FROM students st
            JOIN users u ON st.user_id = u.id
            JOIN classrooms c ON st.classroom_id = c.id
            CROSS JOIN lineup_sessions ls
            LEFT JOIN lineup_attendance la ON la.lineup_session_id = ls.id AND la.student_id = st.id
            WHERE ls.session_date BETWEEN ? AND ?
        ";
        $params = [$startDate, $endDate];
        if ($selectedClassroom > 0) {
            $sql .= " AND st.classroom_id = ?";
            $params[] = $selectedClassroom;
        }
        $sql .= " ORDER BY ls.session_date DESC, c.name, st.student_code";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();

    } elseif ($reportType === 'students') {
        // รายงานข้อมูลนักเรียน
        $reportTitle = 'รายงานรายชื่อและข้อมูลนักศึกษา';
        $sql = "
            SELECT s.*, u.full_name, u.email, u.phone, u.status, c.name as classroom_name, c.level
            FROM students s
            JOIN users u ON s.user_id = u.id
            JOIN classrooms c ON s.classroom_id = c.id
            WHERE 1=1
        ";
        $params = [];
        if ($selectedClassroom > 0) {
            $sql .= " AND s.classroom_id = ?";
            $params[] = $selectedClassroom;
        }
        $sql .= " ORDER BY c.level ASC, s.student_code ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();

    } elseif ($reportType === 'grades') {
        // รายงานคะแนนและเกรด
        $reportTitle = 'รายงานสรุปผลการเรียนและระดับคะแนน (Grades)';
        $sql = "
            SELECT sc.*, s.student_code, u.full_name, c.name as classroom_name, sub.subject_code, sub.name_th as subject_name
            FROM scores sc
            JOIN students s ON sc.student_id = s.id
            JOIN users u ON s.user_id = u.id
            JOIN classrooms c ON s.classroom_id = c.id
            JOIN subjects sub ON sc.subject_id = sub.id
            WHERE 1=1
        ";
        $params = [];
        if ($selectedClassroom > 0) {
            $sql .= " AND s.classroom_id = ?";
            $params[] = $selectedClassroom;
        }
        if ($selectedSubject > 0) {
            $sql .= " AND sc.subject_id = ?";
            $params[] = $selectedSubject;
        }
        $sql .= " ORDER BY sub.subject_code ASC, s.student_code ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();

    } elseif ($reportType === 'submissions') {
        // รายงานการส่งงาน
        $reportTitle = 'รายงานสรุปการส่งงานและการบ้าน';
        $sql = "
            SELECT a.title as assignment_title, a.due_date, a.max_score,
                   sub.subject_code, sub.name_th as subject_name,
                   s.student_code, u.full_name,
                   sm.submitted_at, sm.score, sm.status as submission_status
            FROM assignments a
            JOIN subjects sub ON a.subject_id = sub.id
            JOIN classrooms c ON a.classroom_id = c.id
            JOIN students s ON s.classroom_id = c.id
            JOIN users u ON s.user_id = u.id
            LEFT JOIN submissions sm ON sm.assignment_id = a.id AND sm.student_id = s.id
            WHERE 1=1
        ";
        $params = [];
        if ($selectedClassroom > 0) {
            $sql .= " AND a.classroom_id = ?";
            $params[] = $selectedClassroom;
        }
        if ($selectedSubject > 0) {
            $sql .= " AND a.subject_id = ?";
            $params[] = $selectedSubject;
        }
        $sql .= " ORDER BY a.due_date DESC, s.student_code ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reportData = $stmt->fetchAll();

    } else {
        // รายงานการเข้าเรียน (Default)
        $reportType = 'attendance';
        $reportTitle = 'รายงานสถิติการเข้าเรียน (Attendance Summary)';
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
            LEFT JOIN attendance a ON s.id = a.student_id 
                 AND (a.attendance_date BETWEEN ? AND ?)
                 " . ($selectedSubject > 0 ? " AND a.subject_id = {$selectedSubject}" : "") . "
            WHERE 1=1
        ";
        $params = [$startDate, $endDate];
        if ($selectedClassroom > 0) {
            $sql .= " AND s.classroom_id = ?";
            $params[] = $selectedClassroom;
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
    <h3 style="margin: 0; font-size: 16pt;"><?= APP_NAME ?> - <?= APP_SUBTITLE ?></h3>
    <h4 style="margin: 5px 0 15px 0; font-size: 13pt;"><?= htmlspecialchars($reportTitle) ?></h4>
    <div style="font-size: 10pt; color: #555;">ออกรายงานเมื่อ: <?= formatThaiDateTime(date('Y-m-d H:i:s')) ?></div>
    <hr style="margin: 15px 0;">
</div>

<div class="card no-print">
    <div class="card-header border-bottom-0 pb-0">
        <!-- Report Type Tabs -->
        <ul class="nav nav-tabs card-header-tabs">
            <li class="nav-item">
                <a class="nav-link <?= $reportType === 'attendance' ? 'active fw-bold' : '' ?>" href="<?= BASE_URL ?>admin/reports.php?type=attendance">
                    <i class="bi bi-calendar-check me-1"></i> รายงานการเข้าเรียน
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $reportType === 'lineup' ? 'active fw-bold' : '' ?>" href="<?= BASE_URL ?>admin/reports.php?type=lineup">
                    <i class="bi bi-qr-code-scan me-1"></i> รายงานการเข้าแถว (Line-up)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $reportType === 'grades' ? 'active fw-bold' : '' ?>" href="<?= BASE_URL ?>admin/reports.php?type=grades">
                    <i class="bi bi-award me-1"></i> รายงานผลการเรียน (เกรด)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $reportType === 'submissions' ? 'active fw-bold' : '' ?>" href="<?= BASE_URL ?>admin/reports.php?type=submissions">
                    <i class="bi bi-clipboard-data me-1"></i> รายงานการส่งงาน
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $reportType === 'students' ? 'active fw-bold' : '' ?>" href="<?= BASE_URL ?>admin/reports.php?type=students">
                    <i class="bi bi-person-lines-fill me-1"></i> ทะเบียนนักศึกษา
                </a>
            </li>
        </ul>
    </div>

    <!-- Filter Bar -->
    <div class="card-body bg-light bg-opacity-50 border-bottom py-3">
        <form action="<?= BASE_URL ?>admin/reports.php" method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="type" value="<?= htmlspecialchars($reportType) ?>">

            <div class="col-md-3 col-6">
                <label class="form-label small mb-1">ห้องเรียน / กลุ่มเรียน</label>
                <select name="classroom_id" class="form-select form-select-sm">
                    <option value="">-- ทุกห้องเรียน --</option>
                    <?php foreach ($classrooms as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $selectedClassroom == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($reportType !== 'students'): ?>
                <div class="col-md-3 col-6">
                    <label class="form-label small mb-1">รายวิชา</label>
                    <select name="subject_id" class="form-select form-select-sm">
                        <option value="">-- ทุกรายวิชา --</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $selectedSubject == $s['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['subject_code']) ?> <?= htmlspecialchars($s['name_th']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <?php if ($reportType === 'attendance' || $reportType === 'lineup'): ?>
                <div class="col-md-2 col-6">
                    <label class="form-label small mb-1">ตั้งแต่วันที่</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div class="col-md-2 col-6">
                    <label class="form-label small mb-1">ถึงวันที่</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($endDate) ?>">
                </div>
            <?php endif; ?>

            <div class="col-md-2 col-12 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                    <i class="bi bi-filter"></i> ดึงรายงาน
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Report Content Card -->
<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title mb-0"><i class="bi bi-file-earmark-bar-graph text-primary"></i> <?= htmlspecialchars($reportTitle) ?></h5>
            <div class="small text-muted">พบข้อมูลทั้งหมด <?= count($reportData) ?> รายการ</div>
        </div>
        <div class="d-flex gap-2 no-print">
            <button type="button" class="btn btn-outline-success btn-sm" onclick="exportTableToCSV('reportTable', 'report_<?= $reportType ?>_<?= date('Ymd') ?>.csv')">
                <i class="bi bi-file-earmark-excel me-1"></i> ส่งออก Excel (CSV)
            </button>
            <button type="button" class="btn btn-dark btn-sm" onclick="printReport()">
                <i class="bi bi-printer me-1"></i> พิมพ์ / PDF
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0" id="reportTable">
            <?php if ($reportType === 'lineup'): ?>
                <!-- 1.5 Line-up Attendance Table -->
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>วันที่เข้าแถว</th>
                        <th>รหัสนักศึกษา</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>ห้องเรียน</th>
                        <th>เวลาเช็กชื่อ</th>
                        <th class="text-center">สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">ไม่พบข้อมูลการเข้าแถวตามเงื่อนไข</td></tr>
                    <?php else: ?>
                        <?php foreach ($reportData as $idx => $r): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td><?= formatThaiDate($r['session_date']) ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($r['student_code']) ?></td>
                                <td><?= htmlspecialchars($r['full_name']) ?></td>
                                <td><?= htmlspecialchars($r['classroom_name']) ?></td>
                                <td><?= !empty($r['check_in_time']) ? date('H:i:s', strtotime($r['check_in_time'])) . ' น.' : '-' ?></td>
                                <td class="text-center">
                                    <?php
                                    if (empty($r['lineup_status'])) {
                                        echo '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 rounded-pill">ขาดเข้าแถว</span>';
                                    } elseif ($r['lineup_status'] === 'on_time') {
                                        echo '<span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill">ตรงเวลา</span>';
                                    } else {
                                        echo '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1 rounded-pill">สาย</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>

            <?php elseif ($reportType === 'attendance'): ?>
                <!-- 1. Attendance Summary Table -->
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

            <?php elseif ($reportType === 'grades'): ?>
                <!-- 2. Grades Report Table -->
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
                                    <span class="badge bg-dark-subtle text-dark fw-bold px-2 py-1"><?= htmlspecialchars($r['grade']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>

            <?php elseif ($reportType === 'submissions'): ?>
                <!-- 3. Assignment Submissions Report Table -->
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>รหัสนักศึกษา</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>ชื่องาน / วิชา</th>
                        <th>วันครบกำหนด</th>
                        <th>วันที่ส่ง</th>
                        <th class="text-center">สถานะ</th>
                        <th class="text-center">คะแนน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">ไม่พบข้อมูลการส่งงานตามเงื่อนไข</td></tr>
                    <?php else: ?>
                        <?php foreach ($reportData as $idx => $r): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($r['student_code']) ?></td>
                                <td><?= htmlspecialchars($r['full_name']) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($r['assignment_title']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($r['subject_name']) ?></small>
                                </td>
                                <td class="small"><?= formatThaiDate($r['due_date'], true, true) ?></td>
                                <td class="small"><?= !empty($r['submitted_at']) ? formatThaiDate($r['submitted_at'], true, true) : '-' ?></td>
                                <td class="text-center"><?= getSubmissionBadge($r['submission_status'], $r['due_date']) ?></td>
                                <td class="text-center fw-semibold">
                                    <?= $r['score'] !== null ? number_format($r['score'], 1) . '/' . number_format($r['max_score'], 0) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>

            <?php else: ?>
                <!-- 4. Students Directory Table -->
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>รหัสนักศึกษา</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>ห้องเรียน / ชั้นปี</th>
                        <th>เพศ</th>
                        <th>เบอร์โทรศัพท์</th>
                        <th>เบอร์ผู้ปกครอง</th>
                        <th>สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportData)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">ไม่พบข้อมูลนักศึกษาตามเงื่อนไข</td></tr>
                    <?php else: ?>
                        <?php foreach ($reportData as $idx => $r): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td class="fw-semibold"><?= htmlspecialchars($r['student_code']) ?></td>
                                <td><?= htmlspecialchars($r['full_name']) ?></td>
                                <td><?= htmlspecialchars($r['classroom_name']) ?> (<?= htmlspecialchars($r['level']) ?>)</td>
                                <td><?= $r['gender'] === 'female' ? 'หญิง' : 'ชาย' ?></td>
                                <td class="small"><?= htmlspecialchars($r['phone'] ?? '-') ?></td>
                                <td class="small"><?= htmlspecialchars($r['parent_phone'] ?? '-') ?></td>
                                <td>
                                    <span class="badge <?= $r['status'] === 'active' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?>">
                                        <?= $r['status'] === 'active' ? 'กำลังศึกษา' : 'พ้นสภาพ' ?>
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
