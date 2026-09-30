<?php
/**
 * Carrinho e finalização do pedido (Figura 9)
 * RF01 – pedido sem cadastro · RF02 – carrinho
 * RF04 – pagar pelo site ou na entrega · RF05 – PIX, cartão e dinheiro
 */
require_once __DIR__ . '/includes/bootstrap.php';

$produtos = db()->query('SELECT * FROM produto WHERE disponivel = 1')->fetchAll();
$dadosJs = [];
foreach ($produtos as $p) {
    $dadosJs[$p['id_produto']] = [
        'nome'   => $p['nome'],
        'preco'  => preco_atual($p),
        'normal' => (float) $p['preco'],
        'imagem' => $p['imagem'],
    ];
}

$tituloPagina  = 'Meu carrinho | Arte na Cozinha';
$paginaAtual   = 'carrinho';
$scriptsPagina = ['assets/js/finalizar.js'];
require __DIR__ . '/includes/cabecalho.php';
?>

<div class="container">
  <div class="cabecalho-pagina">
    <a href="index.php" class="botao-icone" aria-label="Voltar ao cardápio"><?= icone('voltar') ?></a>
    <h1>Meu carrinho</h1>
  </div>

  <div class="vazio cartao" id="carrinhoVazio" hidden>
    <span class="emoji">🧁</span>
    <p style="margin-bottom:16px">Seu carrinho está vazio.<br>Que tal escolher um docinho?</p>
    <a href="index.php" class="botao">Ver cardápio</a>
  </div>

  <form id="formPedido" class="layout-carrinho" novalidate hidden>
    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">

    <div class="coluna-principal">
      <div id="erroGeral" class="alerta alerta-erro" role="alert" hidden></div>
      <?php if (!loja_aberta()): ?>
        <div class="alerta alerta-info">A loja está fechada no momento (<?= h(config_loja('horario')) ?>). Seu carrinho fica salvo para quando abrirmos.</div>
      <?php endif; ?>

      <section class="cartao" aria-labelledby="tItens">
        <h2 id="tItens">Itens do pedido</h2>
        <div id="listaItens"></div>
      </section>

      <section class="cartao" aria-labelledby="tEntrega">
        <h2 id="tEntrega">Dados para entrega <small>· sem cadastro</small></h2>
        <div class="campos">
          <div class="campos campos-duplos">
            <div class="campo">
              <label for="nome">Nome</label>
              <input id="nome" name="nome" autocomplete="name" maxlength="100" required placeholder=" ">
              <span class="msg-erro">Informe seu nome.</span>
            </div>
            <div class="campo">
              <label for="telefone">WhatsApp</label>
              <input id="telefone" name="telefone" type="tel" inputmode="tel" autocomplete="tel" maxlength="16" required placeholder="(15) 99999-0000">
              <span class="msg-erro">Informe um WhatsApp válido com DDD.</span>
            </div>
          </div>
          <div class="campo">
            <label for="endereco">Endereço (rua e número)</label>
            <input id="endereco" name="endereco" autocomplete="street-address" maxlength="150" required placeholder="Rua das Flores, 120">
            <span class="msg-erro">Informe o endereço de entrega.</span>
          </div>
          <div class="campos campos-duplos">
            <div class="campo">
              <label for="bairro">Bairro</label>
              <input id="bairro" name="bairro" maxlength="60" required placeholder="Centro">
              <span class="msg-erro">Informe o bairro.</span>
            </div>
            <div class="campo">
              <label for="complemento">Complemento (opcional)</label>
              <input id="complemento" name="complemento" maxlength="60" placeholder="Apto, bloco, referência">
            </div>
          </div>
          <div class="campo">
            <label for="observacao">Observações (opcional)</label>
            <textarea id="observacao" name="observacao" maxlength="200" placeholder="Ex.: sem granulado, tocar a campainha..."></textarea>
          </div>
        </div>
      </section>

      <section class="cartao" aria-labelledby="tPagamento">
        <h2 id="tPagamento">Forma de pagamento</h2>
        <div class="alternador" role="radiogroup" aria-label="Quando pagar">
          <label><input type="radio" name="quando" value="site" checked><span>Pagar pelo site</span></label>
          <label><input type="radio" name="quando" value="entrega"><span>Pagar na entrega</span></label>
        </div>
        <p class="dica" id="dicaPagamento">Pelo site: PIX ou cartão. Na entrega: cartão ou dinheiro.</p>
        <div class="opcoes-pagamento">
          <label class="opcao" data-quando="site">
            <input type="radio" name="forma" value="pix" checked>
            <span class="rotulo">PIX</span><span class="extra">aprovação na hora</span>
          </label>
          <label class="opcao" data-quando="site entrega">
            <input type="radio" name="forma" value="cartao">
            <span class="rotulo">Cartão de crédito ou débito</span>
          </label>
          <label class="opcao" data-quando="entrega" hidden>
            <input type="radio" name="forma" value="dinheiro">
            <span class="rotulo">Dinheiro</span>
          </label>
        </div>
        <div class="campo" id="campoTroco" hidden style="margin-top:12px">
          <label for="troco">Troco para quanto? (opcional)</label>
          <input id="troco" name="troco" inputmode="decimal" maxlength="10" placeholder="R$ 50,00">
        </div>
      </section>
    </div>

    <aside class="coluna-resumo">
      <section class="cartao" aria-label="Resumo do pedido">
        <div class="resumo-linha"><span>Subtotal</span><span id="valorSubtotal">R$ 0,00</span></div>
        <div class="resumo-linha"><span>Taxa de entrega</span><span id="valorTaxa"><?= dinheiro(config_loja('taxa_entrega')) ?></span></div>
        <div class="resumo-linha resumo-total"><span>Total</span><span id="valorTotal">R$ 0,00</span></div>
      </section>

      <button type="submit" class="botao botao-bloco botao-grande" id="botaoFinalizar" <?= loja_aberta() ? '' : 'disabled' ?>>
        Finalizar pedido
      </button>
      <p class="nota">Você receberá a confirmação do pedido pelo WhatsApp.</p>
      <p class="nota" style="font-size:.8rem">Usamos seus dados apenas para a entrega. <a href="privacidade.php">Aviso de privacidade</a>.</p>
    </aside>
  </form>
