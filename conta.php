<?php
/**
 * Minha conta — área do cliente
 * Endereço salvo, pedidos anteriores, acompanhar e "pedir de novo".
 */
require_once __DIR__ . '/includes/bootstrap.php';

if (!cliente_logado()) {
    redirecionar('entrar.php?voltar=conta.php');
}

// ---------------------------------------------------------------------
// Ações: salvar endereço ou sair
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido($_POST['csrf'] ?? null)) {
        flash('erro', 'Sua sessão expirou. Tente novamente.');
        redirecionar('conta.php');
    }

    if (($_POST['acao'] ?? '') === 'sair') {
        unset($_SESSION['cliente']);
        session_regenerate_id(true);
        flash('info', 'Você saiu da sua conta.');
        redirecionar('index.php');
    }

    if (($_POST['acao'] ?? '') === 'endereco') {
        $c = dados_cliente_logado();
        $nome = mb_substr(trim((string) $_POST['nome']), 0, 100);
        $endereco = mb_substr(trim((string) $_POST['endereco']), 0, 150);
        $bairro = mb_substr(trim((string) $_POST['bairro']), 0, 60);
        $complemento = mb_substr(trim((string) $_POST['complemento']), 0, 60);

        if (mb_strlen($nome) < 2 || mb_strlen($endereco) < 5 || mb_strlen($bairro) < 2) {
            flash('erro', 'Preencha nome, endereço (rua e número) e bairro.');
        } elseif ($c['id_cliente']) {
            db()->prepare('UPDATE cliente SET nome = ?, endereco = ?, bairro = ?, complemento = ? WHERE id_cliente = ?')
                ->execute([$nome, $endereco, $bairro, $complemento ?: null, $c['id_cliente']]);
            entrar_cliente((int) $c['id_cliente'], $nome, $c['telefone']);
            flash('sucesso', 'Endereço salvo. Ele já vem preenchido no carrinho.');
        } else {
            db()->prepare('INSERT INTO cliente (nome, telefone, endereco, bairro, complemento) VALUES (?, ?, ?, ?, ?)')
                ->execute([$nome, $c['telefone'], $endereco, $bairro, $complemento ?: null]);
            entrar_cliente((int) db()->lastInsertId(), $nome, $c['telefone']);
            flash('sucesso', 'Endereço salvo. Ele já vem preenchido no carrinho.');
        }
    }
    redirecionar('conta.php');
}

$cliente = dados_cliente_logado();

// Pedidos feitos com este WhatsApp (mais recentes primeiro)
$st = db()->prepare(
    "SELECT p.id_pedido, p.data_hora, p.status, p.valor_total, p.forma_pagamento
       FROM pedido p JOIN cliente c ON c.id_cliente = p.id_cliente
      WHERE c.telefone = ?
      ORDER BY p.data_hora DESC
      LIMIT 30"
);
$st->execute([$cliente['telefone']]);
$pedidos = $st->fetchAll();

// Itens de cada pedido + se o produto ainda está no cardápio (para "pedir de novo")
$itensPorPedido = [];
if ($pedidos) {
    $ids = array_column($pedidos, 'id_pedido');
    $marcadores = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare(
        "SELECT i.id_pedido, i.id_produto, i.quantidade, pr.nome, pr.disponivel
           FROM item_pedido i JOIN produto pr ON pr.id_produto = i.id_produto
          WHERE i.id_pedido IN ($marcadores) ORDER BY i.id_item"
    );
    $st->execute($ids);
    foreach ($st->fetchAll() as $item) {
        $itensPorPedido[$item['id_pedido']][] = $item;
    }
}

$abertos = array_filter($pedidos, fn($p) => in_array($p['status'], ['recebido', 'em_preparo', 'saiu_entrega'], true));
$primeiroNome = explode(' ', $cliente['nome'])[0];

