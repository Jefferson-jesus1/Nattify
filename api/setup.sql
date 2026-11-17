-- Naffity — schema do banco (execute isso uma vez no phpMyAdmin da Hostinger)
-- Todas as tabelas usam utf8mb4 para suportar emojis e acentuação corretamente.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  id TINYINT PRIMARY KEY DEFAULT 1,
  site_name VARCHAR(120) NOT NULL DEFAULT 'Naffity',
  tagline VARCHAR(200) NOT NULL DEFAULT 'doces artesanais',
  primary_color VARCHAR(20) NOT NULL DEFAULT '#b8996b',
  bg_color VARCHAR(20) NOT NULL DEFAULT '#f7f1e8',
  whatsapp VARCHAR(30) NOT NULL DEFAULT '',
  instagram VARCHAR(60) NOT NULL DEFAULT '_naffity',
  delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 6.00,
  min_order DECIMAL(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_users (
  id TINYINT PRIMARY KEY DEFAULT 1,
  username VARCHAR(60) NOT NULL,
  password_hash VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_sessions (
  token CHAR(64) PRIMARY KEY,
  created_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
  id CHAR(32) PRIMARY KEY,
  name VARCHAR(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
  id CHAR(32) PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description TEXT,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  stock INT NOT NULL DEFAULT 0,
  category_id CHAR(32) NULL,
  image_url VARCHAR(255) NOT NULL DEFAULT '',
  active TINYINT(1) NOT NULL DEFAULT 1,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  rating DECIMAL(2,1) NOT NULL DEFAULT 5.0,
  rating_count INT NOT NULL DEFAULT 0,
  tags VARCHAR(255) NOT NULL DEFAULT '',
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
  id CHAR(32) PRIMARY KEY,
  code VARCHAR(20) NOT NULL,
  customer_name VARCHAR(150) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  address TEXT,
  items JSON NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  delivery_fee DECIMAL(10,2) NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  payment_method VARCHAR(20) NOT NULL,
  payment_status VARCHAR(30) NOT NULL,
  status VARCHAR(30) NOT NULL,
  notes TEXT,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Usuário admin padrão: usuário "admin", senha "doce123" (troque depois de logar!)
INSERT IGNORE INTO admin_users (id, username, password_hash) VALUES
  (1, 'admin', '$2y$10$qr4hWJFkFTBPJnNdwr6B.u8cnpGhOyVD.2AZaWYNlYIcAINM8dlvq');

INSERT IGNORE INTO settings (id) VALUES (1);

-- Categorias de exemplo
INSERT IGNORE INTO categories (id, name) VALUES
  ('c1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1', 'Pudins'),
  ('c2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2', 'Taças & Copinhos'),
  ('c3a3a3a3a3a3a3a3a3a3a3a3a3a3a3a3', 'Especiais');

-- Produtos de exemplo (baseados nos doces reais: pudim, mousse, taças, paçoca)
INSERT IGNORE INTO products (id, name, description, price, stock, category_id, image_url, active, featured, rating, rating_count, tags) VALUES
  ('p1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1', 'Pudim de Leite Individual', 'Pudim de leite condensado cremoso, calda de caramelo na medida certa.', 9.50, 18, 'c1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1', '', 1, 1, 4.9, 42, 'mais pedido'),
  ('p2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2', 'Taça de Chocolate com Kiwi', 'Camadas de brigadeiro cremoso, kiwi fresco e chantilly.', 12.90, 10, 'c2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2', '', 1, 1, 4.8, 27, ''),
  ('p3a3a3a3a3a3a3a3a3a3a3a3a3a3a3a3', 'Copo da Felicidade de Maracujá', 'Mousse de maracujá com pedaços de fruta e calda especial.', 13.90, 8, 'c2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2', '', 1, 1, 5.0, 19, 'novidade'),
  ('p4a4a4a4a4a4a4a4a4a4a4a4a4a4a4a4', 'Pudim de Chia com Frutas Vermelhas', 'Pudim de chia cremoso com morango e geleia de frutas vermelhas.', 11.50, 12, 'c3a3a3a3a3a3a3a3a3a3a3a3a3a3a3a3', '', 1, 0, 4.7, 15, 'fit'),
  ('p5a5a5a5a5a5a5a5a5a5a5a5a5a5a5a5', 'Copinho de Paçoca com Amendoim', 'Creme de paçoca coberto com amendoim crocante.', 10.50, 14, 'c3a3a3a3a3a3a3a3a3a3a3a3a3a3a3a3', '', 1, 0, 4.6, 11, ''),
  ('p6a6a6a6a6a6a6a6a6a6a6a6a6a6a6a6', 'Taça Mesclada Chocolate & Maracujá', 'Mousse de maracujá mesclado com ganache de chocolate meio amargo.', 13.50, 9, 'c2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2', '', 1, 0, 4.9, 8, ''),
  ('p7a7a7a7a7a7a7a7a7a7a7a7a7a7a7a7', 'Copo de Morango com Chantilly', 'Camadas de bolo, creme e morangos frescos, finalizado com chantilly.', 12.00, 0, 'c2a2a2a2a2a2a2a2a2a2a2a2a2a2a2a2', '', 1, 0, 4.8, 22, '');
