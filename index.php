<?php
/**
 * SaaS Platform Landing Page
 */
require_once 'includes/layout.php';
require_once __DIR__ . '/includes/TenantManager.php';

extract(TenantManager::getBrandingVars());

$title = $appName . ' — Hotel Management Platform';
renderHeader($title, ['nav' => 'kiosk']);

$pricingPlans = [
    [
        'id' => 'starter',
        'name' => 'Starter',
        'price' => '1000',
        'currency' => 'ETB',
        'period' => '/month',
        'featured' => false,
        'features' => [
            'POS (Cashier) + Orders',
            'Kitchen, bar & display screens',
            'Basic menu & table management',
            'Basic reports (daily summary)',
            'Up to 10 staff accounts',
            'Standard support',
        ],
    ],
    [
        'id' => 'pro',
        'name' => 'Pro',
        'price' => '1500',
        'currency' => 'ETB',
        'period' => '/month',
        'featured' => true,
        'features' => [
            'Everything in Starter',
            'Reception + room management',
            'Store inventory + stock control',
            'Distribution categories & floors',
            '1 VIP menu tier',
            'Advanced reports + cloud import',
            'Up to 25 staff accounts',
        ],
    ],
    [
        'id' => 'premium',
        'name' => 'Premium',
        'price' => '2000',
        'currency' => 'ETB',
        'period' => '/month',
        'featured' => false,
        'features' => [
            'Everything in Pro',
            'Unlimited VIP menu tiers',
            'Advanced permissions & roles',
            'Multi-floor & multi-station operations',
            'Unlimited staff accounts',
            'Dedicated onboarding',
        ],
    ],
];

$heroNavLinks = [
    ['name' => 'Overview', 'active' => true],
    ['name' => 'Orders', 'active' => false],
    ['name' => 'Stock', 'active' => false],
    ['name' => 'Reports', 'active' => false],
    ['name' => 'Services', 'active' => false],
];

$heroMetrics = [
    ['label' => "Today's Revenue", 'value' => '$12,480', 'icon' => 'dollar-sign'],
    ['label' => 'Average Order', 'value' => '$42.50', 'icon' => 'trending-up'],
    ['label' => 'Active Orders', 'value' => '6', 'icon' => 'bell-ring'],
    ['label' => 'Stock Alerts', 'value' => '3', 'icon' => 'package'],
];

$heroActions = [
    ['title' => 'View Reports', 'desc' => 'Full Sales & Strategic Analytics', 'icon' => 'bar-chart-3'],
    ['title' => 'Manage Stock', 'desc' => 'Live Inventory Audit & Controls', 'icon' => 'package'],
    ['title' => 'Services', 'desc' => 'Room, Floor & Customer Workflow', 'icon' => 'key-round'],
];
?>

<div id="saas-landing">

<!-- NAV -->
<header class="sl-nav">
    <div class="sl-nav-inner">
        <a href="#" class="sl-brand">
            <?php if ($publicLogoUrl): ?>
                <span class="sl-brand-logo"><img src="<?php echo htmlspecialchars($publicLogoUrl); ?>" alt=""></span>
            <?php else: ?>
                <span class="sl-brand-icon"><i data-lucide="building-2"></i></span>
            <?php endif; ?>
            <span class="sl-brand-name"><?php echo htmlspecialchars($appName); ?></span>
        </a>
        <nav class="sl-desktop-nav">
            <a href="#features">Features</a>
            <a href="#pricing">Pricing</a>
            <a href="#integrations">Integrations</a>
        </nav>
        <div class="sl-nav-ctas">
            <a href="login.php" class="sl-btn-outline">Sign In</a>
            <a href="#pricing" class="sl-btn-green">Get Started</a>
            <button id="slMobileMenuBtn" class="sl-mobile-toggle" aria-label="Menu"><i data-lucide="menu"></i></button>
        </div>
    </div>
</header>

<div id="slMobileNav" class="sl-mobile-nav">
    <div class="sl-mobile-nav-header">
        <span><?php echo htmlspecialchars($appName); ?></span>
        <button id="slMobileNavClose"><i data-lucide="x"></i></button>
    </div>
    <nav class="sl-mobile-links">
        <a href="#features" class="sl-mobile-link">Features</a>
        <a href="#pricing" class="sl-mobile-link">Pricing</a>
        <a href="#integrations" class="sl-mobile-link">Integrations</a>
        <a href="login.php" class="sl-mobile-link">Sign In</a>
        <a href="#pricing" class="sl-mobile-link sl-mobile-cta">Get Started</a>
    </nav>
