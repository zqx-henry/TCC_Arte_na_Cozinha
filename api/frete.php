<?php
/**
 * API – Calcular frete e tempo de entrega pela distância
 * Recebe { csrf, endereco, bairro } e devolve distância, taxa e previsão.
 * O resultado fica guardado na sessão por 30 minutos e é reaproveitado
 * em api/pedido.php, para o cliente pagar exatamente o valor que viu.
 */
require_once __DIR__ . '/../includes/frete.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responder_json(['ok' => false, 'erro' => 'Método não permitido.'], 405);
}

$dados = json_decode(file_get_contents('php://input'), true) ?: [];
if (!csrf_valido($dados['csrf'] ?? null)) {
    responder_json(['ok' => false, 'erro' => 'Sua sessão expirou. Atualize a página.'], 403);
}

$endereco = mb_substr(trim(strip_tags((string) ($dados['endereco'] ?? ''))), 0, 150);
$bairro   = mb_substr(trim(strip_tags((string) ($dados['bairro'] ?? ''))), 0, 60);
if (mb_strlen($endereco) < 5 || mb_strlen($bairro) < 2) {
    responder_json(['ok' => false, 'erro' => 'Informe o endereço e o bairro para calcular o frete.'], 422);
}

// Limite de 20 cálculos a cada 10 minutos por visitante (protege os serviços de mapa gratuitos)
$janela = array_filter($_SESSION['frete_consultas'] ?? [], fn($t) => $t > time() - 600);
if (count($janela) >= 20) {
    responder_json(['ok' => false, 'erro' => 'Muitas consultas de frete seguidas. Aguarde alguns minutos.'], 429);
}
$janela[] = time();
$_SESSION['frete_consultas'] = array_values($janela);

session_write_close(); // libera a sessão enquanto consulta o mapa (pode levar alguns segundos)
$frete = calcular_frete($endereco, $bairro);
session_start();

if ($frete['ok']) {
    $_SESSION['frete'][normalizar_endereco("$endereco|$bairro")] = $frete + ['em' => time()];
}

responder_json($frete + ['valor_km' => (float) config_loja('valor_km', '3.30')], $frete['ok'] ? 200 : 422);
