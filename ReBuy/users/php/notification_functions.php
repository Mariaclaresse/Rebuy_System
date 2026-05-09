<?php
// Notification functions

// Create notifications table if it doesn't exist
function ensureNotificationsTable() {
    global $conn;
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('promo', 'message', 'order', 'system', 'wishlist') DEFAULT 'system',
        is_read BOOLEAN DEFAULT FALSE,
        sender_id INT DEFAULT NULL,
        redirect_url VARCHAR(500) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    return $conn->query($sql);
}

function createNotification($user_id, $title, $message, $type = 'system', $sender_id = null, $redirect_url = null) {
    global $conn;
    
    // Ensure table exists
    ensureNotificationsTable();
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, sender_id, redirect_url) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
        return false;
    }
    $stmt->bind_param("isssis", $user_id, $title, $message, $type, $sender_id, $redirect_url);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function getUserNotifications($user_id, $limit = 20, $unread_only = false) {
    global $conn;
    
    // Ensure table exists
    ensureNotificationsTable();
    
    $sql = "SELECT * FROM notifications WHERE user_id = ?";
    if ($unread_only) {
        $sql .= " AND is_read = FALSE";
    }
    $sql .= " ORDER BY created_at DESC LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return [];
    }
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    return $notifications;
}

function getUnreadNotificationCount($user_id) {
    global $conn;
    
    // Ensure table exists
    ensureNotificationsTable();
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    if ($stmt === false) {
        return 0;
    }
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    return $count;
}

function markNotificationAsRead($notification_id, $user_id) {
    global $conn;
    
    // Ensure table exists
    ensureNotificationsTable();
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
    if ($stmt === false) {
        return false;
    }
    $stmt->bind_param("ii", $notification_id, $user_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function markAllNotificationsAsRead($user_id) {
    global $conn;
    
    // Ensure table exists
    ensureNotificationsTable();
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
    if ($stmt === false) {
        return false;
    }
    $stmt->bind_param("i", $user_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function deleteNotification($notification_id, $user_id) {
    global $conn;
    
    // Ensure table exists
    ensureNotificationsTable();
    
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    if ($stmt === false) {
        return false;
    }
    $stmt->bind_param("ii", $notification_id, $user_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

function getNotificationIcon($type) {
    $icons = [
        'promo' => 'fas fa-tag',
        'message' => 'fas fa-envelope',
        'order' => 'fas fa-shopping-cart',
        'system' => 'fas fa-info-circle',
        'wishlist' => 'fas fa-heart'
    ];
    return $icons[$type] ?? 'fas fa-bell';
}

function getNotificationColor($type) {
    $colors = [
        'promo' => '#ff6b6b',
        'message' => '#4ecdc4',
        'order' => '#45b7d1',
        'system' => '#96ceb4',
        'wishlist' => '#ff6b9d'
    ];
    return $colors[$type] ?? '#96ceb4';
}

function formatNotificationTime($created_at) {
    $time = strtotime($created_at);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return floor($diff / 86400) . ' days ago';
    } else {
        return date('M j, Y', $time);
    }
}

// Auto-create notifications for various events
function notifyOrderStatusChange($user_id, $order_id, $status) {
    $title = '📦 Order Update';
    $message = "Your order #{$order_id} status has been updated to: {$status}";
    createNotification($user_id, $title, $message, 'order');
}

function notifyNewMessage($user_id, $sender_id, $sender_name, $message_preview) {
    $title = '💬 New Message';
    $message = "You have a new message from {$sender_name}: " . substr($message_preview, 0, 50) . "...";
    $redirect_url = "message.php?sender_id={$sender_id}";
    createNotification($user_id, $title, $message, 'message', $sender_id, $redirect_url);
}

function notifyMessageSent($sender_id, $receiver_name, $message_preview) {
    $title = '✅ Message Sent';
    $message = "Your message to {$receiver_name} has been sent: " . substr($message_preview, 0, 50) . "...";
    $redirect_url = "message.php?sender_id={$receiver_name}";
    createNotification($sender_id, $title, $message, 'message', null, $redirect_url);
}

function notifyPromo($user_id, $promo_title, $promo_description) {
    $title = '🎉 ' . $promo_title;
    $message = $promo_description;
    createNotification($user_id, $title, $message, 'promo');
}

function notifyWishlistItemAvailable($user_id, $product_name) {
    $title = '❤️ Wishlist Item Available';
    $message = "{$product_name} from your wishlist is now back in stock!";
    createNotification($user_id, $title, $message, 'wishlist');
}
?>
