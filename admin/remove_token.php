<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Cek autentikasi admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

// Set default return page
$return_page = isset($_GET['return']) ? $_GET['return'] : 'dashboard.php';
$redirect_url = ADMIN_URL . '/' . $return_page;

// Hapus semua token
if (isset($_GET['all']) && $_GET['all'] == 1) {
    $_SESSION['valid_tokens'] = [];
    $_SESSION['success_message'] = 'Semua token akses telah dihapus.';
    
    // Hapus semua token message flags
    foreach ($_SESSION as $key => $value) {
        if (strpos($key, 'token_message_shown_') === 0) {
            unset($_SESSION[$key]);
        }
    }
    
    header('Location: ' . $redirect_url);
    exit;
}

// Hapus token spesifik
if (isset($_GET['order_id']) && !empty($_GET['order_id'])) {
    $order_id = (int)$_GET['order_id'];
    
    if (isset($_SESSION['valid_tokens'][$order_id])) {
        $token = $_SESSION['valid_tokens'][$order_id]['token'];
        unset($_SESSION['valid_tokens'][$order_id]);
        
        $_SESSION['success_message'] = 'Token akses untuk Pesanan #' . $order_id . ' telah dihapus.';
    }
    
    // Redirect ke halaman sebelumnya jika ada
    header('Location: ' . $redirect_url);
    exit;
}

// Jika tidak ada parameter yang valid, kembali ke dashboard
header('Location: ' . $redirect_url);
exit;