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

/**
 * FETCH singolo fixture per ID (API-FOOTBALL: fixtures?id=...)
 */
function fb_fixture_by_id(int $fixture_id): array {
    return fb_get('fixtures', ['id' => $fixture_id]);
}

/**
 * Estrae un singolo fixture con i campi minimi (stesso formato di fb_extract_fixtures_minimal)
 * Ritorna: null|array ['fixture_id','date','home_name','away_name','home_id','away_id']
 */
function fb_extract_one_fixture_minimal(array $api_json): ?array {
    if (!isset($api_json['response'][0])) return null;
    $fx = $api_json['response'][0];
    return [
        'fixture_id' => $fx['fixture']['id'] ?? null,
        'date'       => $fx['fixture']['date'] ?? null,
        'home_name'  => $fx['teams']['home']['name'] ?? null,
        'away_name'  => $fx['teams']['away']['name'] ?? null,
        'home_id'    => $fx['teams']['home']['id'] ?? null,
        'away_id'    => $fx['teams']['away']['id'] ?? null,
    ];
}

/* =========================================================================
 *  AGGIUNTE MINIME per preload round N+1 (richieste)
 *  - NON rompono nulla: funzioni nuove, gli alias esistenti restano invariati
 * ========================================================================= */

/** Converte ISO8601 → 'Y-m-d H:i:s' (UTC→locale server via strtotime) */
function fb_iso_to_mysql_datetime(?string $iso): ?string {
    if (!$iso) return null;
    $ts = strtotime($iso);
    if ($ts === false) return null;
    return date('Y-m-d H:i:s', $ts);
}

/**
 * Variante "compatta" per ottenere direttamente l'elenco pronto
 * per insert in `tournament_events` (campi minimi).
 *
 * Ritorna:
 *  ['ok'=>true, 'status'=>int, 'fixtures'=>[
 *      ['fixture_id'=>int, 'home'=>string, 'away'=>string, 'kickoff_at'=>'Y-m-d H:i:s']
 *  ]]
 *  (in errore: ['ok'=>false, 'status'=>int, 'error'=>string])
 */
function fb_fixtures_matchday_compact(int $league_id, string $season_human, int $matchday, string $roundLabelPattern = 'Regular Season - %d'): array {
    $resp = fb_fixtures_matchday($league_id, $season_human, $matchday, $roundLabelPattern);
    if (!$resp['ok']) {
        return ['ok'=>false, 'status'=>$resp['status'], 'error'=>$resp['error'] ?? 'Errore API'];
    }
    $fixtures = [];
    $json = $resp['data'];
    if (!isset($json['response']) || !is_array($json['response'])) {
        return ['ok'=>true, 'status'=>$resp['status'], 'fixtures'=>[]];
    }
    foreach ($json['response'] as $fx) {
        $fixtures[] = [
            'fixture_id' => $fx['fixture']['id'] ?? null,
            'home'       => $fx['teams']['home']['name'] ?? null,
            'away'       => $fx['teams']['away']['name'] ?? null,
            'kickoff_at' => fb_iso_to_mysql_datetime($fx['fixture']['date'] ?? null),
        ];
    }
    return ['ok'=>true, 'status'=>$resp['status'], 'fixtures'=>$fixtures];
}

/**
 * Alias comodo se vuoi recuperare per "label round" (coppe).
 * Stesso formato della compact precedente.
 */
function fb_fixtures_round_label_compact(int $league_id, string $season_human, string $round_label): array {
    $resp = fb_fixtures_round_label($league_id, $season_human, $round_label);
    if (!$resp['ok']) {
        return ['ok'=>false, 'status'=>$resp['status'], 'error'=>$resp['error'] ?? 'Errore API'];
    }
    $fixtures = [];
    $json = $resp['data'];
    if (!isset($json['response']) || !is_array($json['response'])) {
        return ['ok'=>true, 'status'=>$resp['status'], 'fixtures'=>[]];
    }
    foreach ($json['response'] as $fx) {
        $fixtures[] = [
            'fixture_id' => $fx['fixture']['id'] ?? null,
            'home'       => $fx['teams']['home']['name'] ?? null,
            'away'       => $fx['teams']['away']['name'] ?? null,
            'kickoff_at' => fb_iso_to_mysql_datetime($fx['fixture']['date'] ?? null),
        ];
    }
    return ['ok'=>true, 'status'=>$resp['status'], 'fixtures'=>$fixtures];
}
