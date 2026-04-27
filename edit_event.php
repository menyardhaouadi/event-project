<?php 
include 'connect.php'; 
session_start();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$admin_id = $_SESSION['user_id'] ?? 0;

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t = $_POST['title'];
    $d = $_POST['description'];
    $da = $_POST['date'];

    $conn->query("UPDATE events 
    SET title='$t', description='$d', date='$da' 
    WHERE id=$id AND admin_id=$admin_id");

    header("Location: admin.php");
    exit();
}

// get event
$res = $conn->query("SELECT * FROM events WHERE id=$id AND admin_id=$admin_id");
$event = $res ? $res->fetch_assoc() : null;
?>

<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="style.css?v=unreal1">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="page-app">
<main class="site-shell app-shell">
    <header class="topbar">
        <div>
            <div class="brand-mark">EM</div>
            <h1 class="page-title">Edit event details.</h1>
            <p class="page-subtitle">Update the title, description, or date for an event you own and then return to the admin dashboard.</p>
        </div>

        <div class="topbar-actions">
            <a class="btn btn-secondary" href="admin.php">Back to Admin</a>
            <a class="btn btn-ghost" href="logout.php">Logout</a>
        </div>
    </header>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Event editor</h2>
                <p class="panel-subtitle">Only events assigned to your admin account can be updated here.</p>
            </div>
        </div>

        <?php if ($event): ?>
            <form method="POST" class="form-stack">
                <div class="field">
                    <label for="title">Event name</label>
                    <input id="title" name="title" value="<?php echo e($event['title']); ?>" required>
                </div>

                <div class="field">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" required><?php echo e($event['description']); ?></textarea>
                </div>

                <div class="field">
                    <label for="date">Event date</label>
                    <input id="date" type="date" name="date" value="<?php echo e($event['date']); ?>" required>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Update Event</button>
                    <a class="btn btn-secondary" href="admin.php">Cancel</a>
                </div>
            </form>
        <?php else: ?>
            <div class="empty-state">This event could not be found, or it does not belong to your admin account anymore.</div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
