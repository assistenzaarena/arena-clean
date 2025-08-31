<?php
/**
 * config/competitions.php
 *
 * [SCOPO] Mappa statica delle competizioni da mostrare nel menu a tendina
 *         durante la creazione di un torneo. NIENTE input manuale: l'admin sceglie da qui.
 *
 * [CAMPI]
 * - key            : chiave interna "umana" (slug) con cui identifichiamo la competizione
 * - name           : nome visibile in UI
 * - league_id      : ID competizione su API-FOOTBALL (intero)
 * - country        : codice/descrizione paese o confederazione (solo info)
 * - round_type     : 'matchday' per campionati a giornata numerica | 'round_label' per coppe/tornei con round testuali
 * - expected_matches_per_matchday : partite attese in una giornata (solo per 'matchday', utile allo Step 2)
 * - default_season : stringa stagione precompilata nel form (editabile dall'admin)
 *
 * [USO]
 * $competitions = require __DIR__ . '/competitions.php';
 * $one = $competitions['serie_a']; // esempio
 */

return [
    'serie_a' => [
        'name'  => 'Serie A (Italia)',
        'league_id' => 135,
        'country'   => 'ITA',
        'round_type'=> 'matchday',             // giornata numerica
        'expected_matches_per_matchday' => 10, // 10 match
        'default_season' => '2025/2026',
    ],
    'serie_b' => [
        'name'  => 'Serie B (Italia)',
        'league_id' => 136,
        'country'   => 'ITA',
        'round_type'=> 'matchday',
        'expected_matches_per_matchday' => 10,
        'default_season' => '2025/2026',
    ],
    'premier_league' => [
        'name'  => 'Premier League (Inghilterra)',
        'league_id' => 39,
        'country'   => 'ENG',
        'round_type'=> 'matchday',
        'expected_matches_per_matchday' => 10,
        'default_season' => '2025/2026',
    ],
    'bundesliga' => [
        'name'  => 'Bundesliga (Germania)',
        'league_id' => 78,
        'country'   => 'GER',
        'round_type'=> 'matchday',
        'expected_matches_per_matchday' => 9,
        'default_season' => '2025/2026',
    ],
    'la_liga' => [
        'name'  => 'La Liga (Spagna)',
        'league_id' => 140,
        'country'   => 'ESP',
        'round_type'=> 'matchday',
        'expected_matches_per_matchday' => 10,
        'default_season' => '2025/2026',
    ],
    'ligue_1' => [
        'name'  => 'Ligue 1 (Francia)',
        'league_id' => 61,
        'country'   => 'FRA',
        'round_type'=> 'matchday',
        'expected_matches_per_matchday' => 9,
        'default_season' => '2025/2026',
    ],

    // Coppe: round_label (il concetto è “turno/round”, non “giornata” numerica)
    'ucl' => [
        'name'  => 'UEFA Champions League',
        'league_id' => 2,
        'country'   => 'UEFA',
        'round_type'=> 'round_label',          // Step 2: popoleremo le round label da API
        'expected_matches_per_matchday' => null,
        'default_season' => '2025/2026',
    ],
    'uel' => [
        'name'  => 'UEFA Europa League',
        'league_id' => 3,
        'country'   => 'UEFA',
        'round_type'=> 'round_label',
        'expected_matches_per_matchday' => null,
        'default_season' => '2025/2026',
    ],
    'uecl' => [
        'name'  => 'UEFA Europa Conference League',
        'league_id' => 848,
        'country'   => 'UEFA',
        'round_type'=> 'round_label',
        'expected_matches_per_matchday' => null,
        'default_season' => '2025/2026',
    ],
    'world_cup' => [
        'name'  => 'FIFA World Cup (Mondiali)',
        'league_id' => 1,
        'country'   => 'FIFA',
        'round_type'=> 'round_label',          // qualificazioni/fase finale: round testuali
        'expected_matches_per_matchday' => null,
        'default_season' => '2022',
    ],
];
