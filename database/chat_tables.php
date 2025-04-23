<?php
require_once 'includes/config.php';

// Membuat tabel untuk conversations (percakapan)
$create_conversations_table = "
CREATE TABLE IF NOT EXISTS `conversations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `last_message` text,
  `unread_customer` int(11) NOT NULL DEFAULT '0',
  `unread_vendor` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `vendor_id` (`vendor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// Membuat tabel untuk messages (pesan)
$create_messages_table = "
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `sender_type` enum('customer','vendor') NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `conversation_id` (`conversation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// Eksekusi query untuk membuat tabel
if ($conn->query($create_conversations_table)) {
    echo "Tabel conversations berhasil dibuat.<br>";
} else {
    echo "Error membuat tabel conversations: " . $conn->error . "<br>";
}

if ($conn->query($create_messages_table)) {
    echo "Tabel chat_messages berhasil dibuat.<br>";
} else {
    echo "Error membuat tabel chat_messages: " . $conn->error . "<br>";
}
?>