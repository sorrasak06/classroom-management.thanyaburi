<?php
/**
 * Classroom Management System - Core Helper Functions
 * ฟังก์ชันช่วยเหลือทั่วไป การแปลงวันที่ไทย การจัดการไฟล์ Flash Message และ Badge
 */

/**
 * กรองข้อมูลป้องกัน XSS
 * @param mixed $data
 * @return mixed
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}

/**
 * แปลงวันที่เป็นรูปแบบภาษาไทย
 * @param string|null $dateStr เช่น '2026-08-17' หรือ '2026-08-17 14:30:00'
 * @param bool $showTime แสดงเวลาด้วยหรือไม่
 * @param bool $shortMonth ใช้ชื่อเดือนแบบย่อหรือไม่
 * @return string
 */
function formatThaiDate(?string $dateStr, bool $showTime = false, bool $shortMonth = false): string {
    if (empty($dateStr) || $dateStr === '0000-00-00' || $dateStr === '0000-00-00 00:00:00') {
        return '-';
    }

    $thaiMonthsFull = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];

    $thaiMonthsShort = [
        1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
        5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
        9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
    ];

    $time = strtotime($dateStr);
    if (!$time) return '-';

    $day = date('j', $time);
    $monthNum = (int)date('n', $time);
    $year = (int)date('Y', $time) + 543; // แปลง ค.ศ. เป็น พ.ศ.

    $monthName = $shortMonth ? ($thaiMonthsShort[$monthNum] ?? '') : ($thaiMonthsFull[$monthNum] ?? '');

    $result = "{$day} {$monthName} {$year}";

    if ($showTime) {
        $result .= ' เวลา ' . date('H:i', $time) . ' น.';
    }

    return $result;
}

/**
 * แปลงวันและเวลาภาษาไทย
 * @param string|null $datetimeStr
 * @return string
 */
function formatThaiDateTime(?string $datetimeStr): string {
    return formatThaiDate($datetimeStr, true, true);
}

/**
 * จัดรูปแบบเวลา เช่น 08:30 น.
 * @param string|null $timeStr
 * @return string
 */
function formatTime(?string $timeStr): string {
    if (empty($timeStr)) return '-';
    $time = strtotime($timeStr);
    return $time ? date('H:i', $time) . ' น.' : '-';
}

/**
 * ดึงชื่อวันภาษาไทยจากตัวเลขวัน (1=จันทร์, ..., 7=อาทิตย์)
 * @param int $dayNum
 * @return string
 */
function getDayThaiName(int $dayNum): string {
    $days = [
        1 => 'จันทร์',
        2 => 'อังคาร',
        3 => 'พุธ',
        4 => 'พฤหัสบดี',
        5 => 'ศุกร์',
        6 => 'เสาร์',
        7 => 'อาทิตย์'
    ];
    return $days[$dayNum] ?? '-';
}

/**
 * คำนวณตัดเกรดจากคะแนนรวม 100
 * @param float $score
 * @return string
 */
function calcGrade(float $score): string {
    if ($score >= 80) return 'A';
    if ($score >= 75) return 'B+';
    if ($score >= 70) return 'B';
    if ($score >= 65) return 'C+';
    if ($score >= 60) return 'C';
    if ($score >= 55) return 'D+';
    if ($score >= 50) return 'D';
    return 'F';
}

/**
 * คำนวณ Grade Point (แต้มระดับคะแนน)
 * @param string $grade
 * @return float
 */
function getGradePoint(string $grade): float {
    switch (strtoupper(trim($grade))) {
        case 'A':  return 4.0;
        case 'B+': return 3.5;
        case 'B':  return 3.0;
        case 'C+': return 2.5;
        case 'C':  return 2.0;
        case 'D+': return 1.5;
        case 'D':  return 1.0;
        default:   return 0.0;
    }
}

/**
 * Badge สถานะการเช็กชื่อ
 * @param string $status
 * @return string HTML Badge
 */
