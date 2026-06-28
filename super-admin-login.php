<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

extract(TenantManager::getBrandingVars());

if (isAuthenticated()) {
    header('Location: ' . routeUserBasedOnRole(getCurrentUser()['role'] ?? ''));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $res = loginSuperAdmin($username, $password);
    if ($res['success']) {
        header('Location: platform-admin.php');
        exit;
    }
    $error = $res['message'] ?? 'Invalid credentials';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin - <?php echo htmlspecialchars($appName); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="public/css/theme.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="auth-page">
    <header class="auth-page-header">
        <a href="index.php" class="auth-brand">
            <span class="auth-brand-icon"><i data-lucide="shield"></i></span>
            <span><span class="auth-brand-name">Super Admin</span><br><span class="auth-brand-tag">Platform Control</span></span>
        </a>
        <a href="login.php" class="auth-link">Hotel Login</a>
    </header>

    <main style="flex:1;display:flex;align-items:center;justify-content:center;padding:6rem 1.5rem 3rem">
        <div class="auth-card" style="max-width:420px">
            <h1 class="auth-title">Super Admin</h1>
            <p class="auth-subtitle">Platform sign in</p>

            <?php if ($error): ?>
                <div class="auth-error"><i data-lucide="alert-circle" style="width:16px;height:16px"></i><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" style="display:flex;flex-direction:column;gap:1.25rem">
                <div>
                    <label class="auth-label">Username / Email</label>
                    <input type="text" name="username" required autocomplete="username" placeholder="kidayos2014@gmail.com" class="auth-input">
                </div>
                <div>
                    <label class="auth-label">Password</label>
                    <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••" class="auth-input">
                </div>
                <button type="submit" class="auth-btn">Sign In</button>
            </form>

            <div class="auth-footer-links">
                <a href="login.php" class="auth-link">Hotel Staff Login</a>
                <a href="index.php" class="auth-link" style="opacity:0.5;font-weight:500">← Return to Home</a>
            </div>
        </div>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>
