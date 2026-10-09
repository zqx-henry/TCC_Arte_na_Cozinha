/**
 * Contagem regressiva das promoções
 *
 * Qualquer elemento com data-fim="<segundos desde 1970>" mostra o tempo que falta,
 * atualizado a cada segundo. Usa a hora do SERVIDOR como referência (window.AGORA_SERVIDOR),
 * para o contador ficar certo mesmo se o relógio do celular estiver adiantado/atrasado.
 *
 * Quando o tempo acaba, a página é recarregada: o servidor já encerra a promoção e
 * o produto volta ao preço normal (inclusive no carrinho).
 */
const Contagem = (() => {
  const diferenca = window.AGORA_SERVIDOR ? window.AGORA_SERVIDOR * 1000 - Date.now() : 0;
  const agora = () => Date.now() + diferenca;

  /** 2d 04h 12min · 3h 05min 09s · 04min 33s */
  function formatar(segundos) {
    const s = Math.max(0, Math.floor(segundos));
    const d = Math.floor(s / 86400);
    const h = Math.floor((s % 86400) / 3600);
    const m = Math.floor((s % 3600) / 60);
    const seg = s % 60;
    const dois = (n) => String(n).padStart(2, '0');
    if (d > 0) return `${d}d ${dois(h)}h ${dois(m)}min`;
    if (h > 0) return `${h}h ${dois(m)}min ${dois(seg)}s`;
    return `${dois(m)}min ${dois(seg)}s`;
  }

  let recarregando = false;

  function atualizar() {
    document.querySelectorAll('[data-fim]').forEach((el) => {
      const restante = Number(el.dataset.fim) - agora() / 1000;
      const alvo = el.querySelector('.contagem-valor') || el;
      if (restante <= 0) {
        alvo.textContent = 'encerrada';
        el.classList.add('contagem-acabou');
        // Recarrega uma vez para mostrar o preço normal (o painel também se atualiza)
        if (!recarregando && el.dataset.recarregar !== 'nao') {
          recarregando = true;
          // Proteção: no máximo uma recarga por promoção encerrada (evita ficar recarregando sem parar)
          const chave = 'arteNaCozinha.recarga.' + el.dataset.fim;
          let jaRecarregou = false;
          try { jaRecarregou = sessionStorage.getItem(chave) === '1'; sessionStorage.setItem(chave, '1'); } catch { /* ignora */ }
          if (!jaRecarregou) setTimeout(() => location.reload(), 1500);
        }
        return;
      }
      alvo.textContent = formatar(restante);
      el.classList.toggle('contagem-urgente', restante < 3600); // última hora: destaque
    });
  }

  atualizar();
  setInterval(atualizar, 1000);
  return { formatar, atualizar, agora };
})();
