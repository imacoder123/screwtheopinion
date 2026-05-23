<?php
$conn = new mysqli(
    "sql308.infinityfree.com",   // Host
    "if0_41839085",              // Username
    "vSwuD6ks0pi",// Password
    "if0_41839085_dbdbdv"        // Database name
);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
