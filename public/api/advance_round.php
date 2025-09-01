  <div class="card">
    <h3 style="margin:0 0 10px;">Meta torneo</h3>

    <!-- Select round (lista 1..current_round_no) + lock -->
    <form method="post" action="" style="display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <input type="hidden" name="action" value="update_meta">

      <label>Round corrente
        <select name="current_round_no">
          <?php
            $maxR = max(1, (int)$current_round_no);
            for ($r=1; $r<=$maxR; $r++):
          ?>
            <option value="<?php echo $r; ?>" <?php echo ($r===(int)$current_round_no)?'selected':''; ?>>
              <?php echo $r; ?>
            </option>
          <?php endfor; ?>
        </select>
      </label>

      <label>Lock scelte (data/ora)
        <input type="datetime-local" name="lock_at"
               value="<?php echo $torneo['lock_at'] ? htmlspecialchars(date('Y-m-d\TH:i', strtotime($torneo['lock_at']))) : ''; ?>">
      </label>

      <button class="btn" type="submit">Salva meta</button>
    </form>
    <div class="muted" style="margin-top:6px">Il lock scelte blocca globalmente il torneo al momento indicato.</div>

    <!-- Avanza round (forza) -->
    <form method="post" action="/api/advance_round.php" style="margin-top:10px;"
          onsubmit="return confirm('Avanzare al round successivo?\nVerranno caricati automaticamente gli eventi del nuovo round (se disponibili).');">
      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrf); ?>">
      <input type="hidden" name="tournament_id" value="<?php echo (int)$id; ?>">
      <input type="hidden" name="redirect" value="1">
      <button class="btn" type="submit">Avanza round</button>
    </form>
  </div>
