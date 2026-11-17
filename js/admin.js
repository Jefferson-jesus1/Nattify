let CATEGORIES = [];
let PRODUCTS = [];
let SETTINGS = {};

function isLogged() { return !!localStorage.getItem('admin_token'); }

function showApp() {
  document.getElementById('loginScreen').style.display = 'none';
  document.getElementById('adminApp').style.display = 'block';
  loadAll();
}
function showLogin() {
  document.getElementById('loginScreen').style.display = 'flex';
  document.getElementById('adminApp').style.display = 'none';
}

document.getElementById('loginForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const errBox = document.getElementById('loginError');
  errBox.style.display = 'none';
  try {
    const res = await api('/auth/login', {
      method: 'POST',
      body: { username: document.getElementById('loginUser').value, password: document.getElementById('loginPass').value }
    });
    localStorage.setItem('admin_token', res.token);
    showApp();
  } catch (err) {
    errBox.textContent = err.message;
    errBox.style.display = 'block';
  }
});
document.getElementById('logoutBtn').addEventListener('click', () => {
  localStorage.removeItem('admin_token');
  showLogin();
});

// ---------- Tabs ----------
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(`tab-${btn.dataset.tab}`).classList.add('active');
  });
});

async function loadAll() {
  try {
    SETTINGS = await api('/settings');
    CATEGORIES = await api('/categories');
    PRODUCTS = await api('/products/admin/all', { auth: true });
    applyBranding();
    renderProducts();
    renderCategories();
    renderClientes();
    renderFinanceiro();
  } catch (err) {
    if (err.message.includes('Sessão') || err.message.includes('autenticado')) {
      localStorage.removeItem('admin_token');
      showLogin();
    } else {
      toast(err.message, 'err');
    }
  }
}

function applyBranding() {
  document.getElementById('adminSiteName').textContent = `Painel — ${SETTINGS.siteName}`;
}

// ---------- Clientes (ranking de compras por período) ----------
let clientesPeriod = 'day';

document.getElementById('clientesPeriodChips').querySelectorAll('.chip').forEach(chip => {
  chip.addEventListener('click', () => {
    document.querySelectorAll('#clientesPeriodChips .chip').forEach(c => c.classList.remove('active'));
    chip.classList.add('active');
    clientesPeriod = chip.dataset.period;
    renderClientes();
  });
});

const PERIOD_LABELS = { day: 'hoje', week: 'esta semana', month: 'este mês', year: 'este ano' };

async function renderClientes() {
  const data = await api(`/ledger/ranking?period=${clientesPeriod}`, { auth: true });
  const totalPeriodo = data.ranking.reduce((s, r) => s + r.total, 0);
  document.getElementById('clientesStatGrid').innerHTML = `
    <div class="stat-card"><div class="num">${brl(totalPeriodo)}</div><div class="label">Total comprado (${PERIOD_LABELS[clientesPeriod]})</div></div>
    <div class="stat-card"><div class="num">${data.ranking.length}</div><div class="label">Clientes diferentes</div></div>
    <div class="stat-card"><div class="num">${data.ranking[0] ? escapeHtml(data.ranking[0].customerName) : '—'}</div><div class="label">Quem mais comprou</div></div>`;

  document.getElementById('clientesRankingList').innerHTML = data.ranking.length
    ? data.ranking.map((r, i) => `<div class="row"><span>${i + 1}º — ${escapeHtml(r.customerName)} <span style="color:var(--ink-soft)">(${r.qty}x)</span></span><span><b>${brl(r.total)}</b></span></div>`).join('')
    : `<div class="row" style="color:var(--ink-soft)">Nenhuma compra lançada nesse período ainda.</div>`;
}

// ---------- Produtos ----------
function renderProducts() {
  const grid = document.getElementById('adminProductGrid');
  if (PRODUCTS.length === 0) {
    grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1; color:var(--cream-text);">Nenhum produto cadastrado ainda.</div>`;
    return;
  }
  grid.innerHTML = PRODUCTS.map(p => {
    const media = p.imageUrl ? `<img src="${p.imageUrl}" alt="">` : `😋`;
    return `
    <div class="admin-card">
      <div class="thumb">${p.imageUrl ? `<img src="${p.imageUrl}">` : media}
        ${!p.active ? '<span class="inactive-flag">Inativo</span>' : ''}
      </div>
      <div class="body">
        <h4>${escapeHtml(p.name)}</h4>
        <div class="row2"><span>${brl(p.price)}</span><span style="color:var(--ink-soft)">${(CATEGORIES.find(c => c.id === p.categoryId) || {}).name || 'Sem categoria'}</span></div>
        <div class="row2">
          <span>Estoque:</span>
          <div class="stock-edit">
            <button data-stock-dec="${p.id}">−</button>
            <b>${p.stock}</b>
            <button data-stock-inc="${p.id}">+</button>
          </div>
        </div>
        <div class="actions">
          <button class="act-edit" data-edit="${p.id}">Editar</button>
          <button class="act-del" data-del="${p.id}">Excluir</button>
        </div>
      </div>
    </div>`;
  }).join('');

  grid.querySelectorAll('[data-stock-inc]').forEach(b => b.addEventListener('click', () => changeStock(b.dataset.stockInc, 1)));
  grid.querySelectorAll('[data-stock-dec]').forEach(b => b.addEventListener('click', () => changeStock(b.dataset.stockDec, -1)));
  grid.querySelectorAll('[data-edit]').forEach(b => b.addEventListener('click', () => openProductForm(b.dataset.edit)));
  grid.querySelectorAll('[data-del]').forEach(b => b.addEventListener('click', () => deleteProduct(b.dataset.del)));
}

