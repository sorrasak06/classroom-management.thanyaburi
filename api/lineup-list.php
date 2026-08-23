<?php
/**
 * API: Real-time Line-up Student List
 * GET endpoint - ดึงรายชื่อนักเรียนที่เช็กชื่อเข้าแถวแล้ว
 * สำหรับ AJAX Polling (ทุก 5-10 วินาที) ของ Teacher/Admin
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$currentUser = getCurrentUser();
if (!in_array($currentUser['role'], ['teacher', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;

try {
    if ($sessionId <= 0) {
        // ดึง session ล่าสุดของวันนี้
        $stmtS = $pdo->prepare("SELECT * FROM lineup_sessions WHERE session_date = CURDATE() ORDER BY id DESC LIMIT 1");
        $stmtS->execute();
        $session = $stmtS->fetch();
        if ($session) {
            $sessionId = (int)$session['id'];
        }
    } else {
        $stmtS = $pdo->prepare("SELECT * FROM lineup_sessions WHERE id = ?");
        $stmtS->execute([$sessionId]);
        $session = $stmtS->fetch();
    }

    if (!$session) {
        echo json_encode([
            'success' => true,
            'data'    => [],
            'total'   => 0,
            'session' => null
        ]);
        exit;
    }

    // ดึงรายชื่อนักเรียนที่เช็กชื่อใน Session นี้
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
    $stmtAtt->execute([$sessionId]);
    $rows = $stmtAtt->fetchAll();

    $data = [];
    $seq = 1;
    $onTimeCount = 0;
    $lateCount = 0;

    foreach ($rows as $row) {
        $isLate = ($row['status'] === 'late');
        if ($isLate) {
            $lateCount++;
        } else {
            $onTimeCount++;
        }

        $data[] = [
            'seq'            => sprintf('%03d', $seq++),
            'student_code'   => e($row['student_code']),
            'full_name'      => e($row['full_name']),
            'classroom'      => e($row['classroom_name']),
            'check_in_time'  => date('H:i:s', strtotime($row['check_in_time'])),
            'check_in_thai'  => formatThaiDate($row['check_in_time'], true),
            'status'         => $row['status'],
            'status_text'    => $isLate ? 'สาย' : 'ตรงเวลา',
            'status_badge'   => $isLate 
                                ? '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1 rounded-pill"><i class="bi bi-clock-history me-1"></i>สาย</span>'
                                : '<span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill"><i class="bi bi-check-circle me-1"></i>ตรงเวลา</span>'
        ];
    }

    echo json_encode([
        'success' => true,
        'data'    => $data,
        'total'   => count($data),
        'stats'   => [
            'on_time' => $onTimeCount,
            'late'    => $lateCount
        ],
        'session' => [
            'id'             => $session['id'],
            'session_date'   => $session['session_date'],
            'session_date_th'=> formatThaiDate($session['session_date']),
            'start_time'     => date('H:i', strtotime($session['start_time'])),
            'end_time'       => date('H:i', strtotime($session['end_time'])),
            'late_threshold' => date('H:i', strtotime($session['late_threshold'])),
            'status'         => $session['status']
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
