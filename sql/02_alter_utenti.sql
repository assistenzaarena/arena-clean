-- Aggiungo campi necessari se non già presenti
ALTER TABLE utenti
  ADD COLUMN email VARCHAR(190) NULL AFTER password_hash,
  ADD COLUMN phone VARCHAR(32)  NULL AFTER email,
  ADD COLUMN verification_token VARCHAR(64) NULL AFTER phone,
  ADD COLUMN verified_at DATETIME NULL AFTER verification_token;

-- Vincoli di unicità (username, email, telefono)
ALTER TABLE utenti
  ADD UNIQUE KEY uq_utenti_username (username),
  ADD UNIQUE KEY uq_utenti_email (email),
  ADD UNIQUE KEY uq_utenti_phone (phone);