async function changeStock(id, delta) {
  try {
    await api(`/products/${id}/stock`, { method: 'PATCH', auth: true, body: { delta } });
    PRODUCTS = await api('/products/admin/all', { auth: true });
    renderProducts();
  } catch (err) { toast(err.message, 'err'); }
}

async function deleteProduct(id) {
  if (!confirm('Tem certeza que deseja excluir este produto? Essa ação não pode ser desfeita.')) return;
  try {
    await api(`/products/${id}`, { method: 'DELETE', auth: true });
    toast('Produto excluído.');
    PRODUCTS = await api('/products/admin/all', { auth: true });
    renderProducts();
  } catch (err) { toast(err.message, 'err'); }
}

function fillCategorySelect() {
  document.getElementById('pfCategory').innerHTML =
    `<option value="">Sem categoria</option>` + CATEGORIES.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
}

function openProductForm(id) {
  fillCategorySelect();
  const form = document.getElementById('productForm');
  form.reset();
  document.getElementById('pfId').value = '';
  document.getElementById('pfActive').checked = true;
  document.getElementById('pfFeatured').checked = false;

  if (id) {
    const p = PRODUCTS.find(x => x.id === id);
    document.getElementById('productFormTitle').textContent = 'Editar produto';
    document.getElementById('pfId').value = p.id;
    document.getElementById('pfName').value = p.name;
    document.getElementById('pfDesc').value = p.description || '';
    document.getElementById('pfPrice').value = p.price;
    document.getElementById('pfStock').value = p.stock;
    document.getElementById('pfCategory').value = p.categoryId || '';
    document.getElementById('pfActive').checked = p.active;
    document.getElementById('pfFeatured').checked = p.featured;
  } else {
    document.getElementById('productFormTitle').textContent = 'Novo produto';
  }
  document.getElementById('productFormOverlay').classList.add('show');
}
document.getElementById('newProductBtn').addEventListener('click', () => openProductForm(null));
document.getElementById('closeProductForm').addEventListener('click', () => document.getElementById('productFormOverlay').classList.remove('show'));

document.getElementById('productForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const id = document.getElementById('pfId').value;
  const fd = new FormData();
  fd.append('name', document.getElementById('pfName').value);
  fd.append('description', document.getElementById('pfDesc').value);
  fd.append('price', document.getElementById('pfPrice').value);
  fd.append('stock', document.getElementById('pfStock').value);
  fd.append('categoryId', document.getElementById('pfCategory').value);
  fd.append('active', document.getElementById('pfActive').checked);
  fd.append('featured', document.getElementById('pfFeatured').checked);
  const file = document.getElementById('pfImage').files[0];
  if (file) fd.append('image', file);

  const btn = document.getElementById('pfSubmit');
  btn.disabled = true; btn.textContent = 'Salvando...';
  try {
    if (id) await api(`/products/${id}`, { method: 'PUT', isForm: true, auth: true, body: fd });
    else await api('/products', { method: 'POST', isForm: true, auth: true, body: fd });
    toast('Produto salvo com sucesso!');
    document.getElementById('productFormOverlay').classList.remove('show');
    PRODUCTS = await api('/products/admin/all', { auth: true });
    renderProducts();
  } catch (err) {
    toast(err.message, 'err');
  } finally {
    btn.disabled = false; btn.textContent = 'Salvar produto';
  }
});

// ---------- Categorias ----------
function renderCategories() {
  document.getElementById('catList').innerHTML = CATEGORIES.length
    ? CATEGORIES.map(c => `<div class="row"><span>${escapeHtml(c.name)}</span><button class="remove-x" data-catdel="${c.id}">excluir</button></div>`).join('')
    : `<div class="row" style="color:var(--ink-soft)">Nenhuma categoria ainda.</div>`;
  document.querySelectorAll('[data-catdel]').forEach(b => b.addEventListener('click', async () => {
    if (!confirm('Excluir esta categoria?')) return;
    try {
      await api(`/categories/${b.dataset.catdel}`, { method: 'DELETE', auth: true });
      CATEGORIES = await api('/categories');
      renderCategories();
      fillCategorySelect();
    } catch (err) { toast(err.message, 'err'); }
  }));
}
document.getElementById('newCatForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const input = document.getElementById('newCatName');
  if (!input.value.trim()) return;
  try {
    await api('/categories', { method: 'POST', auth: true, body: { name: input.value.trim() } });
    input.value = '';
    CATEGORIES = await api('/categories');
    renderCategories();
  } catch (err) { toast(err.message, 'err'); }
});

