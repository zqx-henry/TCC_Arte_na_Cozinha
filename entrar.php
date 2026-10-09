<?php
/**
 * Entrar — login simplificado do cliente (nome + WhatsApp, sem senha)
 *
 * - WhatsApp já usado em pedidos: o primeiro nome precisa conferir com o cadastro.
 * - WhatsApp novo: a conta é criada automaticamente no primeiro pedido.
 * Vantagens: endereço preenchido sozinho, pedidos anteriores e "pedir de novo".
 */
require_once __DIR__ . '/includes/bootstrap.php';

$voltar = (string) ($_GET['voltar'] ?? $_POST['voltar'] ?? 'conta.php');
if (!in_array($voltar, ['conta.php', 'carrinho.php', 'index.php'], true)) {
    $voltar = 'conta.php';
}
if (cliente_logado()) {
    redirecionar($voltar);
}

$erro = '';
$nome = '';
$telefone = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($_POST['nome'] ?? '')))), 0, 100);
    $telefone = so_digitos((string) ($_POST['telefone'] ?? ''));

    // Limite de tentativas: 8 a cada 10 minutos (evita adivinhar nomes)
    $tentativas = array_filter($_SESSION['entrar_tentativas'] ?? [], fn($t) => $t > time() - 600);

    if (!csrf_valido($_POST['csrf'] ?? null)) {
        $erro = 'Sua sessão expirou. Tente novamente.';
    } elseif (count($tentativas) >= 8) {
        $erro = 'Muitas tentativas. Aguarde alguns minutos.';
    } elseif (mb_strlen($nome) < 2) {
        $erro = 'Informe seu nome.';
    } elseif (!preg_match('/^\d{10,11}$/', $telefone)) {
        $erro = 'Informe um WhatsApp válido com DDD.';
    } else {
        $st = db()->prepare('SELECT id_cliente, nome FROM cliente WHERE telefone = ? ORDER BY id_cliente DESC LIMIT 1');
        $st->execute([$telefone]);
        $cadastro = $st->fetch();

        $primeiro = fn(string $n) => explode(' ', normalizar_texto($n))[0];

        if ($cadastro && $primeiro($cadastro['nome']) !== $primeiro($nome)) {
            $tentativas[] = time();
            $_SESSION['entrar_tentativas'] = array_values($tentativas);
            $erro = 'O nome não confere com o cadastro deste WhatsApp. Use o mesmo nome dos seus pedidos.';
        } else {
            unset($_SESSION['entrar_tentativas']);
            entrar_cliente(
                $cadastro ? (int) $cadastro['id_cliente'] : null,
                $cadastro ? $cadastro['nome'] : $nome,
                $telefone
            );
            flash('sucesso', $cadastro
                ? 'Olá, ' . explode(' ', $cadastro['nome'])[0] . '! Seu endereço e seus pedidos foram carregados.'
                : 'Bem-vindo(a)! Seu endereço será salvo no seu primeiro pedido.');
            redirecionar($voltar);
        }
    }
}

$tituloPagina = 'Entrar | Arte na Cozinha';
$paginaAtual  = 'conta';
require __DIR__ . '/includes/cabecalho.php';
?>
<div class="container" style="max-width:520px">
  <div class="cabecalho-pagina">
    <a href="<?= h($voltar === 'conta.php' ? 'index.php' : $voltar) ?>" class="botao-icone" aria-label="Voltar"><?= icone('voltar') ?></a>
    <h1>Entrar</h1>
  </div>

  <form class="cartao" method="post" novalidate>
    <h2>É rapidinho: só nome e WhatsApp</h2>
    <p class="dica" style="margin-top:0">Sem senha e sem cadastro longo.</p>

    <?php if ($erro): ?><div class="alerta alerta-erro" role="alert"><?= h($erro) ?></div><?php endif; ?>

    <?= csrf_campo() ?>
    <input type="hidden" name="voltar" value="<?= h($voltar) ?>">
    <div class="campos">
      <div class="campo">
        <label for="nome">Seu nome</label>
        <input id="nome" name="nome" autocomplete="name" maxlength="100" required value="<?= h($nome) ?>" placeholder="Maria Souza">
      </div>
      <div class="campo">
        <label for="telefone">WhatsApp</label>
        <input id="telefone" name="telefone" type="tel" inputmode="tel" autocomplete="tel" maxlength="16" required
               value="<?= h($telefone ? formatar_telefone($telefone) : '') ?>" placeholder="(15) 99999-0000">
      </div>
      <button type="submit" class="botao botao-bloco botao-grande">Entrar</button>
    </div>

    <ul class="vantagens">
      <li>📍 Seu endereço preenchido sozinho no carrinho</li>
      <li>🧾 Seus pedidos anteriores em um só lugar</li>
      <li>🔁 "Pedir de novo" com um toque</li>
      <li>🛵 Acompanhe os pedidos sem precisar do link</li>
    </ul>
    <p class="nota" style="font-size:.8rem">Usamos seus dados apenas para as entregas. <a href="privacidade.php">Aviso de privacidade</a>.</p>
  </form>
</div>
<script>
  // Máscara do WhatsApp: (15) 99999-0000
  const tel = document.getElementById('telefone');
  tel.addEventListener('input', () => {
    const d = tel.value.replace(/\D/g, '').slice(0, 11);
    let v = d;
    if (d.length > 2) v = `(${d.slice(0, 2)}) ${d.slice(2)}`;
    if (d.length > 7) v = `(${d.slice(0, 2)}) ${d.slice(2, d.length - 4)}-${d.slice(-4)}`;
    tel.value = v;
  });
</script>
<?php require __DIR__ . '/includes/rodape.php'; ?>
