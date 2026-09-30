/**
 * Cardápio digital (Figura 8)
 * RF06 – pesquisa pelo nome e promoções · RF07 – filtro por categoria
 */
(() => {
  const campoBusca   = document.getElementById('campoBusca');
  const chips        = document.querySelectorAll('.chip');
  const listaCard    = document.getElementById('listaCardapio');
  const cards        = listaCard ? listaCard.querySelectorAll('.card-produto') : [];
  const semResult    = document.getElementById('semResultados');
  const maisPedidos  = document.getElementById('secaoMaisPedidos');
  const titulo       = document.getElementById('tituloCardapio');
  const totalResult  = document.getElementById('totalResultados');
  const barra        = document.getElementById('barraCarrinho');

  let categoriaAtual = 'Todos';

  // Permite abrir o cardápio já filtrado: index.php?categoria=Bolos
  const params = new URLSearchParams(location.search);
  if (params.get('categoria')) categoriaAtual = params.get('categoria');

  /** Remove acentos para a busca funcionar com "pao" ou "pão" */
  const normalizar = (t) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

  function filtrar() {
    const termo = normalizar(campoBusca.value);
    let visiveis = 0;

    cards.forEach((card) => {
      const okCategoria = categoriaAtual === 'Todos'
        || (categoriaAtual === 'Promoções' ? card.dataset.promo === '1' : card.dataset.categoria === categoriaAtual);
      const okBusca = !termo || normalizar(card.dataset.busca).includes(termo);
      const mostrar = okCategoria && okBusca;
      card.hidden = !mostrar;
      if (mostrar) visiveis++;
    });

    const filtrando = termo || categoriaAtual !== 'Todos';
    if (maisPedidos) maisPedidos.hidden = Boolean(filtrando);
    semResult.hidden = visiveis > 0;
    titulo.textContent = termo ? `Resultados para “${campoBusca.value.trim()}”`
      : categoriaAtual === 'Todos' ? 'Cardápio completo' : categoriaAtual;
    totalResult.textContent = `${visiveis} ${visiveis === 1 ? 'item' : 'itens'}`;

    chips.forEach((c) => c.classList.toggle('ativo', c.dataset.categoria === categoriaAtual));
  }

  chips.forEach((chip) => chip.addEventListener('click', () => {
    categoriaAtual = chip.dataset.categoria;
    filtrar();
    const url = new URL(location.href);
    if (categoriaAtual === 'Todos') url.searchParams.delete('categoria'); else url.searchParams.set('categoria', categoriaAtual);
    history.replaceState(null, '', url);
  }));

  let espera;
  campoBusca.addEventListener('input', () => { clearTimeout(espera); espera = setTimeout(filtrar, 120); });

  // -------------------------------------------------------------------
  // Botões "+" viram seletor de quantidade quando o item está no carrinho
  // -------------------------------------------------------------------
  const svg = (d) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="${d}"/></svg>`;

  function desenharAcoes() {
    document.querySelectorAll('.card-acao').forEach((acao) => {
      const id = acao.dataset.acao;
      const qtd = Carrinho.quantidade(id);
      const nome = window.PRODUTOS[id]?.nome || 'produto';
      if (qtd > 0) {
        acao.innerHTML = `<div class="quantidade">
            <button type="button" data-menos="${id}" aria-label="Diminuir ${nome}">${svg('M5 12h14')}</button>
            <span aria-live="polite">${qtd}</span>
            <button type="button" data-adicionar="${id}" aria-label="Adicionar mais ${nome}">${svg('M12 5v14M5 12h14')}</button>
          </div>`;
      } else {
        acao.innerHTML = `<button type="button" class="botao-adicionar" data-adicionar="${id}" aria-label="Adicionar ${nome} ao carrinho">${svg('M12 5v14M5 12h14')}</button>`;
      }
    });

    const total = Carrinho.totalItens();
    barra.hidden = total === 0;
    document.getElementById('barraCarrinhoTexto').textContent = `Ver carrinho · ${total} ${total === 1 ? 'item' : 'itens'}`;
    document.getElementById('barraCarrinhoTotal').textContent = formatarDinheiro(Carrinho.subtotal());
  }

  document.addEventListener('click', (e) => {
    const mais = e.target.closest('[data-adicionar]');
    const menos = e.target.closest('[data-menos]');
    if (mais) {
      const id = mais.dataset.adicionar;
      const primeiraVez = Carrinho.quantidade(id) === 0;
      Carrinho.adicionar(id);
      if (primeiraVez) mostrarAviso(`${window.PRODUTOS[id].nome} adicionado ao carrinho 🧁`);
    } else if (menos) {
      Carrinho.diminuir(menos.dataset.menos);
    }
  });

  document.addEventListener('carrinho:alterado', desenharAcoes);
  // Atualiza se o carrinho for alterado em outra aba
  window.addEventListener('storage', () => { desenharAcoes(); Carrinho.atualizarContador(); });

  desenharAcoes();
  filtrar();
})();
