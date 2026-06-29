<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

extract(TenantManager::getBrandingVars());

if (isAuthenticated()) {
    header('Location: ' . routeUserBasedOnRole(getCurrentUser()['role'] ?? ''));
    exit;
}

$error = '';
$success = '';
$form = ['hotel_name' => '', 'slug' => '', 'owner_name' => '', 'username' => ''];
$selectedPlan = $_POST['plan'] ?? $_GET['plan'] ?? null;
$selectedPlan = $selectedPlan !== null ? strtolower(trim((string) $selectedPlan)) : null;
$allowedPlans = ['starter', 'pro', 'premium'];
if ($selectedPlan === null || $selectedPlan === '') {
    header('Location: index.php#pricing');
    exit;
}
if (!in_array($selectedPlan, $allowedPlans, true)) {
    header('Location: index.php#pricing');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['hotel_name'] = trim($_POST['hotel_name'] ?? '');
    $form['slug'] = trim($_POST['slug'] ?? '');
    $form['owner_name'] = trim($_POST['owner_name'] ?? '');
    $form['username'] = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if ($password !== $confirm) {
        $error = 'Passwords do not match';
    } else {
        $res = TenantManager::registerTenant($form['hotel_name'], $form['slug'], $form['owner_name'], $form['username'], $password, '', $selectedPlan);
        if ($res['success']) {
            $loginRes = login($form['hotel_name'], $form['username'], $password);
            if ($loginRes['success']) {
                header('Location: admin.php?welcome=1');
                exit;
            }
            $success = 'Account created. Please sign in.';
        } else {
            $error = $res['message'] ?? 'Registration failed';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo htmlspecialchars($appName); ?></title>
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
            <span><span class="auth-brand-name"><?php echo htmlspecialchars($appName); ?></span></span>
        </a>
        <a href="login.php" class="auth-link">Sign In</a>
    </header>

    <main style="flex:1;display:flex;align-items:center;justify-content:center;padding:6rem 1.5rem 3rem">
        <div class="auth-card auth-card-wide">
            <h1 class="auth-title">Start Your Hotel</h1>
            <p class="auth-subtitle">Register as a new tenant</p>

            <?php if ($error): ?>
                <div class="auth-error"><i data-lucide="alert-circle" style="width:16px;height:16px"></i><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#047857;padding:0.85rem 1rem;border-radius:0.75rem;margin-bottom:1.25rem;font-size:0.85rem">
                    <?php echo htmlspecialchars($success); ?> <a href="login.php" class="auth-link">Sign in</a>
                </div>
            <?php endif; ?>

            <form method="POST" id="registerForm" style="display:flex;flex-direction:column;gap:1rem">
                <input type="hidden" name="plan" value="<?php echo htmlspecialchars($selectedPlan); ?>">
                <div style="background:#f0faf5;border:1px solid #c5d5cc;color:#145239;padding:0.85rem 1rem;border-radius:0.75rem;margin-bottom:0.35rem;font-size:0.85rem;font-weight:600">
                    Selected plan: <strong style="text-transform:uppercase"><?php echo htmlspecialchars($selectedPlan); ?></strong>
                    <span style="opacity:0.8;font-weight:500">(&nbsp;<a class="auth-link" href="index.php#pricing">change</a>&nbsp;)</span>
                </div>
                <div>
                    <label class="auth-label">Hotel / Business Name</label>
                    <input type="text" name="hotel_name" id="hotelName" value="<?php echo htmlspecialchars($form['hotel_name']); ?>" required placeholder="Grand Addis Hotel" class="auth-input">
                </div>
                <div>
                    <label class="auth-label">Your Hotel URL</label>
                    <div style="display:flex;align-items:center;gap:0.5rem">
                        <span class="auth-muted" style="white-space:nowrap">yourapp.com/</span>
                        <input type="text" name="slug" id="hotelSlug" value="<?php echo htmlspecialchars($form['slug']); ?>" pattern="[a-z0-9][a-z0-9-]{1,46}[a-z0-9]" placeholder="grand-addis" class="auth-input" style="font-family:monospace">
                    </div>
                    <p id="slugStatus" class="auth-muted" style="margin-top:0.35rem"></p>
                </div>
                <div>
                    <label class="auth-label">Your Full Name</label>
                    <input type="text" name="owner_name" value="<?php echo htmlspecialchars($form['owner_name']); ?>" required placeholder="Abebe Kebede" class="auth-input">
                </div>
                <div>
                    <label class="auth-label">Username</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($form['username']); ?>" required pattern="[a-zA-Z0-9][a-zA-Z0-9._-]{1,30}[a-zA-Z0-9]" placeholder="abe.admin" class="auth-input">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                    <div>
                        <label class="auth-label">Password</label>
                        <input type="password" name="password" required minlength="8" placeholder="Min. 8 characters" class="auth-input">
                    </div>
                    <div>
                        <label class="auth-label">Confirm</label>
                        <input type="password" name="password_confirm" required minlength="8" placeholder="Repeat" class="auth-input">
                    </div>
                </div>
                <button type="submit" class="auth-btn" style="margin-top:0.5rem">Create Hotel Account</button>
            </form>

            <div class="auth-footer-links">
                <p class="auth-muted">Already have an account?</p>
                <a href="login.php" class="auth-link">Sign In to Your Hotel</a>
            </div>
        </div>
    </main>

    <script>
        lucide.createIcons();
        const hotelName = document.getElementById('hotelName');
        const hotelSlug = document.getElementById('hotelSlug');
        const slugStatus = document.getElementById('slugStatus');
        let slugTouched = <?php echo $form['slug'] !== '' ? 'true' : 'false'; ?>;

        function slugify(text) {
            return text.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 48);
        }
        hotelName.addEventListener('input', () => { if (!slugTouched) { hotelSlug.value = slugify(hotelName.value); checkSlug(); } });
        hotelSlug.addEventListener('input', () => { slugTouched = true; hotelSlug.value = slugify(hotelSlug.value); checkSlug(); });

        let slugTimer;
        function checkSlug() {
            clearTimeout(slugTimer);
            const slug = hotelSlug.value;
            if (slug.length < 3) { slugStatus.textContent = 'URL must be at least 3 characters'; slugStatus.style.color = '#b45309'; return; }
            slugTimer = setTimeout(async () => {
                try {
                    const res = await fetch('api/auth/check-slug.php?slug=' + encodeURIComponent(slug));
                    const data = await res.json();
                    if (data.available) { slugStatus.textContent = 'This URL is available'; slugStatus.style.color = '#047857'; }
                    else { slugStatus.textContent = data.message || 'This URL is taken'; slugStatus.style.color = '#b91c1c'; }
                } catch (e) { slugStatus.textContent = ''; }
            }, 400);
        }
        if (hotelSlug.value) checkSlug();
    </script>
</body>
</html>
