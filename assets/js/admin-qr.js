/**
 * QR Code da rota de entrega (painel → detalhes do pedido)
 * Gera o QR no próprio navegador, com a biblioteca qrcode-generator (MIT),
 * sem enviar o endereço do cliente para nenhum serviço externo (LGPD).
 */
(() => {
  const alvo = document.getElementById('qrRota');
  if (!alvo || typeof qrcode === 'undefined') return;

  qrcode.stringToBytes = qrcode.stringToBytesFuncs['UTF-8'];
  const qr = qrcode(0, 'M'); // versão automática · correção de erro média (aguenta papel amassado)
  qr.addData(alvo.dataset.url);
  qr.make();
  alvo.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true, alt: 'QR Code da rota de entrega' });
})();
