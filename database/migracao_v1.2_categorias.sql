-- =====================================================================
--  ARTE NA COZINHA — Migração v1.2
--  Categorias do cardápio editáveis pelo painel
--
--  Use SOMENTE em um banco que já tem a versão 1.1.
--  (Vindo da 1.0: importe antes migracao_v1.1_frete_promocoes.sql.)
--  Instalação nova: importe apenas database/arte_na_cozinha.sql.
-- =====================================================================

-- Na hospedagem, apague a linha abaixo (o banco já está selecionado no phpMyAdmin)
USE arte_na_cozinha;

-- Lista de categorias (tabela de apoio). O produto continua guardando o nome
-- em produto.categoria VARCHAR(40), como no dicionário de dados (Quadro 13).
CREATE TABLE IF NOT EXISTS categoria (
  id_categoria  INT          NOT NULL AUTO_INCREMENT,
  nome          VARCHAR(40)  NOT NULL,
  ordem         SMALLINT     NOT NULL DEFAULT 0,   -- ordem dos botões no cardápio
  PRIMARY KEY (id_categoria),
  UNIQUE KEY uk_categoria_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO categoria (nome, ordem) VALUES
('Bolos', 1), ('Doces', 2), ('Tortas', 3), ('Bebidas', 4);

-- Categorias que já existiam nos produtos e não estão na lista acima
INSERT IGNORE INTO categoria (nome, ordem)
SELECT DISTINCT categoria, 50 FROM produto;
