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
            We scrape Amazon, Target, eBay, 6pm &amp; more every 3 hours.</p>
            <p class="disclaimer">As an Amazon Associate and affiliate partner, we earn from qualifying purchases at no extra cost to you. Prices verified at time of posting.</p>
        </div>

        <div class="footer-links-row">
            <div class="footer-links">
                <h4>Stores</h4>
                <ul>
                    <li><a href="/?store=amazon">🛒 Amazon</a></li>
                    <li><a href="/?store=target">🎯 Target</a></li>
                    <li><a href="/?store=ebay">🔴 eBay</a></li>
                    <li><a href="/?store=6pm">👠 6pm</a></li>
                    <li><a href="/?store=zappos">👟 Zappos</a></li>
                    <li><a href="/?store=walmart">🔵 Walmart</a></li>
                    <li><a href="/?store=bestbuy">🟡 Best Buy</a></li>
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
                </ul>
            </div>
            <div class="footer-links">
                <h4>Info</h4>
                <ul>
                    <li><a href="/blog/">📝 Blog</a></li>
                    <li><a href="/about.php">About 50OFF</a></li>
                    <li><a href="/privacy.php">Privacy Policy</a></li>
                    <li><a href="/terms.php">Terms of Use</a></li>
                    <li><a href="/sitemap.xml">Sitemap</a></li>
                </ul>
            </div>
        </div>

    </div>

    <div class="footer-bottom container">
        <p>© <?= date('Y') ?> 50OFF — All prices subject to change. Verified deals from top US retailers.</p>
    </div>
</footer>

<!-- ── Email capture modal (triggered after first save) ─────────────────────── -->
<div id="email-save-modal" aria-hidden="true">
    <div id="email-save-backdrop"></div>
    <div id="email-save-sheet">
        <button id="email-save-close" aria-label="Close">✕</button>
        <div id="email-save-icon">♥</div>
        <h2>Deal saved!</h2>
        <p>Enter your email to keep your list &amp; get alerts when prices drop.<br><strong>No password. Always free.</strong></p>
        <div id="email-save-preview"></div>
        <form id="email-save-form">
            <input type="email" id="email-save-input" placeholder="your@email.com" autocomplete="email" inputmode="email">
            <button type="submit" id="email-save-submit">Keep My List →</button>
        </form>
        <button id="email-save-skip">No thanks, save locally only</button>
        <p class="email-save-fine">We'll email you a link to your personal deals list. Unsubscribe anytime.</p>
    </div>
</div>

<script src="/assets/js/main.js"></script>

