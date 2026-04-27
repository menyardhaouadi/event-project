<?php
include 'connect.php';
session_start();

$token = $_GET['token'] ?? '';

if (!$token) {
    die("Invalid access (no token)");
}

$stmt = $conn->prepare("
SELECT users.username, users.email, events.title, events.date, events.start_time, events.end_time, events.location
FROM event_participants
JOIN users ON users.id = event_participants.user_id
JOIN events ON events.id = event_participants.event_id
WHERE event_participants.token = ?
");

if (!$stmt) {
    die("SQL ERROR: " . $conn->error);
}

$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    die("Invalid or expired token");
}

$data = $res->fetch_assoc();
$stmt->close();

$username = $data['username'];
$email = $data['email'];
$event_name = $data['title'];
$event_date = $data['date'];
$start_time = $data['start_time'] ?? '';
$end_time = $data['end_time'] ?? '';
$location = trim((string) ($data['location'] ?? ''));
$event_time = 'Time to be announced';

if ($start_time && $end_time) {
    $event_time = $start_time . ' - ' . $end_time;
} elseif ($start_time) {
    $event_time = $start_time;
}

if ($location === '') {
    $location = 'Location to be announced';
}

$stmt2 = $conn->prepare("UPDATE event_participants SET checked_in=1 WHERE token=?");
if ($stmt2) {
    $stmt2->bind_param("s", $token);
    $stmt2->execute();
    $stmt2->close();
}

$calendarStart = strtotime(trim($event_date . ' ' . ($start_time ?: '09:00:00')));
$calendarEnd = strtotime(trim($event_date . ' ' . ($end_time ?: '10:00:00')));

if ($calendarEnd <= $calendarStart) {
    $calendarEnd = strtotime('+1 hour', $calendarStart);
}

$calendarStartFormatted = date('Ymd\THis', $calendarStart);
$calendarEndFormatted = date('Ymd\THis', $calendarEnd);
$eventDateFormatted = date('F j, Y', strtotime($event_date));
?>

<!DOCTYPE html>
<html>
<head>
    <title>Event Check-in</title>
    <link rel="stylesheet" href="style.css?v=unreal1">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="page-auth">

<main class="site-shell auth-shell">
    <div class="auth-layout">
        <section class="panel auth-card">
            <span class="eyebrow">Check-in complete</span>
            <h1 class="auth-title">You are checked in for <?php echo htmlspecialchars($event_name, ENT_QUOTES, 'UTF-8'); ?>.</h1>
            <p class="auth-copy">Use either action below to send your welcome email or place the event directly on your Google Calendar.</p>

            <div class="section-stack">
                <div class="stat-card">
                    <strong><?php echo htmlspecialchars($eventDateFormatted, ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span><?php echo htmlspecialchars($event_time, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>

                <div class="stat-card">
                    <strong><?php echo htmlspecialchars($location, ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span>Your QR scan has been recorded successfully.</span>
                </div>
            </div>

            <div class="form-actions" style="margin-top: 24px;">
                <button onclick="sendEmail()" class="btn btn-primary" type="button">
                    Send Welcome Email
                </button>

                <a
                    class="btn btn-secondary"
                    href="https://www.google.com/calendar/render?action=TEMPLATE&text=<?php echo urlencode($event_name); ?>&dates=<?php echo urlencode($calendarStartFormatted . '/' . $calendarEndFormatted); ?>&location=<?php echo urlencode($location); ?>&details=<?php echo urlencode('Checked in as ' . $username . ' for ' . $event_name); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Add to Google Calendar
                </a>
            </div>
        </section>

        <aside class="panel info-card">
            <span class="eyebrow">Welcome</span>
            <h2 class="panel-title"><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>, your spot is confirmed.</h2>
            <p class="hero-side-copy">
                The welcome email now receives the real event date, time, and location so the message and calendar handoff stay consistent.
            </p>
        </aside>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/emailjs-com@3/dist/email.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    emailjs.init("PwpRTCO22gMYNO_Gs");

    window.sendEmail = function() {
        const params = {
            user_name: <?php echo json_encode($username); ?>,
            event_name: <?php echo json_encode($event_name); ?>,
            event_date: <?php echo json_encode($eventDateFormatted); ?>,
            event_time: <?php echo json_encode($event_time); ?>,
            event_location: <?php echo json_encode($location); ?>,
            user_email: <?php echo json_encode($email); ?>,
            email: <?php echo json_encode($email); ?>,
            to_email: <?php echo json_encode($email); ?>,
            recipient_email: <?php echo json_encode($email); ?>,
            reply_to: <?php echo json_encode($email); ?>,
            message: <?php echo json_encode("Welcome to {$event_name} on {$eventDateFormatted} at {$event_time}. Location: {$location}."); ?>
        };

        emailjs.send("service_xnqqhgt", "template_9wfpcgg", params)
            .then(function(response) {
                console.log("SUCCESS", response);
                alert("Welcome email sent successfully.");
            })
            .catch(function(error) {
                console.error("EMAIL ERROR:", error);
                alert("Email failed: " + (error.text || JSON.stringify(error)));
            });
    };
});
</script>

</body>
</html>
