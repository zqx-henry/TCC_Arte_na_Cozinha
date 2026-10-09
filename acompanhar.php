<?php
/**
 * Acompanhamento do pedido (Figura 10)
 * RF03 – acompanhar pedido · RF08 – confirmação pelo WhatsApp
 *
 * O link contém um código (t) gerado a partir do número do pedido;
 * assim, ninguém consegue ver o pedido de outra pessoa trocando o número (LGPD).
 */
require_once __DIR__ . '/includes/bootstrap.php';

$idPedido = (int) ($_GET['pedido'] ?? 0);
$token    = (string) ($_GET['t'] ?? '');
$erroBusca = '';

// Busca manual: número do pedido + WhatsApp usado no pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $num = (int) ($_POST['numero'] ?? 0);
    $tel = so_digitos((string) ($_POST['telefone'] ?? ''));
    $st = db()->prepare('SELECT c.telefone FROM pedido p JOIN cliente c ON c.id_cliente = p.id_cliente WHERE p.id_pedido = ?');
    $st->execute([$num]);
    $telPedido = $st->fetchColumn();
    if ($num > 0 && $telPedido && $tel !== '' && $telPedido === $tel) {
        redirecionar('acompanhar.php?pedido=' . $num . '&t=' . token_pedido($num));
    }
    $erroBusca = 'Não encontramos um pedido com esse número e WhatsApp. Confira os dados.';
}

$pedido = null;
if ($idPedido > 0 && hash_equals(token_pedido($idPedido), $token)) {
    $pedido = buscar_pedido($idPedido);
}

$tituloPagina  = $pedido ? 'Pedido nº ' . $pedido['id_pedido'] . ' | Arte na Cozinha' : 'Acompanhar pedido | Arte na Cozinha';
$paginaAtual   = 'acompanhar';
$scriptsPagina = $pedido ? ['assets/js/acompanhar.js'] : [];
require __DIR__ . '/includes/cabecalho.php';

// ---------------------------------------------------------------------
// Tela de busca (sem pedido)
// ---------------------------------------------------------------------
if (!$pedido): ?>
<div class="container" style="max-width:560px">
  <div class="cabecalho-pagina">
    <a href="index.php" class="botao-icone" aria-label="Voltar ao cardápio"><?= icone('voltar') ?></a>
    <h1>Acompanhar pedido</h1>
  </div>

  <?php if ($idPedido > 0): ?>
    <div class="alerta alerta-erro">Link de acompanhamento inválido. Use o número do pedido e o WhatsApp abaixo.</div>
  <?php endif; ?>
  <?php if ($erroBusca): ?><div class="alerta alerta-erro"><?= h($erroBusca) ?></div><?php endif; ?>

  <div class="cartao" id="ultimoPedido" hidden>
    <h2>Seu último pedido</h2>
    <p class="dica" style="margin-top:0">Encontramos um pedido feito neste aparelho.</p>
    <a class="botao botao-bloco" id="linkUltimoPedido" href="#">Ver pedido</a>
  </div>

  <form class="cartao" method="post">
    <h2>Buscar pelo número</h2>
    <div class="campos">
      <div class="campo">
        <label for="numero">Número do pedido</label>
        <input id="numero" name="numero" inputmode="numeric" required placeholder="1027" value="<?= h($_POST['numero'] ?? '') ?>">
      </div>
      <div class="campo">
        <label for="telefoneBusca">WhatsApp usado no pedido</label>
        <input id="telefoneBusca" name="telefone" type="tel" inputmode="tel" required placeholder="(15) 99999-0000" value="<?= h($_POST['telefone'] ?? '') ?>">
      </div>
      <button class="botao botao-bloco" type="submit">Acompanhar</button>
    </div>
  </form>
</div>
<script>
  try {
    const ultimo = JSON.parse(localStorage.getItem('arteNaCozinha.ultimoPedido'));
    if (ultimo && ultimo.url) {
      document.getElementById('ultimoPedido').hidden = false;
      const link = document.getElementById('linkUltimoPedido');
      link.href = ultimo.url;
      link.textContent = 'Ver pedido nº ' + Number(ultimo.id);
    }
  } catch (e) {}
</script>
<?php
    require __DIR__ . '/includes/rodape.php';
    exit;
endif;

// ---------------------------------------------------------------------
// Tela do pedido
// ---------------------------------------------------------------------
$status    = $pedido['status'];
$etapas    = ['recebido', 'em_preparo', 'saiu_entrega', 'entregue'];
$posAtual  = array_search($status, $etapas, true);
$horarios  = [];
foreach ($pedido['historico'] as $hst) {
    $horarios[$hst['status']] = hora($hst['data_hora']);
}

// Previsão calculada pela distância no momento do pedido (preparo + trajeto)
[$minMin, $minMax] = previsao_pedido($pedido);
$inicio = strtotime($pedido['data_hora']);
$km = $pedido['distancia_km'] !== null ? number_format((float) $pedido['distancia_km'], 1, ',', '') . ' km' : null;
$previsao = date('H:i', $inicio + $minMin * 60) . ' – ' . date('H:i', $inicio + $minMax * 60);