</div>

<!-- HERO -->
<section class="sl-hero">
    <div class="sl-hero-inner">
        <div class="sl-hero-text reveal">
            <h1>All-in-One SaaS to Simplify Hotel Management</h1>
            <p>Save time, reduce costs, and run your hotel smarter than ever with <?php echo htmlspecialchars($appName); ?> — reception, POS, inventory, staff, and reports in one place.</p>
            <a href="#pricing" class="sl-btn-green sl-btn-lg">Start Your Free Trial Today</a>
        </div>
        <div class="sl-hero-preview reveal reveal-d1">
            <div class="sl-admin-preview" aria-hidden="true">
                <div class="sl-preview-nav">
                    <div class="sl-preview-brand">
                        <?php if ($publicLogoUrl): ?>
                            <span class="sl-preview-logo"><img src="<?php echo htmlspecialchars($publicLogoUrl); ?>" alt=""></span>
                        <?php else: ?>
                            <span class="sl-preview-logo sl-preview-logo-fallback"><?php echo htmlspecialchars(strtoupper(substr($appName, 0, 3))); ?></span>
                        <?php endif; ?>
                        <div>
                            <strong><?php echo htmlspecialchars($appName); ?></strong>
                            <span><?php echo htmlspecialchars($appTagline); ?></span>
                        </div>
                    </div>
                    <div class="sl-preview-nav-links">
                        <?php foreach ($heroNavLinks as $link): ?>
                            <span class="sl-preview-nav-link<?php echo $link['active'] ? ' active' : ''; ?>"><?php echo htmlspecialchars($link['name']); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="sl-preview-nav-right">
                        <span class="sl-preview-greeting">Hi, Admin!</span>
                        <span class="sl-preview-logout">Logout</span>
                    </div>
                </div>
                <div class="sl-preview-body">
                    <div class="sl-preview-header">
                        <div class="sl-preview-header-left">
                            <div class="sl-preview-header-icon"><i data-lucide="bar-chart-3"></i></div>
                            <div>
                                <h3>Admin Dashboard</h3>
                                <p>Business Intelligence &amp; Performance Hub</p>
                            </div>
                        </div>
                        <div class="sl-preview-refresh"><i data-lucide="refresh-cw"></i></div>
                    </div>
                    <div class="sl-preview-metrics">
                        <?php foreach ($heroMetrics as $metric): ?>
                            <div class="sl-preview-metric">
                                <div class="sl-preview-metric-top">
                                    <span><?php echo htmlspecialchars($metric['label']); ?></span>
                                    <div class="sl-preview-metric-icon"><i data-lucide="<?php echo $metric['icon']; ?>"></i></div>
                                </div>
                                <strong><?php echo htmlspecialchars($metric['value']); ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="sl-preview-actions">
                        <?php foreach ($heroActions as $action): ?>
                            <div class="sl-preview-action">
                                <div class="sl-preview-action-top">
                                    <i data-lucide="<?php echo $action['icon']; ?>"></i>
                                    <i data-lucide="arrow-right" class="sl-preview-action-arrow"></i>
                                </div>
                                <h4><?php echo htmlspecialchars($action['title']); ?></h4>
                                <p><?php echo htmlspecialchars($action['desc']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FEATURE 1 -->
<section id="features" class="sl-feature-row">
    <div class="sl-section-inner sl-feature-split">
        <div class="sl-feature-copy reveal">
            <span class="sl-eyebrow">Bookings & Reservations</span>
            <h2>Manage every guest stay from one dashboard</h2>
            <p>Handle check-ins, extensions, and checkouts with real-time room status. Your reception team always knows who is arriving, staying, and departing today.</p>
            <a href="#pricing" class="sl-link-arrow">Get started <i data-lucide="arrow-right"></i></a>
        </div>
        <div class="sl-feature-visual reveal reveal-d1">
            <div class="sl-mini-card">
                <p class="sl-mini-title">Reservation Details</p>
                <div class="sl-mini-grid">
                    <div class="sl-mini-stat"><strong>48</strong><span>Bookings</span></div>
                    <div class="sl-mini-stat"><strong>16</strong><span>Check-in</span></div>
                    <div class="sl-mini-stat"><strong>9</strong><span>Check-out</span></div>
                    <div class="sl-mini-stat"><strong>23</strong><span>Stay Now</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FEATURE 2 -->
<section class="sl-feature-row sl-feature-row-alt">
    <div class="sl-section-inner sl-feature-split sl-feature-split-reverse">
        <div class="sl-feature-copy reveal">
            <span class="sl-eyebrow">Revenue Management</span>
            <h2>Track performance and grow your revenue</h2>
            <p>Live financial reports, category sales, cashier reconciliation, and inventory usage — so you always know what is driving profit across restaurant, bar, and rooms.</p>
            <a href="#pricing" class="sl-link-arrow">Get started <i data-lucide="arrow-right"></i></a>
        </div>
        <div class="sl-feature-visual reveal reveal-d1">
            <div class="sl-mini-card">
                <p class="sl-mini-title">Revenue Overview</p>
                <svg viewBox="0 0 200 60" class="sl-chart-line"><polyline points="0,50 25,42 50,45 75,30 100,35 125,20 150,28 175,15 200,22" fill="none" stroke="#1d6b4a" stroke-width="2.5"/></svg>
                <div class="sl-chart-legend"><span></span> Monthly revenue</div>
            </div>
        </div>
    </div>
</section>

<!-- PRICING -->
<section id="pricing" class="sl-pricing">
    <div class="sl-section-inner sl-pricing-head reveal">
        <h2>Simple, transparent pricing</h2>
        <p>Choose the plan that fits your hotel. No hidden fees — scale as you grow.</p>
    </div>
    <div class="sl-section-inner sl-pricing-grid">
        <?php foreach ($pricingPlans as $i => $plan): ?>
        <div class="sl-price-card reveal reveal-d<?php echo ($i % 3) + 1; ?><?php echo $plan['featured'] ? ' sl-price-featured' : ''; ?>">
            <div class="sl-price-top">
                <h3><?php echo htmlspecialchars($plan['name']); ?></h3>
                <div class="sl-price-amount">
                    <span class="sl-price-currency"><?php echo htmlspecialchars($plan['currency'] ?? 'ETB'); ?></span>
                    <span class="sl-price-num"><?php echo htmlspecialchars($plan['price']); ?></span>
                    <span class="sl-price-period"><?php echo htmlspecialchars($plan['period']); ?></span>
                </div>
            </div>
            <ul class="sl-price-features">
                <?php foreach ($plan['features'] as $feat): ?>
                <li><i data-lucide="check"></i><?php echo htmlspecialchars($feat); ?></li>
                <?php endforeach; ?>
            </ul>
            <a href="register.php?plan=<?php echo urlencode($plan['id'] ?? strtolower($plan['name'])); ?>" class="sl-price-btn<?php echo $plan['featured'] ? ' sl-price-btn-fill' : ''; ?>">Choose Plan</a>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- INTEGRATIONS -->
<section id="integrations" class="sl-integrations">
    <div class="sl-section-inner sl-integrations-inner reveal">
        <h2>Easily connects with the tools you already use</h2>
        <p>Plug into your existing workflow — payments, messaging, storage, and more.</p>
        <div class="sl-orbit-wrap">
            <div class="sl-orbit-center">
                <?php if ($publicLogoUrl): ?>
                    <img src="<?php echo htmlspecialchars($publicLogoUrl); ?>" alt="">
                <?php else: ?>
                    <i data-lucide="building-2"></i>
                <?php endif; ?>
            </div>
            <?php
            $orbitIcons = ['credit-card','mail','message-circle','cloud','smartphone','printer','bar-chart-2','shield'];
            foreach ($orbitIcons as $j => $icon):
                $angle = $j * 45;
            ?>
            <div class="sl-orbit-item" style="--angle: <?php echo $angle; ?>deg">
                <span><i data-lucide="<?php echo $icon; ?>"></i></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- NEWSLETTER -->
<section class="sl-newsletter">
    <div class="sl-section-inner reveal">
        <div class="sl-newsletter-box">
            <div class="sl-newsletter-text">
                <h3>Stay updated with <?php echo htmlspecialchars($appName); ?></h3>
                <p>Get product updates, hotel operations tips, and new feature announcements.</p>
            </div>
            <form class="sl-newsletter-form" onsubmit="event.preventDefault(); alert('Thanks for subscribing!');">
                <input type="email" placeholder="Enter your email" required>
                <button type="submit" class="sl-btn-green">Subscribe Now</button>
            </form>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="sl-footer">
    <div class="sl-section-inner sl-footer-grid">
        <div class="sl-footer-brand">
            <div class="sl-footer-logo">
                <?php if ($publicLogoUrl): ?>
                    <img src="<?php echo htmlspecialchars($publicLogoUrl); ?>" alt="">
                <?php else: ?>
                    <i data-lucide="building-2"></i>
                <?php endif; ?>
                <span><?php echo htmlspecialchars($appName); ?></span>
            </div>
            <p>All-in-one hotel management platform for modern hospitality businesses.</p>
        </div>
        <div class="sl-footer-col">
            <h4>Company</h4>
            <a href="#features">Features</a>
            <a href="#pricing">Pricing</a>
            <a href="#pricing">Register</a>
        </div>
        <div class="sl-footer-col">
            <h4>Help</h4>
            <a href="login.php">Sign In</a>
            <a href="super-admin-login.php">Platform Admin</a>
            <a href="#integrations">Integrations</a>
        </div>
        <div class="sl-footer-col">
            <h4>Resources</h4>
            <a href="#pricing">Plans</a>
            <a href="#pricing">Free Trial</a>
            <a href="#features">Modules</a>
        </div>
    </div>
    <div class="sl-section-inner sl-footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($appName); ?>. All rights reserved.</p>
    </div>
</footer>

</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800;900&display=swap');

#saas-landing, #saas-landing * { box-sizing: border-box; }
#saas-landing {
    --green: #1d6b4a;
    --green-dark: #145239;
    --green-light: #e8f5ef;
    --green-soft: #f0faf5;
    --text: #0f1f1a;
    --muted: #3d5249;
    --border: #e2ebe6;
    --white: #ffffff;
    --shadow: 0 4px 24px rgba(26, 46, 40, 0.08);
    --shadow-lg: 0 20px 50px rgba(26, 46, 40, 0.12);
    font-family: 'Inter', sans-serif;
    font-size: 17px;
    font-weight: 500;
    line-height: 1.65;
    background: var(--white);
    color: var(--text);
    overflow-x: hidden;
    -webkit-font-smoothing: antialiased;
}
body, main, main > div { flex-direction: column !important; width: 100% !important; }
html { scroll-behavior: smooth; }
.sl-section-inner { max-width: 1140px; margin: 0 auto; padding: 0 1.5rem; }