<!-- ── Email capture enhancement — runs AFTER main.js ──────────────────────── -->
<script>
(function() {
    const TOKEN_KEY = '50off_token';
    const EMAIL_KEY = '50off_email';
    let _pendingDealId = null;

    // ── Patch main.js toggleSave to trigger email modal on first save ─────────
    if (typeof window.toggleSave !== 'function' || typeof window.getSaved !== 'function') return;
    const _origToggleSave = window.toggleSave;
    window.toggleSave = function(btn, event) {
        const id      = btn.dataset.id;
        const saved   = getSaved(); // main.js function
        const wasNew  = !saved[id];

        _origToggleSave(btn, event); // run original (localStorage + panel + toast)

        if (wasNew && !localStorage.getItem(EMAIL_KEY)) {
            // First save, no email yet — prompt after short delay
            _pendingDealId = id;
            setTimeout(() => showEmailModal(btn.dataset), 700);
        } else if (wasNew && localStorage.getItem(EMAIL_KEY)) {
            // Already has email — silently sync to server
            syncToServer(localStorage.getItem(EMAIL_KEY), id);
        }
    };

    // ── Email modal ───────────────────────────────────────────────────────────
    const modal    = document.getElementById('email-save-modal');
    const backdrop = document.getElementById('email-save-backdrop');
    const closeBtn = document.getElementById('email-save-close');
    const skipBtn  = document.getElementById('email-save-skip');
    const form     = document.getElementById('email-save-form');

    function showEmailModal(dealData) {
        if (!modal) return;
        const preview = document.getElementById('email-save-preview');
        if (dealData && dealData.title && preview) {
            preview.innerHTML = `<div class="save-deal-chip">
                <img src="${dealData.img||''}" onerror="this.style.display='none'" alt="" width="48" height="48">
                <span><strong>${dealData.pct}% OFF</strong> — ${dealData.title.slice(0,52)}…</span>
            </div>`;
        }
        modal.style.display = 'block';
        requestAnimationFrame(() => modal.classList.add('open'));
        document.getElementById('email-save-input').focus();
        document.body.style.overflow = 'hidden';
    }

    function hideEmailModal() {
        modal?.classList.remove('open');
        setTimeout(() => { if (modal) modal.style.display = 'none'; }, 280);
        document.body.style.overflow = '';
    }

    backdrop?.addEventListener('click', hideEmailModal);
    closeBtn?.addEventListener('click', hideEmailModal);
    skipBtn?.addEventListener('click',  hideEmailModal);

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = document.getElementById('email-save-input').value.trim();
        if (!email || !_pendingDealId) return;
        const btn = document.getElementById('email-save-submit');
        btn.disabled = true; btn.textContent = 'Saving…';
        await syncToServer(email, _pendingDealId, true);
        btn.disabled = false; btn.textContent = 'Keep My List →';
    });

    async function syncToServer(email, dealId, fromForm = false) {
        try {
            const res  = await fetch('/api/save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, deal_id: parseInt(dealId) }),
            });
            const data = await res.json();
            if (!data.ok) return;
            localStorage.setItem(TOKEN_KEY, data.token);
            localStorage.setItem(EMAIL_KEY, email);
            if (fromForm) {
                hideEmailModal();
                const token = encodeURIComponent(data.token);
                showToast(`♥ List saved! <a href="/saved.php?token=${token}" style="color:#fff;text-decoration:underline">View it →</a>`, 'save', 5000);
            }
        } catch {}
    }

    // ── Sync saved count badge on mobile nav ──────────────────────────────────
    // main.js uses 'header-saved-count' and '50off_saved_v1' key
    // mobile-saved-count is in our bottom nav — keep it in sync
    const origUpdateCount = window.updateSavedCount;
    if (typeof origUpdateCount === 'function') {
        window.updateSavedCount = function() {
            origUpdateCount();
            const count = Object.keys(getSaved()).length;
            const badge = document.getElementById('mobile-saved-count');
            if (badge) {
                badge.textContent = count > 0 ? count : '';
                badge.style.display = count > 0 ? 'flex' : 'none';
            }
        };
    }
})();
</script>

<!-- Mobile Bottom Nav -->
<?php
$uri          = $_SERVER['REQUEST_URI'] ?? '/';
$isBlogPage   = str_starts_with($uri, '/blog');
$isSearchPage = str_starts_with($uri, '/search');
$isHomePage   = (bool)preg_match('/^\/(\?.*)?$/', $uri);
$isCatPage    = !empty($_GET['category']);
?>
<nav class="mobile-bottom-nav" aria-label="Mobile navigation">
    <div class="mobile-bottom-nav-inner">

        <a href="/" class="mobile-nav-item <?= $isHomePage && !$isCatPage ? 'active' : '' ?>" aria-label="Home">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Home
        </a>

        <a href="/search.php" class="mobile-nav-item <?= $isSearchPage ? 'active' : '' ?>" aria-label="Search">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            Search
        </a>

        <button class="mobile-nav-item <?= $isCatPage ? 'active' : '' ?>" onclick="openDrawer()" aria-label="Browse categories">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            Browse
        </button>

        <button class="mobile-nav-item" onclick="openSavedPanel()" aria-label="Saved deals">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            <span class="mobile-nav-badge" id="mobile-saved-count" style="display:none"></span>
            Saved
        </button>

        <a href="/blog/" class="mobile-nav-item <?= $isBlogPage ? 'active' : '' ?>" aria-label="Blog">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            Blog
        </a>

    </div>
</nav>
</body>
</html>
