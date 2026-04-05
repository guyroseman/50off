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
            We scrape Amazon, Target &amp; eBay every 3 hours.</p>
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

<!-- ── Save Deal Modal ──────────────────────────────────────────────────────── -->
<div id="save-modal" role="dialog" aria-modal="true" aria-labelledby="save-modal-title" style="display:none">
    <div id="save-modal-backdrop" onclick="closeSaveModal()"></div>
    <div id="save-modal-sheet">
        <button class="save-modal-close" onclick="closeSaveModal()" aria-label="Close">✕</button>
        <!-- Step 1: Email entry -->
        <div id="save-step-email">
            <div class="save-modal-icon">♡</div>
            <h2 id="save-modal-title">Save this deal</h2>
            <p>Enter your email to save deals &amp; get alerts when prices drop.<br><strong>No password. Always free.</strong></p>
            <div id="save-deal-preview"></div>
            <form id="save-email-form" onsubmit="submitSaveEmail(event)">
                <input
                    type="email"
                    id="save-email-input"
                    placeholder="your@email.com"
                    required
                    autocomplete="email"
                    inputmode="email"
                >
                <button type="submit" id="save-submit-btn">Save Deal</button>
            </form>
            <p class="save-modal-fine">We'll send you a link to your personal deals list. Unsubscribe anytime.</p>
        </div>
        <!-- Step 2: Success -->
        <div id="save-step-done" style="display:none">
            <div class="save-modal-icon">♥</div>
            <h2>Deal saved!</h2>
            <p id="save-done-msg">Check your email for a link to your personal deals list.</p>
            <a id="save-view-link" href="/saved.php" class="save-view-btn">View My Saved Deals</a>
            <button onclick="closeSaveModal()" class="save-modal-dismiss">Close</button>
        </div>
    </div>
</div>

<!-- ── Saved Panel (bottom drawer via mobile Saved nav) ──────────────────── -->
<div id="saved-panel" style="display:none">
    <div id="saved-panel-backdrop" onclick="closeSavedPanel()"></div>
    <div id="saved-panel-sheet">
        <div class="saved-panel-header">
            <h3>Saved Deals</h3>
            <button onclick="closeSavedPanel()" aria-label="Close">✕</button>
        </div>
        <div id="saved-panel-body">
            <p style="color:#6b7280;text-align:center;padding:2rem 1rem">
                Click ♡ on any deal to save it here.<br>Enter your email once to keep your list across devices.
            </p>
        </div>
        <div id="saved-panel-footer" style="display:none">
            <a id="saved-panel-link" href="/saved.php" class="save-view-btn" style="display:block;text-align:center">
                View Full List &amp; Get Email Alerts →
            </a>
        </div>
    </div>
</div>

<script>
// ── Save system state ─────────────────────────────────────────────────────────
const SAVE_KEY   = '50off_saves';     // localStorage: {dealId: true, ...}
const TOKEN_KEY  = '50off_token';     // localStorage: subscriber token
const EMAIL_KEY  = '50off_email';     // localStorage: subscriber email (display only)

let _pendingDealId   = null;
let _pendingDealData = null;

function getSavedMap()  { try { return JSON.parse(localStorage.getItem(SAVE_KEY) || '{}'); } catch { return {}; } }
function setSavedMap(m) { localStorage.setItem(SAVE_KEY, JSON.stringify(m)); }
function getToken()     { return localStorage.getItem(TOKEN_KEY) || ''; }
function getEmail()     { return localStorage.getItem(EMAIL_KEY) || ''; }

// ── Boot: restore heart states from localStorage ──────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const saved = getSavedMap();
    document.querySelectorAll('.deal-save-btn').forEach(btn => {
        if (saved[btn.dataset.id]) markSaved(btn, true);
    });
    updateSavedBadge();
});

// ── Heart button click ────────────────────────────────────────────────────────
function toggleSave(btn, e) {
    e.preventDefault();
    e.stopPropagation();
    const id   = btn.dataset.id;
    const saved = getSavedMap();

    if (saved[id]) {
        // Already saved — unsave immediately
        delete saved[id];
        setSavedMap(saved);
        markSaved(btn, false);
        updateSavedBadge();
        // If we have a token, also remove from server
        const token = getToken();
        if (token) {
            fetch(`/api/save.php?token=${encodeURIComponent(token)}&deal_id=${id}&action=remove`).catch(() => {});
        }
        return;
    }

    // Not saved yet — open modal if no email stored, else save directly
    _pendingDealId   = id;
    _pendingDealData = btn.dataset;
    const email = getEmail();
    if (email && getToken()) {
        doSave(email, id, btn);
    } else {
        openSaveModal(btn.dataset);
    }
}

