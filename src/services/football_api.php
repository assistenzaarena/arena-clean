<?php
/**
 * src/services/football_api.php
 *
 * SCOPO: Funzioni di servizio per interrogare API-FOOTBALL (v3) e ottenere i fixtures.
 * Dipendenze: API_FOOTBALL_KEY definita in src/config.php
 *
 * N.B. In questo micro-step facciamo solo "lettura" (no scritture DB).
 */

if (!defined('API_FOOTBALL_KEY')) {
    // Evita fatal se config non è stata caricata
    define('API_FOOTBALL_KEY', '');
}

/**
 * Normalizza stagione in formato anno a 4 cifre (API usa '2024', non '2024/2025').
 * Es.: "2024/2025" => "2024"
 */
function fb_normalize_season(string $season): string {
    if (preg_match('/(\d{4})/', $season, $m)) {
        return $m[1]; // prima occorrenza di 4 cifre
    }
    return trim($season);
}

/**
 * Esegue una GET su API-FOOTBALL con header di autenticazione.
 * Ritorna array ['ok'=>bool, 'status'=>int, 'data'=>array|null, 'error'=>string|null]
 */
function fb_get(string $endpoint, array $query): array {
    $base = 'https://v3.football.api-sports.io/';
    $url  = $base . ltrim($endpoint, '/');

    // Aggiungo query string
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'x-apisports-key: ' . API_FOOTBALL_KEY,
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT => 20,
    ]);

    $raw  = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok'=>false, 'status'=>0, 'data'=>null, 'error'=>'cURL error: ' . $err];
    }

    $status = (int)($info['http_code'] ?? 0);
    $json   = json_decode($raw, true);

    if ($status >= 200 && $status < 300 && is_array($json)) {
        return ['ok'=>true, 'status'=>$status, 'data'=>$json, 'error'=>null];
    }

    $msg = is_array($json) && isset($json['errors']) ? json_encode($json['errors']) : 'HTTP '.$status;
    return ['ok'=>false, 'status'=>$status, 'data'=>$json, 'error'=>$msg];
}

/**
 * FETCH fixtures per LEGA (campionati) e GIORNATA NUMERICA.
 * Nota: API-FOOTBALL per i campionati usa il parametro "round" testuale:
 *       "Regular Season - {N}" (pattern più comune).
 * Alcune leghe usano label leggermente diverse: lo aggiusteremo se serve.
 */
function fb_fixtures_matchday(int $league_id, string $season_human, int $matchday, string $roundLabelPattern = 'Regular Season - %d'): array {
    $season = fb_normalize_season($season_human);
    $round  = sprintf($roundLabelPattern, $matchday);

    return fb_get('fixtures', [
        'league' => $league_id,
        'season' => $season,
        'round'  => $round,
    ]);
}

/**
 * FETCH fixtures per COPPE/ROUND LABEL testuale (es. "Round of 16", "Group A - 2").
 */
function fb_fixtures_round_label(int $league_id, string $season_human, string $round_label): array {
    $season = fb_normalize_season($season_human);
    return fb_get('fixtures', [
        'league' => $league_id,
        'season' => $season,
        'round'  => $round_label,
    ]);
}

/**
 * Estrae un elenco minimale di partite dal payload API v3.
 * Ritorna array di righe: [
 *   'fixture_id', 'date', 'home_name', 'away_name', 'home_id', 'away_id'
 * ]
 */
function fb_extract_fixtures_minimal(array $api_json): array {
    $out = [];
    if (!isset($api_json['response']) || !is_array($api_json['response'])) {
        return $out;
    }
    foreach ($api_json['response'] as $fx) {
        $out[] = [
            'fixture_id' => $fx['fixture']['id'] ?? null,
            'date'       => $fx['fixture']['date'] ?? null,
            'home_name'  => $fx['teams']['home']['name'] ?? null,
            'away_name'  => $fx['teams']['away']['name'] ?? null,
            'home_id'    => $fx['teams']['home']['id'] ?? null,
            'away_id'    => $fx['teams']['away']['id'] ?? null,
        ];
    }
    return $out;
}
