-- Naffity — migração 002: sistema financeiro (clientes + fluxo de caixa)
-- Execute isso no phpMyAdmin (aba "Importar" ou "SQL"), depois do setup.sql inicial.
-- Não afeta as tabelas já existentes.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS ledger_entries (
  id CHAR(32) PRIMARY KEY,
  entry_date DATE NOT NULL,
  type ENUM('entrada','saida') NOT NULL,
  category VARCHAR(60) NOT NULL DEFAULT '',
  customer_name VARCHAR(150) NULL,
  amount DECIMAL(10,2) NOT NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  INDEX idx_entry_date (entry_date),
  INDEX idx_type (type),
  INDEX idx_customer (customer_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
