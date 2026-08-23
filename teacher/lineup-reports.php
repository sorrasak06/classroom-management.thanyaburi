<?php
/**
 * Teacher Line-up Attendance Reports
 * หน้าสรุปรายงานการเข้าแถวสำหรับครูผู้สอน
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole(['teacher', 'admin']);

$currentUser = getCurrentUser();
$teacherId   = (int)($currentUser['role_id'] ?? 0);

// ตัวกรอง (GET Filter)
$filterDate     = trim($_GET['date'] ?? '');
$filterMonth    = trim($_GET['month'] ?? '');
$filterClassroom= (int)($_GET['classroom_id'] ?? 0);
$filterStatus   = trim($_GET['status'] ?? '');
$exportCsv      = isset($_GET['export']) && $_GET['export'] === 'csv';

// 1. ดึงรายการห้องเรียนที่ครูรับผิดชอบ
$classroomsList = $pdo->query("SELECT id, name FROM classrooms ORDER BY name ASC")->fetchAll();

// Construct SQL Query
$where = ["1=1"];
$params = [];

if (!empty($filterDate)) {
    $where[] = "ls.session_date = ?";
    $params[] = $filterDate;
}
if (!empty($filterMonth)) {
    $where[] = "DATE_FORMAT(ls.session_date, '%Y-%m') = ?";
    $params[] = $filterMonth;
}
if ($filterClassroom > 0) {
    $where[] = "st.classroom_id = ?";
    $params[] = $filterClassroom;
}
if (!empty($filterStatus)) {
    if ($filterStatus === 'absent') {
        $where[] = "la.id IS NULL";
    } else {
        $where[] = "la.status = ?";
        $params[] = $filterStatus;
    }
}

$whereClause = implode(" AND ", $where);

// Export CSV (UTF-8 BOM สำหรับ Excel ภาษาไทย)
if ($exportCsv) {
    $csvStmt = $pdo->prepare("
        SELECT 
            ls.session_date,
            st.student_code,
            u.full_name,
            c.name as classroom_name,
            la.check_in_time,
            la.status
        FROM students st
        JOIN users u ON st.user_id = u.id
        JOIN classrooms c ON st.classroom_id = c.id
        CROSS JOIN lineup_sessions ls
        LEFT JOIN lineup_attendance la ON la.lineup_session_id = ls.id AND la.student_id = st.id
        WHERE {$whereClause}
        ORDER BY ls.session_date DESC, c.name, st.student_code
    ");
    $csvStmt->execute($params);
    $csvRows = $csvStmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="lineup_report_' . date('Ymd_His') . '.csv"');

    // UTF-8 BOM
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    fputcsv($output, ['วันที่เข้าแถว', 'รหัสนักศึกษา', 'ชื่อ-นามสกุล', 'ห้องเรียน', 'เวลาเช็กชื่อ', 'สถานะ']);

    foreach ($csvRows as $r) {
        $statusStr = 'ขาดเข้าแถว';
        if (!empty($r['status'])) {
            $statusStr = ($r['status'] === 'on_time') ? 'ตรงเวลา' : 'สาย';
        }
        fputcsv($output, [
            $r['session_date'],
            $r['student_code'],
            $r['full_name'],
            $r['classroom_name'],
            !empty($r['check_in_time']) ? date('H:i:s', strtotime($r['check_in_time'])) : '-',
            $statusStr
        ]);
    }
    fclose($output);
    exit;
}

// 2. ดึงข้อมูลรายงานสำหรับแสดงผลบนเว็บ
$stmtReport = $pdo->prepare("
    SELECT 
        ls.session_date,
        st.student_code,
        u.full_name,
        c.name as classroom_name,
        la.check_in_time,
        la.status
    FROM students st
    JOIN users u ON st.user_id = u.id
    JOIN classrooms c ON st.classroom_id = c.id
    CROSS JOIN lineup_sessions ls
    LEFT JOIN lineup_attendance la ON la.lineup_session_id = ls.id AND la.student_id = st.id
    WHERE {$whereClause}
    ORDER BY ls.session_date DESC, c.name, st.student_code
    LIMIT 200
");
$stmtReport->execute($params);
$reportRows = $stmtReport->fetchAll();

// คำนวณสถิติ
$totalRecords = count($reportRows);
$onTimeCount  = 0;
$lateCount    = 0;
$absentCount  = 0;

foreach ($reportRows as $r) {
    if (empty($r['status'])) {
        $absentCount++;
    } elseif ($r['status'] === 'on_time') {
        $onTimeCount++;
    } else {
        $lateCount++;
    }
}
$onTimePct = ($totalRecords > 0) ? round(($onTimeCount / $totalRecords) * 100, 1) : 0;

$pageTitle = 'รายงานสถิติการเข้าแถว';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1"><i class="bi bi-file-earmark-bar-graph text-primary me-2"></i> รายงานการเข้าแถวประจำวัน</h3>
        <p class="text-muted mb-0">ค้นหา กรองข้อมูล และส่งออกรายงานสถิติการเข้าแถวของนักศึกษา</p>
    </div>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-outline-secondary rounded-pill">
            <i class="bi bi-printer me-1"></i> พิมพ์รายงาน
        </button>
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-success rounded-pill">
            <i class="bi bi-file-earmark-excel me-1"></i> Export CSV (Excel)
        </a>
    </div>
</div>

<!-- Filter Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" action="" class="row g-3">
            <div class="col-md-3">
                <label class="form-label small fw-bold">เลือกวันที่เฉพาะ:</label>
                <input type="date" name="date" class="form-control" value="<?= e($filterDate) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">เลือกเดือน:</label>
                <input type="month" name="month" class="form-control" value="<?= e($filterMonth) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">ห้องเรียน:</label>
                <select name="classroom_id" class="form-select">
                    <option value="">-- ทุกห้องเรียน --</option>
                    <?php foreach ($classroomsList as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($filterClassroom == $c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">สถานะ:</label>
                <select name="status" class="form-select">
                    <option value="">-- ทุกสถานะ --</option>
                    <option value="on_time" <?= ($filterStatus === 'on_time') ? 'selected' : '' ?>>ตรงเวลา</option>
                    <option value="late" <?= ($filterStatus === 'late') ? 'selected' : '' ?>>มาสาย</option>
                    <option value="absent" <?= ($filterStatus === 'absent') ? 'selected' : '' ?>>ขาดเข้าแถว</option>
                </select>
            </div>
            <div class="col-12 text-end">
                <a href="<?= BASE_URL ?>teacher/lineup-reports.php" class="btn btn-light rounded-pill me-2">รีเซ็ต</a>
                <button type="submit" class="btn btn-primary rounded-pill px-4"><i class="bi bi-search me-1"></i> ค้นหา</button>
            </div>
        </form>
    </div>
</div>

<!-- Stat KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-value"><?= number_format($totalRecords) ?> รายการ</div>
                <div class="stat-label">ข้อมูลทั้งหมดในรายงาน</div>
            </div>
            <div class="stat-icon primary"><i class="bi bi-list-stars"></i></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-value text-success"><?= number_format($onTimeCount) ?> (<?= $onTimePct ?>%)</div>
                <div class="stat-label">มาตรงเวลา</div>
            </div>
            <div class="stat-icon success"><i class="bi bi-check-circle-fill"></i></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-value text-warning-emphasis"><?= number_format($lateCount) ?> รายการ</div>
                <div class="stat-label">มาสาย</div>
            </div>
            <div class="stat-icon warning"><i class="bi bi-clock-history"></i></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-value text-danger"><?= number_format($absentCount) ?> รายการ</div>
                <div class="stat-label">ขาดเข้าแถว</div>
            </div>
            <div class="stat-icon danger"><i class="bi bi-x-circle-fill"></i></div>
        </div>
    </div>
</div>

<!-- Table Card -->
<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>วันที่</th>
                    <th>รหัสนักศึกษา</th>
                    <th>ชื่อ - นามสกุล</th>
                    <th>ห้องเรียน</th>
                    <th>เวลาเช็กชื่อ</th>
                    <th>สถานะ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reportRows)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">ไม่พบข้อมูลตามเงื่อนไขที่เลือก</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reportRows as $r): ?>
                        <tr>
                            <td class="fw-medium text-dark"><?= formatThaiDate($r['session_date']) ?></td>
                            <td><code><?= e($r['student_code']) ?></code></td>
                            <td><?= e($r['full_name']) ?></td>
                            <td><?= e($r['classroom_name']) ?></td>
                            <td><?= !empty($r['check_in_time']) ? date('H:i:s', strtotime($r['check_in_time'])) . ' น.' : '-' ?></td>
                            <td>
                                <?php
                                if (empty($r['status'])) {
                                    echo '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 rounded-pill">ขาดเข้าแถว</span>';
                                } elseif ($r['status'] === 'on_time') {
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
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
