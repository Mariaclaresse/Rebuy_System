<?php
require_once 'db.php';
require_once 'notification_functions.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;

// Get notification count if user is logged in
$notification_count = 0;
$recent_notifications = [];
if ($is_logged_in) {
    $notification_count = getUnreadNotificationCount($user_id);
    $recent_notifications = getUserNotifications($user_id, 5);
}

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

// Get current page for navbar highlighting
$current_page = basename($_SERVER['PHP_SELF']);
function is_active($page) {
    global $current_page;
    return $current_page === $page ? 'active' : '';
}
?>

<style>
<?php include '../css/notification-dropdown.css'; ?>
</style>

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
            <a href="seller_profile.php" class="<?php echo is_active('seller_profile.php'); ?>">My Shop</a>
            <a href="seller_dashboard.php" class="<?php echo is_active('seller_dashboard.php'); ?>">Dashboard</a>
            <a href="message.php" class="<?php echo is_active('message.php'); ?>">Messages</a>
        </nav>
        <?php else: ?>
        <!-- Regular User Navigation -->
        <nav class="nav-menu">
            <a href="dashboard.php" class="<?php echo is_active('dashboard.php'); ?>">Home</a>
            <a href="shop.php" class="<?php echo is_active('shop.php'); ?>">Shop</a>
            <a href="orders.php" class="<?php echo is_active('orders.php'); ?>">Orders</a>
            <a href="wishlist.php" class="<?php echo is_active('wishlist.php'); ?>">Wishlist</a>
            <a href="message.php" class="<?php echo is_active('message.php'); ?>">Messages</a>
        </nav>
        <?php endif; ?>
        
        <div class="header-icons">
            <?php if (!$is_seller): ?>
            <a href="cart.php" class="icon-btn">
                <i class="fas fa-shopping-bag"></i>
            </a>
            <?php endif; ?>
            <div class="notification-dropdown">
                <a href="<?php echo $is_seller ? 'seller_notification.php' : 'notification.php'; ?>" class="icon-btn notification-bell">
                    <i class="fas fa-bell"></i>
                    <?php if ($notification_count > 0): ?>
                        <span class="notification-count"><?php echo $notification_count; ?></span>
                    <?php endif; ?>
                </a>
                <div class="notification-menu" id="notification-menu">
                    <div class="notification-header">
                        <h4>Notifications</h4>
                        <?php if ($notification_count > 0): ?>
                            <a href="<?php echo $is_seller ? 'seller_notification.php' : 'notification.php'; ?>" class="view-all">View All</a>
                        <?php endif; ?>
                    </div>
                    <div class="notification-list-dropdown">
                        <?php if (!empty($recent_notifications)): ?>
                            <?php foreach ($recent_notifications as $notif): ?>
                                <?php 
                                $is_clickable = ($notif['type'] == 'message' && !empty($notif['redirect_url']));
                                $click_target = $is_clickable ? $notif['redirect_url'] : 'notification.php';
                                ?>
                                <a href="<?php echo htmlspecialchars($click_target); ?>" class="notification-item-dropdown <?php echo !$notif['is_read'] ? 'unread' : ''; ?> <?php echo $is_clickable ? 'clickable' : ''; ?>">
                                    <div class="notif-icon">
                                        <i class="<?php echo getNotificationIcon($notif['type']); ?>" style="color: <?php echo getNotificationColor($notif['type']); ?>;"></i>
                                    </div>
                                    <div class="notif-content">
                                        <h5>
                                            <?php echo htmlspecialchars($notif['title']); ?>
                                            <?php if ($is_clickable): ?>
                                                <small style="color: #007bff;">↩ Reply</small>
                                            <?php endif; ?>
                                        </h5>
                                        <p><?php echo htmlspecialchars(substr($notif['message'], 0, 80)) . '...'; ?></p>
                                        <span class="notif-time"><?php echo formatNotificationTime($notif['created_at']); ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-notifications">
                                <i class="fas fa-bell-slash"></i>
                                <p>No notifications</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
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
