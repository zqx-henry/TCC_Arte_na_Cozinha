<?php
/**
 * Painel – Configurações da loja
 * Loja aberta/fechada, taxa de entrega, WhatsApp, troca de senha e administradores.
 */
require_once __DIR__ . '/../includes/auth.php';
$admin = exigir_login();

function salvar_config(string $chave, string $valor): void
{
    db()->prepare('INSERT INTO configuracao (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)')
        ->execute([$chave, $valor]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigir_post_valido();
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'alternar_loja') {
        $abrir = !loja_aberta();
        salvar_config('loja_aberta', $abrir ? '1' : '0');
        flash($abrir ? 'sucesso' : 'info', $abrir ? 'Loja aberta! O site já está recebendo pedidos.' : 'Loja fechada. O site não aceita novos pedidos até você abrir novamente.');
        // Volta para a página do painel de onde veio (somente caminhos do próprio painel)
        $ref = parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''));
        $caminho = ($ref['path'] ?? '') . (isset($ref['query']) ? '?' . $ref['query'] : '');
        redirecionar(str_contains($ref['path'] ?? '', '/admin/') ? $caminho : 'index.php');
    }

    if ($acao === 'loja') {
        $taxa = str_replace(',', '.', trim((string) $_POST['taxa_entrega']));
        $whats = so_digitos((string) $_POST['whatsapp_loja']);
        if (!is_numeric($taxa) || (float) $taxa < 0 || (float) $taxa > 100) {
            flash('erro', 'Taxa de entrega inválida.');
        } elseif (strlen($whats) < 10 || strlen($whats) > 13) {
            flash('erro', 'WhatsApp da loja inválido. Use DDD + número.');
        } else {
            salvar_config('loja_aberta', isset($_POST['loja_aberta']) ? '1' : '0');
            salvar_config('taxa_entrega', number_format((float) $taxa, 2, '.', ''));
            salvar_config('tempo_entrega', mb_substr(trim((string) $_POST['tempo_entrega']), 0, 30) ?: '40–60 min');
            salvar_config('whatsapp_loja', strlen($whats) <= 11 ? '55' . $whats : $whats);
            salvar_config('horario', mb_substr(trim((string) $_POST['horario']), 0, 120));
            salvar_config('cidade', mb_substr(trim((string) $_POST['cidade']), 0, 80));
            flash('sucesso', 'Configurações da loja salvas.');
        }
    }

    if ($acao === 'senha') {
        $st = db()->prepare('SELECT senha_hash FROM administrador WHERE id_admin = ?');
        $st->execute([$admin['id']]);
        $nova = (string) $_POST['nova_senha'];
        if (!password_verify((string) $_POST['senha_atual'], (string) $st->fetchColumn())) {
            flash('erro', 'A senha atual está incorreta.');
        } elseif (strlen($nova) < 8) {
            flash('erro', 'A nova senha precisa ter pelo menos 8 caracteres.');
        } elseif ($nova !== $_POST['confirmar_senha']) {
            flash('erro', 'A confirmação não confere com a nova senha.');
        } else {
            db()->prepare('UPDATE administrador SET senha_hash = ? WHERE id_admin = ?')
                ->execute([password_hash($nova, PASSWORD_DEFAULT), $admin['id']]);
            flash('sucesso', 'Senha alterada com sucesso.');
        }
    }

    if ($acao === 'novo_admin') {
        $nome  = mb_substr(trim((string) $_POST['nome']), 0, 100);
        $email = mb_strtolower(trim((string) $_POST['email']));
        $senha = (string) $_POST['senha'];
        $existe = db()->prepare('SELECT COUNT(*) FROM administrador WHERE email = ?');
        $existe->execute([$email]);
        if (mb_strlen($nome) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('erro', 'Informe nome e e-mail válidos para o novo administrador.');
        } elseif ((int) $existe->fetchColumn() > 0) {
            flash('erro', 'Já existe um administrador com esse e-mail.');
        } elseif (strlen($senha) < 8) {
            flash('erro', 'A senha do novo administrador precisa ter pelo menos 8 caracteres.');
        } else {
            db()->prepare('INSERT INTO administrador (nome, email, senha_hash) VALUES (?, ?, ?)')
                ->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT)]);
            flash('sucesso', 'Administrador “' . $nome . '” cadastrado.');
        }
    }

    redirecionar('configuracoes.php');
}

