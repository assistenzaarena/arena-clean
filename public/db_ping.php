<?php
// === DEBUG TEMPORANEO: CANCELLALO DOPO AVER RISOLTO ===
require_once __DIR__ . '/../src/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "APP_ENV = " . APP_ENV . PHP_EOL;
echo "DB_HOST = " . DB_HOST . PHP_EOL;
echo "DB_PORT = " . DB_PORT . PHP_EOL;
echo "DB_NAME = " . DB_NAME . PHP_EOL;
echo "DB_USER = " . DB_USER . PHP_EOL;

// 1) DNS resolve
$ip = gethostbyname(DB_HOST);
echo "DNS resolve: " . DB_HOST . " -> " . $ip . PHP_EOL;

// 2) Test socket verso host:porta (rete raggiungibile?)
$start = microtime(true);
$fp = @fsockopen(DB_HOST, (int)DB_PORT, $errno, $errstr, 3);
$elapsed = round((microtime(true) - $start), 3);
if ($fp) {
  echo "TCP connect OK in {$elapsed}s" . PHP_EOL;
  fclose($fp);
} else {
  echo "TCP connect FAIL in {$elapsed}s: [{$errno}] {$errstr}" . PHP_EOL;
}

// 3) Test PDO
require_once __DIR__ . '/../src/db.php'; // usa DSN con ;port= e timeout
echo "PDO OK (connessione riuscita)" . PHP_EOL;
