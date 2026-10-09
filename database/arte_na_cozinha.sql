-- =====================================================================
--  ARTE NA COZINHA — Site de delivery próprio para confeitaria
--  TCC · Curso Técnico em Desenvolvimento de Sistemas · Etec Fernando Prestes
--
--  Script de criação do banco de dados (MySQL 5.7+ / MariaDB 10.3+)
--  Baseado no DER (Figura 3) e no dicionário de dados (Quadros 10 a 14).
--
--  Entidades do DER:  CLIENTE, PEDIDO, ITEM_PEDIDO, PRODUTO, ADMINISTRADOR
--  Tabelas de apoio:  HISTORICO_STATUS (horários da linha do tempo do
--                     acompanhamento — RF03), CONFIGURACAO (dados da loja
--                     editáveis no painel), ENTREGA (frete por distância de
--                     cada pedido), PROMOCAO (prazo para o fim do desconto)
--                     CACHE_ENDERECO (endereços já localizados no mapa)
--                     e CATEGORIA (categorias do cardápio editáveis).
--
--  Versão 1.1 — frete por distância e promoções com tempo para acabar.
--  Versão 1.2 — categorias editáveis e conta do cliente (nome + WhatsApp).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS arte_na_cozinha
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE arte_na_cozinha;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS categoria;
DROP TABLE IF EXISTS cache_endereco;
DROP TABLE IF EXISTS promocao;
DROP TABLE IF EXISTS entrega;
DROP TABLE IF EXISTS historico_status;
DROP TABLE IF EXISTS item_pedido;
DROP TABLE IF EXISTS pedido;
DROP TABLE IF EXISTS produto;
DROP TABLE IF EXISTS cliente;
DROP TABLE IF EXISTS administrador;
DROP TABLE IF EXISTS configuracao;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Quadro 14 – ADMINISTRADOR
-- ---------------------------------------------------------------------
CREATE TABLE administrador (
  id_admin    INT          NOT NULL AUTO_INCREMENT,
  nome        VARCHAR(100) NOT NULL,
  email       VARCHAR(120) NOT NULL,
  senha_hash  VARCHAR(255) NOT NULL,           -- password_hash() (bcrypt)
  PRIMARY KEY (id_admin),
  UNIQUE KEY uk_admin_email (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Quadro 10 – CLIENTE  (pedido sem cadastro: RF01)
-- ---------------------------------------------------------------------
CREATE TABLE cliente (
  id_cliente   INT          NOT NULL AUTO_INCREMENT,
  nome         VARCHAR(100) NOT NULL,
  telefone     VARCHAR(20)  NOT NULL,          -- WhatsApp (somente dígitos)
  endereco     VARCHAR(150) NOT NULL,
  bairro       VARCHAR(60)  NOT NULL,
  complemento  VARCHAR(60)  NULL,
  PRIMARY KEY (id_cliente),
  KEY idx_cliente_telefone (telefone)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Quadro 13 – PRODUTO
-- ---------------------------------------------------------------------
CREATE TABLE produto (
  id_produto         INT           NOT NULL AUTO_INCREMENT,
  id_admin           INT           NOT NULL,
  nome               VARCHAR(100)  NOT NULL,
  descricao          VARCHAR(255)  NOT NULL,
  categoria          VARCHAR(40)   NOT NULL,
  preco              DECIMAL(8,2)  NOT NULL,
  preco_promocional  DECIMAL(8,2)  NULL,
  imagem             VARCHAR(255)  NULL,
  disponivel         BOOLEAN       NOT NULL DEFAULT TRUE,
  PRIMARY KEY (id_produto),
  KEY idx_produto_categoria (categoria),
  CONSTRAINT fk_produto_admin FOREIGN KEY (id_admin)
    REFERENCES administrador (id_admin)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Quadro 11 – PEDIDO
-- status: recebido | em_preparo | saiu_entrega | entregue | cancelado
-- ---------------------------------------------------------------------
CREATE TABLE pedido (
  id_pedido        INT            NOT NULL AUTO_INCREMENT,
  id_cliente       INT            NOT NULL,
  id_admin         INT            NULL,        -- preenchido no aceite
  data_hora        DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status           VARCHAR(20)    NOT NULL DEFAULT 'recebido',
  forma_pagamento  VARCHAR(20)    NOT NULL,    -- pix | cartao | dinheiro
  pago_no_site     BOOLEAN        NOT NULL DEFAULT FALSE,
  taxa_entrega     DECIMAL(8,2)   NOT NULL DEFAULT 0,
  valor_total      DECIMAL(10,2)  NOT NULL,
  observacao       VARCHAR(255)   NULL,
  PRIMARY KEY (id_pedido),
  KEY idx_pedido_data (data_hora),
  KEY idx_pedido_status (status),
  CONSTRAINT fk_pedido_cliente FOREIGN KEY (id_cliente)
    REFERENCES cliente (id_cliente),
  CONSTRAINT fk_pedido_admin FOREIGN KEY (id_admin)
    REFERENCES administrador (id_admin)
) ENGINE=InnoDB AUTO_INCREMENT=1020;

-- ---------------------------------------------------------------------
-- Quadro 12 – ITEM_PEDIDO
-- ---------------------------------------------------------------------
CREATE TABLE item_pedido (
  id_item         INT            NOT NULL AUTO_INCREMENT,
  id_pedido       INT            NOT NULL,
  id_produto      INT            NOT NULL,
  quantidade      INT            NOT NULL,
  preco_unitario  DECIMAL(8,2)   NOT NULL,     -- preço no momento da compra
  subtotal        DECIMAL(10,2)  NOT NULL,
  PRIMARY KEY (id_item),
  CONSTRAINT fk_item_pedido FOREIGN KEY (id_pedido)
    REFERENCES pedido (id_pedido) ON DELETE CASCADE,
  CONSTRAINT fk_item_produto FOREIGN KEY (id_produto)
    REFERENCES produto (id_produto)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Apoio – HISTORICO_STATUS (linha do tempo do acompanhamento, Figura 10)
-- ---------------------------------------------------------------------
CREATE TABLE historico_status (
  id_historico  INT          NOT NULL AUTO_INCREMENT,
  id_pedido     INT          NOT NULL,
  status        VARCHAR(20)  NOT NULL,
  data_hora     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_historico),
  CONSTRAINT fk_historico_pedido FOREIGN KEY (id_pedido)
    REFERENCES pedido (id_pedido) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Apoio – CONFIGURACAO (tela "Configurações" do painel)
-- ---------------------------------------------------------------------
CREATE TABLE configuracao (
  chave  VARCHAR(40)  NOT NULL,
  valor  VARCHAR(255) NOT NULL,
  PRIMARY KEY (chave)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Apoio – ENTREGA (frete calculado pela distância — 1:1 com PEDIDO)
-- ---------------------------------------------------------------------
CREATE TABLE entrega (
  id_pedido     INT           NOT NULL,
  distancia_km  DECIMAL(6,2)  NULL,          -- NULL quando foi usada a taxa padrão
  tempo_min     SMALLINT      NOT NULL,      -- previsão (preparo + deslocamento)
  tempo_max     SMALLINT      NOT NULL,
  metodo        VARCHAR(12)   NOT NULL,      -- rota | linha_reta | padrao
  PRIMARY KEY (id_pedido),
  CONSTRAINT fk_entrega_pedido FOREIGN KEY (id_pedido)
    REFERENCES pedido (id_pedido) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Apoio – PROMOCAO (tempo para o fim do desconto — 1:1 com PRODUTO)
-- ---------------------------------------------------------------------
CREATE TABLE promocao (
  id_produto   INT          NOT NULL,
  data_inicio  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_fim     DATETIME     NOT NULL,
  modo         VARCHAR(12)  NOT NULL DEFAULT 'automatico',  -- automatico | manual
  PRIMARY KEY (id_produto),
  KEY idx_promocao_fim (data_fim),
  CONSTRAINT fk_promocao_produto FOREIGN KEY (id_produto)
    REFERENCES produto (id_produto) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Apoio – CATEGORIA (lista e ordem das categorias, editáveis no painel).
-- O produto guarda o nome em produto.categoria VARCHAR(40), como no Quadro 13.
-- ---------------------------------------------------------------------
CREATE TABLE categoria (
  id_categoria  INT          NOT NULL AUTO_INCREMENT,
  nome          VARCHAR(40)  NOT NULL,
  ordem         SMALLINT     NOT NULL DEFAULT 0,
  PRIMARY KEY (id_categoria),
  UNIQUE KEY uk_categoria_nome (nome)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Apoio – CACHE_ENDERECO (evita repetir consultas ao OpenStreetMap)
-- ---------------------------------------------------------------------
CREATE TABLE cache_endereco (
  chave      VARCHAR(255)   NOT NULL,
  lat        DECIMAL(10,7)  NULL,             -- NULL = endereço não encontrado
  lng        DECIMAL(10,7)  NULL,
  precisao   VARCHAR(10)    NULL,             -- endereco | rua | bairro
  criado_em  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (chave)
) ENGINE=InnoDB;

-- =====================================================================
--  DADOS INICIAIS (ilustrativos, como nas Figuras 8 a 11)
-- =====================================================================

-- Administrador de demonstração
--   e-mail: admin@artenacozinha.com.br   senha: Arte@2026
--   (troque a senha em Painel > Configurações antes de publicar)
INSERT INTO administrador (id_admin, nome, email, senha_hash) VALUES
(1, 'Administrador', 'admin@artenacozinha.com.br',
 '$2y$10$/.1oB0cN0ygMsJQ3I4OxQugCoBuK7cRnuXhx7vXCJdV7vEPCLS7gy');

INSERT INTO configuracao (chave, valor) VALUES
('loja_aberta',       '1'),
('taxa_entrega',      '5.00'),   -- taxa padrão, se o serviço de mapas estiver fora do ar
('tempo_entrega',     '40–60 min'),
('whatsapp_loja',     '5515999990000'),
('nome_loja',         'Arte na Cozinha'),
('cidade',            'Sorocaba – SP'),
('horario',           'Terça a domingo, das 10h às 20h'),
('produto_destaque',  '13'),
-- Frete por distância (v1.1)
('endereco_loja',       'R. Francisco Catalano, 440 - Jardim Brasilândia'),
('loja_lat',            '-23.4766020'),
('loja_lng',            '-47.4638526'),
('valor_km',            '3.30'),   -- média brasileira por km
('taxa_minima',         '0.00'),
('raio_maximo_km',      '25'),
('tempo_preparo',       '30'),     -- minutos
('promo_duracao_horas', '168');    -- promoções acabam sozinhas em 7 dias

INSERT INTO categoria (nome, ordem) VALUES
('Bolos', 1), ('Doces', 2), ('Tortas', 3), ('Bebidas', 4);

-- Produtos (categoria: Bolos | Doces | Tortas | Bebidas)
INSERT INTO produto (id_produto, id_admin, nome, descricao, categoria, preco, preco_promocional, imagem, disponivel) VALUES
( 1, 1, 'Cupcake Red Velvet',            'Massa aveludada com cobertura de cream cheese',                'Doces',   9.90, NULL,  'assets/img/produtos/cupcake-red-velvet.svg', 1),
( 2, 1, 'Brigadeiro gourmet (4 un.)',    'Caixa com 4 unidades, sabores sortidos',                       'Doces',  16.00, NULL,  'assets/img/produtos/brigadeiro-gourmet.svg', 1),
( 3, 1, 'Bolo de chocolate (1 kg)',      'Recheio de brigadeiro e cobertura de ganache',                 'Bolos',  79.90, NULL,  'assets/img/produtos/bolo-chocolate.svg', 1),
( 4, 1, 'Bolo de cenoura (1 kg)',        'Massa fofinha de cenoura com cobertura de chocolate',          'Bolos',  64.90, NULL,  'assets/img/produtos/bolo-cenoura.svg', 1),
( 5, 1, 'Bolo de morango com chantilly', 'Pão de ló, creme de baunilha, morangos frescos e chantilly',   'Bolos',  89.90, 79.90, 'assets/img/produtos/bolo-morango.svg', 1),
( 6, 1, 'Torta de limão',                'Fatia com massa amanteigada, creme de limão e merengue',       'Tortas', 14.50, NULL,  'assets/img/produtos/torta-limao.svg', 1),
( 7, 1, 'Torta holandesa',               'Fatia com creme, base de biscoito e cobertura de chocolate',   'Tortas', 15.90, NULL,  'assets/img/produtos/torta-holandesa.svg', 1),
( 8, 1, 'Cheesecake de frutas vermelhas','Fatia de cheesecake com calda de frutas vermelhas',            'Tortas', 16.90, 14.90, 'assets/img/produtos/cheesecake.svg', 1),
( 9, 1, 'Brownie',                       'Brownie de chocolate meio amargo com castanhas',               'Doces',  11.90, NULL,  'assets/img/produtos/brownie.svg', 1),
(10, 1, 'Macarons (6 un.)',              'Caixa com 6 macarons de sabores variados',                     'Doces',  29.90, NULL,  'assets/img/produtos/macarons.svg', 1),
(11, 1, 'Trufas (6 un.)',                'Trufas de chocolate belga com recheios cremosos',              'Doces',  21.00, NULL,  'assets/img/produtos/trufas.svg', 1),
(12, 1, 'Donut recheado',                'Donut com recheio de doce de leite e cobertura rosa',          'Doces',   8.50, NULL,  'assets/img/produtos/donut.svg', 1),
(13, 1, 'Bolo de pote de Ninho com morango','Camadas de bolo, creme de leite Ninho e morangos',          'Bolos',  16.90, 13.90, 'assets/img/produtos/bolo-pote-ninho.svg', 1),
(14, 1, 'Café coado',                    'Café especial coado na hora (200 ml)',                         'Bebidas', 6.00, NULL,  'assets/img/produtos/cafe.svg', 1),
(15, 1, 'Chocolate quente',              'Chocolate cremoso com chantilly (300 ml)',                     'Bebidas',10.90, NULL,  'assets/img/produtos/chocolate-quente.svg', 1),
(16, 1, 'Suco natural de laranja',       'Suco feito na hora (400 ml)',                                  'Bebidas', 8.90, NULL,  'assets/img/produtos/suco-laranja.svg', 1),
(17, 1, 'Milk-shake de morango',         'Sorvete de creme batido com morangos (400 ml)',                'Bebidas',15.90, NULL,  'assets/img/produtos/milkshake.svg', 1);

-- Clientes ilustrativos (Figura 11)
INSERT INTO cliente (id_cliente, nome, telefone, endereco, bairro, complemento) VALUES
(1, 'Ana Paula',       '15991110001', 'Rua São Bento, 45',          'Centro',          NULL),
(2, 'Carlos Mendes',   '15991110002', 'Av. General Carneiro, 820',  'Vila Lucy',       'Apto 32'),
(3, 'Juliana Ramos',   '15991110003', 'Rua Humberto de Campos, 150','Jardim Zulmira',  NULL),
(4, 'Maria Souza',     '15999990000', 'Rua das Flores, 120',        'Centro',          'Casa azul'),
(5, 'Pedro Lima',      '15991110005', 'Rua Padre Luiz, 77',         'Vila Hortência',  NULL),
(6, 'Fernanda Torres', '15991110006', 'Av. Itavuvu, 1500',          'Vila Olímpia',    'Bloco B');

-- Pedidos ilustrativos com horários relativos a "agora" (aparecem em "Pedidos de hoje")
SET @agora = NOW();

INSERT INTO pedido (id_pedido, id_cliente, id_admin, data_hora, status, forma_pagamento, pago_no_site, taxa_entrega, valor_total, observacao) VALUES
(1022, 6, 1, @agora - INTERVAL 86 MINUTE, 'entregue',     'pix',      1,  7.26, 21.16, NULL),
(1024, 5, 1, @agora - INTERVAL 58 MINUTE, 'entregue',     'cartao',   1, 13.20, 61.20, NULL),
(1027, 4, 1, @agora - INTERVAL 36 MINUTE, 'saiu_entrega', 'pix',      1, 18.81, 48.71, 'Tocar a campainha'),
(1029, 3, 1, @agora - INTERVAL 19 MINUTE, 'em_preparo',   'dinheiro', 0, 15.84, 42.24, 'Troco para R$ 50'),
(1030, 2, 1, @agora - INTERVAL 13 MINUTE, 'em_preparo',   'pix',      1, 17.16, 97.06, NULL),
(1031, 1, NULL, @agora - INTERVAL 2 MINUTE, 'recebido',   'cartao',   0, 28.38, 48.18, NULL);

-- Frete de cada pedido: distância da rota (OpenStreetMap) × R$ 3,30
INSERT INTO entrega (id_pedido, distancia_km, tempo_min, tempo_max, metodo) VALUES
(1022, 2.2, 35, 50, 'rota'),
(1024, 4.0, 40, 55, 'rota'),
(1027, 5.7, 40, 55, 'rota'),
(1029, 4.8, 40, 55, 'rota'),
(1030, 5.2, 40, 55, 'rota'),
(1031, 8.6, 45, 60, 'rota');

ALTER TABLE pedido AUTO_INCREMENT = 1032;

INSERT INTO item_pedido (id_pedido, id_produto, quantidade, preco_unitario, subtotal) VALUES
(1022, 13, 1, 13.90, 13.90),
(1024,  2, 3, 16.00, 48.00),
(1027, 13, 1, 13.90, 13.90),
(1027,  2, 1, 16.00, 16.00),
(1029,  6, 1, 14.50, 14.50),
(1029,  9, 1, 11.90, 11.90),
(1030,  3, 1, 79.90, 79.90),
(1031,  1, 2,  9.90, 19.80);

INSERT INTO historico_status (id_pedido, status, data_hora) VALUES
(1022, 'recebido',     @agora - INTERVAL 86 MINUTE),
(1022, 'em_preparo',   @agora - INTERVAL 83 MINUTE),
(1022, 'saiu_entrega', @agora - INTERVAL 70 MINUTE),
(1022, 'entregue',     @agora - INTERVAL 55 MINUTE),
(1024, 'recebido',     @agora - INTERVAL 58 MINUTE),
(1024, 'em_preparo',   @agora - INTERVAL 55 MINUTE),
(1024, 'saiu_entrega', @agora - INTERVAL 40 MINUTE),
(1024, 'entregue',     @agora - INTERVAL 22 MINUTE),
(1027, 'recebido',     @agora - INTERVAL 36 MINUTE),
(1027, 'em_preparo',   @agora - INTERVAL 30 MINUTE),
(1027, 'saiu_entrega', @agora - INTERVAL 7 MINUTE),
(1029, 'recebido',     @agora - INTERVAL 19 MINUTE),
(1029, 'em_preparo',   @agora - INTERVAL 16 MINUTE),
(1030, 'recebido',     @agora - INTERVAL 13 MINUTE),
(1030, 'em_preparo',   @agora - INTERVAL 10 MINUTE),
(1031, 'recebido',     @agora - INTERVAL 2 MINUTE);

-- Prazos das promoções (contagem regressiva no site)
INSERT INTO promocao (id_produto, data_inicio, data_fim, modo) VALUES
(13, @agora,                    @agora + INTERVAL 7 DAY,                                   'automatico'),
( 8, @agora - INTERVAL 4 DAY,   @agora + INTERVAL 3 DAY,                                   'automatico'),
( 5, @agora - INTERVAL 1 DAY,   TIMESTAMP(DATE(@agora + INTERVAL 2 DAY), '20:00:00'),      'manual');
