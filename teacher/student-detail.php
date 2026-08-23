<?php
/**
 * Teacher - Student Profile Detail
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('teacher');

$studentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($studentId <= 0) {
    setFlash('error', 'ไม่พบข้อมูลนักศึกษา');
    header('Location: ' . BASE_URL . 'teacher/students.php');
    exit;
}

try {
    // ดึงข้อมูลโปรไฟล์นักศึกษา
    $stmtStd = $pdo->prepare("
        SELECT s.*, u.full_name, u.email, u.phone, u.avatar, u.status, u.created_at as registered_at,
               c.name as classroom_name, c.level, c.academic_year
        FROM students s
        JOIN users u ON s.user_id = u.id
        JOIN classrooms c ON s.classroom_id = c.id
        WHERE s.id = ?
    ");
    $stmtStd->execute([$studentId]);
    $student = $stmtStd->fetch();

    if (!$student) {
        setFlash('error', 'ไม่พบข้อมูลนักศึกษาในระบบ');
        header('Location: ' . BASE_URL . 'teacher/students.php');
        exit;
    }

    $pageTitle = 'ประวัตินักศึกษา: ' . $student['full_name'];

    // สถิติการเข้าเรียน
    $stmtAttStats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_days,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
            SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_days
        FROM attendance
        WHERE student_id = ?
    ");
    $stmtAttStats->execute([$studentId]);
    $attStats = $stmtAttStats->fetch();

    $totalDays = (int)($attStats['total_days'] ?? 0);
    $presentDays = (int)($attStats['present_days'] ?? 0);
    $presentRate = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : 0;

    // ประวัติการเช็กชื่อ 10 ครั้งล่าสุด
    $stmtAttLog = $pdo->prepare("
        SELECT a.*, s.subject_code, s.name_th as subject_name, u.full_name as teacher_name
        FROM attendance a
        JOIN subjects s ON a.subject_id = s.id
        JOIN users u ON a.recorded_by = u.id
        WHERE a.student_id = ?
        ORDER BY a.attendance_date DESC
        LIMIT 10
    ");
    $stmtAttLog->execute([$studentId]);
    $attLogs = $stmtAttLog->fetchAll();

    // รายการคะแนนและผลการเรียน
    $stmtScores = $pdo->prepare("
        SELECT sc.*, s.subject_code, s.name_th as subject_name, s.credits
        FROM scores sc
        JOIN subjects s ON sc.subject_id = s.id
        WHERE sc.student_id = ?
        ORDER BY sc.academic_year DESC, sc.term ASC
    ");
    $stmtScores->execute([$studentId]);
    $scores = $stmtScores->fetchAll();

    // งานที่ส่ง
    $stmtSubs = $pdo->prepare("
        SELECT sm.*, a.title as assignment_title, a.max_score, a.due_date,
               s.subject_code, s.name_th as subject_name
        FROM submissions sm
        JOIN assignments a ON sm.assignment_id = a.id
        JOIN subjects s ON a.subject_id = s.id
        WHERE sm.student_id = ?
        ORDER BY sm.submitted_at DESC
    ");
    $stmtSubs->execute([$studentId]);
    $submissions = $stmtSubs->fetchAll();

} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="<?= BASE_URL ?>teacher/students.php" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> กลับหน้ารายชื่อนักศึกษา
    </a>
    <button onclick="printReport()" class="btn btn-sm btn-outline-dark no-print">
        <i class="bi bi-printer me-1"></i> พิมพ์ประวัติ
    </button>
</div>

<div class="row g-4">
    <!-- Left Column: Student Profile Card -->
    <div class="col-lg-4">
        <div class="card mb-4 text-center">
            <div class="card-body p-4">
                <img src="<?= getUserAvatarUrl($student['avatar'], $student['full_name']) ?>" class="rounded-circle shadow-sm mb-3 border border-3 border-primary-subtle" width="110" height="110" alt="Avatar">
                <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($student['full_name']) ?></h5>
                <div class="badge bg-primary-subtle text-primary px-3 py-1.5 rounded-pill mb-3">
                    รหัสนักศึกษา: <?= htmlspecialchars($student['student_code']) ?>
                </div>

                <div class="list-group list-group-flush text-start small border-top pt-2">
                    <div class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">ห้องเรียน / ชั้นปี:</span>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($student['classroom_name']) ?> (<?= htmlspecialchars($student['level']) ?>)</span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">ปีการศึกษา:</span>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($student['academic_year']) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">เพศ:</span>
                        <span><?= $student['gender'] === 'female' ? 'หญิง' : 'ชาย' ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">วันเกิด:</span>
                        <span><?= formatThaiDate($student['birth_date']) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">อีเมล:</span>
                        <span><?= htmlspecialchars($student['email']) ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">เบอร์โทรศัพท์:</span>
                        <span><?= htmlspecialchars($student['phone'] ?? '-') ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between px-0 py-2">
                        <span class="text-muted">เบอร์โทรผู้ปกครอง:</span>
                        <span><?= htmlspecialchars($student['parent_phone'] ?? '-') ?></span>
                    </div>
                    <div class="list-group-item px-0 py-2">
                        <span class="text-muted d-block mb-1">ที่อยู่:</span>
                        <span class="text-dark"><?= htmlspecialchars($student['address'] ?? '-') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Stats Overview -->
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0"><i class="bi bi-pie-chart-fill text-success"></i> สรุปการเข้าเรียน</h6>
            </div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="display-6 fw-bold <?= $presentRate >= 80 ? 'text-success' : 'text-danger' ?>"><?= $presentRate ?>%</div>
                    <div class="small text-muted">อัตราการเข้าเรียนสะสม</div>
                </div>

                <div class="row g-2 text-center small">
                    <div class="col-6">
                        <div class="p-2 bg-success-subtle rounded border border-success-subtle">
                            <div class="fw-bold text-success fs-5"><?= $attStats['present_days'] ?? 0 ?></div>
                            <div>มาเรียน</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 bg-danger-subtle rounded border border-danger-subtle">
                            <div class="fw-bold text-danger fs-5"><?= $attStats['absent_days'] ?? 0 ?></div>
                            <div>ขาดเรียน</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 bg-warning-subtle rounded border border-warning-subtle">
                            <div class="fw-bold text-warning-emphasis fs-5"><?= $attStats['late_days'] ?? 0 ?></div>
                            <div>สาย</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 bg-info-subtle rounded border border-info-subtle">
                            <div class="fw-bold text-info-emphasis fs-5"><?= $attStats['leave_days'] ?? 0 ?></div>
                            <div>ลา</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Scores & Submission & Attendance Log Tabs -->
    <div class="col-lg-8">
        <!-- 1. Academic Scores -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0"><i class="bi bi-award-fill text-primary"></i> ผลการเรียนและคะแนนสะสม</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>รหัสวิชา</th>
                            <th>ชื่อรายวิชา</th>
                            <th class="text-center">งาน (40)</th>
                            <th class="text-center">กลางภาค (30)</th>
                            <th class="text-center">ปลายภาค (30)</th>
                            <th class="text-center fw-bold">รวม (100)</th>
                            <th class="text-center">เกรด</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($scores)): ?>
                            <tr><td colspan="7" class="text-center py-3 text-muted">ยังไม่มีการบันทึกผลการเรียน</td></tr>
                        <?php else: ?>
                            <?php foreach ($scores as $sc): ?>
                                <tr>
                                    <td><span class="badge bg-secondary-subtle text-dark"><?= htmlspecialchars($sc['subject_code']) ?></span></td>
                                    <td><?= htmlspecialchars($sc['subject_name']) ?></td>
                                    <td class="text-center"><?= number_format($sc['assignment_score'], 1) ?></td>
                                    <td class="text-center"><?= number_format($sc['midterm_score'], 1) ?></td>
                                    <td class="text-center"><?= number_format($sc['final_score'], 1) ?></td>
                                    <td class="text-center fw-bold text-primary"><?= number_format($sc['total_score'], 1) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-dark px-2.5 py-1"><?= htmlspecialchars($sc['grade']) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. Assignments Submission History -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0"><i class="bi bi-clipboard-check text-warning"></i> ประวัติการส่งงานและการบ้าน</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ชื่องาน</th>
                            <th>วิชา</th>
                            <th>วันที่ส่ง</th>
                            <th class="text-center">สถานะ</th>
                            <th class="text-center">คะแนน</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($submissions)): ?>
                            <tr><td colspan="5" class="text-center py-3 text-muted">ยังไม่มีประวัติการส่งงาน</td></tr>
                        <?php else: ?>
                            <?php foreach ($submissions as $sm): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($sm['assignment_title']) ?></div>
                                        <?php if (!empty($sm['feedback'])): ?>
                                            <div class="small text-muted fst-italic">ความเห็นครู: <?= htmlspecialchars($sm['feedback']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?= htmlspecialchars($sm['subject_name']) ?></td>
                                    <td class="small text-muted"><?= formatThaiDate($sm['submitted_at'], true, true) ?></td>
                                    <td class="text-center"><?= getSubmissionBadge($sm['status'], $sm['due_date']) ?></td>
                                    <td class="text-center fw-bold text-success">
                                        <?= $sm['score'] !== null ? number_format($sm['score'], 1) . '/' . number_format($sm['max_score'], 0) : '-' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. Recent Attendance History -->
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0"><i class="bi bi-calendar-check text-info"></i> ประวัติการเข้าเรียนล่าสุด</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>วันที่</th>
                            <th>รายวิชา</th>
                            <th>สถานะ</th>
                            <th>หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($attLogs)): ?>
                            <tr><td colspan="4" class="text-center py-3 text-muted">ยังไม่มีประวัติการเช็กชื่อ</td></tr>
                        <?php else: ?>
                            <?php foreach ($attLogs as $log): ?>
                                <tr>
                                    <td class="small fw-medium"><?= formatThaiDate($log['attendance_date']) ?></td>
                                    <td class="small"><?= htmlspecialchars($log['subject_name']) ?></td>
                                    <td><?= getAttendanceBadge($log['status']) ?></td>
                                    <td class="small text-muted"><?= htmlspecialchars($log['remark'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
