<?php
/**
 * Painel – Cadastrar / editar produto (RF10 / UC10)
 * Campos da entidade PRODUTO (Quadro 13), com envio de foto.
 */
require_once __DIR__ . '/../includes/auth.php';
$admin = exigir_login();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$produto = [
    'id_produto' => 0, 'nome' => '', 'descricao' => '', 'categoria' => CATEGORIAS[0],
    'preco' => '', 'preco_promocional' => '', 'imagem' => '', 'disponivel' => 1,
];

if ($id > 0) {
    $st = db()->prepare('SELECT * FROM produto WHERE id_produto = ?');
    $st->execute([$id]);
    $produto = $st->fetch() ?: null;
    if (!$produto) {
        flash('erro', 'Produto não encontrado.');
        redirecionar('produtos.php');
    }
}

/** "1.234,50" ou "1234.50" -> 1234.50 */
function ler_preco(string $valor): ?float
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

$erros = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_valido();

    $produto['nome']              = mb_substr(trim((string) $_POST['nome']), 0, 100);
    $produto['descricao']         = mb_substr(trim((string) $_POST['descricao']), 0, 255);
    $produto['categoria']         = (string) $_POST['categoria'];
    $produto['preco']             = ler_preco((string) $_POST['preco']);
    $produto['preco_promocional'] = ler_preco((string) ($_POST['preco_promocional'] ?? ''));
    $produto['disponivel']        = isset($_POST['disponivel']) ? 1 : 0;

    if (mb_strlen($produto['nome']) < 2)                       $erros['nome'] = 'Informe o nome do produto.';
    if (mb_strlen($produto['descricao']) < 3)                  $erros['descricao'] = 'Escreva uma descrição curta.';
    if (!in_array($produto['categoria'], CATEGORIAS, true))    $erros['categoria'] = 'Escolha uma categoria.';
    if ($produto['preco'] === null || $produto['preco'] <= 0)  $erros['preco'] = 'Informe um preço válido.';
    if ($produto['preco_promocional'] !== null
        && ($produto['preco_promocional'] <= 0 || $produto['preco_promocional'] >= (float) $produto['preco'])) {
        $erros['preco_promocional'] = 'O preço promocional deve ser menor que o preço normal (ou deixe em branco).';
    }

    // Upload da foto (JPG, PNG ou WEBP até 3 MB)
    $novaImagem = null;
    if (!empty($_FILES['imagem']['name'])) {
        $arq = $_FILES['imagem'];
        $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = $arq['error'] === UPLOAD_ERR_OK ? (new finfo(FILEINFO_MIME_TYPE))->file($arq['tmp_name']) : '';
        if ($arq['error'] !== UPLOAD_ERR_OK) {
            $erros['imagem'] = 'Não foi possível enviar a foto (tamanho máximo do servidor excedido?).';
        } elseif (!isset($tipos[$mime])) {
            $erros['imagem'] = 'Envie uma foto em JPG, PNG ou WEBP.';
        } elseif ($arq['size'] > 3 * 1024 * 1024) {
            $erros['imagem'] = 'A foto deve ter no máximo 3 MB.';
        } else {
            $pasta = __DIR__ . '/../uploads/produtos';
            if (!is_dir($pasta)) {
                mkdir($pasta, 0755, true);
            }
            $nomeArquivo = bin2hex(random_bytes(8)) . '.' . $tipos[$mime];
            if (move_uploaded_file($arq['tmp_name'], $pasta . '/' . $nomeArquivo)) {
                $novaImagem = 'uploads/produtos/' . $nomeArquivo;
            } else {
                $erros['imagem'] = 'Falha ao salvar a foto no servidor.';
            }
        }
    }

    if (!$erros) {
        $imagemAntiga = $produto['imagem'];
        $imagem = $novaImagem ?? ($produto['imagem'] ?: null);
        $dados = [
            $produto['nome'], $produto['descricao'], $produto['categoria'], $produto['preco'],
            $produto['preco_promocional'], $imagem, $produto['disponivel'],
        ];
        if ($id > 0) {
            db()->prepare(
                'UPDATE produto SET nome = ?, descricao = ?, categoria = ?, preco = ?, preco_promocional = ?, imagem = ?, disponivel = ?
                  WHERE id_produto = ?'
            )->execute([...$dados, $id]);
            if ($novaImagem && str_starts_with((string) $imagemAntiga, 'uploads/') && is_file(__DIR__ . '/../' . $imagemAntiga)) {
                unlink(__DIR__ . '/../' . $imagemAntiga);
            }
            flash('sucesso', 'Produto “' . $produto['nome'] . '” atualizado. As alterações já aparecem no cardápio.');
        } else {
            db()->prepare(
                'INSERT INTO produto (nome, descricao, categoria, preco, preco_promocional, imagem, disponivel, id_admin)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([...$dados, $admin['id']]);
            flash('sucesso', 'Produto “' . $produto['nome'] . '” cadastrado.');
        }
        redirecionar('produtos.php');
    } elseif ($novaImagem) {
        unlink(__DIR__ . '/../' . $novaImagem);
    }
}

