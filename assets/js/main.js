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

// ─── Save System (server-based, requires login) ──────────────────────────────
// window.__isLoggedIn and window.__savedIds are set by PHP in the page
let _savedSet = new Set(window.__savedIds || []);

function toggleSave(btn, event) {
    event.preventDefault();
    event.stopPropagation();

    // Not logged in → show toast then redirect to login
    if (!window.__isLoggedIn) {
        showToast('Log in to save deals ♡', 'default');
        setTimeout(() => {
            const returnUrl = encodeURIComponent(location.pathname + location.search);
            location.href = '/login.php?redirect=' + returnUrl;
        }, 800);
        return;
    }

    const id = String(btn.dataset.id);
    const isSaved = _savedSet.has(id);

    if (isSaved) {
        // Unsave — optimistic update, revert on failure
        _savedSet.delete(id);
        markBtn(btn, false);
        showToast('Removed from saved deals', 'default');
        fetch('/api/auth.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'unsave', deal_id: parseInt(id) }),
        }).then(r => r.json()).then(data => {
            if (!data.ok) { _savedSet.add(id); markBtn(btn, true); showToast('Could not remove. Try again.', 'default'); }
        }).catch(() => { _savedSet.add(id); markBtn(btn, true); showToast('Network error. Try again.', 'default'); });
    } else {
        // Save — optimistic update, revert on failure
        _savedSet.add(id);
        markBtn(btn, true);
        showToast('♥ Deal saved!', 'save');
        fetch('/api/auth.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'save', deal_id: parseInt(id) }),
        }).then(r => r.json()).then(data => {
            if (!data.ok) { _savedSet.delete(id); markBtn(btn, false); showToast('Could not save. Try again.', 'default'); }
        }).catch(() => { _savedSet.delete(id); markBtn(btn, false); showToast('Network error. Try again.', 'default'); });
    }
}

function markBtn(btn, saved) {
    if (btn.classList.contains('deal-detail-save-text')) {
        // Text button on detail page
        const label = btn.querySelector('#detail-save-label') || btn;
        label.textContent = saved ? '♥ Saved' : '♡ Save Deal';
        btn.classList.toggle('saved', saved);
    } else {
        // Heart icon button on cards
        btn.textContent = saved ? '♥' : '♡';
        btn.classList.toggle('saved', saved);
    }
}

function syncAllHearts() {
    document.querySelectorAll('.deal-save-btn, .deal-detail-save-text').forEach(btn => {
        const id = String(btn.dataset.id);
        if (id) markBtn(btn, _savedSet.has(id));
    });
}

// ─── Toast Notifications ───────────────────────────────────────────────────────
const toastContainer = document.getElementById('toast-container');

function showToast(msg, type = 'default', duration = 3000) {
    if (!toastContainer) return;
    const t = document.createElement('div');
    t.className = `toast${type === 'success' ? ' toast-success' : type === 'deal' ? ' toast-deal' : type === 'save' ? ' toast-save' : ''}`;
    if (msg.includes('<')) t.innerHTML = msg; else t.textContent = msg;
    toastContainer.appendChild(t);
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
