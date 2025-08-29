<?php
// [SCOPO] Testare la connessione al DB tramite PDO.
// Questo file va eliminato appena confermiamo che funziona (sicurezza).

require_once __DIR__ . '/../src/config.php'; // carica le costanti DB_*

header('Content-Type: text/plain; charset=utf-8'); // output testuale

echo "APP_ENV=" . APP_ENV . PHP_EOL;
echo "DB_HOST=" . DB_HOST . PHP_EOL;
echo "DB_PORT=" . DB_PORT . PHP_EOL;
echo "DB_NAME=" . DB_NAME . PHP_EOL;
echo "DB_USER=" . DB_USER . PHP_EOL;

// 1) Test DNS
$ip = gethostbyname(DB_HOST);
echo "RESOLVE=" . $ip . PHP_EOL;

// 2) Test TCP
$start = microtime(true);
$fp = @fsockopen(DB_HOST, (int)DB_PORT, $errno, $errstr, 3);
$ms = (int)((microtime(true) - $start) * 1000);
if ($fp) { echo "TCP=OK in {$ms}ms\n"; fclose($fp); }
else { echo "TCP=FAIL in {$ms}ms [{$errno}] {$errstr}\n"; exit; }

// 3) Test PDO
try {
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 3,
    ]);
    echo "PDO=OK\n";
} catch (Throwable $e) {
    echo "PDO=FAIL: " . $e->getMessage() . "\n";
}
