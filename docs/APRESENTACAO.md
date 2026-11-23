# Roteiro de apresentação — Arte na Cozinha

Guia para o grupo explicar **como o sistema foi construído** e fazer a **demonstração ao vivo** para a banca.

---

## 1. Como o sistema foi construído (a partir do TCC)

Cada decisão do código vem de uma seção do documento:

| Seção do TCC | O que virou no código |
|---|---|
| 5.1 Requisitos funcionais (Quadro 9) | Cada RF tem uma tela ou arquivo responsável (tabela no README) |
| 5.2 DER + dicionário de dados (Quadros 10–14) | `database/arte_na_cozinha.sql`: tabelas, tipos, PK e FK idênticos |
| 5.3 Casos de uso (Quadro 15) | UC05 "Realizar pedido" inclui UC06 (pagamento) e UC07 (WhatsApp) no mesmo fluxo; UC12 estende UC11 (botão de ação na lista de pedidos) |
| 7.1.1 Logotipo (Figuras 6 e 7) | Recriado em SVG vetorial: principal, horizontal, negativa, monocromática e ícone (favicon) |
| 7.1.2 Paleta (Quadro 19) | Variáveis CSS `--cor-principal`, `--cor-secundaria`… exatamente como no Apêndice A |
| 7.1.3 Tipografia (Quadro 20) | Lora itálico (logo e destaques) + Poppins (textos, botões, preços, painel) |
| 7.1.4 Telas (Figuras 8–11) | `index.php`, `carrinho.php`, `acompanhar.php`, `admin/index.php` |
| 3.1.4 Viabilidade legal (LGPD) | Coleta mínima, `privacidade.php`, senha criptografada, link de pedido protegido |
| 8 Testes (Quadro 21) | CT01 a CT15 executados e registrados no README |
| Melhoria v1.1 — frete | Frete = distância da rota × **R$ 3,30/km** e tempo = preparo + trajeto, saindo da **R. Francisco Catalano, 440 – Jardim Brasilândia** (`includes/frete.php`) |
| Melhoria v1.1 — promoções | Cada desconto tem prazo **automático** (7 dias) ou **manual**, com contagem regressiva e fim automático (`promocao`, `contagem.js`) |
| Melhoria v1.2 — conta do cliente | Login só com nome + WhatsApp: endereço salvo, pedidos anteriores e "pedir de novo" (`entrar.php`, `conta.php`) |
| Melhoria v1.2 — painel | Categorias editáveis (`admin/categorias.php`) e promoções separadas da edição de produto |

### Arquitetura em 3 camadas

```
 NAVEGADOR (cliente)                SERVIDOR (PHP)                   BANCO (MySQL)
 ───────────────────                ──────────────                   ─────────────
 HTML + CSS + JavaScript   ──►   index.php / carrinho.php   ──►   produto, configuracao
 carrinho no localStorage  ──►   api/pedido.php (JSON)       ──►   cliente, pedido,
                                                                    item_pedido, historico
 acompanhar.js (a cada 10s)──►   api/status.php              ──►   pedido.status
 painel (admin/)           ──►   login + sessão + CSRF       ──►   administrador (bcrypt)
```

### Fluxo de um pedido (Figura 5)

1. O cliente abre o cardápio (`index.php`). O PHP busca os produtos no banco e monta a página.
2. Ao tocar em **+**, o JavaScript guarda o item no **localStorage**, então o carrinho não se perde se a página recarregar.
3. No carrinho, o cliente informa **nome, WhatsApp e endereço** (sem cadastro, RF01) e escolhe **pagar pelo site ou na entrega** (RF04/RF05).
4. Ao finalizar, o JavaScript envia os dados em **JSON** para `api/pedido.php`, que:
   - valida os campos e a forma de pagamento (dinheiro só na entrega);
   - **busca os preços no banco**, nunca confiando no preço enviado pelo navegador;
   - grava `cliente`, `pedido`, `item_pedido` e `historico_status` numa **transação**: ou grava tudo, ou nada.
5. O cliente vai para a tela de acompanhamento, com o botão que abre o **WhatsApp com o resumo pronto** (RF08).
6. No painel, o pedido aparece como **Novo**, com som e aviso. O administrador clica em **Aceitar → Saiu p/ entrega → Entregue** (RF11).
7. A tela do cliente consulta o status a cada 10 segundos e **atualiza sozinha** (RF03).

### Segurança (RNF10)

| Ameaça | Proteção usada |
|---|---|
| Roubo de senhas | `password_hash()` (bcrypt): a senha nunca fica em texto no banco |
| SQL Injection | PDO com *prepared statements* em todas as consultas |
| XSS (código malicioso na página) | Função `h()` com `htmlspecialchars` em tudo que é exibido |
| CSRF (formulário falso) | Token secreto em todos os formulários e na API de pedidos |
| Força bruta no login | 5 tentativas erradas bloqueiam por 5 minutos |
| Ver pedido de outra pessoa | Link de acompanhamento com código HMAC (LGPD) |
| Alterar preço pelo navegador | O servidor recalcula tudo com os preços do banco |
| Acesso a arquivos internos | `.htaccess` bloqueia `includes/`, `database/` e `scripts/` |
| Upload malicioso | Só JPG/PNG/WEBP (checagem do tipo real), nome aleatório, PHP bloqueado em `uploads/` |

