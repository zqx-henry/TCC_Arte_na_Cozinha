<?php
/**
 * API – Registrar pedido (UC05 Realizar pedido, inclui UC06 e UC07)
 * Recebe JSON do carrinho, valida os dados e grava CLIENTE, PEDIDO,
 * ITEM_PEDIDO, HISTORICO_STATUS e ENTREGA em uma única transação.
 *
 * Os preços e o frete são SEMPRE calculados no servidor (nunca vêm do navegador).
 */
require_once __DIR__ . '/../includes/frete.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'erro' => 'Método não permitido.'], 405);
}

$dados = json_decode(file_get_contents('php://input'), true);
if (!is_array($dados)) {
    responder_json(['ok' => false, 'erro' => 'Dados inválidos.'], 400);
}

if (!csrf_valido($dados['csrf'] ?? null)) {
    responder_json(['ok' => false, 'erro' => 'Sua sessão expirou. Atualize a página e tente novamente.'], 403);
}

if (!loja_aberta()) {
    responder_json(['ok' => false, 'erro' => 'A loja está fechada no momento. Tente novamente no horário de atendimento.'], 409);
}

// Evita pedidos duplicados por clique repetido (1 pedido a cada 15 s por sessão)
if (isset($_SESSION['ultimo_pedido_em']) && time() - $_SESSION['ultimo_pedido_em'] < 15) {
    responder_json(['ok' => false, 'erro' => 'Aguarde alguns segundos antes de enviar outro pedido.'], 429);
}

// ---------------------------------------------------------------------
// Validação dos dados de entrega (RF01)
// ---------------------------------------------------------------------
$texto = fn(string $campo, int $max) => mb_substr(trim(strip_tags((string) ($dados[$campo] ?? ''))), 0, $max);

$nome        = $texto('nome', 100);
$telefone    = so_digitos((string) ($dados['telefone'] ?? ''));
$endereco    = $texto('endereco', 150);
$bairro      = $texto('bairro', 60);
$complemento = $texto('complemento', 60);
$observacao  = $texto('observacao', 255);
$forma       = (string) ($dados['forma_pagamento'] ?? '');
$pagoNoSite  = !empty($dados['pago_no_site']);
$itens       = $dados['itens'] ?? [];

$erros = [];
if (mb_strlen($nome) < 2)                       $erros[] = 'nome';
if (!preg_match('/^\d{10,11}$/', $telefone))    $erros[] = 'WhatsApp com DDD';
if (mb_strlen($endereco) < 5)                   $erros[] = 'endereço';
if (mb_strlen($bairro) < 2)                     $erros[] = 'bairro';
if ($erros) {
    responder_json(['ok' => false, 'erro' => 'Preencha corretamente: ' . implode(', ', $erros) . '.'], 422);
}

// RF05: dinheiro somente na entrega; PIX somente pelo site
if (!isset(FORMAS_PAGAMENTO[$forma])
    || ($forma === 'dinheiro' && $pagoNoSite)
    || ($forma === 'pix' && !$pagoNoSite)) {
    responder_json(['ok' => false, 'erro' => 'Forma de pagamento inválida.'], 422);
}

if (!is_array($itens) || !$itens || count($itens) > 50) {
    responder_json(['ok' => false, 'erro' => 'Seu carrinho está vazio.'], 422);
}

// ---------------------------------------------------------------------
// Busca os produtos no banco e calcula os valores
// ---------------------------------------------------------------------
$quantidades = [];
foreach ($itens as $id => $qtd) {
    $id = (int) $id;
    $qtd = (int) $qtd;
    if ($id > 0 && $qtd > 0 && $qtd <= 50) {
        $quantidades[$id] = $qtd;
    }
}
if (!$quantidades) {
    responder_json(['ok' => false, 'erro' => 'Seu carrinho está vazio.'], 422);
}

$marcadores = implode(',', array_fill(0, count($quantidades), '?'));
$st = db()->prepare(SQL_PRODUTO_COM_PROMO . " WHERE p.disponivel = 1 AND p.id_produto IN ($marcadores)");
$st->execute(array_keys($quantidades));
$produtos = array_column($st->fetchAll(), null, 'id_produto');

