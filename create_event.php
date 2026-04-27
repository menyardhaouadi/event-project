<?php 
include 'connect.php'; 
session_start();

$title = $_POST['title'];
$desc = $_POST['description'];
$date = $_POST['date'];
$category = $_POST['category'];
$allowed = ['hackathon', 'workshop', 'adventure'];

if (!in_array($category, $allowed)) {
    die("Invalid category");
};
$start = $_POST['start_time'];
$end = $_POST['end_time'];
$max = $_POST['max_participants'];
$admin_id = $_SESSION['user_id'];
$location = trim($_POST['location'] ?? 'Tunis');
$latitude = trim((string) ($_POST['latitude'] ?? ''));
$longitude = trim((string) ($_POST['longitude'] ?? ''));

if ($location === '') {
    $location = 'Tunis';
}

$stmt = $conn->prepare("
    INSERT INTO events
    (title, description, date, category, start_time, end_time, max_participants, admin_id, location, latitude, longitude)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''))
");

if (!$stmt) {
    die("Could not create event");
}

$stmt->bind_param('ssssssiisss', $title, $desc, $date, $category, $start, $end, $max, $admin_id, $location, $latitude, $longitude);
$stmt->execute();
$stmt->close();

header("Location: admin.php");
