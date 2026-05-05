<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

require_once 'db.php';

$user_id = $_SESSION['user_id'];
$order_id = $_POST['order_id'] ?? '';
$new_status = $_POST['status'] ?? '';

// Validate inputs
if (empty($order_id) || empty($new_status)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

// Validate status
$valid_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
if (!in_array($new_status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

// Check if user is a seller
$seller_check = $conn->query("SHOW COLUMNS FROM users LIKE 'is_seller'");
if ($seller_check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT is_seller FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if ($user['is_seller'] != 1) {
        echo json_encode(['success' => false, 'message' => 'Not a seller']);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Not a seller']);
    exit();
}

// Check if order belongs to this seller
$check_stmt = $conn->prepare("SELECT so.id, so.status, so.product_id, so.customer_id, so.quantity, so.order_id as seller_order_ref FROM seller_orders so WHERE so.id = ? AND so.seller_id = ?");
$check_stmt->bind_param("ii", $order_id, $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found or does not belong to you']);
    $check_stmt->close();
    exit();
}

$order = $check_result->fetch_assoc();
$check_stmt->close();

// Get the orders.id from seller_orders.customer_id
$order_numeric_id = $order['customer_id'];

// Validate status transition
$current_status = $order['status'];
$valid_transitions = [
    'pending' => ['processing', 'cancelled'],
    'processing' => ['shipped', 'cancelled'],
    'shipped' => ['delivered'],
    'delivered' => [],
    'cancelled' => []
];

if (!in_array($new_status, $valid_transitions[$current_status])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status transition']);
    exit();
}

// Add timestamp columns if they don't exist
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS accepted_at TIMESTAMP NULL AFTER status");
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS processing_at TIMESTAMP NULL AFTER accepted_at");
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipped_at TIMESTAMP NULL AFTER processing_at");
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS delivered_at TIMESTAMP NULL AFTER shipped_at");
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS cancelled_at TIMESTAMP NULL AFTER delivered_at");

// Update order status
$update_stmt = $conn->prepare("UPDATE seller_orders SET status = ? WHERE id = ? AND seller_id = ?");
$update_stmt->bind_param("sii", $new_status, $order_id, $user_id);

if ($update_stmt->execute()) {
    // Update main orders table timestamp based on status
    $timestamp_column = '';
    switch($new_status) {
        case 'processing':
            $timestamp_column = 'processing_at';
            break;
        case 'shipped':
            $timestamp_column = 'shipped_at';
            break;
        case 'delivered':
            $timestamp_column = 'delivered_at';
            break;
        case 'cancelled':
            $timestamp_column = 'cancelled_at';
            break;
    }
    
    if ($timestamp_column) {
        $orders_update = $conn->prepare("UPDATE orders SET $timestamp_column = NOW() WHERE id = ?");
        $orders_update->bind_param("i", $order_numeric_id);
        $orders_update->execute();
        $orders_update->close();
    }
    
    // Set accepted_at if this is the first status change from pending
    if ($current_status == 'pending' && $new_status != 'cancelled') {
        $accepted_update = $conn->prepare("UPDATE orders SET accepted_at = NOW() WHERE id = ?");
        $accepted_update->bind_param("i", $order_numeric_id);
        $accepted_update->execute();
        $accepted_update->close();
    }
    
    // Update the main orders.status column to reflect overall seller_orders status
    // Get all seller orders for this customer order
    $seller_orders_stmt = $conn->prepare("SELECT status FROM seller_orders WHERE customer_id = ?");
    $seller_orders_stmt->bind_param("i", $order_numeric_id);
    $seller_orders_stmt->execute();
    $seller_result = $seller_orders_stmt->get_result();
    
    $has_cancelled = false;
    $has_delivered = false;
    $has_shipped = false;
    $has_processing = false;
    $all_delivered = true;
    
    while ($so = $seller_result->fetch_assoc()) {
        if ($so['status'] == 'cancelled') {
            $has_cancelled = true;
        }
        if ($so['status'] == 'delivered') {
            $has_delivered = true;
        }
        if ($so['status'] == 'shipped') {
            $has_shipped = true;
        }
        if ($so['status'] == 'processing') {
            $has_processing = true;
        }
        if ($so['status'] != 'delivered') {
            $all_delivered = false;
        }
    }
    $seller_orders_stmt->close();
    
    // Determine overall status
    $overall_status = 'pending';
    if ($has_cancelled) {
        $overall_status = 'cancelled';
    } elseif ($all_delivered) {
        $overall_status = 'delivered';
    } elseif ($has_shipped) {
        $overall_status = 'shipped';
    } elseif ($has_processing) {
        $overall_status = 'processing';
    }
    
    // Update orders.status
    $status_update = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $status_update->bind_param("si", $overall_status, $order_numeric_id);
    $status_update->execute();
    $status_update->close();
    
    // If order is cancelled, restore stock
    if ($new_status == 'cancelled') {
        $restore_stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
        $restore_stmt->bind_param("ii", $order['quantity'], $order['product_id']);
        $restore_stmt->execute();
        $restore_stmt->close();
    }
    
    echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$update_stmt->close();
$conn->close();
