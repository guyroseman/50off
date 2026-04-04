<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Privacy Policy';
include 'includes/header.php';
?>
<div class="container" style="max-width:760px;padding-top:2rem;padding-bottom:4rem">
    <h1 style="font-size:2rem;font-weight:900;margin-bottom:.5rem">Privacy Policy</h1>
    <p style="color:var(--slate);margin-bottom:2rem">Last updated: <?= date('F j, Y') ?></p>

    <div class="legal-body">

    <h2>1. Who We Are</h2>
    <p>50OFF (50offsale.com) is a deal aggregation website that curates discounts of 50% or more from major US retailers including Amazon, Target, and eBay. We do not sell products — we link to third-party retailers.</p>

    <h2>2. Information We Collect</h2>
    <p>We collect minimal data necessary to operate the site:</p>
    <ul>
        <li><strong>Usage data:</strong> Anonymous page views, deal clicks, and search queries. These are used to improve site performance and deal relevance.</li>
        <li><strong>Local storage:</strong> Saved/wishlisted deals are stored only in your browser's localStorage — never on our servers.</li>
        <li><strong>Server logs:</strong> Standard web server logs (IP addresses, browser type, pages visited) are retained for up to 30 days for security and debugging.</li>
    </ul>

    <h2>3. Cookies</h2>
    <p>We use no tracking cookies beyond what is required for basic site functionality. If you click through to a retailer (Amazon, Target, eBay), those sites have their own cookie policies which apply independently.</p>

    <h2>4. Affiliate Links</h2>
    <p>Many links on this site are affiliate links. When you click a link and make a purchase, we may earn a small commission from the retailer at no extra cost to you. This is how we keep the site free. We are a participant in the Amazon Services LLC Associates Program.</p>

    <h2>5. Third-Party Services</h2>
    <p>We may use third-party analytics tools (such as Google Analytics) to understand traffic patterns. These services may collect anonymized usage data per their own privacy policies.</p>

    <h2>6. Data Sharing</h2>
    <p>We do not sell, rent, or share your personal data with any third parties beyond what is described above.</p>

    <h2>7. Your Rights</h2>
    <p>Since we store virtually no personal data, there is little to request. If you have questions about data we may hold, contact us and we will respond within 30 days.</p>

    <h2>8. Children's Privacy</h2>
    <p>This site is not directed at children under 13. We do not knowingly collect information from children.</p>

    <h2>9. Changes to This Policy</h2>
    <p>We may update this policy occasionally. Changes will be reflected by updating the "Last updated" date above.</p>

    <h2>10. Contact</h2>
    <p>Questions? Email us at <strong>privacy@50offsale.com</strong></p>

    </div>
</div>

<style>
.legal-body h2 { font-size:1.1rem; font-weight:800; margin:1.75rem 0 .5rem; color:var(--ink); }
.legal-body p, .legal-body li { font-size:.92rem; line-height:1.75; color:#374151; margin-bottom:.75rem; }
.legal-body ul { padding-left:1.5rem; }
.legal-body li { margin-bottom:.4rem; }
</style>

<?php include 'includes/footer.php'; ?>
