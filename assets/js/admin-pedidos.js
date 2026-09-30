/**
 * Painel – Pedidos: avisa em tempo real quando chega um pedido novo.
 * Consulta admin/api_novos.php a cada 15 segundos.
 */
(() => {
  let ultimoConhecido = window.ULTIMO_PEDIDO || 0;
  const aviso = document.getElementById('avisoNovo');
  const atualizado = document.getElementById('atualizadoEm');
  let ultimaConsulta = Date.now();

  /** Som curto de notificação (gerado pelo navegador, sem arquivo de áudio) */
  function tocarSom() {
    try {
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      [0, 0.18].forEach((atraso, i) => {
        const osc = ctx.createOscillator();
        const vol = ctx.createGain();
        osc.frequency.value = i ? 1046 : 784;
        vol.gain.setValueAtTime(0.18, ctx.currentTime + atraso);
        vol.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + atraso + 0.25);
        osc.connect(vol).connect(ctx.destination);
        osc.start(ctx.currentTime + atraso);
        osc.stop(ctx.currentTime + atraso + 0.3);
      });
    } catch { /* navegador sem áudio */ }
  }

  async function verificar() {
    try {
      const resp = await fetch('api_novos.php', { cache: 'no-store' });
      if (resp.status === 401) { location.href = 'login.php'; return; }
      const json = await resp.json();
      ultimaConsulta = Date.now();
      if (json.ultimo > ultimoConhecido) {
        ultimoConhecido = json.ultimo;
        aviso.hidden = false;
        tocarSom();
        mostrarAviso('🔔 Novo pedido recebido!');
        document.title = '(novo) ' + document.title.replace(/^\(novo\) /, '');
      }
    } catch { /* sem conexão: tenta de novo */ }
  }

  setInterval(verificar, 15000);
  setInterval(() => {
    const seg = Math.round((Date.now() - ultimaConsulta) / 1000);
    atualizado.textContent = seg < 20 ? 'atualizado agora' : `atualizado há ${seg}s`;
  }, 5000);
})();
