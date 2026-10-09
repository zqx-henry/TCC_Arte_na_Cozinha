<?php
/**
 * Página inicial e cardápio digital (Figura 8)
 * RF06 – pesquisar produtos e ver promoções · RF07 – filtrar por categoria
 * RF02 – adicionar ao carrinho (assets/js/carrinho.js)
 */
require_once __DIR__ . '/includes/bootstrap.php';

$produtos = db()->query(
    SQL_PRODUTO_COM_PROMO . " WHERE p.disponivel = 1
      ORDER BY COALESCE((SELECT c.ordem FROM categoria c WHERE c.nome = p.categoria), 999), p.categoria, p.nome"
)->fetchAll();

// "Mais pedidos": produtos com mais unidades vendidas
$maisPedidos = db()->query(
    "SELECT p.id_produto
       FROM item_pedido i
       JOIN produto p ON p.id_produto = i.id_produto
       JOIN pedido pe ON pe.id_pedido = i.id_pedido
      WHERE p.disponivel = 1 AND pe.status <> 'cancelado'
      GROUP BY p.id_produto
      ORDER BY SUM(i.quantidade) DESC, p.nome
      LIMIT 3"
)->fetchAll(PDO::FETCH_COLUMN);

// Promoção da semana (banner): produto escolhido no painel ou a primeira promoção ativa
$destaque = null;
$promocoes = array_values(array_filter($produtos, 'em_promocao'));
foreach ($produtos as $p) {
    if ((string) $p['id_produto'] === config_loja('produto_destaque') && em_promocao($p)) {
        $destaque = $p;
    }
}
$destaque ??= $promocoes[0] ?? null;

// Dados usados pelo JavaScript do carrinho
$dadosJs = [];
foreach ($produtos as $p) {
    $dadosJs[$p['id_produto']] = [
        'nome'   => $p['nome'],
        'preco'  => preco_atual($p),
        'normal' => (float) $p['preco'],
        'imagem' => $p['imagem'],
    ];
}

$porId = array_column($produtos, null, 'id_produto');

/** Card de produto (7.1.4) */
function card_produto(array $p): string
{
    ob_start(); ?>
    <article class="card-produto" data-id="<?= (int) $p['id_produto'] ?>"
             data-categoria="<?= h($p['categoria']) ?>"
             data-promo="<?= em_promocao($p) ? '1' : '0' ?>"
             data-busca="<?= h(mb_strtolower($p['nome'] . ' ' . $p['descricao'] . ' ' . $p['categoria'])) ?>">
      <div class="card-foto">
        <img src="<?= h($p['imagem'] ?: 'assets/img/logo-icone.svg') ?>" alt="<?= h($p['nome']) ?>" loading="lazy" width="96" height="96">
        <?php if (em_promocao($p)): ?><span class="selo">Promoção</span><?php endif; ?>
      </div>
      <div class="card-info">
        <h3><?= h($p['nome']) ?></h3>
        <p><?= h($p['descricao']) ?></p>
        <span class="preco"><?= dinheiro(preco_atual($p)) ?></span>
        <?php if (em_promocao($p)): ?><span class="preco-antigo"><?= dinheiro($p['preco']) ?></span><?php endif; ?>
        <?php if (em_promocao($p) && $p['promo_fim']): ?>
          <div class="contagem-mini"<?= atributo_fim($p['promo_fim']) ?>>⏳ Acaba em <span class="contagem-valor"><?= h(tempo_restante($p['promo_fim'])) ?></span></div>
        <?php endif; ?>
      </div>
      <div class="card-acao" data-acao="<?= (int) $p['id_produto'] ?>">
        <button type="button" class="botao-adicionar" data-adicionar="<?= (int) $p['id_produto'] ?>" aria-label="Adicionar <?= h($p['nome']) ?> ao carrinho">
          <?= icone('mais') ?>
        </button>
      </div>
    </article>
    <?php return ob_get_clean();
}

