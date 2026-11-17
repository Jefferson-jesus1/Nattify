let PRODUCTS = [];
let CATEGORIES = [];
let SETTINGS = {};
let activeCategory = '';
let searchTerm = '';

const PLACEHOLDER_EMOJIS = ['🍮', '🍰', '🍫', '🍓', '🥧', '🍨'];
function placeholderFor(name) {
  let h = 0;
  for (const c of String(name)) h = (h + c.charCodeAt(0)) % PLACEHOLDER_EMOJIS.length;
  return PLACEHOLDER_EMOJIS[h];
}

async function boot() {
  try {
    const [settings, categories, products] = await Promise.all([
      api('/settings'),
      api('/categories'),
      api('/products')
    ]);
    SETTINGS = settings; CATEGORIES = categories; PRODUCTS = products;
    applyBranding();
    renderChips();
    renderGrid();
    initReveal();
  } catch (e) {
    toast('Não foi possível carregar o cardápio. Verifique se o servidor está rodando.', 'err');
  }
}

function applyBranding() {
  document.title = `${SETTINGS.siteName} — doces artesanais`;
  document.getElementById('siteName').textContent = SETTINGS.siteName;
  document.getElementById('siteTag').textContent = SETTINGS.tagline;
  if (SETTINGS.instagram) document.getElementById('footerInsta').textContent = `@${SETTINGS.instagram.replace(/^@/, '')}`;
}

function renderChips() {
  const box = document.getElementById('categoryChips');
  box.innerHTML = `<button class="chip ${activeCategory === '' ? 'active' : ''}" data-cat="">Tudo</button>` +
    CATEGORIES.map(c => `<button class="chip ${activeCategory === c.id ? 'active' : ''}" data-cat="${c.id}">${escapeHtml(c.name)}</button>`).join('');
  box.querySelectorAll('.chip').forEach(btn => btn.addEventListener('click', () => {
    activeCategory = btn.dataset.cat;
    renderChips();
    renderGrid();
  }));
}

function renderGrid() {
  const grid = document.getElementById('productGrid');
  let list = PRODUCTS;
  if (activeCategory) list = list.filter(p => p.categoryId === activeCategory);
  if (searchTerm) {
    const s = searchTerm.toLowerCase();
    list = list.filter(p => p.name.toLowerCase().includes(s) || (p.description || '').toLowerCase().includes(s));
  }
  document.getElementById('gridTitle').textContent = activeCategory
    ? (CATEGORIES.find(c => c.id === activeCategory)?.name || 'Doces')
    : 'Nossos doces';

  if (list.length === 0) {
    grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1;"><div class="glyph">🥄</div>Nenhum doce encontrado por aqui.<br>Tente outra busca ou categoria.</div>`;
    return;
  }
  grid.innerHTML = list.map((p, i) => cardHtml(p, i)).join('');
  grid.querySelectorAll('[data-open]').forEach(el => el.addEventListener('click', () => openProduct(el.dataset.open)));
  initReveal();
}

function cardHtml(p, i) {
  const out = p.stock <= 0;
  const low = !out && p.stock <= 3;
  const media = p.imageUrl
    ? `<img src="${p.imageUrl}" alt="${escapeHtml(p.name)}">`
    : `<div class="ph">${placeholderFor(p.name)}</div>`;
  const delay = (i % 6) * 90;
  return `
  <div class="cup-card" data-reveal="scale" style="transition-delay:${delay}ms;" data-open="${p.id}">
    <div class="cup-media">
      ${media}
      ${out ? '<span class="badge out">esgotado</span>' : (low ? `<span class="badge low">últimas ${p.stock}</span>` : (p.featured ? '<span class="badge">destaque</span>' : ''))}
    </div>
    <div class="cup-body">
      <h3>${escapeHtml(p.name)}</h3>
      <div class="desc">${escapeHtml(p.description || '')}</div>
      <div class="cup-meta">⭐ ${p.rating?.toFixed?.(1) ?? p.rating} <span style="opacity:.6">(${p.ratingCount || 0})</span></div>
      <div class="cup-foot">
        <div class="price">${brl(p.price)}</div>
      </div>
    </div>
  </div>`;
}

document.getElementById('searchInput').addEventListener('input', (e) => {
  searchTerm = e.target.value;
  renderGrid();
});

// ---------- Scroll reveal (IntersectionObserver) — movimento leve ao rolar a página ----------
let revealObserver;
function initReveal() {
  if (!revealObserver) {
    revealObserver = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('in-view');
          revealObserver.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
  }
  document.querySelectorAll('[data-reveal]:not(.in-view)').forEach(el => revealObserver.observe(el));
}

// ---------- Parallax suave nas formas flutuantes do hero ----------
(function initParallax() {
  const shapes = document.querySelectorAll('.float-shape');
  if (!shapes.length) return;
  let ticking = false;
  window.addEventListener('scroll', () => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
      const y = window.scrollY;
      shapes.forEach((el, i) => {
        const speed = 0.06 + (i % 3) * 0.03;
        el.style.transform = `translateY(${y * speed}px)`;
      });
      ticking = false;
    });
  }, { passive: true });
})();

// ---------- Modal de produto (somente visualização, sem carrinho) ----------
function openProduct(id) {
  const p = PRODUCTS.find(x => x.id === id);
  if (!p) return;
  const out = p.stock <= 0;
  const media = p.imageUrl ? `<img class="modal-hero-img" src="${p.imageUrl}" alt="${escapeHtml(p.name)}">` : `<div class="modal-hero-ph">${placeholderFor(p.name)}</div>`;
  document.getElementById('productModalBox').innerHTML = `
    <button class="modal-close" id="closeProductModal">✕</button>
    ${media}
    <div class="modal-content">
      <h3 style="font-size:24px;">${escapeHtml(p.name)}</h3>
      <p style="color:var(--brown-soft); margin-top:8px; line-height:1.6; font-size:14.5px;">${escapeHtml(p.description || '')}</p>
      <div style="display:flex; align-items:center; justify-content:space-between; margin-top:18px;">
        <div class="price" style="font-size:26px;">${brl(p.price)}</div>
        <div style="font-size:13px; color:var(--brown-soft);">${out ? 'Esgotado no momento' : `${p.stock} disponíveis`}</div>
      </div>
    </div>`;
  document.getElementById('closeProductModal').addEventListener('click', closeProductModal);
  document.getElementById('productModalOverlay').classList.add('show');
}
function closeProductModal() { document.getElementById('productModalOverlay').classList.remove('show'); }
document.getElementById('productModalOverlay').addEventListener('click', (e) => { if (e.target.id === 'productModalOverlay') closeProductModal(); });

boot();