// ---------- Financeiro (lançamentos + fluxo de caixa) ----------
let financeiroPeriod = 'day';
let financeiroFilter = null; // {from, to} quando ela usa o filtro manual de datas

document.getElementById('financeiroPeriodChips').querySelectorAll('.chip').forEach(chip => {
  chip.addEventListener('click', () => {
    document.querySelectorAll('#financeiroPeriodChips .chip').forEach(c => c.classList.remove('active'));
    chip.classList.add('active');
    financeiroPeriod = chip.dataset.period;
    financeiroFilter = null;
    document.getElementById('fDateFrom').value = '';
    document.getElementById('fDateTo').value = '';
    renderFinanceiro();
  });
});

document.getElementById('lgType').addEventListener('change', (e) => {
  document.getElementById('lgCustomerField').style.display = e.target.value === 'entrada' ? 'block' : 'none';
});

document.getElementById('lgDate').valueAsDate = new Date();

document.getElementById('ledgerForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('lgSubmit');
  btn.disabled = true; btn.textContent = 'Lançando...';
  try {
    await api('/ledger', {
      method: 'POST', auth: true, body: {
        entryDate: document.getElementById('lgDate').value,
        type: document.getElementById('lgType').value,
        amount: parseFloat(document.getElementById('lgAmount').value) || 0,
        category: document.getElementById('lgCategory').value,
        customerName: document.getElementById('lgCustomer').value,
        description: document.getElementById('lgDesc').value,
      }
    });
    toast('Lançamento registrado!');
    document.getElementById('ledgerForm').reset();
    document.getElementById('lgDate').valueAsDate = new Date();
    renderFinanceiro();
    renderClientes();
  } catch (err) { toast(err.message, 'err'); }
  finally { btn.disabled = false; btn.textContent = 'Lançar'; }
});

document.getElementById('fFilterBtn').addEventListener('click', () => {
  const from = document.getElementById('fDateFrom').value;
  const to = document.getElementById('fDateTo').value;
  if (!from || !to) { toast('Preencha as duas datas para filtrar.', 'err'); return; }
  document.querySelectorAll('#financeiroPeriodChips .chip').forEach(c => c.classList.remove('active'));
  financeiroFilter = { from, to };
  renderFinanceiro();
});

async function renderFinanceiro() {
  let summary, list;
  if (financeiroFilter) {
    list = await api(`/ledger?from=${financeiroFilter.from}&to=${financeiroFilter.to}`, { auth: true });
    const totalIn = list.filter(l => l.type === 'entrada').reduce((s, l) => s + l.amount, 0);
    const totalOut = list.filter(l => l.type === 'saida').reduce((s, l) => s + l.amount, 0);
    summary = { totalIn, totalOut, saldo: totalIn - totalOut, from: financeiroFilter.from, to: financeiroFilter.to };
  } else {
    summary = await api(`/ledger/summary?period=${financeiroPeriod}`, { auth: true });
    list = await api(`/ledger?from=${summary.from}&to=${summary.to}`, { auth: true });
  }

  document.getElementById('financeiroStatGrid').innerHTML = `
    <div class="stat-card"><div class="num" style="color:var(--success, #4a7a4a)">${brl(summary.totalIn)}</div><div class="label">Entradas</div></div>
    <div class="stat-card"><div class="num" style="color:var(--danger)">${brl(summary.totalOut)}</div><div class="label">Saídas</div></div>
    <div class="stat-card"><div class="num">${brl(summary.saldo)}</div><div class="label">Saldo (${summary.from} a ${summary.to})</div></div>`;

  const box = document.getElementById('ledgerList');
  box.innerHTML = list.length ? list.map(l => `
    <div class="order-card">
      <div class="oh">
        <div>
          <div class="code" style="color:${l.type === 'entrada' ? 'var(--success, #4a7a4a)' : 'var(--danger)'}">${l.type === 'entrada' ? '↑ Entrada' : '↓ Saída'} — ${brl(l.amount)}</div>
          <div style="font-size:12.5px; color:var(--ink-soft); margin-top:2px;">${new Date(l.entryDate + 'T00:00:00').toLocaleDateString('pt-BR')}${l.category ? ' · ' + escapeHtml(l.category) : ''}${l.customerName ? ' · ' + escapeHtml(l.customerName) : ''}</div>
        </div>
        <button class="remove-x" data-lgdel="${l.id}">excluir</button>
      </div>
      ${l.description ? `<div class="items">${escapeHtml(l.description)}</div>` : ''}
    </div>`).join('') : `<div class="empty-state" style="color:var(--cream-text)">Nenhum lançamento nesse período ainda.</div>`;

  box.querySelectorAll('[data-lgdel]').forEach(b => b.addEventListener('click', async () => {
    if (!confirm('Excluir este lançamento?')) return;
    try {
      await api(`/ledger/${b.dataset.lgdel}`, { method: 'DELETE', auth: true });
      toast('Lançamento excluído.');
      renderFinanceiro();
      renderClientes();
    } catch (err) { toast(err.message, 'err'); }
  }));
}

// ---------- Boot ----------
if (isLogged()) showApp(); else showLogin();
