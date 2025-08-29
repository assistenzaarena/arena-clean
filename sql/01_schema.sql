-- Schema placeholder. li useremo tra poco.
CREATE TABLE IF NOT EXISTS healthcheck (
  id INT AUTO_INCREMENT PRIMARY KEY,
  note VARCHAR(50) NOT NULL DEFAULT 'ok',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
