<?php
/**
 * Error 404 - Not Found
 */
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config.php';
}
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - ไม่พบหน้าที่ต้องการ - <?= APP_NAME ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 bg-light p-4">
    <div class="card border-0 shadow-lg p-4 p-md-5 text-center" style="max-width: 540px; border-radius: 1.25rem;">
        <div class="mb-3">
            <div class="badge bg-warning-subtle text-warning-emphasis p-3 rounded-circle d-inline-flex mb-2">
                <i class="bi bi-compass-fill" style="font-size: 3rem;"></i>
            </div>
        </div>
        <h1 class="display-5 fw-bold text-dark">404</h1>
        <h4 class="fw-bold text-dark mb-2">ไม่พบหน้าที่คุณต้องการ (Page Not Found)</h4>
        <p class="text-muted mb-4">
            หน้าที่คุณกำลังค้นหาอาจถูกย้าย ลบ หรือ URL ที่ระบุไม่ถูกต้อง กรุณาตรวจสอบลิงก์อีกครั้ง
        </p>
        <div class="d-flex gap-2 justify-content-center">
            <a href="<?= BASE_URL ?>" class="btn btn-primary px-4 py-2 rounded-pill">
                <i class="bi bi-house-door-fill me-1"></i> กลับสู่หน้าหลัก
            </a>
            <button onclick="history.back()" class="btn btn-outline-secondary px-4 py-2 rounded-pill">
                <i class="bi bi-arrow-left me-1"></i> ย้อนกลับ
            </button>
        </div>
    </div>
</body>
</html>
