<?php
/**
 * Painel – Promoções (RF10 / UC10)
 * Define o preço promocional, o tempo para o fim do desconto (automático ou manual)
 * e a "Promoção da semana" exibida no banner da página inicial.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_promocao.php';
$admin = exigir_login();

/** "13,90" ou "13.90" -> 13.90 (null se vazio, -1 se inválido) */
function ler_valor(string $valor): ?float
{
    $valor = trim(str_replace(['R$', ' '], '', $valor));
    if ($valor === '') {
        return null;
    }
    if (str_contains($valor, ',')) {
        $valor = str_replace(['.', ','], ['', '.'], $valor);
    }
    return is_numeric($valor) ? round((float) $valor, 2) : -1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_valido();
    $id   = (int) ($_POST['id'] ?? 0);
    $acao = $_POST['acao'] ?? 'salvar';

    $st = db()->prepare('SELECT nome, preco FROM produto WHERE id_produto = ?');
    $st->execute([$id]);
    $p = $st->fetch();

    if (!$p) {
        flash('erro', 'Escolha um produto.');
    } elseif ($acao === 'destaque') {
        db()->prepare('INSERT INTO configuracao (chave, valor) VALUES (\'produto_destaque\', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)')
            ->execute([(string) $id]);
        flash('sucesso', '“' . $p['nome'] . '” agora é a promoção da semana no banner do site.');
    } elseif ($acao === 'encerrar') {
        salvar_promocao($id, null);
        flash('sucesso', 'Promoção de “' . $p['nome'] . '” encerrada. O produto voltou ao preço normal.');
    } else {
        $valor = ler_valor((string) ($_POST['preco_promocional'] ?? ''));
        if ($valor === null || $valor <= 0 || $valor >= (float) $p['preco']) {
            flash('erro', 'O preço promocional de “' . $p['nome'] . '” deve ser maior que zero e menor que ' . dinheiro($p['preco']) . '.');
        } elseif ($erro = salvar_promocao($id, $valor, (string) ($_POST['modo_prazo'] ?? 'automatico'), (string) ($_POST['fim_manual'] ?? ''))) {
            flash('erro', $erro);
        } else {
            $st = db()->prepare('SELECT data_fim FROM promocao WHERE id_produto = ?');
            $st->execute([$id]);
            $fim = strtotime((string) $st->fetchColumn());
            flash('sucesso', 'Promoção de “' . $p['nome'] . '” salva: ' . dinheiro($valor)
                . '. O desconto acaba sozinho em ' . date('d/m', $fim) . ' às ' . date('H:i', $fim) . '.');
        }
    }
    redirecionar('promocoes.php');
}

$produtos = db()->query(
    SQL_PRODUTO_COM_PROMO . " ORDER BY FIELD(p.categoria, 'Bolos', 'Doces', 'Tortas', 'Bebidas'), p.nome"
)->fetchAll();
$ativas   = array_values(array_filter($produtos, 'em_promocao'));
usort($ativas, fn($a, $b) => strcmp((string) $a['promo_fim'], (string) $b['promo_fim'])); // acabando primeiro
$semPromo = array_values(array_filter($produtos, fn($p) => !em_promocao($p) && $p['disponivel']));
$destaque = config_loja('produto_destaque');

$tituloPagina = 'Promoções';
$menuAtivo    = 'promocoes';
require __DIR__ . '/../includes/admin_topo.php';
?>

<div class="painel-cabecalho">
  <div>
    <h1>Promoções</h1>
    <p class="subtitulo"><?= count($ativas) ?> promoção(ões) ativa(s) · cada desconto acaba sozinho no prazo definido
      (automático: <?= h(descrever_horas(duracao_promocao_horas())) ?>, alterável em <a href="configuracoes.php">Configurações</a>)</p>
  </div>
</div>