$fmt = fn($v) => ($v === null || $v === '' || $v < 0) ? '' : number_format((float) $v, 2, ',', '');

$tituloPagina  = $id ? 'Editar produto' : 'Novo produto';
$menuAtivo     = 'produtos';
require __DIR__ . '/../includes/admin_topo.php';
?>

<div class="painel-cabecalho">
  <div>
    <a href="produtos.php" class="voltar-link"><?= icone('voltar') ?> Produtos</a>
    <h1><?= $id ? 'Editar produto' : 'Novo produto' ?></h1>
  </div>
</div>

<?php if ($erros): ?><div class="alerta alerta-erro">Confira os campos destacados.</div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="grade-formulario" novalidate>
  <?= csrf_campo() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">

  <section class="cartao">
    <div class="campos">
      <div class="campo <?= isset($erros['nome']) ? 'erro' : '' ?>">
        <label for="nome">Nome do produto</label>
        <input id="nome" name="nome" maxlength="100" required value="<?= h($produto['nome']) ?>">
        <span class="msg-erro"><?= h($erros['nome'] ?? '') ?></span>
      </div>
      <div class="campo <?= isset($erros['descricao']) ? 'erro' : '' ?>">
        <label for="descricao">Descrição (aparece no cardápio)</label>
        <textarea id="descricao" name="descricao" maxlength="255" required><?= h($produto['descricao']) ?></textarea>
        <span class="msg-erro"><?= h($erros['descricao'] ?? '') ?></span>
      </div>
      <div class="campo <?= isset($erros['categoria']) ? 'erro' : '' ?>">
        <label for="categoria">Categoria</label>
        <select id="categoria" name="categoria">
          <?php foreach (CATEGORIAS as $cat): ?>
            <option <?= $produto['categoria'] === $cat ? 'selected' : '' ?>><?= h($cat) ?></option>
          <?php endforeach; ?>
        </select>
        <span class="msg-erro"><?= h($erros['categoria'] ?? '') ?></span>
      </div>
      <div class="campos campos-duplos">
        <div class="campo <?= isset($erros['preco']) ? 'erro' : '' ?>">
          <label for="preco">Preço normal (R$)</label>
          <input id="preco" name="preco" inputmode="decimal" required placeholder="0,00" value="<?= h($fmt($produto['preco'])) ?>">
          <span class="msg-erro"><?= h($erros['preco'] ?? '') ?></span>
        </div>
        <div class="campo <?= isset($erros['preco_promocional']) ? 'erro' : '' ?>">
          <label for="preco_promocional">Preço promocional (opcional)</label>
          <input id="preco_promocional" name="preco_promocional" inputmode="decimal" placeholder="em branco = sem promoção" value="<?= h($fmt($produto['preco_promocional'])) ?>">
          <span class="msg-erro"><?= h($erros['preco_promocional'] ?? '') ?></span>
        </div>
      </div>
      <label class="checkbox">
        <input type="checkbox" name="disponivel" <?= $produto['disponivel'] ? 'checked' : '' ?>>
        Mostrar no cardápio (disponível para pedido)
      </label>
    </div>
  </section>

  <section class="cartao">
    <h2>Foto do produto</h2>
    <div class="previa-foto">
      <img id="previaImagem" src="../<?= h($produto['imagem'] ?: 'assets/img/logo-icone.svg') ?>" alt="Prévia da foto">
    </div>
    <div class="campo <?= isset($erros['imagem']) ? 'erro' : '' ?>" style="margin-top:12px">
      <input type="file" name="imagem" id="imagem" accept="image/jpeg,image/png,image/webp" class="entrada-arquivo">
      <span class="msg-erro"><?= h($erros['imagem'] ?? '') ?></span>
    </div>
    <p class="dica">JPG, PNG ou WEBP, até 3 MB. Dica: fotos quadradas ficam melhores.</p>
  </section>

  <div class="barra-salvar">
    <a href="produtos.php" class="botao botao-contorno">Cancelar</a>
    <button type="submit" class="botao"><?= $id ? 'Salvar alterações' : 'Cadastrar produto' ?></button>
  </div>
</form>

<script>
  document.getElementById('imagem').addEventListener('change', (e) => {
    const arquivo = e.target.files[0];
    if (arquivo) document.getElementById('previaImagem').src = URL.createObjectURL(arquivo);
  });
</script>

<?php require __DIR__ . '/../includes/admin_rodape.php'; ?>
