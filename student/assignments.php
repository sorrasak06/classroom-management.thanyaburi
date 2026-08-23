<?php
/**
 * Student - Assignments List
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('student');

$pageTitle = 'งานและการบ้านของฉัน';
$currentUser = getCurrentUser();
$studentId = $currentUser['role_id'] ?? 0;
$classroomId = $currentUser['classroom_id'] ?? 0;

$filter = trim($_GET['filter'] ?? 'all'); // all, pending, submitted, graded

$sql = "
    SELECT a.*, 
           s.subject_code, s.name_th as subject_name,
           u.full_name as teacher_name,
           sm.id as submission_id, sm.submission_file, sm.submitted_at, sm.score, sm.feedback, sm.status as submission_status
    FROM assignments a
    JOIN subjects s ON a.subject_id = s.id
    JOIN teachers t ON a.teacher_id = t.id
    JOIN users u ON t.user_id = u.id
    LEFT JOIN submissions sm ON sm.assignment_id = a.id AND sm.student_id = ?
    WHERE a.classroom_id = ?
";
$params = [$studentId, $classroomId];

if ($filter === 'pending') {
    $sql .= " AND sm.id IS NULL";
} elseif ($filter === 'submitted') {
    $sql .= " AND sm.id IS NOT NULL AND (sm.status = 'submitted' OR sm.status = 'late')";
} elseif ($filter === 'graded') {
    $sql .= " AND sm.status = 'graded'";
}

$sql .= " ORDER BY a.due_date ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $assignments = $stmt->fetchAll();
} catch (PDOException $e) {
    $dbError = $e->getMessage();
    $assignments = [];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="card">
    <div class="card-header flex-column flex-md-row gap-3 align-items-start align-items-md-center">
        <div>
            <h5 class="card-title mb-0"><i class="bi bi-journal-text text-primary"></i> งานและการบ้าน (<?= count($assignments) ?> รายการ)</h5>
            <div class="small text-muted">ตรวจสอบงานที่ได้รับมอบหมาย กำหนดส่ง และส่งงานออนไลน์</div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="card-body bg-light bg-opacity-50 border-bottom py-2.5">
        <ul class="nav nav-pills gap-1">
            <li class="nav-item">
                <a class="nav-link btn-sm <?= $filter === 'all' ? 'active' : '' ?>" href="<?= BASE_URL ?>student/assignments.php?filter=all">
                    ทั้งหมด
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link btn-sm <?= $filter === 'pending' ? 'active' : '' ?>" href="<?= BASE_URL ?>student/assignments.php?filter=pending">
                    <i class="bi bi-hourglass me-1"></i> ยังไม่ส่ง
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link btn-sm <?= $filter === 'submitted' ? 'active' : '' ?>" href="<?= BASE_URL ?>student/assignments.php?filter=submitted">
                    <i class="bi bi-send-check me-1"></i> ส่งแล้ว (รอตรวจ)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link btn-sm <?= $filter === 'graded' ? 'active' : '' ?>" href="<?= BASE_URL ?>student/assignments.php?filter=graded">
                    <i class="bi bi-check2-all me-1"></i> ตรวจแล้ว
                </a>
            </li>
        </ul>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>ชื่องาน / รายวิชา</th>
                    <th>อาจารย์ผู้สอน</th>
                    <th>คะแนนเต็ม</th>
                    <th>วันครบกำหนดส่ง</th>
                    <th>สถานะ</th>
                    <th>คะแนนที่ได้</th>
                    <th class="text-end" style="width: 120px;">ดำเนินการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($assignments)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            ไม่พบรายการงานในหมวดนี้
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($assignments as $idx => $a): 
                        $isOverdue = strtotime($a['due_date']) < time();
                    ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td>
                                <div class="fw-semibold text-dark">
                                    <a href="<?= BASE_URL ?>student/assignment-detail.php?id=<?= $a['id'] ?>" class="text-dark hover-primary">
                                        <?= htmlspecialchars($a['title']) ?>
                                    </a>
                                </div>
                                <div class="small text-muted mt-0.5"><?= htmlspecialchars($a['subject_code']) ?> <?= htmlspecialchars($a['subject_name']) ?></div>
                            </td>
                            <td class="small"><?= htmlspecialchars($a['teacher_name']) ?></td>
                            <td>
                                <span class="badge bg-secondary-subtle text-dark"><?= number_format($a['max_score'], 0) ?> คะแนน</span>
                            </td>
                            <td>
                                <div class="small <?= $isOverdue && empty($a['submission_id']) ? 'text-danger fw-semibold' : 'text-dark' ?>">
                                    <?= formatThaiDate($a['due_date'], true, true) ?>
                                </div>
                                <?php if ($isOverdue && empty($a['submission_id'])): ?>
                                    <span class="badge bg-danger-subtle text-danger" style="font-size: 0.68rem;">เลยกำหนด</span>
                                <?php endif; ?>
                            </td>
                            <td><?= getSubmissionBadge($a['submission_status'], $a['due_date']) ?></td>
                            <td class="fw-bold">
                                <?php if ($a['score'] !== null): ?>
                                    <span class="text-success"><?= number_format($a['score'], 1) ?></span> <span class="small text-muted">/ <?= number_format($a['max_score'], 0) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= BASE_URL ?>student/assignment-detail.php?id=<?= $a['id'] ?>" class="btn btn-sm <?= empty($a['submission_id']) ? 'btn-primary' : 'btn-outline-primary' ?>">
                                    <?= empty($a['submission_id']) ? '<i class="bi bi-upload me-1"></i>ส่งงาน' : '<i class="bi bi-eye me-1"></i>ดูรายละเอียด' ?>
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
