<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'About 50OFF';
$totalDeals = countDeals([]);
include 'includes/header.php';
?>
<div class="container" style="max-width:760px;padding-top:2rem;padding-bottom:4rem">

    <div style="text-align:center;padding:2.5rem 1rem 2rem">
        <div style="font-size:4rem;margin-bottom:1rem">🏷️</div>
        <h1 style="font-size:2.25rem;font-weight:900;margin-bottom:.75rem">About 50OFF</h1>
        <p style="font-size:1.1rem;color:var(--slate);max-width:520px;margin:0 auto;line-height:1.65">
            We built 50OFF for one reason: <strong style="color:var(--ink)">you shouldn't have to hunt for discounts</strong>. The deals should come to you.
        </p>
    </div>

    <div class="about-stats-row">
        <div class="about-stat">
            <span class="about-stat-num"><?= number_format($totalDeals) ?>+</span>
            <span class="about-stat-label">Live Deals</span>
        </div>
        <div class="about-stat">
            <span class="about-stat-num">50%+</span>
            <span class="about-stat-label">Minimum Discount</span>
        </div>
        <div class="about-stat">
            <span class="about-stat-num">3</span>
            <span class="about-stat-label">Major Retailers</span>
        </div>
        <div class="about-stat">
            <span class="about-stat-num">3h</span>
            <span class="about-stat-label">Update Frequency</span>
        </div>
    </div>

    <div class="legal-body" style="margin-top:2.5rem">

    <h2>How It Works</h2>
    <p>Every 3 hours, our scrapers pull the latest deals from Amazon, Target, and eBay. Each deal is automatically validated — we only list products that are <strong>genuinely 50% off or more</strong>, with a verified original price. No fake "was" prices. No gimmicks.</p>

    <h2>Why 50%?</h2>
    <p>We chose 50% as our floor because anything less isn't a real deal — it's just marketing. We want every deal on this site to make you stop and think "wait, really?" That's the bar we hold ourselves to.</p>

    <h2>What We Sell</h2>
    <p>Nothing. We don't sell products. We don't process payments. We find the deals, you buy directly from the retailer. We may earn an affiliate commission when you click through — that's how we keep the site free and uncluttered.</p>

    <h2>Our Retailers</h2>
    <ul>
        <li><strong>Amazon</strong> — Electronics, clothing, kitchen, toys, sports, and more.</li>
        <li><strong>Target</strong> — Home, clothing, beauty, and seasonal deals.</li>
        <li><strong>eBay</strong> — Clearance, open-box, and brand-new discounted items.</li>
    </ul>

    <h2>Built for Mobile</h2>
    <p>Most deal-hunting happens on your phone, in a spare moment. 50OFF is designed mobile-first — fast, clean, and scroll-friendly. Save deals to your wishlist and come back to them later.</p>

    <h2>Questions or Feedback?</h2>
    <p>We'd love to hear from you. Email <strong>hello@50offsale.com</strong> — we read everything.</p>

    </div>

    <div style="text-align:center;margin-top:3rem">
        <a href="/" class="deal-btn" style="display:inline-flex">Browse All Deals →</a>
    </div>
</div>

<style>
.about-stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin: 1.5rem 0;
    text-align: center;
}
.about-stat {
    background: var(--ash);
    border: 1.5px solid var(--border);
    border-radius: var(--radius);
    padding: 1.25rem .75rem;
}
.about-stat-num { display:block; font-size:1.75rem; font-weight:900; color:var(--orange); }
.about-stat-label { display:block; font-size:.75rem; font-weight:600; color:var(--slate); margin-top:.2rem; }
.legal-body h2 { font-size:1.1rem; font-weight:800; margin:1.75rem 0 .5rem; color:var(--ink); }
.legal-body p, .legal-body li { font-size:.92rem; line-height:1.75; color:#374151; margin-bottom:.75rem; }
.legal-body ul { padding-left:1.5rem; }
.legal-body li { margin-bottom:.4rem; }
@media (max-width:480px) { .about-stats-row { grid-template-columns: repeat(2, 1fr); } }
</style>

<?php include 'includes/footer.php'; ?>
