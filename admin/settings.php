<?php
/**
 * Admin - System Settings
 * การตั้งค่าระบบและข้อมูลสถาบันการศึกษา
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$pageTitle = 'การตั้งค่าระบบ';

// จัดการการบันทึกการตั้งค่า
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    verifyCsrfOrDie();

    $settingsToUpdate = [
        'school_name'           => trim($_POST['school_name'] ?? ''),
        'school_name_en'        => trim($_POST['school_name_en'] ?? ''),
        'system_title'          => trim($_POST['system_title'] ?? ''),
        'current_academic_year' => trim($_POST['current_academic_year'] ?? '2567'),
        'current_term'          => trim($_POST['current_term'] ?? '1'),
        'director_name'         => trim($_POST['director_name'] ?? ''),
        'contact_email'         => trim($_POST['contact_email'] ?? ''),
        'contact_phone'         => trim($_POST['contact_phone'] ?? ''),
        'address'               => trim($_POST['address'] ?? '')
    ];

    try {
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM system_settings WHERE setting_key = ?");
        $stmtInsert = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_group, updated_at) VALUES (?, ?, 'general', NOW())");
        $stmtUpdate = $pdo->prepare("UPDATE system_settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");

        foreach ($settingsToUpdate as $key => $val) {
            $stmtCheck->execute([$key]);
            if ($stmtCheck->fetchColumn() > 0) {
                $stmtUpdate->execute([$val, $key]);
            } else {
                $stmtInsert->execute([$key, $val]);
            }
        }
        setFlash('success', 'บันทึกการตั้งค่าระบบเรียบร้อยแล้ว');
    } catch (PDOException $e) {
        setFlash('error', 'เกิดข้อผิดพลาดในการบันทึก: ' . $e->getMessage());
    }

    header('Location: ' . BASE_URL . 'admin/settings.php');
    exit;
}

// ดึงการตั้งค่าทั้งหมด
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // default fallbacks
}

function getVal($key, $default = '') {
    global $settings;
    return $settings[$key] ?? $default;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="row g-4">
    <!-- Left Column: Settings Form -->
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h5 class="card-title mb-0 fw-bold text-dark">
                    <i class="bi bi-gear-wide-connected text-primary me-2"></i>ข้อมูลสถาบันการศึกษาและการตั้งค่าทั่วไป
                </h5>
            </div>
            <div class="card-body p-4">
                <form action="<?= BASE_URL ?>admin/settings.php" method="POST">
                    <?= getCsrfField() ?>
                    <input type="hidden" name="action" value="save_settings">

                    <h6 class="fw-bold text-primary mb-3"><i class="bi bi-building me-1"></i> ข้อมูลสถาบัน</h6>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ชื่อสถานศึกษา (ภาษาไทย)</label>
                            <input type="text" name="school_name" class="form-control" value="<?= htmlspecialchars(getVal('school_name', 'วิทยาลัยเทคนิคธัญบุรี')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ชื่อสถานศึกษา (ภาษาอังกฤษ)</label>
                            <input type="text" name="school_name_en" class="form-control" value="<?= htmlspecialchars(getVal('school_name_en', 'Thanyaburi Technical College')) ?>">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ชื่อระบบแอปพลิเคชัน</label>
                            <input type="text" name="system_title" class="form-control" value="<?= htmlspecialchars(getVal('system_title', APP_NAME)) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ผู้อำนวยการสถานศึกษา</label>
                            <input type="text" name="director_name" class="form-control" value="<?= htmlspecialchars(getVal('director_name', 'ดร.สมศักดิ์ วัฒนากุล')) ?>">
                        </div>
                    </div>

                    <hr class="my-4">

                    <h6 class="fw-bold text-primary mb-3"><i class="bi bi-calendar-event me-1"></i> ปีการศึกษาและภาคเรียนปัจจุบัน</h6>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ปีการศึกษา</label>
                            <input type="text" name="current_academic_year" class="form-control" value="<?= htmlspecialchars(getVal('current_academic_year', '2567')) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">ภาคเรียนที่</label>
                            <select name="current_term" class="form-select">
                                <option value="1" <?= (getVal('current_term', '1') === '1') ? 'selected' : '' ?>>ภาคเรียนที่ 1</option>
                                <option value="2" <?= (getVal('current_term', '1') === '2') ? 'selected' : '' ?>>ภาคเรียนที่ 2</option>
                                <option value="3" <?= (getVal('current_term', '1') === '3') ? 'selected' : '' ?>>ภาคฤดูร้อน</option>
                            </select>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h6 class="fw-bold text-primary mb-3"><i class="bi bi-telephone-inbound me-1"></i> ข้อมูลการติดต่อ</h6>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">อีเมลติดต่อกลาง</label>
                            <input type="email" name="contact_email" class="form-control" value="<?= htmlspecialchars(getVal('contact_email', 'contact@thanya.ac.th')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">เบอร์โทรศัพท์ติดต่อ</label>
                            <input type="text" name="contact_phone" class="form-control" value="<?= htmlspecialchars(getVal('contact_phone', '02-577-1111')) ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">ที่อยู่สถานศึกษา</label>
                        <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars(getVal('address', 'เลขที่ 1 หมู่ 3 ถ.รังสิต-นครนายก ต.รังสิต อ.ธัญบุรี จ.ปทุมธานี 12110')) ?></textarea>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold rounded-pill shadow-sm">
                            <i class="bi bi-save me-1"></i> บันทึกการเปลี่ยนแปลง
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: System Status & Grading Scheme Reference -->
    <div class="col-lg-4">
        <!-- System Info Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-info-circle-fill text-info me-1"></i> ข้อมูลสภาพแวดล้อมระบบ (Server)
                </h6>
            </div>
            <div class="card-body p-3">
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">PHP Version:</span>
                        <span class="fw-semibold font-monospace"><?= phpversion() ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Database Server:</span>
                        <span class="fw-semibold">MySQL / MariaDB</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Character Set:</span>
                        <span class="fw-semibold font-monospace">utf8mb4</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Web Server:</span>
                        <span class="fw-semibold"><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'Apache') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Timezone:</span>
                        <span class="fw-semibold">Asia/Bangkok (UTC+7)</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between px-0">
                        <span class="text-muted">Session Status:</span>
                        <span class="badge bg-success-subtle text-success">Active</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Thai Standard Grading Scale Reference -->
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0 text-dark">
                    <i class="bi bi-award-fill text-warning me-1"></i> เกณฑ์การตัดเกรดมาตรฐาน (ปวส.)
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0 small text-center">
                        <thead class="table-light">
                            <tr>
                                <th>ช่วงคะแนน</th>
                                <th>เกรด</th>
                                <th>แต้มระดับ</th>
                                <th>ความหมาย</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>80 - 100</td><td><strong class="text-success">A (4.0)</strong></td><td>4.0</td><td>ดีเยี่ยม (Excellent)</td></tr>
                            <tr><td>75 - 79</td><td><strong class="text-primary">B+ (3.5)</strong></td><td>3.5</td><td>ดีมาก (Very Good)</td></tr>
                            <tr><td>70 - 74</td><td><strong class="text-primary">B (3.0)</strong></td><td>3.0</td><td>ดี (Good)</td></tr>
                            <tr><td>65 - 69</td><td><strong class="text-info">C+ (2.5)</strong></td><td>2.5</td><td>ค่อนข้างดี (Fairly Good)</td></tr>
                            <tr><td>60 - 64</td><td><strong class="text-info">C (2.0)</strong></td><td>2.0</td><td>พอใช้ (Fair)</td></tr>
                            <tr><td>55 - 59</td><td><strong class="text-warning">D+ (1.5)</strong></td><td>1.5</td><td>อ่อน (Poor)</td></tr>
                            <tr><td>50 - 54</td><td><strong class="text-warning">D (1.0)</strong></td><td>1.0</td><td>อ่อนมาก (Very Poor)</td></tr>
                            <tr><td>0 - 49</td><td><strong class="text-danger">F (0.0)</strong></td><td>0.0</td><td>ตก (Failed)</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
