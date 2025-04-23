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
$pageTitle = 'Chat';

// Get partner ID
$partner_id = isset($_GET['partner_id']) ? (int)$_GET['partner_id'] : 0;

if (!$partner_id) {
    header('Location: ' . SITE_URL);
    exit;
}

// Verifikasi partner ada
if ($user_role == 'customer') {
    // Get vendor data
    $query = "SELECT v.id as vendor_id, u.id as user_id, v.shop_name, u.username
              FROM vendors v
              JOIN users u ON v.user_id = u.id
              WHERE v.id = $partner_id";
} else {
    // Get customer data
    $query = "SELECT u.id as user_id, u.username
              FROM users u
              WHERE u.id = $partner_id AND u.role = 'customer'";
}

$result = $conn->query($query);

if (!$result || $result->num_rows === 0) {
    header('Location: ' . SITE_URL);
    exit;
}

$partner = $result->fetch_assoc();
$partner_user_id = $partner['user_id'];

// Proses kirim pesan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    
    if (!empty($message)) {
        $message = $conn->real_escape_string(htmlspecialchars($message));
        
        $sql = "INSERT INTO messages (sender_id, receiver_id, message) 
                VALUES ($user_id, $partner_user_id, '$message')";
        
        if ($conn->query($sql)) {
            // Redirect untuk mencegah repost
            header('Location: ' . SITE_URL . '/chat.php?partner_id=' . $partner_id);
            exit;
        }
    }
}

// Get messages
$sql = "SELECT m.*, 
         u_sender.username as sender_name, 
         u_receiver.username as receiver_name
         FROM messages m
         JOIN users u_sender ON m.sender_id = u_sender.id
         JOIN users u_receiver ON m.receiver_id = u_receiver.id
         WHERE (m.sender_id = $user_id AND m.receiver_id = $partner_user_id)
            OR (m.sender_id = $partner_user_id AND m.receiver_id = $user_id)
         ORDER BY m.created_at ASC";

$messages_result = $conn->query($sql);
$messages = [];
if ($messages_result && $messages_result->num_rows > 0) {
    while ($row = $messages_result->fetch_assoc()) {
        $messages[] = $row;
    }
}

// Mark messages as read
$conn->query("UPDATE messages SET is_read = 1 
              WHERE sender_id = $partner_user_id 
              AND receiver_id = $user_id 
              AND is_read = 0");

// Get partner name
$partner_name = isset($partner['shop_name']) ? $partner['shop_name'] : $partner['username'];

include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Chat dengan <?= htmlspecialchars($partner_name) ?></h5>
                    <a href="javascript:history.back()" class="btn btn-sm btn-light">Kembali</a>
                </div>
                
                <div class="card-body">
                    <div id="chatContainer" class="chat-messages mb-4" style="height: 400px; overflow-y: auto; border: 1px solid #eee; padding: 15px; border-radius: 5px;">
                        <?php if (empty($messages)): ?>
                            <div class="text-center text-muted py-5">
                                <p>Belum ada pesan. Mulai percakapan dengan mengirim pesan pertama Anda.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                                <?php $is_own = $msg['sender_id'] == $user_id; ?>
                                <div class="message mb-3 <?= $is_own ? 'text-right' : 'text-left' ?>">
                                    <div class="d-inline-block p-3 rounded" 
                                         style="max-width: 80%; <?= $is_own ? 'background-color: #4e73df; color: white;' : 'background-color: #f0f2f5; color: #333;' ?>">
                                        <div class="message-sender small font-weight-bold mb-1">
                                            <?= htmlspecialchars($is_own ? 'Saya' : $msg['sender_name']) ?>
                                        </div>
                                        <div class="message-text">
                                            <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                        </div>
                                        <div class="message-time small text-right mt-1" 
                                             style="<?= $is_own ? 'opacity: 0.7;' : 'color: #666;' ?>">
                                            <?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <form method="post" action="">
                        <div class="input-group">
                            <textarea class="form-control" placeholder="Tulis pesan..." name="message" required></textarea>
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-paper-plane"></i> Kirim
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-scroll to bottom
document.addEventListener('DOMContentLoaded', function() {
    const chatContainer = document.getElementById('chatContainer');
    chatContainer.scrollTop = chatContainer.scrollHeight;
});
</script>

<?php include 'includes/footer.php'; ?>