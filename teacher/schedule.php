<?php
/**
 * Teacher - Teaching Timetable
 * ตารางสอนประจำสัปดาห์ของครูผู้สอน
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('teacher');

$pageTitle = 'ตารางสอนของฉัน';
$currentUser = getCurrentUser();
$teacherId = $currentUser['role_id'] ?? 0;

// ดึงตารางสอนทั้งหมดของครู
try {
    $stmt = $pdo->prepare("
        SELECT sc.*, s.subject_code, s.name_th as subject_name, s.credits,
               c.name as classroom_name, c.level
        FROM schedules sc
        JOIN subjects s ON sc.subject_id = s.id
        JOIN classrooms c ON sc.classroom_id = c.id
        WHERE s.teacher_id = ?
        ORDER BY sc.day_of_week ASC, sc.start_time ASC
    ");
    $stmt->execute([$teacherId]);
    $schedules = $stmt->fetchAll();
} catch (PDOException $e) {
    $dbError = $e->getMessage();
    $schedules = [];
}

// จัดกลุ่มตารางตามวัน
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

<div class="card mb-4 no-print">
    <div class="card-body">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
            <div>
                <h5 class="card-title mb-1"><i class="bi bi-calendar-week-fill text-primary"></i> ตารางสอนประจำภาคเรียน</h5>
                <p class="text-muted small mb-0">ครูผู้สอน: <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong> (รหัสครู: <?= htmlspecialchars($currentUser['code']) ?>)</p>
            </div>
            <div>
                <button type="button" class="btn btn-outline-primary btn-sm rounded-pill" onclick="window.print()">
                    <i class="bi bi-printer-fill me-1"></i> พิมพ์ตารางสอน
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Weekly Timetable Grid -->
<div class="card mb-4">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="bi bi-calendar-event text-primary me-1"></i> ตารางสอนรายสัปดาห์ (จันทร์ - ศุกร์)
        </h6>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
            <?= count($schedules) ?> คาบสอนทั้งหมด
        </span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0 text-center">
                <thead class="table-light">
                    <tr>
                        <th style="width: 120px;">วัน</th>
                        <th>ช่วงเวลาและรายวิชาที่สอน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $days = [
                        1 => ['name' => 'วันจันทร์', 'color' => '#fef08a', 'border' => '#eab308'],
                        2 => ['name' => 'วันอังคาร', 'color' => '#fbcfe8', 'border' => '#ec4899'],
                        3 => ['name' => 'วันพุธ', 'color' => '#bbf7d0', 'border' => '#22c55e'],
                        4 => ['name' => 'วันพฤหัสบดี', 'color' => '#fed7aa', 'border' => '#f97316'],
                        5 => ['name' => 'วันศุกร์', 'color' => '#bae6fd', 'border' => '#0ea5e9']
                    ];
                    foreach ($days as $dayNum => $dayInfo):
                        $dayItems = $schedulesByDay[$dayNum] ?? [];
                    ?>
                        <tr>
                            <td class="fw-bold" style="background-color: <?= $dayInfo['color'] ?>33; border-left: 4px solid <?= $dayInfo['border'] ?>;">
                                <div class="fs-6"><?= $dayInfo['name'] ?></div>
                            </td>
                            <td class="p-3 text-start">
                                <?php if (empty($dayItems)): ?>
                                    <span class="text-muted small italic ps-2">ไม่มีชั่วโมงสอนในวันนี้</span>
                                <?php else: ?>
                                    <div class="row g-3">
                                        <?php foreach ($dayItems as $item): ?>
                                            <div class="col-md-6 col-xl-4">
                                                <div class="card h-100 border shadow-sm p-3" style="border-radius: 0.75rem; border-top: 3px solid <?= $dayInfo['border'] ?> !important;">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($item['subject_code']) ?></span>
                                                        <span class="badge bg-primary text-white">
                                                            <i class="bi bi-clock me-1"></i><?= date('H:i', strtotime($item['start_time'])) ?> - <?= date('H:i', strtotime($item['end_time'])) ?> น.
                                                        </span>
                                                    </div>
                                                    <div class="fw-bold text-dark fs-6 mb-1">
                                                        <?= htmlspecialchars($item['subject_name']) ?>
                                                    </div>
                                                    <div class="small text-muted mb-2">
                                                        <i class="bi bi-door-open me-1"></i><?= htmlspecialchars($item['classroom_name']) ?>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center pt-2 border-top small">
                                                        <span class="text-danger fw-medium"><i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($item['room_number']) ?></span>
                                                        <span class="text-secondary"><?= $item['credits'] ?> หน่วยกิต</span>
                                                    </div>
                                                    <div class="mt-3 pt-2 border-top d-flex gap-2">
                                                        <a href="<?= BASE_URL ?>teacher/attendance.php" class="btn btn-sm btn-outline-success flex-fill py-1">
                                                            <i class="bi bi-check-circle me-1"></i> เช็กชื่อ
                                                        </a>
                                                        <a href="<?= BASE_URL ?>teacher/assignments.php" class="btn btn-sm btn-outline-primary flex-fill py-1">
                                                            <i class="bi bi-clipboard-plus me-1"></i> สั่งงาน
                                                        </a>
                                                    </div>
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

<!-- Detailed Table List -->
<div class="card">
    <div class="card-header bg-white py-3">
        <h6 class="fw-bold mb-0 text-dark">
            <i class="bi bi-list-check text-primary me-1"></i> รายการคาบสอนทั้งหมด
        </h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">วัน</th>
                        <th>เวลา</th>
                        <th>รหัสวิชา</th>
                        <th>ชื่อวิชา</th>
                        <th>หน่วยกิต</th>
                        <th>ห้องเรียน</th>
                        <th>สถานที่/ห้องแล็บ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($schedules)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">ไม่มีข้อมูลตารางสอน</td></tr>
                    <?php else: ?>
                        <?php foreach ($schedules as $s): ?>
                            <tr>
                                <td class="ps-4 fw-semibold">
                                    <span class="badge bg-light text-dark border"><?= getDayThaiName((int)$s['day_of_week']) ?></span>
                                </td>
                                <td>
                                    <span class="text-primary font-monospace fw-medium">
                                        <?= date('H:i', strtotime($s['start_time'])) ?> - <?= date('H:i', strtotime($s['end_time'])) ?> น.
                                    </span>
                                </td>
                                <td><span class="badge bg-secondary-subtle text-secondary font-monospace"><?= htmlspecialchars($s['subject_code']) ?></span></td>
                                <td class="fw-medium"><?= htmlspecialchars($s['subject_name']) ?></td>
                                <td><?= $s['credits'] ?></td>
                                <td><?= htmlspecialchars($s['classroom_name']) ?></td>
                                <td><span class="text-danger fw-medium"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($s['room_number']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
