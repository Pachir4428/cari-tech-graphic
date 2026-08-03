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

/* Carrega o conteúdo gerido no painel (Serviços e Portfólio) a partir de
 * conteudo.php. É buscado uma só vez (cache). Se o servidor não estiver
 * disponível (ex.: sem PHP), devolve arrays vazios e o site usa os textos
 * padrão do i18n. */
window.loadContent = function () {
  if (window.__contentPromise) return window.__contentPromise;
  window.__contentPromise = fetch('conteudo.php', { headers: { 'Accept': 'application/json' } })
    .then((r) => (r.ok ? r.json() : {}))
    .then((d) => {
      // Contactos geridos no painel passam a valer para os formulários e o chat.
      const contact = (d && d.contact) || {};
      if (contact.whatsapp) window.SITE_CONFIG.whatsapp = String(contact.whatsapp).replace(/\D/g, '');
      if (contact.email) window.SITE_CONFIG.email = contact.email;
      if (contact.phone) window.SITE_CONFIG.phone = contact.phone;
      // Logótipo definido no painel passa a ser também o ícone (favicon) do site.
      const branding = (d && d.branding) || {};
      const fav = branding.light || branding.dark;
      if (fav) {
        let link = document.querySelector('link[rel~="icon"]');
        if (!link) { link = document.createElement('link'); link.rel = 'icon'; document.head.appendChild(link); }
        link.href = fav;
      }
      return {
        services: (d && d.services) || [],
        portfolio: (d && d.portfolio) || [],
        testimonials: (d && d.testimonials) || [],
        headings: (d && d.headings) || {},
        contact,
        branding: (d && d.branding) || {},
        payments: (d && d.payments) || {},
        gateway: (d && d.gateway) || { mpesa: false, emola: false },
        sites: (d && d.sites) || [],
      };
    })
    .catch(() => ({ services: [], portfolio: [], testimonials: [], headings: {}, contact: {}, branding: {}, payments: {}, gateway: { mpesa: false, emola: false }, sites: [] }));
  return window.__contentPromise;
};

/* Pede à MozPayment (via pagamento.php) para cobrar o valor por M-Pesa/e-Mola.
 * Requer o leadId + email + código do pedido (devolvidos por sendLead).
 * Devolve { ok, message }. */
window.payGateway = async function (data) {
  const res = await fetch('pagamento.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify(data),
  });
  let payload = {};
  try { payload = await res.json(); } catch (e) { /* resposta não-JSON */ }
  if (!res.ok || !payload.ok) {
    throw new Error((payload && payload.message) || `Falha no pagamento (HTTP ${res.status})`);
  }
  return payload;
};

/* Envia um anexo (brief/referência) de um pedido já criado. Requer o
 * leadId + email + código devolvidos por sendLead. Devolve { ok, message }. */
window.uploadClienteFicheiro = async function (file, { leadId, email, codigo }) {
  const form = new FormData();
  form.append('leadId', leadId);
  form.append('email', email);
  form.append('codigo', codigo);
  form.append('file', file);
  const res = await fetch('cliente-api.php?action=upload', { method: 'POST', body: form });
  let payload = {};
  try { payload = await res.json(); } catch (e) { /* resposta não-JSON */ }
  if (!res.ok || !payload.ok) {
    throw new Error((payload && payload.message) || `Falha no envio do anexo (HTTP ${res.status})`);
  }
  return payload;
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
