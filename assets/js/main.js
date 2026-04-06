// 50OFF — main.js  (mobile-optimized, addicting features)

// ─── Helpers ──────────────────────────────────────────────────────────────────
function updateParam(key, value) {
    const url = new URL(window.location.href);
    value ? url.searchParams.set(key, value) : url.searchParams.delete(key);
    url.searchParams.delete('page');
    return url.toString();
}

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
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

// Close drawer on swipe right
let drawerTouchStartX = 0;
drawer?.addEventListener('touchstart', e => { drawerTouchStartX = e.touches[0].clientX; }, { passive: true });
drawer?.addEventListener('touchend', e => {
    if (e.changedTouches[0].clientX - drawerTouchStartX > 60) closeDrawer();
}, { passive: true });

// ─── Saved Deals (localStorage wishlist) ──────────────────────────────────────
const SAVED_KEY = '50off_saved_v1';

function getSaved() {
    try { return JSON.parse(localStorage.getItem(SAVED_KEY) || '{}'); }
    catch { return {}; }
}

function setSaved(data) {
    try { localStorage.setItem(SAVED_KEY, JSON.stringify(data)); }
    catch {}
}

function toggleSave(btn, event) {
    event.preventDefault();
    event.stopPropagation();

    const id    = btn.dataset.id;
    const title = btn.dataset.title;
    const price = btn.dataset.price;
    const pct   = btn.dataset.pct;
    const img   = btn.dataset.img;
    const link  = btn.dataset.link;

    const saved = getSaved();

    if (saved[id]) {
        delete saved[id];
        btn.textContent = '♡';
        btn.classList.remove('saved');
        showToast('💔 Removed from saved deals', 'default');
    } else {
        saved[id] = { id, title, price, pct, img, link, savedAt: Date.now() };
        btn.textContent = '♥';
        btn.classList.add('saved');
        showToast('♥ Saved! View in your wishlist', 'save');
    }

    setSaved(saved);
    updateSavedCount();
    updateDrawerSavedSummary();
}

function updateSavedCount() {
    const count = Object.keys(getSaved()).length;
    const headerCount = document.getElementById('header-saved-count');
    const mobileCount = document.getElementById('mobile-saved-count');
    const savedBtns   = document.querySelectorAll('.deal-save-btn');

    if (headerCount) {
        headerCount.textContent = count;
        headerCount.classList.toggle('show', count > 0);
    }
    if (mobileCount) {
        mobileCount.textContent = count;
        mobileCount.classList.toggle('show', count > 0);
    }

    // Sync heart icons on current page
    const saved = getSaved();
    savedBtns.forEach(btn => {
        const isSaved = !!saved[btn.dataset.id];
        btn.textContent = isSaved ? '♥' : '♡';
        btn.classList.toggle('saved', isSaved);
    });
}

function updateDrawerSavedSummary() {
    const el    = document.getElementById('drawer-saved-summary');
    if (!el) return;
    const count = Object.keys(getSaved()).length;
    el.innerHTML = count > 0
        ? `<a href="#" onclick="openSavedPanel();return false;" style="color:var(--orange);font-weight:600">${count} deal${count!==1?'s':''} saved → View wishlist</a>`
        : `<span>No saved deals yet. Tap ♡ on any deal!</span>`;
}

// ─── Saved Panel ───────────────────────────────────────────────────────────────
const savedPanel   = document.getElementById('saved-panel');
const savedOverlay = document.getElementById('saved-overlay');

function openSavedPanel() {
    renderSavedPanel();
    savedPanel?.classList.add('open');
    savedOverlay?.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeSavedPanel() {
    savedPanel?.classList.remove('open');
    savedOverlay?.classList.remove('open');
    document.body.style.overflow = '';
}
savedOverlay?.addEventListener('click', closeSavedPanel);

function renderSavedPanel() {
    const body      = document.getElementById('saved-panel-body');
    const countEl   = document.getElementById('saved-panel-count');
    const saved     = getSaved();
    const items     = Object.values(saved).sort((a,b) => b.savedAt - a.savedAt);
    if (!body) return;

    if (countEl) countEl.textContent = items.length ? `(${items.length})` : '';

    if (!items.length) {
        body.innerHTML = `<p class="saved-empty"><span class="big-icon">♡</span>No saved deals yet.<br>Tap the heart on any deal to save it here!</p>`;
        return;
    }

    body.innerHTML = items.map(d => `
        <div style="display:flex;gap:.75rem;padding:.85rem 0;border-bottom:1px solid var(--border);align-items:center">
            <img src="${escHtml(d.img)}" width="56" height="56" style="border-radius:8px;object-fit:contain;background:var(--ash);flex-shrink:0" onerror="this.src='/assets/images/placeholder.svg'">
            <div style="flex:1;min-width:0">
                <a href="${escHtml(d.link)}" style="font-size:.82rem;font-weight:600;color:var(--ink);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;line-height:1.35">${escHtml(d.title)}</a>
                <div style="margin-top:.3rem;display:flex;align-items:center;gap:.4rem">
                    <span style="font-size:1rem;font-weight:800;color:var(--green)">$${escHtml(d.price)}</span>
                    <span style="font-size:.72rem;font-weight:700;color:var(--red);background:var(--red-light);padding:.1rem .35rem;border-radius:4px">-${escHtml(d.pct)}%</span>
                </div>
            </div>
            <button onclick="removeSaved('${escHtml(d.id)}')" style="background:none;border:none;color:var(--slate);font-size:1.1rem;padding:.25rem;cursor:pointer;flex-shrink:0" aria-label="Remove">✕</button>
        </div>
    `).join('') + `<div style="padding-top:1rem;text-align:center"><button onclick="clearAllSaved()" style="font-size:.8rem;color:var(--slate);background:none;border:1px solid var(--border);padding:.4rem .9rem;border-radius:var(--radius-xs);cursor:pointer">Clear all</button></div>`;
}

function removeSaved(id) {
    const saved = getSaved();
    delete saved[id];
    setSaved(saved);
    updateSavedCount();
    updateDrawerSavedSummary();
    renderSavedPanel();
}

function clearAllSaved() {
    setSaved({});
    updateSavedCount();
    updateDrawerSavedSummary();
    renderSavedPanel();
    showToast('Wishlist cleared', 'default');
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

// Show "N new deals" toast on page load (based on deal count)
window.addEventListener('load', () => {
    // Only show on the main deals listing, not everywhere
    const dealsGrid = document.getElementById('deals-grid');
    if (!dealsGrid) return;
    const count = dealsGrid.querySelectorAll('.deal-card').length;
    if (count > 0) {
        setTimeout(() => showToast(`🔥 ${count}+ deals live right now`, 'deal', 4000), 1200);
    }
});

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

// Touch swipe on carousel
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

    // Replace pagination with a sentinel + loader
    const loader = document.createElement('div');
    loader.className = 'scroll-loader';
    loader.innerHTML = '<div class="scroll-spinner"></div> Loading more deals…';
    const sentinel = document.createElement('div');
    sentinel.id = 'scroll-sentinel';

    pagination.after(loader, sentinel);
    pagination.style.display = 'none';

    let loading  = false;
    let page     = parseInt(new URLSearchParams(location.search).get('page') || '1');
    let exhausted = false;

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
                updateSavedCount(); // sync hearts on new cards
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

// ─── Cat bar: fade edges to show scroll affordance (mobile) ───────────────────
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

// ─── Initialize on DOM ready ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    updateSavedCount();
    updateDrawerSavedSummary();
});
