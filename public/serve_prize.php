<?php
// public/serve_prize.php
if (session_status() === PHP_SESSION_NONE) session_start();

// Per servire i file caricati in /tmp/uploads/prizes in modo sicuro
$filename = basename($_GET['f'] ?? '');
if ($filename === '' || $filename === '.' || $filename === '..') {
    http_response_code(400);
    exit('Bad request');
}

// Percorso assoluto del file
$path = rtrim(sys_get_temp_dir(), '/') . '/uploads/prizes/' . $filename;
if (!is_file($path)) {
    http_response_code(404);
    exit('File non trovato');
}

// MIME type
$mime = mime_content_type($path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
// opzionale: caching leggero (commenta se non lo vuoi)
// header('Cache-Control: public, max-age=86400');

readfile($path);
