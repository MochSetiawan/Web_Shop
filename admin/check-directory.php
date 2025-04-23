<?php
// Script untuk memeriksa dan memperbaiki direktori upload
$uploadDir = '../assets/img/';

if (!file_exists($uploadDir)) {
    if (mkdir($uploadDir, 0755, true)) {
        echo "Directory created successfully: $uploadDir";
    } else {
        echo "Failed to create directory: $uploadDir";
    }
} else {
    echo "Directory exists: $uploadDir";
    
    // Periksa izin direktori
    if (is_writable($uploadDir)) {
        echo "<br>Directory is writable";
    } else {
        echo "<br>Directory is NOT writable. Attempting to set permissions...";
        
        if (chmod($uploadDir, 0755)) {
            echo "<br>Permissions updated successfully";
        } else {
            echo "<br>Failed to update permissions";
        }
    }
}
?>