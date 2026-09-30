/**
 * Painel administrativo — comportamentos gerais
 */
(() => {
  // Menu lateral no celular
  const lateral = document.getElementById('lateral');
  const fundo = document.getElementById('lateralFundo');
  const abrir = document.getElementById('abrirMenu');

  function alternarMenu(aberto) {
    lateral.classList.toggle('aberta', aberto);
    fundo.hidden = !aberto;
    abrir?.setAttribute('aria-expanded', String(aberto));
    document.body.style.overflow = aberto ? 'hidden' : '';
  }
  abrir?.addEventListener('click', () => alternarMenu(true));
  fundo?.addEventListener('click', () => alternarMenu(false));
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') alternarMenu(false); });

  // Confirmação antes de ações irreversíveis (cancelar pedido, excluir produto)
  document.querySelectorAll('form[data-confirmar]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      if (!confirm(form.dataset.confirmar)) e.preventDefault();
    });
  });

  // Evita envio duplo dos formulários
  document.querySelectorAll('form[method="post"]').forEach((form) => {
    form.addEventListener('submit', (e) => {
      if (e.defaultPrevented) return;
      setTimeout(() => form.querySelectorAll('button[type="submit"]').forEach((b) => { b.disabled = true; }), 0);
    });
  });
})();

function mostrarAviso(texto) {
  const toast = document.getElementById('toast');
  if (!toast) return;
  toast.textContent = texto;
  toast.classList.add('visivel');
  clearTimeout(mostrarAviso.timer);
  mostrarAviso.timer = setTimeout(() => toast.classList.remove('visivel'), 3000);
}
