<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$product_id = $_POST['product_id'] ?? 0;
$seller_id = $_POST['seller_id'] ?? 0;
$rating = $_POST['rating'] ?? 5;
$comment = $_POST['comment'] ?? '';

if (!$product_id) {
    header("Location: shop.php");
    exit();
}

// If seller_id is 0, set it to NULL
if (!$seller_id) {
    $seller_id = NULL;
}

// Check if user already reviewed this product
$check_stmt = $conn->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id = ?");
$check_stmt->bind_param("ii", $user_id, $product_id);
$check_stmt->execute();
$existing = $check_stmt->get_result()->fetch_assoc();
$check_stmt->close();

if ($existing) {
    // User already reviewed, redirect back
    header("Location: product_details.php?id=" . $product_id);
    exit();
}

// Handle file upload
$image_url = NULL;
$media_type = 'image';

if (isset($_FILES['review_media']) && $_FILES['review_media']['error'] == 0) {
    $upload_dir = '../uploads/reviews/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file = $_FILES['review_media'];
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Allowed file types
    $allowed_image_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $allowed_video_ext = ['mp4', 'webm', 'mov'];
    $allowed_ext = array_merge($allowed_image_ext, $allowed_video_ext);
    
    // Validate file type
    if (!in_array($file_ext, $allowed_ext)) {
        die("Invalid file type. Only images (JPG, PNG, GIF, WebP) and videos (MP4, WebM, MOV) are allowed.");
    }
    
    // Validate file size (max 10MB)
    $max_size = 10 * 1024 * 1024;
    if ($file_size > $max_size) {
        die("File size too large. Maximum size is 10MB.");
    }
    
    // Determine media type
    if (in_array($file_ext, $allowed_video_ext)) {
        $media_type = 'video';
    }
    
    // Generate unique filename
    $new_filename = 'review_' . $user_id . '_' . $product_id . '_' . time() . '.' . $file_ext;
    $file_path = $upload_dir . $new_filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file_tmp, $file_path)) {
        die("Failed to upload file.");
    }
    
    $image_url = 'uploads/reviews/' . $new_filename;
}

// Add columns if they don't exist
$conn->query("ALTER TABLE reviews ADD COLUMN IF NOT EXISTS image_url VARCHAR(255) NULL AFTER comment");
$conn->query("ALTER TABLE reviews ADD COLUMN IF NOT EXISTS media_type ENUM('image', 'video') DEFAULT 'image' AFTER image_url");

// Insert new review
if ($seller_id) {
    if ($image_url) {
        $stmt = $conn->prepare("INSERT INTO reviews (product_id, user_id, seller_id, rating, comment, image_url, media_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiisss", $product_id, $user_id, $seller_id, $rating, $comment, $image_url, $media_type);
    } else {
        $stmt = $conn->prepare("INSERT INTO reviews (product_id, user_id, seller_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiis", $product_id, $user_id, $seller_id, $rating, $comment);
    }
} else {
    if ($image_url) {
        $stmt = $conn->prepare("INSERT INTO reviews (product_id, user_id, rating, comment, image_url, media_type) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiisss", $product_id, $user_id, $rating, $comment, $image_url, $media_type);
    } else {
        $stmt = $conn->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiis", $product_id, $user_id, $rating, $comment);
    }
}

if (!$stmt->execute()) {
    die("Error inserting review: " . $stmt->error);
}
$stmt->close();

header("Location: product_details.php?id=" . $product_id);
exit();
?>
