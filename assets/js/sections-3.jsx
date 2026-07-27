// ==========================================================================
// Cari Tech Graphic — Sections part 3 (Contact, Footer, Header, WhatsApp)
// ==========================================================================

const { useState: useState3, useEffect: useEffect3, useRef: useRef3 } = React;

// ---------- Contactos geridos no painel (com fallback para os valores padrão) ----------
function useContact() {
  const [c, setC] = useState3({});
  useEffect3(() => {
    window.loadContent().then((d) => { if (d && d.contact) setC(d.contact); });
  }, []);
  return c;
}
const CONTACT_DEFAULT = { email: 'contacto@caritechgraphic.com', phone: '+258 87 987 7200', whatsapp: '258834157731' };
const telHref = (phone) => 'tel:+' + String(phone || '').replace(/[^\d]/g, '');
const waHref = (num) => 'https://wa.me/' + String(num || '').replace(/\D/g, '');

// Logótipo gerido no painel (imagem) — com fallback para o logótipo SVG padrão.
function useBranding() {
  const [b, setB] = useState3({});
  useEffect3(() => {
    window.loadContent().then((d) => { if (d && d.branding) setB(d.branding); });
  }, []);
  return b;
}
// Segue o tema actual do site (claro/escuro) para escolher a versão do logótipo.
function useThemeMode() {
  const [mode, setMode] = useState3(document.documentElement.getAttribute('data-theme') || 'light');
  useEffect3(() => {
    const obs = new MutationObserver(() => setMode(document.documentElement.getAttribute('data-theme') || 'light'));
    obs.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    return () => obs.disconnect();
  }, []);
  return mode;
}
function LogoMark({ branding }) {
  const mode = useThemeMode();
  branding = branding || {};
  // Em fundo escuro usa o logótipo "escuro" (claro na cor); em fundo claro o "claro".
  const logo = mode === 'dark' ? (branding.dark || branding.light) : (branding.light || branding.dark);
  if (logo) {
    return <img src={logo} alt="Cari Tech Graphic" className="logo-img" style={{ height: 40, width: 'auto', display: 'block' }} />;
  }
  // Sem logótipo carregado: mostra apenas o nome do site (sem símbolo).
  return (
    <span className="logo-text logo-text-only">
      <span className="logo-name">Cari Tech Graphic</span>
    </span>
  );
}

