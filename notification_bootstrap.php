<?php

function ensure_notification_schema($conn) {
    static $ready = false;

    if ($ready) {
        return;
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS event_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            event_id INT NOT NULL,
            user_id INT NOT NULL,
            type VARCHAR(40) NOT NULL DEFAULT 'event_join',
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin_read (admin_id, is_read),
            INDEX idx_event (event_id),
            INDEX idx_user (user_id)
        )
    ");

    $column_check = $conn->query("SHOW COLUMNS FROM messages LIKE 'read_at'");

    if ($column_check && $column_check->num_rows === 0) {
        $conn->query("ALTER TABLE messages ADD read_at DATETIME NULL DEFAULT NULL");
    }

    $ready = true;
}

function unread_dm_count($conn, $user_id) {
    ensure_notification_schema($conn);

    $user_id = (int) $user_id;
    $result = $conn->query("
        SELECT COUNT(*) AS c
        FROM messages
        WHERE receiver_id = $user_id
          AND read_at IS NULL
    ");

    return $result ? (int) $result->fetch_assoc()['c'] : 0;
}

function unread_join_count($conn, $admin_id) {
    ensure_notification_schema($conn);

    $admin_id = (int) $admin_id;
    $result = $conn->query("
        SELECT COUNT(*) AS c
        FROM event_notifications
        WHERE admin_id = $admin_id
          AND is_read = 0
    ");

    return $result ? (int) $result->fetch_assoc()['c'] : 0;
}

?>
