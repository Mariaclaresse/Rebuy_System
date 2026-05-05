<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$wishlist_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("DELETE FROM wishlist WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $wishlist_id, $user_id);
$stmt->execute();
$stmt->close();

header("Location: wishlist.php");
exit();
?>
