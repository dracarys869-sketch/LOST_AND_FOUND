<?php
/**
 * Dashboard Router — redirects to role-specific dashboard
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
header('Location: ' . dashboardUrl());
exit;
