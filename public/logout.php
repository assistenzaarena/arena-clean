<?php
// [SCOPO] Disconnettere l'utente (logout).
session_start();
session_unset();      // svuota la sessione
session_destroy();    // distrugge la sessione
header("Location: /login.php"); // torna al login
exit;