$pagamento = FORMAS_PAGAMENTO[$pedido['forma_pagamento']];
$textoPagamento = $pedido['pago_no_site'] ? "pagamento via $pagamento confirmado" : "pagamento na entrega ($pagamento)";

$novo = isset($_GET['novo']);
$linkLoja = link_whatsapp(config_loja('whatsapp_loja')); // sem mensagem pronta
?>
<div class="container" style="max-width:980px" id="telaPedido"
     data-pedido="<?= (int) $pedido['id_pedido'] ?>" data-token="<?= h($token) ?>" data-status="<?= h($status) ?>">
  <div class="cabecalho-pagina">
    <a href="index.php" class="botao-icone" aria-label="Voltar ao cardápio"><?= icone('voltar') ?></a>
    <h1>Pedido nº <?= (int) $pedido['id_pedido'] ?></h1>
  </div>

  <?php if ($novo): ?>
    <div class="alerta alerta-sucesso">🎉 Pedido recebido! Você pode acompanhar o andamento aqui
      ou em <a href="conta.php">Minha conta</a>. A loja confirma o pedido pelo WhatsApp <?= h(formatar_telefone($pedido['telefone'])) ?>.</div>
  <?php endif; ?>

  <div class="layout-acompanhar">
    <div>
      <section class="cartao previsao">
        <?php if ($status === 'entregue'): ?>
          <p class="rotulo">Pedido entregue</p>
          <p class="horario"><?= h($horarios['entregue'] ?? '') ?></p>
        <?php elseif ($status === 'cancelado'): ?>
          <p class="rotulo">Situação</p>
          <p class="horario" style="font-size:1.8rem">Cancelado</p>
        <?php else: ?>
          <p class="rotulo">Previsão de entrega</p>
          <p class="horario"><?= h($previsao) ?></p>
        <?php endif; ?>
        <p class="endereco"><?= h($pedido['endereco']) ?> – <?= h($pedido['bairro']) ?></p>
        <?php if ($km): ?>
          <p class="distancia">📍 <?= h($km) ?> da confeitaria · saindo da <?= h(config_loja('endereco_loja')) ?></p>
        <?php endif; ?>
      </section>

      <section class="cartao" aria-labelledby="tAndamento">
        <h2 id="tAndamento">Andamento do pedido</h2>
        <?php if ($status === 'cancelado'): ?>
          <p>Este pedido foi cancelado pela loja. Em caso de dúvida, fale com a gente pelo WhatsApp.</p>
        <?php else: ?>
        <ol class="linha-tempo">
          <?php foreach ($etapas as $i => $etapa):
              $classe = $i < $posAtual ? 'feito' : ($i === $posAtual ? ($etapa === 'entregue' ? 'feito' : 'atual') : '');
              $info = STATUS_PEDIDO[$etapa];
              if (isset($horarios[$etapa])) {
                  $detalhe = $horarios[$etapa] . ' · ' . ($etapa === 'recebido' ? $textoPagamento : $info['texto']);
              } else {
                  $detalhe = 'aguardando';
              }
          ?>
          <li class="<?= $classe ?>" <?= $classe === 'atual' ? 'aria-current="step"' : '' ?>>
            <span class="ponto"></span>
            <strong><?= h($info['rotulo']) ?></strong>
            <small><?= h($detalhe) ?></small>
          </li>
          <?php endforeach; ?>
        </ol>
        <?php endif; ?>
        <p class="nota" style="text-align:left;font-size:.8rem" id="avisoAtualizacao">Esta tela se atualiza sozinha.</p>
      </section>
    </div>

    <div>
      <section class="cartao" aria-labelledby="tResumo">
        <h2 id="tResumo">Resumo</h2>
        <?php foreach ($pedido['itens'] as $item): ?>
          <div class="resumo-linha"><span><?= (int) $item['quantidade'] ?>× <?= h($item['produto']) ?></span><span><?= dinheiro($item['subtotal']) ?></span></div>
        <?php endforeach; ?>
        <div class="resumo-linha"><span>Frete<?= $km ? ' (' . h($km) . ')' : '' ?></span><span><?= dinheiro($pedido['taxa_entrega']) ?></span></div>
        <div class="resumo-linha resumo-total">
          <span>Total <?= $pedido['pago_no_site'] ? 'pago' : 'a pagar' ?> (<?= h($pagamento) ?>)</span>
          <span><?= dinheiro($pedido['valor_total']) ?></span>
        </div>
        <?php if ($pedido['observacao']): ?>
          <p class="dica" style="margin-bottom:0">Obs.: <?= h($pedido['observacao']) ?></p>
        <?php endif; ?>
      </section>

      <a class="botao botao-contorno botao-bloco botao-grande" href="<?= h($linkLoja) ?>" target="_blank" rel="noopener">Falar com a loja no WhatsApp</a>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/rodape.php'; ?>
