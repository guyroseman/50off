<?php // includes/footer.php ?>
</main><!-- /.site-main -->

<footer class="site-footer">
    <div class="container footer-inner">

        <!-- Brand: logo + disclaimer -->
        <div class="footer-brand">
            <a href="/" class="logo" style="margin-bottom:.75rem">
                <div class="logo-icon">50</div>
                <div class="logo-text-wrap">
                    <span class="logo-name" style="color:#fff">50<span class="logo-off">OFF</span></span>
                    <span class="logo-tag">Find deals, not products</span>
                </div>
            </a>
            <p>Only deals with <strong style="color:#fff">50% off or more</strong>.<br>
            We scrape Amazon, Target, eBay &amp; top deal sites every 3 hours.</p>
            <p class="disclaimer">As an Amazon Associate and affiliate partner, we earn from qualifying purchases at no extra cost to you. Prices verified at time of posting.</p>
        </div>

        <!-- Desktop: 3 link columns individually. Mobile: wrapped in .footer-links-row -->
        <div class="footer-links-row">
            <div class="footer-links">
                <h4>Stores</h4>
                <ul>
                    <li><a href="/?store=amazon">🛒 Amazon</a></li>
                    <li><a href="/?store=target">🎯 Target</a></li>
                    <li><a href="/?store=ebay">🔴 eBay</a></li>
                    <li><a href="/?store=walmart">🔵 Walmart</a></li>
                    <li><a href="/?store=bestbuy">🟡 Best Buy</a></li>
                    <li><a href="/?store=costco">⭕ Costco</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h4>Categories</h4>
                <ul>
                    <li><a href="/?category=electronics">📱 Electronics</a></li>
                    <li><a href="/?category=kitchen">🍳 Kitchen</a></li>
                    <li><a href="/?category=clothing">👗 Clothing</a></li>
                    <li><a href="/?category=home">🏠 Home</a></li>
                    <li><a href="/?category=toys">🧸 Toys</a></li>
                    <li><a href="/?category=sports">⚽ Sports</a></li>
                    <li><a href="/?category=beauty">💄 Beauty</a></li>
                    <li><a href="/?category=clearance">🏷️ Clearance</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h4>Blog</h4>
                <ul>
                    <li><a href="/blog/">📝 All Posts</a></li>
                    <li><a href="/blog/?cat=roundup">🔥 Roundups</a></li>
                    <li><a href="/blog/?cat=guide">📖 Guides</a></li>
                    <li><a href="/blog/how-50off-works">How it Works</a></li>
                </ul>
            </div>
        </div><!-- /.footer-links-row -->

        <!-- Info links: full-width on mobile as inline row -->
        <div class="footer-links footer-links-info">
            <h4>Info</h4>
            <ul>
                <li><a href="/about.php">About 50OFF</a></li>
                <li><a href="/privacy.php">Privacy Policy</a></li>
                <li><a href="/terms.php">Terms of Use</a></li>
                <li><a href="/sitemap.xml">Sitemap</a></li>
                <li><a href="/admin/">Admin</a></li>
            </ul>
        </div>

    </div><!-- /.footer-inner -->

    <div class="footer-bottom container">
        <p>© <?= date('Y') ?> 50OFF — All prices subject to change. Verified deals from top US retailers.</p>
    </div>
</footer>

<script src="/assets/js/main.js"></script>

<!-- Mobile Bottom Nav -->
<?php
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$isBlogPage   = str_starts_with($uri, '/blog');
$isSearchPage = str_starts_with($uri, '/search');
$isHomePage   = (bool)preg_match('/^\/(\?.*)?$/', $uri);
$isCatPage    = !empty($_GET['category']);
?>
<nav class="mobile-bottom-nav" aria-label="Mobile navigation">
    <div class="mobile-bottom-nav-inner">

        <a href="/" class="mobile-nav-item <?= $isHomePage && !$isCatPage ? 'active' : '' ?>" aria-label="Home">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Home
        </a>

        <a href="/search.php" class="mobile-nav-item <?= $isSearchPage ? 'active' : '' ?>" aria-label="Search">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            Search
        </a>

        <button class="mobile-nav-item <?= $isCatPage ? 'active' : '' ?>" onclick="openDrawer()" aria-label="Browse categories">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            Browse
        </button>

        <button class="mobile-nav-item" onclick="openSavedPanel()" aria-label="Saved deals">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            <span class="mobile-nav-badge" id="mobile-saved-count"></span>
            Saved
        </button>

        <a href="/blog/" class="mobile-nav-item <?= $isBlogPage ? 'active' : '' ?>" aria-label="Blog">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            Blog
        </a>

    </div>
</nav>
</body>
</html>
