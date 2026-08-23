<?php
/**
 * Admin - Schedule & Timetable Management
 * จัดการตารางเรียนและตารางสอน
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$pageTitle = 'จัดการตารางเรียน/ตารางสอน';

// ดึงข้อมูลสำหรับตัวเลือก Dropdown
$classrooms = $pdo->query("SELECT id, name, level FROM classrooms ORDER BY level ASC, name ASC")->fetchAll();
$subjects = $pdo->query("
    SELECT s.id, s.subject_code, s.name_th, s.classroom_id, c.name as classroom_name, u.full_name as teacher_name
    FROM subjects s
    JOIN classrooms c ON s.classroom_id = c.id
    JOIN teachers t ON s.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    ORDER BY s.subject_code ASC
")->fetchAll();

$teachers = $pdo->query("
    SELECT t.id, t.teacher_code, u.full_name
    FROM teachers t
    JOIN users u ON t.user_id = u.id
    ORDER BY u.full_name ASC
")->fetchAll();

// 1. เพิ่มคาบเรียนในตาราง
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    verifyCsrfOrDie();

    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $classroom_id = (int)($_POST['classroom_id'] ?? 0);
    $day_of_week = (int)($_POST['day_of_week'] ?? 1);
    $start_time = trim($_POST['start_time'] ?? '');
    $end_time = trim($_POST['end_time'] ?? '');
    $room_number = trim($_POST['room_number'] ?? '');

    if ($subject_id <= 0 || $classroom_id <= 0 || empty($start_time) || empty($end_time) || empty($room_number)) {
        setFlash('error', 'กรุณากรอกข้อมูลคาบเรียนให้ครบถ้วน');
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO schedules (subject_id, classroom_id, day_of_week, start_time, end_time, room_number, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$subject_id, $classroom_id, $day_of_week, $start_time, $end_time, $room_number]);
            setFlash('success', 'เพิ่มตารางเรียนเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            setFlash('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . 'admin/schedules.php?classroom_id=' . $classroom_id);
    exit;
}

// 2. แก้ไขคาบเรียน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    verifyCsrfOrDie();

    $id = (int)$_POST['id'];
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $classroom_id = (int)($_POST['classroom_id'] ?? 0);
    $day_of_week = (int)($_POST['day_of_week'] ?? 1);
    $start_time = trim($_POST['start_time'] ?? '');
    $end_time = trim($_POST['end_time'] ?? '');
    $room_number = trim($_POST['room_number'] ?? '');

    if ($subject_id <= 0 || $classroom_id <= 0 || empty($start_time) || empty($end_time) || empty($room_number)) {
        setFlash('error', 'กรุณากรอกข้อมูลคาบเรียนให้ครบถ้วน');
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE schedules 
                SET subject_id = ?, classroom_id = ?, day_of_week = ?, start_time = ?, end_time = ?, room_number = ?
                WHERE id = ?
            ");
            $stmt->execute([$subject_id, $classroom_id, $day_of_week, $start_time, $end_time, $room_number, $id]);
            setFlash('success', 'แก้ไขตารางเรียนเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            setFlash('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    header('Location: ' . BASE_URL . 'admin/schedules.php?classroom_id=' . $classroom_id);
    exit;
}

// 3. ลบคาบเรียน
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    if (!validateCsrfToken($_GET['csrf_token'] ?? '')) {
        setFlash('error', 'คำขอไม่ถูกต้องหรือหมดอายุ');
    } else {
        $id = (int)$_GET['id'];
        $redirectClass = (int)($_GET['classroom_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("DELETE FROM schedules WHERE id = ?");
            $stmt->execute([$id]);
            setFlash('success', 'ลบคาบเรียนออกจากตารางเรียบร้อยแล้ว');
        } catch (PDOException $e) {
            setFlash('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
    $targetUrl = BASE_URL . 'admin/schedules.php' . ($redirectClass > 0 ? '?classroom_id=' . $redirectClass : '');
    header('Location: ' . $targetUrl);
    exit;
}

// Filter ห้องเรียนที่เลือก
$selectedClassroomId = isset($_GET['classroom_id']) ? (int)$_GET['classroom_id'] : ($classrooms[0]['id'] ?? 0);

// ดึงตารางเรียนตามห้องเรียน
$schedules = [];
if ($selectedClassroomId > 0) {
    $stmtSched = $pdo->prepare("
        SELECT sc.*, s.subject_code, s.name_th as subject_name, s.credits,
               u.full_name as teacher_name, c.name as classroom_name
        FROM schedules sc
        JOIN subjects s ON sc.subject_id = s.id
        JOIN classrooms c ON sc.classroom_id = c.id
        JOIN teachers t ON s.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE sc.classroom_id = ?
        ORDER BY sc.day_of_week ASC, sc.start_time ASC
    ");
    $stmtSched->execute([$selectedClassroomId]);
    $schedules = $stmtSched->fetchAll();
}

// จัดกลุ่มตารางตามวัน (1=จันทร์, ..., 5=ศุกร์)
$schedulesByDay = [1 => [], 2 => [], 3 => [], 4 => [], 5 => []];
foreach ($schedules as $item) {
    $day = (int)$item['day_of_week'];
    if (isset($schedulesByDay[$day])) {
        $schedulesByDay[$day][] = $item;
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h5 class="card-title mb-1"><i class="bi bi-calendar3 text-primary"></i> จัดการตารางเรียนประจำห้องเรียน</h5>
                <p class="text-muted small mb-0">กำหนดเวลาเรียน รายวิชา ครูผู้สอน และห้องเรียนประจำสัปดาห์</p>
            </div>
            
            <div class="d-flex flex-wrap align-items-center gap-2">
                <!-- Dropdown เลือกห้องเรียน -->
                <form method="GET" class="d-flex align-items-center gap-2 m-0">
                    <label for="selectClassroom" class="form-label mb-0 small text-muted text-nowrap">เลือกห้อง:</label>
                    <select name="classroom_id" id="selectClassroom" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 220px;">
                        <?php foreach ($classrooms as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($selectedClassroomId === (int)$c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>

                <button type="button" class="btn btn-sm btn-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                    <i class="bi bi-plus-circle me-1"></i> เพิ่มคาบเรียน
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Visual Weekly Grid Schedule -->
<div class="card mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="bi bi-calendar-week-fill text-primary me-1"></i> ตารางเรียนรายสัปดาห์ (วันจันทร์ - วันศุกร์)
        </h6>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
            <?= count($schedules) ?> คาบเรียนที่ลงทะเบียน
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0 text-center">
                <thead class="table-light">
                    <tr>
                        <th style="width: 120px;">วัน / เวลา</th>
                        <th style="min-width: 180px;">ช่วงเช้า (08:30 - 11:30)</th>
                        <th style="width: 80px;" class="bg-secondary-subtle">พัก</th>
                        <th style="min-width: 180px;">ช่วงบ่าย (13:00 - 16:00)</th>
                        <th style="min-width: 150px;">ช่วงเย็น / พิเศษ (16:00+)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $days = [
                        1 => ['name' => 'จันทร์', 'color' => '#fef08a', 'border' => '#eab308'],
                        2 => ['name' => 'อังคาร', 'color' => '#fbcfe8', 'border' => '#ec4899'],
                        3 => ['name' => 'พุธ', 'color' => '#bbf7d0', 'border' => '#22c55e'],
                        4 => ['name' => 'พฤหัสบดี', 'color' => '#fed7aa', 'border' => '#f97316'],
                        5 => ['name' => 'ศุกร์', 'color' => '#bae6fd', 'border' => '#0ea5e9']
                    ];
                    foreach ($days as $dayNum => $dayInfo):
                        $dayItems = $schedulesByDay[$dayNum] ?? [];
                    ?>
                        <tr>
                            <td class="fw-bold" style="background-color: <?= $dayInfo['color'] ?>33; border-left: 4px solid <?= $dayInfo['border'] ?>;">
                                <div class="fs-6"><?= $dayInfo['name'] ?></div>
                            </td>
                            <td colspan="4" class="p-2 text-start">
                                <?php if (empty($dayItems)): ?>
                                    <span class="text-muted small italic ps-2">ไม่มีการจัดตารางเรียนในวันนี้</span>
                                <?php else: ?>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($dayItems as $item): ?>
                                            <div class="card border shadow-sm p-2.5" style="border-radius: 0.5rem; min-width: 240px; max-width: 320px; border-top: 3px solid <?= $dayInfo['border'] ?> !important;">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <span class="badge bg-light text-dark border font-monospace small"><?= htmlspecialchars($item['subject_code']) ?></span>
                                                    <span class="badge bg-primary text-white small">
                                                        <i class="bi bi-clock me-1"></i><?= date('H:i', strtotime($item['start_time'])) ?> - <?= date('H:i', strtotime($item['end_time'])) ?>
                                                    </span>
                                                </div>
                                                <div class="fw-semibold text-dark mt-1 small text-truncate" title="<?= htmlspecialchars($item['subject_name']) ?>">
                                                    <?= htmlspecialchars($item['subject_name']) ?>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mt-2 pt-1 border-top" style="font-size: 0.75rem;">
                                                    <span class="text-muted"><i class="bi bi-geo-alt-fill text-danger me-1"></i><?= htmlspecialchars($item['room_number']) ?></span>
                                                    <span class="text-muted"><i class="bi bi-person me-1"></i><?= htmlspecialchars($item['teacher_name']) ?></span>
                                                </div>
                                                <div class="mt-2 text-end">
                                                    <button type="button" class="btn btn-xs btn-outline-warning py-0 px-1.5 edit-schedule-btn"
                                                            data-id="<?= $item['id'] ?>"
                                                            data-subject_id="<?= $item['subject_id'] ?>"
                                                            data-classroom_id="<?= $item['classroom_id'] ?>"
                                                            data-day="<?= $item['day_of_week'] ?>"
                                                            data-start="<?= $item['start_time'] ?>"
                                                            data-end="<?= $item['end_time'] ?>"
                                                            data-room="<?= htmlspecialchars($item['room_number']) ?>">
                                                        <i class="bi bi-pencil"></i> แก้ไข
                                                    </button>
                                                    <a href="<?= BASE_URL ?>admin/schedules.php?action=delete&id=<?= $item['id'] ?>&classroom_id=<?= $selectedClassroomId ?>&csrf_token=<?= getCsrfToken() ?>" 
                                                       class="btn btn-xs btn-outline-danger py-0 px-1.5"
                                                       onclick="return confirm('ยืนยันลบคาบเรียนนี้?');">
                                                        <i class="bi bi-trash"></i> ลบ
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Detailed Table View -->
<div class="card">
    <div class="card-header bg-white py-3">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="bi bi-list-columns-reverse text-primary me-1"></i> รายการคาบเรียนทั้งหมดในห้องเรียนนี้
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">วัน</th>
                        <th>ช่วงเวลา</th>
                        <th>รหัสวิชา</th>
                        <th>ชื่อรายวิชา</th>
                        <th>ครูผู้สอน</th>
                        <th>ห้องเรียน/สถานที่</th>
                        <th class="text-center pe-4">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($schedules)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="bi bi-calendar-x display-6 d-block mb-2 text-secondary"></i>
                                ไม่พบข้อมูลตารางเรียนในห้องเรียนนี้
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($schedules as $s): ?>
                            <tr>
                                <td class="ps-4 fw-semibold">
                                    <span class="badge bg-light text-dark border">
                                        <?= getDayThaiName((int)$s['day_of_week']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-primary fw-medium font-monospace">
                                        <?= date('H:i', strtotime($s['start_time'])) ?> - <?= date('H:i', strtotime($s['end_time'])) ?> น.
                                    </span>
                                </td>
                                <td><span class="badge bg-secondary-subtle text-secondary font-monospace"><?= htmlspecialchars($s['subject_code']) ?></span></td>
                                <td class="fw-medium"><?= htmlspecialchars($s['subject_name']) ?></td>
                                <td><?= htmlspecialchars($s['teacher_name']) ?></td>
                                <td><i class="bi bi-geo-alt text-danger me-1"></i><?= htmlspecialchars($s['room_number']) ?></td>
                                <td class="text-center pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-warning edit-schedule-btn"
                                                data-id="<?= $s['id'] ?>"
                                                data-subject_id="<?= $s['subject_id'] ?>"
                                                data-classroom_id="<?= $s['classroom_id'] ?>"
                                                data-day="<?= $s['day_of_week'] ?>"
                                                data-start="<?= $s['start_time'] ?>"
                                                data-end="<?= $s['end_time'] ?>"
                                                data-room="<?= htmlspecialchars($s['room_number']) ?>"
                                                title="แก้ไข">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="<?= BASE_URL ?>admin/schedules.php?action=delete&id=<?= $s['id'] ?>&classroom_id=<?= $selectedClassroomId ?>&csrf_token=<?= getCsrfToken() ?>" 
                                           class="btn btn-outline-danger" 
                                           title="ลบ"
                                           onclick="return confirm('ยืนยันลบคาบเรียนนี้?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: เพิ่มคาบเรียน -->
<div class="modal fade" id="addScheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= BASE_URL ?>admin/schedules.php" method="POST">
                <?= getCsrfField() ?>
                <input type="hidden" name="action" value="create">
                
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary me-2"></i>เพิ่มคาบเรียนในตาราง</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ห้องเรียน <span class="text-danger">*</span></label>
                        <select name="classroom_id" class="form-select" required>
                            <?php foreach ($classrooms as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($selectedClassroomId === (int)$c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">รายวิชา <span class="text-danger">*</span></label>
                        <select name="subject_id" class="form-select" required>
                            <option value="">-- เลือกรายวิชา --</option>
                            <?php foreach ($subjects as $sub): ?>
                                <option value="<?= $sub['id'] ?>">
                                    <?= htmlspecialchars($sub['subject_code'] . ' - ' . $sub['name_th'] . ' (ครู: ' . $sub['teacher_name'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">วันในสัปดาห์ <span class="text-danger">*</span></label>
                        <select name="day_of_week" class="form-select" required>
                            <option value="1">วันจันทร์</option>
                            <option value="2">วันอังคาร</option>
                            <option value="3">วันพุธ</option>
                            <option value="4">วันพฤหัสบดี</option>
                            <option value="5">วันศุกร์</option>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">เวลาเริ่มเรียน <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" class="form-control" value="08:30" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">เวลาสิ้นสุด <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" class="form-control" value="11:30" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">ห้องเรียน / สถานที่ <span class="text-danger">*</span></label>
                        <input type="text" name="room_number" class="form-control" placeholder="เช่น Lab IT 401 หรือ Room 302" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4">บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: แก้ไขคาบเรียน -->
<div class="modal fade" id="editScheduleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form action="<?= BASE_URL ?>admin/schedules.php" method="POST">
                <?= getCsrfField() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-warning me-2"></i>แก้ไขคาบเรียน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">ห้องเรียน <span class="text-danger">*</span></label>
                        <select name="classroom_id" id="edit_classroom_id" class="form-select" required>
                            <?php foreach ($classrooms as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">รายวิชา <span class="text-danger">*</span></label>
                        <select name="subject_id" id="edit_subject_id" class="form-select" required>
                            <?php foreach ($subjects as $sub): ?>
                                <option value="<?= $sub['id'] ?>">
                                    <?= htmlspecialchars($sub['subject_code'] . ' - ' . $sub['name_th']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">วันในสัปดาห์ <span class="text-danger">*</span></label>
                        <select name="day_of_week" id="edit_day_of_week" class="form-select" required>
                            <option value="1">วันจันทร์</option>
                            <option value="2">วันอังคาร</option>
                            <option value="3">วันพุธ</option>
                            <option value="4">วันพฤหัสบดี</option>
                            <option value="5">วันศุกร์</option>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold">เวลาเริ่มเรียน <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" id="edit_start_time" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold">เวลาสิ้นสุด <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" id="edit_end_time" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">ห้องเรียน / สถานที่ <span class="text-danger">*</span></label>
                        <input type="text" name="room_number" id="edit_room_number" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-4 text-dark fw-semibold">บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = '
<script>
document.querySelectorAll(".edit-schedule-btn").forEach(btn => {
    btn.addEventListener("click", function() {
        document.getElementById("edit_id").value = this.dataset.id;
        document.getElementById("edit_classroom_id").value = this.dataset.classroom_id;
        document.getElementById("edit_subject_id").value = this.dataset.subject_id;
        document.getElementById("edit_day_of_week").value = this.dataset.day;
        document.getElementById("edit_start_time").value = this.dataset.start;
        document.getElementById("edit_end_time").value = this.dataset.end;
        document.getElementById("edit_room_number").value = this.dataset.room;
        
        const modal = new bootstrap.Modal(document.getElementById("editScheduleModal"));
        modal.show();
    });
});
</script>
';
require_once __DIR__ . '/../includes/footer.php';
?>
