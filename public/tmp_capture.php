<?php
/**
 * TEMPORARY capture helper — REMOVE after screenshot session
 * Usage: /tmp_capture.php?role=superadmin&url=/superadmin/companies
 * 
 * Directly sets $_SESSION variables for screenshot capture.
 * NEVER USE IN PRODUCTION.
 */
session_start();
$role = $_GET['role'] ?? '';
$url = $_GET['url'] ?? '/';
$cid = $_GET['cid'] ?? '9';

// Unset any existing auth
unset($_SESSION['user_id'], $_SESSION['role_code'], $_SESSION['company_id'], $_SESSION['user_name']);

switch ($role) {
    case 'superadmin':
        $_SESSION['user_id'] = 1;
        $_SESSION['role_code'] = 'superadmin';
        $_SESSION['user_name'] = 'Суперадминистратор';
        break;
    case 'owner':
        $_SESSION['user_id'] = 5;
        $_SESSION['role_code'] = 'company_owner';
        $_SESSION['company_id'] = (int)$cid;
        $_SESSION['user_name'] = 'Руководитель Тест';
        break;
    case 'logist1':
        $_SESSION['user_id'] = 5; // central user_id for session
        $_SESSION['role_code'] = 'logist';
        $_SESSION['company_id'] = (int)$cid;
        $_SESSION['user_name'] = 'Логист Runtime 1';
        $_SESSION['local_user_id'] = 1;
        break;
    case 'logist2':
        $_SESSION['user_id'] = 5;
        $_SESSION['role_code'] = 'logist';
        $_SESSION['company_id'] = (int)$cid;
        $_SESSION['user_name'] = 'Логист Runtime 2';
        $_SESSION['local_user_id'] = 2;
        break;
}

// Save session and redirect
session_write_close();
header('Location: ' . $url);
exit;
