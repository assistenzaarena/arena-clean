<?php
// Dichiariamo che rispondiamo testo semplice (più facile da diagnosticare)
header('Content-Type: text/plain; charset=utf-8'); // header di risposta
echo "HELLO\n";                                    // stringa di test
echo "time=" . date('H:i:s') . "\n";               // orario per capire che non è cache
