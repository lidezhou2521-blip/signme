<?php
require_once 'config.php';

// Simple routing logic
$page = isset($_GET['page']) ? $_GET['page'] : 'login';

if (!isset($_SESSION['user_id']) && $page !== 'login') {
    header('Location: index.php?page=login');
    exit();
}

switch ($page) {
    case 'login':
        include 'login.php';
        break;
    case 'dashboard':
        include 'dashboard.php';
        break;
    case 'sign':
        include 'sign.php';
        break;
    case 'team':
        include 'team.php';
        break;
    case 'settings':
        include 'settings.php';
        break;
    case 'logout':
        session_destroy();
        header('Location: index.php?page=login');
        exit();
    default:
        include 'login.php';
        break;
}
?>
