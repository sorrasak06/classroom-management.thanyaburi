<?php
/**
 * CSRF Protection Helper
 * ระบบป้องกันการโจมตีแบบ Cross-Site Request Forgery
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * สร้างหรือดึง CSRF Token ปัจจุบัน
 * @return string
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * สร้าง Hidden Input สำหรับใส่ใน Form
 * @return string HTML input element
 */
function getCsrfField(): string {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * ตรวจสอบความถูกต้องของ CSRF Token
 * @param string|null $token
 * @return bool
 */
function validateCsrfToken(?string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * ตรวจสอบ CSRF Token หากไม่ถูกต้องให้ตัดการทำงานและแสดงหน้า 403 ทันที
 */
function verifyCsrfOrDie(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validateCsrfToken()) {
            http_response_code(403);
            if (file_exists(ROOT_PATH . 'errors/403.php')) {
                require ROOT_PATH . 'errors/403.php';
            } else {
                echo '<div style="text-align:center; padding:50px; font-family:sans-serif;"><h3>403 Forbidden</h3><p>CSRF Token ไม่ถูกต้อง หรือหมดอายุ กรุณารีเฟรชหน้าเว็บและลองใหม่</p></div>';
            }
            exit;
        }
    }
}
