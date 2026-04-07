// 50OFF — main.js (clean server-based saves, mobile-optimized)

// ─── Helpers ──────────────────────────────────────────────────────────────────
function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function trackDealClick(dealId) {
    navigator.sendBeacon && navigator.sendBeacon('/api/track.php', JSON.stringify({ id: dealId }));
}

// ─── Mobile Drawer ─────────────────────────────────────────────────────────────
const drawer        = document.getElementById('mobile-drawer');
const drawerOverlay = document.getElementById('drawer-overlay');
const menuBtn       = document.getElementById('mobile-menu-btn');
const drawerClose   = document.getElementById('drawer-close');

function openDrawer() {
    drawer?.classList.add('open');
    drawerOverlay?.classList.add('open');
    document.body.style.overflow = 'hidden';
    menuBtn?.setAttribute('aria-expanded', 'true');
}
function closeDrawer() {
    drawer?.classList.remove('open');
    drawerOverlay?.classList.remove('open');
    document.body.style.overflow = '';
    menuBtn?.setAttribute('aria-expanded', 'false');
}

menuBtn?.addEventListener('click', openDrawer);
drawerClose?.addEventListener('click', closeDrawer);
drawerOverlay?.addEventListener('click', closeDrawer);

let drawerTouchStartX = 0;
drawer?.addEventListener('touchstart', e => { drawerTouchStartX = e.touches[0].clientX; }, { passive: true });
drawer?.addEventListener('touchend', e => {
    if (e.changedTouches[0].clientX - drawerTouchStartX > 60) closeDrawer();
}, { passive: true });

// ─── Save System (server-based) ──────────────────────────────────────────────
// window.__isLoggedIn and window.__savedIds are set by PHP in the page
let _savedSet = new Set((window.__savedIds || []).map(String));
let _pendingDealId = null; // Deal to save after successful login

// Event delegation: works for cards added later (infinite scroll)
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.deal-save-btn, .deal-detail-save-text');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    handleSaveClick(btn);
}, true); // Use capture so we beat the <a> link inside the card

// Backwards-compat: any inline onclick="toggleSave(this, event)" still works
function toggleSave(btn, event) {
    if (event) { event.preventDefault(); event.stopPropagation(); }
    handleSaveClick(btn);
}

function handleSaveClick(btn) {
    const id = String(btn.dataset.id || '');
    if (!id) { console.warn('[save] no data-id on button'); return; }

    // Not logged in → show inline modal (no redirect)
    if (!window.__isLoggedIn) {
        _pendingDealId = id;
        openLoginModal();
        return;
    }

    const isSaved = _savedSet.has(id);
    if (isSaved) {
        unsaveDeal(id, btn);
    } else {
        saveDeal(id, btn);
    }
}

