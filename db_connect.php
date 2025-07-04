<?php
// db_connect.php

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "attendance_db";

// Connect to MySQL
$conn = new mysqli($host, $user, $pass, $dbname); // Pass $dbname directly for immediate selection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>