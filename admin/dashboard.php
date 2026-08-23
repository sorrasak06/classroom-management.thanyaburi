<?php
/**
 * Admin Dashboard
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('admin');

$pageTitle = 'แดชบอร์ดผู้ดูแลระบบ';

// ดึงสถิติภาพรวม
try {
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalTeachers = $pdo->query("SELECT COUNT(*) FROM teachers")->fetchColumn();
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $totalClassrooms = $pdo->query("SELECT COUNT(*) FROM classrooms")->fetchColumn();
    $totalSubjects = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();

    // สถิติการเข้าเรียนภาพรวม
    $attStats = $pdo->query("
        SELECT 
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_count,
            COUNT(*) as total_attendance
        FROM attendance
    ")->fetch();

    $totalAtt = (int)($attStats['total_attendance'] ?? 0);
    $presentPct = $totalAtt > 0 ? round(($attStats['present_count'] / $totalAtt) * 100, 1) : 0;
    $absentPct  = $totalAtt > 0 ? round(($attStats['absent_count'] / $totalAtt) * 100, 1) : 0;
    $latePct    = $totalAtt > 0 ? round(($attStats['late_count'] / $totalAtt) * 100, 1) : 0;
    $leavePct   = $totalAtt > 0 ? round(($attStats['leave_count'] / $totalAtt) * 100, 1) : 0;

    // สถิติการเข้าแถววันนี้
    $lineupToday = $pdo->query("
        SELECT 
            COUNT(la.id) as attended_count,
            SUM(CASE WHEN la.status = 'on_time' THEN 1 ELSE 0 END) as on_time_count,
            SUM(CASE WHEN la.status = 'late' THEN 1 ELSE 0 END) as late_count
        FROM lineup_sessions ls
        LEFT JOIN lineup_attendance la ON ls.id = la.lineup_session_id
        WHERE ls.session_date = CURDATE()
    ")->fetch();
    $lineupAttended = (int)($lineupToday['attended_count'] ?? 0);
    $lineupOnTime   = (int)($lineupToday['on_time_count'] ?? 0);
    $lineupLate     = (int)($lineupToday['late_count'] ?? 0);
    $lineupAbsent   = max(0, (int)$totalStudents - $lineupAttended);
    $lineupPct      = ((int)$totalStudents > 0) ? round(($lineupAttended / (int)$totalStudents) * 100, 1) : 0;

    // ประกาศล่าสุด
    $stmtAnn = $pdo->query("
        SELECT a.*, u.full_name as author_name 
        FROM announcements a 
        LEFT JOIN users u ON a.author_id = u.id 
        ORDER BY a.is_pinned DESC, a.created_at DESC 
        LIMIT 5
    ");
    $announcements = $stmtAnn->fetchAll();

    // รายชื่อผู้ใช้ที่เพิ่งสร้างล่าสุด
    $recentUsers = $pdo->query("
        SELECT * FROM users 
        ORDER BY created_at DESC 
        LIMIT 6
    ")->fetchAll();

} catch (PDOException $e) {
    $dbError = $e->getMessage();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<!-- KPI Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-value"><?= number_format($totalStudents) ?></div>
                <div class="stat-label">นักศึกษาทั้งหมด</div>
            </div>
            <div class="stat-icon success">
                <i class="bi bi-mortarboard-fill"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-value"><?= number_format($totalTeachers) ?></div>
                <div class="stat-label">ครูผู้สอน</div>
            </div>
            <div class="stat-icon primary">
                <i class="bi bi-person-workspace"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-value"><?= number_format($totalClassrooms) ?></div>
                <div class="stat-label">ห้องเรียน</div>
            </div>
            <div class="stat-icon info">
                <i class="bi bi-door-open-fill"></i>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div>
                <div class="stat-value"><?= number_format($totalSubjects) ?></div>
                <div class="stat-label">รายวิชาที่เปิดสอน</div>
            </div>
            <div class="stat-icon warning">
                <i class="bi bi-journal-bookmark-fill"></i>
            </div>
        </div>
    </div>
</div>

<!-- Main Row: Attendance Overview & Quick Actions -->
<div class="row g-4 mb-4">
    <!-- Attendance Overview Card -->
    <div class="col-lg-8">
        <div class="card h-100 mb-0">
            <div class="card-header">
                <h5 class="card-title"><i class="bi bi-pie-chart-fill text-primary"></i> สถิติการเข้าเรียนภาพรวมทั้งสถาบัน</h5>
                <a href="<?= BASE_URL ?>admin/reports.php" class="btn btn-sm btn-outline-primary rounded-pill">ดูรายงานรายละเอียด</a>
            </div>
            <div class="card-body">
                <div class="row g-3 text-center mb-4">
                    <div class="col-3">
                        <div class="p-3 bg-success-subtle rounded-3 border border-success-subtle">
                            <div class="fs-4 fw-bold text-success"><?= $attStats['present_count'] ?? 0 ?></div>
                            <div class="small text-success fw-medium">มาเรียน (<?= $presentPct ?>%)</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-3 bg-danger-subtle rounded-3 border border-danger-subtle">
                            <div class="fs-4 fw-bold text-danger"><?= $attStats['absent_count'] ?? 0 ?></div>
                            <div class="small text-danger fw-medium">ขาดเรียน (<?= $absentPct ?>%)</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-3 bg-warning-subtle rounded-3 border border-warning-subtle">
                            <div class="fs-4 fw-bold text-warning-emphasis"><?= $attStats['late_count'] ?? 0 ?></div>
                            <div class="small text-warning-emphasis fw-medium">สาย (<?= $latePct ?>%)</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-3 bg-info-subtle rounded-3 border border-info-subtle">
                            <div class="fs-4 fw-bold text-info-emphasis"><?= $attStats['leave_count'] ?? 0 ?></div>
                            <div class="small text-info-emphasis fw-medium">ลา (<?= $leavePct ?>%)</div>
                        </div>
                    </div>
                </div>

                <!-- Visual Attendance Bar -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between small text-muted mb-1">
                        <span>สัดส่วนการเข้าเรียนสะสม (<?= number_format($totalAtt) ?> รายการบันทึก)</span>
                        <span class="fw-semibold text-success">อัตราการมาเรียน <?= $presentPct ?>%</span>
                    </div>
                    <div class="progress" style="height: 14px; border-radius: 10px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $presentPct ?>%" title="มาเรียน <?= $presentPct ?>%"></div>
                        <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $latePct ?>%" title="สาย <?= $latePct ?>%"></div>
                        <div class="progress-bar bg-info" role="progressbar" style="width: <?= $leavePct ?>%" title="ลา <?= $leavePct ?>%"></div>
                        <div class="progress-bar bg-danger" role="progressbar" style="width: <?= $absentPct ?>%" title="ขาด <?= $absentPct ?>%"></div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-3 small text-muted mt-3">
                    <div class="d-flex align-items-center gap-1.5"><span class="badge bg-success p-1 rounded-circle"></span> มาเรียน</div>
                    <div class="d-flex align-items-center gap-1.5"><span class="badge bg-warning p-1 rounded-circle"></span> สาย</div>
                    <div class="d-flex align-items-center gap-1.5"><span class="badge bg-info p-1 rounded-circle"></span> ลา</div>
                    <div class="d-flex align-items-center gap-1.5"><span class="badge bg-danger p-1 rounded-circle"></span> ขาดเรียน</div>
                </div>

                <hr class="my-4">

                <!-- Line-up Attendance Card -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-qr-code-scan text-primary me-2"></i> สถิติการเข้าแถววันนี้ (<?= formatThaiDate(date('Y-m-d')) ?>)</h6>
                    <a href="<?= BASE_URL ?>admin/reports.php?type=lineup" class="btn btn-sm btn-link text-primary p-0 text-decoration-none">ดูรายงานการเข้าแถว &raquo;</a>
                </div>
                <div class="row g-3 text-center">
                    <div class="col-3">
                        <div class="p-2.5 bg-primary-subtle rounded-3 border border-primary-subtle">
                            <div class="fs-5 fw-bold text-primary"><?= number_format($lineupAttended) ?> / <?= number_format($totalStudents) ?></div>
                            <div class="small text-primary fw-medium">มาเข้าแถว (<?= $lineupPct ?>%)</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2.5 bg-success-subtle rounded-3 border border-success-subtle">
                            <div class="fs-5 fw-bold text-success"><?= number_format($lineupOnTime) ?></div>
                            <div class="small text-success fw-medium">ตรงเวลา</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2.5 bg-warning-subtle rounded-3 border border-warning-subtle">
                            <div class="fs-5 fw-bold text-warning-emphasis"><?= number_format($lineupLate) ?></div>
                            <div class="small text-warning-emphasis fw-medium">สาย</div>
                        </div>
                    </div>
                    <div class="col-3">
                        <div class="p-2.5 bg-danger-subtle rounded-3 border border-danger-subtle">
                            <div class="fs-5 fw-bold text-danger"><?= number_format($lineupAbsent) ?></div>
                            <div class="small text-danger fw-medium">ยังไม่มา/ขาด</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Card -->
    <div class="col-lg-4">
        <div class="card h-100 mb-0">
            <div class="card-header">
                <h5 class="card-title"><i class="bi bi-lightning-charge-fill text-warning"></i> เมนูด่วน (Quick Actions)</h5>
            </div>
            <div class="card-body d-flex flex-column gap-2.5">
                <a href="<?= BASE_URL ?>admin/user-form.php" class="btn btn-soft-primary text-start justify-content-start py-2.5">
                    <i class="bi bi-person-plus-fill fs-5 me-2 text-primary"></i>
                    <div>
                        <div class="fw-semibold">เพิ่มผู้ใช้งานใหม่</div>
                        <div class="small text-muted">เพิ่มครู นักเรียน หรือผู้ดูแลระบบ</div>
                    </div>
                </a>
                <a href="<?= BASE_URL ?>admin/classrooms.php" class="btn btn-soft-primary text-start justify-content-start py-2.5">
                    <i class="bi bi-door-open-fill fs-5 me-2 text-info"></i>
                    <div>
                        <div class="fw-semibold">จัดการห้องเรียน</div>
                        <div class="small text-muted">สร้างกลุ่มเรียน กำหนดครูที่ปรึกษา</div>
                    </div>
                </a>
                <a href="<?= BASE_URL ?>admin/subjects.php" class="btn btn-soft-primary text-start justify-content-start py-2.5">
                    <i class="bi bi-journal-plus fs-5 me-2 text-success"></i>
                    <div>
                        <div class="fw-semibold">เพิ่มรายวิชา</div>
                        <div class="small text-muted">เปิดวิชาเรียนและมอบหมายครูผู้สอน</div>
                    </div>
                </a>
                <a href="<?= BASE_URL ?>admin/reports.php" class="btn btn-soft-primary text-start justify-content-start py-2.5">
                    <i class="bi bi-printer-fill fs-5 me-2 text-secondary"></i>
                    <div>
                        <div class="fw-semibold">ออกรายงาน / Export</div>
                        <div class="small text-muted">พิมพ์และส่งออก Excel / PDF</div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Bottom Row: Recent Users & Latest Announcements -->
<div class="row g-4">
    <!-- Recent Users Table -->
    <div class="col-lg-6">
        <div class="card mb-0">
            <div class="card-header">
                <h5 class="card-title"><i class="bi bi-people text-primary"></i> ผู้ใช้งานล่าสุด</h5>
                <a href="<?= BASE_URL ?>admin/users.php" class="btn btn-sm btn-outline-primary rounded-pill">ดูทั้งหมด</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ผู้ใช้งาน</th>
                            <th>บทบาท</th>
                            <th>วันที่สร้าง</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentUsers)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">ยังไม่มีข้อมูลผู้ใช้</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentUsers as $u): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="<?= getUserAvatarUrl($u['avatar'], $u['full_name']) ?>" class="rounded-circle" width="32" height="32" alt="Avatar">
                                            <div>
                                                <div class="fw-medium text-dark"><?= htmlspecialchars($u['full_name']) ?></div>
                                                <div class="small text-muted">@<?= htmlspecialchars($u['username']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= getRoleBadge($u['role']) ?></td>
                                    <td class="small text-muted"><?= formatThaiDate($u['created_at'], false, true) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Latest Announcements -->
    <div class="col-lg-6">
        <div class="card mb-0">
            <div class="card-header">
                <h5 class="card-title"><i class="bi bi-megaphone-fill text-danger"></i> ประกาศข่าวสารล่าสุด</h5>
                <a href="<?= BASE_URL ?>admin/announcements.php" class="btn btn-sm btn-outline-primary rounded-pill">จัดการประกาศ</a>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php if (empty($announcements)): ?>
                        <div class="text-center text-muted py-4">ยังไม่มีประกาศข่าวสาร</div>
                    <?php else: ?>
                        <?php foreach ($announcements as $ann): ?>
                            <div class="list-group-item p-3">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <h6 class="mb-0 fw-semibold text-dark">
                                        <?php if ($ann['is_pinned']): ?>
                                            <span class="badge bg-danger-subtle text-danger me-1"><i class="bi bi-pin-angle-fill"></i> ปักหมุด</span>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($ann['title']) ?>
                                    </h6>
                                    <small class="text-muted text-nowrap ms-2"><?= formatThaiDate($ann['created_at'], false, true) ?></small>
                                </div>
                                <p class="text-muted small mb-1 text-truncate-2" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                    <?= htmlspecialchars(mb_substr($ann['content'], 0, 150)) ?>...
                                </p>
                                <div class="d-flex align-items-center gap-2 small text-muted">
                                    <span><i class="bi bi-person me-1"></i><?= htmlspecialchars($ann['author_name'] ?? 'ผู้ดูแลระบบ') ?></span>
                                    <span>&bull;</span>
                                    <span>เป้าหมาย: <strong><?= htmlspecialchars($ann['target_role'] === 'all' ? 'ทุกคน' : ($ann['target_role'] === 'teacher' ? 'ครู' : 'นักเรียน')) ?></strong></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
