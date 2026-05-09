<?php
session_start();
require_once 'db.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
if (!$is_logged_in) {
    echo '<div style="background: #fff3cd; color: #856404; padding: 10px; margin: 10px; border-radius: 5px; text-align: center;">
            <strong>Notice:</strong> You must be <a href="login.php" style="color: #856404; font-weight: bold;">logged in</a> to add items to cart.
          </div>';
}

// Get product ID from URL
$product_id = $_GET['id'] ?? 0;
if (!$product_id) {
    header("Location: shop.php");
    exit();
}

// Get product details
$stmt = $conn->prepare("SELECT p.*, u.first_name, u.last_name, u.id as seller_id FROM products p LEFT JOIN users u ON p.seller_id = u.id WHERE p.id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

// Check if product is in user's wishlist
$is_in_wishlist = false;
if ($is_logged_in) {
    $wishlist_stmt = $conn->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $wishlist_stmt->bind_param("ii", $_SESSION['user_id'], $product_id);
    $wishlist_stmt->execute();
    $wishlist_result = $wishlist_stmt->get_result();
    $is_in_wishlist = $wishlist_result->num_rows > 0;
    $wishlist_stmt->close();
}

if (!$product) {
    header("Location: shop.php");
    exit();
}

// Get related products (same category, excluding current product)
$related_stmt = $conn->prepare("SELECT id, name, price, original_price, image_url FROM products WHERE category = ? AND id != ? ORDER BY RAND() LIMIT 4");
$related_stmt->bind_param("si", $product['category'], $product_id);
$related_stmt->execute();
$related_result = $related_stmt->get_result();

// Get more recommended products from same category (for the shop category section)
$recommended_stmt = $conn->prepare("SELECT id, name, price, original_price, image_url FROM products WHERE category = ? AND id != ? ORDER BY RAND() LIMIT 8");
$recommended_stmt->bind_param("si", $product['category'], $product_id);
$recommended_stmt->execute();
$recommended_result = $recommended_stmt->get_result();

// Get reviews for this product
$reviews_stmt = $conn->prepare("SELECT r.*, u.first_name, u.last_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? ORDER BY r.created_at DESC");
$reviews_stmt->bind_param("i", $product_id);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();
$reviews_stmt->close();

// Get total number of reviews for this product
$total_reviews_stmt = $conn->prepare("SELECT COUNT(*) as total_reviews FROM reviews WHERE product_id = ?");
$total_reviews_stmt->bind_param("i", $product_id);
$total_reviews_stmt->execute();
$total_reviews_result = $total_reviews_stmt->get_result();
$total_reviews_data = $total_reviews_result->fetch_assoc();
$total_reviews = $total_reviews_data['total_reviews'];
$total_reviews_stmt->close();

// Get count of reviews for each star rating
$rating_counts_stmt = $conn->prepare("SELECT rating, COUNT(*) as count FROM reviews WHERE product_id = ? GROUP BY rating ORDER BY rating DESC");
$rating_counts_stmt->bind_param("i", $product_id);
$rating_counts_stmt->execute();
$rating_counts_result = $rating_counts_stmt->get_result();
$rating_counts = array();
while ($row = $rating_counts_result->fetch_assoc()) {
    $rating_counts[$row['rating']] = $row['count'];
}
$rating_counts_stmt->close();

// Initialize all star counts to 0
for ($i = 1; $i <= 5; $i++) {
    if (!isset($rating_counts[$i])) {
        $rating_counts[$i] = 0;
    }
}

// Check if current user has already reviewed this product
$user_has_reviewed = false;
$user_review = null;
if ($is_logged_in) {
    $user_review_stmt = $conn->prepare("SELECT r.*, u.first_name, u.last_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? AND r.user_id = ?");
    $user_review_stmt->bind_param("ii", $product_id, $_SESSION['user_id']);
    $user_review_stmt->execute();
    $user_review_result = $user_review_stmt->get_result();
    if ($user_review_result->num_rows > 0) {
        $user_has_reviewed = true;
        $user_review = $user_review_result->fetch_assoc();
    }
    $user_review_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ReBuy</title>
    <link rel="icon" type="image/x-icon" href="../../assets/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/header-footer.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
        }

        .product-page {
            max-width: 1300px;
            margin: 0 auto;
            padding: 25px;
        }

        .product-container {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            background: white;
            padding: 35px;
            border-radius: 12px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .product-images {
            position: relative;
        }

        .main-image {
            width: 80%;
            height: 500px;
            background: #f8f9fa;
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #e9ecef;
        }

        .main-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .main-image:hover img {
            transform: scale(1.05);
        }

        .image-thumbnails {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .thumbnail {
            width: 100px;
            height: 100px;
            border-radius: 6px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .thumbnail:hover,
        .thumbnail.active {
            border-color: #2d5016;
        }

        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .product-info {
            padding: 5px 0;
        }

        .product-badge {
            display: inline-block;
            background: #e8f5e8;
            color: #2d5016;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .product-title {
            font-size: 30px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 5px;
            line-height: 1;
        }

        .product-rating {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .stars {
            display: flex;
            gap: 1px;
        }

        .star {
            color: #ffc107;
            font-size: 15px;
        }

        .star.empty {
            color: #e9ecef;
        }

        .rating-text {
            color: #666;
            font-size: 14px;
        }

        .product-price {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
            padding: 20px 0;
            border-top: 1px solid #e9ecef;
            border-bottom: 1px solid #e9ecef;
        }

        .current-price {
            font-size: 32px;
            font-weight: 700;
            color: #2d5016;
        }

        .original-price {
            font-size: 20px;
            color: #999;
            text-decoration: line-through;
        }

        .discount-badge {
            background: #ff4444;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
        }

        .product-meta {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 15px;
        }

        .meta-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .meta-icon {
            font-size: 22px;
            color: #2d5016;
            margin-bottom: 8px;
        }

        .meta-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }

        .meta-value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .product-description {
            margin-bottom: 15px;
        }

        .description-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #1a1a1a;
        }

        .description-text {
            line-height: 1.2;
            color: #666;
        }

        .weight-options {
            margin-bottom: 20px;
        }

        .weight-label {
            font-size: 14px;
            font-weight: 600;
            color: #666;
            margin-bottom: 10px;
        }

        .weight-buttons {
            display: flex;
            gap: 10px;
        }

        .weight-btn {
            padding: 10px 20px;
            border: 2px solid #e9ecef;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            transition: all 0.3s ease;
        }

        .weight-btn:hover {
            border-color: #2d5016;
            color: #2d5016;
        }

        .weight-btn.active {
            background: #2d5016;
            color: white;
            border-color: #2d5016;
        }

        .seller-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .seller-details {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .seller-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #2d5016;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
        }

        .seller-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .seller-status {
            font-size: 12px;
            color: #28a745;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }

        .quantity-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .quantity-label {
            font-size: 14px;
            color: #666;
            font-weight: 600;
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
        }

        .quantity-btn {
            background: white;
            border: none;
            width: 40px;
            height: 40px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-btn:hover {
            background: #f8f9fa;
        }

        .quantity-input {
            border: none;
            width: 50px;
            height: 40px;
            text-align: center;
            font-size: 16px;
            font-weight: 600;
        }

        .btn-add-cart {
            background: #2d5016;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            flex: 1;
            justify-content: center;
        }

        .btn-add-cart:hover {
            background: #1e3009;
            transform: translateY(-2px);
        }

        .btn-buy-now {
            background: #f4c430;
            color: #333;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
        }

        .btn-buy-now:hover {
            background: #e6b800;
            transform: translateY(-2px);
        }

        .btn-wishlist {
            background: white;
            color: #e74c3c;
            border: 2px solid #e74c3c;
            width: 50px;
            height: 50px;
            border-radius: 8px;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .btn-wishlist:hover {
            background: #e74c3c;
            color: white;
            transform: scale(1.1);
        }

        .btn-wishlist.in-wishlist {
            background: #e74c3c;
            color: white;
        }

        .btn-wishlist.in-wishlist:hover {
            background: #c0392b;
        }

        .product-features {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .feature {
            text-align: center;
            padding: 15px;
        }

        .feature-icon {
            font-size: 24px;
            color: #2d5016;
            margin-bottom: 8px;
        }

        .feature-text {
            font-size: 12px;
            color: #666;
        }

        .related-products {
            margin-bottom: 60px;
        }

        .section-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .section-title {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 10px;
        }

        .section-subtitle {
            color: #666;
            font-size: 16px;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
        }

        .related-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .related-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .related-image {
            height: 200px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .related-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .related-card:hover .related-image img {
            transform: scale(1.05);
        }

        .related-discount {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ff4444;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .related-info {
            padding: 20px;
        }

        .related-category {
            font-size: 12px;
            color: #666;
            margin-bottom: 8px;
        }

        .related-name {
            font-weight: 600;
            margin-bottom: 12px;
            color: #1a1a1a;
            font-size: 16px;
            line-height: 1.4;
        }

        .related-price {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .related-current {
            color: #2d5016;
            font-weight: 700;
            font-size: 18px;
        }

        .related-original {
            color: #999;
            text-decoration: line-through;
            font-size: 14px;
        }

        .add-to-cart-btn {
            width: 100%;
            background: #2d5016;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            margin-top: 15px;
        }

        .add-to-cart-btn:hover {
            background: #1e3009;
        }

        .featured-categories {
            margin-bottom: 60px;
            padding: 60px 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .category-card {
            background: white;
            padding: 30px 20px;
            border-radius: 12px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
        }

        .category-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #2d5016, #4a7c2e);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .category-card:hover::before {
            transform: scaleX(1);
        }

        .category-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
        }

        .category-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #2d5016, #4a7c2e);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            transition: all 0.3s ease;
        }

        .category-card:hover .category-icon {
            transform: scale(1.1);
            box-shadow: 0 8px 20px rgba(45, 80, 22, 0.3);
        }

        .category-card h3 {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 10px;
        }

        .category-card p {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .category-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #2d5016;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .category-link:hover {
            color: #4a7c2e;
            gap: 12px;
        }

        .category-link i {
            font-size: 12px;
            transition: transform 0.3s ease;
        }

        .category-card:hover .category-link i {
            transform: translateX(4px);
        }

        @media (max-width: 1024px) {
            .product-container {
                grid-template-columns: 1fr;
                gap: 40px;
            }

            .related-grid {
                grid-template-columns: repeat(3, 1fr);
            }

            .product-features {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .product-page {
                padding: 15px;
            }

            .product-container {
                padding: 25px;
            }

            .main-image {
                height: 350px;
            }

            .product-title {
                font-size: 24px;
            }

            .current-price {
                font-size: 24px;
            }

            .product-meta {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .related-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .product-features {
                grid-template-columns: 1fr;
            }
        }

        .shop-hero {
            background: linear-gradient(135deg, #2d5016 0%, #4a7c2e 100%);
            color: white;
            padding: 30px 0 30px;
            text-align: center;
        }
        .shop-hero h1 {
            font-size: 35px;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .shop-hero p {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 20px;
        }

        /* Product Tabs */
        .product-tabs-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }

        .tabs-header {
            display: flex;
            gap: 30px;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 25px;
        }

        .tab-btn {
            padding: 15px 0;
            background: none;
            border: none;
            font-size: 16px;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            position: relative;
            transition: color 0.3s;
        }

        .tab-btn:hover {
            color: #2d5016;
        }

        .tab-btn.active {
            color: #2d5016;
        }

        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: #2d5016;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .tab-description {
            line-height: 1.8;
            color: #666;
            font-size: 15px;
        }

        .additional-info-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .additional-info-table td {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            font-size: 14px;
        }

        .additional-info-table td:first-child {
            font-weight: 600;
            color: #333;
            width: 200px;
            background: #f8f9fa;
        }

        .additional-info-table td:last-child {
            color: #666;
        }

        .review-section {
            color: #666;
            font-size: 15px;
        }

        .review-form {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .review-form h3 {
            font-size: 18px;
            color: #333;
            margin-bottom: 20px;
        }

        .rating-input {
            display: flex;
            gap: 5px;
            margin-bottom: 15px;
        }

        .rating-input input {
            display: none;
        }

        .rating-input label {
            font-size: 24px;
            color: #ddd;
            cursor: pointer;
            transition: color 0.3s;
        }

        .rating-input label.active,
        .rating-input label:hover {
            color: #ffc107;
        }

        .review-textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            resize: vertical;
            min-height: 100px;
            margin-bottom: 15px;
        }

        .review-textarea:focus {
            outline: none;
            border-color: #2d5016;
        }

        .submit-review-btn {
            background: #2d5016;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .submit-review-btn:hover {
            background: #1a3009;
        }

        .reviews-list {
            margin-top: 30px;
        }

        .review-item {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            margin-bottom: 15px;
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .reviewer-name {
            font-weight: 600;
            color: #333;
        }

        .review-date {
            font-size: 12px;
            color: #999;
        }

        .review-rating {
            color: #ffc107;
            margin-bottom: 10px;
        }

        .review-comment {
            color: #666;
            line-height: 1.6;
        }

        .review-media {
            margin-top: 15px;
            border-radius: 8px;
            overflow: hidden;
            max-width: 100%;
        }

        .review-media img {
            max-width: 100%;
            max-height: 300px;
            object-fit: cover;
            border-radius: 8px;
        }

        .review-media video {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
        }

        .no-reviews {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        /* Footer Features */
        .footer-features {
            display: flex;
            justify-content: center;
            gap: 60px;
            padding: 40px 0;
            background: white;
            border-bottom: 1px solid #e9ecef;
        }

        .footer-feature {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .footer-feature-icon {
            width: 60px;
            height: 60px;
            background: #2d5016;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .footer-feature-text h4 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .footer-feature-text p {
            font-size: 13px;
            color: #666;
        }
    </style>
</head>
<body>
    <?php include '_header.php'; ?>

         <!-- Shop Hero Section -->
    <section class="shop-hero">
        <div class="container">
            <h1>Shop</h1>
            <p>Discover our amazing collections</p>
        </div>
    </section>

    <div class="product-page">

        <!-- Product Container -->
        <div class="product-container">
            <div class="product-images">
                <div class="main-image">
                    <img src="<?php echo !empty($product['image_url']) ? '../' . htmlspecialchars($product['image_url']) : 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=400&q=80'; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                </div>
                <div class="image-thumbnails">
                    <div class="thumbnail active">
                        <img src="<?php echo !empty($product['image_url']) ? '../' . htmlspecialchars($product['image_url']) : 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=400&q=80'; ?>" alt="Thumbnail 1">
                    </div>
                </div>
            </div>

            <div class="product-info">
                <div class="product-badge" style="<?php echo ($product['stock_quantity'] ?? 0) <= 0 ? 'background: #dc3545;' : ''; ?>">
                    <?php echo ($product['stock_quantity'] ?? 0) <= 0 ? 'Out of Stock' : 'In Stock'; ?>
                </div>
                
                <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>
                
                <div class="product-rating">
                    <div class="stars">
                        <?php 
                        $rating = $product['rating'] ?? 0;
                        for ($i = 1; $i <= 5; $i++) {
                            if ($i <= $rating) {
                                echo '<i class="fas fa-star star"></i>';
                            } else {
                                echo '<i class="fas fa-star star empty"></i>';
                            }
                        }
                        ?>
                    </div>
                    <span class="rating-text"><?php echo number_format($rating, 1); ?> (<?php echo $total_reviews; ?> reviews)</span>
                </div>
                
                <!-- Rating Breakdown -->
                <div class="rating-breakdown" style="margin-bottom: 15px; background: #f8f9fa; padding: 15px; border-radius: 8px;">
                    <div style="font-weight: 600; margin-bottom: 10px; color: #333;">Rating Breakdown</div>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 5px;">
                            <span style="width: 60px; font-size: 14px;"><?php echo $i; ?> stars</span>
                            <div style="flex: 1; background: #e9ecef; height: 8px; border-radius: 4px; overflow: hidden;">
                                <div style="background: #ffc107; height: 100%; width: <?php echo $total_reviews > 0 ? ($rating_counts[$i] / $total_reviews) * 100 : 0; ?>%; transition: width 0.3s ease;"></div>
                            </div>
                            <span style="width: 40px; text-align: right; font-size: 14px; color: #666;"><?php echo $rating_counts[$i]; ?></span>
                        </div>
                    <?php endfor; ?>
                </div>

                <div class="product-price">
                    <span class="current-price">₱<?php echo number_format($product['price'], 2); ?></span>
                    <?php if ($product['original_price'] && $product['original_price'] > $product['price']): ?>
                        <span class="original-price">₱<?php echo number_format($product['original_price'], 2); ?></span>
                        <span class="discount-badge">-<?php echo round((($product['original_price'] - $product['price']) / $product['original_price']) * 100); ?>%</span>
                    <?php endif; ?>
                </div>

                <div class="product-meta">
                    <div class="meta-item">
                        <div class="meta-icon"><i class="fas fa-tag"></i></div>
                        <div class="meta-label">Category</div>
                        <div class="meta-value"><?php echo htmlspecialchars(ucfirst($product['category'])); ?></div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-icon"><i class="fas fa-box"></i></div>
                        <div class="meta-label">Stock</div>
                        <div class="meta-value"><?php echo $product['stock_quantity'] ?? 0; ?> units</div>
                    </div>
                    <div class="meta-item">
                        <div class="meta-icon"><i class="fas fa-truck"></i></div>
                        <div class="meta-label">Shipping</div>
                        <div class="meta-value">Free delivery</div>
                    </div>
                </div>

                <div class="product-description">
                    <div class="description-title">Description</div>
                    <div class="description-text">
                        <?php echo nl2br(htmlspecialchars($product['description'] ?? 'No description available for this product.')); ?>
                    </div>
                </div>

                <?php if ($product['first_name'] || $product['last_name']): ?>
                    <div class="seller-info">
                        <div class="seller-details">
                            <div class="seller-avatar">
                                <?php echo strtoupper(substr($product['first_name'] ?? 'S', 0, 1) . substr($product['last_name'] ?? 'S', 0, 1)); ?>
                            </div>
                            <div>
                                <div class="seller-name">Sold by: <?php echo htmlspecialchars($product['first_name'] . ' ' . $product['last_name']); ?></div>
                                <div class="seller-status">
                                    <i class="fas fa-check-circle"></i> Verified Seller
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <a href="message.php?user_id=<?php echo $product['seller_id']; ?>" class="btn-add-cart" style="width: auto; padding: 10px 20px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                                <i class="fas fa-envelope"></i> Contact Seller
                            </a>
                            <a href="seller_profile.php?seller_id=<?php echo $product['seller_id']; ?>" class="btn-add-cart" style="width: auto; padding: 10px 20px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                                <i class="fas fa-store"></i> Visit Store
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="action-buttons">
                    <div class="quantity-section">
                        <span class="quantity-label">Quantity:</span>
                        <div class="quantity-selector">
                            <button class="quantity-btn" onclick="changeQuantity(-1)">-</button>
                            <input type="number" id="quantity" class="quantity-input" value="1" min="1" max="<?php echo $product['stock_quantity'] ?? 1; ?>">
                            <button class="quantity-btn" onclick="changeQuantity(1)">+</button>
                        </div>
                    </div>
                </div>

                <div class="action-buttons">
                    <?php if ($is_logged_in): ?>
                        <button class="btn-wishlist <?php echo $is_in_wishlist ? 'in-wishlist' : ''; ?>" onclick="toggleWishlist(<?php echo $product_id; ?>, this)" title="<?php echo $is_in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                            <i class="<?php echo $is_in_wishlist ? 'fas' : 'far'; ?> fa-heart"></i>
                        </button>
                    <?php else: ?>
                        <a href="login.php" class="btn-wishlist" title="Add to Wishlist">
                            <i class="far fa-heart"></i>
                        </a>
                    <?php endif; ?>
                    <?php if (($product['stock_quantity'] ?? 0) > 0): ?>
                        <button class="btn-add-cart" onclick="addToCart(<?php echo $product_id; ?>, event)">
                            <i class="fas fa-shopping-cart"></i> Add to Cart
                        </button>
                        <button class="btn-buy-now" onclick="buyNow(<?php echo $product_id; ?>)">
                            Buy Now
                        </button>
                    <?php else: ?>
                        <button class="btn-add-cart" disabled style="opacity: 0.5; cursor: not-allowed;">
                            <i class="fas fa-shopping-cart"></i> Out of Stock
                        </button>
                        <button class="btn-buy-now" disabled style="opacity: 0.5; cursor: not-allowed;">
                            Out of Stock
                        </button>
                    <?php endif; ?>
                </div>

                <div class="product-features">
                    <div class="feature">
                        <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                        <div class="feature-text">1 Year Warranty</div>
                    </div>
                    <div class="feature">
                        <div class="feature-icon"><i class="fas fa-undo"></i></div>
                        <div class="feature-text">30-Day Returns</div>
                    </div>
                    <div class="feature">
                        <div class="feature-icon"><i class="fas fa-headset"></i></div>
                        <div class="feature-text">24/7 Support</div>
                    </div>
                    <div class="feature">
                        <div class="feature-icon"><i class="fas fa-lock"></i></div>
                        <div class="feature-text">Secure Payment</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Tabs -->
        <div class="product-tabs-section">
            <div class="tabs-header">
                <button class="tab-btn active" data-tab="description">Description</button>
                <button class="tab-btn" data-tab="additional">Additional Information</button>
                <button class="tab-btn" data-tab="review">Review</button>
            </div>
            <div class="tabs-content">
                <div class="tab-content active" id="description">
                    <div class="tab-description">
                        <?php echo nl2br(htmlspecialchars($product['description'] ?? 'No description available for this product.')); ?>
                    </div>
                </div>
                <div class="tab-content" id="additional">
                    <div class="additional-info-table">
                        <table>
                            <tr>
                                <td>Product Type</td>
                                <td><?php echo htmlspecialchars(ucfirst($product['category'])); ?></td>
                            </tr>
                            <tr>
                                <td>Origin</td>
                                <td>Local</td>
                            </tr>
                            <tr>
                                <td>Color</td>
                                <td>As shown</td>
                            </tr>
                            <tr>
                                <td>Guarantee</td>
                                <td>30 Days</td>
                            </tr>
                            <tr>
                                <td>Barcode</td>
                                <td><?php echo 'PRD' . str_pad($product['id'], 6, '0', STR_PAD_LEFT); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="tab-content" id="review">
                    <div class="review-section">
                        <?php if ($is_logged_in): ?>
                            <?php if ($user_has_reviewed): ?>
                                <div class="review-form">
                                    <h3>Your Review</h3>
                                    <div class="review-item" style="background: #f8f9fa; border: none;">
                                        <div class="review-header">
                                            <div class="reviewer-name">
                                                <?php echo htmlspecialchars($user_review['first_name'] . ' ' . $user_review['last_name']); ?>
                                            </div>
                                            <div class="review-date">
                                                <?php echo date('F j, Y', strtotime($user_review['created_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="review-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php if ($i <= $user_review['rating']): ?>
                                                    <i class="fas fa-star"></i>
                                                <?php else: ?>
                                                    <i class="far fa-star"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="review-comment">
                                            <?php echo nl2br(htmlspecialchars($user_review['comment'])); ?>
                                        </div>
                                        <?php if (!empty($user_review['image_url'])): ?>
                                            <div class="review-media">
                                                <?php if (($user_review['media_type'] ?? 'image') == 'video'): ?>
                                                    <video controls>
                                                        <source src="<?php echo '../' . htmlspecialchars($user_review['image_url']); ?>" type="video/mp4">
                                                        Your browser does not support the video tag.
                                                    </video>
                                                <?php else: ?>
                                                    <img src="<?php echo '../' . htmlspecialchars($user_review['image_url']); ?>" alt="Review image">
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <p style="color: #666; margin-top: 15px; font-size: 14px;">You have already reviewed this product.</p>
                                </div>
                            <?php else: ?>
                                <div class="no-reviews">
                                    <p>You can write a review for this product after your order is delivered.</p>
                                    <p style="margin-top: 10px;"><a href="settings.php#orders" style="color: #2d5016; font-weight: 600;">View your orders</a> to write a review.</p>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="no-reviews">
                                <p>Please <a href="login.php" style="color: #2d5016; font-weight: 600;">login</a> to write a review.</p>
                            </div>
                        <?php endif; ?>

                        <div class="reviews-list">
                            <h3 style="font-size: 18px; color: #333; margin-bottom: 20px;">Customer Reviews</h3>
                            <?php if ($reviews_result->num_rows > 0): ?>
                                <?php while ($review = $reviews_result->fetch_assoc()): ?>
                                    <div class="review-item">
                                        <div class="review-header">
                                            <div class="reviewer-name">
                                                <?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?>
                                            </div>
                                            <div class="review-date">
                                                <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                                            </div>
                                        </div>
                                        <div class="review-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php if ($i <= $review['rating']): ?>
                                                    <i class="fas fa-star"></i>
                                                <?php else: ?>
                                                    <i class="far fa-star"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="review-comment">
                                            <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                                        </div>
                                        <?php if (!empty($review['image_url'])): ?>
                                            <div class="review-media">
                                                <?php if (($review['media_type'] ?? 'image') == 'video'): ?>
                                                    <video controls>
                                                        <source src="<?php echo '../' . htmlspecialchars($review['image_url']); ?>" type="video/mp4">
                                                        Your browser does not support the video tag.
                                                    </video>
                                                <?php else: ?>
                                                    <img src="<?php echo '../' . htmlspecialchars($review['image_url']); ?>" alt="Review image">
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="no-reviews">
                                    <p>No reviews yet. Be the first to review this product!</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        
        <?php if ($related_result->num_rows > 0): ?>
        <div class="related-products">
            <div class="section-header">
                <h2 class="section-title">Explore Related Products</h2>
                <p class="section-subtitle">Discover similar products that might interest you</p>
            </div>
            <div class="related-grid">
                <?php while ($related = $related_result->fetch_assoc()): ?>
                    <div class="related-card" onclick="window.location.href='product_details.php?id=<?php echo $related['id']; ?>'">
                        <div class="related-image">
                            <img src="<?php echo !empty($related['image_url']) ? '../' . htmlspecialchars($related['image_url']) : 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=400&q=80'; ?>" alt="<?php echo htmlspecialchars($related['name']); ?>">
                            <?php if ($related['original_price'] && $related['original_price'] > $related['price']): ?>
                                <span class="related-discount">-<?php echo round((($related['original_price'] - $related['price']) / $related['original_price']) * 100); ?>%</span>
                            <?php endif; ?>
                        </div>
                        <div class="related-info">
                            <div class="related-category"><?php echo htmlspecialchars(ucfirst($product['category'])); ?></div>
                            <div class="related-name"><?php echo htmlspecialchars($related['name']); ?></div>
                            <div class="related-price">
                                <span class="related-current">₱<?php echo number_format($related['price'], 2); ?></span>
                                <?php if ($related['original_price']): ?>
                                    <span class="related-original">₱<?php echo number_format($related['original_price'], 2); ?></span>
                                <?php endif; ?>
                            </div>
                            <button class="add-to-cart-btn" onclick="event.stopPropagation(); addToCart(<?php echo $related['id']; ?>, event)">
                                <i class="fas fa-shopping-cart"></i> Add to Cart
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer Features -->
        <div class="footer-features">
            <div class="footer-feature">
                <div class="footer-feature-icon"><i class="fas fa-truck"></i></div>
                <div class="footer-feature-text">
                    <h4>Free Shipping</h4>
                    <p>On orders over ₱500</p>
                </div>
            </div>
            <div class="footer-feature">
                <div class="footer-feature-icon"><i class="fas fa-credit-card"></i></div>
                <div class="footer-feature-text">
                    <h4>Flexible Payment</h4>
                    <p>Pay with multiple methods</p>
                </div>
            </div>
            <div class="footer-feature">
                <div class="footer-feature-icon"><i class="fas fa-headset"></i></div>
                <div class="footer-feature-text">
                    <h4>24x7 Support</h4>
                    <p>Dedicated support team</p>
                </div>
            </div>
        </div>

        <!-- Recommended Products Section -->
        <?php if ($recommended_result->num_rows > 0): ?>
        <div class="featured-categories">
            <div class="section-header">
                <h2 class="section-title">Recommended for You</h2>
                <p class="section-subtitle">More products in <?php echo htmlspecialchars(ucfirst($product['category'])); ?></p>
            </div>
            
            <div class="related-grid" style="max-width: 1200px; margin: 0 auto; padding: 0 20px;">
                <?php while ($recommended = $recommended_result->fetch_assoc()): ?>
                    <div class="related-card" onclick="window.location.href='product_details.php?id=<?php echo $recommended['id']; ?>'">
                        <div class="related-image">
                            <img src="<?php echo !empty($recommended['image_url']) ? '../' . htmlspecialchars($recommended['image_url']) : 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=400&q=80'; ?>" alt="<?php echo htmlspecialchars($recommended['name']); ?>">
                            <?php if ($recommended['original_price'] && $recommended['original_price'] > $recommended['price']): ?>
                                <span class="related-discount">-<?php echo round((($recommended['original_price'] - $recommended['price']) / $recommended['original_price']) * 100); ?>%</span>
                            <?php endif; ?>
                        </div>
                        <div class="related-info">
                            <div class="related-category"><?php echo htmlspecialchars(ucfirst($product['category'])); ?></div>
                            <div class="related-name"><?php echo htmlspecialchars($recommended['name']); ?></div>
                            <div class="related-price">
                                <span class="related-current">₱<?php echo number_format($recommended['price'], 2); ?></span>
                                <?php if ($recommended['original_price']): ?>
                                    <span class="related-original">₱<?php echo number_format($recommended['original_price'], 2); ?></span>
                                <?php endif; ?>
                            </div>
                            <button class="add-to-cart-btn" onclick="event.stopPropagation(); addToCart(<?php echo $recommended['id']; ?>, event)">
                                <i class="fas fa-shopping-cart"></i> Add to Cart
                            </button>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

     <!-- Footer -->
    <footer>

        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="footer-logo">
                        <i class="fas fa-shopping-bag"></i>
                        <span>ReBuy</span>
                    </div>
                    <p class="footer-text">ReBuy lets you buy quality second-hand items for less, saving money while supporting a more sustainable lifestyle.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-pinterest"></i></a>
                    </div>
                </div>

                <div class="footer-section">
                    <h3>Company</h3>
                    <ul>
                        <li><a href="about_us.php">About Us</a></li>
                        <li><a href="#">Contact Us</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Customer Services</h3>
                    <ul>
                        <li><a href="settings.php">My Account</a></li>
                        <li><a href="#">Track Your Order</a></li>
                        <li><a href="#">Returns</a></li>
                        <li><a href="#">FAQ</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Our Information</h3>
                    <ul>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms & Condition</a></li>
                        <li><a href="#">Return Policy</a></li>
                        <li><a href="#">Shipping Info</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <p class="footer-text"><i class="fas fa-phone"></i> +639813446215</p>
                    <p class="footer-text"><i class="fa-solid fa-envelope"></i> rebuy@gmail.com</p>
                    <p class="footer-text"><i class="fa-solid fa-location-dot"></i> T. Curato St. Cabadbaran City Agusan Del Norte, Philippines, 8600</p>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; Copyright @ 2026 <strong>ReBuy</strong>. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        function setRating(rating) {
            document.getElementById('rating').value = rating;
            const labels = document.querySelectorAll('.rating-input label');
            labels.forEach(label => {
                const labelRating = parseInt(label.getAttribute('data-rating'));
                if (labelRating <= rating) {
                    label.classList.add('active');
                } else {
                    label.classList.remove('active');
                }
            });
        }

        function toggleWishlist(productId, button) {
            const formData = new FormData();
            formData.append('product_id', productId);
            
            fetch('wishlist_toggle.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const icon = button.querySelector('i');
                    if (data.action === 'added') {
                        button.classList.add('in-wishlist');
                        icon.classList.remove('far');
                        icon.classList.add('fas');
                        button.title = 'Remove from Wishlist';
                    } else {
                        button.classList.remove('in-wishlist');
                        icon.classList.remove('fas');
                        icon.classList.add('far');
                        button.title = 'Add to Wishlist';
                    }
                }
            })
            .catch(error => {
                console.error('Error toggling wishlist:', error);
            });
        }

        function changeQuantity(change) {
            const input = document.getElementById('quantity');
            const newValue = parseInt(input.value) + change;
            const maxValue = parseInt(input.max);
            
            if (newValue >= 1 && newValue <= maxValue) {
                input.value = newValue;
            }
        }

        function addToCart(productId, event) {
            console.log('addToCart called with productId:', productId);
            console.log('Event object:', event);
            
            const quantity = document.getElementById('quantity').value;
            console.log('Quantity:', quantity);
            
            // Create form data for the request
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('quantity', quantity);
            
            console.log('Sending request to cart_add.php...');
            
            // Send AJAX request to cart_add.php
            fetch('cart_add.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                console.log('Response received:', response.status, response.statusText);
                
                if (response.status === 401) {
                    console.log('User not logged in, redirecting to login');
                    // User not logged in, redirect to login
                    window.location.href = 'login.php';
                    return;
                }
                if (!response.ok) {
                    console.log('Response not OK:', response.status);
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                
                if (data.success) {
                    console.log('Product added successfully!');
                    
                    // Show success feedback
                    let button;
                    if (event && event.target && event.target.classList.contains('add-to-cart-btn')) {
                        button = event.target;
                        const originalText = button.innerHTML;
                        button.innerHTML = '<i class="fas fa-check"></i> Added!';
                        button.style.background = '#28a745';
                        
                        setTimeout(() => {
                            button.innerHTML = originalText;
                            button.style.background = '';
                        }, 2000);
                    } else if (event && event.target) {
                        button = event.target.closest('.btn-add-cart');
                        if (button) {
                            const originalText = button.innerHTML;
                            button.innerHTML = '<i class="fas fa-check"></i> Added to Cart!';
                            button.style.background = '#28a745';
                            
                            setTimeout(() => {
                                button.innerHTML = originalText;
                                button.style.background = '';
                            }, 2000);
                        }
                    }
                    
                    // Update cart badge with the count from server
                    const badge = document.querySelector('.cart-badge');
                    if (badge && data.cart_count !== undefined) {
                        console.log('Updating cart badge to:', data.cart_count);
                        badge.textContent = data.cart_count;
                    }
                } else {
                    console.log('Server returned error:', data.error);
                    throw new Error(data.error || 'Failed to add to cart');
                }
            })
            .catch(error => {
                console.error('Error adding to cart:', error);
                alert('Error adding to cart: ' + error.message);
                
                // Show error feedback
                let button;
                if (event && event.target && event.target.classList.contains('add-to-cart-btn')) {
                    button = event.target;
                    const originalText = button.innerHTML;
                    button.innerHTML = '<i class="fas fa-exclamation"></i> Error';
                    button.style.background = '#dc3545';
                    
                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.style.background = '';
                    }, 2000);
                } else if (event && event.target) {
                    button = event.target.closest('.btn-add-cart');
                    if (button) {
                        const originalText = button.innerHTML;
                        button.innerHTML = '<i class="fas fa-exclamation"></i> Error';
                        button.style.background = '#dc3545';
                        
                        setTimeout(() => {
                            button.innerHTML = originalText;
                            button.style.background = '';
                        }, 2000);
                    }
                }
            });
        }
        
        
        function buyNow(productId) {
            const quantity = document.getElementById('quantity').value;
            
            // Here you would implement buy now functionality
            // For now, redirect to checkout
            window.location.href = 'checkout.php?product_id=' + productId + '&quantity=' + quantity;
        }

        // Thumbnail click functionality
        document.querySelectorAll('.thumbnail').forEach((thumb, index) => {
            thumb.addEventListener('click', function() {
                document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                // In a real implementation, you would change the main image here
                // For now, we'll just show the active state
            });
        });

        // Tab functionality
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Remove active class from all tabs
                document.querySelectorAll('.tab-btn').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                // Add active class to clicked tab
                this.classList.add('active');
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Weight button functionality
        document.querySelectorAll('.weight-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.weight-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Real-time stock monitoring
        let currentStock = <?php echo $product['stock_quantity'] ?? 0; ?>;
        let stockCheckInterval;
        
        function checkStockUpdate() {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'ajax_stock_update.php?action=get_stock&product_id=<?php echo $product_id; ?>', true);
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success && response.stock_quantity !== currentStock) {
                            const oldStock = currentStock;
                            currentStock = response.stock_quantity;
                            updateStockDisplay(oldStock, currentStock);
                        }
                    } catch (e) {
                        // Silent error handling
                    }
                }
            };
            
            xhr.send();
        }
        
        function updateStockDisplay(oldStock, newStock) {
            // Update stock badge
            const stockBadge = document.querySelector('.product-badge');
            if (stockBadge) {
                if (newStock <= 0) {
                    stockBadge.style.background = '#dc3545';
                    stockBadge.textContent = 'Out of Stock';
                } else {
                    stockBadge.style.background = '#e8f5e8';
                    stockBadge.textContent = 'In Stock';
                }
            }
            
            // Update stock meta information
            const stockMeta = document.querySelector('.meta-value');
            if (stockMeta && stockMeta.textContent.includes('units')) {
                stockMeta.textContent = newStock + ' units';
            }
            
            // Update quantity input max value
            const quantityInput = document.getElementById('quantity');
            if (quantityInput) {
                quantityInput.max = newStock > 0 ? newStock : 1;
                if (parseInt(quantityInput.value) > newStock) {
                    quantityInput.value = newStock > 0 ? newStock : 1;
                }
            }
            
            // Update add to cart buttons
            const addCartBtns = document.querySelectorAll('.btn-add-cart, .btn-buy-now');
            addCartBtns.forEach(btn => {
                if (newStock <= 0) {
                    btn.disabled = true;
                    btn.style.opacity = '0.5';
                    btn.style.cursor = 'not-allowed';
                    if (btn.classList.contains('btn-add-cart')) {
                        btn.innerHTML = '<i class="fas fa-shopping-cart"></i> Out of Stock';
                    } else {
                        btn.textContent = 'Out of Stock';
                    }
                } else {
                    btn.disabled = false;
                    btn.style.opacity = '1';
                    btn.style.cursor = 'pointer';
                    if (btn.classList.contains('btn-add-cart')) {
                        btn.innerHTML = '<i class="fas fa-shopping-cart"></i> Add to Cart';
                    } else {
                        btn.textContent = 'Buy Now';
                    }
                }
            });
            
            // Show stock update notification
            if (newStock > oldStock) {
                showStockNotification('Stock increased! ' + (newStock - oldStock) + ' units added.', 'success');
            } else if (newStock < oldStock) {
                showStockNotification('Stock decreased! ' + (oldStock - newStock) + ' units sold.', 'info');
            }
        }
        
        function showStockNotification(message, type) {
            // Remove existing stock notifications
            const existingNotif = document.querySelector('.stock-notification');
            if (existingNotif) {
                existingNotif.remove();
            }
            
            // Create notification element
            const notification = document.createElement('div');
            notification.className = 'stock-notification';
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                padding: 15px 20px;
                border-radius: 8px;
                min-width: 300px;
                animation: slideIn 0.3s ease;
                background: ${type === 'success' ? '#d4edda' : '#d1ecf1'};
                color: ${type === 'success' ? '#155724' : '#0c5460'};
                border-left: 4px solid ${type === 'success' ? '#28a745' : '#17a2b8'};
                font-size: 14px;
            `;
            notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'}"></i> ${message}`;
            
            // Add to page
            document.body.appendChild(notification);
            
            // Remove after 5 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    if (notification.parentNode) {
                        document.body.removeChild(notification);
                    }
                }, 300);
            }, 5000);
        }
        
        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
        
        // Start stock monitoring (check every 10 seconds)
        stockCheckInterval = setInterval(checkStockUpdate, 10000);
        
        // Stop monitoring when page is hidden to save resources
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                clearInterval(stockCheckInterval);
            } else {
                stockCheckInterval = setInterval(checkStockUpdate, 10000);
                checkStockUpdate(); // Check immediately when page becomes visible
            }
        });
    </script>
</body>
</html>
