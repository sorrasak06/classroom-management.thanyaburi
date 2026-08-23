<?php
/**
 * Classroom Management System - Configuration File
 * ระบบบริหารจัดการชั้นเรียน สำหรับโครงงานระดับ ปวส.
 */

// เริ่มต้น Session หากยังไม่ได้เริ่ม
if (session_status() === PHP_SESSION_NONE) {
    // กำหนด Session Security Settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// ตั้งค่า Timezone ประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// ข้อมูลพื้นฐานของระบบ
define('APP_NAME', 'ระบบบริหารจัดการชั้นเรียน');
define('APP_NAME_EN', 'Classroom Management System');
define('APP_SUBTITLE', 'วิทยาลัยเทคนิคธัญบุรี (Thanyaburi Technical College)');
define('APP_VERSION', '1.0.0');

// ตั้งค่าการเชื่อมต่อฐานข้อมูล MySQL (XAMPP Default)
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'classroom_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// คำนวณ Base URL อัตโนมัติ รองรับทั้ง Localhost, Subfolder และ Custom Port
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

// ตัดโฟลเดอร์ย่อยถ้าอยู่ใน admin, teacher, student, profile, notifications, errors, api
$cleanScriptDir = preg_replace('/(\/(admin|teacher|student|profile|notifications|errors|api))$/', '', $scriptDir);
$cleanScriptDir = rtrim($cleanScriptDir, '/');

define('BASE_URL', $protocol . $host . $cleanScriptDir . '/');
define('ROOT_PATH', __DIR__ . '/');
define('UPLOAD_PATH', ROOT_PATH . 'assets/uploads/');

// สร้างการเชื่อมต่อฐานข้อมูลด้วย PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // หากไม่สามารถเชื่อมต่อฐานข้อมูลได้ ให้แสดงคำแนะนำอย่างชัดเจน
    $dbError = $e->getMessage();
    // ถ้าหน้าปัจจุบันไม่ใช่หน้าสำหรับแสดง error ให้จัดรูปแบบเตือนสวยงาม
    if (basename($_SERVER['PHP_SELF']) !== '500.php') {
        ?>
        <!DOCTYPE html>
        <html lang="th">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>เชื่อมต่อฐานข้อมูลไม่สำเร็จ - <?= APP_NAME ?></title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <style>
                body { font-family: 'Prompt', sans-serif; background-color: #f8fafc; color: #1e293b; }
            </style>
        </head>
        <body class="d-flex align-items-center justify-content-center min-vh-100 p-4">
            <div class="card border-0 shadow-lg p-4 p-md-5" style="max-width: 600px; border-radius: 1.25rem;">
                <div class="text-center mb-4">
                    <div class="display-3 text-danger mb-2">⚠️</div>
                    <h3 class="fw-bold text-danger">ไม่สามารถเชื่อมต่อฐานข้อมูลได้</h3>
                    <p class="text-muted">กรุณาตรวจสอบการตั้งค่า MySQL ใน XAMPP หรือไฟล์ config.php</p>
                </div>
                <div class="alert alert-warning py-3">
                    <strong>ข้อผิดพลาด:</strong><br>
                    <code><?= htmlspecialchars($dbError) ?></code>
                </div>
                <h6 class="fw-bold mt-3">ขั้นตอนการแก้ไขสำหรับ XAMPP:</h6>
                <ol class="text-muted small ps-3">
                    <li>เปิดโปรแกรม <strong>XAMPP Control Panel</strong> แล้วกดปุ่ม <strong>Start</strong> ที่ <code>Apache</code> และ <code>MySQL</code></li>
                    <li>เปิดเบราว์เซอร์ไปที่ <code>http://localhost/phpmyadmin</code></li>
                    <li>สร้างฐานข้อมูลชื่อ <code>classroom_db</code> (Collation: <code>utf8mb4_unicode_ci</code>)</li>
                    <li>กดปุ่ม <strong>Import</strong> แล้วเลือกไฟล์ <code>database.sql</code> ในโฟลเดอร์โปรเจกต์</li>
                    <li>รีเฟรชหน้านี้อีกครั้ง</li>
                </ol>
                <div class="text-center mt-3">
                    <button onclick="location.reload()" class="btn btn-primary px-4 py-2 rounded-pill fw-semibold">
                        ลองเชื่อมต่อใหม่อีกครั้ง
                    </button>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
