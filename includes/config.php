<?php
/**
 * Configurações gerais do sistema.
 *
 * Em produção (hospedagem), crie o arquivo includes/config.local.php
 * copiando includes/config.local.example.php e preencha os dados do
 * banco fornecidos pela hospedagem. Esse arquivo não vai para o GitHub.
 */

$config = [
    'db_host'    => '127.0.0.1',
    'db_porta'   => 3306,
    'db_nome'    => 'arte_na_cozinha',
    'db_usuario' => 'root',
    'db_senha'   => '',

    // Chave usada para gerar o link seguro de acompanhamento do pedido.
    // Troque por um texto longo e aleatório em produção.
    'chave_app'  => 'troque-esta-chave-em-producao-arte-na-cozinha-2026',

    // Mostra erros detalhados (desligar em produção).
    'debug'      => true,
];

if (is_file(__DIR__ . '/config.local.php')) {
    $config = array_merge($config, require __DIR__ . '/config.local.php');
}

date_default_timezone_set('America/Sao_Paulo');

if ($config['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

define('APP_CONFIG', $config);
