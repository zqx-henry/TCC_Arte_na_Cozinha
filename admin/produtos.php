<?php
/**
 * Painel – Produtos e preços (RF10 / UC10)
 * Listagem, ativar/desativar no cardápio e exclusão.
 */
require_once __DIR__ . '/../includes/auth.php';
$admin = exigir_login();

// ---------------------------------------------------------------------
// Ações (POST)
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_valido();
    $id = (int) ($_POST['id'] ?? 0);
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'disponivel') {
        db()->prepare('UPDATE produto SET disponivel = NOT disponivel WHERE id_produto = ?')->execute([$id]);
        flash('sucesso', 'Disponibilidade do produto atualizada.');
    } elseif ($acao === 'excluir') {
        $st = db()->prepare('SELECT COUNT(*) FROM item_pedido WHERE id_produto = ?');
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) {
            // Produto com pedidos não pode ser apagado (mantém o histórico de vendas)
            db()->prepare('UPDATE produto SET disponivel = 0 WHERE id_produto = ?')->execute([$id]);
            flash('info', 'Este produto já aparece em pedidos, então foi apenas removido do cardápio (o histórico de vendas foi preservado).');
        } else {
            $st = db()->prepare('SELECT imagem FROM produto WHERE id_produto = ?');
            $st->execute([$id]);
            $imagem = (string) $st->fetchColumn();
            db()->prepare('DELETE FROM produto WHERE id_produto = ?')->execute([$id]);
            if (str_starts_with($imagem, 'uploads/') && is_file(__DIR__ . '/../' . $imagem)) {
                unlink(__DIR__ . '/../' . $imagem);
            }
            flash('sucesso', 'Produto excluído.');
        }
    }
    redirecionar('produtos.php' . (!empty($_POST['voltar_qs']) ? '?' . preg_replace('/[^\w=&%.+-]/', '', $_POST['voltar_qs']) : ''));
}

// ---------------------------------------------------------------------
// Listagem
// ---------------------------------------------------------------------
$categoria = (string) ($_GET['categoria'] ?? '');
$busca     = trim((string) ($_GET['q'] ?? ''));

$where = ['1=1'];
$params = [];
if (in_array($categoria, CATEGORIAS, true)) {
    $where[] = 'p.categoria = ?';
    $params[] = $categoria;
}
if ($busca !== '') {
    $where[] = 'p.nome LIKE ?';
    $params[] = '%' . $busca . '%';
}

$st = db()->prepare(
    "SELECT p.*, pr.data_fim AS promo_fim, pr.modo AS promo_modo, COALESCE(SUM(i.quantidade), 0) AS vendidos
       FROM produto p
       LEFT JOIN promocao pr ON pr.id_produto = p.id_produto
       LEFT JOIN item_pedido i ON i.id_produto = p.id_produto
      WHERE " . implode(' AND ', $where) . "
      GROUP BY p.id_produto
      ORDER BY FIELD(p.categoria, 'Bolos', 'Doces', 'Tortas', 'Bebidas'), p.nome"
);
$st->execute($params);
$produtos = $st->fetchAll();

$tituloPagina = 'Produtos e preços';
$menuAtivo    = 'produtos';
require __DIR__ . '/../includes/admin_topo.php';
?>

<div class="painel-cabecalho">
  <div>
    <h1>Produtos e preços</h1>
    <p class="subtitulo"><?= count($produtos) ?> produto(s) · o que estiver “No cardápio” aparece para os clientes</p>
  </div>
  <div class="painel-cabecalho-acoes">
    <form class="busca-painel" method="get" role="search">
      <?php if ($categoria): ?><input type="hidden" name="categoria" value="<?= h($categoria) ?>"><?php endif; ?>
      <?= icone('busca') ?>
      <input type="search" name="q" value="<?= h($busca) ?>" placeholder="Buscar produto" aria-label="Buscar produto">
    </form>
    <a href="produto_form.php" class="botao"><?= icone('mais') ?> Novo produto</a>
  </div>
</div>

<nav class="abas" aria-label="Filtrar por categoria">
  <a href="produtos.php" class="<?= $categoria === '' ? 'ativo' : '' ?>">Todas</a>
  <?php foreach (CATEGORIAS as $cat): ?>
    <a href="?categoria=<?= urlencode($cat) ?>" class="<?= $categoria === $cat ? 'ativo' : '' ?>"><?= h($cat) ?></a>
  <?php endforeach; ?>
</nav>

<div class="tabela-caixa">
  <table class="tabela tabela-responsiva">
    <thead>
      <tr><th>Produto</th><th>Categoria</th><th>Preço</th><th>Promoção</th><th>Vendidos</th><th>Cardápio</th><th>Ações</th></tr>
    </thead>
    <tbody>
      <?php if (!$produtos): ?>
        <tr class="linha-vazia"><td colspan="7">Nenhum produto encontrado.</td></tr>
      <?php endif; ?>
      <?php foreach ($produtos as $p): ?>
        <tr class="<?= $p['disponivel'] ? '' : 'linha-inativa' ?>">
          <td data-rotulo="Produto">
            <div class="produto-celula">
              <img src="../<?= h($p['imagem'] ?: 'assets/img/logo-icone.svg') ?>" alt="" width="48" height="48" loading="lazy">
              <div><strong><?= h($p['nome']) ?></strong><small><?= h($p['descricao']) ?></small></div>
            </div>
          </td>
          <td data-rotulo="Categoria"><?= h($p['categoria']) ?></td>
          <td data-rotulo="Preço"><?= dinheiro($p['preco']) ?></td>
          <td data-rotulo="Promoção"><?php if (em_promocao($p)): ?>
            <span class="etiqueta etiqueta-preparo"><?= dinheiro($p['preco_promocional']) ?></span>
            <?php if ($p['promo_fim']): ?><small class="prazo-mini"<?= atributo_fim($p['promo_fim']) ?>>⏳ <span class="contagem-valor"><?= h(tempo_restante($p['promo_fim'])) ?></span></small><?php endif; ?>
          <?php else: ?><span class="traco">—</span><?php endif; ?></td>
          <td data-rotulo="Vendidos"><?= (int) $p['vendidos'] ?></td>
          <td data-rotulo="Cardápio">
            <form method="post">
              <?= csrf_campo() ?>
              <input type="hidden" name="id" value="<?= (int) $p['id_produto'] ?>">
              <input type="hidden" name="acao" value="disponivel">
              <input type="hidden" name="voltar_qs" value="<?= h($_SERVER['QUERY_STRING'] ?? '') ?>">
              <button type="submit" class="interruptor <?= $p['disponivel'] ? 'ligado' : '' ?>" aria-pressed="<?= $p['disponivel'] ? 'true' : 'false' ?>">
                <span></span><?= $p['disponivel'] ? 'No cardápio' : 'Oculto' ?>
              </button>
            </form>
          </td>
          <td data-rotulo="Ações" class="celula-acao">
            <a class="botao-acao" href="produto_form.php?id=<?= (int) $p['id_produto'] ?>"><?= icone('editar') ?> Editar</a>
            <form method="post" data-confirmar="Excluir “<?= h($p['nome']) ?>”?">
              <?= csrf_campo() ?>
              <input type="hidden" name="id" value="<?= (int) $p['id_produto'] ?>">
              <input type="hidden" name="acao" value="excluir">
              <button type="submit" class="botao-ver perigo" aria-label="Excluir <?= h($p['nome']) ?>" title="Excluir"><?= icone('lixeira') ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../includes/admin_rodape.php'; ?>
