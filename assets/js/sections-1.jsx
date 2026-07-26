// ==========================================================================
// Cari Tech Graphic — Sections part 1 (Hero, Stats, About, Services)
// ==========================================================================

const { useState, useEffect, useRef, useMemo } = React;

// ---------- shared hooks ----------
function useReveal() {
  const ref = useRef(null);
  useEffect(() => {
    if (!ref.current) return;
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((e) => {
          if (e.isIntersecting) {
            e.target.classList.add('in');
            io.unobserve(e.target);
          }
        });
      },
      { threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
    );
    ref.current.querySelectorAll('.reveal').forEach((el) => io.observe(el));
    return () => io.disconnect();
  }, []);
  return ref;
}

function useCountUp(target, trigger) {
  const [val, setVal] = useState(0);
  useEffect(() => {
    if (!trigger) return;
    let raf, start;
    const dur = 1600;
    const step = (t) => {
      if (!start) start = t;
      const p = Math.min(1, (t - start) / dur);
      const eased = 1 - Math.pow(1 - p, 3);
      setVal(Math.round(target * eased));
      if (p < 1) raf = requestAnimationFrame(step);
    };
    raf = requestAnimationFrame(step);
    return () => cancelAnimationFrame(raf);
  }, [target, trigger]);
  return val;
}

// ---------- Hero variant: orbital ----------
function HeroOrbital({ t }) {
  return (
    <div className="hero-orbital">
      <div className="orbital-stage">
        <div className="orbital-blob orbital-blob-a" />
        <div className="orbital-blob orbital-blob-b" />
        <svg className="orbital-rings" viewBox="0 0 600 600" aria-hidden="true">
          <defs>
            <linearGradient id="ring-grad" x1="0" x2="1" y1="0" y2="1">
              <stop offset="0" stopColor="var(--accent)" stopOpacity="0.9" />
              <stop offset="1" stopColor="var(--accent-2)" stopOpacity="0.2" />
            </linearGradient>
          </defs>
          <circle cx="300" cy="300" r="240" fill="none" stroke="url(#ring-grad)" strokeWidth="1.5" strokeDasharray="2 6" opacity="0.6" />
          <circle cx="300" cy="300" r="180" fill="none" stroke="url(#ring-grad)" strokeWidth="1.5" opacity="0.5" />
          <circle cx="300" cy="300" r="120" fill="none" stroke="url(#ring-grad)" strokeWidth="1.5" strokeDasharray="4 4" opacity="0.4" />
        </svg>
        <div className="orbital-core">
          <img className="orbital-photo" src="uploads/owner-casual.png" alt="Pachir · Fundador da Cari Tech Graphic" />
        </div>
        <div className="orbital-card orbital-card-1">
          <div className="oc-dot" /><span>Live · ON-SITE</span>
        </div>
        <div className="orbital-card orbital-card-2">
          <div className="oc-num">98<span>%</span></div>
          <div className="oc-lab">Satisfação</div>
        </div>
        <div className="orbital-card orbital-card-3">
          <Icon.Sparkle size={16} />
          <span>IA integrada</span>
        </div>
      </div>
    </div>
  );
}

// ---------- Hero variant: split mosaic ----------
function HeroMosaic({ t }) {
  return (
    <div className="hero-mosaic">
      <div className="mosaic-tile mosaic-tile-1">
        <div className="placeholder-img">design · branding</div>
      </div>
      <div className="mosaic-tile mosaic-tile-2 mosaic-accent">
        <div className="mt-eyebrow">Live</div>
        <div className="mt-num">120+</div>
        <div className="mt-lab">projectos entregues</div>
      </div>
      <div className="mosaic-tile mosaic-tile-3">
        <div className="placeholder-img">desenvolvimento web</div>
      </div>
      <div className="mosaic-tile mosaic-tile-4">
        <div className="mt-icon-stack">
          <Icon.Brain size={28} />
        </div>
        <div className="mt-lab" style={{ marginTop: 'auto' }}>
          IA & Automação<br/>nos nossos fluxos
        </div>
      </div>
    </div>
  );
}

