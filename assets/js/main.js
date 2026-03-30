// 50OFF — main.js

// ─── Update URL param helper (used in sort/filter controls) ──────────────────
function updateParam(key, value) {
    const url = new URL(window.location.href);
    if (value) {
        url.searchParams.set(key, value);
    } else {
        url.searchParams.delete(key);
    }
    url.searchParams.delete('page'); // reset page on filter change
    return url.toString();
}

// ─── Track deal click (async, fire-and-forget) ───────────────────────────────
function trackDealClick(dealId) {
    navigator.sendBeacon('/api/track.php', JSON.stringify({ id: dealId }));
}

// ─── Lazy image loading fallback ─────────────────────────────────────────────
document.querySelectorAll('img[loading="lazy"]').forEach(img => {
    img.addEventListener('error', () => {
        img.src = '/assets/images/placeholder.svg';
    });
});

// ─── Search autocomplete (live search as you type) ───────────────────────────
const searchInput = document.querySelector('.search-input');
if (searchInput) {
    let debounceTimer;
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            const q = searchInput.value.trim();
            if (q.length >= 2) {
                fetchSuggestions(q);
            } else {
                removeSuggestions();
            }
        }, 280);
    });

    searchInput.addEventListener('blur', () => {
        setTimeout(removeSuggestions, 200);
    });
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
    box.style.cssText = `
        position:absolute; top:calc(100% + 4px); left:0; right:0;
        background:var(--surface-2); border:1.5px solid var(--border);
        border-radius:12px; overflow:hidden; z-index:200;
        box-shadow:0 8px 32px rgba(0,0,0,.6);
    `;
    items.forEach(item => {
        const a = document.createElement('a');
        a.href = `/deal.php?id=${item.id}`;
        a.innerHTML = `
            <div style="display:flex;align-items:center;gap:.75rem;padding:.6rem 1rem;transition:background .15s"
                 onmouseover="this.style.background='var(--surface-3)'"
                 onmouseout="this.style.background='transparent'">
                <img src="${item.image}" width="36" height="36" style="object-fit:contain;border-radius:6px;background:var(--surface-3)" onerror="this.style.display='none'">
                <div>
                    <div style="font-size:.88rem;font-weight:600">${escHtml(item.title)}</div>
                    <div style="font-size:.78rem;color:var(--orange)">$${item.price} <span style="color:var(--green)">-${item.pct}%</span></div>
                </div>
            </div>`;
        box.appendChild(a);
    });

    form.style.position = 'relative';
    form.appendChild(box);
}

function removeSuggestions() {
    document.querySelectorAll('.search-suggestions').forEach(el => el.remove());
}

function escHtml(str) {
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ─── Mobile menu ─────────────────────────────────────────────────────────────
const menuBtn = document.querySelector('.mobile-menu-btn');
if (menuBtn) {
    menuBtn.addEventListener('click', () => {
        document.body.classList.toggle('menu-open');
    });
}

// ─── Infinite scroll (optional, progressive enhancement) ─────────────────────
// Uncomment to enable infinite scroll instead of pagination
/*
let isLoading = false;
let currentPage = parseInt(new URLSearchParams(location.search).get('page') || '1');

const observer = new IntersectionObserver(entries => {
    if (entries[0].isIntersecting && !isLoading) {
        loadMoreDeals();
    }
}, { threshold: 0.5 });

const sentinel = document.createElement('div');
sentinel.id = 'scroll-sentinel';
document.querySelector('#deals-grid')?.after(sentinel);
if (document.querySelector('.pagination')) {
    document.querySelector('.pagination').style.display = 'none';
    observer.observe(sentinel);
}

async function loadMoreDeals() {
    isLoading = true;
    currentPage++;
    const url = new URL(location.href);
    url.searchParams.set('page', currentPage);
    url.searchParams.set('_json', '1');
    const r = await fetch(url.toString());
    const html = await r.text();
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    const cards = tmp.querySelectorAll('.deal-card');
    if (cards.length) {
        cards.forEach(c => document.querySelector('#deals-grid').appendChild(c));
        isLoading = false;
    } else {
        observer.disconnect();
    }
}
*/
