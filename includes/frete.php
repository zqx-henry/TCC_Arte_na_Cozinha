<?php
/**
 * Cálculo do frete por distância
 *
 * 1. Localiza o endereço do cliente no mapa (geocodificação — OpenStreetMap/Nominatim).
 * 2. Calcula a rota de carro da confeitaria até o cliente (OSRM), obtendo distância e tempo.
 * 3. Taxa = distância em km × valor por km (padrão R$ 3,30, média brasileira).
 * 4. Tempo = tempo de preparo + tempo de deslocamento.
 *
 * Se o serviço de rotas não responder, usa a distância em linha reta × 1,35
 * (fator médio de desvio das ruas). Se nenhum serviço de mapa responder
 * (sem internet), usa a taxa e o tempo padrão das Configurações.
 *
 * Os serviços usados são gratuitos e não exigem cadastro. As consultas ficam
 * guardadas na tabela cache_endereco para não repetir a mesma pesquisa.
 */
require_once __DIR__ . '/bootstrap.php';

const URL_GEOCODIFICACAO = 'https://nominatim.openstreetmap.org/search';
const URL_ROTA           = 'https://router.project-osrm.org/route/v1/driving/';
const FATOR_RUAS         = 1.35;  // linha reta → distância real aproximada
const VELOCIDADE_MEDIA   = 25;    // km/h de moto na cidade (usada sem o serviço de rotas)

/** Requisição HTTP com tempo limite curto (o cliente não pode ficar esperando) */
function requisicao_json(string $url): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_USERAGENT      => 'ArteNaCozinha/1.1 (site de delivery proprio - TCC Etec Fernando Prestes)',
        CURLOPT_HTTPHEADER     => ['Accept-Language: pt-BR'],
    ]);
    $resposta = curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resposta === false || $codigo !== 200) {
        return null;
    }
    $json = json_decode($resposta, true);
    return is_array($json) ? $json : null;
}

