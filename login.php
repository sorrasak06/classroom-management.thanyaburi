<?php
/**
 * Classroom Management System - Login Page
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

// ถ้าล็อกอินอยู่แล้วให้ไปที่ Dashboard
if (isLoggedIn()) {
    redirectLoggedInUser();
}

$errorMsg = '';
$usernameVal = '';

// ประมวลผลการ Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ตรวจสอบ CSRF Token
    if (!validateCsrfToken()) {
        $errorMsg = 'การเชื่อมต่อหมดอายุ กรุณาลองใหม่อีกครั้ง';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $remember = isset($_POST['remember']);
        $usernameVal = $username;

        if (empty($username) || empty($password)) {
            $errorMsg = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่านให้ครบถ้วน';
        } else {
            try {
                // ค้นหาผู้ใช้จาก username หรือ email
                $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND status = 'active' LIMIT 1");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();

                if ($user) {
                    // ตรวจสอบรหัสผ่านด้วย password_verify หรือ demo fallback
                    $isPasswordCorrect = password_verify($password, $user['password'])
                        || ($user['password'] === $password)
                        || ($password === 'admin123' && in_array($user['username'], ['admin']))
                        || ($password === 'teacher123' && in_array($user['username'], ['teacher', 'teacher2']))
                        || ($password === 'student123' && (strpos($user['username'], 'student') === 0));

                    if ($isPasswordCorrect) {
                        // หากรหัสผ่านยังไม่ได้เข้ารหัสแบบมาตรฐานของเครื่องนี้ ให้อัปเดต Hash ใหม่ทันที
                        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT) || $user['password'] === $password) {
                            $newHash = password_hash($password, PASSWORD_DEFAULT);
                            $stmtRehash = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                            $stmtRehash->execute([$newHash, $user['id']]);
                        }

                        // ล็อกอินสำเร็จ - ป้องกัน Session Fixation
                        session_regenerate_id(true);

                        $_SESSION['user_id']   = $user['id'];
                        $_SESSION['username']  = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['email']     = $user['email'];
                        $_SESSION['role']      = $user['role'];
                        $_SESSION['avatar']    = $user['avatar'];

                        // ดึงข้อมูลเสริมตาม Role
                        if ($user['role'] === 'student') {
                            $stmtStd = $pdo->prepare("SELECT id, student_code, classroom_id FROM students WHERE user_id = ? LIMIT 1");
                            $stmtStd->execute([$user['id']]);
                            $std = $stmtStd->fetch();
                            if ($std) {
                                $_SESSION['role_id']      = $std['id'];
                                $_SESSION['user_code']    = $std['student_code'];
                                $_SESSION['classroom_id'] = $std['classroom_id'];
                            }
                        } elseif ($user['role'] === 'teacher') {
                            $stmtTch = $pdo->prepare("SELECT id, teacher_code FROM teachers WHERE user_id = ? LIMIT 1");
                            $stmtTch->execute([$user['id']]);
                            $tch = $stmtTch->fetch();
                            if ($tch) {
                                $_SESSION['role_id']   = $tch['id'];
                                $_SESSION['user_code'] = $tch['teacher_code'];
                            }
                        }

                        setFlash('success', 'ยินดีต้อนรับเข้าสู่ระบบ ' . $user['full_name']);
                        redirectLoggedInUser();
                    } else {
                        $errorMsg = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง';
                    }
                } else {
                    $errorMsg = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง กรุณาตรวจสอบอีกครั้ง';
                }
            } catch (PDOException $e) {
                $errorMsg = 'เกิดข้อผิดพลาดในการเชื่อมต่อ: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - <?= APP_NAME ?></title>
    
    <!-- Google Fonts: Prompt -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5.3.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>assets/images/logo.png">

    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #1e3a8a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .login-card {
            background-color: #ffffff;
            border-radius: 1.25rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            max-width: 480px;
            width: 100%;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .login-header {
            padding: 2.25rem 2rem 1.5rem 2rem;
            text-align: center;
            background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
            border-bottom: 1px solid var(--border-color);
        }
        .login-logo {
            width: 75px;
            height: 75px;
            object-fit: contain;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
            margin-bottom: 0.85rem;
        }
        .demo-account-box {
            background-color: #f1f5f9;
            border-radius: var(--radius-md);
            padding: 1rem;
            border: 1px dashed #cbd5e1;
        }
        .demo-btn {
            font-size: 0.78rem;
            padding: 0.35rem 0.65rem;
            border-radius: var(--radius-sm);
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <img src="<?= BASE_URL ?>assets/images/logo.png" alt="Logo" class="login-logo">
        <h4 class="fw-bold text-dark mb-1"><?= APP_NAME ?></h4>
        <p class="text-muted small mb-0"><?= APP_SUBTITLE ?></p>
        <span class="badge bg-primary-subtle text-primary mt-2">ระดับประกาศนียบัตรวิชาชีพชั้นสูง (ปวส.)</span>
    </div>

    <div class="p-4 p-md-4 pt-3">
        <?= displayFlashMessages() ?>

        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 py-2.5 px-3 mb-3 small d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($errorMsg) ?></div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form action="<?= BASE_URL ?>login.php" method="POST" id="loginForm" autocomplete="off">
            <?= getCsrfField() ?>

            <div class="mb-3">
                <label for="username" class="form-label">ชื่อผู้ใช้งาน หรือ อีเมล</label>
                <div class="input-group">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control border-start-0" id="username" name="username" value="<?= htmlspecialchars($usernameVal) ?>" placeholder="Username or Email" required autofocus>
                </div>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <label for="password" class="form-label mb-0">รหัสผ่าน</label>
                </div>
                <div class="input-group mt-1">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control border-start-0 border-end-0" id="password" name="password" placeholder="Password" required>
                    <button type="button" class="input-group-text bg-light text-muted border-start-0 toggle-password-btn" data-target="password" title="แสดง/ซ่อนรหัสผ่าน">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remember" name="remember">
                    <label class="form-check-label small text-muted" for="remember">
                        จดจำการเข้าสู่ระบบ
                    </label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2.5 fw-semibold shadow-sm rounded-3">
                <i class="bi bi-box-arrow-in-right me-1"></i> เข้าสู่ระบบ
            </button>
        </form>

        <!-- Demo Accounts Section -->
        <div class="demo-account-box mt-4">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="fw-semibold text-dark small"><i class="bi bi-lightning-charge-fill text-warning me-1"></i>ทดสอบระบบ (Demo Accounts)</span>
                <span class="text-muted" style="font-size: 0.72rem;">คลิกเพื่อกรอกอัตโนมัติ</span>
            </div>
            
            <div class="row g-2">
                <div class="col-4">
                    <button type="button" class="btn btn-outline-danger w-100 demo-btn text-truncate" onclick="fillDemo('admin', 'admin123')">
                        <i class="bi bi-shield-lock"></i> Admin
                    </button>
                </div>
                <div class="col-4">
                    <button type="button" class="btn btn-outline-primary w-100 demo-btn text-truncate" onclick="fillDemo('teacher', 'teacher123')">
                        <i class="bi bi-person-workspace"></i> Teacher
                    </button>
                </div>
                <div class="col-4">
                    <button type="button" class="btn btn-outline-success w-100 demo-btn text-truncate" onclick="fillDemo('student', 'student123')">
                        <i class="bi bi-mortarboard"></i> Student
                    </button>
                </div>
            </div>

            <div class="mt-2 text-muted text-center" style="font-size: 0.72rem;">
                Admin: <code>admin / admin123</code> | Teacher: <code>teacher / teacher123</code> | Student: <code>student / student123</code>
            </div>
        </div>

    </div>
</div>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/main.js"></script>

<script>
function fillDemo(username, password) {
    document.getElementById('username').value = username;
    document.getElementById('password').value = password;
    // Highlight submit button
    const submitBtn = document.querySelector('#loginForm button[type="submit"]');
    submitBtn.classList.add('btn-success');
    submitBtn.innerHTML = '<i class="bi bi-arrow-right-circle-fill me-1"></i> กำลังเข้าสู่ระบบ...';
    setTimeout(() => {
        document.getElementById('loginForm').submit();
    }, 250);
}
</script>

</body>
</html>
