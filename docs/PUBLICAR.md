# Como publicar o site e acessar de qualquer lugar

Há dois caminhos. O **Caminho A** é o definitivo: o site fica no ar 24 horas, com endereço próprio.
O **Caminho B** é rápido para uma demonstração: o site roda no seu computador e ganha um link público
temporário.

---

## Caminho A — Hospedagem gratuita com PHP + MySQL (recomendado)

Qualquer hospedagem com **PHP 8** e **MySQL** serve (seção 6.1, RNF04). Exemplo com a
**InfinityFree** (gratuita, sem cartão de crédito):

### 1. Criar a conta e o site
1. Acesse <https://www.infinityfree.com> e crie uma conta.
2. Clique em **Create Account** (conta de hospedagem) e escolha um subdomínio gratuito,
   por exemplo `artenacozinha.infinityfreeapp.com`.
   (Depois dá para usar o domínio próprio `.com.br`, citado no Quadro 4.)

### 2. Criar o banco de dados
1. No painel da hospedagem (**Control Panel / cPanel**), abra **MySQL Databases**.
2. Crie um banco (ex.: `arte_na_cozinha`). Anote os dados exibidos:
   **MySQL Host**, **MySQL User**, **MySQL Password** e o **nome completo do banco**
   (algo como `if0_12345678_arte_na_cozinha`).
3. Abra o **phpMyAdmin** desse banco → aba **Importar**.
4. Antes de importar, abra `database/arte_na_cozinha.sql` num editor e **apague estas duas linhas**
   (a hospedagem já criou o banco com outro nome):
   ```sql
   CREATE DATABASE IF NOT EXISTS arte_na_cozinha ...;
   USE arte_na_cozinha;
   ```
5. Importe o arquivo.

### 3. Enviar os arquivos
1. No painel, abra o **Online File Manager** (ou use o FileZilla com os dados de FTP).
2. Entre na pasta **`htdocs`** e envie **todo o conteúdo** do projeto (exceto a pasta `.git`).

### 4. Configurar a conexão com o banco
1. Na hospedagem, copie `includes/config.local.example.php` para **`includes/config.local.php`**.
2. Preencha com os dados anotados no passo 2 e troque a `chave_app` por um texto longo e aleatório:
   ```php
   return [
       'db_host'    => 'sql123.infinityfree.com',
       'db_porta'   => 3306,
       'db_nome'    => 'if0_12345678_arte_na_cozinha',
       'db_usuario' => 'if0_12345678',
       'db_senha'   => 'SENHA_DO_BANCO',
       'chave_app'  => 'um-texto-bem-longo-e-aleatorio-so-seu',
       'debug'      => false,
   ];
   ```
   Esse arquivo **não vai para o GitHub** (está no `.gitignore`), então a senha do banco fica protegida.

### 5. HTTPS e segurança
1. No painel da hospedagem, ative o **SSL gratuito** para o domínio.
2. No arquivo `.htaccess` da raiz, **descomente** as duas linhas de "Força HTTPS" (RNF10).
3. Entre em `https://SEU-SITE/admin/`, faça login e **troque a senha** em **Configurações**.
4. Em **Configurações**, coloque o **WhatsApp real** da loja.

5. Em **Configurações → Frete por distância**, confira o endereço da loja, o valor por km e o raio de entrega.

Pronto: o site abre em qualquer celular ou computador pelo endereço da hospedagem. 🎉

### Atualizando um site que já estava publicado (versão 1.0 → 1.1)

1. Envie os arquivos novos por cima dos antigos (o `includes/config.local.php` da hospedagem continua lá).
2. No phpMyAdmin da hospedagem, importe **`database/migracao_v1.1_frete_promocoes.sql`** e depois
   **`database/migracao_v1.2_categorias.sql`**, apagando antes a linha `USE arte_na_cozinha;` de cada um.
   (Se o site já estava na 1.1, importe só a 1.2.) Os pedidos e produtos existentes são mantidos.

### Frete por distância na hospedagem

O cálculo do frete consulta dois serviços gratuitos pela internet: `nominatim.openstreetmap.org` (mapa) e
`router.project-osrm.org` (rota). Algumas hospedagens gratuitas **bloqueiam conexões de saída**. Nesse caso
o site continua funcionando, mas cobra a **taxa padrão** das Configurações. Para testar, faça um pedido de
teste: se o carrinho mostrar "X km da confeitaria", o cálculo por distância está funcionando.

> Alternativas com PHP + MySQL: Hostinger, HostGator, Locaweb (pagas, com domínio `.com.br`)
> ou AwardSpace (gratuita).

---

## Caminho B — Link público temporário a partir do seu computador

Use para mostrar o site no celular de outra pessoa ou para a banca, sem hospedagem.
O link funciona **enquanto o computador estiver ligado** com o XAMPP rodando.

1. Instale o Cloudflare Tunnel (uma vez só). No PowerShell:
   ```powershell
   winget install --id Cloudflare.cloudflared
   ```
2. Ligue o **Apache** e o **MySQL** no XAMPP.
3. Rode:
   ```powershell
   cloudflared tunnel --url http://localhost:80
   ```
4. O terminal mostra um endereço como `https://palavras-aleatorias.trycloudflare.com`.
   O site fica em: **`https://palavras-aleatorias.trycloudflare.com/artes-na-cozinha/`**
5. Para encerrar, pressione `Ctrl + C`.

Não precisa de conta. O link muda a cada vez que o comando é executado.

---

## Checklist antes de apresentar

- [ ] Banco importado e o site abre sem erros
- [ ] Senha do painel trocada
- [ ] WhatsApp da loja configurado
- [ ] Loja marcada como **aberta**
- [ ] Frete calculando por km (carrinho mostra "X km da confeitaria")
- [ ] Pelo menos uma promoção ativa com prazo (para mostrar a contagem regressiva)
- [ ] Testado no celular (Android e iPhone) — CT10
- [ ] Tempo de carregamento medido no Chrome (F12 → Lighthouse) — CT11
