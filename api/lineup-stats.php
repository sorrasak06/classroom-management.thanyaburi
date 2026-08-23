<?php
/**
 * API: Line-up Attendance Statistics
 * GET endpoint - ดึงสถิติการเข้าแถวสำหรับ Dashboard
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
$role = $currentUser['role'];

try {
    // ดึงจำนวนนักเรียนทั้งหมดในระบบ
    $totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

    // ดึง Session ของวันนี้
    $stmtS = $pdo->prepare("SELECT * FROM lineup_sessions WHERE session_date = CURDATE() ORDER BY id DESC LIMIT 1");
    $stmtS->execute();
    $todaySession = $stmtS->fetch();

    $todayStats = [
        'has_session' => (bool)$todaySession,
        'status'      => $todaySession['status'] ?? 'no_session',
        'attended'    => 0,
        'on_time'     => 0,
        'late'        => 0,
        'absent'      => 0,
        'pct'         => 0.0,
        'total_students' => $totalStudents
    ];

    if ($todaySession) {
        $stmtA = $pdo->prepare("
            SELECT 
                COUNT(*) as total_attended,
                SUM(CASE WHEN status = 'on_time' THEN 1 ELSE 0 END) as on_time_count,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count
            FROM lineup_attendance
            WHERE lineup_session_id = ?
        ");
        $stmtA->execute([$todaySession['id']]);
        $attRow = $stmtA->fetch();

        $attended = (int)($attRow['total_attended'] ?? 0);
        $onTime   = (int)($attRow['on_time_count'] ?? 0);
        $late     = (int)($attRow['late_count'] ?? 0);
        $absent   = max(0, $totalStudents - $attended);
        $pct      = ($totalStudents > 0) ? round(($attended / $totalStudents) * 100, 1) : 0;

        $todayStats['attended'] = $attended;
        $todayStats['on_time']  = $onTime;
        $todayStats['late']     = $late;
        $todayStats['absent']   = $absent;
        $todayStats['pct']      = $pct;
    }

    $personalStats = null;
    if ($role === 'student') {
        $studentId = (int)($currentUser['role_id'] ?? 0);
        $totalSessions = (int)$pdo->query("SELECT COUNT(*) FROM lineup_sessions")->fetchColumn();

        $stmtP = $pdo->prepare("
            SELECT 
                COUNT(*) as total_attended,
                SUM(CASE WHEN status = 'on_time' THEN 1 ELSE 0 END) as on_time,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
            FROM lineup_attendance
            WHERE student_id = ?
        ");
        $stmtP->execute([$studentId]);
        $pRow = $stmtP->fetch();

        $pAtt    = (int)($pRow['total_attended'] ?? 0);
        $pOnTime = (int)($pRow['on_time'] ?? 0);
        $pLate   = (int)($pRow['late'] ?? 0);
        $pAbsent = max(0, $totalSessions - $pAtt);
        $pPct    = ($totalSessions > 0) ? round(($pAtt / $totalSessions) * 100, 1) : 0;

        // เช็กสิทธิ์วันนี้
        $stmtTodayPersonal = $pdo->prepare("
            SELECT la.check_in_time, la.status
            FROM lineup_attendance la
            JOIN lineup_sessions ls ON la.lineup_session_id = ls.id
            WHERE la.student_id = ? AND ls.session_date = CURDATE()
            LIMIT 1
        ");
        $stmtTodayPersonal->execute([$studentId]);
        $todayCheck = $stmtTodayPersonal->fetch();

        $personalStats = [
            'total_sessions' => $totalSessions,
            'attended'       => $pAtt,
            'on_time'        => $pOnTime,
            'late'           => $pLate,
            'absent'         => $pAbsent,
            'pct'            => $pPct,
            'checked_today'  => (bool)$todayCheck,
            'today_detail'   => $todayCheck ? [
                'check_in_time' => date('H:i:s', strtotime($todayCheck['check_in_time'])),
                'status'        => $todayCheck['status'],
                'status_text'   => ($todayCheck['status'] === 'on_time') ? 'มาตรงเวลา' : 'มาสาย'
            ] : null
        ];
    }

    echo json_encode([
        'success'  => true,
        'today'    => $todayStats,
        'personal' => $personalStats
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
