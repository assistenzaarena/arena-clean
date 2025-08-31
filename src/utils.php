<?php
/**
 * src/utils.php
 *
 * Funzioni di utilità riutilizzabili nel progetto
 */

/**
 * Genera un codice numerico univoco di 5 cifre (es. "04231") 
 * per una colonna specifica in una tabella specifica.
 *
 * @param PDO    $pdo    Connessione al DB
 * @param string $table  Nome tabella (es. 'tournaments')
 * @param string $column Nome colonna (es. 'tournament_code')
 * @return string Codice univoco di 5 cifre
 */
function generate_unique_code(PDO $pdo, string $table, string $column): string {
    do {
        // genera un numero tra 00000 e 99999 con zero padding
        $code = str_pad((string)random_int(0, 99999), 5, '0', STR_PAD_LEFT);

        // controlla se già esiste
        $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$column} = :c LIMIT 1");
        $stmt->execute([':c' => $code]);
        $exists = $stmt->fetch();
    } while ($exists); // ripeti se duplicato

    return $code;
}
