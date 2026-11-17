// Controle do Tema de Natal (aba Aparência). A escolha é salva no servidor e vale para todos os visitantes.
(function () {
  const statusEl = document.getElementById('themeStatus');
  const btn = document.getElementById('themeToggleBtn');
  const msg = document.getElementById('themeMsg');
  if (!statusEl || !btn) return;
  let current = null;

  function render() {
    const on = current === 'natal';
    statusEl.textContent = on ? 'Ativado' : 'Desativado';
    statusEl.style.color = on ? 'var(--success)' : 'var(--brown-soft)';
    btn.textContent = on ? 'Voltar ao tema padrão' : 'Ativar tema de Natal';
    btn.disabled = false;
  }
  function show(text, ok) {
    msg.textContent = text;
    msg.style.color = ok ? 'var(--success)' : 'var(--danger)';
    msg.style.display = 'block';
  }

  async function load() {
    try {
      const r = await api('/theme');
      current = r.theme === 'natal' ? 'natal' : 'padrao';
      render();
    } catch (e) {
      statusEl.textContent = 'indisponível';
      show('Não foi possível ler o tema atual. Recarregue a página.', false);
    }
  }

  btn.addEventListener('click', async () => {
    const next = current === 'natal' ? 'padrao' : 'natal';
    btn.disabled = true;
    msg.style.display = 'none';
    try {
      const r = await api('/theme', { method: 'POST', body: { theme: next }, auth: true });
      current = r.theme;
      render();
      show(current === 'natal' ? 'Tema de Natal ativado. Os clientes verão ao abrir ou atualizar o cardápio.' : 'Tema padrão restaurado no cardápio.', true);
    } catch (e) {
      render();
      show(e.message || 'Não foi possível salvar o tema. Tente novamente.', false);
    }
  });

  load();
})();
