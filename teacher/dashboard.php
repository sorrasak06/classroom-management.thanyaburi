<?php
/**
 * Teacher Dashboard
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('teacher');

$pageTitle = 'แดชบอร์ดครูผู้สอน';
$currentUser = getCurrentUser();
$teacherId = $currentUser['role_id'] ?? 0;

try {
    // 1. ดึงจำนวนนักเรียนทั้งหมดในห้องที่ครูสอน
    $stmtStdCount = $pdo->prepare("
        SELECT COUNT(DISTINCT s.id) 
        FROM students s 
        JOIN subjects sub ON s.classroom_id = sub.classroom_id 
        WHERE sub.teacher_id = ?
    ");
    $stmtStdCount->execute([$teacherId]);
    $totalStudents = (int)$stmtStdCount->fetchColumn();

    // 2. ดึงจำนวนงานที่ต้องตรวจ (submissions ที่ status = 'submitted')
    $stmtPending = $pdo->prepare("
        SELECT COUNT(*) 
        FROM submissions sm 
        JOIN assignments a ON sm.assignment_id = a.id 
        WHERE a.teacher_id = ? AND sm.status = 'submitted'
    ");
    $stmtPending->execute([$teacherId]);
    $pendingGrading = (int)$stmtPending->fetchColumn();

    // 3. ดึงวิชาทั้งหมดที่ครูสอน
    $stmtSub = $pdo->prepare("
        SELECT s.*, c.name as classroom_name, COUNT(DISTINCT st.id) as student_count
        FROM subjects s 
        JOIN classrooms c ON s.classroom_id = c.id
        LEFT JOIN students st ON st.classroom_id = c.id
        WHERE s.teacher_id = ?
        GROUP BY s.id
    ");
    $stmtSub->execute([$teacherId]);
    $mySubjects = $stmtSub->fetchAll();

    // 4. สถิติการเช็กชื่อของวันนี้
    $today = date('Y-m-d');
    $stmtTodayAtt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN a.status = 'leave' THEN 1 ELSE 0 END) as leave_count,
            COUNT(*) as total_today
        FROM attendance a
        WHERE a.recorded_by = ? AND a.attendance_date = ?
    ");
    $stmtTodayAtt->execute([$currentUser['id'], $today]);
    $todayStats = $stmtTodayAtt->fetch();

    // สถิติการเข้าแถววันนี้
    $stmtLineupToday = $pdo->query("
        SELECT 
            ls.id, ls.status as session_status,
            COUNT(la.id) as attended_count,
            SUM(CASE WHEN la.status = 'on_time' THEN 1 ELSE 0 END) as on_time_count,
            SUM(CASE WHEN la.status = 'late' THEN 1 ELSE 0 END) as late_count
        FROM lineup_sessions ls
        LEFT JOIN lineup_attendance la ON ls.id = la.lineup_session_id
        WHERE ls.session_date = CURDATE()
        GROUP BY ls.id
        LIMIT 1
    ");
    $lineupToday = $stmtLineupToday->fetch();

    // 5. งานและการบ้านที่ใกล้ครบกำหนด
    $stmtAssign = $pdo->prepare("
        SELECT a.*, s.subject_code, s.name_th as subject_name, c.name as classroom_name,
               (SELECT COUNT(*) FROM submissions sm WHERE sm.assignment_id = a.id) as submitted_count,
               (SELECT COUNT(*) FROM students st WHERE st.classroom_id = a.classroom_id) as total_students
        FROM assignments a
        JOIN subjects s ON a.subject_id = s.id
        JOIN classrooms c ON a.classroom_id = c.id
        WHERE a.teacher_id = ?
        ORDER BY a.due_date DESC
        LIMIT 5
    ");
    $stmtAssign->execute([$teacherId]);
    $recentAssignments = $stmtAssign->fetchAll();

    // 6. ประกาศล่าสุด
    $announcements = $pdo->query("
        SELECT a.*, u.full_name as author_name 
        FROM announcements a 
        LEFT JOIN users u ON a.author_id = u.id 
        WHERE a.target_role IN ('all', 'teacher')
        ORDER BY a.is_pinned DESC, a.created_at DESC 
        LIMIT 4
    ")->fetchAll();

} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<!-- KPI Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-value"><?= number_format($totalStudents) ?></div>
                <div class="stat-label">นักศึกษาในความรับผิดชอบ</div>
            </div>
            <div class="stat-icon primary">
                <i class="bi bi-people-fill"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-value text-danger"><?= number_format($pendingGrading) ?></div>
                <div class="stat-label">การบ้านรอการตรวจ</div>
            </div>
            <div class="stat-icon danger">
                <i class="bi bi-clipboard-check-fill"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-value text-success"><?= $todayStats['present_count'] ?? 0 ?></div>
                <div class="stat-label">เช็กชื่อมาเรียนวันนี้</div>
            </div>
            <div class="stat-icon success">
                <i class="bi bi-person-check-fill"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-value"><?= count($mySubjects) ?></div>
                <div class="stat-label">รายวิชาที่รับผิดชอบ</div>
            </div>
            <div class="stat-icon warning">
                <i class="bi bi-journal-bookmark-fill"></i>
            </div>
        </div>
    </div>
</div>

<!-- Main Section: Today Check-in & My Subjects -->
<div class="row g-4 mb-4">
    <!-- Today Attendance Quick Action & Stats -->
    <div class="col-lg-7">
        <div class="card h-100 mb-0">
            <div class="card-header">
                <h5 class="card-title"><i class="bi bi-calendar-check-fill text-primary"></i> สถานะการเช็กชื่อวันนี้ (<?= formatThaiDate(date('Y-m-d')) ?>)</h5>
                <a href="<?= BASE_URL ?>teacher/attendance.php" class="btn btn-sm btn-primary rounded-pill">
                    <i class="bi bi-check-circle me-1"></i> ทำการเช็กชื่อทันที
                </a>
            </div>
            <div class="card-body">
                <div class="row g-3 text-center mb-4">
                    <div class="col-3">
                        <div class="p-3 bg-success-subtle rounded-3 border border-success-subtle">
                            <div class="fs-4 fw-bold text-success"><?= $todayStats['present_count'] ?? 0 ?></div>
                            <div class="small text-success fw-medium">มาเรียน</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-3 bg-danger-subtle rounded-3 border border-danger-subtle">
                            <div class="fs-4 fw-bold text-danger"><?= $todayStats['absent_count'] ?? 0 ?></div>
                            <div class="small text-danger fw-medium">ขาดเรียน</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-3 bg-warning-subtle rounded-3 border border-warning-subtle">
                            <div class="fs-4 fw-bold text-warning-emphasis"><?= $todayStats['late_count'] ?? 0 ?></div>
                            <div class="small text-warning-emphasis fw-medium">สาย</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-3 bg-info-subtle rounded-3 border border-info-subtle">
                            <div class="fs-4 fw-bold text-info-emphasis"><?= $todayStats['leave_count'] ?? 0 ?></div>
                            <div class="small text-info-emphasis fw-medium">ลา</div>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <!-- Line-up Quick Action & Stats -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-qr-code-scan text-primary me-2"></i> การเช็กชื่อเข้าแถววันนี้</h6>
                    <a href="<?= BASE_URL ?>teacher/lineup-attendance.php" class="btn btn-sm btn-primary rounded-pill">
                        <i class="bi bi-qr-code me-1"></i> เปิด QR เช็กชื่อเข้าแถว
                    </a>
                </div>
                <div class="p-3 bg-light rounded-3 border mb-4">
                    <div class="row text-center align-items-center">
                        <div class="col-4 border-end">
                            <div class="small text-muted mb-1">สแกนเข้าแถวแล้ว</div>
                            <div class="fs-4 fw-bold text-primary"><?= (int)($lineupToday['attended_count'] ?? 0) ?> คน</div>
                        </div>
                        <div class="col-4 border-end">
                            <div class="small text-muted mb-1">ตรงเวลา</div>
                            <div class="fs-4 fw-bold text-success"><?= (int)($lineupToday['on_time_count'] ?? 0) ?> คน</div>
                        </div>
                        <div class="col-4">
                            <div class="small text-muted mb-1">สาย</div>
                            <div class="fs-4 fw-bold text-warning-emphasis"><?= (int)($lineupToday['late_count'] ?? 0) ?> คน</div>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold text-dark mb-3">รายวิชาที่สอนในภาคเรียนนี้:</h6>
                <div class="list-group list-group-flush">
                    <?php if (empty($mySubjects)): ?>
                        <div class="text-muted small">ยังไม่มีรายวิชาที่ได้รับมอบหมาย</div>
                    <?php else: ?>
                        <?php foreach ($mySubjects as $sub): ?>
                            <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold text-dark"><?= htmlspecialchars($sub['subject_code']) ?> <?= htmlspecialchars($sub['name_th']) ?></div>
                                    <div class="small text-muted">ห้องเรียน: <?= htmlspecialchars($sub['classroom_name']) ?> &bull; นักศึกษา <?= $sub['student_count'] ?> คน</div>
                                </div>
                                <div class="d-flex gap-1.5">
                                    <a href="<?= BASE_URL ?>teacher/attendance.php?subject_id=<?= $sub['id'] ?>" class="btn btn-sm btn-outline-primary" title="เช็กชื่อ">
                                        <i class="bi bi-calendar-check"></i> เช็กชื่อ
                                    </a>
                                    <a href="<?= BASE_URL ?>teacher/scores.php?subject_id=<?= $sub['id'] ?>" class="btn btn-sm btn-outline-success" title="บันทึกคะแนน">
                                        <i class="bi bi-award"></i> คะแนน
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Shortcuts & Pending Grading -->
    <div class="col-lg-5">
        <div class="card h-100 mb-0">
            <div class="card-header">
                <h5 class="card-title"><i class="bi bi-clipboard-data-fill text-warning"></i> รายการงานและการบ้านล่าสุด</h5>
                <a href="<?= BASE_URL ?>teacher/assignments.php" class="btn btn-sm btn-outline-primary rounded-pill">จัดการงาน</a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if (empty($recentAssignments)): ?>
                        <div class="text-center text-muted py-4">ยังไม่มีงานที่มอบหมาย</div>
                    <?php else: ?>
                        <?php foreach ($recentAssignments as $a): ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <h6 class="mb-0 fw-semibold text-dark">
                                        <a href="<?= BASE_URL ?>teacher/assignment-submissions.php?id=<?= $a['id'] ?>" class="text-dark hover-primary">
                                            <?= htmlspecialchars($a['title']) ?>
                                        </a>
                                    </h6>
                                    <span class="badge bg-primary-subtle text-primary ms-2"><?= number_format($a['max_score'], 0) ?> คะแนน</span>
                                </div>
                                <div class="small text-muted mb-2">
                                    <?= htmlspecialchars($a['subject_name']) ?> (<?= htmlspecialchars($a['classroom_name']) ?>)
                                </div>
                                <div class="d-flex justify-content-between align-items-center small">
                                    <span class="text-muted"><i class="bi bi-clock me-1"></i>ส่งภายใน: <?= formatThaiDate($a['due_date'], true, true) ?></span>
                                    <span class="badge bg-secondary-subtle text-dark">
                                        ส่งแล้ว <?= $a['submitted_count'] ?>/<?= $a['total_students'] ?> คน
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Announcements -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title"><i class="bi bi-bell-fill text-primary"></i> ข่าวสารและประกาศจากสถาบัน</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($announcements as $ann): ?>
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded-3 border h-100">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-semibold text-dark"><?= htmlspecialchars($ann['title']) ?></span>
                            <small class="text-muted"><?= formatThaiDate($ann['created_at'], false, true) ?></small>
                        </div>
                        <p class="small text-muted mb-0"><?= htmlspecialchars(mb_substr($ann['content'], 0, 140)) ?>...</p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
