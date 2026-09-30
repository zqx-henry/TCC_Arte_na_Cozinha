<?php
/**
 * Painel – Promoções (RF10 / UC10)
 * Define o preço promocional de cada produto e a "Promoção da semana"
 * exibida no banner da página inicial.
 */
require_once __DIR__ . '/../includes/auth.php';
$admin = exigir_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_valido();
    $id = (int) ($_POST['id'] ?? 0);

    if (($_POST['acao'] ?? '') === 'destaque') {
        db()->prepare('INSERT INTO configuracao (chave, valor) VALUES (\'produto_destaque\', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)')
            ->execute([(string) $id]);
        flash('sucesso', 'Promoção da semana atualizada no banner do site.');
    } else {
        $st = db()->prepare('SELECT nome, preco FROM produto WHERE id_produto = ?');
        $st->execute([$id]);
        $p = $st->fetch();
        $valor = trim(str_replace(['R$', ' '], '', (string) ($_POST['preco_promocional'] ?? '')));
        if (str_contains($valor, ',')) {
            $valor = str_replace(['.', ','], ['', '.'], $valor);
        }

        if (!$p) {
            flash('erro', 'Produto não encontrado.');
        } elseif ($valor === '' || ($_POST['acao'] ?? '') === 'remover') {
            db()->prepare('UPDATE produto SET preco_promocional = NULL WHERE id_produto = ?')->execute([$id]);
            flash('sucesso', 'Promoção de “' . $p['nome'] . '” encerrada.');
        } elseif (!is_numeric($valor) || (float) $valor <= 0 || (float) $valor >= (float) $p['preco']) {
            flash('erro', 'O preço promocional de “' . $p['nome'] . '” deve ser maior que zero e menor que ' . dinheiro($p['preco']) . '.');
        } else {
            db()->prepare('UPDATE produto SET preco_promocional = ? WHERE id_produto = ?')->execute([round((float) $valor, 2), $id]);
            flash('sucesso', 'Promoção de “' . $p['nome'] . '” salva: ' . dinheiro($valor) . '.');
        }
    }
    redirecionar('promocoes.php');
}

$produtos = db()->query(
    "SELECT * FROM produto ORDER BY (preco_promocional IS NULL), FIELD(categoria, 'Bolos', 'Doces', 'Tortas', 'Bebidas'), nome"
)->fetchAll();
$destaque = config_loja('produto_destaque');
$ativas = count(array_filter($produtos, 'em_promocao'));

$tituloPagina = 'Promoções';
$menuAtivo    = 'promocoes';
require __DIR__ . '/../includes/admin_topo.php';
?>

<div class="painel-cabecalho">
  <div>
    <h1>Promoções</h1>
    <p class="subtitulo"><?= $ativas ?> promoção(ões) ativa(s) · aparecem com selo no cardápio e no filtro “Promoções”</p>
  </div>
</div>

<div class="tabela-caixa">
  <table class="tabela tabela-responsiva">
    <thead>
      <tr><th>Produto</th><th>Preço normal</th><th>Preço promocional</th><th>Desconto</th><th>Banner da semana</th></tr>
    </thead>
    <tbody>
      <?php foreach ($produtos as $p):
          $promo = em_promocao($p);
          $desconto = $promo ? round((1 - $p['preco_promocional'] / $p['preco']) * 100) : 0;
      ?>
        <tr class="<?= $p['disponivel'] ? '' : 'linha-inativa' ?>">
          <td data-rotulo="Produto">
            <div class="produto-celula">
              <img src="../<?= h($p['imagem'] ?: 'assets/img/logo-icone.svg') ?>" alt="" width="48" height="48" loading="lazy">
              <div><strong><?= h($p['nome']) ?></strong><small><?= h($p['categoria']) ?><?= $p['disponivel'] ? '' : ' · oculto no cardápio' ?></small></div>
            </div>
          </td>
          <td data-rotulo="Preço normal"><?= dinheiro($p['preco']) ?></td>
          <td data-rotulo="Preço promocional">
            <form method="post" class="form-promo">
              <?= csrf_campo() ?>
              <input type="hidden" name="id" value="<?= (int) $p['id_produto'] ?>">
              <input name="preco_promocional" inputmode="decimal" placeholder="—" aria-label="Preço promocional de <?= h($p['nome']) ?>"
                     value="<?= $promo ? number_format((float) $p['preco_promocional'], 2, ',', '') : '' ?>">
              <button type="submit" class="botao-acao">Salvar</button>
              <?php if ($promo): ?>
                <button type="submit" name="acao" value="remover" class="botao-ver perigo" title="Encerrar promoção" aria-label="Encerrar promoção"><?= icone('x') ?></button>
              <?php endif; ?>
            </form>
          </td>
          <td data-rotulo="Desconto"><?= $promo ? '<span class="etiqueta etiqueta-preparo">−' . $desconto . '%</span>' : '<span class="traco">—</span>' ?></td>
          <td data-rotulo="Banner da semana">
            <?php if ($promo && $p['disponivel']): ?>
              <?php if ($destaque === (string) $p['id_produto']): ?>
                <span class="etiqueta etiqueta-novo">★ Em destaque</span>
              <?php else: ?>
                <form method="post">
                  <?= csrf_campo() ?>
                  <input type="hidden" name="id" value="<?= (int) $p['id_produto'] ?>">
                  <button type="submit" name="acao" value="destaque" class="botao-acao">Destacar</button>
                </form>
              <?php endif; ?>
            <?php else: ?>
              <span class="traco">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../includes/admin_rodape.php'; ?>
