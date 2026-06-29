<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

extract(TenantManager::getBrandingVars());

if (isAuthenticated()) {
    $user = getCurrentUser();
    header('Location: ' . routeUserBasedOnRole($user['role'] ?? ''));
    exit;
}

$error = '';
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'tenant_deactivated') {
        $error = 'This hotel account has been deactivated. Please contact platform support.';
    } elseif ($_GET['error'] === 'deactivated') {
        $error = 'Your account has been deactivated.';
    }
}
$hotelName = $_POST['hotel_name'] ?? $_GET['hotel'] ?? '';
$username = $_POST['username'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hotelName = trim($_POST['hotel_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $superRes = loginSuperAdmin($username, $password);
    if ($superRes['success']) {
        header('Location: platform-admin.php');
        exit;
    }

    if ($hotelName === '') {
        $error = $superRes['message'] ?? 'Enter your hotel name to sign in as hotel staff.';
    } else {
        $res = login($hotelName, $username, $password);
        if ($res['success']) {
            header('Location: ' . routeUserBasedOnRole($res['user']['role'] ?? ''));
            exit;
        }
        $error = $res['message'] ?? 'Invalid credentials or account suspended.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo htmlspecialchars($appName); ?></title>
    <?php if ($faviconUrl): ?><link rel="icon" href="<?php echo htmlspecialchars($faviconUrl); ?>" /><?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="public/css/theme.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="auth-page">
    <header class="auth-page-header">
        <a href="index.php" class="auth-brand">
            <?php if ($logoUrl): ?>
                <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="" style="width:44px;height:44px;border-radius:12px;object-fit:cover">
            <?php else: ?>
                <span class="auth-brand-icon"><i data-lucide="building-2"></i></span>
            <?php endif; ?>
            <span>
                <span class="auth-brand-name"><?php echo htmlspecialchars($appName); ?></span><br>
                <span class="auth-brand-tag"><?php echo htmlspecialchars($appTagline); ?></span>
            </span>
        </a>
        <a href="register.php" class="auth-link">Register</a>
    </header>

    <main style="flex:1;display:flex;align-items:center;justify-content:center;padding:6rem 1.5rem 3rem">
        <div class="auth-card">
            <h1 class="auth-title">Welcome Back</h1>
            <p class="auth-subtitle">Sign in to your account</p>

            <?php if ($error): ?>
                <div class="auth-error"><i data-lucide="alert-circle" style="width:16px;height:16px"></i><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" style="display:flex;flex-direction:column;gap:1.25rem">
                <div>
                    <label class="auth-label">Hotel Name <span style="font-weight:400;text-transform:none;letter-spacing:0">(staff only)</span></label>
                    <input type="text" name="hotel_name" value="<?php echo htmlspecialchars($hotelName); ?>" placeholder="Grand Addis Hotel" class="auth-input">
                    <p class="auth-muted" style="margin-top:0.35rem">Leave blank for platform super admin</p>
                </div>
                <div>
                    <label class="auth-label">Username / Email</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" required autocomplete="username" placeholder="your.username" class="auth-input">
                </div>
                <div>
                    <label class="auth-label">Password</label>
                    <input type="password" name="password" required autocomplete="current-password" placeholder="••••••••" class="auth-input">
                </div>
                <button type="submit" class="auth-btn">Sign In</button>
            </form>

            <div class="auth-footer-links">
                <p class="auth-muted">New to the platform?</p>
                <a href="register.php" class="auth-link">Register Your Hotel</a>
                <a href="index.php" class="auth-link" style="opacity:0.5;font-weight:500">← Return to Home</a>
            </div>
        </div>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>