.reveal { opacity: 0; transform: translateY(20px); animation: slUp 0.7s ease forwards; }
.reveal-d1 { animation-delay: 0.12s; }
.reveal-d2 { animation-delay: 0.22s; }
.reveal-d3 { animation-delay: 0.32s; }
@keyframes slUp { to { opacity: 1; transform: none; } }

.sl-btn-green {
    display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
    background: var(--green); color: #fff; border: none;
    padding: 0.7rem 1.5rem; border-radius: 999px;
    font-weight: 700; font-size: 1rem; text-decoration: none;
    transition: background 0.2s, transform 0.2s, box-shadow 0.2s;
    box-shadow: 0 4px 14px rgba(29, 107, 74, 0.35);
    cursor: pointer;
}
.sl-btn-green:hover { background: var(--green-dark); transform: translateY(-1px); }
.sl-btn-green.sl-btn-lg { padding: 0.9rem 1.75rem; font-size: 1.0625rem; font-weight: 800; }

.sl-btn-outline {
    display: inline-flex; align-items: center;
    padding: 0.65rem 1.25rem; border-radius: 999px;
    border: 1.5px solid var(--border); color: var(--text);
    font-weight: 700; font-size: 0.9375rem; text-decoration: none;
    transition: border-color 0.2s, color 0.2s;
}
.sl-btn-outline:hover { border-color: var(--green); color: var(--green); }

