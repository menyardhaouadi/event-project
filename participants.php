<?php
include 'connect.php';
session_start();

header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

$event_id = (int) ($_GET['id'] ?? 0);
$current_user_id = (int) $_SESSION['user_id'];

$res = $conn->query("
SELECT users.id, users.username
FROM event_participants
JOIN users ON users.id = event_participants.user_id
WHERE event_participants.event_id = $event_id
");

$data = [];

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $data[] = [
            "id" => (int)$row['id'],
            "username" => $row['username'],
            "is_self" => ((int)$row['id'] === $current_user_id)
        ];
    }
}

echo json_encode($data);
exit();