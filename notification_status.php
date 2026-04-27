<?php
include 'connect.php';
include 'notification_bootstrap.php';
session_start();

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$user_id = (int) ($_SESSION['user_id'] ?? 0);
$role = $_SESSION['role'] ?? '';

if (!$user_id) {
    echo json_encode(['ok' => false, 'message' => 'Not logged in']);
    exit();
}

ensure_notification_schema($conn);

$mark_joins_read = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_joins_read';

$join_notifications = [];
$dm_unread_by_user = [];

$dm_stmt = $conn->prepare("
    SELECT sender_id, COUNT(*) AS unread_count
    FROM messages
    WHERE receiver_id = ?
      AND read_at IS NULL
    GROUP BY sender_id
");

if ($dm_stmt) {
    $dm_stmt->bind_param('i', $user_id);
    $dm_stmt->execute();
    $dm_result = $dm_stmt->get_result();

    while ($row = $dm_result->fetch_assoc()) {
        $dm_unread_by_user[(int) $row['sender_id']] = (int) $row['unread_count'];
    }

    $dm_stmt->close();
}

if ($role === 'admin') {
    $stmt = $conn->prepare("
        SELECT
            event_notifications.id,
            event_notifications.created_at,
            event_notifications.is_read,
            users.username,
            users.email,
            events.title,
            events.date,
            events.location
        FROM event_notifications
        JOIN users ON users.id = event_notifications.user_id
        JOIN events ON events.id = event_notifications.event_id
        WHERE event_notifications.admin_id = ?
        ORDER BY event_notifications.created_at DESC, event_notifications.id DESC
        LIMIT 8
    ");

    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $join_notifications[] = $row;
        }

        $stmt->close();
    }

    if ($mark_joins_read) {
        $stmt = $conn->prepare('UPDATE event_notifications SET is_read = 1 WHERE admin_id = ?');

        if ($stmt) {
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->close();
        }
    }
}

echo json_encode([
    'ok' => true,
    'dm_unread' => unread_dm_count($conn, $user_id),
    'dm_unread_by_user' => $dm_unread_by_user,
    'join_unread' => ($role === 'admin' && !$mark_joins_read) ? unread_join_count($conn, $user_id) : 0,
    'join_notifications' => $join_notifications
]);
?>