---

## 2. Roteiro da demonstração ao vivo (≈ 8 minutos)


1. **Cardápio (Figura 8):** mostre o status "Aberto agora", a busca (digite "limao", sem acento), os filtros (Bolos, Promoções) e o banner da promoção da semana.
2. **Carrinho (Figura 9):** adicione 2 produtos, altere quantidades e tente **finalizar vazio** para mostrar a validação (CT04).
   Digite um endereço de Sorocaba (ex.: *Rua Aparecida, 500 – Jardim Santa Rosália*): o quadro mostra **"Frete calculado: R$ 7,92"** e a entrega estimada (CT12). Troque por um bairro mais longe para mostrar o frete mudando.
3. **Pagamento:** preencha os dados, escolha **PIX pelo site** e mostre o QR Code de demonstração. Depois volte e mostre que "Pagar na entrega" libera **Dinheiro** e esconde o PIX (RF05).
4. **Acompanhamento (Figura 10):** finalize e mostre o número do pedido, a previsão, a linha do tempo e o botão do WhatsApp.
5. **Painel (Figura 11):** no computador, entre em `/admin`. O pedido aparece como **Novo**. Clique em **Aceitar**.
   Abra o pedido e mostre o **QR Code do entregador**: aponte a câmera do celular e o Google Maps abre com a rota até o cliente (CT22).
6. **Tempo real:** volte ao celular. Em até 10 segundos, a linha do tempo muda para **Em preparo** (CT09).
7. **Produtos e promoções:** altere o preço de um produto (CT08). Em **Promoções**, crie uma promoção no modo **Automático** e mostre o aviso **"Tempo automático para o fim do desconto"**. Depois crie outra no modo **Manual** terminando em 2 minutos e mostre a contagem no cardápio zerando e o preço voltando ao normal (CT15).
8. **Conta do cliente:** toque no ícone de pessoa, entre com o nome e o WhatsApp usados no pedido e mostre os pedidos anteriores. Toque em **Pedir de novo**: o carrinho abre já com os itens, o endereço preenchido e *"Frete calculado: R$ …"* (CT16–CT18).
9. **Categorias:** no painel, renomeie "Tortas" para "Tortas e Cheesecakes" e mostre o botão mudando no cardápio (CT19). Depois volte o nome.
10. **Loja fechada:** clique em "Loja: aberta" para fechar e mostre que o site deixa de aceitar pedidos.
11. **Celular:** mostre o painel no celular (menu vira gaveta e tabelas viram cartões).

---

## 3. Perguntas que a banca pode fazer

**Por que PHP e MySQL?**
São gratuitos, bem documentados e aceitos por praticamente qualquer hospedagem barata (Quadro 4 e RNF04). Na seção 3.1.1, o grupo já previa aprofundar os estudos em back-end e banco de dados.

**Onde fica o carrinho antes de finalizar?**
No `localStorage` do navegador: rápido e sem precisar de cadastro. Só os IDs e as quantidades ficam lá; os preços vêm sempre do servidor.

**Como o cliente acompanha em "tempo real"?**
A página consulta `api/status.php` a cada 10 segundos (técnica de *polling*). Quando o status muda, a página se atualiza. Se a aba estiver em segundo plano, a consulta pausa para economizar bateria.

**O pagamento é real?**
Nesta versão, o pagamento on-line é **simulado** para a apresentação. A integração com uma instituição de pagamento (taxa por transação, Quadro 4) é o próximo passo. A estrutura já registra `forma_pagamento` e `pago_no_site`, como no dicionário de dados.

**E o WhatsApp? Ele envia sozinho?**
Na primeira versão, como previsto na seção 3.1.1, o sistema gera um **link de conversa** com o resumo já preenchido (gratuito). O envio automático pela API do WhatsApp Business é uma melhoria futura (Conclusão).

**Como a LGPD é atendida?**
Coleta só nome, WhatsApp e endereço; tem aviso de privacidade; o painel exige login; a senha é criptografada; e o link de acompanhamento tem um código, para que ninguém veja o pedido de outra pessoa trocando o número.

**Por que há tabelas a mais que o DER?**
São **tabelas de apoio**, criadas para não alterar as 5 entidades do DER, que estão exatamente como nos Quadros 10–14.
`historico_status` guarda o horário de cada etapa (linha do tempo da Figura 10). `configuracao` guarda WhatsApp, endereço da loja, valor por km e se a loja está aberta, editáveis no painel sem mexer no código. `entrega` guarda a distância e a previsão de cada pedido (ligada 1:1 ao PEDIDO). `promocao` guarda o prazo de cada desconto (ligada 1:1 ao PRODUTO). `cache_endereco` guarda os endereços já localizados no mapa.

