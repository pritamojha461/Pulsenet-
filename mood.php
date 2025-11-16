<?php
// DATABASE CONNECTION
$conn = new mysqli("localhost", "root", "", "pulsenet_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// GET DATA
$mood = $_POST['mood'] ?? '';
$thoughts = $_POST['thoughts'] ?? '';

if ($mood == "" || $thoughts == "") {
    echo "Please provide mood and thoughts.";
    exit;
}

// INSERT INTO DATABASE
$sql = "INSERT INTO mood_entries (mood, thoughts, created_at) VALUES (?, ?, NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $mood, $thoughts);

if ($stmt->execute()) {
    echo "Your mood has been recorded successfully 😊";
} else {
    echo "Error saving your mood :(";
}

$stmt->close();
$conn->close();
?>
