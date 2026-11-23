<?php
/**
 * Créditos das fotos do cardápio
 * As fotos são do Wikimedia Commons, com licenças livres (domínio público ou Creative Commons).
 * As licenças CC BY / CC BY-SA exigem citar autor, licença e origem — por isso esta página.
 * Os dados vêm de assets/img/produtos/creditos.json.
 */
require_once __DIR__ . '/includes/bootstrap.php';

$creditos = json_decode((string) @file_get_contents(__DIR__ . '/assets/img/produtos/creditos.json'), true) ?: [];

// Nome do produto como aparece no cardápio (pelo caminho da foto)
$nomes = db()->query('SELECT imagem, nome FROM produto')->fetchAll(PDO::FETCH_KEY_PAIR);

$tituloPagina = 'Créditos das fotos | Arte na Cozinha';
require __DIR__ . '/includes/cabecalho.php';
?>
<div class="container" style="max-width:900px">
  <div class="cabecalho-pagina">
    <a href="index.php" class="botao-icone" aria-label="Voltar ao cardápio"><?= icone('voltar') ?></a>
    <h1>Créditos das fotos</h1>
  </div>

  <div class="cartao texto-longo">
    <p>As fotos do cardápio são <strong>ilustrativas</strong> e vêm do
      <a href="https://commons.wikimedia.org" target="_blank" rel="noopener">Wikimedia Commons</a>,
      com licenças livres. Agradecemos aos autores. Elas podem ser trocadas pelas fotos reais da confeitaria
      em <em>Painel → Produtos e preços</em>.</p>
  </div>

  <div class="lista-creditos">
    <?php foreach ($creditos as $c): ?>
      <article class="cartao credito">
        <img src="<?= h($c['arquivo']) ?>" alt="" width="96" height="96" loading="lazy">
        <div>
          <strong><?= h($nomes[$c['arquivo']] ?? $c['titulo']) ?></strong>
          <small>Foto: <?= h($c['autor']) ?> · Licença <?= h($c['licenca']) ?></small>
          <small><a href="<?= h($c['pagina']) ?>" target="_blank" rel="noopener">“<?= h($c['titulo']) ?>” no Wikimedia Commons</a></small>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/rodape.php'; ?>
