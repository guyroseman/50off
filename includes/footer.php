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

<!-- ══ AI DEAL ASSISTANT CHAT WIDGET ════════════════════════════════════════ -->
<div id="chat-widget">
    <button id="chat-fab" onclick="toggleChat()" aria-label="Deal Assistant">
        <svg id="chat-fab-icon" width="24" height="24" fill="none" stroke="#fff" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <svg id="chat-fab-close" width="24" height="24" fill="none" stroke="#fff" stroke-width="2" viewBox="0 0 24 24" style="display:none"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <div id="chat-panel" style="display:none">
        <div id="chat-header">
            <span>🤖 Deal Assistant</span>
            <button onclick="toggleChat()" aria-label="Close">✕</button>
        </div>
        <div id="chat-messages">
            <div class="chat-msg chat-bot">
                Hey! 👋 I'm your 50OFF deal assistant. Ask me things like:<br>
                <em>"Show me shoe deals"</em> · <em>"Deals under $30"</em> · <em>"Best Amazon deals"</em>
            </div>
        </div>
        <div id="chat-suggestions">
            <button onclick="sendChat('Best deals right now')">🔥 Best deals</button>
            <button onclick="sendChat('Show me shoe deals')">👟 Shoes</button>
            <button onclick="sendChat('Cheapest electronics under $50')">📱 Electronics</button>
            <button onclick="sendChat('Kitchen deals')">🍳 Kitchen</button>
            <button onclick="sendChat('Amazon deals')">🛒 Amazon</button>
        </div>
        <form id="chat-form" onsubmit="handleChat(event)">
            <input type="text" id="chat-input" placeholder="Ask about deals…" autocomplete="off" maxlength="200">
            <button type="submit" id="chat-send">→</button>
        </form>
    </div>
</div>

<script src="/assets/js/main.js?v=<?= filemtime(__DIR__ . '/../assets/js/main.js') ?>"></script>

<!-- Chat widget JS -->
<script>
function toggleChat() {
    const panel = document.getElementById('chat-panel');
    const icon  = document.getElementById('chat-fab-icon');
    const close = document.getElementById('chat-fab-close');
    const open  = panel.style.display === 'none';
    panel.style.display = open ? 'flex' : 'none';
    icon.style.display  = open ? 'none' : '';
    close.style.display = open ? '' : 'none';
    if (open) document.getElementById('chat-input').focus();
}

function handleChat(e) {
    e.preventDefault();
    const input = document.getElementById('chat-input');
    const msg = input.value.trim();
    if (!msg) return;
    input.value = '';
    sendChat(msg);
}

// Safe escHtml fallback in case main.js hasn't loaded yet
function _esc(s) { return typeof escHtml === 'function' ? escHtml(s) : String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

async function sendChat(msg) {
    const messages = document.getElementById('chat-messages');
    if (!messages) return;
    const suggestions = document.getElementById('chat-suggestions');
    if (suggestions) suggestions.style.display = 'none';

    // Add user message
    const userDiv = document.createElement('div');
    userDiv.className = 'chat-msg chat-user';
    userDiv.textContent = msg;
    messages.appendChild(userDiv);
    messages.scrollTop = messages.scrollHeight;

    // Add typing indicator
    const typing = document.createElement('div');
    typing.className = 'chat-msg chat-bot chat-typing';
    typing.textContent = '...';
    messages.appendChild(typing);
    messages.scrollTop = messages.scrollHeight;

    try {
        const res = await fetch('/api/chat.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ message: msg }),
        });
        if (!res.ok && res.status !== 429) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        typing.remove();

        // Add bot reply — use textContent for safety, then add link replacements
        let reply = _esc(data.reply || data.error || 'Sorry, try again!');
        // Convert [123] deal references to clickable links
        reply = reply.replace(/\[(\d+)\]/g, '<a href="/deal.php?id=$1" style="color:var(--orange);font-weight:600">[View Deal]</a>');
        const botDiv = document.createElement('div');
        botDiv.className = 'chat-msg chat-bot';
        botDiv.innerHTML = reply;
        messages.appendChild(botDiv);

        // Add deal cards if any
        if (data.deals && data.deals.length > 0) {
            let html = '<div class="chat-deals">';
            data.deals.forEach(d => {
                html += `<a href="/deal.php?id=${_esc(d.id)}" class="chat-deal-card">
                    <span class="chat-deal-title">${_esc(String(d.title||'').slice(0,40))}…</span>
                    <span class="chat-deal-price">${_esc(d.price||'')} <small>${_esc(d.pct||'')} off</small></span>
                </a>`;
            });
            html += '</div>';
            const dealsDiv = document.createElement('div');
            dealsDiv.innerHTML = html;
            messages.appendChild(dealsDiv);
        }
    } catch(err) {
        typing.remove();
        const errDiv = document.createElement('div');
        errDiv.className = 'chat-msg chat-bot';
        errDiv.textContent = 'Something went wrong. Try again!';
        messages.appendChild(errDiv);
    }
    messages.scrollTop = messages.scrollHeight;
}
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

        <a href="<?= $_isLoggedIn ? '/account.php' : '/login.php' ?>" class="mobile-nav-item" aria-label="<?= $_isLoggedIn ? 'Account' : 'Log in' ?>">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <?= $_isLoggedIn ? 'Account' : 'Log In' ?>
        </a>

        <a href="/blog/" class="mobile-nav-item <?= $isBlogPage ? 'active' : '' ?>" aria-label="Blog">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            Blog
        </a>

    </div>
</nav>
</body>
</html>
