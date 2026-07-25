/* ==========================================================================
 * Cari Tech Graphic — Configuração central do site (frontend)
 * --------------------------------------------------------------------------
 * Altere aqui os contactos e o endpoint. Estes valores são usados pelos
 * formulários (Contacto / Orçamento), pelo botão de WhatsApp e pelo chat.
 * ========================================================================== */
window.SITE_CONFIG = {
  // Número de WhatsApp em formato internacional, só dígitos (sem +, espaços ou -)
  whatsapp: '258834157731',

  // E-mail principal do estúdio (mostrado no site)
  email: 'contacto@caritechgraphic.com',

  // Telefone de apresentação
  phone: '+258 87 987 7200',

  // Endpoint que recebe os formulários no servidor (PHP na Hostinger).
  // Deixe 'enviar.php' para hospedagem partilhada. Se usar outro backend,
  // aponte para o URL completo (ex.: 'https://api.seudominio.com/lead').
  endpoint: 'enviar.php',
};

/* Monta um link wa.me com mensagem pré-preenchida a partir dos dados do form. */
window.buildWhatsAppLink = function (data) {
  data = data || {};
  const num = (window.SITE_CONFIG && window.SITE_CONFIG.whatsapp) || '258834157731';
  const linhas = [
    'Olá Cari Tech Graphic! Gostaria de um orçamento.',
    data.name ? `\nNome: ${data.name}` : '',
    data.service ? `Serviço: ${data.service}` : '',
    data.phone ? `Telefone: ${data.phone}` : '',
    data.email ? `E-mail: ${data.email}` : '',
    data.message ? `\nMensagem: ${data.message}` : '',
  ].filter(Boolean).join('\n');
  return `https://wa.me/${num}?text=${encodeURIComponent(linhas)}`;
};

/* Envia os dados do formulário para o servidor. Devolve { ok, message }.
 * Se o endpoint não existir (ex.: aberto por file:// ou ambiente sem PHP),
 * o chamador deve oferecer o WhatsApp como alternativa. */
window.sendLead = async function (data) {
  const endpoint = (window.SITE_CONFIG && window.SITE_CONFIG.endpoint) || 'enviar.php';
  const res = await fetch(endpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify(data),
  });
  let payload = {};
  try { payload = await res.json(); } catch (e) { /* resposta não-JSON */ }
  if (!res.ok || !payload.ok) {
    throw new Error((payload && payload.message) || `Falha no envio (HTTP ${res.status})`);
  }
  return payload;
};