/* NAV */
.sl-nav {
    position: fixed; inset: 0 0 auto 0; z-index: 100;
    background: rgba(255,255,255,0.92); backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
}
.sl-nav-inner {
    max-width: 1140px; margin: 0 auto; padding: 0 1.5rem; height: 72px;
    display: flex; align-items: center; gap: 2rem;
}
.sl-brand { display: flex; align-items: center; gap: 0.6rem; text-decoration: none; color: var(--text); }
.sl-brand-logo { width: 36px; height: 36px; border-radius: 10px; overflow: hidden; }
.sl-brand-logo img { width: 100%; height: 100%; object-fit: cover; }
.sl-brand-icon {
    width: 36px; height: 36px; border-radius: 10px; background: var(--green-light);
    display: grid; place-items: center; color: var(--green);
}
.sl-brand-icon i { width: 20px; height: 20px; }
.sl-brand-name { font-weight: 900; font-size: 1.15rem; }
.sl-desktop-nav { display: flex; gap: 0.25rem; margin-left: auto; }
.sl-desktop-nav a {
    padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none;
    color: var(--muted); font-weight: 600; font-size: 0.9375rem;
    transition: color 0.2s, background 0.2s;
}
.sl-desktop-nav a:hover { color: var(--green); background: var(--green-soft); }
.sl-nav-ctas { display: flex; align-items: center; gap: 0.65rem; }
.sl-mobile-toggle {
    display: none; width: 40px; height: 40px; border-radius: 10px;
    border: 1px solid var(--border); background: #fff; cursor: pointer;
    align-items: center; justify-content: center;
}
.sl-mobile-nav {
    display: none; position: fixed; inset: 0; z-index: 200;
    background: #fff; flex-direction: column;
}
.sl-mobile-nav.open { display: flex; }
.sl-mobile-nav-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0 1.5rem; height: 72px; border-bottom: 1px solid var(--border);
    font-weight: 700;
}
.sl-mobile-nav-header button {
    width: 40px; height: 40px; border-radius: 10px; border: 1px solid var(--border);
    background: #fff; cursor: pointer; display: grid; place-items: center;
}
.sl-mobile-links { padding: 1.5rem; display: flex; flex-direction: column; gap: 0.25rem; }
.sl-mobile-link {
    padding: 1rem; border-radius: 12px; text-decoration: none;
    font-weight: 700; font-size: 1rem; color: var(--text);
}
.sl-mobile-cta { background: var(--green) !important; color: #fff !important; text-align: center; margin-top: 0.5rem; }

/* HERO */
.sl-hero {
    padding: 7rem 0 4rem;
    background: linear-gradient(180deg, var(--green-soft) 0%, #fff 55%);
}
.sl-hero-inner {
    max-width: 1140px; margin: 0 auto; padding: 0 1.5rem;
    display: grid; grid-template-columns: 1fr 1.15fr; gap: 3rem; align-items: center;
}
.sl-hero-text h1 {
    font-size: clamp(2.25rem, 4.5vw, 3.5rem); font-weight: 900;
    line-height: 1.12; letter-spacing: -0.03em; margin: 0 0 1.25rem;
}
.sl-hero-text p { font-size: 1.125rem; font-weight: 500; line-height: 1.7; color: var(--muted); margin: 0 0 2rem; max-width: 480px; }

/* Admin dashboard preview (matches admin.php) */
.sl-admin-preview {
    background: #fff;
    border-radius: 16px;
    box-shadow: var(--shadow-lg);
    border: 1px solid var(--border);
    overflow: hidden;
    min-height: 380px;
}

.sl-preview-nav {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    height: 52px;
    padding: 0 0.85rem;
    background: #fff;
    border-bottom: 1px solid var(--border);
}

.sl-preview-brand {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-shrink: 0;
}

.sl-preview-logo {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid var(--border);
    background: var(--green-light);
    display: grid;
    place-items: center;
}

.sl-preview-logo img { width: 100%; height: 100%; object-fit: cover; }

.sl-preview-logo-fallback {
    background: var(--green);
    color: #fff;
    font-size: 0.55rem;
    font-weight: 800;
    letter-spacing: 0.04em;
}

.sl-preview-brand strong {
    display: block;
    font-size: 0.72rem;
    font-weight: 800;
    color: var(--text);
    line-height: 1.1;
}

.sl-preview-brand span {
    display: block;
    font-size: 0.5rem;
    font-weight: 700;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-top: 1px;
}

.sl-preview-nav-links {
    display: flex;
    align-items: center;
    gap: 0.15rem;
    flex: 1;
    min-width: 0;
    overflow: hidden;
}

.sl-preview-nav-link {
    padding: 0.3rem 0.55rem;
    border-radius: 6px;
    font-size: 0.62rem;
    font-weight: 600;
    color: var(--muted);
    white-space: nowrap;
}

.sl-preview-nav-link.active {
    background: var(--green-light);
    color: var(--green);
    font-weight: 700;
}

.sl-preview-nav-right {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    flex-shrink: 0;
}

.sl-preview-greeting {
    font-size: 0.58rem;
    font-weight: 600;
    color: var(--muted);
}

.sl-preview-logout {
    font-size: 0.58rem;
    font-weight: 700;
    color: var(--green);
    background: var(--green-soft);
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: 0.25rem 0.45rem;
}

.sl-preview-body {
    background: #f7faf8;
    padding: 0.85rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}

.sl-preview-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 0.7rem 0.85rem;
    box-shadow: var(--shadow);
}

.sl-preview-header-left {
    display: flex;
    align-items: center;
    gap: 0.55rem;
    min-width: 0;
}

.sl-preview-header-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: #f0f5f2;
    border: 1px solid var(--border);
    display: grid;
    place-items: center;
    color: var(--green);
    flex-shrink: 0;
}

