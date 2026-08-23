<?php
/**
 * Teacher - Attendance Check Management
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('teacher');

$pageTitle = 'ระบบเช็กชื่อเข้าเรียน';
$currentUser = getCurrentUser();
$teacherId = $currentUser['role_id'] ?? 0;

// ดึงวิชาทั้งหมดที่ครูคนนี้สอน
$stmtSubs = $pdo->prepare("
    SELECT s.*, c.name as classroom_name, c.level 
    FROM subjects s 
    JOIN classrooms c ON s.classroom_id = c.id 
    WHERE s.teacher_id = ? 
    ORDER BY s.subject_code ASC
");
$stmtSubs->execute([$teacherId]);
$subjects = $stmtSubs->fetchAll();

// กำหนดวิชาเริ่มต้น
$selectedSubjectId = !empty($_GET['subject_id']) ? (int)$_GET['subject_id'] : ($subjects[0]['id'] ?? 0);
$selectedDate = !empty($_GET['date']) ? trim($_GET['date']) : date('Y-m-d');

// ดึงข้อมูลวิชาที่เลือก
$currentSubject = null;
foreach ($subjects as $s) {
    if ($s['id'] === $selectedSubjectId) {
        $currentSubject = $s;
        break;
    }
}

$students = [];
if ($currentSubject) {
    // ดึงนักเรียนในห้องนั้น พร้อมสถานะการเช็กชื่อของวันที่เลือก (ถ้ามี)
    $stmtStudents = $pdo->prepare("
        SELECT s.id as student_id, s.student_code, u.full_name, u.avatar,
               a.status as recorded_status, a.remark as recorded_remark
        FROM students s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN attendance a ON s.id = a.student_id 
             AND a.subject_id = ? 
             AND a.attendance_date = ?
        WHERE s.classroom_id = ? AND u.status = 'active'
        ORDER BY s.student_code ASC
    ");
    $stmtStudents->execute([$selectedSubjectId, $selectedDate, $currentSubject['classroom_id']]);
    $students = $stmtStudents->fetchAll();
}

// ประมวลผลบันทึกการเช็กชื่อ (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_attendance') {
    verifyCsrfOrDie();

    $postSubjectId = (int)$_POST['subject_id'];
    $postClassroomId = (int)$_POST['classroom_id'];
    $postDate = trim($_POST['attendance_date']);
    $attendanceData = $_POST['attendance'] ?? []; // student_id => status
    $remarks = $_POST['remark'] ?? []; // student_id => text

    if ($postSubjectId <= 0 || empty($postDate) || empty($attendanceData)) {
        setFlash('error', 'ข้อมูลการเช็กชื่อไม่ครบถ้วน');
    } else {
        try {
            $pdo->beginTransaction();

            $stmtSave = $pdo->prepare("
                INSERT INTO attendance (student_id, subject_id, classroom_id, attendance_date, status, remark, recorded_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE status = VALUES(status), remark = VALUES(remark), recorded_by = VALUES(recorded_by), updated_at = NOW()
            ");

            $countSaved = 0;
            foreach ($attendanceData as $sId => $status) {
                $rem = trim($remarks[$sId] ?? '');
                $stmtSave->execute([
                    (int)$sId,
                    $postSubjectId,
                    $postClassroomId,
                    $postDate,
                    $status,
                    $rem,
                    $currentUser['id']
                ]);
                $countSaved++;
            }

            $pdo->commit();
            setFlash('success', "บันทึกการเช็กชื่อประจำวันที่ " . formatThaiDate($postDate) . " เรียบร้อยแล้ว (จำนวน {$countSaved} คน)");
            header("Location: " . BASE_URL . "teacher/attendance.php?subject_id={$postSubjectId}&date={$postDate}");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            setFlash('error', 'เกิดข้อผิดพลาดในการบันทึก: ' . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="card">
    <div class="card-header flex-column flex-md-row gap-3 align-items-start align-items-md-center">
        <div>
            <h5 class="card-title mb-0"><i class="bi bi-calendar-check-fill text-primary"></i> เช็กชื่อเข้าเรียน (Attendance)</h5>
            <div class="small text-muted">บันทึกเวลาเรียนประจำวันสำหรับนักศึกษาในรายวิชาที่สอน</div>
        </div>
        <div>
            <a href="<?= BASE_URL ?>teacher/attendance-history.php<?= $selectedSubjectId > 0 ? '?subject_id='.$selectedSubjectId : '' ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-clock-history me-1"></i> ดูประวัติการเช็กชื่อย้อนหลัง
            </a>
        </div>
    </div>

    <!-- Selection Bar -->
    <div class="card-body bg-light bg-opacity-50 border-bottom py-3">
        <form action="<?= BASE_URL ?>teacher/attendance.php" method="GET" class="row g-2 align-items-end">
            <div class="col-md-5 col-12">
                <label class="form-label small mb-1">เลือกรายวิชาและห้องเรียน</label>
                <select name="subject_id" class="form-select" onchange="this.form.submit()">
                    <?php if (empty($subjects)): ?>
                        <option value="">-- ไม่พบรายวิชาที่สอน --</option>
                    <?php else: ?>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $selectedSubjectId === $s['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['subject_code']) ?> <?= htmlspecialchars($s['name_th']) ?> (<?= htmlspecialchars($s['classroom_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-4 col-8">
                <label class="form-label small mb-1">วันที่เช็กชื่อ</label>
                <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()">
            </div>
            <div class="col-md-3 col-4">
                <button type="submit" class="btn btn-dark w-100">โหลดข้อมูล</button>
            </div>
        </form>
    </div>

    <?php if (!$currentSubject): ?>
        <div class="card-body py-5 text-center text-muted">
            <i class="bi bi-journal-x fs-1 d-block mb-2"></i>
            กรุณาเลือกรายวิชาเพื่อทำการเช็กชื่อ
        </div>
    <?php else: ?>
        <form action="<?= BASE_URL ?>teacher/attendance.php" method="POST">
            <?= getCsrfField() ?>
            <input type="hidden" name="action" value="save_attendance">
            <input type="hidden" name="subject_id" value="<?= $currentSubject['id'] ?>">
            <input type="hidden" name="classroom_id" value="<?= $currentSubject['classroom_id'] ?>">
            <input type="hidden" name="attendance_date" value="<?= htmlspecialchars($selectedDate) ?>">

            <!-- Quick Batch Buttons -->
            <div class="card-body border-bottom py-2.5 bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="small text-muted">
                    วิชา: <strong><?= htmlspecialchars($currentSubject['name_th']) ?></strong> | ห้อง: <strong><?= htmlspecialchars($currentSubject['classroom_name']) ?></strong> | วันที่: <strong><?= formatThaiDate($selectedDate) ?></strong>
                </div>
                <div class="d-flex gap-1.5">
                    <span class="small text-muted align-self-center me-1 d-none d-sm-inline">เลือกด่วน:</span>
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="setAllAttendanceStatus('present')">
                        <i class="bi bi-check-all"></i> มาเรียนทั้งหมด
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="setAllAttendanceStatus('absent')">
                        <i class="bi bi-x"></i> ขาดทั้งหมด
                    </button>
                </div>
            </div>

            <!-- Attendance Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>รหัสประจำตัว</th>
                            <th>ชื่อ-นามสกุล</th>
                            <th style="min-width: 320px;" class="text-center">สถานะการเข้าเรียน</th>
                            <th style="min-width: 200px;">หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-people fs-1 d-block mb-2"></i>
                                    ไม่พบนักศึกษาในห้องเรียนนี้
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $idx => $std): 
                                $currentStatus = $std['recorded_status'] ?? 'present';
                            ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-dark fw-bold"><?= htmlspecialchars($std['student_code']) ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="<?= getUserAvatarUrl($std['avatar'], $std['full_name']) ?>" class="rounded-circle" width="32" height="32" alt="Avatar">
                                            <span class="fw-semibold text-dark"><?= htmlspecialchars($std['full_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group w-100" role="group" aria-label="Attendance Status">
                                            <input type="radio" class="btn-check" name="attendance[<?= $std['student_id'] ?>]" id="pres_<?= $std['student_id'] ?>" value="present" <?= $currentStatus === 'present' ? 'checked' : '' ?>>
                                            <label class="btn btn-outline-success btn-sm py-1.5" for="pres_<?= $std['student_id'] ?>">
                                                <i class="bi bi-check-circle me-1"></i> มา
                                            </label>

                                            <input type="radio" class="btn-check" name="attendance[<?= $std['student_id'] ?>]" id="late_<?= $std['student_id'] ?>" value="late" <?= $currentStatus === 'late' ? 'checked' : '' ?>>
                                            <label class="btn btn-outline-warning btn-sm py-1.5" for="late_<?= $std['student_id'] ?>">
                                                <i class="bi bi-clock-history me-1"></i> สาย
                                            </label>

                                            <input type="radio" class="btn-check" name="attendance[<?= $std['student_id'] ?>]" id="leave_<?= $std['student_id'] ?>" value="leave" <?= $currentStatus === 'leave' ? 'checked' : '' ?>>
                                            <label class="btn btn-outline-info btn-sm py-1.5" for="leave_<?= $std['student_id'] ?>">
                                                <i class="bi bi-envelope-paper me-1"></i> ลา
                                            </label>

                                            <input type="radio" class="btn-check" name="attendance[<?= $std['student_id'] ?>]" id="abs_<?= $std['student_id'] ?>" value="absent" <?= $currentStatus === 'absent' ? 'checked' : '' ?>>
                                            <label class="btn btn-outline-danger btn-sm py-1.5" for="abs_<?= $std['student_id'] ?>">
                                                <i class="bi bi-x-circle me-1"></i> ขาด
                                            </label>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" name="remark[<?= $std['student_id'] ?>]" class="form-control form-control-sm" placeholder="หมายเหตุ (ถ้ามี)" value="<?= htmlspecialchars($std['recorded_remark'] ?? '') ?>">
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($students)): ?>
                <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center">
                    <div class="small text-muted">จำนวนนักศึกษาทั้งหมด <?= count($students) ?> คน</div>
                    <button type="submit" class="btn btn-primary px-4 py-2">
                        <i class="bi bi-save2 me-1"></i> บันทึกผลการเช็กชื่อ
                    </button>
                </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