**Como o frete é calculado?**
O endereço do cliente é localizado no mapa pelo **OpenStreetMap** (Nominatim), e a rota de carro a partir da confeitaria é calculada pelo **OSRM**. Os dois serviços são gratuitos e não exigem cadastro. Frete = distância da rota × **R$ 3,30 por km** (média brasileira); tempo = **30 min de preparo + tempo do trajeto** (+20% de margem para o trânsito). O valor é recalculado no servidor ao fechar o pedido, então ninguém consegue alterar o frete pelo navegador.

**E se o serviço de mapas cair?**
O sistema tem planos B: sem rota, usa a distância em linha reta × 1,35 (desvio médio das ruas); sem nenhum serviço de mapa, usa a taxa padrão configurada no painel, para a loja não perder a venda. Os endereços já pesquisados ficam guardados (`cache_endereco`) e não precisam ser consultados de novo.

**Como a promoção acaba sozinha?**
Cada promoção tem uma data de fim na tabela `promocao`, definida automaticamente (7 dias) ou manualmente pelo administrador. Toda página do site chama `expirar_promocoes()`, que tira o desconto dos produtos vencidos antes de mostrar qualquer preço. No navegador, `contagem.js` mostra a contagem regressiva usando a **hora do servidor** (não a do celular) e recarrega a página quando o tempo zera.

**Por que o login não tem senha?**
Para não criar barreira, o objetivo do TCC é um pedido "sem cadastro" (RF01). A conta usa nome + WhatsApp: se o número
já tem pedidos, o primeiro nome precisa conferir, e há limite de tentativas. A conta guarda só o que o cliente já
informaria em qualquer pedido (nome, WhatsApp e endereço). Uma melhoria futura seria confirmar o número por um
código enviado pelo WhatsApp Business.

**Como as categorias ficam editáveis sem mudar o DER?**
O produto continua guardando o nome da categoria em `produto.categoria` (Quadro 13). A tabela de apoio `categoria`
guarda a lista e a ordem. Ao renomear, uma **transação** atualiza a categoria e todos os produtos dela de uma vez.

**Quanto a loja economiza?**
Em um pedido de R$ 100 no Plano Entrega do iFood, cerca de R$ 26,50 ficam com a plataforma (Quadro 1). No site próprio, o custo fica restrito a hospedagem, domínio (R$ 40/ano) e à taxa do meio de pagamento.

---

## 4. Diferenciais do sistema (o que não é "só mais um site de confeitaria")

Já implementados:

| Diferencial | Por que importa |
|---|---|
| **Frete pela distância real** (rota × R$ 3,30/km) | Preço justo para perto e longe; plataformas genéricas cobram taxa fixa ou por faixa |
| **QR Code da rota para o entregador** | Entregador não digita endereço: aponta a câmera e sai dirigindo. Menos erro de entrega |
| **Promoções que acabam sozinhas** (automático ou data marcada) | A loja não esquece desconto ligado; a contagem cria senso de urgência no cliente |
| **Conta só com nome + WhatsApp** e "Pedir de novo" | Sem senha nem cadastro longo; recompra em um toque (confeitaria vive de cliente fiel) |
| **Sem comissão por pedido** | Comparado ao iFood (até ~26,5% por pedido, Quadro 1) |
| **LGPD desde o projeto** | Link de pedido com código, coleta mínima, QR gerado sem enviar dados a terceiros |
| **Funciona no navegador**, sem instalar app | Barreira zero para cliente novo (Quadro 3) |

Ideias para apresentar como evolução (não implementadas):

- **Agendamento de encomendas** com data e hora de retirada/entrega — essencial para bolos de festa, que o iFood não atende bem.
- **Monte seu bolo**: o cliente escolhe massa, recheio, cobertura e tamanho, com preço calculado na hora.
- **Calendário de produção** no painel: quantos bolos e de quais sabores fazer em cada dia.
- **Fidelidade pelo WhatsApp**: a cada 10 pedidos, um docinho grátis (o histórico já existe na conta do cliente).
- **Avaliação pós-entrega** com foto, exibida no cardápio como prova social.
- **Rastreio do entregador em tempo real** no mapa, a partir do celular dele (o QR já abre a rota).
- **Alerta de estoque**: produto some do cardápio sozinho quando acaba a produção do dia.

---

## 5. Melhorias futuras (Conclusão do TCC)

- Integração com meio de pagamento real (PIX automático / cartão)
- Envio automático pelo WhatsApp Business API
- Programa de fidelidade e cupons de desconto
- Relatórios de vendas no painel (gráficos por período e por produto)
- Backup semanal automático do banco (manutenção preventiva, Quadro 23)
