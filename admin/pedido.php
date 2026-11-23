<?php
/**
 * Painel – Detalhes do pedido (RF11)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/frete.php';
$admin = exigir_login();

$pedido = buscar_pedido((int) ($_GET['id'] ?? 0));
if (!$pedido) {
    flash('erro', 'Pedido não encontrado.');
    redirecionar('index.php');
}

$id   = (int) $pedido['id_pedido'];
$s    = STATUS_PEDIDO[$pedido['status']];
$prox = PROXIMO_STATUS[$pedido['status']] ?? null;
$responsavel = null;
if ($pedido['id_admin']) {
    $st = db()->prepare('SELECT nome FROM administrador WHERE id_admin = ?');
    $st->execute([$pedido['id_admin']]);
    $responsavel = $st->fetchColumn();
}
$subtotal = array_sum(array_column($pedido['itens'], 'subtotal'));

$linkRota = link_rota_entrega($pedido);

$tituloPagina  = 'Pedido nº ' . $id;
$menuAtivo     = 'pedidos';
$scriptsPagina = ['../assets/js/vendor/qrcode.js', '../assets/js/admin-qr.js'];
require __DIR__ . '/../includes/admin_topo.php';

$formStatus = function (string $status, string $rotulo, string $classe = 'botao') use ($id) {
    return '<form method="post" action="acao_pedido.php"' . ($status === 'cancelado' ? ' data-confirmar="Cancelar o pedido nº ' . $id . '?"' : '') . '>'
         . csrf_campo()
         . '<input type="hidden" name="id" value="' . $id . '">'
         . '<input type="hidden" name="status" value="' . h($status) . '">'
         . '<input type="hidden" name="voltar" value="pedido.php?id=' . $id . '">'
         . '<button type="submit" class="' . $classe . '">' . h($rotulo) . '</button></form>';
};
?>

<div class="painel-cabecalho">
  <div>
    <a href="index.php" class="voltar-link"><?= icone('voltar') ?> Pedidos</a>
    <h1>Pedido nº <?= $id ?> <span class="etiqueta etiqueta-<?= $s['classe'] ?>"><?= h($s['admin']) ?></span></h1>
    <p class="subtitulo"><?= h(data_extenso(strtotime($pedido['data_hora']))) ?>, às <?= hora($pedido['data_hora']) ?><?= $responsavel ? ' · Responsável: ' . h($responsavel) : '' ?></p>
  </div>
  <div class="painel-cabecalho-acoes">
    <button type="button" class="botao botao-contorno" onclick="window.print()">Imprimir comanda</button>
    <?php if ($prox) echo $formStatus($prox['status'], $prox['botao']); ?>
  </div>
</div>

<div class="grade-detalhe">
  <section class="cartao">
    <h2>Itens</h2>
    <table class="tabela tabela-simples">
      <thead><tr><th>Produto</th><th>Qtd.</th><th>Preço unit.</th><th>Subtotal</th></tr></thead>
      <tbody>
        <?php foreach ($pedido['itens'] as $item): ?>
          <tr>
            <td><?= h($item['produto']) ?></td>
            <td><?= (int) $item['quantidade'] ?></td>
            <td><?= dinheiro($item['preco_unitario']) ?></td>
            <td><?= dinheiro($item['subtotal']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="resumo-linha" style="margin-top:10px"><span>Subtotal</span><span><?= dinheiro($subtotal) ?></span></div>
    <div class="resumo-linha"><span>Frete<?= $pedido['distancia_km'] !== null ? ' (' . number_format((float) $pedido['distancia_km'], 1, ',', '') . ' km)' : '' ?></span><span><?= dinheiro($pedido['taxa_entrega']) ?></span></div>
    <div class="resumo-linha resumo-total"><span>Total</span><span><?= dinheiro($pedido['valor_total']) ?></span></div>
    <p class="dica">Pagamento: <strong><?= h(FORMAS_PAGAMENTO[$pedido['forma_pagamento']]) ?></strong> ·
      <?= $pedido['pago_no_site'] ? 'pago pelo site' : 'cobrar na entrega' ?></p>
    <?php if ($pedido['observacao']): ?>
      <div class="alerta alerta-info"><strong>Observação:</strong> <?= h($pedido['observacao']) ?></div>
    <?php endif; ?>
  </section>

  <div>
    <section class="cartao">
      <h2>Cliente e entrega</h2>
      <p><strong><?= h($pedido['nome']) ?></strong></p>
      <p><?= h(formatar_telefone($pedido['telefone'])) ?></p>
      <p style="margin-top:8px"><?= h($pedido['endereco']) ?> – <?= h($pedido['bairro']) ?></p>
      <?php if ($pedido['complemento']): ?><p class="dica" style="margin:2px 0 0"><?= h($pedido['complemento']) ?></p><?php endif; ?>
      <?php [$tMin, $tMax] = previsao_pedido($pedido); ?>
      <p class="dica" style="margin:8px 0 0">
        <?php if ($pedido['distancia_km'] !== null): ?>
          📍 <strong><?= number_format((float) $pedido['distancia_km'], 1, ',', '') ?> km</strong> da confeitaria
          <?= $pedido['metodo_frete'] === 'linha_reta' ? '(estimada)' : '' ?> ·
        <?php else: ?>
          Frete pela taxa padrão ·
        <?php endif; ?>
        previsão <?= $tMin ?>–<?= $tMax ?> min (até <?= date('H:i', strtotime($pedido['data_hora']) + $tMax * 60) ?>)
      </p>
      <div class="acoes-linha">
        <a class="botao botao-whatsapp" target="_blank" rel="noopener"
           href="<?= h(link_whatsapp($pedido['telefone'], 'Olá, ' . explode(' ', $pedido['nome'])[0] . '! Aqui é da Arte na Cozinha, sobre o seu pedido nº ' . $id . '.')) ?>">
          <?= icone('whatsapp') ?> WhatsApp do cliente
        </a>
        <a class="botao botao-contorno" target="_blank" rel="noopener" href="<?= h($linkRota) ?>">Rota da entrega</a>
      </div>
    </section>

    <?php if (!in_array($pedido['status'], ['entregue', 'cancelado'], true)): ?>
    <!-- QR Code da rota: o entregador aponta a câmera e o Google Maps abre com o destino -->
    <section class="cartao qr-entrega">
      <div class="qr-codigo" id="qrRota" data-url="<?= h($linkRota) ?>" role="img"
           aria-label="QR Code com a rota até o endereço do cliente"></div>
      <div>
        <h2>QR Code para o entregador</h2>
        <p class="dica" style="margin-top:0">Aponte a câmera do celular: o <strong>Google Maps</strong> abre com a rota
          de onde o entregador estiver até <strong><?= h($pedido['endereco']) ?> – <?= h($pedido['bairro']) ?></strong>.</p>
        <p class="dica qr-impressao">Também sai na <strong>comanda impressa</strong>, para colar na embalagem.</p>
      </div>
    </section>
    <?php endif; ?>

    <section class="cartao">
      <h2>Histórico</h2>
      <ol class="linha-tempo">
        <?php foreach ($pedido['historico'] as $i => $hst): $ultimo = $i === count($pedido['historico']) - 1; ?>
          <li class="<?= $ultimo && !in_array($hst['status'], ['entregue', 'cancelado'], true) ? 'atual' : 'feito' ?>">
            <span class="ponto"></span>
            <strong><?= h(STATUS_PEDIDO[$hst['status']]['rotulo']) ?></strong>
            <small><?= date('d/m H:i', strtotime($hst['data_hora'])) ?></small>
          </li>
        <?php endforeach; ?>
      </ol>
      <p class="dica">Link de acompanhamento do cliente:<br>
        <a href="<?= h(link_acompanhamento($id)) ?>" target="_blank" rel="noopener" style="word-break:break-all"><?= h(link_acompanhamento($id)) ?></a></p>
    </section>

    <?php if (!in_array($pedido['status'], ['entregue', 'cancelado'], true)): ?>
      <div class="zona-perigo">
        <?= $formStatus('cancelado', 'Cancelar pedido', 'botao botao-perigo') ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/admin_rodape.php'; ?>
