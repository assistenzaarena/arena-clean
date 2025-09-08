<?php
// public/index.php — Home (Guest)

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$ROOT = __DIR__; // /var/www/html

// Header guest
require_once $ROOT . '/header_guest.php';
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8">
  <title>ARENA — Sopravvivi. Vinci. Fai la Storia.</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/assets/base.css">
  <style>
    :root{
      --bg:#0e0f11;
      --card:#121318;
      --muted:#9aa3af;
      --text:#f5f6f7;
      --accent:#00c074;      /* verde CTA */
      --accent-2:#00a862;
      --red:#e62329;         /* rosso brand */
      --maxw:1100px;
    }
    body{background:#0c0d10;color:var(--text);}
    .wrap{max-width:var(--maxw); margin:0 auto; padding:0 16px;}

    /* HERO --------------------------------------------------- */
    .hero{
      background: radial-gradient(1200px 500px at 50% -200px, rgba(230,35,41,.18) 0%, transparent 60%), linear-gradient(180deg,#0f1114,#0b0c10);
      border-bottom:1px solid rgba(255,255,255,.08);
    }
    .hero-inner{max-width:var(--maxw); margin:0 auto; padding:64px 16px 40px; text-align:center;}
    .kicker{color:var(--muted); letter-spacing:.08em; font-weight:800; text-transform:uppercase;}
    .title{font-size: clamp(34px, 6vw, 64px); font-weight: 900; line-height:1.05; margin:8px 0 8px;}
    .subtitle{color:#d8dbe0; font-size: clamp(15px, 2.6vw, 18px); margin:0 0 20px;}
    .cta{display:flex; gap:12px; justify-content:center; flex-wrap:wrap; margin-top:10px;}
    .btn{
      display:inline-flex; align-items:center; justify-content:center;
      height:46px; padding:0 18px; border-radius:9999px; font-weight:900; text-decoration:none;
    }
    .btn--primary{ background:var(--accent); color:#fff; border:1px solid var(--accent); box-shadow:0 6px 22px rgba(0,192,116,.28); }
    .btn--primary:hover{ background:var(--accent-2); border-color:var(--accent-2); transform:translateY(-1px); }
    .btn--ghost{ background:transparent; color:#fff; border:1px solid rgba(255,255,255,.32); }
    .btn--ghost:hover{ border-color:#fff; transform:translateY(-1px); }

    /* HERO IMAGE --------------------------------------------- */
    .hero-img img{
      max-width:900px;        /* limite dimensione desktop */
      width:100%;             /* responsive */
      height:auto;
      border-radius:16px;
      box-shadow:0 10px 28px rgba(0,0,0,.35);
      margin:40px auto;
      display:block;
    }

    /* VALUE PROPS ------------------------------------------- */
    .section{padding:36px 0;}
    .grid-3{display:grid; grid-template-columns:repeat(3,1fr); gap:16px;}
    @media (max-width: 900px){ .grid-3{grid-template-columns:1fr;} }
    .card{
      background:linear-gradient(180deg,#14161b,#101216);
      border:1px solid rgba(255,255,255,.08);
      border-radius:16px; padding:18px;
      box-shadow:0 10px 24px rgba(0,0,0,.28), inset 0 0 0 1px rgba(255,255,255,.03);
    }
    .card h3{margin:6px 0 6px; font-size:20px;}
    .card p{color:#cfd3d8; margin:0;}

    /* COME FUNZIONA ----------------------------------------- */
    .how{background:linear-gradient(180deg,#0c0e12,#0b0c10); border-top:1px solid rgba(255,255,255,.06); border-bottom:1px solid rgba(255,255,255,.06);}
    .steps{display:grid; grid-template-columns:repeat(3,1fr); gap:16px;}
    @media (max-width: 900px){ .steps{grid-template-columns:1fr;} }
    .step{background:var(--card); border:1px solid rgba(255,255,255,.08); border-radius:16px; padding:18px;}
    .step .n{display:inline-flex; width:28px; height:28px; align-items:center; justify-content:center; border-radius:9999px;
      background:#1c2128; border:1px solid #2a3038; color:#dbe2ea; font-weight:900; margin-bottom:8px;}
    .step h4{margin:0 0 4px; font-size:18px;}
    .step p{color:#cfd3d8; margin:0;}

    /* PERCHÈ ARENA ------------------------------------------ */
    .why{display:grid; grid-template-columns:1.2fr .8fr; gap:24px; align-items:center;}
    @media (max-width: 900px){ .why{grid-template-columns:1fr; text-align:center;} }
    .why h2{font-size: clamp(24px,4vw,34px); margin:0 0 10px;}
    .why ul{margin:0; padding-left:18px; color:#cfd3d8;}
    .why .panel{background:var(--card); border:1px solid rgba(255,255,255,.08); border-radius:16px; padding:18px;}

    /* BANNER ------------------------------------------------ */
    .banner{
      margin:36px 0 14px; padding:22px 18px; text-align:center;
      background: radial-gradient(900px 280px at 50% -120px, rgba(0,192,116,.22) 0%, transparent 60%), linear-gradient(180deg,#12161b,#0f1217);
      border:1px solid rgba(255,255,255,.10); border-radius:16px;
    }
    .banner h3{margin:0 0 10px; font-size: clamp(22px,3.6vw,28px);}
    .muted{color:#9aa3af; font-size:13px; margin-top:8px;}
  </style>
</head>
<body>

  <!-- HERO -->
  <section class="hero">
    <div class="hero-inner wrap">
      <div class="kicker">modalità survivor</div>
      <h1 class="title">Sopravvivi. Vinci. Fai la Storia.</h1>
      <p class="subtitle">Scegli la squadra giusta, turno dopo turno. L’Arena è sfida, adrenalina e strategia.</p>
      <div class="cta">
        <a href="/registrazione.php" class="btn btn--primary">Inizia ora</a>
        <a href="/il_gioco.php" class="btn btn--ghost">Scopri come funziona</a>
      </div>
    </div>
  </section>

  <!-- HERO IMAGE -->
  <section class="hero-img">
    <img src="/assets/home/immagine_1.png" alt="Survive. Win. Make History.">
  </section>

  <main class="wrap">
    <!-- VALUE PROPS -->
    <section class="section">
      <div class="grid-3">
        <div class="card">
          <h3>Veloce da capire</h3>
          <p>Una squadra per turno, resti in gioco se soddisfi il requisito. Il resto è strategia.</p>
        </div>
        <div class="card">
          <h3>Trasparente</h3>
          <p>Regole chiare, risultati verificabili, strumenti anti-abuso. Zero sorprese.</p>
        </div>
        <div class="card">
          <h3>Fair-play</h3>
          <p>Ambiente sicuro: no bot, no multi-account, no collusioni. Vince il merito.</p>
        </div>
      </div>
    </section>

    <!-- COME FUNZIONA -->
    <section class="section how">
      <div class="wrap" style="max-width:var(--maxw); padding:0;">
        <div class="steps">
          <div class="step">
            <span class="n">1</span>
            <h4>Iscriviti</h4>
            <p>Crea l’account e unisciti ai tornei attivi.</p>
          </div>
          <div class="step">
            <span class="n">2</span>
            <h4>Scegli la squadra</h4>
            <p>Ogni turno prendi la decisione giusta. Non puoi riutilizzare la stessa.</p>
          </div>
          <div class="step">
            <span class="n">3</span>
            <h4>Resta in piedi</h4>
            <p>Supera i round, elimina gli avversari, conquista l’Arena.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- PERCHÈ ARENA -->
    <section class="section">
      <div class="why">
        <div>
          <h2>Perché scegliere ARENA</h2>
          <ul>
            <li>Adrenalina a ogni turno, senza complicazioni.</li>
            <li>Design moderno, mobile-first, prestazioni rapide.</li>
            <li>Regole solide e anti-abuso: vincono i migliori.</li>
            <li>Community competitiva e tornei sempre nuovi.</li>
          </ul>
        </div>
        <div class="panel">
          <h3 style="margin:0 0 8px;">Pronto a entrare?</h3>
          <p class="muted" style="margin:0 0 12px;">Inizia gratis, prova la modalità e fai la tua prima scelta.</p>
          <div class="cta">
            <a href="/registrazione.php" class="btn btn--primary">Registrati</a>
            <a href="/login.php" class="btn btn--ghost">Hai già un account?</a>
          </div>
        </div>
      </div>
    </section>

    <!-- BANNER FINALE -->
    <section class="banner">
      <h3>È il tuo momento. Entra nell’Arena.</h3>
      <div class="cta">
        <a href="/registrazione.php" class="btn btn--primary">Entra ora</a>
      </div>
      <div class="muted">Contano solo i sopravvissuti.</div>
    </section>
  </main>

<?php
// Footer
if (file_exists($ROOT . '/footer_public.php')) {
  require_once $ROOT . '/footer_public.php';
} elseif (file_exists($ROOT . '/footer.php')) {
  require_once $ROOT . '/footer.php';
}
?>
</body>
</html>