/** Distância em linha reta entre dois pontos (fórmula de Haversine), em km */
function distancia_linha_reta(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $r = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/** Texto padronizado usado como chave do cache */
function normalizar_endereco(string $texto): string
{
    $texto = mb_strtolower(trim(preg_replace('/\s+/', ' ', $texto)));
    return mb_substr($texto, 0, 200);
}

/**
 * Localiza o endereço no mapa.
 * Retorna ['lat' => ..., 'lng' => ...], false (não encontrado) ou null (serviço fora do ar).
 */
function geocodificar(string $endereco, string $bairro, string $cidade): array|false|null
{
    $chave = normalizar_endereco("$endereco|$bairro|$cidade");
    $st = db()->prepare('SELECT lat, lng, precisao FROM cache_endereco WHERE chave = ?');
    $st->execute([$chave]);
    if ($c = $st->fetch()) {
        return $c['lat'] === null ? false : ['lat' => (float) $c['lat'], 'lng' => (float) $c['lng'], 'precisao' => $c['precisao']];
    }

    // "Sorocaba – SP" -> cidade "Sorocaba", estado "SP"
    [$nomeCidade, $uf] = array_map('trim', preg_split('/\s*[–-]\s*/u', $cidade, 2) + [1 => 'SP']);

    // "Rua das Flores, 120" -> rua "Rua das Flores", número "120"
    $numero = preg_match('/,?\s*(\d+[A-Za-z]?)\s*$/', $endereco, $m) ? $m[1] : '';
    $rua = trim(preg_replace('/,?\s*\d+[A-Za-z]?\s*$/', '', $endereco));

    // Tentativas, da mais precisa para a mais genérica.
    // A última usa o centro do bairro: o frete fica aproximado, mas o cliente consegue pedir.
    $tentativas = [
        ['endereco', ['street' => trim("$numero $rua"), 'city' => $nomeCidade, 'state' => $uf]],
        ['endereco', ['q' => "$rua, $bairro, $nomeCidade, $uf"]],
        ['rua',      ['q' => "$rua, $nomeCidade, $uf"]],
        ['bairro',   ['q' => "$bairro, $nomeCidade, $uf"]],
    ];

    $servicoRespondeu = false;
    foreach ($tentativas as $i => [$precisao, $parametros]) {
        if ($i > 0) {
            usleep(1100000); // política de uso do OpenStreetMap: no máximo 1 consulta por segundo
        }
        $url = URL_GEOCODIFICACAO . '?' . http_build_query($parametros + [
            'format' => 'jsonv2', 'limit' => 1, 'countrycodes' => 'br',
        ]);
        $resultado = requisicao_json($url);
        if ($resultado === null) {
            continue;
        }
        $servicoRespondeu = true;
        if (!empty($resultado[0]['lat'])) {
            $ponto = ['lat' => (float) $resultado[0]['lat'], 'lng' => (float) $resultado[0]['lon'], 'precisao' => $precisao];
            db()->prepare('REPLACE INTO cache_endereco (chave, lat, lng, precisao) VALUES (?, ?, ?, ?)')
                ->execute([$chave, $ponto['lat'], $ponto['lng'], $precisao]);
            return $ponto;
        }
    }

    if ($servicoRespondeu) {
        // Guarda que não foi encontrado (evita repetir a pesquisa a cada tecla)
        db()->prepare('REPLACE INTO cache_endereco (chave, lat, lng) VALUES (?, NULL, NULL)')->execute([$chave]);
        return false;
    }
    return null;
}

/** Rota de carro: ['km' => ..., 'minutos' => ...] ou null */
function calcular_rota(array $origem, array $destino): ?array
{
    $url = URL_ROTA . "{$origem['lng']},{$origem['lat']};{$destino['lng']},{$destino['lat']}?overview=false";
    $json = requisicao_json($url);
    if (($json['code'] ?? '') !== 'Ok' || empty($json['routes'][0])) {
        return null;
    }
    return [
        'km'      => $json['routes'][0]['distance'] / 1000,
        'minutos' => $json['routes'][0]['duration'] / 60,
    ];
}

/** Arredonda para múltiplos de 5 minutos (ex.: 42 -> 40) */
function arredondar_5(float $minutos): int
{
    return (int) (round($minutos / 5) * 5);
}

/**
 * Calcula o frete até o endereço do cliente.
 *
 * Retorna:
 *   ok           bool    se é possível entregar
 *   erro         string  motivo, quando ok = false
 *   distancia_km float   distância percorrida (null no método "padrao")
 *   taxa         float   valor do frete
 *   tempo_min / tempo_max  int  previsão em minutos (preparo + deslocamento)
 *   metodo       string  rota | linha_reta | padrao
 */
function calcular_frete(string $endereco, string $bairro): array
{
    $valorKm     = (float) config_loja('valor_km', '3.30');
    $taxaMinima  = (float) config_loja('taxa_minima', '0');
    $raioMaximo  = (float) config_loja('raio_maximo_km', '25');
    $preparo     = (int) config_loja('tempo_preparo', '30');
    $loja        = ['lat' => (float) config_loja('loja_lat'), 'lng' => (float) config_loja('loja_lng')];

    $padrao = function () {
        preg_match_all('/\d+/', config_loja('tempo_entrega', '40-60'), $m);
        return [
            'ok' => true, 'distancia_km' => null, 'metodo' => 'padrao',
            'taxa' => round((float) config_loja('taxa_entrega', '5'), 2),
            'tempo_min' => (int) ($m[0][0] ?? 40), 'tempo_max' => (int) (end($m[0]) ?: 60),
        ];
    };

    if (!$loja['lat'] || !$loja['lng']) {
        return $padrao(); // endereço da loja ainda não localizado
    }

    $destino = geocodificar($endereco, $bairro, config_loja('cidade', 'Sorocaba – SP'));
    if ($destino === null) {
        return $padrao(); // serviço de mapa fora do ar: não impede a venda
    }
    if ($destino === false) {
        return ['ok' => false, 'erro' => 'Não encontramos esse endereço nem o bairro no mapa. Confira o nome da rua, o número e o bairro.'];
    }

    $rota = calcular_rota($loja, $destino);
    if ($rota) {
        $km = $rota['km'];
        $deslocamento = $rota['minutos'] * 1.2; // margem para trânsito, semáforos e estacionar
        $metodo = 'rota';
    } else {
        $km = distancia_linha_reta($loja['lat'], $loja['lng'], $destino['lat'], $destino['lng']) * FATOR_RUAS;
        $deslocamento = $km / VELOCIDADE_MEDIA * 60;
        $metodo = 'linha_reta';
    }

    $km = round($km, 1);
    if ($raioMaximo > 0 && $km > $raioMaximo) {
        return ['ok' => false, 'erro' => sprintf(
            'Esse endereço fica a %s km da confeitaria. No momento entregamos até %s km.',
            number_format($km, 1, ',', ''), number_format($raioMaximo, 0, ',', '')
        )];
    }

    $tempoMin = max(10, arredondar_5($preparo + $deslocamento));
    return [
        'ok'           => true,
        'distancia_km' => $km,
        'taxa'         => round(max($taxaMinima, $km * $valorKm), 2),
        'tempo_min'    => $tempoMin,
        'tempo_max'    => $tempoMin + 15,
        'metodo'       => $metodo,
        'aproximado'   => ($destino['precisao'] ?? 'endereco') !== 'endereco', // localizado só pela rua ou pelo bairro
    ];
}

/** Localiza o endereço da própria loja (usado ao salvar as Configurações) */
function localizar_loja(string $endereco, string $cidade): ?array
{
    $partes = preg_split('/\s+-\s+/', $endereco, 2); // "R. Francisco Catalano, 440 - Jardim Brasilândia"
    $rua = trim($partes[0]);
    $bairro = trim($partes[1] ?? '');
    $ponto = geocodificar($rua, $bairro, $cidade);
    return is_array($ponto) ? $ponto : null;
}
