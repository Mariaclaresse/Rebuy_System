<?php
session_start();
include 'db.php';
include 'notification_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_count':
        $count = getUnreadNotificationCount($user_id);
        echo json_encode(['count' => $count]);
        break;
        
    case 'mark_read':
        if (isset($_POST['notification_id'])) {
            $notification_id = (int)$_POST['notification_id'];
            $success = markNotificationAsRead($notification_id, $user_id);
            echo json_encode(['success' => $success]);
        } else {
            echo json_encode(['error' => 'Notification ID required']);
        }
        break;
        
    case 'mark_all_read':
        $success = markAllNotificationsAsRead($user_id);
        echo json_encode(['success' => $success]);
        break;
        
    case 'get_notifications':
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
        $notifications = getUserNotifications($user_id, $limit);
        echo json_encode(['notifications' => $notifications]);
        break;
        
    case 'create':
        if (isset($_POST['title']) && isset($_POST['message'])) {
            $title = $_POST['title'];
            $message = $_POST['message'];
            $type = $_POST['type'] ?? 'system';
            $target_user_id = isset($_POST['target_user_id']) ? (int)$_POST['target_user_id'] : $user_id;
            
            $success = createNotification($target_user_id, $title, $message, $type);
            echo json_encode(['success' => $success]);
        } else {
            echo json_encode(['error' => 'Title and message required']);
        }
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>