// ---------- Hero variant: minimal flowing ----------
function HeroMinimal({ t }) {
  return (
    <div className="hero-minimal">
      <svg className="hm-curve" viewBox="0 0 500 600" preserveAspectRatio="none" aria-hidden="true">
        <defs>
          <linearGradient id="hm-grad" x1="0" x2="1" y1="0" y2="1">
            <stop offset="0" stopColor="var(--accent)" />
            <stop offset="1" stopColor="var(--accent-2)" />
          </linearGradient>
        </defs>
        <path d="M0 100 Q250 50 500 200 Q300 400 400 600 L0 600 Z" fill="url(#hm-grad)" opacity="0.18" />
        <path d="M0 200 Q200 150 500 300 Q300 500 350 600 L0 600 Z" fill="var(--brand-deep)" opacity="0.06" />
      </svg>
      <div className="hm-stack">
        <div className="hm-image">
          <div className="placeholder-img">retrato · cliente</div>
        </div>
        <div className="hm-pill">
          <span className="hm-pill-dot" /> Cari Tech · 2026
        </div>
        <div className="hm-quote">
          <Icon.Quote size={20} />
          <span>“Mais rápidos, mais inteligentes, mais criativos.”</span>
        </div>
      </div>
    </div>
  );
}

// ---------- Hero ----------
function Hero({ t, heroStyle, onStart }) {
  const ref = useReveal();
  const Visual = heroStyle === 'mosaic' ? HeroMosaic : heroStyle === 'minimal' ? HeroMinimal : HeroOrbital;

  return (
    <section className="hero" id="home" ref={ref} data-screen-label="01 Hero">
      <div className="blob hero-blob-a" />
      <div className="blob hero-blob-b" />
      <div className="container hero-grid">
        <div className="hero-copy">
          <div className="eyebrow reveal">{t.hero.eyebrow}</div>
          <h1 className="h-display reveal delay-1">
            {t.hero.title_a}{' '}
            <span className="gradient-text">{t.hero.title_b}.</span>
          </h1>
          <p className="lede reveal delay-2">{t.hero.subtitle}</p>
          <div className="hero-cta reveal delay-3">
            <button type="button" className="btn btn-accent" onClick={() => onStart('')}>
              {t.hero.cta1}
              <Icon.Arrow size={16} />
            </button>
            <a href="#services" className="btn btn-ghost">
              <Icon.Play size={12} />
              {t.hero.cta2}
            </a>
          </div>
          <div className="hero-meta reveal delay-4">
            <div className="meta-pill"><span className="meta-dot live" />{t.hero.live}</div>
            <div className="meta-sep" />
            <div className="meta-text">{t.hero.badge1} · {t.hero.badge2}</div>
          </div>
        </div>
        <div className="hero-visual reveal delay-2">
          <Visual t={t} />
        </div>
      </div>
      <a href="#stats" className="hero-scroll" aria-label="Scroll">
        <span />
      </a>
    </section>
  );
}

// ---------- Stats ----------
function Stats({ t }) {
  const ref = useRef(null);
  const [trigger, setTrigger] = useState(false);
  useEffect(() => {
    if (!ref.current) return;
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((e) => {
          if (e.isIntersecting) {
            setTrigger(true);
            e.target.querySelectorAll('.reveal').forEach((el) => el.classList.add('in'));
            io.disconnect();
          }
        });
      },
      { threshold: 0.3 }
    );
    io.observe(ref.current);
    return () => io.disconnect();
  }, []);

  return (
    <section className="section stats" id="stats" ref={ref} data-screen-label="02 Stats">
      <div className="container">
        <div className="stats-head reveal">
          <div className="eyebrow">{t.stats.eyebrow}</div>
          <h2 className="h-section">{t.stats.title}</h2>
        </div>
        <div className="stats-grid">
          {t.stats.items.map((s, i) => (
            <StatCard key={i} stat={s} trigger={trigger} idx={i} />
          ))}
        </div>
      </div>
    </section>
  );
}

function StatCard({ stat, trigger, idx }) {
  const v = useCountUp(stat.n, trigger);
  return (
    <div className={`card stat-card reveal delay-${idx + 1}`}>
      <div className="stat-num">
        {v}<span className="stat-suffix">{stat.suffix}</span>
      </div>
      <div className="stat-label">{stat.label}</div>
      <div className="stat-bar"><div className="stat-bar-fill" style={{ width: trigger ? '100%' : '0%' }} /></div>
    </div>
  );
}

