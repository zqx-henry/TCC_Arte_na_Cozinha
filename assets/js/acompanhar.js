/**
 * Acompanhamento em tempo real (RF03)
 * Consulta o status a cada 10 segundos; quando a loja muda a etapa no
 * painel, a tela é atualizada automaticamente.
 */
(() => {
  const tela = document.getElementById('telaPedido');
  if (!tela) return;
  const { pedido, token } = tela.dataset;
  let statusAtual = tela.dataset.status;
  const finais = ['entregue', 'cancelado'];

  async function verificar() {
    if (document.hidden || finais.includes(statusAtual)) return;
    try {
      const resp = await fetch(`api/status.php?pedido=${encodeURIComponent(pedido)}&t=${encodeURIComponent(token)}`, { cache: 'no-store' });
      const json = await resp.json();
      if (json.ok && json.status !== statusAtual) {
        statusAtual = json.status;
        if ('vibrate' in navigator) navigator.vibrate(120);
        mostrarAviso(`Seu pedido: ${json.rotulo}`);
        setTimeout(() => location.reload(), 900);
      }
    } catch { /* sem internet: tenta de novo depois */ }
  }

  if (finais.includes(statusAtual)) {
    document.getElementById('avisoAtualizacao')?.remove();
  } else {
    setInterval(verificar, 10000);
    document.addEventListener('visibilitychange', verificar);
  }
})();
