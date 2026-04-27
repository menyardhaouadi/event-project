<?php
include 'connect.php';
session_start();

$error = null;

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username=?");
    if (!$stmt) {
        die("SQL ERROR: " . $conn->error);
    }

    $stmt->bind_param("s", $u);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    $storedPassword = $user['password'] ?? '';
    $isValidPassword = false;

    if ($user) {
        $isValidPassword =
            password_verify($p, $storedPassword) ||
            hash_equals((string) $storedPassword, (string) $p);
    }

    if ($isValidPassword) {
        if (!password_get_info($storedPassword)['algo']) {
            $newHash = password_hash($p, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password=? WHERE id=?");

            if ($update) {
                $update->bind_param("si", $newHash, $user['id']);
                $update->execute();
                $update->close();
            }
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];

        if ($user['role'] === 'admin') {
            header("Location: admin.php");
        } else {
            header("Location: dashboard.php");
        }
        exit();
    }

    $error = "Wrong username or password";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Login</title>
<link rel="stylesheet" href="style.css?v=unreal1">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="page-auth">

<main class="site-shell auth-shell">
    <div class="auth-layout">

        <section class="panel auth-card">
            <span class="eyebrow">Welcome back</span>
            <h1 class="auth-title">Log in and continue managing your event experience.</h1>
            <p class="auth-copy">This restores the original polished sign-in screen while keeping the newer QR welcome and calendar features available after login.</p>

            <?php if ($error): ?>
                <div class="notice notice-error"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form method="POST" class="form-stack">
                <div class="field">
                    <label for="username">Username</label>
                    <input id="username" name="username" placeholder="Enter your username" required>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" placeholder="Enter your password" required>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Login</button>
                    <a class="btn btn-secondary" href="index.html">Back Home</a>
                </div>
            </form>
        </section>

        <aside class="panel info-card">
            <span class="eyebrow">Recovered</span>
            <h2 class="panel-title">Users can sign in again, even if their account was created before password hashing was added.</h2>
            <p class="hero-side-copy">
                Successful legacy logins are upgraded to a hashed password automatically so the login flow is safer going forward.
            </p>
        </aside>

    </div>
</main>

</body>
</html>
