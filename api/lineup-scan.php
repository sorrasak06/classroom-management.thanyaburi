<?php
/**
 * API: QR Line-up Attendance Scan
 * POST endpoint - บันทึกการเช็กชื่อเข้าแถวด้วย QR Code
 *
 * Security:
 * - student_id ดึงจาก Session เท่านั้น ห้ามรับจาก POST/GET
 * - ตรวจสอบ Server Time เท่านั้น ไม่เชื่อเวลา Client
 * - CSRF Protection
 * - PDO Prepared Statements
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// JSON response เสมอ
header('Content-Type: application/json; charset=utf-8');

// ตรวจสอบว่า Login อยู่หรือไม่
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'กรุณาเข้าสู่ระบบก่อน', 'redirect' => BASE_URL . 'login.php']);
    exit;
}

// ตรวจสอบ Role ต้องเป็น student
$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'ไม่มีสิทธิ์ใช้งาน']);
    exit;
}

// รับเฉพาะ POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// ตรวจ CSRF
require_once __DIR__ . '/../includes/csrf.php';
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token ไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง']);
    exit;
}

// ดึง student_id จาก SESSION เท่านั้น - ห้ามรับจาก POST/URL
$studentId = (int)($currentUser['role_id'] ?? 0);
if ($studentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลนักศึกษา กรุณาเข้าสู่ระบบใหม่']);
    exit;
}

// รับ token จาก POST (ไม่ใช่จาก URL)
$token = trim($_POST['token'] ?? '');

// Validate token format: ต้องเป็น hex 64 ตัวอักษร
if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
    echo json_encode(['success' => false, 'message' => 'QR Code ไม่ถูกต้อง']);
    exit;
}

try {
    // ============================================================
    // 1. ค้นหา Session จาก Token
    // ============================================================
    $stmtSession = $pdo->prepare("
        SELECT id, session_date, token, start_time, end_time, late_threshold, status, created_by, created_at
        FROM lineup_sessions
        WHERE token = ?
        LIMIT 1
    ");
    $stmtSession->execute([$token]);
    $session = $stmtSession->fetch();

    if (!$session) {
        echo json_encode(['success' => false, 'message' => 'QR Code ไม่ถูกต้อง หรือไม่พบ Session การเข้าแถว']);
        exit;
    }

    // ============================================================
    // 2. ตรวจสอบว่า Session ยัง Active หรือไม่
    // ============================================================
    if ($session['status'] !== 'active') {
        $statusMsg = ($session['status'] === 'closed') ? 'การเช็กชื่อถูกปิดแล้ว' : 'Session การเข้าแถวถูกยกเลิก';
        echo json_encode(['success' => false, 'message' => $statusMsg]);
        exit;
    }

    // ============================================================
    // 3. ตรวจสอบวันที่ด้วย Server Time (ห้ามเชื่อ Client)
    // ============================================================
    $serverToday = date('Y-m-d'); // Server time เท่านั้น
    if ($session['session_date'] !== $serverToday) {
        echo json_encode([
            'success'  => false,
            'message'  => 'QR Code หมดอายุ ไม่สามารถเช็กชื่อย้อนหลังได้',
            'detail'   => 'QR Code นี้สร้างเมื่อวันที่ ' . formatThaiDate($session['session_date'])
        ]);
        exit;
    }

    // ============================================================
    // 4. ตรวจสอบเวลาด้วย Server Time (ห้ามเชื่อ Client)
    // ============================================================
    $tz    = new DateTimeZone('Asia/Bangkok');
    $now   = new DateTime('now', $tz);
    $datePrefix = $serverToday . ' ';

    $startDT = new DateTime($datePrefix . $session['start_time'], $tz);
    $endDT   = new DateTime($datePrefix . $session['end_time'],   $tz);
    $lateDT  = new DateTime($datePrefix . $session['late_threshold'], $tz);

    if ($now < $startDT) {
        echo json_encode([
            'success' => false,
            'message' => 'ยังไม่ถึงเวลาเช็กชื่อเข้าแถว เวลาเริ่ม ' . date('H:i', strtotime($session['start_time'])) . ' น.'
        ]);
        exit;
    }

    if ($now > $endDT) {
        echo json_encode([
            'success' => false,
            'message' => 'หมดเวลาการเช็กชื่อเข้าแถวแล้ว (สิ้นสุด ' . date('H:i', strtotime($session['end_time'])) . ' น.)'
        ]);
        exit;
    }

    // ============================================================
    // 5. ตรวจสอบว่านักเรียนเคยเช็กชื่อใน Session นี้แล้วหรือไม่
    // ============================================================
    $stmtDup = $pdo->prepare("
        SELECT id, check_in_time, status
        FROM lineup_attendance
        WHERE lineup_session_id = ? AND student_id = ?
        LIMIT 1
    ");
    $stmtDup->execute([$session['id'], $studentId]);
    $existing = $stmtDup->fetch();

    if ($existing) {
        $statusText = ($existing['status'] === 'on_time') ? 'มาเข้าแถวตรงเวลา' : 'มาสาย';
        echo json_encode([
            'success'        => false,
            'already'        => true,
            'message'        => 'คุณเช็กชื่อเข้าแถวแล้ว',
            'check_in_time'  => date('H:i:s', strtotime($existing['check_in_time'])),
            'check_in_thai'  => formatThaiDate($existing['check_in_time'], true),
            'status'         => $existing['status'],
            'status_text'    => $statusText,
            'session_date'   => formatThaiDate($session['session_date'])
        ]);
        exit;
    }

    // ============================================================
    // 6. กำหนดสถานะ: ตรงเวลา หรือ สาย
    // ============================================================
    $attendanceStatus = ($now <= $lateDT) ? 'on_time' : 'late';

    // ============================================================
    // 7. บันทึกการเช็กชื่อ
    // ============================================================
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

    $stmtInsert = $pdo->prepare("
        INSERT INTO lineup_attendance
            (lineup_session_id, student_id, check_in_time, status, ip_address, user_agent, created_at)
        VALUES
            (?, ?, NOW(), ?, ?, ?, NOW())
    ");
    $stmtInsert->execute([
        $session['id'],
        $studentId,
        $attendanceStatus,
        $ipAddress,
        $userAgent
    ]);

    $checkInTime = $pdo->query("SELECT NOW() as t")->fetchColumn();
    $statusText  = ($attendanceStatus === 'on_time') ? 'มาเข้าแถวตรงเวลา' : 'มาสาย';

    echo json_encode([
        'success'       => true,
        'message'       => 'เช็กชื่อเข้าแถวสำเร็จ',
        'check_in_time' => date('H:i:s', strtotime($checkInTime)),
        'check_in_thai' => formatThaiDate($checkInTime, true),
        'status'        => $attendanceStatus,
        'status_text'   => $statusText,
        'session_date'  => formatThaiDate($session['session_date']),
        'student_name'  => $currentUser['full_name']
    ]);

} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        echo json_encode(['success' => false, 'already' => true, 'message' => 'คุณเช็กชื่อเข้าแถวแล้ว']);
    } else {
        error_log('lineup-scan.php PDOException: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง']);
    }
    exit;
}
