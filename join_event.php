<?php
include 'connect.php';
include 'notification_bootstrap.php';
session_start();

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$event_id = (int) ($_GET['id'] ?? 0);

if (!$user_id || !$event_id) {
    header("Location: dashboard.php");
    exit();
}

ensure_notification_schema($conn);

// ❗ 1. check if already joined
$check = $conn->query("
SELECT * FROM event_participants 
WHERE user_id=$user_id AND event_id=$event_id
");

if ($check && $check->num_rows > 0) {
    header("Location: dashboard.php");
    exit();
}

// ❗ 2. check if event is full
$count = $conn->query("
SELECT COUNT(*) as c FROM event_participants 
WHERE event_id=$event_id
")->fetch_assoc()['c'];

$max_result = $conn->query("
SELECT max_participants FROM events 
WHERE id=$event_id
");

$event = $max_result ? $max_result->fetch_assoc() : null;
$max = $event['max_participants'] ?? 0;

if ($count >= $max) {
    header("Location: dashboard.php");
    exit();
}

// ❗ 3. generate token ONCE
$token = md5(uniqid());

// ❗ 4. insert ONLY ONCE
$conn->query("
INSERT INTO event_participants (user_id, event_id, token)
VALUES ($user_id, $event_id, '$token')
");

$admin_result = $conn->query("SELECT admin_id FROM events WHERE id=$event_id LIMIT 1");
$event_owner = $admin_result ? $admin_result->fetch_assoc() : null;
$admin_id = (int) ($event_owner['admin_id'] ?? 0);

if ($admin_id > 0 && $admin_id !== $user_id) {
    $stmt = $conn->prepare("
        INSERT INTO event_notifications (admin_id, event_id, user_id, type)
        VALUES (?, ?, ?, 'event_join')
    ");

    if ($stmt) {
        $stmt->bind_param('iii', $admin_id, $event_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

header("Location: dashboard.php");
exit();
?>
