<?php
/**
 * Platform Super Admin Dashboard
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/TenantManager.php';

requirePlatformSuperAdmin();

$user = getCurrentUser();
$platformBranding = TenantManager::getPlatformBrandingVars();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Admin - <?php echo htmlspecialchars($platformBranding['appName']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="public/css/theme.css">
    <?php if (!empty($platformBranding['faviconUrl'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($platformBranding['faviconUrl']); ?>" />
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="theme-light min-h-screen" style="background:#f7faf8;color:#1a2e28">
    <header class="border-b sticky top-0 z-40" style="background:#fff;border-color:#e2ebe6;box-shadow:0 1px 3px rgba(0,0,0,0.04)">
        <div class="max-w-7xl mx-auto px-6 py-5 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <?php if (!empty($platformBranding['publicLogoUrl'])): ?>
                    <img src="<?php echo htmlspecialchars($platformBranding['publicLogoUrl']); ?>" alt="" class="w-10 h-10 rounded-xl object-cover border" style="border-color:#e2ebe6">
                <?php else: ?>
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white text-xs font-black" style="background:#1d6b4a">HS</div>
                <?php endif; ?>
                <div>
                    <p class="text-[10px] font-black uppercase tracking-[0.35em]" style="color:#1d6b4a">Platform Super Admin</p>
                    <h1 class="text-xl font-bold mt-1"><?php echo htmlspecialchars($platformBranding['appName']); ?> Control Panel</h1>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm hidden sm:inline" style="color:#5c6f68"><?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['username']); ?>)</span>
                <a href="logout.php" class="text-xs font-bold uppercase tracking-wider" style="color:#1d6b4a">Logout</a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-10">
        <div class="rounded-2xl border overflow-hidden mb-10" style="background:#fff;border-color:#e2ebe6;box-shadow:0 4px 24px rgba(26,46,40,0.06)">
            <div class="px-6 py-4 border-b" style="border-color:#e2ebe6">
                <h2 class="font-bold">Website Branding</h2>
                <p class="text-xs mt-1" style="color:#5c6f68">Manage the main website name, logo, and favicon shown on the landing page and login screens</p>
            </div>
            <div class="p-6 grid lg:grid-cols-12 gap-8">
                <div class="lg:col-span-4 space-y-4">
                    <p class="text-[10px] font-black uppercase tracking-widest" style="color:#5c6f68">Preview</p>
                    <div class="rounded-xl border p-5 text-center" style="border-color:#e2ebe6;background:#f7faf8">
                        <div class="w-20 h-20 mx-auto rounded-xl overflow-hidden border flex items-center justify-center" style="border-color:#e2ebe6;background:#fff">
                            <img id="brand-logo-preview" src="<?php echo htmlspecialchars($platformBranding['publicLogoUrl']); ?>" alt="" class="w-full h-full object-cover"<?php echo empty($platformBranding['publicLogoUrl']) ? ' style="display:none"' : ''; ?>>
                            <span id="brand-logo-fallback" class="text-xs font-black" style="color:#1d6b4a<?php echo !empty($platformBranding['publicLogoUrl']) ? ';display:none' : ''; ?>">LOGO</span>
                        </div>
                        <p id="brand-name-preview" class="font-bold mt-3"><?php echo htmlspecialchars($platformBranding['appName']); ?></p>
                        <p id="brand-tag-preview" class="text-xs mt-1" style="color:#5c6f68"><?php echo htmlspecialchars($platformBranding['appTagline']); ?></p>
                    </div>
                    <div class="rounded-xl border p-4 flex items-center gap-3" style="border-color:#e2ebe6;background:#f7faf8">
                        <img id="brand-favicon-preview" src="<?php echo htmlspecialchars($platformBranding['faviconUrl'] ?: $platformBranding['publicLogoUrl']); ?>" alt="" class="w-5 h-5 object-contain" <?php echo empty($platformBranding['faviconUrl']) && empty($platformBranding['publicLogoUrl']) ? 'style="display:none"' : ''; ?>>
                        <span id="brand-tab-preview" class="text-xs truncate" style="color:#5c6f68"><?php echo htmlspecialchars($platformBranding['appName']); ?></span>
                    </div>
                </div>
                <div class="lg:col-span-8 space-y-5">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="auth-label">Website Name</label>
                            <input id="brand-app-name" type="text" value="<?php echo htmlspecialchars($platformBranding['appName']); ?>" class="auth-input">
                        </div>
                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="auth-label">Website Tagline</label>
                            <input id="brand-app-tagline" type="text" value="<?php echo htmlspecialchars($platformBranding['appTagline']); ?>" class="auth-input">
                        </div>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <label class="auth-label">Logo</label>
                            <div class="rounded-xl border border-dashed p-5 text-center cursor-pointer" style="border-color:#c5d5cc" onclick="document.getElementById('brand-logo-file').click()">
                                <i data-lucide="upload-cloud" class="w-6 h-6 mx-auto" style="color:#1d6b4a"></i>
                                <p class="text-xs font-bold mt-2" style="color:#5c6f68">Upload logo (PNG, JPG, WebP)</p>
                                <input id="brand-logo-file" type="file" accept="image/*" class="hidden">
                            </div>
                        </div>
                        <div class="space-y-2">
                            <label class="auth-label">Favicon</label>
                            <div class="rounded-xl border border-dashed p-5 text-center cursor-pointer" style="border-color:#c5d5cc" onclick="document.getElementById('brand-favicon-file').click()">
                                <i data-lucide="image" class="w-6 h-6 mx-auto" style="color:#1d6b4a"></i>
                                <p class="text-xs font-bold mt-2" style="color:#5c6f68">Upload favicon icon</p>
                                <input id="brand-favicon-file" type="file" accept="image/*" class="hidden">
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="button" id="brand-save-btn" class="auth-btn" style="width:auto;padding-left:2rem;padding-right:2rem">Save Website Branding</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid sm:grid-cols-3 gap-4 mb-10">
            <div class="rounded-2xl border p-6" style="background:#fff;border-color:#e2ebe6;box-shadow:0 4px 24px rgba(26,46,40,0.06)">
                <p class="text-[10px] font-black uppercase tracking-widest" style="color:#5c6f68">Total Hotels</p>
                <p id="stat-total" class="text-3xl font-bold mt-2">0</p>
            </div>
            <div class="rounded-2xl border p-6" style="background:#fff;border-color:#e2ebe6;box-shadow:0 4px 24px rgba(26,46,40,0.06)">
                <p class="text-[10px] font-black uppercase tracking-widest" style="color:#5c6f68">Active</p>
                <p id="stat-active" class="text-3xl font-bold mt-2" style="color:#1d6b4a">0</p>
            </div>
            <div class="rounded-2xl border p-6" style="background:#fff;border-color:#e2ebe6;box-shadow:0 4px 24px rgba(26,46,40,0.06)">
                <p class="text-[10px] font-black uppercase tracking-widest" style="color:#5c6f68">Your Role</p>
                <p class="text-lg font-bold mt-2" style="color:#1d6b4a">Super Admin</p>
            </div>
        </div>

        <div class="rounded-2xl border overflow-hidden" style="background:#fff;border-color:#e2ebe6;box-shadow:0 4px 24px rgba(26,46,40,0.06)">
            <div class="px-6 py-4 border-b flex items-center justify-between gap-4" style="border-color:#e2ebe6">
                <div>
                    <h2 class="font-bold">Registered Hotels</h2>
                    <p class="text-xs mt-1" style="color:#5c6f68">View credentials, edit, activate/deactivate, or delete hotels</p>
                </div>
                <button type="button" onclick="openCreate()" class="px-4 py-2 rounded-xl text-white text-xs font-black uppercase tracking-wider" style="background:#1d6b4a">+ New Hotel</button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[960px]">
                    <thead class="text-left text-[10px] uppercase tracking-widest border-b" style="color:#5c6f68;border-color:#e2ebe6">
                        <tr>
                            <th class="px-4 py-3">Hotel</th>
                            <th class="px-4 py-3">Slug</th>
                            <th class="px-4 py-3">Owner</th>
                            <th class="px-4 py-3">Username</th>
                            <th class="px-4 py-3">Password</th>
                            <th class="px-4 py-3">Plan</th>
                            <th class="px-4 py-3">Paid Until</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Created</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tenants-body">
                        <tr><td colspan="10" class="px-6 py-10 text-center" style="color:#5c6f68">Loading hotels...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Create / Edit Modal -->
    <div id="tenant-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(15,26,22,0.5)">
        <div class="w-full max-w-lg rounded-2xl border shadow-2xl overflow-hidden" style="background:#fff;border-color:#e2ebe6">
            <div class="px-6 py-5 border-b flex items-center justify-between" style="border-color:#e2ebe6">
                <h3 id="modal-title" class="font-bold text-lg">New Hotel</h3>
                <button type="button" onclick="closeModal()" style="color:#5c6f68"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <form id="tenant-form" class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                <div class="grid sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5 sm:col-span-2">
                        <label class="auth-label">Hotel Name</label>
                        <input id="field-name" type="text" required class="auth-input">
                    </div>
                    <div class="space-y-1.5 sm:col-span-2">
                        <label class="auth-label">URL Slug</label>
                        <input id="field-slug" type="text" required pattern="[a-z0-9][a-z0-9-]{1,46}[a-z0-9]" class="auth-input" style="font-family:monospace">
                    </div>
                    <div id="owner-fields" class="contents">
                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="auth-label">Owner Full Name</label>
                            <input id="field-owner-name" type="text" class="auth-input">
                        </div>
                        <div class="space-y-1.5">
                            <label class="auth-label">Owner Username</label>
                            <input id="field-owner-username" type="text" class="auth-input">
                        </div>
                        <div class="space-y-1.5">
                            <label class="auth-label">Owner Password</label>
                            <input id="field-owner-password" type="text" class="auth-input" style="font-family:monospace">
                            <p id="password-hint" class="auth-muted">Min. 8 characters</p>
                        </div>
                    </div>
                        <div class="space-y-1.5">
                        <label class="auth-label">Plan</label>
                        <select id="field-plan" class="auth-input">
                            <option value="starter">Starter (1,000 ETB)</option>
                            <option value="pro">Pro (1,500 ETB)</option>
                            <option value="premium">Premium (2,000 ETB)</option>
                        </select>
                    </div>
                    <div class="space-y-1.5 sm:col-span-2">
                        <label class="auth-label">Paid Until</label>
                        <div class="flex gap-2">
                            <input id="field-paid-until" type="text" class="auth-input" readonly style="font-family:monospace;opacity:0.85">
                            <button type="button" id="confirm-payment-btn" class="px-4 py-3 rounded-xl text-[10px] font-bold uppercase tracking-wider" style="background:#f0faf5;border:1px solid #e2ebe6;color:#1d6b4a;white-space:nowrap">
                                Confirm payment (+1 month)
                            </button>
                        </div>
                        <p class="auth-muted">Hotels auto-deactivate after this date unless payment is confirmed.</p>
                    </div>
                    <div class="space-y-1.5">
                        <label class="auth-label">Status</label>
                        <select id="field-status" class="auth-input">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="space-y-1.5 sm:col-span-2">
                        <label class="auth-label">Paid Until</label>
                        <div class="flex gap-2">
                            <input id="field-paid-until" type="text" class="auth-input" readonly style="font-family:monospace;opacity:0.85">
                            <button type="button" id="confirm-payment-btn" class="px-4 py-3 rounded-xl text-[10px] font-bold uppercase tracking-wider" style="background:#f0faf5;border:1px solid #e2ebe6;color:#1d6b4a;white-space:nowrap">
                                Confirm payment (+1 month)
                            </button>
                        </div>
                        <p class="auth-muted">If expired and unpaid, the system will auto-deactivate the hotel on their next request.</p>
                    </div>
                </div>
                <div class="flex gap-3 pt-2">
                    <button type="button" onclick="closeModal()" class="flex-1 py-3 rounded-xl border text-sm font-bold uppercase tracking-wider" style="border-color:#e2ebe6;color:#5c6f68">Cancel</button>
                    <button type="submit" class="flex-1 auth-btn" style="width:auto">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toast" class="hidden fixed bottom-6 right-6 z-50 px-5 py-3 rounded-xl text-sm font-semibold shadow-2xl"></div>

    <script src="public/js/platform-admin.js"></script>
</body>
</html>
