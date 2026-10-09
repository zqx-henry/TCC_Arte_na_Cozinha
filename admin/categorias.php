<?php
/**
 * Painel – Categorias do cardápio (Bolos, Doces, Tortas, Bebidas…)
 * Adicionar, renomear, mudar a ordem dos botões no cardápio e excluir.
 *
 * Ao renomear, todos os produtos da categoria são atualizados juntos (transação).
 */
require_once __DIR__ . '/../includes/auth.php';
$admin = exigir_login();

/** Renumera a ordem: 1, 2, 3… (mantém a lista sempre organizada) */
function renumerar_categorias(): void
{
    $ids = db()->query('SELECT id_categoria FROM categoria ORDER BY ordem, nome')->fetchAll(PDO::FETCH_COLUMN);
    $st = db()->prepare('UPDATE categoria SET ordem = ? WHERE id_categoria = ?');
    foreach ($ids as $i => $id) {
        $st->execute([$i + 1, $id]);
    }
}

function buscar_categoria(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM categoria WHERE id_categoria = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function nome_categoria_valido(string $nome, int $ignorarId = 0): ?string
{
    if (mb_strlen($nome) < 2 || mb_strlen($nome) > 40) {
        return 'O nome da categoria deve ter entre 2 e 40 caracteres.';
    }
    $st = db()->prepare('SELECT COUNT(*) FROM categoria WHERE nome = ? AND id_categoria <> ?');
    $st->execute([$nome, $ignorarId]);
    return (int) $st->fetchColumn() > 0 ? 'Já existe uma categoria chamada “' . $nome . '”.' : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_valido();
    $acao = $_POST['acao'] ?? '';
    $id   = (int) ($_POST['id'] ?? 0);
    $nome = trim(preg_replace('/\s+/', ' ', (string) ($_POST['nome'] ?? '')));
    $pdo  = db();

    if ($acao === 'adicionar') {
        if ($erro = nome_categoria_valido($nome)) {
            flash('erro', $erro);
        } else {
            $ordem = (int) $pdo->query('SELECT COALESCE(MAX(ordem), 0) + 1 FROM categoria')->fetchColumn();
            $pdo->prepare('INSERT INTO categoria (nome, ordem) VALUES (?, ?)')->execute([$nome, $ordem]);
            flash('sucesso', 'Categoria “' . $nome . '” criada. Ela aparece no cardápio quando tiver produtos.');
        }
    } elseif ($cat = buscar_categoria($id)) {
        if ($acao === 'renomear') {
            if ($nome === $cat['nome']) {
                // nada mudou
            } elseif ($erro = nome_categoria_valido($nome, $id)) {
                flash('erro', $erro);
            } else {
                $pdo->beginTransaction();
                $pdo->prepare('UPDATE categoria SET nome = ? WHERE id_categoria = ?')->execute([$nome, $id]);
                $pdo->prepare('UPDATE produto SET categoria = ? WHERE categoria = ?')->execute([$nome, $cat['nome']]);
                $pdo->commit();
                flash('sucesso', 'Categoria “' . $cat['nome'] . '” renomeada para “' . $nome . '”. Os produtos foram atualizados.');
            }
        } elseif ($acao === 'subir' || $acao === 'descer') {
            renumerar_categorias();
            $cat = buscar_categoria($id);
            $vizinha = $pdo->prepare($acao === 'subir'
                ? 'SELECT * FROM categoria WHERE ordem < ? ORDER BY ordem DESC LIMIT 1'
                : 'SELECT * FROM categoria WHERE ordem > ? ORDER BY ordem ASC LIMIT 1');
            $vizinha->execute([$cat['ordem']]);
            if ($v = $vizinha->fetch()) {
                $troca = $pdo->prepare('UPDATE categoria SET ordem = ? WHERE id_categoria = ?');
                $troca->execute([$v['ordem'], $cat['id_categoria']]);
                $troca->execute([$cat['ordem'], $v['id_categoria']]);
            }
        } elseif ($acao === 'excluir') {
            $st = $pdo->prepare('SELECT COUNT(*) FROM produto WHERE categoria = ?');
            $st->execute([$cat['nome']]);
            $qtd = (int) $st->fetchColumn();
            $destino = (string) ($_POST['destino'] ?? '');
            $total = (int) $pdo->query('SELECT COUNT(*) FROM categoria')->fetchColumn();

            if ($total <= 1) {
                flash('erro', 'O cardápio precisa ter pelo menos uma categoria.');
            } elseif ($qtd > 0 && (!in_array($destino, categorias(), true) || $destino === $cat['nome'])) {
                flash('erro', 'Escolha para qual categoria os ' . $qtd . ' produto(s) de “' . $cat['nome'] . '” vão antes de excluir.');
            } else {
                $pdo->beginTransaction();
                if ($qtd > 0) {
                    $pdo->prepare('UPDATE produto SET categoria = ? WHERE categoria = ?')->execute([$destino, $cat['nome']]);
                }
                $pdo->prepare('DELETE FROM categoria WHERE id_categoria = ?')->execute([$id]);
                $pdo->commit();
                renumerar_categorias();
                flash('sucesso', 'Categoria “' . $cat['nome'] . '” excluída' . ($qtd ? ' e ' . $qtd . ' produto(s) movido(s) para “' . $destino . '”.' : '.'));
            }
        }
    }
    redirecionar('categorias.php');
}

$lista = db()->query(
    'SELECT c.*, (SELECT COUNT(*) FROM produto p WHERE p.categoria = c.nome) AS produtos,
            (SELECT COUNT(*) FROM produto p WHERE p.categoria = c.nome AND p.disponivel = 1) AS no_cardapio
       FROM categoria c ORDER BY c.ordem, c.nome'
)->fetchAll();

$tituloPagina = 'Categorias';
$menuAtivo    = 'categorias';
require __DIR__ . '/../includes/admin_topo.php';
?>

<div class="painel-cabecalho">
  <div>
    <h1>Categorias</h1>
    <p class="subtitulo">Os botões do cardápio (Todos, Bolos, Doces…) aparecem nesta ordem. Categorias sem produtos ficam escondidas do cliente.</p>
  </div>
</div>

<section class="cartao">
  <h2>Nova categoria</h2>
  <form method="post" class="form-linha">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="adicionar">
    <div class="campo" style="flex:1">
      <label for="novaCategoria">Nome</label>
      <input id="novaCategoria" name="nome" maxlength="40" required placeholder="Ex.: Salgados, Sobremesas, Kits de festa">
    </div>
    <button type="submit" class="botao">Adicionar</button>
  </form>
</section>

<div class="lista-categorias">
  <?php foreach ($lista as $i => $c): $idc = (int) $c['id_categoria']; ?>
  <article class="cartao categoria-item">
    <div class="categoria-ordem">
      <form method="post">
        <?= csrf_campo() ?><input type="hidden" name="id" value="<?= $idc ?>">
        <button type="submit" name="acao" value="subir" class="botao-ver" title="Subir" aria-label="Subir <?= h($c['nome']) ?>" <?= $i === 0 ? 'disabled' : '' ?>>▲</button>
      </form>
      <span class="categoria-posicao"><?= $i + 1 ?>º</span>
      <form method="post">
        <?= csrf_campo() ?><input type="hidden" name="id" value="<?= $idc ?>">
        <button type="submit" name="acao" value="descer" class="botao-ver" title="Descer" aria-label="Descer <?= h($c['nome']) ?>" <?= $i === count($lista) - 1 ? 'disabled' : '' ?>>▼</button>
      </form>
    </div>

    <form method="post" class="form-linha categoria-nome">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="renomear">
      <input type="hidden" name="id" value="<?= $idc ?>">
      <div class="campo" style="flex:1">
        <label for="cat<?= $idc ?>">Nome da categoria</label>
        <input id="cat<?= $idc ?>" name="nome" maxlength="40" required value="<?= h($c['nome']) ?>">
      </div>
      <button type="submit" class="botao-acao">Salvar nome</button>
    </form>

    <div class="categoria-info">
      <a href="produtos.php?categoria=<?= urlencode($c['nome']) ?>"><?= (int) $c['produtos'] ?> produto(s)</a>
      <small><?= (int) $c['no_cardapio'] ?> no cardápio</small>
    </div>

    <details class="categoria-excluir">
      <summary>Excluir</summary>
      <form method="post" data-confirmar="Excluir a categoria “<?= h($c['nome']) ?>”?">
        <?= csrf_campo() ?>
        <input type="hidden" name="acao" value="excluir">
        <input type="hidden" name="id" value="<?= $idc ?>">
        <?php if ((int) $c['produtos'] > 0): ?>
          <div class="campo">
            <label for="dest<?= $idc ?>">Mover os <?= (int) $c['produtos'] ?> produto(s) para</label>
            <select id="dest<?= $idc ?>" name="destino" required>
              <option value="">Escolha…</option>
              <?php foreach ($lista as $outra): if ($outra['id_categoria'] == $idc) continue; ?>
                <option><?= h($outra['nome']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
        <button type="submit" class="botao-acao perigo">Excluir categoria</button>
      </form>
    </details>
  </article>
  <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../includes/admin_rodape.php'; ?>
