// ==========================================================================
// Cari Tech Graphic — Modal + Popups + Chat + Partners + Admin link
// ==========================================================================

const { useState: useS4, useEffect: useE4, useRef: useR4 } = React;

// ---------- Generic Modal ----------
function Modal({ open, onClose, children, size = 'md' }) {
  useE4(() => {
    if (!open) return;
    const onKey = (e) => { if (e.key === 'Escape') onClose(); };
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', onKey);
    return () => {
      document.body.style.overflow = '';
      window.removeEventListener('keydown', onKey);
    };
  }, [open, onClose]);
  if (!open) return null;
  return (
    <div className="modal-backdrop" onClick={onClose}>
      <div className={`modal-shell modal-${size}`} onClick={(e) => e.stopPropagation()}>
        <button className="modal-close" onClick={onClose} aria-label="Fechar">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round">
            <line x1="6" y1="6" x2="18" y2="18" /><line x1="18" y1="6" x2="6" y2="18" />
          </svg>
        </button>
        {children}
      </div>
    </div>
  );
}

// ---------- Lead/Quote Form Modal ----------
function LeadModal({ open, onClose, t, prefilledService = '' }) {
  const [form, setForm] = useS4({ name: '', email: '', phone: '', service: '', message: '', website: '' });
  const [errors, setErrors] = useS4({});
  const [status, setStatus] = useS4('idle');
  const [feedback, setFeedback] = useS4('');

  useE4(() => {
    if (open) {
      setForm((f) => ({ ...f, service: prefilledService }));
      setStatus('idle');
      setErrors({});
      setFeedback('');
    }
  }, [open, prefilledService]);

  const update = (k, v) => {
    setForm((f) => ({ ...f, [k]: v }));
    setErrors((e) => ({ ...e, [k]: undefined }));
  };
  const validate = () => {
    const e = {};
    if (!form.name.trim()) e.name = t.contact.err_name;
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) e.email = t.contact.err_email;
    if (form.message.trim().length < 10) e.message = t.contact.err_message;
    setErrors(e);
    return Object.keys(e).length === 0;
  };
  const submit = async (ev) => {
    ev.preventDefault();
    if (!validate()) return;
    setStatus('sending');
    setFeedback('');
    try {
      const res = await window.sendLead(form);
      setStatus('sent');
      setFeedback(res.message || t.contact.success);
      setTimeout(() => { onClose(); setStatus('idle'); }, 3200);
    } catch (err) {
      setStatus('error');
      setFeedback('Não foi possível enviar agora. Continue pelo WhatsApp.');
    }
  };
  const abrirWhatsApp = () => window.open(window.buildWhatsAppLink(form), '_blank', 'noopener');

  return (
    <Modal open={open} onClose={onClose} size="lg">
      <div className="lead-modal-grid">
        <aside className="lead-modal-side">
          <div className="eyebrow" style={{ color: '#fff' }}>Cari Tech Graphic</div>
          <h3 className="h-section" style={{ color: '#fff', fontSize: '34px' }}>
            {prefilledService ? `Pedir orçamento — ${prefilledService}` : t.contact.title}
          </h3>
          <p style={{ color: 'rgba(255,255,255,0.78)', fontSize: 14, lineHeight: 1.6 }}>{t.contact.lede}</p>
          <ul className="lead-bullets">
            <li><Icon.Check size={14} />Resposta em &lt;24h</li>
            <li><Icon.Check size={14} />Orçamento sem compromisso</li>
            <li><Icon.Check size={14} />Equipa em Nampula</li>
          </ul>
          <div className="lead-foot">
            <Icon.Phone size={14} /> +258 87 987 7200
          </div>
        </aside>
        <form className="lead-modal-form" onSubmit={submit} noValidate>
          {status === 'sent' ? (
            <div className="lead-success">
              <div className="ls-icon"><Icon.Check size={28} /></div>
              <h4>{t.contact.success}</h4>
              <p>Vamos contactá-lo brevemente.</p>
            </div>
          ) : (
            <>
              <div className="cf-row">
                <div className={`field ${errors.name ? 'error' : ''}`}>
                  <label>{t.contact.name}</label>
                  <input value={form.name} onChange={(e) => update('name', e.target.value)} placeholder={t.contact.placeholder_name} />
                  <div className="field-error">{errors.name}</div>
                </div>
                <div className={`field ${errors.email ? 'error' : ''}`}>
                  <label>{t.contact.email}</label>
                  <input type="email" value={form.email} onChange={(e) => update('email', e.target.value)} placeholder={t.contact.placeholder_email} />
                  <div className="field-error">{errors.email}</div>
                </div>
              </div>
              <div className="cf-row">
                <div className="field">
                  <label>{t.contact.phone}</label>
                  <input value={form.phone} onChange={(e) => update('phone', e.target.value)} placeholder={t.contact.placeholder_phone} />
                  <div className="field-error" />
                </div>
                <div className="field">
                  <label>{t.contact.service}</label>
                  <select value={form.service} onChange={(e) => update('service', e.target.value)}>
                    <option value="">—</option>
                    {t.services.items.map((s, i) => <option key={i} value={s.t}>{s.t}</option>)}
                  </select>
                  <div className="field-error" />
                </div>
              </div>
              <div className={`field ${errors.message ? 'error' : ''}`}>
                <label>{t.contact.message}</label>
                <textarea rows="4" value={form.message} onChange={(e) => update('message', e.target.value)} placeholder={t.contact.placeholder_message} />
                <div className="field-error">{errors.message}</div>
              </div>
              {/* Honeypot anti-spam — invisível para humanos */}
              <input
                type="text" name="website" value={form.website}
                onChange={(e) => update('website', e.target.value)}
                tabIndex="-1" autoComplete="off" aria-hidden="true"
                style={{ position: 'absolute', left: '-9999px', width: 1, height: 1, opacity: 0 }}
              />
              <button type="submit" className="btn btn-accent cf-submit" disabled={status === 'sending'}>
                {status === 'sending' ? t.contact.sending : t.contact.send}
                <Icon.Arrow size={14} />
              </button>
              {feedback && (
                <p style={{ marginTop: 10, fontSize: 13, fontWeight: 600, color: status === 'error' ? 'var(--danger, #e5484d)' : 'var(--success, #30a46c)' }}>
                  {feedback}
                </p>
              )}
              {status === 'error' && (
                <button type="button" className="btn cf-submit" onClick={abrirWhatsApp} style={{
                  marginTop: 10, background: '#25D366', color: '#fff', display: 'inline-flex',
                  alignItems: 'center', gap: 8, justifyContent: 'center',
                }}>
                  <i className="fa-brands fa-whatsapp" aria-hidden="true" /> Continuar no WhatsApp
                </button>
              )}
            </>
          )}
        </form>
      </div>
    </Modal>
  );
}

