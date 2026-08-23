<?php
/**
 * Student Line-up Attendance Status Page
 * หน้าเช็กชื่อและตรวจสอบสถานะการเข้าแถวประจำวันของนักศึกษา
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('student');

$pageTitle = 'เช็กชื่อเข้าแถว';
$currentUser = getCurrentUser();
$studentId   = (int)($currentUser['role_id'] ?? 0);

// 1. ดึงข้อมูล Session ประจำวันนี้
$stmtSession = $pdo->prepare("SELECT * FROM lineup_sessions WHERE session_date = CURDATE() ORDER BY id DESC LIMIT 1");
$stmtSession->execute();
$todaySession = $stmtSession->fetch();

// 2. ดึงข้อมูลการเข้าแถวของตนเองในวันนี้
$myAttendance = null;
if ($todaySession) {
    $stmtMy = $pdo->prepare("SELECT * FROM lineup_attendance WHERE lineup_session_id = ? AND student_id = ? LIMIT 1");
    $stmtMy->execute([$todaySession['id'], $studentId]);
    $myAttendance = $stmtMy->fetch();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-dark mb-1"><i class="bi bi-qr-code-scan text-primary me-2"></i> การเช็กชื่อเข้าแถวประจำวันนี้</h3>
        <p class="text-muted mb-0">ตรวจสอบสถานะการมาเข้าแถวตอนเช้าของวันที่ <?= formatThaiDate(date('Y-m-d')) ?></p>
    </div>
    <div>
        <a href="<?= BASE_URL ?>student/lineup-history.php" class="btn btn-outline-primary rounded-pill">
            <i class="bi bi-clock-history me-1"></i> ประวัติการเข้าแถวทั้งหมด
        </a>
    </div>
</div>

<?= displayFlashMessages() ?>

<div class="row g-4 justify-content-center">
    <div class="col-lg-8">
        <?php if (!$todaySession): ?>
            <!-- ยังไม่มีการเปิดรับเช็กชื่อวันนี้ -->
            <div class="card border-0 shadow-sm text-center py-5">
                <div class="card-body">
                    <div class="display-3 text-muted mb-3"><i class="bi bi-calendar-x"></i></div>
                    <h4 class="fw-bold text-dark mb-2">ยังไม่มีการเปิดเช็กชื่อเข้าแถวประจำวันนี้</h4>
                    <p class="text-muted">กรุณารอครูผู้สอนสร้าง QR Code สำหรับการเข้าแถวตอนเช้า</p>
                </div>
            </div>

        <?php elseif ($myAttendance): ?>
            <!-- เช็กชื่อเรียบร้อยแล้ว -->
            <div class="card border-0 shadow-sm text-center py-4">
                <div class="card-body p-4 p-md-5">
                    <div class="display-2 text-success mb-3"><i class="bi bi-check-circle-fill"></i></div>
                    <h3 class="fw-bold text-success mb-2">คุณเช็กชื่อเข้าแถวแล้ว</h3>
                    <p class="text-muted mb-4">ระบบบันทึกเวลาการเข้าแถวประจำวันที่ <?= formatThaiDate($todaySession['session_date']) ?> เรียบร้อยแล้ว</p>

                    <div class="row g-3 justify-content-center max-w-md mx-auto mb-4">
                        <div class="col-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="small text-muted mb-1">เวลาที่เช็กชื่อ</div>
                                <div class="fs-4 fw-bold text-dark"><?= date('H:i:s', strtotime($myAttendance['check_in_time'])) ?> น.</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="small text-muted mb-1">สถานะ</div>
                                <div class="mt-1">
                                    <?= ($myAttendance['status'] === 'on_time') 
                                        ? '<span class="badge bg-success px-3 py-2 rounded-pill"><i class="bi bi-check-circle me-1"></i>ตรงเวลา</span>' 
                                        : '<span class="badge bg-warning text-dark px-3 py-2 rounded-pill"><i class="bi bi-clock-history me-1"></i>สาย</span>' ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <a href="<?= BASE_URL ?>student/dashboard.php" class="btn btn-primary rounded-pill px-4">
                        <i class="bi bi-house-door me-1"></i> กลับหน้าหลัก
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- มี Session แต่ยังไม่ได้เช็กชื่อ -->
            <div class="card border-0 shadow-sm text-center py-4">
                <div class="card-body p-4 p-md-5">
                    <div class="display-3 text-primary mb-3"><i class="bi bi-qr-code"></i></div>
                    <h3 class="fw-bold text-dark mb-2">เปิดรับการเช็กชื่อเข้าแถวแล้ว</h3>
                    <p class="text-muted mb-4">
                        ช่วงเวลาเปิดรับ: <strong><?= formatTime($todaySession['start_time']) ?> - <?= formatTime($todaySession['end_time']) ?></strong><br>
                        <span class="text-danger">* หากสแกนหลังเวลา <?= formatTime($todaySession['late_threshold']) ?> จะถือว่ามาสาย</span>
                    </p>

                    <?php if ($todaySession['status'] === 'closed'): ?>
                        <div class="alert alert-danger-subtle text-danger border border-danger-subtle rounded-3 py-3 mb-0">
                            <i class="bi bi-lock-fill me-1"></i> ครูผู้สอนปิดรับการเช็กชื่อประจำวันนี้แล้ว
                        </div>
                    <?php else: ?>
                        <div class="p-4 bg-primary-subtle rounded-3 border border-primary-subtle text-primary mb-4">
                            <h5 class="fw-bold mb-2"><i class="bi bi-phone me-2"></i> คำแนะนำในการเช็กชื่อ</h5>
                            <p class="mb-0 small">ใชักล้องถ่ายรูปจากโทรศัพท์มือถือ หรือแอปพลิเคชันสแกน QR Code สแกนบนหน้าจอของครูผู้สอนเพื่อเช็กชื่อ</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