function getAttendanceBadge(string $status): string {
    switch ($status) {
        case 'present':
            return '<span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill"><i class="bi bi-check-circle me-1"></i>มาเรียน</span>';
        case 'absent':
            return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 rounded-pill"><i class="bi bi-x-circle me-1"></i>ขาดเรียน</span>';
        case 'late':
            return '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1 rounded-pill"><i class="bi bi-clock-history me-1"></i>สาย</span>';
        case 'leave':
            return '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2.5 py-1 rounded-pill"><i class="bi bi-envelope-paper me-1"></i>ลา</span>';
        default:
            return '<span class="badge bg-secondary-subtle text-secondary px-2.5 py-1 rounded-pill">ไม่ระบุ</span>';
    }
}

/**
 * Badge บทบาทผู้ใช้
 * @param string $role
 * @return string HTML Badge
 */
function getRoleBadge(string $role): string {
    switch ($role) {
        case 'admin':
            return '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 rounded-pill"><i class="bi bi-shield-lock me-1"></i>ผู้ดูแลระบบ</span>';
        case 'teacher':
            return '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2.5 py-1 rounded-pill"><i class="bi bi-person-workspace me-1"></i>ครูผู้สอน</span>';
        case 'student':
            return '<span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill"><i class="bi bi-mortarboard me-1"></i>นักศึกษา</span>';
        default:
            return '<span class="badge bg-secondary-subtle text-secondary px-2.5 py-1 rounded-pill">' . htmlspecialchars($role) . '</span>';
    }
}

/**
 * Badge สถานะการส่งงาน
 * @param string|null $status submitted, graded, late หรือ null (ยังไม่ส่ง)
 * @param string|null $dueDate วันที่ครบกำหนด
 * @return string HTML Badge
 */
function getSubmissionBadge(?string $status, ?string $dueDate = null): string {
    if (empty($status)) {
        if ($dueDate && strtotime($dueDate) < time()) {
            return '<span class="badge bg-danger text-white px-2.5 py-1 rounded-pill"><i class="bi bi-exclamation-triangle me-1"></i>เกินกำหนด (ยังไม่ส่ง)</span>';
        }
        return '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2.5 py-1 rounded-pill"><i class="bi bi-hourglass me-1"></i>ยังไม่ส่ง</span>';
    }

    switch ($status) {
        case 'graded':
            return '<span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill"><i class="bi bi-check2-all me-1"></i>ตรวจแล้ว</span>';
        case 'late':
            return '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1 rounded-pill"><i class="bi bi-alarm me-1"></i>ส่งล่าช้า</span>';
        case 'submitted':
        default:
            return '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2.5 py-1 rounded-pill"><i class="bi bi-send-check me-1"></i>ส่งแล้ว (รอตรวจ)</span>';
    }
}

/**
 * จัดการอัปโหลดไฟล์อย่างปลอดภัย
 * @param array $file $_FILES['input_name']
 * @param string $subDir โฟลเดอร์ปลายทาง เช่น 'avatars', 'assignments', 'submissions', 'announcements'
 * @param array $allowedExtensions นามสกุลไฟล์ที่อนุญาต
 * @param int $maxSizeMB ขนาดไฟล์สูงสุด (MB)
 * @return array ['success' => bool, 'filename' => string|null, 'error' => string|null]
 */
function uploadFile(array $file, string $subDir, array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', 'txt'], int $maxSizeMB = 10): array {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'filename' => null, 'error' => 'พารามิเตอร์ไฟล์ไม่ถูกต้อง'];
    }

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'filename' => null, 'error' => null];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'filename' => null, 'error' => 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์ (Code: ' . $file['error'] . ')'];
    }

    // ตรวจสอบขนาดไฟล์
    if ($file['size'] > ($maxSizeMB * 1024 * 1024)) {
        return ['success' => false, 'filename' => null, 'error' => "ขนาดไฟล์ต้องไม่เกิน {$maxSizeMB} MB"];
    }

    // ตรวจสอบนามสกุลไฟล์
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions)) {
        return ['success' => false, 'filename' => null, 'error' => 'ไม่อนุญาตให้อัปโหลดไฟล์นามสกุล .' . $ext];
    }

    // สร้างชื่อไฟล์ใหม่ที่ไม่ซ้ำกัน
    $newFileName = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $targetDir = ROOT_PATH . 'assets/uploads/' . trim($subDir, '/') . '/';

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $destination = $targetDir . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'filename' => null, 'error' => 'ไม่สามารถบันทึกไฟล์ลงในระบบได้'];
    }

    return ['success' => true, 'filename' => $newFileName, 'error' => null];
}