function markSaved(btn, yes) {
    btn.textContent = yes ? '♥' : '♡';
    btn.classList.toggle('saved', yes);
    btn.setAttribute('aria-label', yes ? 'Unsave deal' : 'Save deal');
}

function updateSavedBadge() {
    const count = Object.keys(getSavedMap()).length;
    const badge = document.getElementById('mobile-saved-count');
    if (badge) { badge.textContent = count > 0 ? count : ''; badge.style.display = count > 0 ? '' : 'none'; }
}

// ── Save modal ────────────────────────────────────────────────────────────────
function openSaveModal(dealData) {
    const modal = document.getElementById('save-modal');
    document.getElementById('save-step-email').style.display = '';
    document.getElementById('save-step-done').style.display  = 'none';
    document.getElementById('save-email-input').value = getEmail();
    // Show deal preview
    const preview = document.getElementById('save-deal-preview');
    if (dealData && dealData.title) {
        preview.innerHTML = `<div class="save-deal-chip">
            <img src="${dealData.img || ''}" onerror="this.style.display='none'" alt="">
            <span><strong>${dealData.pct}% OFF</strong> — ${dealData.title.slice(0, 55)}…</span>
        </div>`;
    }
    modal.style.display = '';
    requestAnimationFrame(() => modal.classList.add('open'));
    document.getElementById('save-email-input').focus();
    document.body.style.overflow = 'hidden';
}

function closeSaveModal() {
    const modal = document.getElementById('save-modal');
    modal.classList.remove('open');
    setTimeout(() => { modal.style.display = 'none'; }, 280);
    document.body.style.overflow = '';
}

async function submitSaveEmail(e) {
    e.preventDefault();
    const email = document.getElementById('save-email-input').value.trim();
    if (!email || !_pendingDealId) return;
    const btn = document.getElementById('save-submit-btn');
    btn.disabled = true; btn.textContent = 'Saving…';

    await doSave(email, _pendingDealId, null, true);
    btn.disabled = false; btn.textContent = 'Save Deal';
}

async function doSave(email, dealId, heartBtn, fromModal = false) {
    try {
        const res  = await fetch('/api/save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, deal_id: parseInt(dealId) }),
        });
        const data = await res.json();
        if (!data.ok) { alert(data.error || 'Could not save deal.'); return; }

        // Persist token + email
        localStorage.setItem(TOKEN_KEY, data.token);
        localStorage.setItem(EMAIL_KEY, email);

        // Update saved map
        const saved = getSavedMap();
        saved[dealId] = true;
        setSavedMap(saved);
        updateSavedBadge();

        // Update all heart buttons for this deal on the page
        document.querySelectorAll(`.deal-save-btn[data-id="${dealId}"]`).forEach(b => markSaved(b, true));

        if (fromModal) {
            // Show success step
            const msg  = document.getElementById('save-done-msg');
            const link = document.getElementById('save-view-link');
            msg.textContent  = data.is_new
                ? 'Check your email for a link to your personal deals list.'
                : `Deal saved! You have ${data.saved_count} deal${data.saved_count !== 1 ? 's' : ''} saved.`;
            link.href = `/saved.php?token=${encodeURIComponent(data.token)}`;
            document.getElementById('save-step-email').style.display = 'none';
            document.getElementById('save-step-done').style.display  = '';
            setTimeout(closeSaveModal, 3500);
        }
    } catch (err) {
        console.error('Save error:', err);
        alert('Could not connect. Please try again.');
    }
}

// ── Saved panel (mobile Saved nav) ───────────────────────────────────────────
function openSavedPanel() {
    const saved = getSavedMap();
    const ids   = Object.keys(saved);
    const panel = document.getElementById('saved-panel');
    const body  = document.getElementById('saved-panel-body');
    const foot  = document.getElementById('saved-panel-footer');
    const link  = document.getElementById('saved-panel-link');
    const token = getToken();

    if (ids.length === 0) {
        body.innerHTML = '<p style="color:#6b7280;text-align:center;padding:2rem 1rem">No saved deals yet.<br>Tap ♡ on any deal to save it.</p>';
        foot.style.display = 'none';
    } else {
        body.innerHTML = `<p style="color:#6b7280;font-size:.85rem;padding:.5rem 1rem 0">${ids.length} saved deal${ids.length !== 1 ? 's' : ''}</p>`;
        foot.style.display = '';
        if (token) link.href = `/saved.php?token=${encodeURIComponent(token)}`;
    }

    panel.style.display = '';
    requestAnimationFrame(() => panel.classList.add('open'));
    document.body.style.overflow = 'hidden';
}

function closeSavedPanel() {
    const panel = document.getElementById('saved-panel');
    panel.classList.remove('open');
    setTimeout(() => { panel.style.display = 'none'; }, 280);
    document.body.style.overflow = '';
}
</script>

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
            <span class="mobile-nav-badge" id="mobile-saved-count"></span>
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