</div>

<!-- Pagamento pelo site (simulação para a apresentação do TCC) -->
<div class="modal" id="modalPagamento" hidden role="dialog" aria-modal="true" aria-labelledby="tituloModal">
  <div class="modal-caixa">
    <span class="selo-demo">Ambiente de demonstração · nenhum valor é cobrado</span>
    <div id="pagamentoPix">
      <h2 id="tituloModal">Pagar com PIX</h2>
      <p class="dica" style="margin:0">Escaneie o QR Code no app do seu banco ou use o código copia e cola.</p>
      <div class="qr" id="qrPix" aria-label="QR Code PIX simulado"></div>
      <div class="copia-cola">
        <input id="codigoPix" readonly aria-label="Código PIX copia e cola">
        <button type="button" class="botao botao-contorno" id="copiarPix" style="min-height:42px;padding:8px 14px">Copiar</button>
      </div>
    </div>
    <div id="pagamentoCartao" hidden>
      <h2>Pagar com cartão</h2>
      <p class="dica" style="margin:0">Na versão final, esta etapa abre o checkout seguro da instituição de pagamento escolhida.</p>
      <div class="cartao-simulado">
        <div>Arte na Cozinha</div>
        <div class="numero">•••• •••• •••• 0000</div>
        <div>CARTÃO DE DEMONSTRAÇÃO</div>
      </div>
    </div>
    <div class="resumo-linha resumo-total" style="margin:6px 0 16px"><span>Total</span><span id="valorModal"></span></div>
    <button type="button" class="botao botao-bloco botao-grande" id="confirmarPagamento">Confirmar pagamento</button>
    <button type="button" class="botao botao-contorno botao-bloco" id="cancelarPagamento" style="margin-top:10px">Voltar</button>
  </div>
</div>

<script>
  window.PRODUTOS = <?= json_encode($dadosJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
  window.TAXA_ENTREGA = <?= json_encode((float) config_loja('taxa_entrega')) ?>;
  window.LOJA_ABERTA = <?= loja_aberta() ? 'true' : 'false' ?>;
</script>

<?php require __DIR__ . '/includes/rodape.php'; ?>