$admins = db()->query('SELECT id_admin, nome, email FROM administrador ORDER BY nome')->fetchAll();

$tituloPagina = 'Configurações';
$menuAtivo    = 'configuracoes';
require __DIR__ . '/../includes/admin_topo.php';
?>

<div class="painel-cabecalho">
  <div>
    <h1>Configurações</h1>
    <p class="subtitulo">Dados da loja exibidos no site e acesso ao painel</p>
  </div>
</div>

<div class="grade-detalhe">
  <form method="post" class="cartao">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="loja">
    <h2>Loja</h2>
    <div class="campos">
      <label class="checkbox">
        <input type="checkbox" name="loja_aberta" <?= loja_aberta() ? 'checked' : '' ?>>
        Loja aberta (recebendo pedidos pelo site)
      </label>
      <div class="campos campos-duplos">
        <div class="campo">
          <label for="taxa_entrega">Taxa de entrega (R$)</label>
          <input id="taxa_entrega" name="taxa_entrega" inputmode="decimal" value="<?= h(number_format((float) config_loja('taxa_entrega'), 2, ',', '')) ?>">
        </div>
        <div class="campo">
          <label for="tempo_entrega">Tempo de entrega</label>
          <input id="tempo_entrega" name="tempo_entrega" value="<?= h(config_loja('tempo_entrega')) ?>" placeholder="40–60 min">
        </div>
      </div>
      <div class="campo">
        <label for="whatsapp_loja">WhatsApp da loja (recebe as confirmações)</label>
        <input id="whatsapp_loja" name="whatsapp_loja" type="tel" value="<?= h(formatar_telefone(config_loja('whatsapp_loja'))) ?>">
      </div>
      <div class="campo">
        <label for="horario">Horário de atendimento</label>
        <input id="horario" name="horario" value="<?= h(config_loja('horario')) ?>">
      </div>
      <div class="campo">
        <label for="cidade">Cidade</label>
        <input id="cidade" name="cidade" value="<?= h(config_loja('cidade')) ?>">
      </div>
      <button type="submit" class="botao">Salvar configurações</button>
    </div>
  </form>

  <div>
    <form method="post" class="cartao">
      <?= csrf_campo() ?>
      <input type="hidden" name="acao" value="senha">
      <h2>Alterar minha senha</h2>
      <div class="campos">
        <div class="campo">
          <label for="senha_atual">Senha atual</label>
          <input id="senha_atual" name="senha_atual" type="password" autocomplete="current-password" required>
        </div>
        <div class="campo">
          <label for="nova_senha">Nova senha (mín. 8 caracteres)</label>
          <input id="nova_senha" name="nova_senha" type="password" autocomplete="new-password" minlength="8" required>
        </div>
        <div class="campo">
          <label for="confirmar_senha">Confirmar nova senha</label>
          <input id="confirmar_senha" name="confirmar_senha" type="password" autocomplete="new-password" required>
        </div>
        <button type="submit" class="botao">Alterar senha</button>
      </div>
    </form>

    <section class="cartao">
      <h2>Administradores</h2>
      <ul class="lista-admins">
        <?php foreach ($admins as $a): ?>
          <li><strong><?= h($a['nome']) ?></strong><small><?= h($a['email']) ?><?= (int) $a['id_admin'] === $admin['id'] ? ' · você' : '' ?></small></li>
        <?php endforeach; ?>
      </ul>
      <details class="novo-admin">
        <summary>Adicionar administrador</summary>
        <form method="post" class="campos" style="margin-top:12px">
          <?= csrf_campo() ?>
          <input type="hidden" name="acao" value="novo_admin">
          <div class="campo"><label for="na_nome">Nome</label><input id="na_nome" name="nome" required></div>
          <div class="campo"><label for="na_email">E-mail</label><input id="na_email" name="email" type="email" required></div>
          <div class="campo"><label for="na_senha">Senha inicial (mín. 8)</label><input id="na_senha" name="senha" type="password" autocomplete="new-password" minlength="8" required></div>
          <button type="submit" class="botao botao-contorno">Cadastrar administrador</button>
        </form>
      </details>
    </section>
  </div>
</div>

<?php require __DIR__ . '/../includes/admin_rodape.php'; ?>