<!-- Nova promoção -->
<section class="cartao">
  <h2>Nova promoção</h2>
  <?php if ($semPromo): ?>
  <form method="post" class="form-nova-promo">
    <?= csrf_campo() ?>
    <div class="campos campos-duplos">
      <div class="campo">
        <label for="novoProduto">Produto</label>
        <select id="novoProduto" name="id" required>
          <option value="">Escolha…</option>
          <?php foreach ($semPromo as $p): ?>
            <option value="<?= (int) $p['id_produto'] ?>" data-preco="<?= h($p['preco']) ?>"><?= h($p['nome']) ?> — <?= dinheiro($p['preco']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="campo">
        <label for="novoPreco">Preço promocional (R$)</label>
        <input id="novoPreco" name="preco_promocional" inputmode="decimal" placeholder="0,00" required>
      </div>
    </div>
    <?= campos_prazo_promocao('nova') ?>
    <button type="submit" class="botao">Criar promoção</button>
  </form>
  <?php else: ?>
    <p class="dica">Todos os produtos do cardápio já estão em promoção.</p>
  <?php endif; ?>
</section>

<!-- Promoções ativas -->
<h2 class="titulo-bloco">Promoções ativas</h2>
<?php if (!$ativas): ?>
  <div class="cartao vazio">Nenhuma promoção ativa no momento.</div>
<?php endif; ?>

<div class="lista-promocoes">
  <?php foreach ($ativas as $p):
      $desconto = round((1 - $p['preco_promocional'] / $p['preco']) * 100);
      $idp = (int) $p['id_produto'];
  ?>
  <article class="cartao promo-card <?= $p['disponivel'] ? '' : 'linha-inativa' ?>">
    <div class="promo-topo">
      <div class="produto-celula">
        <img src="../<?= h($p['imagem'] ?: 'assets/img/logo-icone.svg') ?>" alt="" width="48" height="48" loading="lazy">
        <div>
          <strong><?= h($p['nome']) ?></strong>
          <small><?= h($p['categoria']) ?><?= $p['disponivel'] ? '' : ' · oculto no cardápio' ?></small>
        </div>
      </div>
      <div class="promo-precos">
        <s><?= dinheiro($p['preco']) ?></s>
        <strong><?= dinheiro($p['preco_promocional']) ?></strong>
        <span class="etiqueta etiqueta-preparo">−<?= $desconto ?>%</span>
      </div>
    </div>

    <p class="promo-prazo"><?= texto_prazo_promocao($p) ?></p>

    <div class="acoes-linha">
      <?php if ($destaque === (string) $idp): ?>
        <span class="etiqueta etiqueta-novo">★ Promoção da semana (banner)</span>
      <?php elseif ($p['disponivel']): ?>
        <form method="post">
          <?= csrf_campo() ?>
          <input type="hidden" name="id" value="<?= $idp ?>">
          <button type="submit" name="acao" value="destaque" class="botao-acao">★ Destacar no banner</button>
        </form>
      <?php endif; ?>
      <form method="post" data-confirmar="Encerrar agora a promoção de “<?= h($p['nome']) ?>”?">
        <?= csrf_campo() ?>
        <input type="hidden" name="id" value="<?= $idp ?>">
        <button type="submit" name="acao" value="encerrar" class="botao-acao perigo">Encerrar agora</button>
      </form>
    </div>

    <details class="promo-editar">
      <summary>Alterar preço ou prazo</summary>
      <form method="post">
        <?= csrf_campo() ?>
        <input type="hidden" name="id" value="<?= $idp ?>">
        <div class="campo" style="max-width:240px">
          <label for="preco<?= $idp ?>">Preço promocional (R$)</label>
          <input id="preco<?= $idp ?>" name="preco_promocional" inputmode="decimal" required
                 value="<?= number_format((float) $p['preco_promocional'], 2, ',', '') ?>">
        </div>
        <?= campos_prazo_promocao((string) $idp, $p['promo_modo'], $p['promo_fim']) ?>
        <button type="submit" class="botao">Salvar alterações</button>
      </form>
    </details>
  </article>
  <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../includes/admin_rodape.php'; ?>
