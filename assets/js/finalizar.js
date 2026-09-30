/**
 * Carrinho e finalização do pedido (Figura 9)
 * RF01 – pedido sem cadastro · RF02 – carrinho · RF04/RF05 – pagamento
 */
(() => {
  const form        = document.getElementById('formPedido');
  const vazio       = document.getElementById('carrinhoVazio');
  const listaItens  = document.getElementById('listaItens');
  const erroGeral   = document.getElementById('erroGeral');
  const botao       = document.getElementById('botaoFinalizar');
  const modal       = document.getElementById('modalPagamento');
  const campoTroco  = document.getElementById('campoTroco');
  const CHAVE_DADOS = 'arteNaCozinha.dadosEntrega';

  const svg = (d) => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" aria-hidden="true"><path d="${d}"/></svg>`;
  const esc = (t) => String(t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  // -------------------------------------------------------------------
  // Itens e totais
  // -------------------------------------------------------------------
  function total() { return Carrinho.subtotal() + window.TAXA_ENTREGA; }

  function desenhar() {
    const itens = Carrinho.ler();
    const ids = Object.keys(itens);
    form.hidden = ids.length === 0;
    vazio.hidden = ids.length > 0;
    if (!ids.length) return;

    listaItens.innerHTML = ids.map((id) => {
      const p = window.PRODUTOS[id];
      const promo = p.preco < p.normal ? `<span class="preco-antigo">${formatarDinheiro(p.normal)}</span>` : '';
      return `<div class="item-carrinho">
          <div>
            <div class="nome">${esc(p.nome)}</div>
            <span class="preco">${formatarDinheiro(p.preco)}</span>${promo}
          </div>
          <div class="quantidade">
            <button type="button" data-menos="${id}" aria-label="Diminuir ${esc(p.nome)}">${svg('M5 12h14')}</button>
            <span>${itens[id]}</span>
            <button type="button" data-mais="${id}" aria-label="Aumentar ${esc(p.nome)}">${svg('M12 5v14M5 12h14')}</button>
          </div>
        </div>`;
    }).join('');

    document.getElementById('valorSubtotal').textContent = formatarDinheiro(Carrinho.subtotal());
    document.getElementById('valorTotal').textContent = formatarDinheiro(total());
  }

  listaItens.addEventListener('click', (e) => {
    const mais = e.target.closest('[data-mais]');
    const menos = e.target.closest('[data-menos]');
    if (mais) Carrinho.adicionar(mais.dataset.mais);
    if (menos) {
      const id = menos.dataset.menos;
      if (Carrinho.quantidade(id) === 1) mostrarAviso(`${window.PRODUTOS[id].nome} removido do carrinho`);
      Carrinho.diminuir(id);
    }
  });
  document.addEventListener('carrinho:alterado', desenhar);

  // -------------------------------------------------------------------
  // Máscara do WhatsApp: (15) 99999-0000
  // -------------------------------------------------------------------
  const tel = document.getElementById('telefone');
  tel.addEventListener('input', () => {
    const d = tel.value.replace(/\D/g, '').slice(0, 11);
    let v = d;
    if (d.length > 2) v = `(${d.slice(0, 2)}) ${d.slice(2)}`;
    if (d.length > 7) v = `(${d.slice(0, 2)}) ${d.slice(2, d.length - 4)}-${d.slice(-4)}`;
    tel.value = v;
  });

  // Lembra os dados de entrega neste aparelho (facilita o próximo pedido)
  try {
    const salvos = JSON.parse(localStorage.getItem(CHAVE_DADOS)) || {};
    ['nome', 'telefone', 'endereco', 'bairro', 'complemento'].forEach((c) => {
      if (salvos[c]) form.elements[c].value = salvos[c];
    });
  } catch { /* ignora */ }

  // -------------------------------------------------------------------
  // Forma de pagamento: site (PIX, cartão) × entrega (cartão, dinheiro)
  // -------------------------------------------------------------------
  function atualizarPagamento() {
    const quando = form.elements.quando.value;
    document.querySelectorAll('.opcao').forEach((op) => {
      op.hidden = !op.dataset.quando.split(' ').includes(quando);
    });
    const marcada = form.querySelector('input[name="forma"]:checked');
    if (!marcada || marcada.closest('.opcao').hidden) {
      form.querySelector(`.opcao:not([hidden]) input`).checked = true;
    }
    document.getElementById('dicaPagamento').textContent = quando === 'site'
      ? 'Pelo site: PIX ou cartão, com pagamento na hora.'
      : 'Na entrega: cartão ou dinheiro (o entregador leva a maquininha).';
    campoTroco.hidden = form.elements.forma.value !== 'dinheiro';
  }
  form.addEventListener('change', (e) => {
    if (e.target.name === 'quando' || e.target.name === 'forma') atualizarPagamento();
  });

  // -------------------------------------------------------------------
  // Validação (CT04: campo obrigatório vazio exibe aviso e não registra)
  // -------------------------------------------------------------------
  function validar() {
    let primeiroErro = null;
    const regras = {
      nome: (v) => v.trim().length >= 2,
      telefone: (v) => /^\d{10,11}$/.test(v.replace(/\D/g, '')),
      endereco: (v) => v.trim().length >= 5,
      bairro: (v) => v.trim().length >= 2,
    };
    for (const [campo, ok] of Object.entries(regras)) {
      const input = form.elements[campo];
      const valido = ok(input.value);
      input.closest('.campo').classList.toggle('erro', !valido);
      input.setAttribute('aria-invalid', String(!valido));
      if (!valido && !primeiroErro) primeiroErro = input;
    }
    if (primeiroErro) {
      primeiroErro.focus();
      primeiroErro.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    return !primeiroErro;
  }
  form.addEventListener('input', (e) => {
    const campo = e.target.closest('.campo.erro');
    if (campo) campo.classList.remove('erro');
  });

  // -------------------------------------------------------------------
  // Pagamento simulado (PIX / cartão) — apresentação do TCC
  // -------------------------------------------------------------------
  function desenharQrSimulado() {
    const n = 25, t = 8;
    let quad = '';
    const finder = (x, y) => (x >= 0 && x < 7 && y >= 0 && y < 7);
    for (let y = 0; y < n; y++) {
      for (let x = 0; x < n; x++) {
        const emFinder = finder(x, y) || finder(x - (n - 7), y) || finder(x, y - (n - 7));
        if (emFinder) continue;
        if (Math.random() > 0.52) quad += `<rect x="${x * t}" y="${y * t}" width="${t}" height="${t}"/>`;
      }
    }
    const olho = (x, y) => `<rect x="${x}" y="${y}" width="56" height="56" fill="#4A2A2F"/><rect x="${x + 8}" y="${y + 8}" width="40" height="40" fill="#fff"/><rect x="${x + 16}" y="${y + 16}" width="24" height="24" fill="#4A2A2F"/>`;
    document.getElementById('qrPix').innerHTML =
      `<svg viewBox="0 0 200 200" width="100%" height="100%"><g fill="#4A2A2F">${quad}</g>${olho(0, 0)}${olho(144, 0)}${olho(0, 144)}
       <circle cx="100" cy="100" r="18" fill="#fff"/><image href="assets/img/logo-icone.svg" x="86" y="86" width="28" height="28"/></svg>`;
    const aleatorio = Array.from(crypto.getRandomValues(new Uint8Array(10)), (b) => b.toString(16).padStart(2, '0')).join('');
    document.getElementById('codigoPix').value = `00020126BR.GOV.BCB.PIX0114ARTENACOZINHA${aleatorio}5204000053039865802BR`;
  }

  function abrirPagamento() {
    return new Promise((resolve) => {
      const forma = form.elements.forma.value;
      document.getElementById('pagamentoPix').hidden = forma !== 'pix';
      document.getElementById('pagamentoCartao').hidden = forma !== 'cartao';
      document.getElementById('valorModal').textContent = formatarDinheiro(total());
      if (forma === 'pix') desenharQrSimulado();
      modal.hidden = false;
      document.getElementById('confirmarPagamento').focus();

      const fechar = (resultado) => {
        modal.hidden = true;
        document.getElementById('confirmarPagamento').onclick = null;
        document.getElementById('cancelarPagamento').onclick = null;
        resolve(resultado);
      };
      document.getElementById('confirmarPagamento').onclick = () => fechar(true);
      document.getElementById('cancelarPagamento').onclick = () => fechar(false);
      modal.onclick = (e) => { if (e.target === modal) fechar(false); };
    });
  }

  document.getElementById('copiarPix').addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(document.getElementById('codigoPix').value); mostrarAviso('Código PIX copiado!'); }
    catch { document.getElementById('codigoPix').select(); }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.hidden) document.getElementById('cancelarPagamento').click();
  });

  // -------------------------------------------------------------------
  // Envio do pedido para o servidor (api/pedido.php)
  // -------------------------------------------------------------------
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    erroGeral.hidden = true;
    if (!window.LOJA_ABERTA) return;
    if (!validar()) return;

    const pagarNoSite = form.elements.quando.value === 'site';
    if (pagarNoSite && !(await abrirPagamento())) return;

    let observacao = form.elements.observacao.value.trim();
    if (form.elements.forma.value === 'dinheiro' && form.elements.troco.value.trim()) {
      observacao = `Troco para ${form.elements.troco.value.trim()}` + (observacao ? ` · ${observacao}` : '');
    }

    const dados = {
      csrf: form.elements.csrf.value,
      nome: form.elements.nome.value.trim(),
      telefone: form.elements.telefone.value,
      endereco: form.elements.endereco.value.trim(),
      bairro: form.elements.bairro.value.trim(),
      complemento: form.elements.complemento.value.trim(),
      observacao: observacao.slice(0, 255),
      forma_pagamento: form.elements.forma.value,
      pago_no_site: pagarNoSite,
      itens: Carrinho.ler(),
    };

    botao.disabled = true;
    botao.innerHTML = '<span class="carregando"></span> Enviando pedido...';

    try {
      const resp = await fetch('api/pedido.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(dados),
      });
      const json = await resp.json();
      if (!resp.ok || !json.ok) throw new Error(json.erro || 'Não foi possível registrar o pedido.');

      try {
        localStorage.setItem(CHAVE_DADOS, JSON.stringify({
          nome: dados.nome, telefone: dados.telefone, endereco: dados.endereco,
          bairro: dados.bairro, complemento: dados.complemento,
        }));
        localStorage.setItem('arteNaCozinha.ultimoPedido', JSON.stringify({ id: json.id, url: json.url }));
      } catch { /* ignora */ }

      Carrinho.esvaziar();
      location.href = json.url + '&novo=1';
    } catch (err) {
      erroGeral.textContent = err.message;
      erroGeral.hidden = false;
      erroGeral.scrollIntoView({ behavior: 'smooth', block: 'center' });
      botao.disabled = false;
      botao.textContent = 'Finalizar pedido';
    }
  });

  atualizarPagamento();
  desenhar();
})();