.sl-preview-header-icon i { width: 16px; height: 16px; }

.sl-preview-header h3 {
    margin: 0;
    font-size: 0.82rem;
    font-weight: 800;
    color: var(--text);
    line-height: 1.2;
}

.sl-preview-header p {
    margin: 0.15rem 0 0;
    font-size: 0.58rem;
    font-weight: 600;
    color: var(--muted);
}

.sl-preview-refresh {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    background: var(--green-soft);
    border: 1px solid var(--border);
    display: grid;
    place-items: center;
    color: var(--muted);
    flex-shrink: 0;
}

.sl-preview-refresh i { width: 13px; height: 13px; }

.sl-preview-metrics {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
}

.sl-preview-metric {
    background: #f0f5f2;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 0.55rem 0.6rem;
}

.sl-preview-metric-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.25rem;
    margin-bottom: 0.35rem;
}

.sl-preview-metric-top span {
    font-size: 0.52rem;
    font-weight: 600;
    color: var(--muted);
    line-height: 1.3;
}

.sl-preview-metric-icon {
    width: 22px;
    height: 22px;
    border-radius: 6px;
    background: #fff;
    border: 1px solid var(--border);
    display: grid;
    place-items: center;
    color: var(--green);
    flex-shrink: 0;
}

.sl-preview-metric-icon i { width: 11px; height: 11px; }

