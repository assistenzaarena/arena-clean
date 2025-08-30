<?php
// [SCOPO] Generare un hash sicuro a partire da una password test, così lo incolli nel DB.
// [USO]   Vai su /tools/make_hash.php?pwd=LaTuaPasswordFort3!
// [NOTE]  SOLO per sviluppo. Dopo aver generato l’hash, ELIMINA questo file.

// [RIGA] Leggiamo dal query string la password passata come ?pwd=...
$pwd = $_GET['pwd'] ?? '';                // Se non c'è, stringa vuota

// [RIGA] Se non hai passato nulla, mostriamo istruzioni chiare
if ($pwd === '') {
  header('Content-Type: text/plain; charset=utf-8'); // Rispondiamo in testo semplice
  echo "Usa così:\n";
  echo "/tools/make_hash.php?pwd=LaTuaPasswordFort3!\n";
  echo "(Poi copia l'hash che esce e incollalo nel DB nella colonna password_hash.)\n";
  exit;                                   // Fermiamo l'esecuzione
}

// [RIGA] Generiamo l'hash sicuro con l'algoritmo consigliato da PHP
$hash = password_hash($pwd, PASSWORD_DEFAULT); // Esempio: $2y$10$...

// [RIGA] Stampiamo solo l'hash (in testo), così puoi copiarlo facile
header('Content-Type: text/plain; charset=utf-8'); // Output leggibile
echo $hash;                                       // Questo è ciò che incollerai nel DB
