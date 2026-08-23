<?php
/**
 * Notifications - Mark As Read Endpoint
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';

requireAuth();

$currentUser = getCurrentUser();
$userId = (int)$currentUser['id'];

$notificationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$markAll = isset($_GET['all']) && $_GET['all'] === '1';

try {
    if ($markAll) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$userId]);
    } elseif ($notificationId > 0) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $userId]);
    }
} catch (PDOException $e) {}

// Redirect back
$referer = $_SERVER['HTTP_REFERER'] ?? BASE_URL . 'notifications/notifications.php';
header('Location: ' . $referer);
exit;