.sl-preview-metric strong {
    display: block;
    font-size: 0.9rem;
    font-weight: 800;
    color: var(--text);
    line-height: 1;
}

.sl-preview-actions {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
}

.sl-preview-action {
    background: #f0f5f2;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 0.6rem 0.65rem;
}

.sl-preview-action-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.45rem;
}

.sl-preview-action-top i { width: 14px; height: 14px; color: var(--green); }
.sl-preview-action-arrow { color: var(--muted) !important; width: 11px !important; height: 11px !important; }

.sl-preview-action h4 {
    margin: 0 0 0.2rem;
    font-size: 0.68rem;
    font-weight: 800;
    color: var(--text);
}

.sl-preview-action p {
    margin: 0;
    font-size: 0.52rem;
    font-weight: 500;
    color: var(--muted);
    line-height: 1.4;
}

/* FEATURE ROWS */
.sl-feature-row { padding: 5rem 0; }
.sl-feature-row-alt { background: var(--green-soft); }
.sl-feature-split { display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; align-items: center; }
.sl-feature-split-reverse .sl-feature-copy { order: 2; }
.sl-feature-split-reverse .sl-feature-visual { order: 1; }
.sl-eyebrow { display: block; font-size: 0.875rem; font-weight: 800; color: var(--green); margin-bottom: 0.75rem; letter-spacing: 0.04em; text-transform: uppercase; }
.sl-feature-copy h2 { font-size: clamp(1.875rem, 3vw, 2.375rem); font-weight: 900; line-height: 1.2; margin: 0 0 1rem; letter-spacing: -0.02em; }
.sl-feature-copy p { color: var(--muted); font-size: 1.0625rem; font-weight: 500; line-height: 1.75; margin: 0 0 1.5rem; }
.sl-link-arrow { display: inline-flex; align-items: center; gap: 0.4rem; color: var(--green); font-weight: 800; font-size: 1rem; text-decoration: none; }
.sl-link-arrow i { width: 16px; height: 16px; }
.sl-mini-card {
    background: #fff; border-radius: 16px; padding: 1.75rem;
    box-shadow: var(--shadow); border: 1px solid var(--border);
}
.sl-mini-title { font-weight: 800; margin: 0 0 1.25rem; font-size: 1.05rem; }
.sl-mini-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.sl-mini-stat strong { display: block; font-size: 1.75rem; color: var(--green); font-weight: 800; }
.sl-mini-stat span { font-size: 0.875rem; font-weight: 600; color: var(--muted); }
.sl-chart-line { width: 100%; height: 60px; margin-bottom: 0.75rem; }
.sl-chart-legend { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; font-weight: 600; color: var(--muted); }
.sl-chart-legend span { width: 12px; height: 3px; background: var(--green); border-radius: 2px; }

