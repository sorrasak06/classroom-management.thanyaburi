<?php
/**
 * Teacher - Students List
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('teacher');

$pageTitle = 'รายชื่อนักศึกษา';
$currentUser = getCurrentUser();
$teacherId = $currentUser['role_id'] ?? 0;

// ดึงห้องเรียนที่ครูคนนี้สอน
$classrooms = $pdo->prepare("
    SELECT DISTINCT c.id, c.name, c.level 
    FROM classrooms c 
    JOIN subjects s ON s.classroom_id = c.id 
    WHERE s.teacher_id = ?
    ORDER BY c.level ASC, c.name ASC
");
$classrooms->execute([$teacherId]);
$myClassrooms = $classrooms->fetchAll();

$selectedClass = !empty($_GET['classroom_id']) ? (int)$_GET['classroom_id'] : 0;
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT s.*, u.full_name, u.email, u.phone, u.avatar, u.status,
           c.name as classroom_name, c.level,
           (SELECT COUNT(*) FROM attendance a WHERE a.student_id = s.id AND a.status = 'present') as present_count,
           (SELECT COUNT(*) FROM attendance a WHERE a.student_id = s.id) as total_attendance
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN classrooms c ON s.classroom_id = c.id
    WHERE 1=1
";
$params = [];

// ถ้าไม่ได้เลือกห้อง ให้แสดงนักเรียนในห้องที่ครูสอนทั้งหมด
if ($selectedClass > 0) {
    $sql .= " AND s.classroom_id = ?";
    $params[] = $selectedClass;
} else {
    $sql .= " AND s.classroom_id IN (SELECT DISTINCT classroom_id FROM subjects WHERE teacher_id = ?)";
    $params[] = $teacherId;
}

if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE ? OR s.student_code LIKE ? OR u.email LIKE ?)";
    $sParam = "%{$search}%";
    $params = array_merge($params, [$sParam, $sParam, $sParam]);
}

$sql .= " ORDER BY c.level ASC, s.student_code ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
} catch (PDOException $e) {
    $dbError = $e->getMessage();
    $students = [];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="card">
    <div class="card-header flex-column flex-md-row gap-3 align-items-start align-items-md-center">
        <div>
            <h5 class="card-title mb-0"><i class="bi bi-people-fill text-primary"></i> รายชื่อนักศึกษาในความรับผิดชอบ (<?= count($students) ?> คน)</h5>
            <div class="small text-muted">ตรวจสอบประวัติ สถิติการเข้าเรียน และผลการเรียนรายบุคคล</div>
        </div>
    </div>

    <!-- Filter & Search -->
    <div class="card-body bg-light bg-opacity-50 border-bottom py-3">
        <form action="<?= BASE_URL ?>teacher/students.php" method="GET" class="row g-2 align-items-center">
            <div class="col-md-5 col-12">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="ค้นหาชื่อ, รหัสนักศึกษา..." value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-4 col-8">
                <select name="classroom_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">-- ทุกห้องเรียนที่สอน --</option>
                    <?php foreach ($myClassrooms as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $selectedClass == $c['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 col-4 d-flex gap-2">
                <button type="submit" class="btn btn-dark w-100">ค้นหา</button>
                <?php if (!empty($search) || $selectedClass > 0): ?>
                    <a href="<?= BASE_URL ?>teacher/students.php" class="btn btn-outline-secondary" title="ล้างตัวกรอง"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Students Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>นักศึกษา</th>
                    <th>รหัสประจำตัว</th>
                    <th>ห้องเรียน</th>
                    <th>เบอร์โทรศัพท์</th>
                    <th>สถิติมาเรียน</th>
                    <th class="text-end" style="width: 120px;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            ไม่พบข้อมูลนักศึกษาตามเงื่อนไข
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $idx => $s): 
                        $total = (int)$s['total_attendance'];
                        $present = (int)$s['present_count'];
                        $pct = $total > 0 ? round(($present / $total) * 100, 1) : 0;
                    ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2.5">
                                    <img src="<?= getUserAvatarUrl($s['avatar'], $s['full_name']) ?>" class="user-avatar" alt="Avatar">
                                    <div>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($s['full_name']) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($s['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-secondary-subtle text-dark fw-bold"><?= htmlspecialchars($s['student_code']) ?></span>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary"><?= htmlspecialchars($s['classroom_name']) ?></span>
                            </td>
                            <td class="small"><?= htmlspecialchars($s['phone'] ?? '-') ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1" style="height: 6px; width: 60px;">
                                        <div class="progress-bar <?= $pct >= 80 ? 'bg-success' : 'bg-danger' ?>" style="width: <?= $pct ?>%"></div>
                                    </div>
                                    <span class="small fw-semibold <?= $pct >= 80 ? 'text-success' : 'text-danger' ?>"><?= $pct ?>%</span>
                                </div>
                            </td>
                            <td class="text-end">
                                <a href="<?= BASE_URL ?>teacher/student-detail.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-person-lines-fill me-1"></i> ดูประวัติ
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
