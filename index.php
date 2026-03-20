<?php
require_once __DIR__ . '/includes/config.php';
if (isLoggedIn()) {
    redirect(isAdmin() ? SITE_URL . '/admin/dashboard.php' : SITE_URL . '/employee/dashboard.php');
} else {
    redirect(SITE_URL . '/login.php');
}