/**
 * ลบไฟล์อัปโหลด
 * @param string $subDir
 * @param string|null $filename
 */
function deleteUploadedFile(string $subDir, ?string $filename): void {
    if (!empty($filename)) {
        $path = ROOT_PATH . 'assets/uploads/' . trim($subDir, '/') . '/' . $filename;
        if (file_exists($path) && is_file($path)) {
            @unlink($path);
        }
    }
}

/**
 * ตั้งค่า Flash Message
 * @param string $type success, error, warning, info
 * @param string $message
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash_' . $type] = $message;
}

/**
 * แสดง Flash Message และเคลียร์ออกจาก Session
 * @return string HTML Alert
 */
function displayFlashMessages(): string {
    $types = [
        'success' => ['class' => 'alert-success', 'icon' => 'bi-check-circle-fill'],
        'error'   => ['class' => 'alert-danger',  'icon' => 'bi-exclamation-octagon-fill'],
        'warning' => ['class' => 'alert-warning', 'icon' => 'bi-exclamation-triangle-fill'],
        'info'    => ['class' => 'alert-info',    'icon' => 'bi-info-circle-fill']
    ];

    $html = '';
    foreach ($types as $type => $conf) {
        $key = 'flash_' . $type;
        if (isset($_SESSION[$key])) {
            $msg = htmlspecialchars($_SESSION[$key], ENT_QUOTES, 'UTF-8');
            $html .= '<div class="alert ' . $conf['class'] . ' alert-dismissible fade show shadow-sm rounded-3 py-3 px-4 mb-4" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="bi ' . $conf['icon'] . ' fs-5 me-2.5"></i>
                            <div class="fw-medium">' . $msg . '</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                      </div>';
            unset($_SESSION[$key]);
        }
    }
    return $html;
}

/**
 * ดึงจำนวนการแจ้งเตือนที่ยังไม่ได้อ่าน
 * @param PDO $pdo
 * @param int $userId
 * @return int
 */
function getUnreadNotificationsCount(PDO $pdo, int $userId): int {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * สร้างการแจ้งเตือนใหม่
 * @param PDO $pdo
 * @param int $userId
 * @param string $title
 * @param string $message
 * @param string|null $link
 * @return bool
 */
function createNotification(PDO $pdo, int $userId, string $title, string $message, ?string $link = null): bool {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        return $stmt->execute([$userId, $title, $message, $link]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * ดึง URL รูป Avatar หรือรูปเริ่มต้น
 * @param string|null $avatar
 * @param string $name
 * @return string URL
 */
function getUserAvatarUrl(?string $avatar, string $name = 'User'): string {
    if (!empty($avatar) && file_exists(ROOT_PATH . 'assets/uploads/avatars/' . $avatar)) {
        return BASE_URL . 'assets/uploads/avatars/' . $avatar;
    }
    // Fallback UI Avatar generator with matching stylish colors
    $encodedName = urlencode(mb_substr($name, 0, 2));
    return "https://ui-avatars.com/api/?name={$encodedName}&background=3b82f6&color=ffffff&size=128&bold=true";
}

/**
 * ไฮไลต์เมนูที่กำลังเปิดอยู่
 * @param array|string $pages
 * @return string
 */
function isActiveNav($pages): string {
    $currentPage = basename($_SERVER['PHP_SELF']);
    $pagesArray = is_array($pages) ? $pages : [$pages];
    return in_array($currentPage, $pagesArray) ? 'active' : '';
}

/**
 * ฟังก์ชันลัดสำหรับ sanitize & htmlspecialchars
 * @param mixed $str
 * @return string
 */
function e($str): string {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * ดึงค่าการตั้งค่าระบบจากตาราง system_settings
 * @param PDO $pdo
 * @param string $key
 * @param string $default
 * @return string
 */
function getSetting(PDO $pdo, string $key, string $default = ''): string {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false && $val !== null) ? (string)$val : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * อัปเดตค่าการตั้งค่าระบบ
 * @param PDO $pdo
 * @param string $key
 * @param string $value
 * @return bool
 */
function updateSetting(PDO $pdo, string $key, string $value): bool {
    try {
        $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
        return $stmt->execute([$value, $key]);
    } catch (PDOException $e) {
        return false;
    }
}

