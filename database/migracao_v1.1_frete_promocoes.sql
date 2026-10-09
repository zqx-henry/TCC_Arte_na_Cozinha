-- =====================================================================
--  ARTE NA COZINHA — Migração v1.1
--  Frete por distância + promoções com tempo para acabar
--
--  Use este arquivo SOMENTE em um banco que já tem a versão 1.0
--  (ex.: o banco já importado no XAMPP ou na hospedagem).
--  Instalação nova: importe apenas database/arte_na_cozinha.sql.
-- =====================================================================

-- Na hospedagem, apague a linha abaixo (o banco já está selecionado no phpMyAdmin)
USE arte_na_cozinha;

-- Dados do frete calculado de cada pedido (tabela de apoio, ligada 1:1 ao PEDIDO)
CREATE TABLE IF NOT EXISTS entrega (
  id_pedido     INT           NOT NULL,
  distancia_km  DECIMAL(6,2)  NULL,          -- NULL quando foi usada a taxa padrão
  tempo_min     SMALLINT      NOT NULL,      -- previsão (preparo + deslocamento)
  tempo_max     SMALLINT      NOT NULL,
  metodo        VARCHAR(12)   NOT NULL,      -- rota | linha_reta | padrao
  PRIMARY KEY (id_pedido),
  CONSTRAINT fk_entrega_pedido FOREIGN KEY (id_pedido)
    REFERENCES pedido (id_pedido) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Prazo de cada promoção (tabela de apoio, ligada 1:1 ao PRODUTO)
CREATE TABLE IF NOT EXISTS promocao (
  id_produto   INT          NOT NULL,
  data_inicio  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_fim     DATETIME     NOT NULL,
  modo         VARCHAR(12)  NOT NULL DEFAULT 'automatico',  -- automatico | manual
  PRIMARY KEY (id_produto),
  KEY idx_promocao_fim (data_fim),
  CONSTRAINT fk_promocao_produto FOREIGN KEY (id_produto)
    REFERENCES produto (id_produto) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Endereços já localizados no mapa (evita repetir consultas ao OpenStreetMap)
CREATE TABLE IF NOT EXISTS cache_endereco (
  chave      VARCHAR(255)   NOT NULL,
  lat        DECIMAL(10,7)  NULL,             -- NULL = endereço não encontrado
  lng        DECIMAL(10,7)  NULL,
  precisao   VARCHAR(10)    NULL,             -- endereco | rua | bairro
  criado_em  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Novas configurações da loja
INSERT INTO configuracao (chave, valor) VALUES
('endereco_loja',       'R. Francisco Catalano, 440 - Jardim Brasilândia'),
('loja_lat',            '-23.4766020'),
('loja_lng',            '-47.4638526'),
('valor_km',            '3.30'),
('taxa_minima',         '0.00'),
('raio_maximo_km',      '25'),
('tempo_preparo',       '30'),
('promo_duracao_horas', '168')
ON DUPLICATE KEY UPDATE valor = valor;

-- Promoções que já existiam ganham o prazo automático (7 dias a partir de agora)
INSERT IGNORE INTO promocao (id_produto, data_inicio, data_fim, modo)
SELECT id_produto, NOW(), NOW() + INTERVAL 7 DAY, 'automatico'
  FROM produto
 WHERE preco_promocional IS NOT NULL;
