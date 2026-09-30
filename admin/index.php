<?php
/**
 * Painel – Pedidos (Figura 11)
 * RF11 – gerenciar pedidos · UC12 – atualizar status do pedido
 */
require_once __DIR__ . '/../includes/auth.php';
$admin = exigir_login();

$ver   = $_GET['ver'] ?? 'hoje';
$busca = trim((string) ($_GET['q'] ?? ''));
$data  = (string) ($_GET['data'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
    $data = date('Y-m-d');
}

// ---------------------------------------------------------------------
// Indicadores do dia
// ---------------------------------------------------------------------
$st = db()->prepare(
    "SELECT
        SUM(status <> 'cancelado')                           AS pedidos,
        SUM(status = 'recebido')                             AS novos,
        SUM(status = 'em_preparo')                           AS preparo,
        SUM(status = 'saiu_entrega')                         AS entrega,
        COALESCE(SUM(CASE WHEN status <> 'cancelado' THEN valor_total END), 0) AS vendas
       FROM pedido WHERE DATE(data_hora) = ?"
);
$st->execute([$ver === 'historico' ? $data : date('Y-m-d')]);
$ind = $st->fetch();

// ---------------------------------------------------------------------
// Lista de pedidos
// ---------------------------------------------------------------------
$where  = [];
$params = [];
if ($ver === 'abertos') {
    $where[] = "p.status IN ('recebido', 'em_preparo', 'saiu_entrega')";
} elseif ($ver === 'historico') {
    $where[] = 'DATE(p.data_hora) = ?';
    $params[] = $data;
} else {
    $ver = 'hoje';
    $where[] = 'DATE(p.data_hora) = CURDATE()';
}
if ($busca !== '') {
    $where[] = '(p.id_pedido = ? OR c.nome LIKE ? OR c.telefone LIKE ?)';
    $params[] = (int) $busca;
    $params[] = '%' . $busca . '%';
    $digitos  = so_digitos($busca);
    $params[] = $digitos !== '' ? '%' . $digitos . '%' : '-'; // "-" nunca casa com um telefone
}

$st = db()->prepare(
    "SELECT p.*, c.nome, c.telefone,
            GROUP_CONCAT(CONCAT(i.quantidade, '× ', pr.nome) ORDER BY i.id_item SEPARATOR ', ') AS itens
       FROM pedido p
       JOIN cliente c       ON c.id_cliente = p.id_cliente
       JOIN item_pedido i   ON i.id_pedido = p.id_pedido
       JOIN produto pr      ON pr.id_produto = i.id_produto
      WHERE " . implode(' AND ', $where) . "
      GROUP BY p.id_pedido
      ORDER BY FIELD(p.status, 'recebido', 'em_preparo', 'saiu_entrega', 'entregue', 'cancelado'), p.data_hora DESC"
);
$st->execute($params);
$pedidos = $st->fetchAll();

$ultimoId = (int) db()->query('SELECT COALESCE(MAX(id_pedido), 0) FROM pedido')->fetchColumn();

/** "Ana Paula" -> "Ana P." (como na Figura 11) */
function nome_curto(string $nome): string
{
    $partes = preg_split('/\s+/', trim($nome));
    return count($partes) > 1 ? $partes[0] . ' ' . mb_substr(end($partes), 0, 1) . '.' : $partes[0];
}

$tituloPagina  = 'Pedidos';
$menuAtivo     = 'pedidos';
$scriptsPagina = ['../assets/js/admin-pedidos.js'];
require __DIR__ . '/../includes/admin_topo.php';
?>

<div class="painel-cabecalho">
  <div>
    <h1><?= $ver === 'hoje' ? 'Pedidos de hoje' : ($ver === 'abertos' ? 'Pedidos em aberto' : 'Histórico de pedidos') ?></h1>
    <p class="subtitulo"><?= $ver === 'historico' ? h(data_extenso(strtotime($data))) : h(data_extenso()) ?> · <span id="atualizadoEm">atualizado agora</span></p>
  </div>
  <div class="painel-cabecalho-acoes">
    <form class="busca-painel" method="get" role="search">
      <input type="hidden" name="ver" value="<?= h($ver) ?>">
      <?php if ($ver === 'historico'): ?><input type="hidden" name="data" value="<?= h($data) ?>"><?php endif; ?>
      <?= icone('busca') ?>
      <input type="search" name="q" value="<?= h($busca) ?>" placeholder="Buscar pedido ou cliente" aria-label="Buscar pedido ou cliente">
    </form>
    <form method="post" action="configuracoes.php">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="alternar_loja">
      <button type="submit" class="botao-loja <?= loja_aberta() ? 'aberta' : 'fechada' ?>" title="Clique para <?= loja_aberta() ? 'fechar' : 'abrir' ?> a loja">
        Loja: <strong><?= loja_aberta() ? 'aberta' : 'fechada' ?></strong>
      </button>
    </form>
  </div>
</div>

<div class="aviso-novo" id="avisoNovo" hidden>
  <span>🔔 Chegou um novo pedido!</span>
  <a class="botao" href="index.php?ver=<?= h($ver) ?>">Atualizar lista</a>
</div>

<section class="indicadores" aria-label="Resumo do dia">
  <div class="indicador"><span>Pedidos <?= $ver === 'historico' ? 'no dia' : 'hoje' ?></span><strong><?= (int) $ind['pedidos'] ?></strong></div>
  <div class="indicador destaque"><span>Novos (aguardando aceite)</span><strong><?= (int) $ind['novos'] ?></strong></div>
  <div class="indicador"><span>Em preparo / em entrega</span><strong><?= (int) $ind['preparo'] ?> / <?= (int) $ind['entrega'] ?></strong></div>
  <div class="indicador"><span>Vendas do dia</span><strong><?= dinheiro($ind['vendas']) ?></strong></div>
</section>

<nav class="abas" aria-label="Filtro de pedidos">
  <a href="?ver=hoje" class="<?= $ver === 'hoje' ? 'ativo' : '' ?>">Hoje</a>
  <a href="?ver=abertos" class="<?= $ver === 'abertos' ? 'ativo' : '' ?>">Em aberto</a>
  <a href="?ver=historico" class="<?= $ver === 'historico' ? 'ativo' : '' ?>">Histórico</a>
  <?php if ($ver === 'historico'): ?>
    <form method="get" class="filtro-data">
      <input type="hidden" name="ver" value="historico">
      <input type="date" name="data" value="<?= h($data) ?>" max="<?= date('Y-m-d') ?>" aria-label="Data" onchange="this.form.submit()">
    </form>
  <?php endif; ?>
</nav>

<div class="tabela-caixa">
  <table class="tabela tabela-responsiva">
    <thead>
      <tr>
        <th>Nº</th><th>Horário</th><th>Cliente</th><th>Itens</th><th>Total</th><th>Pagamento</th><th>Status</th><th>Ação</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$pedidos): ?>
        <tr class="linha-vazia"><td colspan="8">Nenhum pedido encontrado<?= $busca ? ' para “' . h($busca) . '”' : '' ?>.</td></tr>
      <?php endif; ?>
      <?php foreach ($pedidos as $p):
          $s = STATUS_PEDIDO[$p['status']];
          $prox = PROXIMO_STATUS[$p['status']] ?? null;
      ?>
      <tr>
        <td data-rotulo="Nº"><a class="link-pedido" href="pedido.php?id=<?= (int) $p['id_pedido'] ?>"><strong><?= (int) $p['id_pedido'] ?></strong></a></td>
        <td data-rotulo="Horário"><?= hora($p['data_hora']) ?><?= $ver === 'abertos' && date('Y-m-d', strtotime($p['data_hora'])) !== date('Y-m-d') ? ' <small>(' . date('d/m', strtotime($p['data_hora'])) . ')</small>' : '' ?></td>
        <td data-rotulo="Cliente"><?= h(nome_curto($p['nome'])) ?></td>
        <td data-rotulo="Itens" class="celula-itens"><?= h($p['itens']) ?></td>
        <td data-rotulo="Total"><?= dinheiro($p['valor_total']) ?></td>
        <td data-rotulo="Pagamento"><?= h(FORMAS_PAGAMENTO[$p['forma_pagamento']]) ?> · <?= $p['pago_no_site'] ? 'pago' : 'na entrega' ?></td>
        <td data-rotulo="Status"><span class="etiqueta etiqueta-<?= $s['classe'] ?>"><?= h($s['admin']) ?></span></td>
        <td data-rotulo="Ação" class="celula-acao">
          <?php if ($prox): ?>
            <form method="post" action="acao_pedido.php">
              <?= csrf_campo() ?>
              <input type="hidden" name="id" value="<?= (int) $p['id_pedido'] ?>">
              <input type="hidden" name="status" value="<?= h($prox['status']) ?>">
              <input type="hidden" name="voltar" value="<?= h($_SERVER['REQUEST_URI']) ?>">
              <button type="submit" class="botao-acao"><?= h($prox['botao']) ?></button>
            </form>
          <?php else: ?>
            <span class="traco">—</span>
          <?php endif; ?>
          <a class="botao-ver" href="pedido.php?id=<?= (int) $p['id_pedido'] ?>" aria-label="Ver detalhes do pedido <?= (int) $p['id_pedido'] ?>" title="Detalhes"><?= icone('olho') ?></a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<script>window.ULTIMO_PEDIDO = <?= $ultimoId ?>;</script>
<?php require __DIR__ . '/../includes/admin_rodape.php'; ?>
