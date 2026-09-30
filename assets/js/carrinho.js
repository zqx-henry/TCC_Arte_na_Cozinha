/**
 * Carrinho de compras (RF02)
 * Guarda os itens no navegador (localStorage) no formato { idProduto: quantidade }.
 * Os preços NÃO ficam salvos aqui: o servidor recalcula tudo ao finalizar,
 * usando os preços do banco de dados (segurança).
 */
const Carrinho = (() => {
  const CHAVE = 'arteNaCozinha.carrinho';
  const MAX_QTD = 50;

  function ler() {
    try {
      const dados = JSON.parse(localStorage.getItem(CHAVE)) || {};
      return typeof dados === 'object' && dados !== null ? dados : {};
    } catch {
      return {};
    }
  }

  function gravar(itens) {
    try { localStorage.setItem(CHAVE, JSON.stringify(itens)); } catch { /* modo privado */ }
    document.dispatchEvent(new CustomEvent('carrinho:alterado', { detail: itens }));
    atualizarContador();
  }

  /** Remove produtos que não existem mais / estão indisponíveis */
  function limparInvalidos() {
    if (!window.PRODUTOS) return;
    const itens = ler();
    let mudou = false;
    for (const id of Object.keys(itens)) {
      if (!window.PRODUTOS[id] || !(itens[id] > 0)) { delete itens[id]; mudou = true; }
    }
    if (mudou) gravar(itens);
  }

  function quantidade(id) { return ler()[id] || 0; }

  function definir(id, qtd) {
    const itens = ler();
    qtd = Math.max(0, Math.min(MAX_QTD, parseInt(qtd, 10) || 0));
    if (qtd === 0) delete itens[id]; else itens[id] = qtd;
    gravar(itens);
  }

  function adicionar(id) { definir(id, quantidade(id) + 1); }
  function diminuir(id) { definir(id, quantidade(id) - 1); }
  function esvaziar() { gravar({}); }

  function totalItens() {
    return Object.values(ler()).reduce((s, q) => s + q, 0);
  }

  function subtotal() {
    const produtos = window.PRODUTOS || {};
    return Object.entries(ler()).reduce((s, [id, q]) => s + (produtos[id] ? produtos[id].preco * q : 0), 0);
  }

  function atualizarContador() {
    const el = document.getElementById('contadorCarrinho');
    if (!el) return;
    const total = totalItens();
    el.textContent = total;
    el.hidden = total === 0;
    el.classList.remove('pulo');
    void el.offsetWidth; // reinicia a animação
    el.classList.add('pulo');
  }

  return { ler, quantidade, definir, adicionar, diminuir, esvaziar, totalItens, subtotal, atualizarContador, limparInvalidos };
})();

/** Formata número como moeda brasileira */
function formatarDinheiro(valor) {
  return valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

/** Aviso rápido no topo da tela */
function mostrarAviso(texto) {
  const toast = document.getElementById('toast');
  if (!toast) return;
  toast.textContent = texto;
  toast.classList.add('visivel');
  clearTimeout(mostrarAviso.timer);
  mostrarAviso.timer = setTimeout(() => toast.classList.remove('visivel'), 2200);
}

// Borda no cabeçalho ao rolar a página
window.addEventListener('scroll', () => {
  document.getElementById('topo')?.classList.toggle('rolado', window.scrollY > 8);
}, { passive: true });

Carrinho.limparInvalidos();
Carrinho.atualizarContador();
