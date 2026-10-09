/**
 * Minha conta — "Pedir de novo"
 * Coloca no carrinho os itens de um pedido anterior que ainda estão no cardápio.
 * Os preços são os de hoje (o carrinho sempre usa os preços atuais).
 */
document.querySelectorAll('[data-pedir-novamente]').forEach((botao) => {
  botao.addEventListener('click', () => {
    const itens = JSON.parse(botao.dataset.pedirNovamente);
    for (const [id, qtd] of Object.entries(itens)) {
      Carrinho.definir(id, Carrinho.quantidade(id) + Number(qtd));
    }
    const aviso = botao.dataset.aviso;
    mostrarAviso(aviso ? `Itens adicionados ao carrinho. ${aviso}` : 'Itens adicionados ao carrinho 🧁');
    setTimeout(() => { location.href = 'carrinho.php'; }, aviso ? 1600 : 700);
  });
});