if (count($produtos) !== count($quantidades)) {
    responder_json(['ok' => false, 'erro' => 'Algum produto do carrinho não está mais disponível. Atualize o carrinho.'], 409);
}

$linhas = [];
$subtotalPedido = 0.0;
foreach ($quantidades as $id => $qtd) {
    $preco = preco_atual($produtos[$id]);
    $sub = round($preco * $qtd, 2);
    $subtotalPedido += $sub;
    $linhas[] = [$id, $qtd, $preco, $sub];
}
// Frete por distância: reaproveita o cálculo mostrado no carrinho (até 30 min) ou calcula agora
$chaveFrete = normalizar_endereco("$endereco|$bairro");
$frete = $_SESSION['frete'][$chaveFrete] ?? null;
if (!$frete || $frete['em'] < time() - 1800) {
    $frete = calcular_frete($endereco, $bairro);
}
if (!$frete['ok']) {
    responder_json(['ok' => false, 'erro' => $frete['erro']], 422);
}
$taxa  = round((float) $frete['taxa'], 2);
$total = round($subtotalPedido + $taxa, 2);

// ---------------------------------------------------------------------
// Grava tudo em uma transação
// ---------------------------------------------------------------------
$pdo = db();
try {
    $pdo->beginTransaction();

    // Cliente sem cadastro: reaproveita o registro pelo WhatsApp e atualiza o endereço
    $st = $pdo->prepare('SELECT id_cliente FROM cliente WHERE telefone = ? ORDER BY id_cliente DESC LIMIT 1');
    $st->execute([$telefone]);
    $idCliente = $st->fetchColumn();

    if ($idCliente) {
        $pdo->prepare('UPDATE cliente SET nome = ?, endereco = ?, bairro = ?, complemento = ? WHERE id_cliente = ?')
            ->execute([$nome, $endereco, $bairro, $complemento ?: null, $idCliente]);
    } else {
        $pdo->prepare('INSERT INTO cliente (nome, telefone, endereco, bairro, complemento) VALUES (?, ?, ?, ?, ?)')
            ->execute([$nome, $telefone, $endereco, $bairro, $complemento ?: null]);
        $idCliente = (int) $pdo->lastInsertId();
    }

    $pdo->prepare(
        'INSERT INTO pedido (id_cliente, data_hora, status, forma_pagamento, pago_no_site, taxa_entrega, valor_total, observacao)
         VALUES (?, NOW(), \'recebido\', ?, ?, ?, ?, ?)'
    )->execute([$idCliente, $forma, $pagoNoSite ? 1 : 0, $taxa, $total, $observacao ?: null]);
    $idPedido = (int) $pdo->lastInsertId();

    $stItem = $pdo->prepare(
        'INSERT INTO item_pedido (id_pedido, id_produto, quantidade, preco_unitario, subtotal) VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($linhas as [$idProduto, $qtd, $preco, $sub]) {
        $stItem->execute([$idPedido, $idProduto, $qtd, $preco, $sub]);
    }

    $pdo->prepare('INSERT INTO historico_status (id_pedido, status, data_hora) VALUES (?, \'recebido\', NOW())')
        ->execute([$idPedido]);

    $pdo->prepare('INSERT INTO entrega (id_pedido, distancia_km, tempo_min, tempo_max, metodo) VALUES (?, ?, ?, ?, ?)')
        ->execute([$idPedido, $frete['distancia_km'], $frete['tempo_min'], $frete['tempo_max'], $frete['metodo']]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('Erro ao registrar pedido: ' . $e->getMessage());
    responder_json(['ok' => false, 'erro' => 'Não foi possível registrar o pedido agora. Tente novamente.'], 500);
}

$_SESSION['ultimo_pedido_em'] = time();

responder_json([
    'ok'  => true,
    'id'  => $idPedido,
    'url' => 'acompanhar.php?pedido=' . $idPedido . '&t=' . token_pedido($idPedido),
]);