$tituloPagina = 'Minha conta | Arte na Cozinha';
$paginaAtual  = 'conta';
$scriptsPagina = ['assets/js/conta.js'];
require __DIR__ . '/includes/cabecalho.php';
?>
<div class="container" style="max-width:980px">
  <div class="cabecalho-pagina">
    <a href="index.php" class="botao-icone" aria-label="Voltar ao cardápio"><?= icone('voltar') ?></a>
    <h1>Olá, <?= h($primeiroNome) ?>!</h1>
  </div>

  <?php foreach ($abertos as $p): $s = STATUS_PEDIDO[$p['status']]; ?>
    <a class="pedido-andamento" href="acompanhar.php?pedido=<?= (int) $p['id_pedido'] ?>&t=<?= token_pedido((int) $p['id_pedido']) ?>">
      <span>🛵 Pedido nº <?= (int) $p['id_pedido'] ?> · <strong><?= h($s['rotulo']) ?></strong></span>
      <span>Acompanhar →</span>
    </a>
  <?php endforeach; ?>

  <div class="layout-conta">
    <section class="cartao" aria-labelledby="tPedidos">
      <h2 id="tPedidos">Meus pedidos</h2>
      <?php if (!$pedidos): ?>
        <div class="vazio" style="padding:20px 0">
          <span class="emoji">🧁</span>
          Você ainda não fez pedidos. <a href="index.php">Ver o cardápio</a>
        </div>
      <?php endif; ?>

      <?php foreach ($pedidos as $p):
          $s = STATUS_PEDIDO[$p['status']];
          $itens = $itensPorPedido[$p['id_pedido']] ?? [];
          $repetir = [];
          foreach ($itens as $i) {
              if ($i['disponivel']) $repetir[$i['id_produto']] = ($repetir[$i['id_produto']] ?? 0) + (int) $i['quantidade'];
          }
          $indisponiveis = array_filter($itens, fn($i) => !$i['disponivel']);
      ?>
        <article class="pedido-anterior">
          <div class="pedido-anterior-topo">
            <strong>Pedido nº <?= (int) $p['id_pedido'] ?></strong>
            <span class="etiqueta etiqueta-<?= $s['classe'] ?>"><?= h($s['rotulo']) ?></span>
          </div>
          <p class="dica" style="margin:4px 0"><?= date('d/m/Y', strtotime($p['data_hora'])) ?> às <?= date('H:i', strtotime($p['data_hora'])) ?> · <?= dinheiro($p['valor_total']) ?> · <?= h(FORMAS_PAGAMENTO[$p['forma_pagamento']]) ?></p>
          <p class="pedido-anterior-itens">
            <?= h(implode(', ', array_map(fn($i) => $i['quantidade'] . '× ' . $i['nome'], $itens))) ?>
          </p>
          <div class="acoes-pedido">
            <?php if ($repetir): ?>
              <button type="button" class="botao" data-pedir-novamente='<?= h(json_encode($repetir)) ?>'
                      data-aviso="<?= $indisponiveis ? h(count($indisponiveis) . ' item(ns) não está(ão) mais no cardápio.') : '' ?>">
                <?= icone('repetir') ?> Pedir de novo
              </button>
            <?php endif; ?>
            <a class="botao botao-contorno" href="acompanhar.php?pedido=<?= (int) $p['id_pedido'] ?>&t=<?= token_pedido((int) $p['id_pedido']) ?>">Detalhes</a>
          </div>
        </article>
      <?php endforeach; ?>
    </section>

    <div>
      <form class="cartao" method="post">
        <h2>Meus dados</h2>
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="endereco">
        <div class="campos">
          <div class="campo">
            <label for="nome">Nome</label>
            <input id="nome" name="nome" maxlength="100" required value="<?= h($cliente['nome']) ?>">
          </div>
          <div class="campo">
            <label for="whats">WhatsApp</label>
            <input id="whats" value="<?= h(formatar_telefone($cliente['telefone'])) ?>" readonly>
          </div>
          <div class="campo">
            <label for="endereco">Endereço (rua e número)</label>
            <input id="endereco" name="endereco" maxlength="150" required value="<?= h($cliente['endereco']) ?>" placeholder="Rua das Flores, 120">
          </div>
          <div class="campo">
            <label for="bairro">Bairro</label>
            <input id="bairro" name="bairro" maxlength="60" required value="<?= h($cliente['bairro']) ?>" placeholder="Centro">
          </div>
          <div class="campo">
            <label for="complemento">Complemento (opcional)</label>
            <input id="complemento" name="complemento" maxlength="60" value="<?= h((string) $cliente['complemento']) ?>" placeholder="Apto, bloco, referência">
          </div>
          <button class="botao botao-bloco" type="submit">Salvar endereço</button>
        </div>
      </form>

      <form method="post" class="cartao sair-conta">
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="sair">
        <p class="dica" style="margin:0 0 10px">Usando um aparelho de outra pessoa? Saia da conta ao terminar.</p>
        <button class="botao botao-contorno botao-bloco" type="submit">Sair da conta</button>
      </form>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/rodape.php'; ?>
