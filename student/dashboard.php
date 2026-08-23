<?php
/**
 * Student Dashboard
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('student');

$pageTitle = 'แดชบอร์ดนักศึกษา';
$currentUser = getCurrentUser();
$studentId = $currentUser['role_id'] ?? 0;
$classroomId = $currentUser['classroom_id'] ?? 0;

try {
    // 1. ตารางเรียนวันนี้
    // แปลงวันปัจจุบันใน PHP เป็นตัวเลขวัน (1=Monday, ..., 5=Friday)
    $todayDayOfWeek = (int)date('N'); // 1 (Mon) to 7 (Sun)
    $stmtTodaySchedule = $pdo->prepare("
        SELECT sc.*, s.subject_code, s.name_th as subject_name, u.full_name as teacher_name
        FROM schedules sc
        JOIN subjects s ON sc.subject_id = s.id
        JOIN teachers t ON s.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE sc.classroom_id = ? AND sc.day_of_week = ?
        ORDER BY sc.start_time ASC
    ");
    $stmtTodaySchedule->execute([$classroomId, $todayDayOfWeek]);
    $todayClasses = $stmtTodaySchedule->fetchAll();

    // 2. งานที่ต้องส่ง (ยังไม่ได้ส่ง และยังไม่หมดเขต)
    $stmtPendingTasks = $pdo->prepare("
        SELECT a.*, s.subject_code, s.name_th as subject_name
        FROM assignments a
        JOIN subjects s ON a.subject_id = s.id
        LEFT JOIN submissions sm ON sm.assignment_id = a.id AND sm.student_id = ?
        WHERE a.classroom_id = ? AND sm.id IS NULL
        ORDER BY a.due_date ASC
        LIMIT 5
    ");
    $stmtPendingTasks->execute([$studentId, $classroomId]);
    $pendingTasks = $stmtPendingTasks->fetchAll();

    // 3. งานที่ตรวจแล้วล่าสุด
    $stmtGraded = $pdo->prepare("
        SELECT sm.*, a.title as assignment_title, a.max_score, s.name_th as subject_name
        FROM submissions sm
        JOIN assignments a ON sm.assignment_id = a.id
        JOIN subjects s ON a.subject_id = s.id
        WHERE sm.student_id = ? AND sm.status = 'graded'
        ORDER BY sm.graded_at DESC
        LIMIT 4
    ");
    $stmtGraded->execute([$studentId]);
    $gradedTasks = $stmtGraded->fetchAll();

    // 4. สถิติการเข้าเรียนของตนเอง
    $stmtAtt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
            SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_days
        FROM attendance
        WHERE student_id = ?
    ");
    $stmtAtt->execute([$studentId]);
    $attStats = $stmtAtt->fetch();

    $totalDays = (int)($attStats['total_days'] ?? 0);
    $presentDays = (int)($attStats['present_days'] ?? 0);
    $attRate = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : 100;

    // สถิติการเข้าแถวของตนเอง
    $stmtLineupPersonal = $pdo->prepare("
        SELECT 
            COUNT(la.id) as total_attended,
            SUM(CASE WHEN la.status = 'on_time' THEN 1 ELSE 0 END) as on_time_count,
            SUM(CASE WHEN la.status = 'late' THEN 1 ELSE 0 END) as late_count
        FROM lineup_attendance la
        WHERE la.student_id = ?
    ");
    $stmtLineupPersonal->execute([$studentId]);
    $lineupPersonal = $stmtLineupPersonal->fetch();

    $totalLineupSessions = (int)$pdo->query("SELECT COUNT(*) FROM lineup_sessions")->fetchColumn();
    $myLineupAttended    = (int)($lineupPersonal['total_attended'] ?? 0);
    $myLineupOnTime      = (int)($lineupPersonal['on_time_count'] ?? 0);
    $myLineupLate        = (int)($lineupPersonal['late_count'] ?? 0);
    $myLineupPct         = ($totalLineupSessions > 0) ? round(($myLineupAttended / $totalLineupSessions) * 100, 1) : 0;

    // เช็กสิทธิ์วันนี้
    $stmtTodayPersonalCheck = $pdo->prepare("
        SELECT la.check_in_time, la.status
        FROM lineup_attendance la
        JOIN lineup_sessions ls ON la.lineup_session_id = ls.id
        WHERE la.student_id = ? AND ls.session_date = CURDATE()
        LIMIT 1
    ");
    $stmtTodayPersonalCheck->execute([$studentId]);
    $myTodayLineupCheck = $stmtTodayPersonalCheck->fetch();

    // 5. ประกาศล่าสุด
    $announcements = $pdo->query("
        SELECT a.*, u.full_name as author_name 
        FROM announcements a 
        LEFT JOIN users u ON a.author_id = u.id 
        WHERE a.target_role IN ('all', 'student')
        ORDER BY a.is_pinned DESC, a.created_at DESC 
        LIMIT 3
    ")->fetchAll();

} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<!-- Welcome Banner -->
<div class="card bg-primary text-white border-0 shadow-sm mb-4" style="background: linear-gradient(135deg, #1e40af, #3b82f6) !important;">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <img src="<?= getUserAvatarUrl($currentUser['avatar'], $currentUser['full_name']) ?>" class="rounded-circle border border-2 border-white shadow-sm" width="60" height="60" alt="Avatar">
                <div>
                    <h4 class="fw-bold mb-1">สวัสดี, <?= htmlspecialchars($currentUser['full_name']) ?> 👋</h4>
                    <div class="opacity-75 small">รหัสนักศึกษา: <?= htmlspecialchars($currentUser['code']) ?> &bull; วันนี้วัน<?= getDayThaiName($todayDayOfWeek) ?>ที่ <?= formatThaiDate(date('Y-m-d')) ?></div>
                </div>
            </div>
            <div>
                <a href="<?= BASE_URL ?>student/schedule.php" class="btn btn-light btn-sm text-primary fw-semibold px-3 py-2 rounded-pill">
                    <i class="bi bi-calendar-week me-1"></i> ดูตารางเรียนสัปดาห์นี้
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-value text-danger"><?= count($pendingTasks) ?></div>
                <div class="stat-label">การบ้านที่ต้องส่ง</div>
            </div>
            <div class="stat-icon danger">
                <i class="bi bi-journal-text"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-value text-success"><?= $attRate ?>%</div>
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
                <div class="stat-value"><?= count($todayClasses) ?></div>
                <div class="stat-label">คาบเรียนวันนี้</div>
            </div>
            <div class="stat-icon primary">
                <i class="bi bi-clock-history"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-value text-info"><?= count($gradedTasks) ?></div>
                <div class="stat-label">งานที่ตรวจแล้ว</div>
            </div>
            <div class="stat-icon info">
                <i class="bi bi-award-fill"></i>
            </div>
        </div>
    </div>
</div>

<!-- Line-up Status Alert & Card -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h5 class="fw-bold text-dark mb-1"><i class="bi bi-qr-code-scan text-primary me-2"></i> สถิติการเข้าแถวสะสม</h5>
                <p class="text-muted small mb-0">เข้าแถวแล้ว <?= $myLineupAttended ?> จาก <?= $totalLineupSessions ?> ครั้ง (ตรงเวลา <?= $myLineupOnTime ?> ครั้ง, สาย <?= $myLineupLate ?> ครั้ง)</p>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="text-end">
                    <div class="fs-4 fw-bold text-primary"><?= $myLineupPct ?>%</div>
                    <div class="small text-muted">อัตราการเข้าแถว</div>
                </div>
                <div>
                    <?php if ($myTodayLineupCheck): ?>
                        <a href="<?= BASE_URL ?>student/lineup-attendance.php" class="btn btn-success rounded-pill px-3">
                            <i class="bi bi-check-circle me-1"></i> เช็กชื่อแล้ววันนี้
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>student/lineup-attendance.php" class="btn btn-primary rounded-pill px-3">
                            <i class="bi bi-qr-code-scan me-1"></i> เช็กชื่อเข้าแถววันนี้
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Today's Schedule -->
    <div class="col-lg-6">
        <div class="card h-100 mb-0">
            <div class="card-header">
                <h5 class="card-title"><i class="bi bi-calendar-event-fill text-primary"></i> ตารางเรียนวันนี้ (วัน<?= getDayThaiName($todayDayOfWeek) ?>)</h5>
                <a href="<?= BASE_URL ?>student/schedule.php" class="btn btn-sm btn-outline-primary rounded-pill">ตารางเรียนเต็ม</a>
            </div>
            <div class="card-body">
                <?php if (empty($todayClasses)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-cup-hot fs-1 d-block mb-2 text-secondary"></i>
                        ไม่มีคาบเรียนในวันนี้ พักผ่อนให้เต็มที่!
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2.5">
                        <?php foreach ($todayClasses as $c): ?>
                            <div class="timetable-card p-3">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div class="fw-bold text-dark fs-6"><?= htmlspecialchars($c['subject_code']) ?> <?= htmlspecialchars($c['subject_name']) ?></div>
                                    <span class="badge bg-primary-subtle text-primary">ห้อง <?= htmlspecialchars($c['room_number']) ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center small text-muted">
                                    <div><i class="bi bi-person me-1"></i><?= htmlspecialchars($c['teacher_name']) ?></div>
                                    <div class="fw-semibold text-primary"><i class="bi bi-clock me-1"></i><?= formatTime($c['start_time']) ?> - <?= formatTime($c['end_time']) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Pending Assignments -->
    <div class="col-lg-6">
        <div class="card h-100 mb-0">
            <div class="card-header">
                <h5 class="card-title"><i class="bi bi-alarm-fill text-danger"></i> งานที่ต้องส่ง (ยังไม่ส่ง)</h5>
                <a href="<?= BASE_URL ?>student/assignments.php" class="btn btn-sm btn-outline-primary rounded-pill">ดูงานทั้งหมด</a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if (empty($pendingTasks)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-check-circle fs-1 text-success d-block mb-2"></i>
                            ยอดเยี่ยมมาก! ไม่มีงานค้างส่งในขณะนี้
                        </div>
                    <?php else: ?>
                        <?php foreach ($pendingTasks as $t): 
                            $isOverdue = strtotime($t['due_date']) < time();
                        ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <h6 class="mb-0 fw-semibold text-dark">
                                        <a href="<?= BASE_URL ?>student/assignment-detail.php?id=<?= $t['id'] ?>" class="text-dark hover-primary">
                                            <?= htmlspecialchars($t['title']) ?>
                                        </a>
                                    </h6>
                                    <span class="badge bg-primary-subtle text-primary"><?= number_format($t['max_score'], 0) ?> คะแนน</span>
                                </div>
                                <div class="small text-muted mb-2">วิชา: <?= htmlspecialchars($t['subject_name']) ?></div>
                                <div class="d-flex justify-content-between align-items-center small">
                                    <span class="<?= $isOverdue ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                        <i class="bi bi-clock me-1"></i>กำหนดส่ง: <?= formatThaiDate($t['due_date'], true, true) ?>
                                    </span>
                                    <a href="<?= BASE_URL ?>student/assignment-detail.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-primary py-0.5 px-2">
                                        ส่งงาน <i class="bi bi-arrow-right"></i>
                                    </a>
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
        <h5 class="card-title"><i class="bi bi-megaphone-fill text-danger"></i> ประกาศข่าวสารล่าสุด</h5>
        <a href="<?= BASE_URL ?>student/announcements.php" class="btn btn-sm btn-outline-secondary rounded-pill">ดูทั้งหมด</a>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($announcements as $ann): ?>
                <div class="col-md-4">
                    <div class="p-3 bg-light rounded-3 border h-100">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-danger-subtle text-danger"><i class="bi bi-pin-angle-fill"></i> ประกาศ</span>
                            <small class="text-muted"><?= formatThaiDate($ann['created_at'], false, true) ?></small>
                        </div>
                        <h6 class="fw-bold text-dark mt-2 mb-1"><?= htmlspecialchars($ann['title']) ?></h6>
                        <p class="small text-muted mb-0"><?= htmlspecialchars(mb_substr($ann['content'], 0, 100)) ?>...</p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
