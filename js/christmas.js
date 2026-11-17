// Efeitos do Tema de Natal. Só inicializa quando <html data-theme="natal"> (definido por /api/theme/script).
(function () {
  var root = document.documentElement;
  if (root.getAttribute('data-theme') !== 'natal') return;

  try {
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var finePointer = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    root.classList.add('xmas-js');
    if (!reduce) root.classList.add('xmas-live');

    // ---- Pausa o movimento ambiente com a aba oculta ou com o hero fora da tela ----
    var hero = document.querySelector('.hero');
    var heroVisible = true;
    function syncPause() {
      root.classList.toggle('xmas-paused', document.hidden || !heroVisible);
    }
    document.addEventListener('visibilitychange', syncPause);

    // ---- Rodapé: detalhe natalino ao chegar no fim ----
    var footOrn = document.querySelector('.xmas-footer-orn');
    if ('IntersectionObserver' in window) {
      if (hero) {
        new IntersectionObserver(function (es) { heroVisible = es[0].isIntersecting; syncPause(); }).observe(hero);
      }
      if (footOrn) {
        var fo = new IntersectionObserver(function (es) {
          if (es[0].isIntersecting) { footOrn.classList.add('in-view'); fo.disconnect(); }
        }, { threshold: 0.3 });
        fo.observe(footOrn);
      }
    } else if (footOrn) {
      footOrn.classList.add('in-view');
    }

    // ---- Ornamentos respondem levemente ao ponteiro (apenas mouse, sem movimento reduzido) ----
    if (hero && finePointer && !reduce) {
      var layers = Array.prototype.slice.call(hero.querySelectorAll('.orn-move'));
      var tx = 0, ty = 0, raf = 0;
      function apply() {
        raf = 0;
        layers.forEach(function (el, i) {
          var dir = i % 2 === 0 ? 1 : -1;
          el.style.transform = 'translate3d(' + (tx * 9 * dir).toFixed(2) + 'px,' + (ty * 6).toFixed(2) + 'px,0) rotate(' + (tx * 1.2 * dir).toFixed(2) + 'deg)';
        });
      }
      function queue() { if (!raf) raf = requestAnimationFrame(apply); }
      hero.addEventListener('pointermove', function (e) {
        if (e.pointerType && e.pointerType !== 'mouse') return;
        var r = hero.getBoundingClientRect();
        tx = Math.max(-1, Math.min(1, ((e.clientX - r.left) / r.width - 0.5) * 2));
        ty = Math.max(-1, Math.min(1, ((e.clientY - r.top) / r.height - 0.5) * 2));
        queue();
      });
      hero.addEventListener('pointerleave', function () { tx = 0; ty = 0; queue(); });
    }

    // ---- Cards acessíveis por teclado (Enter/Espaço abrem o detalhe, como o clique) ----
    var grid = document.getElementById('productGrid');
    function enhanceCards() {
      if (!grid) return;
      grid.querySelectorAll('.cup-card[data-open]:not([tabindex])').forEach(function (card) {
        card.setAttribute('tabindex', '0');
        card.setAttribute('role', 'button');
        var t = card.querySelector('h3');
        if (t) card.setAttribute('aria-label', 'Ver detalhes: ' + t.textContent);
      });
    }
    if (grid) {
      grid.addEventListener('keydown', function (e) {
        var card = e.target.closest && e.target.closest('.cup-card[data-open]');
        if (card && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); card.click(); }
      });
      if ('MutationObserver' in window) new MutationObserver(enhanceCards).observe(grid, { childList: true });
      enhanceCards();
    }
  } catch (err) {
    // Falha nos efeitos não pode afetar o cardápio: o conteúdo continua visível.
    root.classList.remove('xmas-live');
  }
})();
