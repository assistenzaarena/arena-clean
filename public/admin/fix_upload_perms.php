<?php
// public/admin/fix_upload_perms.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../src/guards.php'; require_admin();

$PUBLIC_ROOT = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
if ($PUBLIC_ROOT === '' || !is_dir($PUBLIC_ROOT)) {
    $PUBLIC_ROOT = realpath(__DIR__ . '/..'); // /var/www/html/public
}
if ($PUBLIC_ROOT === false) $PUBLIC_ROOT = '/var/www/html';

$dir = $PUBLIC_ROOT . '/uploads/prizes';
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}

$ok = @chmod($dir, 0777);
clearstatcache();

header('Content-Type: text/plain; charset=utf-8');
echo "Cartella: $dir\n";
echo "Permessi: " . substr(sprintf('%o', @fileperms($dir)), -4) . "\n";
echo $ok ? "CHMOD applicato.\n" : "CHMOD fallito (il filesystem potrebbe essere read-only o l'utente non ha permessi).\n";
