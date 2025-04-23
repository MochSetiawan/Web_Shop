<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Pastikan user sudah login
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Anda harus login terlebih dahulu'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? '';

header('Content-Type: application/json');

// Mendapatkan conversations berdasarkan user_id dan role
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'getConversations') {
    $conversations = [];
    
    if ($role === 'customer') {
        $query = "SELECT c.*, v.shop_name as vendor_name, u.username as vendor_username 
                  FROM conversations c 
                  JOIN vendors v ON c.vendor_id = v.id 
                  JOIN users u ON v.user_id = u.id 
                  WHERE c.customer_id = $user_id 
                  ORDER BY c.updated_at DESC";
    } elseif ($role === 'vendor') {
        // Dapatkan vendor_id dari user_id
        $vendor_query = "SELECT id FROM vendors WHERE user_id = $user_id";
        $vendor_result = $conn->query($vendor_query);
        
        if ($vendor_result && $vendor_result->num_rows > 0) {
            $vendor_id = $vendor_result->fetch_assoc()['id'];
            
            $query = "SELECT c.*, u.username as customer_name 
                     FROM conversations c 
                     JOIN users u ON c.customer_id = u.id 
                     WHERE c.vendor_id = $vendor_id 
                     ORDER BY c.updated_at DESC";
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Vendor tidak ditemukan'
            ]);
            exit;
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Role tidak valid'
        ]);
        exit;
    }
    
    $result = $conn->query($query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $conversations[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'conversations' => $conversations
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal mendapatkan conversations'
        ]);
    }
    exit;
}

// Mendapatkan pesan dari conversation tertentu
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'getMessages' && isset($_GET['conversation_id'])) {
    $conversation_id = (int)$_GET['conversation_id'];
    
    // Verifikasi bahwa user memiliki akses ke conversation ini
    $access_query = "";
    
    if ($role === 'customer') {
        $access_query = "SELECT id FROM conversations WHERE id = $conversation_id AND customer_id = $user_id";
    } elseif ($role === 'vendor') {
        $vendor_query = "SELECT id FROM vendors WHERE user_id = $user_id";
        $vendor_result = $conn->query($vendor_query);
        
        if ($vendor_result && $vendor_result->num_rows > 0) {
            $vendor_id = $vendor_result->fetch_assoc()['id'];
            $access_query = "SELECT id FROM conversations WHERE id = $conversation_id AND vendor_id = $vendor_id";
        }
    }
    
    if (empty($access_query) || !$conn->query($access_query)->num_rows) {
        echo json_encode([
            'success' => false,
            'message' => 'Anda tidak memiliki akses ke conversation ini'
        ]);
        exit;
    }
    
    // Mendapatkan pesan
    $query = "SELECT m.*, 
             CASE WHEN m.sender_type = 'customer' THEN u_cust.username ELSE u_vend.username END as sender_name
             FROM chat_messages m
             LEFT JOIN conversations c ON m.conversation_id = c.id
             LEFT JOIN users u_cust ON c.customer_id = u_cust.id AND m.sender_type = 'customer'
             LEFT JOIN vendors v ON c.vendor_id = v.id
             LEFT JOIN users u_vend ON v.user_id = u_vend.id AND m.sender_type = 'vendor'
             WHERE m.conversation_id = $conversation_id
             ORDER BY m.created_at ASC";
    
    $result = $conn->query($query);
    $messages = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        
        // Update pesan menjadi 'read'
        $field_to_update = ($role === 'customer') ? 'unread_customer' : 'unread_vendor';
        $conn->query("UPDATE conversations SET $field_to_update = 0 WHERE id = $conversation_id");
        
        // Update status read di pesan
        if ($role === 'customer') {
            $conn->query("UPDATE chat_messages SET is_read = 1 
                         WHERE conversation_id = $conversation_id AND sender_type = 'vendor'");
        } else {
            $conn->query("UPDATE chat_messages SET is_read = 1 
                         WHERE conversation_id = $conversation_id AND sender_type = 'customer'");
        }
        
        echo json_encode([
            'success' => true,
            'messages' => $messages
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal mendapatkan pesan'
        ]);
    }
    exit;
}

// Mengirim pesan baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sendMessage') {
    $conversation_id = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : 0;
    $message = isset($_POST['message']) ? $_POST['message'] : '';
    $vendor_id = isset($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : 0;
    
    if (empty($message)) {
        echo json_encode([
            'success' => false,
            'message' => 'Pesan tidak boleh kosong'
        ]);
        exit;
    }
    
    // Sanitasi pesan
    $message = $conn->real_escape_string(htmlspecialchars($message));
    
    if ($conversation_id === 0 && $role === 'customer' && $vendor_id > 0) {
        // Buat conversation baru
        $conn->query("INSERT INTO conversations (customer_id, vendor_id, created_at, updated_at, last_message) 
                    VALUES ($user_id, $vendor_id, NOW(), NOW(), '$message')");
        
        $conversation_id = $conn->insert_id;
    } elseif ($conversation_id > 0) {
        // Verifikasi bahwa user memiliki akses ke conversation ini
        $access_query = "";
        
        if ($role === 'customer') {
            $access_query = "SELECT id FROM conversations WHERE id = $conversation_id AND customer_id = $user_id";
        } elseif ($role === 'vendor') {
            $vendor_query = "SELECT id FROM vendors WHERE user_id = $user_id";
            $vendor_result = $conn->query($vendor_query);
            
            if ($vendor_result && $vendor_result->num_rows > 0) {
                $vendor_id = $vendor_result->fetch_assoc()['id'];
                $access_query = "SELECT id FROM conversations WHERE id = $conversation_id AND vendor_id = $vendor_id";
            }
        }
        
        if (empty($access_query) || !$conn->query($access_query)->num_rows) {
            echo json_encode([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke conversation ini'
            ]);
            exit;
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Parameter tidak valid'
        ]);
        exit;
    }
    
    // Mengirim pesan
    $sender_type = ($role === 'customer') ? 'customer' : 'vendor';
    
    $query = "INSERT INTO chat_messages (conversation_id, sender_id, sender_type, message, created_at) 
             VALUES ($conversation_id, $user_id, '$sender_type', '$message', NOW())";
    
    if ($conn->query($query)) {
        // Update conversation dengan pesan terakhir dan waktu
        $field_to_update = ($role === 'customer') ? 'unread_vendor' : 'unread_customer';
        $conn->query("UPDATE conversations 
                     SET updated_at = NOW(), 
                         last_message = '$message', 
                         $field_to_update = $field_to_update + 1 
                     WHERE id = $conversation_id");
        
        echo json_encode([
            'success' => true,
            'conversation_id' => $conversation_id,
            'message' => 'Pesan berhasil dikirim'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal mengirim pesan: ' . $conn->error
        ]);
    }
    exit;
}

// Jika tidak ada action yang cocok
echo json_encode([
    'success' => false,
    'message' => 'Action tidak valid'
]);
?>