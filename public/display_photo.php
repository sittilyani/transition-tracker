<?php
session_start();
include '../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("HTTP/1.0 401 Unauthorized");
    exit();
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($user_id > 0) {
    // Use mysqli to fetch the BLOB data
    $query = "SELECT photo FROM tblusers WHERE user_id = $user_id";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);

        if (!empty($row['photo'])) {
            // Get the BLOB data
            $image_data = $row['photo'];

            // Try to detect the image type from the first few bytes
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_buffer($finfo, $image_data);
            finfo_close($finfo);

            // If detection fails, try to detect by looking at the first bytes
            if ($mime_type === false || $mime_type === 'application/octet-stream') {
                // Check for JPEG signature
                if (bin2hex(substr($image_data, 0, 2)) == 'ffd8') {
                    $mime_type = 'image/jpeg';
                }
                // Check for PNG signature
                elseif (substr($image_data, 0, 8) == "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A") {
                    $mime_type = 'image/png';
                }
                // Check for GIF signature
                elseif (substr($image_data, 0, 3) == 'GIF') {
                    $mime_type = 'image/gif';
                } else {
                    $mime_type = 'image/jpeg'; // Default to JPEG
                }
            }

            // Set headers
            header("Content-Type: " . $mime_type);
            header("Content-Length: " . strlen($image_data));
            header("Cache-Control: max-age=86400"); // Cache for 24 hours

            // Output the image
            echo $image_data;
            exit();
        }
    }
}

// If no photo found, redirect to a default image or show 404
// Option 1: Show a default placeholder
$default_image = '../assets/images/default-user.png';
if (file_exists($default_image)) {
    $image_data = file_get_contents($default_image);
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $default_image);
    finfo_close($finfo);

    header("Content-Type: " . $mime_type);
    header("Content-Length: " . strlen($image_data));
    echo $image_data;
    exit();
}

// Option 2: Show a colored placeholder using GD (if GD is enabled)
if (function_exists('imagecreate')) {
    $width = 200;
    $height = 200;
    $image = imagecreate($width, $height);

    // Set background color (light gray)
    $bg_color = imagecolorallocate($image, 240, 240, 240);
    imagefill($image, 0, 0, $bg_color);

    // Set text color (dark gray)
    $text_color = imagecolorallocate($image, 100, 100, 100);

    // Add text
    $text = "No Photo";
    $font_size = 5;
    $text_width = imagefontwidth($font_size) * strlen($text);
    $text_height = imagefontheight($font_size);
    $x = ($width - $text_width) / 2;
    $y = ($height - $text_height) / 2;

    imagestring($image, $font_size, $x, $y, $text, $text_color);

    header("Content-Type: image/png");
    imagepng($image);
    imagedestroy($image);
    exit();
}

// Fallback
header("HTTP/1.0 404 Not Found");
echo "Photo not found";
exit();
?>