// ---------- About ----------
function About({ t }) {
  const ref = useReveal();
  return (
    <section className="section about" id="about" ref={ref} data-screen-label="03 About">
      <div className="container about-grid">
        <div className="about-visual reveal">
          <div className="about-img-main">
            <div className="placeholder-img">equipa · trabalho colaborativo</div>
          </div>
          <div className="about-img-sub">
            <div className="placeholder-img">handshake · parceria</div>
          </div>
          <div className="about-stat-float">
            <div className="afs-num">5<span>+</span></div>
            <div className="afs-lab">anos<br/>no mercado</div>
          </div>
        </div>
        <div className="about-copy">
          <div className="eyebrow reveal">{t.about.eyebrow}</div>
          <h2 className="h-section reveal delay-1">{t.about.title}</h2>
          <p className="lede reveal delay-2">{t.about.body1}</p>
          <p className="reveal delay-2" style={{ color: 'var(--ink-soft)' }}>{t.about.body2}</p>
          <ul className="about-bullets reveal delay-3">
            <li><Icon.Check size={14} />{t.about.bullet1}</li>
            <li><Icon.Check size={14} />{t.about.bullet2}</li>
            <li><Icon.Check size={14} />{t.about.bullet3}</li>
          </ul>
          <div className="about-pills reveal delay-3">
            <div className="about-pill"><Icon.MapPin size={14} />{t.about.pill1}</div>
            <div className="about-pill"><Icon.MapPin size={14} />{t.about.pill2}</div>
          </div>
          <div className="reveal delay-4">
            <a href="#services" className="btn btn-primary">
              {t.about.cta}
              <Icon.Arrow size={14} />
            </a>
          </div>
        </div>
      </div>
    </section>
  );
}

// ---------- Services ----------
function Services({ t, onView, onRequest, onAdd }) {
  const ref = useReveal();
  const icons = [Icon.Pen, Icon.Code, Icon.Megaphone, Icon.Tag, Icon.Share, Icon.Brain];

  // Conteúdo gerido no painel (se existir) tem prioridade sobre os textos padrão.
  const [managed, setManaged] = useState(null);
  const [head, setHead] = useState(null);
  useEffect(() => {
    window.loadContent().then((c) => {
      if (c.services && c.services.length) {
        setManaged(c.services.map((s, i) => ({
          n: s.n || String(i + 1).padStart(2, '0'),
          t: s.t || s.name || '',
          d: s.d || '',
          img: s.img || '',
        })));
      }
      if (c.headings && c.headings.services) setHead(c.headings.services);
    });
  }, []);
  const items = managed || t.services.items;
  const eyebrow = (head && head.eyebrow) || t.services.eyebrow;
  const title = (head && head.title) || t.services.title;
  const lede = (head && head.lede) || t.services.lede;

  return (
    <section className="section services" id="services" ref={ref} data-screen-label="04 Services">
      <div className="container">
        <div className="services-head">
          <div>
            <div className="eyebrow reveal">{eyebrow}</div>
            <h2 className="h-section reveal delay-1">{title}</h2>
          </div>
          <p className="lede reveal delay-2">{lede}</p>
        </div>
        <div className="services-grid">
          {items.map((s, i) => {
            const Ico = icons[i % icons.length];
            return (
              <article className={`card service-card reveal delay-${(i % 4) + 1}`} key={i}>
                <div className="service-num">{s.n}</div>
                {s.img
                  ? <div className="service-img"><img src={s.img} alt={s.t} loading="lazy" /></div>
                  : <div className="service-icon"><Ico /></div>}
                <h3 className="service-title">{s.t}</h3>
                <p className="service-desc">{s.d}</p>
                <div className="service-card-actions">
                  <button type="button" className="svc-btn svc-btn-primary" onClick={() => (onAdd ? onAdd(s.t) : onRequest(s.t))}>
                    <i className="fa-solid fa-cart-plus" aria-hidden="true" style={{ marginRight: 6 }} />Adicionar ao pedido
                  </button>
                  <button type="button" className="svc-btn svc-btn-ghost" onClick={() => onView(s)}>
                    Visualizar
                  </button>
                </div>
              </article>
            );
          })}
        </div>
      </div>
    </section>
  );
}

Object.assign(window, { Hero, Stats, About, Services, useReveal, useCountUp });
