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
        $whats = so_digitos((string) $_POST['whatsapp_loja']);
        $endereco = mb_substr(trim((string) $_POST['endereco_loja']), 0, 150);
        $cidade = mb_substr(trim((string) $_POST['cidade']), 0, 80);
        if (strlen($whats) < 10 || strlen($whats) > 13) {
            flash('erro', 'WhatsApp da loja inválido. Use DDD + número.');
        } elseif (mb_strlen($endereco) < 5) {
            flash('erro', 'Informe o endereço da loja (rua, número e bairro).');
        } else {
            salvar_config('loja_aberta', isset($_POST['loja_aberta']) ? '1' : '0');
            salvar_config('whatsapp_loja', strlen($whats) <= 11 ? '55' . $whats : $whats);
            salvar_config('horario', mb_substr(trim((string) $_POST['horario']), 0, 120));

            // Endereço mudou: localiza a loja no mapa de novo (ponto de partida do frete)
            if ($endereco !== config_loja('endereco_loja') || $cidade !== config_loja('cidade') || !config_loja('loja_lat')) {
                require_once __DIR__ . '/../includes/frete.php';
                $ponto = localizar_loja($endereco, $cidade);
                if ($ponto) {
                    salvar_config('loja_lat', (string) $ponto['lat']);
                    salvar_config('loja_lng', (string) $ponto['lng']);
                    flash('sucesso', 'Endereço da loja localizado no mapa. O frete passa a ser calculado a partir dele.');
                } else {
                    flash('info', 'Endereço salvo, mas não foi possível localizá-lo no mapa agora. O frete continua saindo do endereço anterior.');
                }
            }
            salvar_config('endereco_loja', $endereco);
            salvar_config('cidade', $cidade);
            flash('sucesso', 'Configurações da loja salvas.');
        }
    }

    if ($acao === 'frete') {
        $num = fn($campo) => str_replace(',', '.', trim((string) ($_POST[$campo] ?? '')));
        $valorKm = $num('valor_km');
        $taxaMinima = $num('taxa_minima') ?: '0';
        $taxaPadrao = $num('taxa_entrega');
        $raio = $num('raio_maximo_km');
        $preparo = $num('tempo_preparo');
        $duracao = (int) ($_POST['promo_duracao_horas'] ?? 0);

        $erros = [];
        if (!is_numeric($valorKm) || $valorKm <= 0 || $valorKm > 50)       $erros[] = 'valor por km';
        if (!is_numeric($taxaMinima) || $taxaMinima < 0 || $taxaMinima > 200) $erros[] = 'taxa mínima';
        if (!is_numeric($taxaPadrao) || $taxaPadrao < 0 || $taxaPadrao > 200) $erros[] = 'taxa padrão';
        if (!is_numeric($raio) || $raio < 0 || $raio > 200)                 $erros[] = 'raio de entrega';
        if (!ctype_digit($preparo) || $preparo < 0 || $preparo > 240)        $erros[] = 'tempo de preparo';
        if ($duracao < 1 || $duracao > 24 * 90)                              $erros[] = 'duração automática das promoções';

        if ($erros) {
            flash('erro', 'Confira: ' . implode(', ', $erros) . '.');
        } else {
            salvar_config('valor_km', number_format((float) $valorKm, 2, '.', ''));
            salvar_config('taxa_minima', number_format((float) $taxaMinima, 2, '.', ''));
            salvar_config('taxa_entrega', number_format((float) $taxaPadrao, 2, '.', ''));
            salvar_config('raio_maximo_km', (string) (float) $raio);
            salvar_config('tempo_preparo', (string) (int) $preparo);
            salvar_config('promo_duracao_horas', (string) $duracao);
            flash('sucesso', 'Frete e prazos salvos.');
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
  <div>
  <form method="post" class="cartao">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="loja">
    <h2>Loja</h2>
    <div class="campos">
      <label class="checkbox">
        <input type="checkbox" name="loja_aberta" <?= loja_aberta() ? 'checked' : '' ?>>
        Loja aberta (recebendo pedidos pelo site)
      </label>
      <div class="campo">
        <label for="endereco_loja">Endereço da loja (ponto de partida do frete)</label>
        <input id="endereco_loja" name="endereco_loja" maxlength="150" value="<?= h(config_loja('endereco_loja')) ?>" placeholder="R. Francisco Catalano, 440 - Jardim Brasilândia">
      </div>
      <p class="dica" style="margin:0">
        <?php if (config_loja('loja_lat')): ?>
          📍 Localizado no mapa (<?= h(config_loja('loja_lat')) ?>, <?= h(config_loja('loja_lng')) ?>) ·
          <a href="https://www.openstreetmap.org/?mlat=<?= h(config_loja('loja_lat')) ?>&mlon=<?= h(config_loja('loja_lng')) ?>#map=17/<?= h(config_loja('loja_lat')) ?>/<?= h(config_loja('loja_lng')) ?>" target="_blank" rel="noopener">conferir no mapa</a>
        <?php else: ?>
          ⚠️ Endereço ainda não localizado no mapa: o frete usa a taxa padrão.
        <?php endif; ?>
      </p>
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

  <form method="post" class="cartao">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="frete">
    <h2>Frete por distância e prazos</h2>
    <p class="dica" style="margin-top:0">Frete = distância da rota × valor por km. Tempo = preparo + trajeto (calculado pelo mapa).</p>
    <div class="campos">
      <div class="campos campos-duplos">
        <div class="campo">
          <label for="valor_km">Valor por km (R$)</label>
          <input id="valor_km" name="valor_km" inputmode="decimal" value="<?= h(number_format((float) config_loja('valor_km', '3.30'), 2, ',', '')) ?>">
        </div>
        <div class="campo">
          <label for="raio_maximo_km">Entregar até (km)</label>
          <input id="raio_maximo_km" name="raio_maximo_km" inputmode="decimal" value="<?= h(config_loja('raio_maximo_km', '25')) ?>">
        </div>
      </div>
      <div class="campos campos-duplos">
        <div class="campo">
          <label for="tempo_preparo">Tempo de preparo (min)</label>
          <input id="tempo_preparo" name="tempo_preparo" inputmode="numeric" value="<?= h(config_loja('tempo_preparo', '30')) ?>">
        </div>
        <div class="campo">
          <label for="taxa_minima">Frete mínimo (R$, 0 = sem mínimo)</label>
          <input id="taxa_minima" name="taxa_minima" inputmode="decimal" value="<?= h(number_format((float) config_loja('taxa_minima', '0'), 2, ',', '')) ?>">
        </div>
      </div>
      <div class="campo">
        <label for="taxa_entrega">Taxa padrão (R$) — usada só se o serviço de mapas estiver fora do ar</label>
        <input id="taxa_entrega" name="taxa_entrega" inputmode="decimal" value="<?= h(number_format((float) config_loja('taxa_entrega'), 2, ',', '')) ?>">
      </div>
      <div class="campo">
        <label for="promo_duracao_horas">Tempo automático para o fim das promoções</label>
        <select id="promo_duracao_horas" name="promo_duracao_horas">
          <?php foreach ([24, 48, 72, 120, 168, 336, 720] as $horas): ?>
            <option value="<?= $horas ?>" <?= duracao_promocao_horas() === $horas ? 'selected' : '' ?>><?= h(descrever_horas($horas)) ?></option>
          <?php endforeach; ?>
          <?php if (!in_array(duracao_promocao_horas(), [24, 48, 72, 120, 168, 336, 720], true)): ?>
            <option value="<?= duracao_promocao_horas() ?>" selected><?= h(descrever_horas(duracao_promocao_horas())) ?></option>
          <?php endif; ?>
        </select>
      </div>
      <button type="submit" class="botao">Salvar frete e prazos</button>
    </div>
  </form>
  </div>

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
