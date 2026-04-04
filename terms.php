<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Terms of Use';
include 'includes/header.php';
?>
<div class="container" style="max-width:760px;padding-top:2rem;padding-bottom:4rem">
    <h1 style="font-size:2rem;font-weight:900;margin-bottom:.5rem">Terms of Use</h1>
    <p style="color:var(--slate);margin-bottom:2rem">Last updated: <?= date('F j, Y') ?></p>

    <div class="legal-body">

    <h2>1. Acceptance</h2>
    <p>By using 50offsale.com ("the Site"), you agree to these Terms of Use. If you do not agree, please do not use the Site.</p>

    <h2>2. What We Do</h2>
    <p>50OFF aggregates publicly available deals from major US retailers. We do not fulfill orders, process payments, or hold inventory. All purchases are made directly with the retailer.</p>

    <h2>3. Price Accuracy</h2>
    <p>Prices and availability change frequently. We verify deals at the time of scraping but cannot guarantee prices are current when you visit. Always confirm the price on the retailer's site before purchasing. We are not responsible for price discrepancies.</p>

    <h2>4. Affiliate Disclosure</h2>
    <p>50OFF participates in affiliate programs including the Amazon Services LLC Associates Program. We may earn a commission when you click links and make purchases. This does not affect the price you pay.</p>

    <h2>5. No Warranties</h2>
    <p>The Site is provided "as is" without warranties of any kind. We do not guarantee that deals are accurate, available, or suitable for any particular purpose.</p>

    <h2>6. Limitation of Liability</h2>
    <p>To the maximum extent permitted by law, 50OFF and its operators shall not be liable for any indirect, incidental, or consequential damages arising from your use of the Site or any linked retailer.</p>

    <h2>7. Third-Party Links</h2>
    <p>Links on this site lead to third-party retailers. We are not responsible for the content, policies, or practices of those sites.</p>

    <h2>8. Prohibited Use</h2>
    <p>You may not use the Site to scrape, automate access, or reproduce deal data for commercial purposes without written permission.</p>

    <h2>9. Changes</h2>
    <p>We reserve the right to modify these terms at any time. Continued use of the Site constitutes acceptance of any changes.</p>

    <h2>10. Contact</h2>
    <p>Questions about these terms? Email <strong>legal@50offsale.com</strong></p>

    </div>
</div>

<style>
.legal-body h2 { font-size:1.1rem; font-weight:800; margin:1.75rem 0 .5rem; color:var(--ink); }
.legal-body p, .legal-body li { font-size:.92rem; line-height:1.75; color:#374151; margin-bottom:.75rem; }
.legal-body ul { padding-left:1.5rem; }
.legal-body li { margin-bottom:.4rem; }
</style>

<?php include 'includes/footer.php'; ?>
