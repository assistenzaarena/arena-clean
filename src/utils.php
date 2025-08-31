<?php
/**
 * src/utils.php
 * Funzioni di utilità riutilizzabili nel progetto.
 *
 * Nota: lasciare il file SENZA tag di chiusura "?>"
 * per evitare spazi/output indesiderati.
 */

declare(strict_types=1);

/**
 * Genera un codice numerico univoco di 5 cifre (es. "04231")
 * per una colonna specifica in una tabella specifica.
 *
 * @param PDO    $pdo    Connessione al DB
 * @param string $table  Nome tabella (es. 'tournaments')
 * @param string $column Nome colonna (es. 'tournament_code')
 * @return string Codice univoco di 5 cifre
 */
function generate_unique_code(PDO $pdo, string $table, string $column): string
{
    do {
        // genera un numero tra 00000 e 99999 con zero padding
        $code = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);

        // controlla se già esiste
        $sql  = "SELECT 1 FROM {$table} WHERE {$column} = :c LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':c' => $code]);
        $exists = (bool) $stmt->fetchColumn();
    } while ($exists); // ripeti se duplicato

    return $code;
}

/**
 * Genera un codice alfanumerico UPPERCASE univoco di lunghezza $len (default 8)
 * per una colonna specifica in una tabella specifica.
 * Esempio d’uso: generate_unique_code8($pdo, 'credit_movements', 'movement_code');
 *
 * @param PDO    $pdo
 * @param string $table
 * @param string $column
 * @param int    $len    Lunghezza codice (default 8)
 * @return string Codice alfanumerico univoco
 */
function generate_unique_code8(PDO $pdo, string $table, string $column, int $len = 8): string
{
    // alfabeto senza O/0 e I/1 per evitare ambiguità
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    do {
        $code = '';
        for ($i = 0; $i < $len; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        $sql  = "SELECT 1 FROM {$table} WHERE {$column} = :c LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':c' => $code]);
        $exists = (bool) $stmt->fetchColumn();
    } while ($exists);

    return $code;
}
    return $code;
}
