<?php
/**
 * Fungsi upload gambar dengan debug info
 */
function uploadImageDebug($file, $uploadDir) {
    // Pastikan direktori ada
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return "Error: Gagal membuat direktori $uploadDir";
        }
    }
    
    // Log info file
    $debugInfo = "File info: " . print_r($file, true) . "\n";
    
    // Validasi file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return "Error: Upload error code " . $file['error'];
    }
    
    if ($file['size'] <= 0) {
        return "Error: File size is zero";
    }
    
    if ($file['size'] > 5242880) { // 5MB
        return "Error: File too large (" . round($file['size']/1024/1024, 2) . "MB)";
    }
    
    // Validasi tipe file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $fileInfo = @getimagesize($file['tmp_name']);
    
    if (!$fileInfo) {
        return "Error: Not a valid image file";
    }
    
    if (!in_array($fileInfo['mime'], $allowedTypes)) {
        return "Error: Invalid mime type " . $fileInfo['mime'];
    }
    
    // Generate nama file unik
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid('cat_') . '.' . $extension;
    $targetFile = $uploadDir . $filename;
    
    // Upload file
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        $debugInfo .= "Success: File uploaded to $targetFile";
        error_log($debugInfo);
        return $filename;
    } else {
        $phpFileUploadErrors = [
            1 => 'File exceeds upload_max_filesize in php.ini',
            2 => 'File exceeds MAX_FILE_SIZE in HTML form',
            3 => 'File was only partially uploaded',
            4 => 'No file was uploaded',
            6 => 'Missing a temporary folder',
            7 => 'Failed to write file to disk',
            8 => 'A PHP extension stopped the file upload'
        ];
        
        $errorMsg = ($file['error'] > 0) ? $phpFileUploadErrors[$file['error']] : 'Unknown error';
        return "Error: Failed to move uploaded file - $errorMsg";
    }
}
?>