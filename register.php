<?php
include 'connect.php';

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $u = trim($_POST['username']);
    $p = trim($_POST['password']);
    $r = $_POST['role'];
    $e = trim($_POST['email']);

    // 🔍 check username OR email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username=? OR email=?");
    $stmt->bind_param("ss", $u, $e);
    $stmt->execute();
    $check = $stmt->get_result();

    if ($check && $check->num_rows > 0) {
        $error = "Username or Email already exists";
    } else {

        // 🔐 hash password (IMPORTANT)
        $hashed_password = password_hash($p, PASSWORD_DEFAULT);

        // ✅ insert user
        $stmt = $conn->prepare("
            INSERT INTO users (username, password, role, email) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("ssss", $u, $hashed_password, $r, $e);

        if ($stmt->execute()) {
            $success = "Account created successfully";
        } else {
            $error = "Error creating account";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<link rel="stylesheet" href="style.css?v=unreal1">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="page-auth">

<main class="site-shell auth-shell">
    <div class="auth-layout">

        <section class="panel auth-card">
            <span class="eyebrow">Create your account</span>
            <h1 class="auth-title">Start with a profile that matches your role.</h1>
            <p class="auth-copy">Admins get publishing controls and users get a streamlined event browsing experience.</p>

            <?php if ($success): ?>
                <div class="notice notice-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="notice notice-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" class="form-stack">

                <div class="field">
                    <label>Username</label>
                    <input name="username" required>
                </div>

                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>

                <div class="field">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>

                <div class="field">
                    <label>Account type</label>
                    <select name="role">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary">Register</button>
                    <a class="btn btn-secondary" href="index.html">Back Home</a>
                </div>

            </form>
        </section>

        <aside class="panel info-card">
            <span class="eyebrow">Why this helps</span>
            <h2 class="panel-title">A more credible first impression.</h2>
            <p class="hero-side-copy">
                The refreshed UI gives the app structure and makes it feel like a real platform.
            </p>
        </aside>

    </div>
</main>

</body>
</html>
