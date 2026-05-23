<?php
session_start();
require '../db.php';
$current_id = $_SESSION['user_id'];
$result = $conn->query("SELECT id, username FROM users WHERE id != $current_id");
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
echo json_encode($users);
?>