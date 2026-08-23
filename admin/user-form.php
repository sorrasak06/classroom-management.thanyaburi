<?php
/**
 * Admin - Add / Edit User Form
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $userId > 0;
$pageTitle = $isEdit ? 'แก้ไขข้อมูลผู้ใช้งาน' : 'เพิ่มผู้ใช้งานใหม่';

// ดึงรายชื่อห้องเรียนสำหรับเลือกให้นักเรียน
$classrooms = $pdo->query("SELECT id, name, level FROM classrooms ORDER BY level ASC, name ASC")->fetchAll();

$errors = [];
$formData = [
    'username'     => '',
    'full_name'    => '',
    'email'        => '',
    'phone'        => '',
    'role'         => 'student',
    'status'       => 'active',
    'avatar'       => null,
    // Student specific
    'student_code' => '',
    'classroom_id' => '',
    'gender'       => 'male',
    'birth_date'   => '',
    'address'      => '',
    'parent_phone' => '',
    // Teacher specific
    'teacher_code' => '',
    'department'   => 'แผนกวิชาเทคโนโลยีสารสนเทศ',
    'position'     => 'ครูผู้ช่วย'
];

// หากเป็นโหมดแก้ไข ให้โหลดข้อมูลเดิม
if ($isEdit) {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               s.student_code, s.classroom_id, s.gender, s.birth_date, s.address, s.parent_phone,
               t.teacher_code, t.department, t.position
        FROM users u
        LEFT JOIN students s ON u.id = s.user_id
        LEFT JOIN teachers t ON u.id = t.user_id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $existing = $stmt->fetch();

    if (!$existing) {
        setFlash('error', 'ไม่พบข้อมูลผู้ใช้งานที่ต้องการแก้ไข');
        header('Location: ' . BASE_URL . 'admin/users.php');
        exit;
    }

    $formData = array_merge($formData, $existing);
}

// ประมวลผลเมื่อ Submit Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfOrDie();

    $formData['username']     = trim($_POST['username'] ?? '');
    $formData['full_name']    = trim($_POST['full_name'] ?? '');
    $formData['email']        = trim($_POST['email'] ?? '');
    $formData['phone']        = trim($_POST['phone'] ?? '');
    $formData['role']         = trim($_POST['role'] ?? 'student');
    $formData['status']       = trim($_POST['status'] ?? 'active');
    $password                 = trim($_POST['password'] ?? '');

    // Student fields
    $formData['student_code'] = trim($_POST['student_code'] ?? '');
    $formData['classroom_id'] = !empty($_POST['classroom_id']) ? (int)$_POST['classroom_id'] : null;
    $formData['gender']       = trim($_POST['gender'] ?? 'male');
    $formData['birth_date']   = !empty($_POST['birth_date']) ? $_POST['birth_date'] : null;
    $formData['address']      = trim($_POST['address'] ?? '');
    $formData['parent_phone'] = trim($_POST['parent_phone'] ?? '');

    // Teacher fields
    $formData['teacher_code'] = trim($_POST['teacher_code'] ?? '');
    $formData['department']   = trim($_POST['department'] ?? '');
    $formData['position']     = trim($_POST['position'] ?? '');

    // 1. ตรวจสอบความถูกต้องของข้อมูลพื้นฐาน
    if (empty($formData['username'])) {
        $errors[] = 'กรุณาระบุชื่อผู้ใช้ (Username)';
    }
    if (empty($formData['full_name'])) {
        $errors[] = 'กรุณาระบุชื่อ-นามสกุล';
    }
    if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'กรุณาระบุอีเมลที่ถูกต้อง';
    }

    // รหัสผ่าน
    if (!$isEdit && empty($password)) {
        $errors[] = 'กรุณาระบุรหัสผ่านสำหรับการสร้างบัญชีใหม่';
    }

    // 2. ตรวจสอบความซ้ำซ้อนของ Username / Email
    $checkSql = "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?";
    $stmtCheck = $pdo->prepare($checkSql);
    $stmtCheck->execute([$formData['username'], $formData['email'], $userId]);
    if ($stmtCheck->fetch()) {
        $errors[] = 'ชื่อผู้ใช้ (Username) หรือ อีเมลนี้มีอยู่ในระบบแล้ว';
    }

    // 3. ตรวจสอบ Role fields
    if ($formData['role'] === 'student') {
        if (empty($formData['student_code'])) {
            $errors[] = 'กรุณาระบุรหัสนักศึกษา';
        } else {
            $stmtCode = $pdo->prepare("SELECT s.id FROM students s JOIN users u ON s.user_id = u.id WHERE s.student_code = ? AND u.id != ?");
            $stmtCode->execute([$formData['student_code'], $userId]);
            if ($stmtCode->fetch()) {
                $errors[] = 'รหัสนักศึกษานี้มีอยู่ในระบบแล้ว';
            }
        }
        if (empty($formData['classroom_id'])) {
            $errors[] = 'กรุณาเลือกห้องเรียนของนักศึกษา';
        }
    } elseif ($formData['role'] === 'teacher') {
        if (empty($formData['teacher_code'])) {
            $errors[] = 'กรุณาระบุรหัสประจำตัวครู';
        } else {
            $stmtTCode = $pdo->prepare("SELECT t.id FROM teachers t JOIN users u ON t.user_id = u.id WHERE t.teacher_code = ? AND u.id != ?");
            $stmtTCode->execute([$formData['teacher_code'], $userId]);
            if ($stmtTCode->fetch()) {
                $errors[] = 'รหัสประจำตัวครูนี้มีอยู่ในระบบแล้ว';
            }
        }
    }

    // 4. จัดการอัปโหลด Avatar
    $newAvatar = $formData['avatar'];
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadResult = uploadFile($_FILES['avatar'], 'avatars', ['jpg', 'jpeg', 'png', 'gif'], 5);
        if ($uploadResult['success']) {
            if (!empty($formData['avatar'])) {
                deleteUploadedFile('avatars', $formData['avatar']);
            }
            $newAvatar = $uploadResult['filename'];
        } else {
            $errors[] = $uploadResult['error'];
        }
    }

    // หากไม่มี Error ให้เริ่มบันทึกลงฐานข้อมูลด้วย Transaction
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($isEdit) {
                // Update users
                if (!empty($password)) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmtUser = $pdo->prepare("
                        UPDATE users 
                        SET username = ?, password = ?, role = ?, email = ?, full_name = ?, phone = ?, avatar = ?, status = ? 
                        WHERE id = ?
                    ");
                    $stmtUser->execute([
                        $formData['username'], $hashedPassword, $formData['role'], $formData['email'], 
                        $formData['full_name'], $formData['phone'], $newAvatar, $formData['status'], $userId
                    ]);
                } else {
                    $stmtUser = $pdo->prepare("
                        UPDATE users 
                        SET username = ?, role = ?, email = ?, full_name = ?, phone = ?, avatar = ?, status = ? 
                        WHERE id = ?
                    ");
                    $stmtUser->execute([
                        $formData['username'], $formData['role'], $formData['email'], 
                        $formData['full_name'], $formData['phone'], $newAvatar, $formData['status'], $userId
                    ]);
                }
                $targetUserId = $userId;
            } else {
                // Insert new user
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmtUser = $pdo->prepare("
                    INSERT INTO users (username, password, role, email, full_name, phone, avatar, status, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmtUser->execute([
                    $formData['username'], $hashedPassword, $formData['role'], $formData['email'], 
                    $formData['full_name'], $formData['phone'], $newAvatar, $formData['status']
                ]);
                $targetUserId = (int)$pdo->lastInsertId();
            }

            // จัดการข้อมูลตารางลูก (Student / Teacher)
            if ($formData['role'] === 'student') {
                // ลบจาก teacher ถ้าเคยมี
                $pdo->prepare("DELETE FROM teachers WHERE user_id = ?")->execute([$targetUserId]);
                
                // ตรวจสอบว่ามีข้อมูล student อยู่แล้วหรือไม่
                $stmtStdCheck = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
                $stmtStdCheck->execute([$targetUserId]);
                if ($stmtStdCheck->fetch()) {
                    $stmtStd = $pdo->prepare("
                        UPDATE students 
                        SET student_code = ?, classroom_id = ?, gender = ?, birth_date = ?, address = ?, parent_phone = ? 
                        WHERE user_id = ?
                    ");
                    $stmtStd->execute([
                        $formData['student_code'], $formData['classroom_id'], $formData['gender'], 
                        $formData['birth_date'], $formData['address'], $formData['parent_phone'], $targetUserId
                    ]);
                } else {
                    $stmtStd = $pdo->prepare("
                        INSERT INTO students (user_id, student_code, classroom_id, gender, birth_date, address, parent_phone, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmtStd->execute([
                        $targetUserId, $formData['student_code'], $formData['classroom_id'], $formData['gender'], 
                        $formData['birth_date'], $formData['address'], $formData['parent_phone']
                    ]);
                }
            } elseif ($formData['role'] === 'teacher') {
                // ลบจาก student ถ้าเคยมี
                $pdo->prepare("DELETE FROM students WHERE user_id = ?")->execute([$targetUserId]);
                
                $stmtTchCheck = $pdo->prepare("SELECT id FROM teachers WHERE user_id = ?");
                $stmtTchCheck->execute([$targetUserId]);
                if ($stmtTchCheck->fetch()) {
                    $stmtTch = $pdo->prepare("
                        UPDATE teachers 
                        SET teacher_code = ?, department = ?, position = ? 
                        WHERE user_id = ?
                    ");
                    $stmtTch->execute([$formData['teacher_code'], $formData['department'], $formData['position'], $targetUserId]);
                } else {
                    $stmtTch = $pdo->prepare("
                        INSERT INTO teachers (user_id, teacher_code, department, position, created_at) 
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmtTch->execute([$targetUserId, $formData['teacher_code'], $formData['department'], $formData['position']]);
                }
            } else {
                // Admin - ลบออกจากทั้ง student และ teacher
                $pdo->prepare("DELETE FROM students WHERE user_id = ?")->execute([$targetUserId]);
                $pdo->prepare("DELETE FROM teachers WHERE user_id = ?")->execute([$targetUserId]);
            }

            $pdo->commit();
            setFlash('success', $isEdit ? 'บันทึกการแก้ไขข้อมูลผู้ใช้งานเรียบร้อยแล้ว' : 'เพิ่มผู้ใช้งานใหม่เรียบร้อยแล้ว');
            header('Location: ' . BASE_URL . 'admin/users.php');
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi <?= $isEdit ? 'bi-pencil-square text-warning' : 'bi-person-plus text-primary' ?>"></i>
                    <?= $pageTitle ?>
                </h5>
                <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> กลับหน้ารายการ
                </a>
            </div>
            
            <div class="card-body p-4">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show rounded-3 p-3 mb-4">
                        <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-1"></i> พบข้อผิดพลาด:</h6>
                        <ul class="mb-0 ps-3 small">
                            <?php foreach ($errors as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" enctype="multipart/form-data" autocomplete="off">
                    <?= getCsrfField() ?>

                    <!-- หมวดที่ 1: ข้อมูลบัญชีผู้ใช้ -->
                    <h6 class="fw-bold text-primary mb-3 pb-2 border-bottom">
                        <i class="bi bi-shield-lock me-1"></i> 1. ข้อมูลบัญชีผู้ใช้และสิทธิ์
                    </h6>

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label required">บทบาทผู้ใช้งาน (Role)</label>
                            <select name="role" id="roleSelect" class="form-select fw-semibold" required onchange="toggleRoleSections()">
                                <option value="student" <?= $formData['role'] === 'student' ? 'selected' : '' ?>>นักศึกษา (Student)</option>
                                <option value="teacher" <?= $formData['role'] === 'teacher' ? 'selected' : '' ?>>ครูผู้สอน (Teacher)</option>
                                <option value="admin" <?= $formData['role'] === 'admin' ? 'selected' : '' ?>>ผู้ดูแลระบบ (Admin)</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label required">ชื่อผู้ใช้งาน (Username)</label>
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($formData['username']) ?>" required placeholder="ภาษาอังกฤษหรือตัวเลข">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label <?= !$isEdit ? 'required' : '' ?>">รหัสผ่าน (Password)</label>
                            <input type="password" name="password" class="form-control" placeholder="<?= $isEdit ? 'เว้นว่างไว้หากไม่ต้องการเปลี่ยน' : 'รหัสผ่านอย่างน้อย 6 ตัวอักษร' ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">ชื่อ-นามสกุล (Full Name)</label>
                            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($formData['full_name']) ?>" required placeholder="เช่น นายสมชาย สุขเกษม">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required">อีเมล (Email)</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($formData['email']) ?>" required placeholder="example@thanya.ac.th">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">เบอร์โทรศัพท์ (Phone)</label>
                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($formData['phone'] ?? '') ?>" placeholder="08x-xxx-xxxx">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">สถานะการใช้งาน</label>
                            <select name="status" class="form-select">
                                <option value="active" <?= $formData['status'] === 'active' ? 'selected' : '' ?>>เปิดใช้งาน (Active)</option>
                                <option value="inactive" <?= $formData['status'] === 'inactive' ? 'selected' : '' ?>>ปิดใช้งาน (Inactive)</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">รูปภาพโปรไฟล์ (Avatar)</label>
                            <input type="file" name="avatar" class="form-control" accept="image/*">
                        </div>
                    </div>

                    <!-- หมวดที่ 2: ข้อมูลนักศึกษา (เฉพาะ Role = student) -->
                    <div id="studentSection" class="role-section mb-4">
                        <h6 class="fw-bold text-success mb-3 pb-2 border-bottom">
                            <i class="bi bi-mortarboard me-1"></i> 2. ข้อมูลเฉพาะสำหรับนักศึกษา
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label required">รหัสนักศึกษา (Student Code)</label>
                                <input type="text" name="student_code" class="form-control" value="<?= htmlspecialchars($formData['student_code'] ?? '') ?>" placeholder="เช่น 67309010001">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label required">ห้องเรียน / ชั้นปี</label>
                                <select name="classroom_id" class="form-select">
                                    <option value="">-- เลือกห้องเรียน --</option>
                                    <?php foreach ($classrooms as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= ($formData['classroom_id'] == $c['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['level']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">เพศ</label>
                                <select name="gender" class="form-select">
                                    <option value="male" <?= ($formData['gender'] ?? '') === 'male' ? 'selected' : '' ?>>ชาย</option>
                                    <option value="female" <?= ($formData['gender'] ?? '') === 'female' ? 'selected' : '' ?>>หญิง</option>
                                    <option value="other" <?= ($formData['gender'] ?? '') === 'other' ? 'selected' : '' ?>>อื่นๆ</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">วัน/เดือน/ปีเกิด</label>
                                <input type="date" name="birth_date" class="form-control" value="<?= htmlspecialchars($formData['birth_date'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">เบอร์โทรผู้ปกครอง</label>
                                <input type="text" name="parent_phone" class="form-control" value="<?= htmlspecialchars($formData['parent_phone'] ?? '') ?>" placeholder="08x-xxx-xxxx">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ที่อยู่ปัจจุบัน</label>
                                <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($formData['address'] ?? '') ?>" placeholder="บ้านเลขที่ ตำบล อำเภอ จังหวัด">
                            </div>
                        </div>
                    </div>

                    <!-- หมวดที่ 3: ข้อมูลครูผู้สอน (เฉพาะ Role = teacher) -->
                    <div id="teacherSection" class="role-section mb-4" style="display: none;">
                        <h6 class="fw-bold text-primary mb-3 pb-2 border-bottom">
                            <i class="bi bi-person-workspace me-1"></i> 2. ข้อมูลเฉพาะสำหรับครูผู้สอน
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label required">รหัสประจำตัวครู (Teacher Code)</label>
                                <input type="text" name="teacher_code" class="form-control" value="<?= htmlspecialchars($formData['teacher_code'] ?? '') ?>" placeholder="เช่น T67001">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">แผนกวิชา</label>
                                <input type="text" name="department" class="form-control" value="<?= htmlspecialchars($formData['department'] ?? 'แผนกวิชาเทคโนโลยีสารสนเทศ') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ตำแหน่ง</label>
                                <input type="text" name="position" class="form-control" value="<?= htmlspecialchars($formData['position'] ?? 'ครูผู้ช่วย') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- ปุ่มบันทึกและยกเลิก -->
                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-outline-secondary px-4">ยกเลิก</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-check2-circle me-1"></i> บันทึกข้อมูล
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleRoleSections() {
    const role = document.getElementById('roleSelect').value;
    const studentSec = document.getElementById('studentSection');
    const teacherSec = document.getElementById('teacherSection');

    if (role === 'student') {
        studentSec.style.display = 'block';
        teacherSec.style.display = 'none';
    } else if (role === 'teacher') {
        studentSec.style.display = 'none';
        teacherSec.style.display = 'block';
    } else {
        studentSec.style.display = 'none';
        teacherSec.style.display = 'none';
    }
}
document.addEventListener('DOMContentLoaded', toggleRoleSections);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
