<?php
/**
 * Teacher Line-up Attendance Page
 * ระบบเช็กชื่อเข้าแถวด้วย QR Code สำหรับครูผู้สอน
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

requireRole(['teacher', 'admin']);

$pageTitle = 'เช็กชื่อเข้าแถว QR Code';
$currentUser = getCurrentUser();

// ดึงเวลาการเข้าแถวจาก System Settings
$startTimeSetting     = getSetting($pdo, 'lineup_start_time', '06:30');
$endTimeSetting       = getSetting($pdo, 'lineup_end_time', '08:30');
$lateThresholdSetting = getSetting($pdo, 'lineup_late_threshold', '08:00');

// ดึงจำนวนนักเรียนทั้งหมดในระบบ
$totalStudentsCount = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

// จัดการ POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!validateCsrfToken($csrfToken)) {
        setFlash('error', 'CSRF Token ไม่ถูกต้อง');
        header('Location: ' . BASE_URL . 'teacher/lineup-attendance.php');
        exit;
    }

    if ($action === 'create_session') {
        // ตรวจสอบว่ามี Session ของวันนี้อยู่แล้วหรือไม่
        $stmtCheck = $pdo->prepare("SELECT id FROM lineup_sessions WHERE session_date = CURDATE() AND status != 'cancelled' LIMIT 1");
        $stmtCheck->execute();
        $existing = $stmtCheck->fetch();

        if ($existing) {
            setFlash('warning', 'มี Session เช็กชื่อเข้าแถวสำหรับวันนี้อยู่แล้ว');
        } else {
            // สร้าง Cryptographically Secure Token 64 hex characters
            $newToken = bin2hex(random_bytes(32));
            $stmtCreate = $pdo->prepare("
                INSERT INTO lineup_sessions 
                    (session_date, token, start_time, end_time, late_threshold, status, created_by, created_at)
                VALUES 
                    (CURDATE(), ?, ?, ?, ?, 'active', ?, NOW())
            ");
            $stmtCreate->execute([
                $newToken,
                $startTimeSetting . ':00',
                $endTimeSetting . ':00',
                $lateThresholdSetting . ':00',
                $currentUser['id']
            ]);
            setFlash('success', 'เปิดรับการเช็กชื่อเข้าแถวประจำวันนี้เรียบร้อยแล้ว');
        }
        header('Location: ' . BASE_URL . 'teacher/lineup-attendance.php');
        exit;

    } elseif ($action === 'close_session') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        if ($sessionId > 0) {
            $stmtClose = $pdo->prepare("UPDATE lineup_sessions SET status = 'closed', closed_at = NOW() WHERE id = ?");
            $stmtClose->execute([$sessionId]);
            setFlash('info', 'ปิดการรับเช็กชื่อเข้าแถวประจำวันนี้แล้ว');
        }
        header('Location: ' . BASE_URL . 'teacher/lineup-attendance.php');
        exit;

    } elseif ($action === 'reopen_session') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        if ($sessionId > 0) {
            $stmtReopen = $pdo->prepare("UPDATE lineup_sessions SET status = 'active', closed_at = NULL WHERE id = ? AND session_date = CURDATE()");
            $stmtReopen->execute([$sessionId]);
            setFlash('success', 'เปิดรับการเช็กชื่อเข้าแถวอีกครั้งเรียบร้อยแล้ว');
        }
        header('Location: ' . BASE_URL . 'teacher/lineup-attendance.php');
        exit;
    }
}

// ดึง Session ประจำวันนี้
$stmtSession = $pdo->prepare("SELECT * FROM lineup_sessions WHERE session_date = CURDATE() ORDER BY id DESC LIMIT 1");
$stmtSession->execute();
$currentSession = $stmtSession->fetch();

// ดึงสถิติผู้เช็กชื่อแล้วของ Session นี้
$attendedCount = 0;
$onTimeCount   = 0;
$lateCount     = 0;
$attendanceList = [];

if ($currentSession) {
    $stmtAtt = $pdo->prepare("
        SELECT 
            la.id,
            la.check_in_time,
            la.status,
            st.student_code,
            u.full_name,
            c.name as classroom_name
        FROM lineup_attendance la
        JOIN students st ON la.student_id = st.id
        JOIN users u ON st.user_id = u.id
        JOIN classrooms c ON st.classroom_id = c.id
        WHERE la.lineup_session_id = ?
        ORDER BY la.check_in_time ASC
    ");
    $stmtAtt->execute([$currentSession['id']]);
    $attendanceList = $stmtAtt->fetchAll();
    $attendedCount  = count($attendanceList);

    foreach ($attendanceList as $a) {
        if ($a['status'] === 'late') {
            $lateCount++;
        } else {
            $onTimeCount++;
        }
    }
}

// QR Code URL สำหรับนักเรียนสแกน
$qrScanUrl = '';
if ($currentSession) {
    $qrScanUrl = BASE_URL . 'student/lineup-scan.php?token=' . $currentSession['token'];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1"><i class="bi bi-qr-code text-primary me-2"></i> ระบบเช็กชื่อเข้าแถวด้วย QR Code</h3>
        <p class="text-muted mb-0">สร้างและแสดง QR Code ประจำวันสำหรับการเข้าแถวตอนเช้าของนักศึกษา</p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>teacher/lineup-reports.php" class="btn btn-outline-primary rounded-pill">
            <i class="bi bi-bar-chart-fill me-1"></i> รายงานสถิติ
        </a>
    </div>
</div>

<?= displayFlashMessages() ?>

<?php if (!$currentSession): ?>
    <!-- กรณีไม่มี Session ของวันนี้ -->
    <div class="card border-0 shadow-sm text-center py-5">
        <div class="card-body">
            <div class="display-1 text-muted mb-3"><i class="bi bi-qr-code-scan"></i></div>
            <h4 class="fw-bold text-dark mb-2">ยังไม่ได้สร้าง QR Code เช็กชื่อประจำวันนี้</h4>
            <p class="text-muted mb-4">
                วันที่ปัจจุบัน: <strong><?= formatThaiDate(date('Y-m-d')) ?></strong><br>
                เวลาเช็กชื่อที่กำหนด: <?= formatTime($startTimeSetting) ?> - <?= formatTime($endTimeSetting) ?> (สายหลัง <?= formatTime($lateThresholdSetting) ?>)
            </p>

            <form method="POST" action="">
                <?= getCsrfField() ?>
                <input type="hidden" name="action" value="create_session">
                <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5 shadow-sm">
                    <i class="bi bi-plus-circle me-2"></i> สร้าง QR Code วันนี้
                </button>
            </form>
        </div>
    </div>

<?php else: ?>
    <!-- กรณีมี Session ประจำวันนี้ -->
    <div class="row g-4 mb-4">
        <!-- ด้านซ้าย: QR Code Display -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 text-center">
                <div class="card-header bg-primary text-white py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-qr-code me-2"></i> QR Code เช็กชื่อเข้าแถว</h5>
                    <small><?= formatThaiDate($currentSession['session_date']) ?></small>
                </div>
                <div class="card-body d-flex flex-column align-items-center justify-content-center p-4">
                    
                    <?php if ($currentSession['status'] === 'active'): ?>
                        <div class="alert alert-success-subtle text-success border border-success-subtle rounded-pill px-4 py-1.5 mb-3">
                            <i class="bi bi-record-fill me-1 animate-pulse"></i> กำลังเปิดรับการเช็กชื่อ
                        </div>
                    <?php elseif ($currentSession['status'] === 'closed'): ?>
                        <div class="alert alert-danger-subtle text-danger border border-danger-subtle rounded-pill px-4 py-1.5 mb-3">
                            <i class="bi bi-lock-fill me-1"></i> ปิดการเช็กชื่อแล้ว
                        </div>
                    <?php endif; ?>

                    <!-- QR Code Canvas Container -->
                    <div id="qrcode-wrapper" class="p-3 bg-white border rounded-3 shadow-sm mb-3 position-relative">
                        <div id="qrcode"></div>
                    </div>

                    <div class="small text-muted mb-3 text-break" style="max-width: 400px;">
                        Token: <code class="user-select-all"><?= htmlspecialchars(substr($currentSession['token'], 0, 16)) ?>...</code>
                    </div>

                    <div class="d-flex gap-2 flex-wrap justify-content-center">
                        <button onclick="window.print()" class="btn btn-outline-secondary rounded-pill">
                            <i class="bi bi-printer me-1"></i> พิมพ์ QR Code
                        </button>
                        <?php if ($currentSession['status'] === 'active'): ?>
                            <form method="POST" action="" onsubmit="return confirm('ยืนยันปิดการรับเช็กชื่อเข้าแถวประจำวันนี้?');">
                                <?= getCsrfField() ?>
                                <input type="hidden" name="action" value="close_session">
                                <input type="hidden" name="session_id" value="<?= $currentSession['id'] ?>">
                                <button type="submit" class="btn btn-danger rounded-pill">
                                    <i class="bi bi-stop-circle me-1"></i> ปิดการเช็กชื่อ
                                </button>
                            </form>
                        <?php elseif ($currentSession['status'] === 'closed'): ?>
                            <form method="POST" action="" onsubmit="return confirm('ยืนยันเปิดรับการเช็กชื่อเข้าแถวอีกครั้ง?');">
                                <?= getCsrfField() ?>
                                <input type="hidden" name="action" value="reopen_session">
                                <input type="hidden" name="session_id" value="<?= $currentSession['id'] ?>">
                                <button type="submit" class="btn btn-success rounded-pill">
                                    <i class="bi bi-unlock-fill me-1"></i> เปิดรับการเช็กชื่ออีกครั้ง
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ด้านขวา: ข้อมูล Session และ สถิติสรุป -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i> ข้อมูลช่วงเวลาการเข้าแถว</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 text-center">
                        <div class="col-4">
                            <div class="p-2.5 bg-light rounded-3 border">
                                <div class="small text-muted">เวลาเปิดรับ</div>
                                <div class="fs-5 fw-bold text-success"><?= formatTime($currentSession['start_time']) ?></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2.5 bg-light rounded-3 border">
                                <div class="small text-muted">เวลาถือว่าสาย</div>
                                <div class="fs-5 fw-bold text-warning-emphasis"><?= formatTime($currentSession['late_threshold']) ?></div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2.5 bg-light rounded-3 border">
                                <div class="small text-muted">เวลาปิดรับ</div>
                                <div class="fs-5 fw-bold text-danger"><?= formatTime($currentSession['end_time']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Overview Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 fw-bold"><i class="bi bi-people-fill me-2 text-primary"></i> จำนวนผู้เข้าแถววันนี้</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="display-4 fw-bold text-primary" id="total-count-display"><?= $attendedCount ?></div>
                        <div class="text-muted">จากนักเรียนทั้งหมด <?= $totalStudentsCount ?> คน</div>
                    </div>
                    <div class="row text-center g-3">
                        <div class="col-6">
                            <div class="p-3 bg-success-subtle rounded-3 border border-success-subtle">
                                <div class="fs-3 fw-bold text-success" id="ontime-count-display"><?= $onTimeCount ?></div>
                                <div class="small text-success fw-medium">ตรงเวลา</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-warning-subtle rounded-3 border border-warning-subtle">
                                <div class="fs-3 fw-bold text-warning-emphasis" id="late-count-display"><?= $lateCount ?></div>
                                <div class="small text-warning-emphasis fw-medium">มาสาย</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ตารางแสดงรายชื่อผู้เข้าแถวแบบ Real-time -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-bold">
                <i class="bi bi-list-check me-2 text-primary"></i> รายชื่อนักศึกษาที่เข้าแถวแล้วแบบ Real-time
            </h5>
            <span class="badge bg-secondary-subtle text-dark" id="live-indicator">
                <i class="bi bi-arrow-repeat spin me-1"></i> อัปเดตอัตโนมัติทุก 5 วินาที
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="attendance-table">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px;">ลำดับ</th>
                        <th>รหัสนักศึกษา</th>
                        <th>ชื่อ - นามสกุล</th>
                        <th>ห้องเรียน</th>
                        <th>เวลาเข้าแถว</th>
                        <th>สถานะ</th>
                    </tr>
                </thead>
                <tbody id="attendance-tbody">
                    <?php if (empty($attendanceList)): ?>
                        <tr id="no-data-row">
                            <td colspan="6" class="text-center text-muted py-4">ยังไม่มีนักศึกษาสแกนเข้าแถว</td>
                        </tr>
                    <?php else: ?>
                        <?php $seq = 1; foreach ($attendanceList as $att): ?>
                            <tr>
                                <td><?= sprintf('%03d', $seq++) ?></td>
                                <td><code class="fw-bold"><?= htmlspecialchars($att['student_code']) ?></code></td>
                                <td class="fw-medium text-dark"><?= htmlspecialchars($att['full_name']) ?></td>
                                <td><?= htmlspecialchars($att['classroom_name']) ?></td>
                                <td><?= date('H:i:s', strtotime($att['check_in_time'])) ?> น.</td>
                                <td><?= getAttendanceBadge($att['status'] === 'on_time' ? 'present' : 'late') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- QRCode Library CDN -->
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($currentSession): ?>
    // Render QR Code
    var qrContainer = document.getElementById('qrcode');
    if (qrContainer) {
        new QRCode(qrContainer, {
            text: "<?= addslashes($qrScanUrl) ?>",
            width: 300,
            height: 300,
            colorDark: "#000000",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.M
        });
    }

    // Real-time Polling ทุก 5 วินาที
    var sessionId = <?= (int)$currentSession['id'] ?>;
    function fetchAttendanceList() {
        fetch('<?= BASE_URL ?>api/lineup-list.php?session_id=' + sessionId)
            .then(function(res) { return res.json(); })
            .then(function(json) {
                if (json.success && json.data) {
                    var tbody = document.getElementById('attendance-tbody');
                    var totalDisplay = document.getElementById('total-count-display');
                    var ontimeDisplay = document.getElementById('ontime-count-display');
                    var lateDisplay = document.getElementById('late-count-display');

                    if (totalDisplay) totalDisplay.textContent = json.total;
                    if (ontimeDisplay && json.stats) ontimeDisplay.textContent = json.stats.on_time;
                    if (lateDisplay && json.stats) lateDisplay.textContent = json.stats.late;

                    if (json.data.length === 0) {
                        tbody.innerHTML = '<tr id="no-data-row"><td colspan="6" class="text-center text-muted py-4">ยังไม่มีนักศึกษาสแกนเข้าแถว</td></tr>';
                        return;
                    }

                    var html = '';
                    json.data.forEach(function(item) {
                        html += '<tr>' +
                            '<td>' + item.seq + '</td>' +
                            '<td><code class="fw-bold">' + item.student_code + '</code></td>' +
                            '<td class="fw-medium text-dark">' + item.full_name + '</td>' +
                            '<td>' + item.classroom + '</td>' +
                            '<td>' + item.check_in_time + ' น.</td>' +
                            '<td>' + item.status_badge + '</td>' +
                            '</tr>';
                    });
                    tbody.innerHTML = html;
                }
            })
            .catch(function(err) { console.error('Polling error:', err); });
    }

    // เรียกครั้งแรกและตั้ง Interval
    setInterval(fetchAttendanceList, 5000);
    <?php endif; ?>
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
