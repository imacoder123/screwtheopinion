<?php
$conn = new mysqli("localhost", "root", "", "screwtheopinion");

// Refresh the page every 1 second
header("Refresh: 1");

$result = $conn->query("SELECT id, username, email FROM users ORDER BY id DESC");

// Full black background + full green text
echo "<body style='margin:0;background:black;'>";
echo "<pre style='color:#00ff00;padding:20px;font-size:18px;'>";

echo "userdb mysql>\n\n";

while ($row = $result->fetch_assoc()) {
    echo "[ID: " . $row['id'] . "] " . $row['username'] . " (" . $row['email'] . ") \n";
}

echo "</pre>";
echo "</body>";
?>