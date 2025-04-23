<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Pastikan user sudah login
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$pageTitle = 'Pesan Saya';

// Get chat partners
if ($user_role == 'customer') {
    // Get vendors this customer has chatted with
    $sql = "SELECT DISTINCT v.id as partner_id, v.shop_name, u.username,
           (SELECT COUNT(*) FROM messages WHERE sender_id = v.user_id AND receiver_id = $user_id AND is_read = 0) as unread_count,
           (SELECT created_at FROM messages 
            WHERE (sender_id = $user_id AND receiver_id = v.user_id) OR (sender_id = v.user_id AND receiver_id = $user_id)
            ORDER BY created_at DESC LIMIT 1) as last_message_time
           FROM messages m
           JOIN users u ON (m.sender_id = u.id OR m.receiver_id = u.id)
           JOIN vendors v ON u.id = v.user_id
           WHERE (m.sender_id = $user_id OR m.receiver_id = $user_id)
           AND u.id != $user_id
           ORDER BY last_message_time DESC";
} else {
    // Get vendor ID
    $vendor_query = "SELECT id FROM vendors WHERE user_id = $user_id";
    $vendor_result = $conn->query($vendor_query);
    $vendor_id = $vendor_result->fetch_assoc()['id'];
    
    // Get customers this vendor has chatted with
    $sql = "SELECT DISTINCT u.id as partner_id, u.username,
           (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = $user_id AND is_read = 0) as unread_count,
           (SELECT created_at FROM messages 
            WHERE (sender_id = $user_id AND receiver_id = u.id) OR (sender_id = u.id AND receiver_id = $user_id)
            ORDER BY created_at DESC LIMIT 1) as last_message_time
           FROM messages m
           JOIN users u ON (m.sender_id = u.id OR m.receiver_id = u.id)
           WHERE (m.sender_id = $user_id OR m.receiver_id = $user_id)
           AND u.id != $user_id AND u.role = 'customer'
           ORDER BY last_message_time DESC";
}

$result = $conn->query($sql);
$partners = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $partners[] = $row;
    }
}

include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Pesan Saya</h5>
                </div>
                
                <div class="card-body">
                    <?php if (empty($partners)): ?>
                        <div class="text-center text-muted py-5">
                            <p>Anda belum memiliki percakapan.</p>
                            <?php if ($user_role == 'customer'): ?>
                                <p>Mulai berbelanja dan chat dengan penjual!</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($partners as $partner): ?>
                                <a href="<?= SITE_URL ?>/chat.php?partner_id=<?= $partner['partner_id'] ?>" 
                                   class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($partner['shop_name'] ?? $partner['username']) ?></strong>
                                        <div class="small text-muted">
                                            Terakhir diupdate: <?= date('d/m/Y H:i', strtotime($partner['last_message_time'])) ?>
                                        </div>
                                    </div>
                                    <?php if ($partner['unread_count'] > 0): ?>
                                        <span class="badge badge-primary badge-pill"><?= $partner['unread_count'] ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>