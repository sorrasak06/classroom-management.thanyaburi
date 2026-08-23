<?php
/**
 * Student - Timetable / Class Schedule
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('student');

$pageTitle = 'ตารางเรียนประจำสัปดาห์';
$currentUser = getCurrentUser();
$classroomId = $currentUser['classroom_id'] ?? 0;

try {
    // ดึงข้อมูลห้องเรียน
    $stmtClass = $pdo->prepare("SELECT * FROM classrooms WHERE id = ?");
    $stmtClass->execute([$classroomId]);
    $classroom = $stmtClass->fetch();

    // ดึงตารางเรียนเรียงตามวันและเวลา
    $stmtSched = $pdo->prepare("
        SELECT sc.*, s.subject_code, s.name_th as subject_name, s.name_en, s.credits,
               u.full_name as teacher_name
        FROM schedules sc
        JOIN subjects s ON sc.subject_id = s.id
        JOIN teachers t ON s.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE sc.classroom_id = ?
        ORDER BY sc.day_of_week ASC, sc.start_time ASC
    ");
    $stmtSched->execute([$classroomId]);
    $allSchedules = $stmtSched->fetchAll();

    // จัดกลุ่มตามวัน (1-5)
    $groupedByDay = [
        1 => ['name' => 'วันจันทร์',    'color' => '#fbbf24', 'classes' => []],
        2 => ['name' => 'วันอังคาร',    'color' => '#f472b6', 'classes' => []],
        3 => ['name' => 'วันพุธ',      'color' => '#34d399', 'classes' => []],
        4 => ['name' => 'วันพฤหัสบดี', 'color' => '#fb923c', 'classes' => []],
        5 => ['name' => 'วันศุกร์',     'color' => '#60a5fa', 'classes' => []]
    ];

    foreach ($allSchedules as $sc) {
        $day = (int)$sc['day_of_week'];
        if (isset($groupedByDay[$day])) {
            $groupedByDay[$day]['classes'][] = $sc;
        }
    }

} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<!-- Print-Only Header -->
<div class="print-header">
    <img src="<?= BASE_URL ?>assets/images/logo.png" style="height: 60px; margin-bottom: 8px;">
    <h3 style="margin: 0; font-size: 16pt;"><?= APP_NAME ?></h3>
    <h4 style="margin: 5px 0 10px 0; font-size: 13pt;">ตารางเรียนประจำกลุ่ม <?= htmlspecialchars($classroom['name'] ?? '') ?></h4>
    <div style="font-size: 10pt; color: #555;">
        นักศึกษา: <?= htmlspecialchars($currentUser['full_name']) ?> (<?= htmlspecialchars($currentUser['code']) ?>) | ภาคเรียนที่ 1/2567
    </div>
    <hr style="margin: 15px 0;">
</div>

<div class="card mb-4">
    <div class="card-header flex-column flex-md-row gap-3 align-items-start align-items-md-center">
        <div>
            <h5 class="card-title mb-0"><i class="bi bi-calendar-week-fill text-primary"></i> ตารางเรียนประจำสัปดาห์ (Timetable)</h5>
            <div class="small text-muted">ห้องเรียน: <strong><?= htmlspecialchars($classroom['name'] ?? '-') ?></strong> &bull; ปีการศึกษา 2567</div>
        </div>
        <div class="no-print">
            <button type="button" class="btn btn-dark btn-sm" onclick="printReport()">
                <i class="bi bi-printer me-1"></i> พิมพ์ตารางเรียน
            </button>
        </div>
    </div>

    <div class="card-body p-4">
        <div class="row g-4">
            <?php foreach ($groupedByDay as $dayNum => $dayData): ?>
                <div class="col-12">
                    <div class="border rounded-3 p-3 bg-white shadow-sm">
                        <!-- Day Header -->
                        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge px-3 py-1.5 rounded-pill text-dark fw-bold" style="background-color: <?= $dayData['color'] ?>; font-size: 0.88rem;">
                                    <?= $dayData['name'] ?>
                                </span>
                                <span class="small text-muted">(มี <?= count($dayData['classes']) ?> คาบเรียน)</span>
                            </div>
                        </div>

                        <?php if (empty($dayData['classes'])): ?>
                            <div class="text-muted small py-2 fst-italic">
                                <i class="bi bi-info-circle me-1"></i> ไม่มีตารางเรียนในวันนี้
                            </div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($dayData['classes'] as $cls): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="p-3 rounded-3 border bg-light h-100 position-relative" style="border-left: 4px solid var(--primary) !important;">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($cls['subject_code']) ?></span>
                                                <span class="badge bg-dark-subtle text-dark">
                                                    <i class="bi bi-door-open me-0.5"></i> <?= htmlspecialchars($cls['room_number']) ?>
                                                </span>
                                            </div>
                                            <h6 class="fw-bold text-dark mb-1"><?= htmlspecialchars($cls['subject_name']) ?></h6>
                                            <div class="small text-muted mb-2"><?= htmlspecialchars($cls['name_en'] ?? '') ?></div>
                                            
                                            <div class="d-flex justify-content-between align-items-center pt-2 border-top small text-muted">
                                                <div><i class="bi bi-person me-1"></i><?= htmlspecialchars($cls['teacher_name']) ?></div>
                                                <div class="fw-semibold text-primary">
                                                    <i class="bi bi-clock me-1"></i><?= formatTime($cls['start_time']) ?> - <?= formatTime($cls['end_time']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
