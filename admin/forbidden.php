<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Cek autentikasi admin
if (!isLoggedIn() || $_SESSION['role'] !== 'admin') {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$pageTitle = 'Access Forbidden';
$currentPage = 'dashboard';

include '../includes/admin-header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 text-center">
            <div class="card shadow-lg border-0">
                <div class="card-body p-5">
                    <div class="mb-4">
                        <i class="fas fa-exclamation-triangle text-danger fa-5x"></i>
                    </div>
                    
                    <h1 class="display-4 text-danger">Access Forbidden</h1>
                    
                    <p class="lead mb-4">You don't have permission to access this order information.</p>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle mr-2"></i>
                        To view order details, you need a token from the vendor who owns the products in this order.
                    </div>
                    
                    <div class="card bg-light mb-4">
                        <div class="card-body">
                            <h5>How to get access:</h5>
                            <ol class="text-left">
                                <li>Contact the vendor and request an access token</li>
                                <li>The vendor can generate a token from their dashboard</li>
                                <li>Enter the token when prompted on the dashboard</li>
                            </ol>
                        </div>
                    </div>
                    
                    <a href="<?= ADMIN_URL ?>/dashboard.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-arrow-left mr-2"></i> Return to Dashboard
                    </a>
                </div>
            </div>
            
            <div class="mt-4 text-muted">
                <small>For security purposes, all order access is restricted unless explicitly granted by the vendor.</small>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/admin-footer.php'; ?>