// ---------- Header ----------
function Header({ t, lang, setLang, theme, setTheme, onStart }) {
  const [scrolled, setScrolled] = useState3(false);
  const [open, setOpen] = useState3(false);
  const branding = useBranding();

  useEffect3(() => {
    const onScroll = () => setScrolled(window.scrollY > 24);
    window.addEventListener('scroll', onScroll);
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  const links = [
    { id: 'home', label: t.nav.home, href: '#home' },
    { id: 'about', label: t.nav.about, href: 'sobre.html' },
    { id: 'services', label: t.nav.services, href: '#services' },
    { id: 'portfolio', label: t.nav.portfolio, href: '#portfolio' },
    { id: 'contact', label: t.nav.contact, href: '#contact' },
  ];

  return (
    <header className={`site-header ${scrolled ? 'scrolled' : ''} ${open ? 'nav-open' : ''}`}>
      {open && <div className="nav-scrim" onClick={() => setOpen(false)} aria-hidden="true" />}
      <div className="container site-header-inner">
        <a href="#home" className="logo" onClick={() => setOpen(false)}>
          <LogoMark branding={branding} />
        </a>
        <nav className={`site-nav ${open ? 'open' : ''}`}>
          {links.map((l) => (
            <a key={l.id} href={l.href} onClick={() => setOpen(false)}>{l.label}</a>
          ))}
          <a href="entrar.html" className="nav-login" onClick={() => setOpen(false)}>
            <i className="fa-solid fa-right-to-bracket" aria-hidden="true" style={{ marginRight: 8 }} />{t.nav.login}
          </a>
        </nav>
        <div className="site-actions">
          <button
            className="action-btn lang-toggle"
            onClick={() => setLang(lang === 'pt' ? 'en' : 'pt')}
            aria-label="Toggle language"
          >
            <Icon.Globe size={14} />
            <span>{lang.toUpperCase()}</span>
          </button>
          <button
            className="action-btn theme-toggle"
            onClick={() => setTheme(theme === 'light' ? 'dark' : 'light')}
            aria-label="Toggle theme"
          >
            {theme === 'light' ? <Icon.Moon size={16} /> : <Icon.Sun size={16} />}
          </button>
          <a href="entrar.html" className="action-btn login-btn" aria-label={t.nav.login} title={t.nav.login}>
            <i className="fa-solid fa-right-to-bracket" aria-hidden="true" />
            <span>{t.nav.login}</span>
          </a>
          <button type="button" className="btn btn-primary header-cta" onClick={() => onStart('')}>
            {t.nav.cta}<Icon.Arrow size={14} />
          </button>
          <button className="hamburger" onClick={() => setOpen(!open)} aria-label="Menu">
            <span /><span /><span />
          </button>
        </div>
      </div>
    </header>
  );
}

// ---------- Contact ----------
function Contact({ t }) {
  const ref = window.useReveal();
  const c = useContact();
  const email = c.email || CONTACT_DEFAULT.email;
  const phone = c.phone || CONTACT_DEFAULT.phone;
  const address = c.address || t.contact.address;
  const hours = c.hours || t.contact.hours;
  const [form, setForm] = useState3({ name: '', email: '', phone: '', service: '', message: '', website: '' });
  const [errors, setErrors] = useState3({});
  const [status, setStatus] = useState3('idle'); // idle | sending | sent | error
  const [feedback, setFeedback] = useState3('');

  function update(k, v) {
    setForm((f) => ({ ...f, [k]: v }));
    setErrors((e) => ({ ...e, [k]: undefined }));
  }

  function validate() {
    const e = {};
    if (!form.name.trim()) e.name = t.contact.err_name;
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) e.email = t.contact.err_email;
    if (form.message.trim().length < 10) e.message = t.contact.err_message;
    setErrors(e);
    return Object.keys(e).length === 0;
  }

  async function submit(ev) {
    ev.preventDefault();
    if (!validate()) return;
    setStatus('sending');
    setFeedback('');
    try {
      const res = await window.sendLead(form);
      setStatus('sent');
      setFeedback(res.message || t.contact.success);
      setForm({ name: '', email: '', phone: '', service: '', message: '', website: '' });
      setTimeout(() => setStatus('idle'), 6000);
    } catch (err) {
      // Servidor indisponível (ex.: PHP não configurado) — oferece WhatsApp
      setStatus('error');
      setFeedback('Não foi possível enviar agora. Fale connosco directamente pelo WhatsApp.');
    }
  }

  function abrirWhatsApp() {
    window.open(window.buildWhatsAppLink(form), '_blank', 'noopener');
  }

  return (
    <section className="section contact" id="contact" ref={ref} data-screen-label="09 Contact">
      <div className="container contact-grid">
        <div className="contact-info">
          <div className="eyebrow reveal">{t.contact.eyebrow}</div>
          <h2 className="h-section reveal delay-1">{t.contact.title}</h2>
          <p className="lede reveal delay-2">{t.contact.lede}</p>

          <div className="contact-cards reveal delay-3">
            <a className="contact-card" href={`mailto:${email}`}>
              <div className="cc-icon"><Icon.Mail size={20} /></div>
              <div>
                <div className="cc-lab">{t.contact.info_email}</div>
                <div className="cc-val">{email}</div>
              </div>
            </a>
            <a className="contact-card" href={telHref(phone)}>
              <div className="cc-icon"><Icon.Phone size={20} /></div>
              <div>
                <div className="cc-lab">{t.contact.info_phone}</div>
                <div className="cc-val">{phone}</div>
              </div>
            </a>
            <div className="contact-card">
              <div className="cc-icon"><Icon.MapPin size={20} /></div>
              <div>
                <div className="cc-lab">{t.contact.info_address}</div>
                <div className="cc-val">{address}</div>
                <div className="cc-hours">{hours}</div>
              </div>
            </div>
          </div>

          <div className="contact-map reveal delay-4">
            <div className="map-stub">
              <svg viewBox="0 0 400 200" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                <defs>
                  <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                    <path d="M 40 0 L 0 0 0 40" fill="none" stroke="var(--line)" strokeWidth="1"/>
                  </pattern>
                </defs>
                <rect width="400" height="200" fill="var(--bg-soft)"/>
                <rect width="400" height="200" fill="url(#grid)"/>
                <path d="M0 110 Q120 70 220 100 T400 80" stroke="var(--accent-2)" strokeWidth="2" fill="none" opacity="0.5"/>
                <path d="M50 0 L80 200 M180 0 L210 200 M280 0 L310 200" stroke="var(--line)" strokeWidth="1"/>
              </svg>
              <div className="map-pin">
                <div className="map-pin-dot" />
                <div className="map-pin-pulse" />
                <div className="map-pin-label">Bairro Mutuanha · Nampula</div>
              </div>
            </div>
          </div>
        </div>

        <form className="contact-form reveal delay-2" onSubmit={submit} noValidate>
          <div className="cf-row">
            <div className={`field ${errors.name ? 'error' : ''}`}>
              <label>{t.contact.name}</label>
              <input
                value={form.name}
                onChange={(e) => update('name', e.target.value)}
                placeholder={t.contact.placeholder_name}
              />
              <div className="field-error">{errors.name}</div>
            </div>
            <div className={`field ${errors.email ? 'error' : ''}`}>
              <label>{t.contact.email}</label>
              <input
                type="email"
                value={form.email}
                onChange={(e) => update('email', e.target.value)}
                placeholder={t.contact.placeholder_email}
              />
              <div className="field-error">{errors.email}</div>
            </div>
          </div>
          <div className="cf-row">
            <div className="field">
              <label>{t.contact.phone}</label>
              <input
                value={form.phone}
                onChange={(e) => update('phone', e.target.value)}
                placeholder={t.contact.placeholder_phone}
              />
              <div className="field-error" />
            </div>
            <div className="field">
              <label>{t.contact.service}</label>
              <select value={form.service} onChange={(e) => update('service', e.target.value)}>
                <option value="">—</option>
                {t.services.items.map((s, i) => (
                  <option key={i} value={s.t}>{s.t}</option>
                ))}
              </select>
              <div className="field-error" />
            </div>
          </div>
          <div className={`field ${errors.message ? 'error' : ''}`}>
            <label>{t.contact.message}</label>
            <textarea
              rows="5"
              value={form.message}
              onChange={(e) => update('message', e.target.value)}
              placeholder={t.contact.placeholder_message}
            />
            <div className="field-error">{errors.message}</div>
          </div>
          {/* Honeypot anti-spam — invisível para humanos */}
          <input
            type="text"
            name="website"
            value={form.website}
            onChange={(e) => update('website', e.target.value)}
            tabIndex="-1"
            autoComplete="off"
            aria-hidden="true"
            style={{ position: 'absolute', left: '-9999px', width: 1, height: 1, opacity: 0 }}
          />
          <button type="submit" className="btn btn-accent cf-submit" disabled={status === 'sending'}>
            {status === 'sending' ? t.contact.sending : status === 'sent' ? t.contact.success : t.contact.send}
            {status !== 'sent' && <Icon.Arrow size={14} />}
            {status === 'sent' && <Icon.Check size={16} />}
          </button>
          {feedback && (
            <p className={`cf-feedback ${status === 'error' ? 'error' : 'ok'}`} style={{
              marginTop: 12, fontSize: 13, fontWeight: 600,
              color: status === 'error' ? 'var(--danger, #e5484d)' : 'var(--success, #30a46c)',
            }}>
              {feedback}
            </p>
          )}
          {status === 'error' && (
            <button type="button" className="btn btn-whatsapp cf-submit" onClick={abrirWhatsApp} style={{
              marginTop: 10, background: '#25D366', color: '#fff', display: 'inline-flex',
              alignItems: 'center', gap: 8, justifyContent: 'center',
            }}>
              <i className="fa-brands fa-whatsapp" aria-hidden="true" /> Enviar pelo WhatsApp
            </button>
          )}
        </form>
      </div>
    </section>
  );
}

// ---------- Footer ----------
function Footer({ t }) {
  const c = useContact();
  const branding = useBranding();
  const email = c.email || CONTACT_DEFAULT.email;
  const phone = c.phone || CONTACT_DEFAULT.phone;
  const whatsapp = c.whatsapp || CONTACT_DEFAULT.whatsapp;
  const address = c.address || t.contact.address;
  return (
    <footer className="site-footer">
      <div className="container">
        <div className="footer-grid">
          <div className="footer-brand">
            <div className="logo">
              <LogoMark branding={branding} />
            </div>
            <p>{t.footer.tagline}</p>
            <div className="socials">
              <a href="#" aria-label="Instagram"><Icon.Instagram size={16} /></a>
              <a href="#" aria-label="Facebook"><Icon.Facebook size={16} /></a>
              <a href="#" aria-label="LinkedIn"><Icon.LinkedIn size={16} /></a>
            </div>
          </div>
          <div className="footer-col">
            <div className="fc-title">{t.footer.explore}</div>
            <a href="#home">{t.nav.home}</a>
            <a href="#about">{t.nav.about}</a>
            <a href="#portfolio">{t.nav.portfolio}</a>
            <a href="#testimonials">{t.testimonials.eyebrow}</a>
            <a href="cliente.html">{t.footer.clientArea || 'Área de Cliente'}</a>
          </div>
          <div className="footer-col">
            <div className="fc-title">{t.footer.services}</div>
            {t.services.items.slice(0, 5).map((s, i) => (
              <a key={i} href="#services">{s.t}</a>
            ))}
          </div>
          <div className="footer-col">
            <div className="fc-title">{t.footer.contact}</div>
            <a href={`mailto:${email}`}>{email}</a>
            <a href={telHref(phone)}>{phone}</a>
            <a href={waHref(whatsapp)} target="_blank" rel="noreferrer">WhatsApp</a>
            <div className="fc-addr">{address}</div>
          </div>
        </div>
        <div className="footer-bottom">
          <span>{t.footer.rights}</span>
          <span>{t.footer.built}</span>
        </div>
      </div>
    </footer>
  );
}

// ---------- WhatsApp floating ----------
function WhatsAppFab() {
  const [visible, setVisible] = useState3(false);
  const c = useContact();
  const whatsapp = c.whatsapp || CONTACT_DEFAULT.whatsapp;
  useEffect3(() => {
    const onScroll = () => setVisible(window.scrollY > 600);
    window.addEventListener('scroll', onScroll);
    return () => window.removeEventListener('scroll', onScroll);
  }, []);
  return (
    <a
      href={waHref(whatsapp)}
      target="_blank"
      rel="noreferrer"
      className={`whatsapp-fab ${visible ? 'show' : ''}`}
      aria-label="WhatsApp"
    >
      <Icon.WhatsApp size={26} />
      <span className="wf-pulse" />
    </a>
  );
}

Object.assign(window, { Header, Contact, Footer, WhatsAppFab });
