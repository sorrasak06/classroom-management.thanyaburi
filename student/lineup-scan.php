<?php
/**
 * Student Line-up Scan Landing Page
 * หน้าเช็กชื่อเมื่อนักเรียนสแกน QR Code (URL: /student/lineup-scan.php?token=XXXXXXXX)
 * 
 * Mobile-First Design
 * Server-side Validation 100%
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

$token = trim($_GET['token'] ?? '');

// หากยังไม่ได้ Login ให้เก็บ URL ไว้แล้ว Redirect ไป Login
if (!isLoggedIn()) {
    if (!empty($token)) {
        $_SESSION['redirect_after_login'] = BASE_URL . 'student/lineup-scan.php?token=' . urlencode($token);
    }
    $_SESSION['flash_info'] = 'กรุณาเข้าสู่ระบบเพื่อเช็กชื่อเข้าแถว';
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

// ต้องเป็นนักศึกษาเท่านั้น
$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'student') {
    $_SESSION['flash_error'] = 'เฉพาะนักศึกษาเท่านั้นที่มีสิทธิ์เช็กชื่อเข้าแถว';
    header('Location: ' . BASE_URL);
    exit;
}

$studentId = (int)($currentUser['role_id'] ?? 0);

// ตัวแปรสำหรับแสดงผล UI
$statusType    = ''; // 'success', 'already', 'error'
$titleMessage  = '';
$detailMessage = '';
$checkInTimeTh = '';
$statusBadge   = '';

if (empty($token) || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
    $statusType    = 'error';
    $titleMessage  = 'QR Code ไม่ถูกต้อง';
    $detailMessage = 'รูปแบบ Token ไม่ถูกต้องหรือลิงก์ไม่สมบูรณ์';
} else {
    try {
        // 1. ค้นหา Session
        $stmtS = $pdo->prepare("SELECT * FROM lineup_sessions WHERE token = ? LIMIT 1");
        $stmtS->execute([$token]);
        $session = $stmtS->fetch();

        if (!$session) {
            $statusType    = 'error';
            $titleMessage  = 'QR Code ไม่ถูกต้อง';
            $detailMessage = 'ไม่พบ Session การเช็กชื่อในระบบ';
        } elseif ($session['status'] !== 'active') {
            $statusType    = 'error';
            $titleMessage  = 'การเช็กชื่อถูกปิดแล้ว';
            $detailMessage = ($session['status'] === 'closed') 
                             ? 'ครูผู้สอนได้ปิดการรับเช็กชื่อประจำวันนี้แล้ว' 
                             : 'Session นี้ถูกยกเลิกแล้ว';
        } else {
            // 2. ตรวจสอบ Server Date
            $serverToday = date('Y-m-d');
            if ($session['session_date'] !== $serverToday) {
                $statusType    = 'error';
                $titleMessage  = 'QR Code หมดอายุ';
                $detailMessage = 'ไม่สามารถเช็กชื่อย้อนหลังได้ (QR Code นี้ของวันที่ ' . formatThaiDate($session['session_date']) . ')';
            } else {
                // 3. ตรวจสอบ Server Time Window
                $tz    = new DateTimeZone('Asia/Bangkok');
                $now   = new DateTime('now', $tz);
                $prefix = $serverToday . ' ';

                $startDT = new DateTime($prefix . $session['start_time'], $tz);
                $endDT   = new DateTime($prefix . $session['end_time'],   $tz);
                $lateDT  = new DateTime($prefix . $session['late_threshold'], $tz);

                if ($now < $startDT) {
                    $statusType    = 'error';
                    $titleMessage  = 'ยังไม่ถึงเวลาเช็กชื่อเข้าแถว';
                    $detailMessage = 'ระบบจะเปิดรับเช็กชื่อเวลา ' . date('H:i', strtotime($session['start_time'])) . ' น.';
                } elseif ($now > $endDT) {
                    $statusType    = 'error';
                    $titleMessage  = 'หมดเวลาการเช็กชื่อเข้าแถวแล้ว';
                    $detailMessage = 'เวลาปิดรับเช็กชื่อคือ ' . date('H:i', strtotime($session['end_time'])) . ' น.';
                } else {
                    // 4. ตรวจสอบ Duplicate (สแกนซ้ำ)
                    $stmtDup = $pdo->prepare("
                        SELECT id, check_in_time, status 
                        FROM lineup_attendance 
                        WHERE lineup_session_id = ? AND student_id = ? 
                        LIMIT 1
                    ");
                    $stmtDup->execute([$session['id'], $studentId]);
                    $existing = $stmtDup->fetch();

                    if ($existing) {
                        $statusType    = 'already';
                        $titleMessage  = 'คุณเช็กชื่อเข้าแถวแล้ว';
                        $checkInTimeTh = formatThaiDate($existing['check_in_time'], true);
                        $statusBadge   = ($existing['status'] === 'on_time') 
                                          ? '<span class="badge bg-success fs-6 px-3 py-2 rounded-pill"><i class="bi bi-check-circle me-1"></i>มาเข้าแถวตรงเวลา</span>'
                                          : '<span class="badge bg-warning text-dark fs-6 px-3 py-2 rounded-pill"><i class="bi bi-clock-history me-1"></i>มาสาย</span>';
                    } else {
                        // 5. บันทึกข้อมูล
                        $attStatus = ($now <= $lateDT) ? 'on_time' : 'late';
                        $ip        = $_SERVER['REMOTE_ADDR'] ?? null;
                        $ua        = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

                        $stmtInsert = $pdo->prepare("
                            INSERT INTO lineup_attendance 
                                (lineup_session_id, student_id, check_in_time, status, ip_address, user_agent, created_at)
                            VALUES 
                                (?, ?, NOW(), ?, ?, ?, NOW())
                        ");
                        $stmtInsert->execute([$session['id'], $studentId, $attStatus, $ip, $ua]);

                        $nowCheckIn    = $pdo->query("SELECT NOW()")->fetchColumn();
                        $statusType    = 'success';
                        $titleMessage  = 'เช็กชื่อเข้าแถวสำเร็จ';
                        $checkInTimeTh = formatThaiDate($nowCheckIn, true);
                        $statusBadge   = ($attStatus === 'on_time')
                                          ? '<span class="badge bg-success fs-6 px-3 py-2 rounded-pill"><i class="bi bi-check-circle me-1"></i>มาเข้าแถวตรงเวลา</span>'
                                          : '<span class="badge bg-warning text-dark fs-6 px-3 py-2 rounded-pill"><i class="bi bi-clock-history me-1"></i>มาสาย</span>';
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $statusType    = 'error';
        $titleMessage  = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
        $detailMessage = 'กรุณาลองใหม่อีกครั้ง หรือแจ้งครูผู้สอน';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titleMessage) ?> - <?= APP_NAME ?></title>
    
    <!-- Google Fonts: Prompt -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background-color: #f1f5f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.25rem;
        }
        .scan-card {
            max-width: 440px;
            width: 100%;
            border-radius: 1.5rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            background: #ffffff;
            overflow: hidden;
        }
        .status-icon-circle {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 1.5rem auto;
        }
    </style>
</head>
<body>

<div class="scan-card p-4 p-md-5 text-center">
    <div class="mb-4">
        <span class="small text-muted fw-semibold text-uppercase tracking-wider"><?= APP_NAME ?></span>
    </div>

    <?php if ($statusType === 'success'): ?>
        <!-- เช็กชื่อสำเร็จ -->
        <div class="status-icon-circle bg-success-subtle text-success">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        <h3 class="fw-bold text-success mb-2"><?= htmlspecialchars($titleMessage) ?></h3>
        <p class="text-muted mb-4">ยินดีด้วย! บันทึกข้อมูลการเข้าแถวของคุณเรียบร้อยแล้ว</p>

        <div class="p-3 bg-light rounded-3 text-start mb-4 border">
            <div class="mb-2">
                <span class="text-muted small">นักศึกษา:</span>
                <div class="fw-semibold text-dark"><?= htmlspecialchars($currentUser['full_name']) ?> (<?= htmlspecialchars($currentUser['code']) ?>)</div>
            </div>
            <div class="mb-2">
                <span class="text-muted small">เวลาที่เช็กชื่อ:</span>
                <div class="fw-semibold text-dark"><?= $checkInTimeTh ?></div>
            </div>
            <div>
                <span class="text-muted small">สถานะการมา:</span>
                <div class="mt-1"><?= $statusBadge ?></div>
            </div>
        </div>

    <?php elseif ($statusType === 'already'): ?>
        <!-- เช็กชื่อแล้วก่อนหน้านี้ -->
        <div class="status-icon-circle bg-info-subtle text-info-emphasis">
            <i class="bi bi-info-circle-fill"></i>
        </div>
        <h3 class="fw-bold text-dark mb-2"><?= htmlspecialchars($titleMessage) ?></h3>
        <p class="text-muted mb-4">คุณได้สแกนเช็กชื่อการเข้าแถวประจำวันนี้ไปเรียบร้อยแล้ว</p>

        <div class="p-3 bg-light rounded-3 text-start mb-4 border">
            <div class="mb-2">
                <span class="text-muted small">นักศึกษา:</span>
                <div class="fw-semibold text-dark"><?= htmlspecialchars($currentUser['full_name']) ?> (<?= htmlspecialchars($currentUser['code']) ?>)</div>
            </div>
            <div class="mb-2">
                <span class="text-muted small">เวลาบันทึก:</span>
                <div class="fw-semibold text-dark"><?= $checkInTimeTh ?></div>
            </div>
            <div>
                <span class="text-muted small">สถานะ:</span>
                <div class="mt-1"><?= $statusBadge ?></div>
            </div>
        </div>

    <?php else: ?>
        <!-- ข้อผิดพลาด / หมดเวลา -->
        <div class="status-icon-circle bg-danger-subtle text-danger">
            <i class="bi bi-exclamation-triangle-fill"></i>
        </div>
        <h3 class="fw-bold text-danger mb-2"><?= htmlspecialchars($titleMessage) ?></h3>
        <p class="text-muted mb-4"><?= htmlspecialchars($detailMessage) ?></p>
    <?php endif; ?>

    <div class="d-grid gap-2">
        <a href="<?= BASE_URL ?>student/dashboard.php" class="btn btn-primary btn-lg rounded-pill fw-semibold">
            <i class="bi bi-house-door-fill me-1"></i> กลับแดชบอร์ด
        </a>
        <a href="<?= BASE_URL ?>student/lineup-history.php" class="btn btn-outline-secondary rounded-pill">
            <i class="bi bi-clock-history me-1"></i> ดูประวัติการเข้าแถว
        </a>
    </div>
</div>

</body>
</html>
