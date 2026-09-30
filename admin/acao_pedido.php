<?php
/**
 * Atualizar status do pedido (UC12 — estende UC11)
 * recebido → em_preparo (aceite) → saiu_entrega → entregue   |   cancelado
 */
require_once __DIR__ . '/../includes/auth.php';
$admin = exigir_login();
exigir_post_valido();

$id     = (int) ($_POST['id'] ?? 0);
$novo   = (string) ($_POST['status'] ?? '');
$voltar = (string) ($_POST['voltar'] ?? 'index.php');

// Só permite voltar para páginas do próprio painel
if (!preg_match('#^(/[\w./-]*admin/)?[\w-]+\.php(\?[\w=&%.+-]*)?$#', $voltar)) {
    $voltar = 'index.php';
}

$st = db()->prepare('SELECT status FROM pedido WHERE id_pedido = ?');
$st->execute([$id]);
$atual = $st->fetchColumn();

if ($atual === false) {
    flash('erro', 'Pedido não encontrado.');
    redirecionar($voltar);
}

$permitido = (PROXIMO_STATUS[$atual]['status'] ?? null) === $novo
          || ($novo === 'cancelado' && !in_array($atual, ['entregue', 'cancelado'], true));

if (!$permitido) {
    flash('erro', 'Não é possível mudar o pedido nº ' . $id . ' de "' . STATUS_PEDIDO[$atual]['admin'] . '" para esse status.');
    redirecionar($voltar);
}

$pdo = db();
$pdo->beginTransaction();
// O administrador que aceita passa a gerenciar o pedido (id_admin — Quadro 11)
$pdo->prepare('UPDATE pedido SET status = ?, id_admin = COALESCE(id_admin, ?) WHERE id_pedido = ?')
    ->execute([$novo, $admin['id'], $id]);
$pdo->prepare('INSERT INTO historico_status (id_pedido, status, data_hora) VALUES (?, ?, NOW())')
    ->execute([$id, $novo]);
$pdo->commit();

// Sugere avisar o cliente pelo WhatsApp com o novo status (RF08)
$pedido = buscar_pedido($id);
$primeiroNome = explode(' ', trim($pedido['nome']))[0];
$mensagens = [
    'em_preparo'   => "Olá, {$primeiroNome}! Seu pedido nº {$id} da Arte na Cozinha foi aceito e já está em preparo. 🧁",
    'saiu_entrega' => "Olá, {$primeiroNome}! Seu pedido nº {$id} saiu para entrega e logo chega aí. 🛵",
    'entregue'     => "Olá, {$primeiroNome}! Seu pedido nº {$id} foi entregue. Obrigado pela preferência! 💗",
    'cancelado'    => "Olá, {$primeiroNome}. Infelizmente o pedido nº {$id} da Arte na Cozinha precisou ser cancelado. Podemos ajudar?",
];
$texto = $mensagens[$novo] . "\n\nAcompanhe: " . link_acompanhamento($id);

flash(
    $novo === 'cancelado' ? 'info' : 'sucesso',
    'Pedido nº ' . $id . ' atualizado para "' . STATUS_PEDIDO[$novo]['admin'] . '".',
    ['url' => link_whatsapp($pedido['telefone'], $texto), 'texto' => 'Avisar cliente no WhatsApp']
);
redirecionar($voltar);
