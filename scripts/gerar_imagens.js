/**
 * Gera os arquivos SVG da identidade visual (logotipo e variações — Figuras 6 e 7)
 * e as imagens ilustrativas dos produtos.
 *
 * Uso:  node scripts/gerar_imagens.js
 */
const fs = require('fs');
const path = require('path');

const COR = {
  framboesa: '#C2185B', morango: '#E8588A', algodao: '#F8D0DE',
  chantilly: '#FFF6F8', chocolate: '#4A2A2F', caramelo: '#E9A23B',
};

const raiz = path.join(__dirname, '..', 'assets', 'img');
fs.mkdirSync(path.join(raiz, 'produtos'), { recursive: true });

/** Cupcake do logotipo (desenhado num quadro de 200×200) */
function cupcake({ circulo, cobertura, forminha, listras, coracao, confeito1, confeito2 }) {
  const conf = (x1, y1, x2, y2, c) =>
    `<line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="${c}" stroke-width="3.6" stroke-linecap="round"/>`;
  return `
  <circle cx="100" cy="100" r="100" fill="${circulo}"/>
  <path d="M100 53 L89 42 A7.6 7.6 0 1 1 100 31.5 A7.6 7.6 0 1 1 111 42 Z" fill="${coracao}"/>
  <g fill="${cobertura}">
    <rect x="73" y="54" width="54" height="27" rx="13.5"/>
    <rect x="63" y="70" width="74" height="27" rx="13.5"/>
    <rect x="53" y="87" width="94" height="31" rx="15.5"/>
  </g>
  ${conf(85, 68, 90, 65, confeito1)}${conf(110, 64, 114, 67, confeito2)}
  ${conf(80, 84, 85, 86, confeito2)}${conf(114, 83, 119, 86, confeito1)}
  ${conf(70, 104, 76, 101, confeito1)}${conf(97, 106, 103, 108, confeito1)}${conf(125, 104, 131, 101.5, confeito2)}
  <path d="M59 117 H141 L129.5 164 Q128.3 170 122 170 H78 Q71.7 170 70.5 164 Z" fill="${forminha}"/>
  <g stroke="${listras}" stroke-width="4" stroke-linecap="round">
    <line x1="79" y1="123" x2="82.5" y2="162"/><line x1="93" y1="123" x2="94.5" y2="162"/>
    <line x1="107" y1="123" x2="105.5" y2="162"/><line x1="121" y1="123" x2="117.5" y2="162"/>
  </g>`;
}

const VARIANTES = {
  principal: { circulo: COR.framboesa, cobertura: COR.algodao, forminha: COR.chantilly, listras: COR.algodao, coracao: COR.caramelo, confeito1: COR.caramelo, confeito2: COR.framboesa },
  negativa:  { circulo: '#FFFFFF', cobertura: COR.morango, forminha: COR.chantilly, listras: COR.framboesa, coracao: COR.caramelo, confeito1: COR.caramelo, confeito2: COR.algodao },
  mono:      { circulo: COR.chocolate, cobertura: '#FFFFFF', forminha: '#FFFFFF', listras: COR.chocolate, coracao: '#FFFFFF', confeito1: COR.chocolate, confeito2: COR.chocolate },
};

const FONTES = `<style>@import url('https://fonts.googleapis.com/css2?family=Lora:ital,wght@1,700&amp;family=Poppins:wght@500&amp;display=swap');</style>`;

function salvar(nome, conteudo) {
  fs.writeFileSync(path.join(raiz, nome), conteudo.trim() + '\n');
  console.log('✔', nome);
}

// Ícone isolado (favicon e redes sociais) — Figura 7 (d)
for (const [nome, v] of Object.entries(VARIANTES)) {
  const arquivo = nome === 'principal' ? 'logo-icone.svg' : `logo-icone-${nome}.svg`;
  salvar(arquivo, `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" role="img" aria-label="Arte na Cozinha">${cupcake(v)}</svg>`);
}

// Versão principal (vertical) — Figura 6
salvar('logo.svg', `
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 560 330" role="img" aria-label="Arte na Cozinha — Confeitaria">
  ${FONTES}
  <g transform="translate(190 0) scale(0.9)">${cupcake(VARIANTES.principal)}</g>
  <text x="280" y="258" text-anchor="middle" textLength="470" lengthAdjust="spacingAndGlyphs" font-family="Lora, Georgia, serif" font-style="italic" font-weight="700" font-size="58" fill="${COR.chocolate}">Arte na Cozinha</text>
  <text x="280" y="302" text-anchor="middle" font-family="Poppins, Arial, sans-serif" font-weight="500" font-size="17" letter-spacing="11" fill="${COR.framboesa}">CONFEITARIA</text>
</svg>`);