$tituloPagina = 'Arte na Cozinha | Confeitaria com delivery próprio';
$paginaAtual  = 'cardapio';
$scriptsPagina = ['assets/js/cardapio.js'];
require __DIR__ . '/includes/cabecalho.php';
?>

<div class="container">
  <?php if (loja_aberta()): ?>
    <p class="status-loja"><strong>Aberto agora</strong> · Frete <?= dinheiro(config_loja('valor_km', '3.30')) ?>/km · Tempo calculado pela distância</p>
  <?php else: ?>
    <p class="status-loja fechada"><strong>Fechado no momento</strong> · <?= h(config_loja('horario')) ?></p>
    <div class="aviso-fechada">A loja está fechada agora. Você pode montar seu carrinho e finalizar quando abrirmos. 💗</div>
  <?php endif; ?>

  <div class="pesquisa" role="search">
    <?= icone('busca') ?>
    <label for="campoBusca" class="sr-only">Buscar produtos</label>
    <input type="search" id="campoBusca" placeholder="Buscar bolos, doces, tortas..." autocomplete="off">
  </div>

  <?php if ($destaque): ?>
  <section class="banner-promo" aria-label="Promoção da semana">
    <div class="banner-texto">
      <span class="selo">Promoção da semana</span>
      <h2><?= h($destaque['nome']) ?></h2>
      <p>
        <span class="preco-de">de <s><?= dinheiro($destaque['preco']) ?></s> por</span>
        <span class="preco-por"><?= dinheiro(preco_atual($destaque)) ?></span>
      </p>
      <?php if ($destaque['promo_fim']): ?>
        <p class="banner-contagem"<?= atributo_fim($destaque['promo_fim']) ?>>⏳ Termina em <strong class="contagem-valor"><?= h(tempo_restante($destaque['promo_fim'])) ?></strong></p>
      <?php endif; ?>
      <button type="button" class="botao" data-adicionar="<?= (int) $destaque['id_produto'] ?>">Quero esse!</button>
    </div>
    <img src="<?= h($destaque['imagem']) ?>" alt="" width="150" height="150">
  </section>
  <?php endif; ?>

  <nav class="categorias" aria-label="Categorias">
    <button type="button" class="chip ativo" data-categoria="Todos">Todos</button>
    <?php if ($promocoes): ?><button type="button" class="chip" data-categoria="Promoções">Promoções</button><?php endif; ?>
    <?php foreach (array_intersect(categorias(), array_column($produtos, 'categoria')) as $cat): // só categorias com produtos ?>
      <button type="button" class="chip" data-categoria="<?= h($cat) ?>"><?= h($cat) ?></button>
    <?php endforeach; ?>
  </nav>

  <?php if ($maisPedidos): ?>
  <section class="secao" id="secaoMaisPedidos">
    <h2 class="secao-titulo">Mais pedidos</h2>
    <div class="lista-produtos">
      <?php foreach ($maisPedidos as $id) { if (isset($porId[$id])) echo card_produto($porId[$id]); } ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="secao" id="secaoCardapio">
    <h2 class="secao-titulo"><span id="tituloCardapio">Cardápio completo</span> <small id="totalResultados"></small></h2>
    <div class="lista-produtos" id="listaCardapio">
      <?php foreach ($produtos as $p) echo card_produto($p); ?>
    </div>
    <div class="vazio" id="semResultados" hidden>
      <span class="emoji">🔍</span>
      Nenhum produto encontrado. Tente outro nome ou categoria.
    </div>
  </section>
</div>

<a href="carrinho.php" class="barra-carrinho" id="barraCarrinho" hidden>
  <span id="barraCarrinhoTexto">Ver carrinho</span>
  <span id="barraCarrinhoTotal">R$ 0,00</span>
</a>

<script>
  window.PRODUTOS = <?= json_encode($dadosJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
</script>

<?php require __DIR__ . '/includes/rodape.php'; ?>
