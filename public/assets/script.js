// Questo script aggiorna il valore "Crediti" in header senza ricaricare la pagina
// Motivazione: UX migliore e base per future dinamiche real-time

// Funzione che chiama l'endpoint PHP e sostituisce il contenuto nel DOM
function refreshCrediti() {
  // Seleziona lo span che mostra i crediti (presente solo se header lo include)
  const el = document.getElementById('creditiVal'); // target del valore
  if (!el) return; // Se non esiste (non loggato), non facciamo nulla

  // Chiamiamo la route che restituisce il numero crediti corrente
  fetch('/src/routes/get_crediti.php', { credentials: 'include' }) // credentials include: manda cookie di sessione
    .then(res => {
      // Controllo semplice dell'esito HTTP
      if (!res.ok) throw new Error('HTTP ' + res.status); // Se non ok, errore
      return res.json(); // Ci aspettiamo JSON del tipo { ok: true, crediti: 123 }
    })
    .then(data => {
      // Se la risposta è valida e contiene "crediti", aggiorniamo
      if (data && data.ok === true && typeof data.crediti === 'number') {
        el.textContent = String(data.crediti); // Aggiorniamo testo in modo sicuro
      }
    })
    .catch(() => {
      // In caso di errore non mostriamo alert invadenti in prod; silenzioso
      // Potremmo loggare in console in dev se APP_ENV lo consente (qui teniamo pulito)
    });
}

// All'avvio pagina proviamo a leggere i crediti una volta
refreshCrediti(); // Primo update

// Poi aggiorniamo ogni 5 secondi (valore prudente per non spammare)
setInterval(refreshCrediti, 5000); // Polling semplice, in futuro potremo usare WebSocket/EventSource
