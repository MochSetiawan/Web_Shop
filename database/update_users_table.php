<?php
require_once '../includes/config.php';

// Tambahkan kolom baru ke tabel users
$alter_table = "
ALTER TABLE users 
ADD COLUMN email_verified TINYINT(1) DEFAULT 0,
ADD COLUMN otp_code VARCHAR(6) DEFAULT NULL,
ADD COLUMN otp_expire DATETIME DEFAULT NULL,
ADD COLUMN verification_attempts INT(11) DEFAULT 0;
";

if ($conn->query($alter_table)) {
    echo "Tabel users berhasil diperbarui dengan kolom verifikasi!";
} else {
    echo "Error: " . $conn->error;
}
?>