function saveDeal(id, btn) {
    // Optimistic update
    _savedSet.add(id);
    markBtn(btn, true);
    showToast('♥ Deal saved!', 'save');

    fetch('/api/auth.php', {
        method:      'POST',
        headers:     {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body:        JSON.stringify({ action: 'save', deal_id: parseInt(id, 10) }),
    })
    .then(r => r.json().catch(() => ({ ok: false, error: 'Bad response' })))
    .then(data => {
        if (!data.ok) {
            _savedSet.delete(id);
            markBtn(btn, false);
            if (data.error && data.error.includes('Login')) {
                showToast('Please log in to save', 'default');
                window.__isLoggedIn = false;
                openLoginModal();
            } else {
                showToast('Could not save: ' + (data.error || 'try again'), 'default');
            }
        }
    })
    .catch(err => {
        console.error('[save] network error', err);
        _savedSet.delete(id);
        markBtn(btn, false);
        showToast('Network error. Try again.', 'default');
    });
}

function unsaveDeal(id, btn) {
    _savedSet.delete(id);
    markBtn(btn, false);
    showToast('Removed from saved', 'default');

    fetch('/api/auth.php', {
        method:      'POST',
        headers:     {'Content-Type': 'application/json'},
        credentials: 'same-origin',
        body:        JSON.stringify({ action: 'unsave', deal_id: parseInt(id, 10) }),
    })
    .then(r => r.json().catch(() => ({ ok: false })))
    .then(data => {
        if (!data.ok) {
            _savedSet.add(id);
            markBtn(btn, true);
            showToast('Could not remove. Try again.', 'default');
        }
    })
    .catch(() => {
        _savedSet.add(id);
        markBtn(btn, true);
        showToast('Network error. Try again.', 'default');
    });
}

function markBtn(btn, saved) {
    if (btn.classList.contains('deal-detail-save-text')) {
        const label = btn.querySelector('#detail-save-label') || btn;
        label.textContent = saved ? '♥ Saved' : '♡ Save Deal';
        btn.classList.toggle('saved', saved);
    } else {
        btn.textContent = saved ? '♥' : '♡';
        btn.classList.toggle('saved', saved);
    }
}

function syncAllHearts() {
    document.querySelectorAll('.deal-save-btn, .deal-detail-save-text').forEach(btn => {
        const id = String(btn.dataset.id || '');
        if (id) markBtn(btn, _savedSet.has(id));
    });
}

// ─── Inline Login Modal (no redirect) ────────────────────────────────────────
function openLoginModal() {
    if (document.getElementById('save-login-modal')) {
        document.getElementById('save-login-modal').classList.add('open');
        return;
    }
    const modal = document.createElement('div');
    modal.id = 'save-login-modal';
    modal.innerHTML = `
        <div class="slm-backdrop" onclick="closeLoginModal()"></div>
        <div class="slm-sheet" role="dialog" aria-modal="true" aria-labelledby="slm-title">
            <button class="slm-close" onclick="closeLoginModal()" aria-label="Close">✕</button>
            <div class="slm-icon">♡</div>
            <h2 id="slm-title">Save this deal</h2>
            <p>Log in or sign up free to save deals to your wishlist.</p>
            <div class="slm-error" id="slm-error" style="display:none"></div>
            <form id="slm-form" onsubmit="submitLoginModal(event)">
                <input type="email" id="slm-email" placeholder="you@email.com" required autocomplete="email">
                <input type="password" id="slm-pass" placeholder="Password (6+ chars)" required minlength="6" autocomplete="current-password">
                <button type="submit" class="slm-btn slm-btn-primary" id="slm-submit">Log In &amp; Save</button>
                <button type="button" class="slm-btn slm-btn-secondary" onclick="switchToSignup()" id="slm-switch">No account? Sign up free</button>
            </form>
            <p class="slm-fine">By signing up, you agree to our terms.</p>
        </div>
    `;
    document.body.appendChild(modal);
    requestAnimationFrame(() => modal.classList.add('open'));
    setTimeout(() => document.getElementById('slm-email')?.focus(), 250);
}

function closeLoginModal() {
    const modal = document.getElementById('save-login-modal');
    if (!modal) return;
    modal.classList.remove('open');
    setTimeout(() => modal.remove(), 250);
    _pendingDealId = null;
}

let _signupMode = false;
function switchToSignup() {
    _signupMode = !_signupMode;
    const submit = document.getElementById('slm-submit');
    const swap   = document.getElementById('slm-switch');
    submit.textContent = _signupMode ? 'Sign Up &amp; Save' : 'Log In &amp; Save';
    swap.textContent   = _signupMode ? 'Already have an account? Log in' : 'No account? Sign up free';
    document.getElementById('slm-pass').setAttribute('autocomplete', _signupMode ? 'new-password' : 'current-password');
}

async function submitLoginModal(e) {
    e.preventDefault();
    const email  = document.getElementById('slm-email').value.trim();
    const pass   = document.getElementById('slm-pass').value;
    const error  = document.getElementById('slm-error');
    const submit = document.getElementById('slm-submit');
    error.style.display = 'none';
    submit.disabled = true;
    submit.textContent = 'Working…';

    try {
        const res = await fetch('/api/auth.php', {
            method:      'POST',
            headers:     {'Content-Type': 'application/json'},
            credentials: 'same-origin',
            body:        JSON.stringify({
                action:   _signupMode ? 'signup' : 'login',
                email:    email,
                password: pass,
            }),
        });
        const data = await res.json();

        if (!data.ok) {
            error.textContent = data.error || 'Something went wrong';
            error.style.display = 'block';
            submit.disabled = false;
            submit.textContent = _signupMode ? 'Sign Up & Save' : 'Log In & Save';
            return;
        }

        // Success — mark as logged in
        window.__isLoggedIn = true;

        // Save the pending deal
        if (_pendingDealId) {
            const btn = document.querySelector(`.deal-save-btn[data-id="${_pendingDealId}"], .deal-detail-save-text[data-id="${_pendingDealId}"]`);
            saveDeal(_pendingDealId, btn || { dataset: { id: _pendingDealId }, classList: { contains: () => false, toggle: () => {} } });
            _pendingDealId = null;
        }

        closeLoginModal();
        showToast('Welcome! ♥ Deal saved.', 'save');
    } catch (err) {
        error.textContent = 'Network error — please try again';
        error.style.display = 'block';
        submit.disabled = false;
        submit.textContent = _signupMode ? 'Sign Up & Save' : 'Log In & Save';
    }
}

// ─── Toast Notifications ───────────────────────────────────────────────────────
function getToastContainer() {
    let c = document.getElementById('toast-container');
    if (!c) {
        c = document.createElement('div');
        c.id = 'toast-container';
        c.setAttribute('aria-live', 'polite');
        document.body.appendChild(c);
    }
    return c;
}

function showToast(msg, type = 'default', duration = 3000) {
    const container = getToastContainer();
    const t = document.createElement('div');
    t.className = `toast${type === 'success' ? ' toast-success' : type === 'deal' ? ' toast-deal' : type === 'save' ? ' toast-save' : ''}`;
    if (msg.includes('<')) t.innerHTML = msg; else t.textContent = msg;
    container.appendChild(t);
    setTimeout(() => {
        t.classList.add('removing');
        t.addEventListener('animationend', () => t.remove());
    }, duration);
}

// ─── Lazy image loading fallback ───────────────────────────────────────────────
document.querySelectorAll('img[loading="lazy"]').forEach(img => {
    img.addEventListener('error', () => { img.src = '/assets/images/placeholder.svg'; });
});

// ─── Search autocomplete ───────────────────────────────────────────────────────
const searchInput = document.querySelector('.search-input');
if (searchInput) {
    let debounceTimer;
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            const q = searchInput.value.trim();
            q.length >= 2 ? fetchSuggestions(q) : removeSuggestions();
        }, 280);
    });
    searchInput.addEventListener('blur', () => setTimeout(removeSuggestions, 200));
}