// ---------- Service Detail Modal ----------
function ServiceModal({ open, onClose, service, onRequest, t }) {
  if (!service) return null;
  return (
    <Modal open={open} onClose={onClose} size="md">
      <div className="detail-modal">
        <div className="detail-cover" style={{ background: 'linear-gradient(135deg, var(--brand-deep), var(--brand-mid))' }}>
          <div className="detail-num">{service.n}</div>
          <h3>{service.t}</h3>
        </div>
        <div className="detail-body">
          <p className="lede">{service.d}</p>
          <div className="detail-feats">
            <div className="eyebrow">O que está incluído</div>
            <ul className="detail-bullets">
              <li><Icon.Check size={14} />Briefing inicial e descoberta</li>
              <li><Icon.Check size={14} />Conceito + 2 rondas de revisão</li>
              <li><Icon.Check size={14} />Entregáveis em formato editável</li>
              <li><Icon.Check size={14} />Suporte pós-entrega de 30 dias</li>
            </ul>
          </div>
          <div className="detail-meta">
            <div><span>Prazo médio</span><strong>2–6 semanas</strong></div>
            <div><span>Modalidade</span><strong>Projecto / mensal</strong></div>
            <div><span>Equipa dedicada</span><strong>3–5 pessoas</strong></div>
          </div>
          <div className="detail-actions">
            <button className="btn btn-accent" onClick={() => { onClose(); onRequest(service.t); }}>
              Pedir Serviço<Icon.Arrow size={14} />
            </button>
            <button className="btn btn-ghost" onClick={onClose}>Voltar</button>
          </div>
        </div>
      </div>
    </Modal>
  );
}

