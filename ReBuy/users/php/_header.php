<?php
require_once 'db.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;

// Check if user is a seller
$is_seller = false;
if ($is_logged_in) {
    $seller_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_seller'");
    if ($seller_check->num_rows > 0) {
        $stmt = $conn->prepare("SELECT is_seller FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        $is_seller = isset($user['is_seller']) && $user['is_seller'] == 1;
    }
}
?>

<!-- Top Bar -->
<div class="top-bar">
    <div class="top-bar-left">
        <span><i class="fas fa-phone"></i> +639813446215</span>
        <span>|</span>
        <span>Sign up and <strong>GET 25% OFF</strong> for your first order</span>
    </div>
    <div class="top-bar-right">
        <a href="#"><i class="fab fa-twitter"></i></a>
        <a href="#"><i class="fab fa-instagram"></i></a>
        <a href="#"><i class="fab fa-pinterest"></i></a>
    </div>
</div>

<!-- Main Header -->
<header class="main-header">
    <div class="header-container">
        <a href="dashboard.php" class="logo">
            <i class="fas fa-shopping-bag"></i>
            <span>ReBuy</span>
        </a>
        
        <?php if ($is_seller): ?>
        <!-- Seller Navigation -->
        <nav class="nav-menu">
            <a href="seller_profile.php">My Shop</a>
            <a href="seller_dashboard.php">Dashboard</a>
            <a href="message.php">Messages</a>
        </nav>
        <?php else: ?>
        <!-- Regular User Navigation -->
        <nav class="nav-menu">
            <a href="dashboard.php">Home</a>
            <a href="shop.php">Shop</a>
            <a href="orders.php">Orders</a>
            <a href="wishlist.php">Wishlist</a>
            <a href="message.php">Messages</a>
        </nav>
        <?php endif; ?>
        
        <div class="header-icons">
            <?php if (!$is_seller): ?>
            <a href="cart.php" class="icon-btn">
                <i class="fas fa-shopping-bag"></i>
            </a>
            <?php endif; ?>
            <a href="<?php echo $is_seller ? 'seller_notification.php' : 'notification.php'; ?>" class="icon-btn">
                <i class="fas fa-bell"></i>
            </a>
            <div class="user-menu">
                <button class="icon-btn"><i class="fas fa-user"></i></button>
                <div class="user-dropdown">
                    <?php if ($is_seller): ?>
                        <a href="manage_products.php"><i class="fas fa-box"></i> Manage Products</a>
                        <a href="seller_orders.php"><i class="fas fa-shopping-cart"></i> Seller Orders</a>
                        <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <?php else: ?>
                        <a href="settings.php"><i class="fas fa-cog"></i> My Account</a>
                        <a href="about_us.php"><i class="fas fa-info-circle"></i> About Us</a>
                    <?php endif; ?>
                    <hr>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>
</header>