/* PRICING */
.sl-pricing { padding: 5rem 0 6rem; background: #fff; }
.sl-pricing-head { text-align: center; margin-bottom: 3rem; }
.sl-pricing-head h2 { font-size: clamp(1.875rem, 3.5vw, 2.625rem); font-weight: 900; margin: 0 0 0.75rem; letter-spacing: -0.02em; }
.sl-pricing-head p { color: var(--muted); font-size: 1.125rem; font-weight: 500; margin: 0; }
.sl-pricing-grid {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; align-items: start;
}
.sl-price-card {
    background: #fff; border: 1.5px solid var(--border); border-radius: 16px;
    padding: 2rem 1.75rem; display: flex; flex-direction: column;
    transition: box-shadow 0.25s, transform 0.25s;
}
.sl-price-card:hover { box-shadow: var(--shadow); transform: translateY(-4px); }
.sl-price-featured {
    border-color: var(--green); box-shadow: var(--shadow-lg);
    transform: scale(1.03);
}
.sl-price-featured:hover { transform: scale(1.03) translateY(-4px); }
.sl-price-top { margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border); }
.sl-price-top h3 { font-size: 1.15rem; font-weight: 800; margin: 0 0 1rem; color: var(--muted); }
.sl-price-amount { display: flex; align-items: baseline; gap: 0.15rem; }
.sl-price-currency { font-size: 1.5rem; font-weight: 700; color: var(--text); }
.sl-price-num { font-size: 3.5rem; font-weight: 800; line-height: 1; letter-spacing: -0.04em; }
.sl-price-period { font-size: 1.0625rem; font-weight: 600; color: var(--muted); }
.sl-price-features { list-style: none; margin: 0 0 2rem; padding: 0; flex: 1; display: flex; flex-direction: column; gap: 0.85rem; }
.sl-price-features li { display: flex; align-items: flex-start; gap: 0.65rem; font-size: 1rem; font-weight: 500; color: var(--muted); line-height: 1.5; }
.sl-price-features i { width: 18px; height: 18px; color: var(--green); flex-shrink: 0; margin-top: 2px; }
.sl-price-btn {
    display: block; text-align: center; padding: 0.85rem 1rem;
    border-radius: 999px; border: 1.5px solid var(--border);
    font-weight: 800; font-size: 1rem; text-decoration: none; color: var(--text);
    transition: border-color 0.2s, background 0.2s, color 0.2s;
}
.sl-price-btn:hover { border-color: var(--green); color: var(--green); }
.sl-price-btn-fill { background: var(--green); border-color: var(--green); color: #fff; }
.sl-price-btn-fill:hover { background: var(--green-dark); border-color: var(--green-dark); color: #fff; }

/* INTEGRATIONS */
.sl-integrations {
    padding: 5rem 0; background: linear-gradient(135deg, #e8f5ef 0%, #d4ede0 100%);
}
.sl-integrations-inner { text-align: center; }
.sl-integrations h2 { font-size: clamp(1.625rem, 3vw, 2.375rem); font-weight: 900; margin: 0 0 0.75rem; }
.sl-integrations p { color: var(--muted); font-size: 1.0625rem; font-weight: 500; margin: 0 0 3rem; }
.sl-orbit-wrap {
    position: relative; width: min(420px, 90vw); height: min(420px, 90vw);
    margin: 0 auto;
}
.sl-orbit-center {
    position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
    width: 80px; height: 80px; border-radius: 50%; background: #fff;
    box-shadow: var(--shadow-lg); display: grid; place-items: center;
    color: var(--green); z-index: 2;
}
.sl-orbit-center img { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; }
.sl-orbit-center i { width: 36px; height: 36px; }
.sl-orbit-item {
    position: absolute; top: 50%; left: 50%;
    width: 52px; height: 52px; margin: -26px 0 0 -26px;
    transform: rotate(var(--angle)) translateY(-165px) rotate(calc(-1 * var(--angle)));
}
.sl-orbit-item span {
    display: grid; place-items: center; width: 52px; height: 52px;
    background: #fff; border-radius: 50%; box-shadow: var(--shadow);
    color: var(--green);
}
.sl-orbit-item i { width: 22px; height: 22px; }

/* NEWSLETTER */
.sl-newsletter { padding: 3rem 0 5rem; }
.sl-newsletter-box {
    background: linear-gradient(135deg, var(--green) 0%, #2d8a5e 100%);
    border-radius: 20px; padding: 2.5rem 3rem;
    display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 2rem;
    color: #fff; position: relative; overflow: hidden;
}
.sl-newsletter-box::before {
    content: ''; position: absolute; inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.06'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    opacity: 0.5; pointer-events: none;
}
.sl-newsletter-text { position: relative; z-index: 1; max-width: 400px; }
.sl-newsletter-text h3 { font-size: 1.5rem; font-weight: 900; margin: 0 0 0.5rem; }
.sl-newsletter-text p { margin: 0; opacity: 0.95; font-size: 1.0625rem; font-weight: 500; line-height: 1.6; }
.sl-newsletter-form { position: relative; z-index: 1; display: flex; gap: 0.75rem; flex-wrap: wrap; }
.sl-newsletter-form input {
    padding: 0.85rem 1.25rem; border-radius: 999px; border: none;
    min-width: 240px; font-size: 1rem; font-weight: 500; outline: none;
}
.sl-newsletter-form .sl-btn-green { background: #fff; color: var(--green); box-shadow: none; }
.sl-newsletter-form .sl-btn-green:hover { background: var(--green-soft); }

/* FOOTER */
.sl-footer { background: #0f1a16; color: rgba(255,255,255,0.7); padding: 4rem 0 0; }
.sl-footer-grid {
    display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr; gap: 2.5rem;
    padding-bottom: 3rem; border-bottom: 1px solid rgba(255,255,255,0.08);
}
.sl-footer-logo { display: flex; align-items: center; gap: 0.6rem; color: #fff; font-weight: 900; font-size: 1.05rem; margin-bottom: 1rem; }
.sl-footer-logo img { width: 32px; height: 32px; border-radius: 8px; }
.sl-footer-logo i { width: 24px; height: 24px; color: var(--green-light); }
.sl-footer-brand p { font-size: 0.9375rem; font-weight: 500; line-height: 1.7; margin: 0; max-width: 280px; }
.sl-footer-col h4 { color: #fff; font-size: 1rem; font-weight: 800; margin: 0 0 1rem; }
.sl-footer-col a {
    display: block; color: rgba(255,255,255,0.7); text-decoration: none;
    font-size: 0.9375rem; font-weight: 500; padding: 0.3rem 0; transition: color 0.2s;
}
.sl-footer-col a:hover { color: #fff; }
.sl-footer-bottom { padding: 1.5rem 0; font-size: 0.875rem; font-weight: 500; color: rgba(255,255,255,0.55); }

@media (max-width: 1023px) {
    .sl-hero-inner, .sl-feature-split { grid-template-columns: 1fr; }
    .sl-feature-split-reverse .sl-feature-copy, .sl-feature-split-reverse .sl-feature-visual { order: unset; }
    .sl-pricing-grid { grid-template-columns: 1fr; max-width: 400px; margin: 0 auto; }
    .sl-price-featured { transform: none; }
    .sl-footer-grid { grid-template-columns: 1fr 1fr; }
    .sl-preview-metrics { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 767px) {
    .sl-desktop-nav, .sl-nav-ctas .sl-btn-outline, .sl-nav-ctas .sl-btn-green { display: none; }
    .sl-mobile-toggle { display: inline-flex; }
    .sl-preview-nav-links, .sl-preview-greeting { display: none; }
    .sl-preview-metrics { grid-template-columns: repeat(2, 1fr); }
    .sl-preview-actions { grid-template-columns: 1fr; }
    .sl-footer-grid { grid-template-columns: 1fr; }
    .sl-newsletter-box { padding: 2rem 1.5rem; }
    .sl-newsletter-form { width: 100%; }
    .sl-newsletter-form input { flex: 1; min-width: 0; }
}
</style>

<script>
(function () {
    const btn = document.getElementById('slMobileMenuBtn');
    const nav = document.getElementById('slMobileNav');
    const close = document.getElementById('slMobileNavClose');
    btn?.addEventListener('click', () => { nav?.classList.add('open'); document.body.style.overflow = 'hidden'; });
    close?.addEventListener('click', () => { nav?.classList.remove('open'); document.body.style.overflow = ''; });
    nav?.querySelectorAll('.sl-mobile-link').forEach(l => l.addEventListener('click', () => { nav.classList.remove('open'); document.body.style.overflow = ''; }));

    if ('IntersectionObserver' in window) {
        const io = new IntersectionObserver((entries) => {
            entries.forEach(e => { if (e.isIntersecting) { e.target.style.animationPlayState = 'running'; io.unobserve(e.target); } });
        }, { threshold: 0.1 });
        document.querySelectorAll('#saas-landing .reveal').forEach(el => {
            el.style.animationPlayState = 'paused';
            io.observe(el);
        });
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();
})();
</script>

<?php renderFooter(); ?>
