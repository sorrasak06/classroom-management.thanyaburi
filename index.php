<?php
/**
 * Classroom Management System - Root Index
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

// หาก Login แล้ว ให้ไปยัง Dashboard ของ Role นั้นๆ ทันที
if (isLoggedIn()) {
    redirectLoggedInUser();
} else {
    // หากยังไม่ Login ให้ไปยังหน้า Login
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}
