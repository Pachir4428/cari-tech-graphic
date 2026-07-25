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
  const [form, setForm] = useS4({ name: '', email: '', phone: '', service: '', message: '' });
  const [errors, setErrors] = useS4({});
  const [status, setStatus] = useS4('idle');

  useE4(() => {
    if (open) {
      setForm((f) => ({ ...f, service: prefilledService }));
      setStatus('idle');
      setErrors({});
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
  const submit = (ev) => {
    ev.preventDefault();
    if (!validate()) return;
    setStatus('sending');
    setTimeout(() => {
      setStatus('sent');
      setTimeout(() => { onClose(); setStatus('idle'); }, 2400);
    }, 1100);
  };

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
              <button type="submit" className="btn btn-accent cf-submit" disabled={status === 'sending'}>
                {status === 'sending' ? t.contact.sending : t.contact.send}
                <Icon.Arrow size={14} />
              </button>
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
          <div className="placeholder-img">{item.tag} · {item.t}</div>
        </div>
        <div className="detail-body">
          <span className="pm-tag" style={{ background: 'var(--accent)' }}>{item.tag}</span>
          <h3 className="h-section" style={{ fontSize: 32, marginTop: 8 }}>{item.t}</h3>
          <p className="lede">{item.c}</p>
          <div className="detail-meta">
            <div><span>Cliente</span><strong>{item.t}</strong></div>
            <div><span>Ano</span><strong>2025</strong></div>
            <div><span>Categoria</span><strong>{item.tag}</strong></div>
            <div><span>Duração</span><strong>6 semanas</strong></div>
          </div>
          <p style={{ color: 'var(--ink-soft)', fontSize: 14 }}>
            Estudo de caso completo: estratégia, design, desenvolvimento e resultados mensuráveis.
            Cada projecto é construído com foco em métricas de negócio reais.
          </p>
          <div className="detail-actions">
            <button className="btn btn-accent" onClick={() => { onClose(); onRequest(''); }}>
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
      const sys = `Tu és o assistente virtual da Cari Tech Graphic — um estúdio de design, marketing e tecnologia em Nampula, Moçambique. Serviços: Design Gráfico, Desenvolvimento Web, Marketing Digital, Branding, Redes Sociais, Soluções com IA. Contactos: contacto@caritechgraphic.com, +258 87 987 7200, WhatsApp 83 415 7731. Responde em português de Moçambique, sé curto, simpático e útil. Se não souberes, sugere falar com a equipa.`;
      const reply = await window.claude.complete({
        messages: [
          { role: 'user', content: `${sys}\n\nPergunta do utilizador: ${q}` },
        ],
      });
      setMsgs((m) => [...m, { role: 'assistant', text: reply || 'Desculpe, não consegui processar agora. Tente outra vez ou contacte-nos.' }]);
    } catch (err) {
      setMsgs((m) => [...m, { role: 'assistant', text: 'Tive um problema a contactar o serviço. Pode tentar novamente, ou ligar para +258 87 987 7200.' }]);
    } finally {
      setBusy(false);
    }
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
              <div className="chat-bubble">{m.text}</div>
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

Object.assign(window, { Modal, LeadModal, ServiceModal, PortfolioModal, Partners, ChatAssistant });