function fetchSuggestions(q) {
    fetch(`/api/suggestions.php?q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(data => renderSuggestions(data))
        .catch(() => {});
}

function renderSuggestions(items) {
    removeSuggestions();
    if (!items.length) return;
    const form = document.querySelector('.search-form');
    const box  = document.createElement('div');
    box.className = 'search-suggestions';
    box.style.cssText = `position:absolute;top:calc(100% + 4px);left:0;right:0;background:#fff;border:1.5px solid var(--border);border-radius:12px;overflow:hidden;z-index:200;box-shadow:var(--shadow-lg)`;
    items.forEach(item => {
        const a = document.createElement('a');
        a.href  = `/deal.php?id=${item.id}`;
        a.innerHTML = `<div style="display:flex;align-items:center;gap:.75rem;padding:.6rem 1rem" onmouseover="this.style.background='var(--ash)'" onmouseout="this.style.background='transparent'"><img src="${escHtml(item.image||'')}" width="36" height="36" style="object-fit:contain;border-radius:6px;background:var(--ash)" onerror="this.style.display='none'"><div><div style="font-size:.88rem;font-weight:600;color:var(--ink)">${escHtml(item.title)}</div><div style="font-size:.78rem;color:var(--orange)">$${escHtml(String(item.price))} <span style="color:var(--green)">-${escHtml(String(item.pct))}%</span></div></div></div>`;
        box.appendChild(a);
    });
    form.style.position = 'relative';
    form.appendChild(box);
}

function removeSuggestions() {
    document.querySelectorAll('.search-suggestions').forEach(el => el.remove());
}

// ─── Hot Deals Carousel ────────────────────────────────────────────────────────
const hotScroll = document.getElementById('hot-deals-scroll');
document.getElementById('hot-prev')?.addEventListener('click', () => hotScroll?.scrollBy({ left: -280, behavior: 'smooth' }));
document.getElementById('hot-next')?.addEventListener('click', () => hotScroll?.scrollBy({ left:  280, behavior: 'smooth' }));

if (hotScroll) {
    let startX = 0, scrollLeft = 0;
    hotScroll.addEventListener('touchstart', e => { startX = e.touches[0].clientX; scrollLeft = hotScroll.scrollLeft; }, { passive: true });
    hotScroll.addEventListener('touchmove',  e => {
        const dx = startX - e.touches[0].clientX;
        hotScroll.scrollLeft = scrollLeft + dx;
    }, { passive: true });
}

// ─── Infinite Scroll ───────────────────────────────────────────────────────────
(function initInfiniteScroll() {
    const grid       = document.getElementById('deals-grid');
    const pagination = document.querySelector('.pagination');
    if (!grid || !pagination) return;

    const loader = document.createElement('div');
    loader.className = 'scroll-loader';
    loader.innerHTML = '<div class="scroll-spinner"></div> Loading more deals…';
    const sentinel = document.createElement('div');
    sentinel.id = 'scroll-sentinel';

    pagination.after(loader, sentinel);
    pagination.style.display = 'none';

    let loading = false, page = parseInt(new URLSearchParams(location.search).get('page') || '1'), exhausted = false;

    const observer = new IntersectionObserver(entries => {
        if (entries[0].isIntersecting && !loading && !exhausted) loadMore();
    }, { rootMargin: '300px' });

    observer.observe(sentinel);

    async function loadMore() {
        loading = true;
        loader.classList.add('visible');
        page++;
        const url = new URL(location.href);
        url.searchParams.set('page', page);
        url.searchParams.set('_json', '1');
        try {
            const res  = await fetch(url.toString());
            const html = await res.text();
            const tmp  = document.createElement('div');
            tmp.innerHTML = html;
            const cards = tmp.querySelectorAll('.deal-card');
            if (cards.length) {
                cards.forEach((c, i) => {
                    c.style.animationDelay = `${i * 0.04}s`;
                    grid.appendChild(c);
                });
                syncAllHearts(); // sync hearts on new cards
            } else {
                exhausted = true;
                observer.disconnect();
                loader.innerHTML = '✓ All deals loaded';
                setTimeout(() => loader.classList.remove('visible'), 2000);
            }
        } catch {}
        loader.classList.remove('visible');
        loading = false;
    }
})();

// ─── Cat pill bar: fade edges on scroll (mobile) ─────────────────────────────
(function catBarFade() {
    const bar = document.querySelector('.cat-pill-strip');
    if (!bar) return;
    function update() {
        bar.style.setProperty('--fade-left',  bar.scrollLeft > 10  ? '1' : '0');
        bar.style.setProperty('--fade-right', bar.scrollLeft < bar.scrollWidth - bar.clientWidth - 10 ? '1' : '0');
    }
    bar.addEventListener('scroll', update, { passive: true });
    update();
})();

// ─── Header scroll shadow ────────────────────────────────────────────────────
(function headerScroll() {
    const header = document.querySelector('.site-header');
    if (!header) return;
    let ticking = false;
    window.addEventListener('scroll', () => {
        if (!ticking) {
            requestAnimationFrame(() => {
                header.classList.toggle('scrolled', window.scrollY > 10);
                ticking = false;
            });
            ticking = true;
        }
    }, { passive: true });
})();

// ─── Initialize ───────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    syncAllHearts();
});
