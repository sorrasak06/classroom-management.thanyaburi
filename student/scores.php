<?php
/**
 * Student - Scores & Academic Performance (GPA)
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('student');

$pageTitle = 'คะแนนและผลการเรียน';
$currentUser = getCurrentUser();
$studentId = $currentUser['role_id'] ?? 0;

try {
    // ดึงคะแนนรายวิชาทั้งหมดของนักศึกษา
    $stmtScores = $pdo->prepare("
        SELECT sc.*, s.subject_code, s.name_th as subject_name, s.name_en, s.credits,
               u.full_name as teacher_name
        FROM scores sc
        JOIN subjects s ON sc.subject_id = s.id
        JOIN teachers t ON s.teacher_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE sc.student_id = ?
        ORDER BY sc.academic_year DESC, sc.term ASC, s.subject_code ASC
    ");
    $stmtScores->execute([$studentId]);
    $scores = $stmtScores->fetchAll();

    // คำนวณ GPA
    $totalCredits = 0;
    $totalWeightPoints = 0;

    foreach ($scores as $sc) {
        $credits = (float)$sc['credits'];
        $gp = getGradePoint($sc['grade']);
        $totalCredits += $credits;
        $totalWeightPoints += ($credits * $gp);
    }

    $gpa = $totalCredits > 0 ? round($totalWeightPoints / $totalCredits, 2) : 0.00;

} catch (PDOException $e) {
    $dbError = $e->getMessage();
    $scores = [];
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<!-- Print-Only Header -->
<div class="print-header">
    <img src="<?= BASE_URL ?>assets/images/logo.png" style="height: 60px; margin-bottom: 8px;">
    <h3 style="margin: 0; font-size: 16pt;"><?= APP_NAME ?></h3>
    <h4 style="margin: 5px 0 10px 0; font-size: 13pt;">ใบรายงานผลการเรียน (Grade Report)</h4>
    <div style="font-size: 10pt; color: #555;">
        นักศึกษา: <strong><?= htmlspecialchars($currentUser['full_name']) ?></strong> (<?= htmlspecialchars($currentUser['code']) ?>) | วันที่พิมพ์: <?= formatThaiDateTime(date('Y-m-d H:i:s')) ?>
    </div>
    <hr style="margin: 15px 0;">
</div>

<!-- GPA Summary Card -->
<div class="card mb-4 bg-primary text-white border-0 shadow-sm" style="background: linear-gradient(135deg, #1e3a8a, #2563eb) !important;">
    <div class="card-body p-4">
        <div class="row align-items-center g-3">
            <div class="col-md-8">
                <h5 class="fw-bold mb-1">ผลการเรียนเฉลี่ยสะสม (GPA)</h5>
                <p class="opacity-75 small mb-0">รหัสนักศึกษา: <?= htmlspecialchars($currentUser['code']) ?> &bull; หลักสูตรประกาศนียบัตรวิชาชีพชั้นสูง (ปวส.)</p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="display-5 fw-bold"><?= number_format($gpa, 2) ?></div>
                <div class="opacity-75 small">หน่วยกิตสะสม: <?= number_format($totalCredits, 1) ?> หน่วยกิต</div>
            </div>
        </div>
    </div>
</div>

<!-- Score Table Card -->
<div class="card">
    <div class="card-header flex-column flex-md-row gap-3 align-items-start align-items-md-center">
        <div>
            <h5 class="card-title mb-0"><i class="bi bi-award-fill text-primary"></i> รายการผลคะแนนรายวิชา (<?= count($scores) ?> วิชา)</h5>
            <div class="small text-muted">คะแนนเก็บระหว่างภาค กลางภาค ปลายภาค และเกรดที่ได้รับ</div>
        </div>
        <div class="no-print">
            <button type="button" class="btn btn-dark btn-sm" onclick="printReport()">
                <i class="bi bi-printer me-1"></i> พิมพ์ใบรายงานผลการเรียน
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>รหัสวิชา</th>
                    <th>ชื่อรายวิชา</th>
                    <th class="text-center">หน่วยกิต</th>
                    <th class="text-center">งาน (40)</th>
                    <th class="text-center">กลางภาค (30)</th>
                    <th class="text-center">ปลายภาค (30)</th>
                    <th class="text-center fw-bold bg-primary-subtle text-primary">รวม (100)</th>
                    <th class="text-center bg-dark text-white fw-bold">เกรด</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($scores)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            <i class="bi bi-award fs-1 d-block mb-2"></i>
                            ยังไม่มีการบันทึกผลการเรียนในระบบ
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($scores as $idx => $sc): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td><span class="badge bg-secondary-subtle text-dark fw-bold"><?= htmlspecialchars($sc['subject_code']) ?></span></td>
                            <td>
                                <div class="fw-semibold text-dark"><?= htmlspecialchars($sc['subject_name']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($sc['teacher_name']) ?></div>
                            </td>
                            <td class="text-center"><?= number_format($sc['credits'], 1) ?></td>
                            <td class="text-center"><?= number_format($sc['assignment_score'], 1) ?></td>
                            <td class="text-center"><?= number_format($sc['midterm_score'], 1) ?></td>
                            <td class="text-center"><?= number_format($sc['final_score'], 1) ?></td>
                            <td class="text-center fw-bold fs-6 text-primary bg-primary-subtle">
                                <?= number_format($sc['total_score'], 1) ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-dark px-2.5 py-1 fs-7"><?= htmlspecialchars($sc['grade']) ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($scores)): ?>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="3" class="text-end">รวมหน่วยกิตและผลการเรียนเฉลี่ย:</td>
                        <td class="text-center"><?= number_format($totalCredits, 1) ?></td>
                        <td colspan="4" class="text-end">เกรดเฉลี่ยรวม (GPA):</td>
                        <td class="text-center text-primary fs-6"><?= number_format($gpa, 2) ?></td>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
