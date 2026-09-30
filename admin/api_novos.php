<?php
/**
 * API do painel – verifica se chegaram pedidos novos (atualização em tempo real).
 */
require_once __DIR__ . '/../includes/auth.php';

if (!admin_logado()) {
    responder_json(['ok' => false], 401);
}

$dados = db()->query(
    "SELECT COALESCE(MAX(id_pedido), 0) AS ultimo, SUM(status = 'recebido') AS novos FROM pedido"
)->fetch();

responder_json([
    'ok'     => true,
    'ultimo' => (int) $dados['ultimo'],
    'novos'  => (int) $dados['novos'],
]);
