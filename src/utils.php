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
<?php
/* ...altre funzioni... */

/**
 * Genera un codice alfanumerico UPPERCASE di $len (default 8) univoco su tabella/colonna.
 * Esempio: generate_unique_code8($pdo, 'credit_movements','movement_code');
 */
function generate_unique_code8(PDO $pdo, string $table, string $column, int $len = 8): string {
  $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // senza O/0/I/1
  do {
    $code = '';
    for ($i=0; $i<$len; $i++) {
      $code .= $alphabet[random_int(0, strlen($alphabet)-1)];
    }
    $q = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$column} = :c LIMIT 1");
    $q->execute([':c'=>$code]);
    $exists = (bool)$q->fetchColumn();
  } while ($exists);
  return $code;
}
