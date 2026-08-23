<?php
/**
 * Authentication & Authorization Helper
 * ระบบตรวจสอบการเข้าสู่ระบบและสิทธิ์การใช้งานตาม Role
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/csrf.php';

/**
 * ตรวจสอบว่าเข้าสู่ระบบอยู่หรือไม่
 * @return bool
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * ดึงข้อมูลผู้ใช้ปัจจุบันจาก Session
 * @return array|null
 */
function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_id'] ?? null,
        'username'  => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'email'     => $_SESSION['email'] ?? '',
        'role'      => $_SESSION['role'] ?? '',
        'avatar'    => $_SESSION['avatar'] ?? null,
        'role_id'   => $_SESSION['role_id'] ?? null, // teacher_id or student_id
        'classroom_id' => $_SESSION['classroom_id'] ?? null, // student's classroom
        'code'      => $_SESSION['user_code'] ?? '' // student_code or teacher_code
    ];
}

/**
 * บังคับให้ต้องเข้าสู่ระบบก่อน หากยังไม่ Login ให้ Redirect ไปหน้า login.php
 */
function requireAuth(): void {
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = 'กรุณาเข้าสู่ระบบก่อนเข้าใช้งาน';
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

/**
 * บังคับตรวจสอบสิทธิ์ (Role)
 * @param array|string $allowedRoles บทบาทที่อนุญาต เช่น 'admin' หรือ ['admin', 'teacher']
 */
function requireRole($allowedRoles): void {
    requireAuth();
    
    $user = getCurrentUser();
    $roles = is_array($allowedRoles) ? $allowedRoles : [$allowedRoles];
    
    if (!in_array($user['role'], $roles)) {
        http_response_code(403);
        if (file_exists(ROOT_PATH . 'errors/403.php')) {
            require ROOT_PATH . 'errors/403.php';
        } else {
            echo '<div style="text-align:center; padding:50px; font-family:sans-serif;"><h3>403 - ปฏิเสธการเข้าถึง</h3><p>คุณไม่มีสิทธิ์เข้าถึงหน้านี้</p><a href="' . BASE_URL . '">กลับหน้าหลัก</a></div>';
        }
        exit;
    }
}

/**
 * หากผู้ใช้ Login อยู่แล้ว ให้ Redirect ไปยัง Dashboard ตามบทบาทของตนเอง
 */
function redirectLoggedInUser(): void {
    if (isLoggedIn()) {
        $role = $_SESSION['role'] ?? '';
        switch ($role) {
            case 'admin':
                header('Location: ' . BASE_URL . 'admin/dashboard.php');
                exit;
            case 'teacher':
                header('Location: ' . BASE_URL . 'teacher/dashboard.php');
                exit;
            case 'student':
                header('Location: ' . BASE_URL . 'student/dashboard.php');
                exit;
            default:
                header('Location: ' . BASE_URL . 'logout.php');
                exit;
        }
    }
}

/**
 * อัปเดตข้อมูล Session ของผู้ใช้
 * @param array $data
 */
function updateUserSession(array $data): void {
    foreach ($data as $key => $val) {
        $_SESSION[$key] = $val;
    }
}
