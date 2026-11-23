-- =====================================================================
--  ARTE NA COZINHA — Migração v1.3
--  Fotos reais no cardápio (Wikimedia Commons, licenças livres)
--
--  Troca as ilustrações SVG pelas fotos em assets/img/produtos/*.jpg.
--  Só altera produtos que ainda usam a ilustração original
--  (fotos enviadas pelo painel não são tocadas).
--  Instalação nova: importe apenas database/arte_na_cozinha.sql.
-- =====================================================================

-- Na hospedagem, apague a linha abaixo (o banco já está selecionado no phpMyAdmin)
USE arte_na_cozinha;

UPDATE produto SET imagem = 'assets/img/produtos/cupcake-red-velvet.jpg' WHERE imagem = 'assets/img/produtos/cupcake-red-velvet.svg';
UPDATE produto SET imagem = 'assets/img/produtos/brigadeiro-gourmet.jpg' WHERE imagem = 'assets/img/produtos/brigadeiro-gourmet.svg';
UPDATE produto SET imagem = 'assets/img/produtos/bolo-chocolate.jpg' WHERE imagem = 'assets/img/produtos/bolo-chocolate.svg';
UPDATE produto SET imagem = 'assets/img/produtos/bolo-cenoura.jpg' WHERE imagem = 'assets/img/produtos/bolo-cenoura.svg';
UPDATE produto SET imagem = 'assets/img/produtos/bolo-morango.jpg' WHERE imagem = 'assets/img/produtos/bolo-morango.svg';
UPDATE produto SET imagem = 'assets/img/produtos/torta-limao.jpg' WHERE imagem = 'assets/img/produtos/torta-limao.svg';
UPDATE produto SET imagem = 'assets/img/produtos/torta-holandesa.jpg' WHERE imagem = 'assets/img/produtos/torta-holandesa.svg';
UPDATE produto SET imagem = 'assets/img/produtos/cheesecake.jpg' WHERE imagem = 'assets/img/produtos/cheesecake.svg';
UPDATE produto SET imagem = 'assets/img/produtos/brownie.jpg' WHERE imagem = 'assets/img/produtos/brownie.svg';
UPDATE produto SET imagem = 'assets/img/produtos/macarons.jpg' WHERE imagem = 'assets/img/produtos/macarons.svg';
UPDATE produto SET imagem = 'assets/img/produtos/trufas.jpg' WHERE imagem = 'assets/img/produtos/trufas.svg';
UPDATE produto SET imagem = 'assets/img/produtos/donut.jpg' WHERE imagem = 'assets/img/produtos/donut.svg';
UPDATE produto SET imagem = 'assets/img/produtos/bolo-pote-ninho.jpg' WHERE imagem = 'assets/img/produtos/bolo-pote-ninho.svg';
UPDATE produto SET imagem = 'assets/img/produtos/cafe.jpg' WHERE imagem = 'assets/img/produtos/cafe.svg';
UPDATE produto SET imagem = 'assets/img/produtos/chocolate-quente.jpg' WHERE imagem = 'assets/img/produtos/chocolate-quente.svg';
UPDATE produto SET imagem = 'assets/img/produtos/suco-laranja.jpg' WHERE imagem = 'assets/img/produtos/suco-laranja.svg';
UPDATE produto SET imagem = 'assets/img/produtos/milkshake.jpg' WHERE imagem = 'assets/img/produtos/milkshake.svg';
