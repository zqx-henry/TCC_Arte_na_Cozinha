<?php
/**
 * API – Status atual do pedido (UC08 Acompanhar pedido)
 * Usada pela tela de acompanhamento para atualizar sozinha.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$id    = (int) ($_GET['pedido'] ?? 0);
$token = (string) ($_GET['t'] ?? '');

if ($id <= 0 || !hash_equals(token_pedido($id), $token)) {
    responder_json(['ok' => false, 'erro' => 'Pedido não encontrado.'], 404);
}

$st = db()->prepare('SELECT status FROM pedido WHERE id_pedido = ?');
$st->execute([$id]);
$status = $st->fetchColumn();

if ($status === false) {
    responder_json(['ok' => false, 'erro' => 'Pedido não encontrado.'], 404);
}

responder_json([
    'ok'     => true,
    'status' => $status,
    'rotulo' => STATUS_PEDIDO[$status]['rotulo'] ?? $status,
]);
