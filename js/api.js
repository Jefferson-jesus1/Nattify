// Helper central de requisições à API
async function api(path, { method = 'GET', body, isForm = false, auth = false } = {}) {
  const headers = {};
  if (!isForm) headers['Content-Type'] = 'application/json';
  if (auth) {
    const token = localStorage.getItem('admin_token');
    if (token) headers['Authorization'] = `Bearer ${token}`;
  }
  // PHP só popula $_FILES em requisições POST — para PUT/PATCH com upload de imagem,
  // mandamos como POST com um campo "_method" que o back-end usa pra rotear certo.
  let realMethod = method;
  if (isForm && method !== 'GET' && method !== 'POST' && body instanceof FormData) {
    body.append('_method', method);
    realMethod = 'POST';
  }
  const res = await fetch(`/api${path}`, {
    method: realMethod,
    headers,
    body: body ? (isForm ? body : JSON.stringify(body)) : undefined
  });
  let data = null;
  try { data = await res.json(); } catch (e) { /* sem corpo */ }
  if (!res.ok) {
    const msg = (data && data.error) || 'Ocorreu um erro. Tente novamente.';
    throw new Error(msg);
  }
  return data;
}

function toast(message, type = 'ok') {
  const holder = document.getElementById('toast-holder');
  if (!holder) return alert(message);
  const el = document.createElement('div');
  el.className = 'toast' + (type === 'err' ? ' err' : '');
  el.textContent = message;
  holder.appendChild(el);
  setTimeout(() => el.remove(), 3200);
}

function brl(value) {
  return (Number(value) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function escapeHtml(str) {
  return String(str || '').replace(/[&<>"']/g, s => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[s]));
}

const STATUS_LABELS = {
  recebido: 'Pedido recebido',
  em_preparo: 'Em preparo',
  saiu_para_entrega: 'Saiu para entrega',
  entregue: 'Entregue',
  cancelado: 'Cancelado'
};
const PAYMENT_LABELS = { pix: 'Pix', cartao: 'Cartão', dinheiro: 'Dinheiro na entrega' };
const PAYMENT_STATUS_LABELS = {
  aguardando_pagamento: 'Aguardando pagamento',
  pago: 'Pago',
  pendente_na_entrega: 'A pagar na entrega'
};
