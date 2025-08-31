<?php
declare(strict_types=1);

/**
 * Genera un codice numerico univoco di 5 cifre (es. "04231")
 * → utile per tornei o eventi se vuoi codici solo numerici.
 */
function generate_unique_code(PDO $pdo, string $table, string $column): string {
    do {
        $code = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        $sql  = "SELECT 1 FROM {$table} WHERE {$column} = :c LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':c' => $code]);
        $exists = (bool) $stmt->fetchColumn();
    } while ($exists);
    return $code;
}

/**
 * Genera un codice alfanumerico univoco (default 8 caratteri).
 * → Usiamo questo per registration_code e movement_code.
 */
function generate_unique_code8(PDO $pdo, string $table, string $column, int $len = 8): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no O/0/I/1
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
