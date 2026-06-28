<?php
/**
 * Unauthorized Access Page
 */
require_once 'includes/layout.php';

// If not logged in, they shouldn't even be here, but just in case
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

renderHeader('Access Denied');
?>

<div class="min-h-[80vh] flex items-center justify-center p-4">
    <div class="glass max-w-md w-full p-10 rounded-3xl border border-red-200 bg-red-50 text-center space-y-8">
        <div class="mx-auto w-24 h-24 rounded-full bg-red-100 border border-red-200 flex items-center justify-center text-red-500">
            <i data-lucide="shield-alert" class="w-12 h-12"></i>
        </div>
        <div class="space-y-3">
            <h1 class="text-3xl font-black tracking-tight" style="color:#1a2e28">Access Denied</h1>
            <p class="text-red-600 font-semibold text-sm">Sorry, you are not permitted to view this page</p>
            <p class="text-sm leading-relaxed max-w-[280px] mx-auto" style="color:#5c6f68">Your account privileges do not allow access to the requested resource.</p>
        </div>
        <div class="pt-4 flex flex-col gap-3">
            <a href="index.php" class="w-full py-4 rounded-xl border text-sm font-bold flex items-center justify-center gap-2" style="border-color:#e2ebe6;color:#1d6b4a">
                <i data-lucide="home" class="w-4 h-4"></i> Return Home
            </a>
            <button onclick="history.back()" class="w-full py-4 rounded-xl bg-red-500 hover:bg-red-600 text-white text-sm font-bold flex items-center justify-center gap-2">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Go Back
            </button>
        </div>
        <p class="text-xs" style="color:#7a8f85">
            Logged in as <span style="color:#1a2e28;font-weight:600"><?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?></span>
        </p>
    </div>
</div>

<?php renderFooter(); ?>
