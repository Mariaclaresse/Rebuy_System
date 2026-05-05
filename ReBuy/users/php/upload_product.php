<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user is a seller
require_once 'db.php';
$user_id = $_SESSION['user_id'];

$seller_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_seller'");
if ($seller_check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT is_seller FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user['is_seller'] != 1) {
        header("Location: dashboard.php");
        exit();
    }
} else {
    header("Location: dashboard.php");
    exit();
}

// Handle product upload
$success_msg = '';
$error_msg = '';

if (isset($_POST['action']) && $_POST['action'] == 'upload_product') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? '';
    $original_price = $_POST['original_price'] ?? '';
    $category = $_POST['category'] ?? '';
    $stock_quantity = $_POST['stock_quantity'] ?? 0;
    
    // Handle image upload
    $image_url = '';
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $upload_dir = '../uploads/products/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['product_image']['name']);
        $target_file = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $target_file)) {
            $image_url = 'uploads/products/' . $file_name;
        } else {
            $error_msg = "Failed to upload image.";
        }
    }
    
    if (empty($error_msg)) {
        $stmt = $conn->prepare("INSERT INTO products (seller_id, name, description, price, original_price, category, image_url, stock_quantity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            $error_msg = "Prepare failed: " . $conn->error;
        } else {
            $stmt->bind_param("isssdssi", $user_id, $name, $description, $price, $original_price, $category, $image_url, $stock_quantity);
            if ($stmt->execute()) {
                $success_msg = "Product uploaded successfully!";
            } else {
                $error_msg = "Failed to upload product: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Product - ReBuy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../css/header-footer.css">
    <style>
        :root {
            --primary-color: #2d5016;
            --secondary-color: #f4c430;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --light-gray: #f5f5f5;
            --dark-gray: #333;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-gray);
            margin: 0;
            padding: 0;
        }

        .upload-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .upload-header {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .upload-header h1 {
            color: var(--primary-color);
            margin: 0;
            font-size: 28px;
        }

        .upload-header p {
            color: #666;
            margin: 5px 0 0 0;
        }

        .upload-form {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-row.full {
            grid-template-columns: 1fr;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 14px;
            font-weight: 600;
            color: var(--dark-gray);
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(45, 80, 22, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .image-upload {
            border: 2px dashed #ddd;
            border-radius: 6px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.3s;
        }

        .image-upload:hover {
            border-color: var(--primary-color);
        }

        .image-upload i {
            font-size: 48px;
            color: #999;
            margin-bottom: 15px;
        }

        .image-preview {
            margin-top: 15px;
            max-width: 200px;
            max-height: 200px;
            border-radius: 6px;
            display: none;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #1a3009;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(45, 80, 22, 0.2);
        }

        .btn-secondary {
            background: #f5f5f5;
            color: var(--dark-gray);
            border: 1px solid #ddd;
        }

        .btn-secondary:hover {
            background: #e8e8e8;
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .required {
            color: var(--danger-color);
        }

        @media (max-width: 768px) {
            .upload-container {
                padding: 10px;
            }
            
            .upload-form {
                padding: 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="top-bar-left">
            <span><i class="fas fa-phone"></i> +639813446215</span>
            <span>|</span>
            <span>Sign up and <strong>GET 25% OFF</strong> for your first order</span>
        </div>
        <div class="top-bar-right">
            <a href="#"><i class="fab fa-facebook"></i></a>
            <a href="#"><i class="fab fa-twitter"></i></a>
            <a href="#"><i class="fab fa-instagram"></i></a>
            <a href="#"><i class="fab fa-youtube"></i></a>
        </div>
    </div>

    <!-- Main Header -->
    <header class="main-header">
        <div class="header-container">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-shopping-bag"></i>
                <span>ReBuy</span>
            </a>
            <nav class="nav-menu">
                <a href="dashboard.php">Home</a>
                <a href="shop.php">Shop</a>
                <a href="seller_dashboard.php">Seller Dashboard</a>
                <a href="orders.php">Orders</a>
                <a href="wishlist.php">Wishlist</a>
            </nav>
            <div class="header-icons">
                <a href="shop.php"><i class="fas fa-search"></i></a>
                <a href="cart.php" class="icon-btn">
                    <i class="fas fa-shopping-bag"></i>
                    <span class="cart-badge">0</span>
                </a>
                <div class="user-menu">
                    <button class="icon-btn"><i class="fas fa-user"></i></button>
                    <div class="user-dropdown">
                        <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
                        <a href="seller_dashboard.php"><i class="fas fa-store"></i> Seller Dashboard</a>
                        <a href="settings.php"><i class="fas fa-cog"></i> My Account</a>
                        <a href="about_us.php"><i class="fas fa-info-circle"></i> About Us</a>
                        <a href="wishlist.php"><i class="fas fa-heart"></i> Wishlist</a>
                        <hr>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="upload-container">
        <!-- Upload Header -->
        <div class="upload-header">
            <h1><i class="fas fa-plus"></i> Upload New Product</h1>
            <p>Add your product to the marketplace and start selling</p>
        </div>

        <!-- Upload Form -->
        <div class="upload-form">
            <?php if ($success_msg): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_product">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Product Name <span class="required">*</span></label>
                        <input type="text" name="name" required placeholder="Enter product name">
                    </div>
                    <div class="form-group">
                        <label>Category <span class="required">*</span></label>
                        <select name="category" required>
                            <option value="">Select category</option>
                            <option value="Electronics">Electronics</option>
                            <option value="Clothing">Clothing</option>
                            <option value="Books">Books</option>
                            <option value="Home & Garden">Home & Garden</option>
                            <option value="Sports">Sports</option>
                            <option value="Toys">Toys</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Selling Price <span class="required">*</span></label>
                        <input type="number" name="price" step="0.01" min="0" required placeholder="₱0.00">
                    </div>
                    <div class="form-group">
                        <label>Original Price</label>
                        <input type="number" name="original_price" step="0.01" min="0" placeholder="₱0.00">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Stock Quantity <span class="required">*</span></label>
                        <input type="number" name="stock_quantity" min="0" required placeholder="0">
                    </div>
                    <div class="form-group">
                        <label>Product Image</label>
                        <div class="image-upload" onclick="document.getElementById('product_image').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to upload image</p>
                            <input type="file" id="product_image" name="product_image" accept="image/*" style="display: none;" onchange="previewImage(event)">
                        </div>
                        <img id="image_preview" class="image-preview" alt="Preview">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" placeholder="Describe your product (features, condition, etc.)"></textarea>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Upload Product
                    </button>
                    <a href="seller_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </form>
        </div>
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
        // User dropdown menu
        document.querySelector('.icon-btn').addEventListener('click', function() {
            document.querySelector('.user-dropdown').classList.toggle('active');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const userMenu = document.querySelector('.user-menu');
            if (!userMenu.contains(event.target)) {
                document.querySelector('.user-dropdown').classList.remove('active');
            }
        });

        // Image preview
        function previewImage(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('image_preview');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        }
    </script>
</body>
</html>