// Horizontal (cabeçalho), negativa (fundo rosa) e monocromática — Figura 7 (a), (b), (c)
const horizontal = (v, corNome, corSub, fundo) => `
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 200" role="img" aria-label="Arte na Cozinha — Confeitaria">
  ${FONTES}
  ${fundo ? `<rect width="640" height="200" rx="24" fill="${fundo}"/>` : ''}
  <g transform="translate(20 20) scale(0.8)">${cupcake(v)}</g>
  <text x="210" y="112" textLength="400" lengthAdjust="spacingAndGlyphs" font-family="Lora, Georgia, serif" font-style="italic" font-weight="700" font-size="60" fill="${corNome}">Arte na Cozinha</text>
  <text x="214" y="148" font-family="Poppins, Arial, sans-serif" font-weight="500" font-size="16" letter-spacing="10" fill="${corSub}">CONFEITARIA</text>
</svg>`;
salvar('logo-horizontal.svg', horizontal(VARIANTES.principal, COR.chocolate, COR.framboesa));
salvar('logo-negativa.svg',   horizontal(VARIANTES.negativa, '#FFFFFF', '#FFFFFF', COR.framboesa));
salvar('logo-mono.svg',       horizontal(VARIANTES.mono, COR.chocolate, COR.chocolate));

// ---------------------------------------------------------------------
// Imagens ilustrativas dos produtos (simulação, como nas Figuras 8 a 11)
// ---------------------------------------------------------------------
const PRODUTOS = [
  ['cupcake-red-velvet', '🧁', '❤️', COR.algodao],
  ['brigadeiro-gourmet', '🍫', '✨', '#F6D5DF'],
  ['bolo-chocolate',     '🎂', '🍫', COR.algodao],
  ['bolo-cenoura',       '🎂', '🥕', '#FBE0CF'],
  ['bolo-morango',       '🍰', '🍓', COR.algodao],
  ['torta-limao',        '🥧', '🍋', '#FBEBC8'],
  ['torta-holandesa',    '🥧', '🍫', '#F3DDD2'],
  ['cheesecake',         '🍰', '🍒', '#F8D0DE'],
  ['brownie',            '🍫', '🌰', '#EFD9D3'],
  ['macarons',           '🍪', '🎀', '#FADCE7'],
  ['trufas',             '🍬', '💝', '#F6D5DF'],
  ['donut',              '🍩', '💗', '#FADCE7'],
  ['bolo-pote-ninho',    '🍨', '🍓', COR.algodao],
  ['cafe',               '☕', '🤎', '#F1E0D6'],
  ['chocolate-quente',   '☕', '🍫', '#EFD9D3'],
  ['suco-laranja',       '🧃', '🍊', '#FBE7C9'],
  ['milkshake',          '🥤', '🍓', '#FADCE7'],
];

const EMOJI_FONT = `'Apple Color Emoji','Segoe UI Emoji','Noto Color Emoji',sans-serif`;
for (const [arquivo, principal, detalhe, fundo] of PRODUTOS) {
  salvar(`produtos/${arquivo}.svg`, `
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400" role="img" aria-label="Imagem ilustrativa">
  <defs>
    <radialGradient id="g" cx="50%" cy="42%" r="70%">
      <stop offset="0" stop-color="#FFFFFF" stop-opacity=".85"/>
      <stop offset=".55" stop-color="${fundo}"/>
      <stop offset="1" stop-color="${fundo}"/>
    </radialGradient>
  </defs>
  <rect width="400" height="400" fill="url(#g)"/>
  <g fill="${COR.framboesa}" opacity=".12">
    <circle cx="48" cy="60" r="10"/><circle cx="350" cy="44" r="7"/><circle cx="360" cy="330" r="12"/>
    <circle cx="36" cy="340" r="6"/><circle cx="320" cy="190" r="5"/><circle cx="70" cy="200" r="5"/>
  </g>
  <ellipse cx="200" cy="318" rx="120" ry="18" fill="${COR.chocolate}" opacity=".08"/>
  <text x="200" y="262" text-anchor="middle" font-size="190" font-family="${EMOJI_FONT}">${principal}</text>
  <text x="318" y="128" text-anchor="middle" font-size="70" font-family="${EMOJI_FONT}">${detalhe}</text>
</svg>`);
}
