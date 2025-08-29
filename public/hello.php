<?php
// Test se Apache+PHP rispondono, senza toccare il DB
header('Content-Type: text/plain; charset=utf-8'); // testo semplice
echo "HELLO from PHP\n";                            // conferma
echo "time=" . date('H:i:s') . "\n";               // orario (per capire se non è cache)
