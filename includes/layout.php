<?php
/**
 * Shared layout components for the PHP Management System
 */

require_once 'lang.php';
require_once 'auth.php';
require_once __DIR__ . '/SettingsManager.php';
require_once __DIR__ . '/menu-tiers.php';

function renderHeader($title = "Management System", $options = []) {
    global $currentLang;
    $user = getCurrentUser();
    $navMode = $options['nav'] ?? 'default';
    $posTab = $options['posTab'] ?? 'standard';
    
    // Load branding & SEO
    $manager = new SettingsManager();
    extract($manager->getBrandingVars());
    
    // Fetch CMS data for SEO
    require_once __DIR__ . '/cms.php';
    $cms = getCmsData();
    $seo = $cms['seo'] ?? [];
    
    $fullTitle = htmlspecialchars($title . " | " . $appName);
    $metaDesc = htmlspecialchars($seo['description'] ?? "Welcome to " . $appName);
    $metaKeywords = htmlspecialchars($seo['keywords'] ?? "");
    $ogImage = htmlspecialchars($seo['og_image'] ?? ($logoUrl ?: ""));
    
    $baseUrl = ""; // No longer used for canonicals
    ?>
    <!DOCTYPE html>
    <html lang="<?php echo $currentLang; ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="<?php echo $metaDesc; ?>">
        <meta name="keywords" content="<?php echo $metaKeywords; ?>">
        <meta name="robots" content="index, follow">
        
        <?php if (!empty($seo['google_verification'])): ?>
        <meta name="google-site-verification" content="<?php echo htmlspecialchars($seo['google_verification']); ?>" />
        <?php endif; ?>

        <title><?php echo $fullTitle; ?></title>
        <?php if ($faviconUrl): ?>
        <link rel="icon" href="<?php echo htmlspecialchars($faviconUrl); ?>" />
        <?php endif; ?>

        <?php if ($publicLogoUrl): ?>
        <script type="application/ld+json">
        {
          "@context": "https://schema.org",
          "@type": "Hotel",
          "name": "<?php echo addslashes($appName); ?>",
          "logo": "<?php echo addslashes((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/" . $publicLogoUrl); ?>",
          "url": "<?php echo addslashes((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]/"); ?>",
          "description": "<?php echo addslashes($metaDesc); ?>",
          "address": {
            "@type": "PostalAddress",
            "addressLocality": "Dilla",
            "addressRegion": "Gedeo Zone, SNNPR",
            "addressCountry": "ET"
          },
          "priceRange": "$$",
          "telephone": "<?php echo addslashes($contact['phone'] ?? ''); ?>"
        }
        </script>
        <?php endif; ?>

        <!-- Tailwind CSS CDN -->
        <script src="https://cdn.tailwindcss.com"></script>
        <!-- Google Fonts: Inter -->
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800;900&display=swap" rel="stylesheet">
        <!-- Lucide Icons -->
        <script src="https://unpkg.com/lucide@latest"></script>
        <!-- Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <link rel="stylesheet" href="public/css/theme.css">
        <style>
            body {
                font-family: 'Inter', sans-serif;
                -webkit-font-smoothing: antialiased;
            }

            /* Smooth Page Transition */
            .page-enter {
                animation: fadeIn 0.4s ease-out;
            }
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }

            /* Standard Glass Components */
            .glass {
                background: var(--app-surface, #fff);
                backdrop-filter: blur(8px);
                border: 1px solid var(--border, #e2ebe6);
                box-shadow: var(--shadow, 0 4px 24px rgba(26,46,40,0.08));
            }

            .sidebar-link {
                display: flex;
                align-items: center;
                gap: 0.875rem;
                padding: 0.875rem 1.25rem;
                border-radius: var(--radius);
                transition: all 0.2s ease;
                color: var(--muted, #3d5249);
                font-size: 0.9375rem;
                font-weight: 600;
            }

            .sidebar-link:hover {
                background-color: var(--green-soft, #f0faf5);
                color: var(--green, #1d6b4a);
            }

            .sidebar-link.active {
                background-color: var(--green-light, #e8f5ef);
                color: var(--green, #1d6b4a);
                font-weight: 700;
            }

            /* Custom Standard Scrollbar */
            ::-webkit-scrollbar { width: 6px; }
            ::-webkit-scrollbar-track { background: transparent; }
            ::-webkit-scrollbar-thumb { 
                background: rgba(29, 107, 74, 0.25); 
                border-radius: 9999px; 
            }
            ::-webkit-scrollbar-thumb:hover { background: rgba(29, 107, 74, 0.4); }

            /* Mobile Menu State */
            #mobile-menu.active {
                transform: translateX(0);
            }
        </style>
        <script>
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {
                        borderRadius: {
                            lg: "var(--radius)",
                            md: "calc(var(--radius) - 2px)",
                            sm: "calc(var(--radius) - 4px)",
                        },
                        colors: {
                            background: "hsl(var(--background))",
                            foreground: "hsl(var(--foreground))",
                            primary: {
                                DEFAULT: "hsl(var(--primary))",
                                foreground: "hsl(var(--primary-foreground))",
                            },
                            secondary: {
                                DEFAULT: "hsl(var(--secondary))",
                                foreground: "hsl(var(--secondary-foreground))",
                            },
                            muted: {
                                DEFAULT: "hsl(var(--muted))",
                                foreground: "hsl(var(--muted-foreground))",
                            },
                            accent: {
                                DEFAULT: "hsl(var(--accent))",
                                foreground: "hsl(var(--accent-foreground))",
                            },
                            border: "hsl(var(--border))",
                        }
                    }
                }
            }
        </script>
    </head>
    <body class="theme-light min-h-screen flex flex-col selection:bg-primary/10 selection:text-primary">
        
        <?php if ($user && $navMode !== 'kiosk'): ?>
        <!-- Top Navigation Bar -->
        <nav class="h-[60px] bg-white border-b border-[#e2ebe6] flex items-center justify-between px-6 shrink-0 z-50 sticky top-0 shadow-sm">
            <!-- Logo & Brand -->
            <a href="admin.php" class="flex items-center gap-3 hover:opacity-90 transition-opacity">
                <?php if ($logoUrl): ?>
                    <div class="w-10 h-10 rounded-xl overflow-hidden bg-[#e8f5ef] border border-[#e2ebe6] flex-shrink-0">
                        <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo" class="w-full h-full object-cover">
                    </div>
                <?php else: ?>
                    <div class="w-10 h-10 rounded-xl bg-[#1d6b4a] flex flex-col items-center justify-center flex-shrink-0">
                        <span class="text-[10px] font-bold tracking-widest text-white leading-none"><?php echo htmlspecialchars(strtoupper(substr($appName, 0, 3))); ?></span>
                    </div>
                <?php endif; ?>
                <div>
                    <h1 class="text-[#0f1f1a] font-extrabold text-xl leading-none tracking-tight mt-0.5"><?php echo htmlspecialchars($appName); ?></h1>
                    <p class="text-xs text-[#3d5249] font-bold uppercase tracking-wider leading-none mt-1"><?php echo htmlspecialchars($appTagline); ?></p>
                </div>
            </a>

            <!-- Nav Links -->
            <div class="hidden md:flex items-center gap-1">
                <?php
                if ($navMode === 'pos') {
                    renderPosNavLinks($posTab);
                } elseif ($navMode === 'kitchen') {
                    echo '<span class="text-sm font-bold uppercase tracking-[0.25em] text-white">Kitchen</span>';
                } else {
                    renderTopNavLinks($user['role']);
                }
                ?>
            </div>

            <!-- Right Side -->
            <div class="flex items-center gap-2 md:gap-4">
                <span class="hidden lg:block text-base font-semibold text-[#3d5249]">
                    Hi, <?php echo htmlspecialchars($user['name'] ?? 'User'); ?>!
                </span>
                <a href="logout.php" class="px-3 py-1.5 md:px-4 md:py-2 bg-[#f0faf5] hover:bg-red-500 hover:text-white text-[#1d6b4a] text-base font-semibold rounded-lg transition-colors border border-[#e2ebe6]">
                    Logout
                </a>
                
                <!-- Mobile Menu Toggle -->
                <button id="mobile-menu-toggle" class="md:hidden p-2 text-[#5c6f68] hover:text-[#1d6b4a] transition-colors">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>
            </div>
        </nav>

        <!-- Mobile Navigation Drawer -->
        <div id="mobile-menu" class="fixed inset-0 z-[100] bg-white/98 backdrop-blur-xl translate-x-full transition-transform duration-300 md:hidden">
            <div class="flex flex-col h-full">
                <!-- Mobile Header -->
                <div class="h-[60px] px-6 flex items-center justify-between border-b border-[#e2ebe6] bg-white">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 rounded-lg bg-[#1d6b4a] flex items-center justify-center">
                            <span class="text-[8px] font-bold text-white"><?php echo htmlspecialchars(strtoupper(substr($appName, 0, 3))); ?></span>
                        </div>
                        <span class="text-[#0f1f1a] font-extrabold text-lg">Menu</span>
                    </div>
                    <button id="mobile-menu-close" class="p-2 text-[#5c6f68] hover:text-[#1d6b4a] transition-colors">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>

                <!-- Mobile Body -->
                <div class="flex-1 overflow-y-auto p-6 space-y-8">
                    <div class="space-y-4">
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-[#1d6b4a]/60 border-b border-[#e2ebe6] pb-2">Main Navigation</p>
                        <div class="flex flex-col gap-1">
                            <?php 
                            if ($navMode === 'pos') {
                                renderPosNavLinks($posTab);
                            } elseif ($navMode === 'kitchen') {
                                echo '<span class="text-sm font-bold uppercase tracking-[0.25em] text-white">Kitchen</span>';
                            } else {
                                // For standard admin, we use the sidebar links as they are more complete and include icons
                                renderSidebarLinks($user['role']);
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Mobile Footer -->
                <div class="p-6 border-t border-[#e2ebe6] bg-[#f7faf8]">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-[#e8f5ef] flex items-center justify-center text-[#1d6b4a]">
                            <i data-lucide="user" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-[#0f1f1a]"><?php echo htmlspecialchars($user['name'] ?? 'User'); ?></p>
                            <p class="text-xs text-[#3d5249] font-bold uppercase tracking-wider"><?php echo htmlspecialchars($user['role'] ?? ''); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Content -->
        <main class="flex-1 flex bg-[#f7faf8] relative min-h-0">
            <div class="flex-1 flex relative page-enter min-h-0 w-full">
    <?php
}

function renderFooter() {
    $user = getCurrentUser();
    ?>
            </div>
        </main>

        <?php if ($user && empty($_SESSION['is_platform_super_admin'])): ?>
        <script src="public/js/cloud-import.js"></script>
        <script>
        (function () {
            const redirectForCode = (code) => {
                const map = {
                    tenant_deactivated: 'tenant_deactivated',
                    deactivated: 'deactivated',
                };
                window.location.href = '/login.php?error=' + (map[code] || 'deactivated');
            };

            const checkSession = async () => {
                try {
                    const res = await fetch('api/auth/session.php', { credentials: 'same-origin' });
                    if (res.status === 401) {
                        const data = await res.json().catch(() => ({}));
                        redirectForCode(data.code || 'deactivated');
                    }
                } catch (e) {
                    // ignore transient network errors
                }
            };

            setInterval(checkSession, 60000);
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') checkSession();
            });
        })();
        </script>
        <?php endif; ?>

        <script>
            lucide.createIcons();

            // Mobile Menu Toggle Logic
            const mobileMenu = document.getElementById('mobile-menu');
            const mobileToggle = document.getElementById('mobile-menu-toggle');
            const mobileClose = document.getElementById('mobile-menu-close');

            if (mobileToggle && mobileMenu) {
                mobileToggle.addEventListener('click', () => {
                    mobileMenu.classList.add('active');
                    document.body.style.overflow = 'hidden';
                });
            }

            if (mobileClose && mobileMenu) {
                mobileClose.addEventListener('click', () => {
                    mobileMenu.classList.remove('active');
                    document.body.style.overflow = '';
                });
            }

            // Close menu on link click
            const mobileLinks = mobileMenu?.querySelectorAll('a');
            mobileLinks?.forEach(link => {
                link.addEventListener('click', () => {
                    mobileMenu.classList.remove('active');
                    document.body.style.overflow = '';
                });
            });
        </script>
    </body>
    </html>
    <?php
}

function getAdminNavLinks($role) {
    if (empty($role)) {
        $user = getCurrentUser();
        $role = $user['role'] ?? 'guest';
    }

    $links = [
        ['name' => 'Overview',     'icon' => 'layout-dashboard', 'url' => 'admin.php',        'roles' => ['admin'], 'perm' => 'overview:view'],
        ['name' => 'Orders',       'icon' => 'shopping-cart',    'url' => 'orders.php',       'roles' => ['admin', 'cashier'], 'perm' => 'orders:view'],
        ['name' => 'Users',        'icon' => 'users',            'url' => 'staff.php',        'roles' => ['admin'], 'perm' => 'users:view'],
        ['name' => 'Store',        'icon' => 'door-closed',      'url' => 'store.php',        'roles' => ['admin', 'store_keeper'], 'perm' => 'store:view'],
        ['name' => 'Stock',        'icon' => 'package-2',        'url' => 'stock.php',        'roles' => ['admin'], 'perm' => 'stock:view'],
        ['name' => 'Reports',      'icon' => 'bar-chart-3',      'url' => 'reports.php',      'roles' => ['admin'], 'perm' => 'reports:view'],
        ['name' => 'Services',     'icon' => 'key-round',       'url' => 'services.php',     'roles' => ['admin', 'reception', 'receptionist'], 'perm' => 'services:view'],
        ['name' => 'Settings',     'icon' => 'settings-2',       'url' => 'settings.php',     'roles' => ['admin'], 'perm' => 'settings:view'],
    ];

    $filtered = [];
    foreach ($links as $link) {
        $hasRole = in_array($role, $link['roles']);
        $hasPerm = isset($link['perm']) && hasPermission($link['perm']);
        
        // Special case for reports
        if ($link['url'] === 'reports.php' && hasPermissionPattern('/^reports:/')) {
            $hasPerm = true;
        }

        // Special case for services / reception hub
        if ($link['url'] === 'services.php' && hasPermission('reception:access')) {
            $hasPerm = true;
        }

        // Special case for overview dashboard
        if ($link['url'] === 'admin.php' && (
            hasPermission('orders:view') ||
            hasPermission('stock:view') ||
            hasPermissionPattern('/^reports:/')
        )) {
            $hasPerm = true;
        }

        if ($hasRole || $hasPerm) {
            if (!empty($_SESSION['is_platform_super_admin']) || PlanFeatures::canAccessNavLink(TenantManager::getCurrentPlan(), $link['url'])) {
                $filtered[] = $link;
            }
        }
    }
    return $filtered;
}

function renderTopNavLinks($role) {
    $links = getAdminNavLinks($role);
    $currentUrl = basename($_SERVER['SCRIPT_NAME']);

    foreach ($links as $link) {
        $active = ($currentUrl === $link['url']);
        $cls = $active
            ? 'px-4 py-2 text-base font-bold text-[#1d6b4a] bg-[#e8f5ef] rounded-md transition-colors'
            : 'px-4 py-2 text-base font-semibold text-[#3d5249] hover:text-[#1d6b4a] hover:bg-[#f0faf5] rounded-md transition-colors';
        echo "<a href='{$link['url']}' class='{$cls}'>{$link['name']}</a>";
    }
}

function renderPosNavLinks($activeTab = 'standard') {
    $tabs = [
        ['key' => 'standard', 'label' => 'Standard POS', 'url' => 'cashier.php'],
    ];

    foreach (getMenuTiers() as $tier) {
        $tabs[] = [
            'key' => $tier['id'],
            'label' => ($tier['name'] ?? 'VIP') . ' POS',
            'url' => 'cashier.php?tier=' . urlencode($tier['id']),
        ];
    }

    $tabs[] = ['key' => 'recent', 'label' => 'Recent Orders', 'url' => 'orders.php?view=recent'];

    foreach ($tabs as $tab) {
        $on = ($activeTab === $tab['key']);
        $cls = $on
            ? 'px-3 py-2 text-xs font-extrabold uppercase tracking-wider text-[#1d6b4a] bg-[#e8f5ef] rounded-md'
            : 'px-3 py-2 text-xs font-extrabold uppercase tracking-wider text-[#3d5249] hover:text-[#1d6b4a] hover:bg-[#f0faf5] rounded-md transition-colors';
        echo "<a href='{$tab['url']}' class='{$cls}'>{$tab['label']}</a>";
    }
}

function renderSidebarLinks($role) {
    if (empty($role)) {
        $user = getCurrentUser();
        $role = $user['role'] ?? 'guest';
    }
    
    $links = getAdminNavLinks($role);
    $currentUrl = basename($_SERVER['SCRIPT_NAME']);

    foreach ($links as $link) {
        $active = ($currentUrl === $link['url']) ? 'active' : '';
        echo "<a href='{$link['url']}' class='sidebar-link {$active}'>";
        echo "<i data-lucide='{$link['icon']}' class='w-4 h-4'></i>";
        echo "<span>{$link['name']}</span>";
        echo "</a>";
    }
}
?>