// ---------- Portfolio Detail Modal ----------
function PortfolioModal({ open, onClose, item, onRequest }) {
  if (!item) return null;
  return (
    <Modal open={open} onClose={onClose} size="lg">
      <div className="detail-modal">
        <div className="detail-cover detail-cover-portfolio">
          {item.img
            ? <img src={item.img} alt={item.t} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
            : <div className="placeholder-img">{item.tag} · {item.t}</div>}
        </div>
        <div className="detail-body">
          <span className="pm-tag" style={{ background: 'var(--accent)' }}>{item.tag}</span>
          <h3 className="h-section" style={{ fontSize: 32, marginTop: 8 }}>{item.t}</h3>
          <p className="lede">{item.c}</p>
          {item.url && (
            <div className="site-preview">
              <div className="site-preview-bar">
                <span className="spb-dot" /><span className="spb-dot" /><span className="spb-dot" />
                <span className="spb-url">{item.url.replace(/^https?:\/\//, '')}</span>
              </div>
              <iframe src={item.url} title={item.t} loading="lazy" referrerPolicy="no-referrer"
                sandbox="allow-scripts allow-same-origin allow-popups" />
              <div className="site-preview-note">Se a pré-visualização não abrir, o site pode bloquear a incorporação — use o botão abaixo.</div>
            </div>
          )}
          {!item.url && (
            <p style={{ color: 'var(--ink-soft)', fontSize: 14 }}>
              Estudo de caso: estratégia, design e desenvolvimento com foco em resultados de negócio reais.
            </p>
          )}
          <div className="detail-actions">
            {item.url && (
              <a className="btn btn-accent" href={item.url} target="_blank" rel="noreferrer noopener">
                Visitar site<Icon.ArrowUpRight size={14} />
              </a>
            )}
            <button className={item.url ? 'btn btn-ghost' : 'btn btn-accent'} onClick={() => { onClose(); onRequest(''); }}>
              Quero algo assim<Icon.Arrow size={14} />
            </button>
            <button className="btn btn-ghost" onClick={onClose}>Fechar</button>
          </div>
        </div>
      </div>
    </Modal>
  );
}

// ---------- Partners Carousel ----------
const PARTNERS = [
  { name: 'Horizonte', glyph: '◇' },
  { name: 'Mutuanha', glyph: '✦' },
  { name: 'Naparama', glyph: '☼' },
  { name: 'Quelimane+', glyph: '◉' },
  { name: 'Macuti', glyph: '⬢' },
  { name: 'TechMoz', glyph: '⌬' },
  { name: 'Nampula Lab', glyph: '◈' },
  { name: 'Rovuma Co.', glyph: '✺' },
];

function Partners({ t }) {
  const ref = window.useReveal();
  const loop = [...PARTNERS, ...PARTNERS];
  return (
    <section className="section partners" ref={ref} data-screen-label="Partners">
      <div className="container partners-head">
        <div className="eyebrow reveal">Parceiros & Clientes</div>
        <h2 className="h-section reveal delay-1">Marcas que confiam no nosso trabalho</h2>
      </div>
      <div className="partners-carousel reveal delay-2">
        <div className="partners-track">
          {loop.map((p, i) => (
            <div className="partner-logo" key={i}>
              <span className="partner-glyph">{p.glyph}</span>
              <span className="partner-name">{p.name}</span>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

// ---------- Chat Assistant FAB ----------
function ChatAssistant() {
  const [open, setOpen] = useS4(false);
  const [msgs, setMsgs] = useS4([
    { role: 'assistant', text: 'Olá! Sou o assistente da Cari Tech. Em que posso ajudar?' },
  ]);
  const [input, setInput] = useS4('');
  const [busy, setBusy] = useS4(false);
  const scrollRef = useR4(null);

  useE4(() => {
    if (scrollRef.current) scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
  }, [msgs, busy, open]);

  const send = async () => {
    const q = input.trim();
    if (!q || busy) return;
    setInput('');
    setMsgs((m) => [...m, { role: 'user', text: q }]);
    setBusy(true);
    try {
      // Se o assistente com IA estiver disponível (ambiente Claude), usa-o.
      if (window.claude && typeof window.claude.complete === 'function') {
        const sys = `Tu és o assistente virtual da Cari Tech Graphic — um estúdio de design, marketing e tecnologia em Nampula, Moçambique. Serviços: Design Gráfico, Desenvolvimento Web, Marketing Digital, Branding, Redes Sociais, Soluções com IA. Contactos: contacto@caritechgraphic.com, +258 87 987 7200, WhatsApp 83 415 7731. Responde em português de Moçambique, sé curto, simpático e útil. Se não souberes, sugere falar com a equipa.`;
        const reply = await window.claude.complete({
          messages: [{ role: 'user', content: `${sys}\n\nPergunta do utilizador: ${q}` }],
        });
        setMsgs((m) => [...m, { role: 'assistant', text: reply || respostaLocal(q) }]);
      } else {
        // Site publicado (ex.: Hostinger): resposta local + encaminhamento humano.
        await new Promise((r) => setTimeout(r, 500));
        setMsgs((m) => [...m, { role: 'assistant', text: respostaLocal(q), wa: true }]);
      }
    } catch (err) {
      setMsgs((m) => [...m, { role: 'assistant', text: respostaLocal(q), wa: true }]);
    } finally {
      setBusy(false);
    }
  };

  // Respostas úteis sem depender de API externa.
  const respostaLocal = (q) => {
    const s = q.toLowerCase();
    if (/(preço|preco|orçament|orcament|custa|quanto|valor)/.test(s))
      return 'Os valores dependem do âmbito de cada projecto. Deixe-nos o seu contacto pelo formulário "Pedir orçamento" ou fale connosco no WhatsApp que respondemos em menos de 24h.';
    if (/(serviç|servic|fazem|oferec|design|web|marketing|branding|logo|site)/.test(s))
      return 'Fazemos Design Gráfico, Desenvolvimento Web, Marketing Digital, Branding, Gestão de Redes Sociais e Soluções com IA. Sobre qual gostaria de saber mais?';
    if (/(prazo|tempo|demora|quando)/.test(s))
      return 'Os prazos variam entre 2 e 6 semanas conforme o projecto. Podemos dar-lhe uma estimativa precisa após um breve briefing.';
    if (/(contact|telefone|email|falar|whatsapp|onde)/.test(s))
      return 'Pode falar connosco pelo WhatsApp 83 415 7731, por e-mail contacto@caritechgraphic.com ou ligar para +258 87 987 7200. Estamos em Nampula, Moçambique.';
    return 'Boa pergunta! Para lhe responder com detalhe, o melhor é falar directamente com a nossa equipa. Toque no botão abaixo para continuar no WhatsApp.';
  };

  const abrirWhatsApp = () => {
    const num = (window.SITE_CONFIG && window.SITE_CONFIG.whatsapp) || '258834157731';
    window.open(`https://wa.me/${num}`, '_blank', 'noopener');
  };

  return (
    <>
      <button
        className={`chat-fab ${open ? 'open' : ''}`}
        onClick={() => setOpen(!open)}
        aria-label="Chat assistente"
      >
        {open ? (
          <i className="fa-solid fa-xmark" style={{ fontSize: 22, lineHeight: 1, color: 'currentColor' }} aria-hidden="true" />
        ) : (
          <i className="fa-solid fa-comment-dots" style={{ fontSize: 24, lineHeight: 1, color: 'currentColor' }} aria-hidden="true" />
        )}
        {!open && <span className="chat-fab-pulse" />}
      </button>
      <div className={`chat-panel ${open ? 'show' : ''}`} role="dialog" aria-label="Chat">
        <div className="chat-header">
          <div className="chat-avatar">
            <i className="fa-solid fa-robot" style={{ fontSize: 16, color: '#fff' }} aria-hidden="true" />
          </div>
          <div>
            <div className="chat-title">Assistente Cari Tech</div>
            <div className="chat-status"><span className="chat-dot" /> Online · IA</div>
          </div>
        </div>
        <div className="chat-body" ref={scrollRef}>
          {msgs.map((m, i) => (
            <div key={i} className={`chat-msg chat-${m.role}`}>
              <div className="chat-bubble">
                {m.text}
                {m.wa && (
                  <button
                    type="button"
                    onClick={abrirWhatsApp}
                    style={{
                      marginTop: 10, background: '#25D366', color: '#fff', border: 'none',
                      borderRadius: 8, padding: '8px 12px', fontWeight: 600, fontSize: 13,
                      display: 'inline-flex', alignItems: 'center', gap: 6, cursor: 'pointer',
                    }}
                  >
                    <i className="fa-brands fa-whatsapp" aria-hidden="true" /> Falar no WhatsApp
                  </button>
                )}
              </div>
            </div>
          ))}
          {busy && (
            <div className="chat-msg chat-assistant">
              <div className="chat-bubble chat-typing"><span /><span /><span /></div>
            </div>
          )}
        </div>
        <form
          className="chat-input"
          onSubmit={(e) => { e.preventDefault(); send(); }}
        >
          <input
            value={input}
            onChange={(e) => setInput(e.target.value)}
            placeholder="Escreva a sua pergunta…"
            disabled={busy}
          />
          <button type="submit" disabled={busy || !input.trim()} aria-label="Enviar">
            <Icon.Arrow size={16} />
          </button>
        </form>
      </div>
    </>
  );
}

// ---------- Carrinho / Checkout de serviços ----------
function CartFab({ count, onOpen }) {
  // Sempre visível para o cliente encontrar o pedido/checkout facilmente.
  return (
    <button className={`cart-fab ${count ? 'has-items' : ''}`} onClick={onOpen} aria-label="Ver pedido">
      <i className="fa-solid fa-cart-shopping" style={{ fontSize: 20 }} aria-hidden="true" />
      <span className="cart-fab-label">Pedido</span>
      {count > 0 && <span className="cart-count">{count}</span>}
    </button>
  );
}

// ---------- Popup: descrição do que o cliente quer (ao adicionar ao pedido) ----------
function ServiceDescModal({ open, onClose, service, onConfirm }) {
  const [desc, setDesc] = useS4('');
  const [error, setError] = useS4('');
  useE4(() => { if (open) { setDesc(''); setError(''); } }, [open, service]);
  const confirmar = () => {
    if (desc.trim().length < 5) { setError('Descreva em poucas palavras o que precisa.'); return; }
    onConfirm(desc.trim());
  };
  return (
    <Modal open={open} onClose={onClose} size="sm">
      <div className="desc-modal">
        <div className="eyebrow" style={{ color: 'var(--accent)' }}>Adicionar ao pedido</div>
        <h3 className="h-section" style={{ fontSize: 24, marginTop: 6 }}>{service}</h3>
        <p style={{ color: 'var(--ink-muted)', fontSize: 14, marginTop: 6 }}>
          O que precisa exactamente para este serviço? Quanto mais detalhe, melhor a proposta.
        </p>
        <div className={`field ${error ? 'error' : ''}`} style={{ marginTop: 10 }}>
          <label>Descrição</label>
          <textarea
            rows="4" value={desc} autoFocus
            onChange={(e) => { setDesc(e.target.value); setError(''); }}
            placeholder="Ex.: Preciso de um logótipo moderno em tons de laranja, com variante clara e escura…"
          />
          <div className="field-error">{error}</div>
        </div>
        <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', marginTop: 6 }}>
          <button type="button" className="btn btn-accent" onClick={confirmar}>
            Adicionar ao pedido<Icon.Arrow size={14} />
          </button>
          <button type="button" className="btn btn-ghost" onClick={onClose}>Cancelar</button>
        </div>
      </div>
    </Modal>
  );
}

// ---------- Pagamento automático (MozPayment: M-Pesa / e-Mola) ----------
function GatewayPay({ leadId, email, codigo, gateway }) {
  const disponiveis = [];
  if (gateway && gateway.mpesa) disponiveis.push('mpesa');
  if (gateway && gateway.emola) disponiveis.push('emola');
  const [metodo, setMetodo] = useS4(disponiveis[0] || 'mpesa');
  const [numero, setNumero] = useS4('');
  const [valor, setValor] = useS4('');
  const [status, setStatus] = useS4('idle'); // idle | sending | sent | error
  const [feedback, setFeedback] = useS4('');
  useE4(() => { if (disponiveis[0]) setMetodo(disponiveis[0]); }, [gateway]);
  if (!disponiveis.length) return null;

  const pagar = async (e) => {
    e.preventDefault();
    if (numero.replace(/\D/g, '').length < 9) { setFeedback('Indique um número de telemóvel válido.'); setStatus('error'); return; }
    const v = parseFloat(String(valor).replace(',', '.'));
    if (!v || v <= 0) { setFeedback('Indique o valor a pagar.'); setStatus('error'); return; }
    setStatus('sending'); setFeedback('Enviámos um pedido de confirmação para o seu telemóvel — aprove-o para concluir.');
    try {
      const res = await window.payGateway({ leadId, email, codigo, metodo, numero: numero.replace(/\D/g, ''), valor: v });
      setStatus('sent'); setFeedback(res.message || 'Pagamento confirmado! Obrigado.');
    } catch (err) {
      setStatus('error'); setFeedback(err.message || 'Não foi possível confirmar o pagamento.');
    }
  };

  if (status === 'sent') {
    return (
      <div className="gateway-pay">
        <div className="lead-success" style={{ padding: '10px 0' }}>
          <div className="ls-icon"><Icon.Check size={22} /></div><h4>{feedback}</h4>
        </div>
      </div>
    );
  }

  return (
    <div className="gateway-pay">
      <div className="cart-pay-head"><i className="fa-solid fa-mobile-screen-button" aria-hidden="true" /> Pagar agora (automático)</div>
      <form onSubmit={pagar}>
        {disponiveis.length > 1 && (
          <div className="gw-methods">
            {disponiveis.map((m) => (
              <label key={m} className={`gw-method ${metodo === m ? 'active' : ''}`}>
                <input type="radio" name="gw-metodo" value={m} checked={metodo === m} onChange={() => setMetodo(m)} />
                {m === 'mpesa' ? 'M-Pesa' : 'e-Mola'}
              </label>
            ))}
          </div>
        )}
        <div className="cf-row">
          <div className="field"><label>Número {metodo === 'mpesa' ? 'M-Pesa' : 'e-Mola'}</label><input value={numero} onChange={(e) => setNumero(e.target.value)} placeholder="84 000 0000" /><div className="field-error" /></div>
          <div className="field"><label>Valor (MT)</label><input value={valor} onChange={(e) => setValor(e.target.value)} placeholder="1000" inputMode="decimal" /><div className="field-error" /></div>
        </div>
        {feedback && <p style={{ fontSize: 13, fontWeight: 600, color: status === 'error' ? 'var(--danger,#e5484d)' : 'var(--ink-soft)' }}>{feedback}</p>}
        <button type="submit" className="btn btn-accent" disabled={status === 'sending'} style={{ marginTop: 4 }}>
          {status === 'sending' ? 'A confirmar…' : `Pagar com ${metodo === 'mpesa' ? 'M-Pesa' : 'e-Mola'}`}<Icon.Arrow size={14} />
        </button>
      </form>
    </div>
  );
}

function CartModal({ open, onClose, items, onRemove, onClear }) {
  const [form, setForm] = useS4({ name: '', email: '', phone: '' });
  const [status, setStatus] = useS4('idle');
  const [feedback, setFeedback] = useS4('');
  const [pay, setPay] = useS4({});
  const [gateway, setGateway] = useS4({});
  const [order, setOrder] = useS4(null); // { leadId, codigo, email } depois de enviar
  useE4(() => {
    if (open) { setStatus('idle'); setFeedback(''); setOrder(null); }
    window.loadContent().then((c) => {
      if (c && c.payments) setPay(c.payments);
      if (c && c.gateway) setGateway(c.gateway);
    });
  }, [open]);
  const hasPay = pay && pay.enabled && (pay.mpesa || pay.emola || pay.bank || pay.onlineLink);
  const resumo = (it) => it.service + (it.desc ? ' — ' + it.desc : '');

  const submit = async (e) => {
    e.preventDefault();
    if (!form.name.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
      setFeedback('Preencha o nome e um e-mail válido.'); return;
    }
    setStatus('sending'); setFeedback('');
    const msg = 'Pedido de serviços:\n- ' + items.map(resumo).join('\n- ');
    try {
      const res = await window.sendLead({ name: form.name, email: form.email, phone: form.phone, service: (items[0] && items[0].service) || 'Vários serviços', message: msg });
      setStatus('sent'); setFeedback(res.message || 'Pedido enviado! Entraremos em contacto em breve.');
      if (res.leadId && res.codigo) setOrder({ leadId: res.leadId, codigo: res.codigo, email: form.email });
      onClear();
    } catch (err) {
      setStatus('error'); setFeedback('Não foi possível enviar agora. Continue pelo WhatsApp.');
    }
  };
  const wa = () => window.open(window.buildWhatsAppLink({
    name: form.name, email: form.email, phone: form.phone,
    service: 'Vários serviços', message: 'Pedido de serviços: ' + items.map(resumo).join(', '),
  }), '_blank', 'noopener');

  return (
    <Modal open={open} onClose={onClose} size="md">
      <div className="cart-modal">
        <div className="eyebrow" style={{ color: 'var(--accent)' }}>Pedido de serviços</div>
        <h3 className="h-section" style={{ fontSize: 28, marginTop: 6 }}>Finalizar pedido</h3>
        {items.length === 0 && !order ? (
          <p style={{ color: 'var(--ink-muted)', marginTop: 12 }}>
            O seu pedido está vazio. Explore os serviços e clique em <strong>“Adicionar ao pedido”</strong>.
          </p>
        ) : (
          <>
            {!order && (
              <ul className="cart-list">
                {items.map((it) => (
                  <li key={it.id}>
                    <Icon.Check size={14} />
                    <div className="cart-item-info">
                      <span>{it.service}</span>
                      {it.desc && <div className="cart-item-desc">{it.desc}</div>}
                    </div>
                    <button type="button" onClick={() => onRemove(it.id)} aria-label="Remover">
                      <i className="fa-solid fa-xmark" aria-hidden="true" />
                    </button>
                  </li>
                ))}
              </ul>
            )}
            {status === 'sent' ? (
              <div className="lead-success"><div className="ls-icon"><Icon.Check size={28} /></div><h4>{feedback}</h4></div>
            ) : (
              <form className="cart-form" onSubmit={submit}>
                <div className="cf-row">
                  <div className="field"><label>Nome</label><input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="O seu nome" /><div className="field-error" /></div>
                  <div className="field"><label>E-mail</label><input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} placeholder="voce@email.com" /><div className="field-error" /></div>
                </div>
                <div className="field"><label>Telefone (opcional)</label><input value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} placeholder="+258 …" /><div className="field-error" /></div>
                {feedback && <p style={{ fontSize: 13, fontWeight: 600, color: status === 'error' ? 'var(--danger,#e5484d)' : 'var(--ink-soft)' }}>{feedback}</p>}
                <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', marginTop: 4 }}>
                  <button type="submit" className="btn btn-accent" disabled={status === 'sending'}>
                    {status === 'sending' ? 'A enviar…' : 'Enviar pedido'}<Icon.Arrow size={14} />
                  </button>
                  <button type="button" className="btn" onClick={wa} style={{ background: '#25D366', color: '#fff', display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                    <i className="fa-brands fa-whatsapp" aria-hidden="true" /> WhatsApp
                  </button>
                </div>
              </form>
            )}
            {order && <GatewayPay leadId={order.leadId} email={order.email} codigo={order.codigo} gateway={gateway} />}
            {hasPay && (
              <div className="cart-pay">
                <div className="cart-pay-head"><i className="fa-solid fa-credit-card" aria-hidden="true" /> Formas de pagamento</div>
                {pay.note && <p className="cart-pay-note">{pay.note}</p>}
                <ul className="cart-pay-list">
                  {pay.mpesa && <li><strong>M-Pesa</strong><span>{pay.mpesa}</span></li>}
                  {pay.emola && <li><strong>e-Mola</strong><span>{pay.emola}</span></li>}
                  {pay.bank && <li><strong>Transferência</strong><span>{pay.bank}</span></li>}
                </ul>
                {pay.onlineLink && (
                  <a className="btn btn-accent" href={pay.onlineLink} target="_blank" rel="noreferrer noopener" style={{ marginTop: 4, display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                    <i className="fa-solid fa-lock" aria-hidden="true" /> Pagar online
                  </a>
                )}
              </div>
            )}
          </>
        )}
      </div>
    </Modal>
  );
}

// ---------- Nossos Sites & Sistemas (geridos no painel) ----------
function SitesFeitos({ t }) {
  const [sites, setSites] = useS4([]);
  const ref = window.useReveal();
  useE4(() => {
    window.loadContent().then((c) => { if (c && Array.isArray(c.sites)) setSites(c.sites); });
  }, []);
  if (!sites.length) return null;
  const ts = (t && t.sites) || {};
  const host = (u) => { try { return new URL(u).hostname.replace(/^www\./, ''); } catch (e) { return u; } };
  return (
    <section id="trabalhos" className="section sites-section" ref={ref}>
      <div className="container">
        <div className="services-head reveal" style={{ marginBottom: 32 }}>
          <div>
            <div className="eyebrow">{ts.eyebrow || 'Nossos sites & sistemas'}</div>
            <h2 className="h-section">{ts.title || 'Trabalhos publicados'}</h2>
          </div>
          <p style={{ color: 'var(--ink-muted)', maxWidth: 420 }}>{ts.lede || 'Alguns dos sites e sistemas que criámos — veja-os ao vivo.'}</p>
        </div>
        <div className="sites-grid">
          {sites.map((s) => (
            <article className="site-card reveal" key={s.id || s.url}>
              <a className="site-preview" href={s.url} target="_blank" rel="noreferrer noopener">
                <div className="site-preview-bar">
                  <span className="spb-dot" /><span className="spb-dot" /><span className="spb-dot" />
                  <span className="spb-url">{host(s.url)}</span>
                </div>
                <div className="site-preview-frame">
                  <iframe src={s.url} title={s.nome || host(s.url)} loading="lazy" scrolling="no"
                    sandbox="allow-scripts allow-same-origin allow-popups" tabIndex="-1" />
                  <span className="site-preview-shield" aria-hidden="true" />
                </div>
              </a>
              <div className="site-card-body">
                <h3>{s.nome || host(s.url)}</h3>
                {s.desc && <p>{s.desc}</p>}
                <a className="btn btn-ghost" href={s.url} target="_blank" rel="noreferrer noopener">
                  <i className="fa-solid fa-arrow-up-right-from-square" aria-hidden="true" /> {ts.visit || 'Visitar site'}
                </a>
              </div>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}

Object.assign(window, { Modal, LeadModal, ServiceModal, PortfolioModal, Partners, ChatAssistant, CartFab, CartModal, ServiceDescModal, GatewayPay, SitesFeitos });
