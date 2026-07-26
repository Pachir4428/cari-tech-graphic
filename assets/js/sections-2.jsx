// ==========================================================================
// Cari Tech Graphic — Sections part 2 (Portfolio, Why, AI, Testimonials)
// ==========================================================================

const { useState: useState2, useEffect: useEffect2, useRef: useRef2 } = React;

// ---------- Portfolio ----------
function Portfolio({ t, onView }) {
  const ref = window.useReveal();
  const [filter, setFilter] = useState2('all');
  const filters = [
    { id: 'all', label: t.portfolio.filters.all },
    { id: 'web', label: t.portfolio.filters.web },
    { id: 'design', label: t.portfolio.filters.design },
    { id: 'marketing', label: t.portfolio.filters.marketing },
  ];

  // Conteúdo gerido no painel (se existir) tem prioridade sobre os textos padrão.
  const [managed, setManaged] = useState2(null);
  const [head, setHead] = useState2(null);
  useEffect2(() => {
    window.loadContent().then((c) => {
      if (c.portfolio && c.portfolio.length) {
        setManaged(c.portfolio.map((p) => ({ t: p.t || '', c: p.c || '', tag: p.tag || 'web', img: p.img || '', url: p.url || '' })));
      }
      if (c.headings && c.headings.portfolio) setHead(c.headings.portfolio);
    });
  }, []);
  const source = managed || t.portfolio.items;
  const eyebrow = (head && head.eyebrow) || t.portfolio.eyebrow;
  const title = (head && head.title) || t.portfolio.title;
  const lede = (head && head.lede) || t.portfolio.lede;

  const items = filter === 'all'
    ? source
    : source.filter((i) => i.tag === filter);

  const tagColors = {
    web: 'linear-gradient(135deg, #22D3EE, #06B6D4)',
    design: 'linear-gradient(135deg, #A78BFA, #6366F1)',
    marketing: 'linear-gradient(135deg, #F59E0B, #EF4444)',
  };

  return (
    <section className="section portfolio" id="portfolio" ref={ref} data-screen-label="05 Portfolio">
      <div className="container">
        <div className="portfolio-head">
          <div>
            <div className="eyebrow reveal">{eyebrow}</div>
            <h2 className="h-section reveal delay-1">{title}</h2>
            <p className="lede reveal delay-2">{lede}</p>
          </div>
          <div className="filter-bar reveal delay-2" role="tablist">
            <Icon.Filter size={14} />
            {filters.map((f) => (
              <button
                key={f.id}
                role="tab"
                aria-selected={filter === f.id}
                className={`filter-btn ${filter === f.id ? 'active' : ''}`}
                onClick={() => setFilter(f.id)}
              >
                {f.label}
              </button>
            ))}
          </div>
        </div>
        <div className="portfolio-grid">
          {items.map((p, i) => (
            <article
              className={`portfolio-card reveal delay-${(i % 4) + 1}`}
              key={p.t + i}
              style={{ '--tag-grad': tagColors[p.tag] }}
              onClick={() => onView(p)}
            >
              <div className="portfolio-img">
                {p.img
                  ? <img className="portfolio-photo" src={p.img} alt={p.t} loading="lazy" />
                  : <div className="placeholder-img">{p.tag} · {p.t.toLowerCase()}</div>}
                {p.url && <span className="portfolio-link-badge" title="Tem site ao vivo"><Icon.ArrowUpRight size={13} /></span>}
                <div className="portfolio-hover">
                  <div className="ph-tag">{p.tag}</div>
                  <h3>{p.t}</h3>
                  <p>{p.c}</p>
                  <div className="ph-arrow"><Icon.ArrowUpRight size={20} /></div>
                </div>
              </div>
              <div className="portfolio-meta">
                <div>
                  <h3 className="pm-title">{p.t}</h3>
                  <span className="pm-cat">{p.c}</span>
                </div>
                <div className="pm-tag" style={{ background: tagColors[p.tag] }}>{p.tag}</div>
              </div>
            </article>
          ))}
        </div>
        {items.length === 0 && (
          <div className="portfolio-empty">No projects in this category yet.</div>
        )}
      </div>
    </section>
  );
}

// ---------- Why us ----------
function Why({ t }) {
  const ref = window.useReveal();
  return (
    <section className="section why" id="why" ref={ref} data-screen-label="06 Why">
      <div className="why-bg-blob" />
      <div className="container">
        <div className="why-head">
          <div className="eyebrow why-eyebrow reveal">{t.why.eyebrow}</div>
          <h2 className="h-section why-title reveal delay-1">{t.why.title}</h2>
          <p className="lede why-lede reveal delay-2">{t.why.lede}</p>
        </div>
        <div className="why-grid">
          {t.why.items.map((w, i) => (
            <div key={i} className={`why-card reveal delay-${i + 1}`}>
              <div className="why-num">0{i + 1}</div>
              <div className="why-bar" />
              <h3 className="why-card-title">{w.t}</h3>
              <p className="why-card-desc">{w.d}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

// ---------- AI Section (radial orbital) ----------
function AI({ t }) {
  const ref = window.useReveal();
  const nodes = t.ai.nodes;
  const [activeId, setActiveId] = useState2(null);
  const [angle, setAngle] = useState2(0);
  const rafRef = useRef2(null);

  // Auto-rotate — ALWAYS on, even when a node is selected
  useEffect2(() => {
    let last = performance.now();
    const loop = (now) => {
      const dt = now - last;
      last = now;
      setAngle((a) => (a + dt * 0.010) % 360); // ~3.6 deg/s — gentle
      rafRef.current = requestAnimationFrame(loop);
    };
    rafRef.current = requestAnimationFrame(loop);
    return () => cancelAnimationFrame(rafRef.current);
  }, []);

  const activeNode = nodes.find((n) => n.id === activeId) || null;
  const relatedIds = activeNode ? activeNode.related : [];

  const handleToggle = (id) => {
    setActiveId((cur) => (cur === id ? null : id));
  };

  const handleBgClick = (e) => {
    if (e.target === e.currentTarget) setActiveId(null);
  };

  // Compute live position of active node (for the connector line)
  let activeLine = null;
  if (activeNode) {
    const idx = nodes.findIndex((x) => x.id === activeNode.id);
    const a = ((idx / nodes.length) * 360 + angle) % 360;
    const rad = (a * Math.PI) / 180;
    activeLine = {
      x: 200 * Math.cos(rad),
      y: 200 * Math.sin(rad),
    };
  }

  return (
    <section className="section ai-section" id="ai" ref={ref} data-screen-label="07 AI">
      <div className="ai-bg-grid" aria-hidden="true" />
      <div className="ai-bg-glow ai-bg-glow-1" aria-hidden="true" />
      <div className="ai-bg-glow ai-bg-glow-2" aria-hidden="true" />

      <div className={`container ai-grid ${activeNode ? 'is-detail-open' : ''}`}>
        <div className="ai-copy">
          <div className="ai-copy-intro">
            <div className="eyebrow reveal" style={{ color: 'var(--accent)' }}>{t.ai.eyebrow}</div>
            <h2 className="h-section reveal delay-1" style={{ color: '#fff' }}>{t.ai.title}</h2>
            <p className="lede reveal delay-2" style={{ color: 'rgba(255,255,255,0.78)' }}>{t.ai.body}</p>

            <div className="ai-legend reveal delay-2">
              {nodes.map((n) => {
                const isActive = activeId === n.id;
                const isRelated = relatedIds.includes(n.id);
                const NodeIcon = Icon[n.icon] || Icon.Sparkle;
                return (
                  <button
                    key={n.id}
                    className={`ai-legend-item ${isActive ? 'is-active' : ''} ${isRelated ? 'is-related' : ''}`}
                    onClick={() => handleToggle(n.id)}
                    type="button"
                  >
                    <span className="ai-legend-ico"><NodeIcon size={14} /></span>
                    <span className="ai-legend-code">{n.code}</span>
                    <span className="ai-legend-title">{n.title}</span>
                  </button>
                );
              })}
            </div>

            <div className="reveal delay-3" style={{ marginTop: 8 }}>
              <a href="#contact" className="btn btn-accent">
                {t.ai.cta}<Icon.Arrow size={14} />
              </a>
            </div>
          </div>

          {activeNode && (
            <div className="ai-detail" key={activeNode.id}>
              <button
                className="ai-detail-close"
                type="button"
                onClick={() => setActiveId(null)}
                aria-label="Close"
              >×</button>

              <div className="ai-detail-head">
                <div className="ai-detail-icon">
                  {(() => { const I = Icon[activeNode.icon] || Icon.Sparkle; return <I size={24} />; })()}
                </div>
                <div className="ai-detail-head-meta">
                  <span className={`ai-status ai-status-${activeNode.status}`}>
                    <span className="ai-status-dot" />
                    {activeNode.statusLabel}
                  </span>
                  <span className="ai-detail-code">{activeNode.code}</span>
                </div>
              </div>

              <h3 className="ai-detail-title">{activeNode.title}</h3>
              <p className="ai-detail-tagline">{activeNode.tagline}</p>
              <p className="ai-detail-body">{activeNode.content}</p>

              {activeNode.includes && (
                <div className="ai-detail-includes">
                  <div className="ai-detail-includes-label">{t.ai.includes}</div>
                  <ul className="ai-detail-list">
                    {activeNode.includes.map((it, i) => (
                      <li key={i}>
                        <span className="ai-detail-tick"><Icon.Check size={12} /></span>
                        <span>{it}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              )}

              <div className="ai-detail-meter">
                <div className="ai-meter-head">
                  <span>{t.ai.capacity}</span>
                  <span className="ai-meter-val">{activeNode.energy}%</span>
                </div>
                <div className="ai-meter-bar">
                  <span style={{ width: `${activeNode.energy}%` }} />
                </div>
              </div>

              {activeNode.related.length > 0 && (
                <div className="ai-detail-related">
                  <div className="ai-detail-includes-label">{t.ai.connected}</div>
                  <div className="ai-card-related-list">
                    {activeNode.related.map((rid) => {
                      const rNode = nodes.find((x) => x.id === rid);
                      if (!rNode) return null;
                      return (
                        <button
                          key={rid}
                          type="button"
                          className="ai-chip"
                          onClick={() => handleToggle(rid)}
                        >
                          {rNode.title}
                          <Icon.Arrow size={10} />
                        </button>
                      );
                    })}
                  </div>
                </div>
              )}
            </div>
          )}
        </div>

        <div
          className={`ai-orbit ${activeNode ? 'is-focused' : ''}`}
          onClick={handleBgClick}
        >
          {/* connection lines as svg */}
          <svg className="ai-orbit-lines" viewBox="-260 -260 520 520" aria-hidden="true">
            {/* active → related dashed connectors */}
            {activeNode && nodes.map((n) => {
              if (!relatedIds.includes(n.id)) return null;
              const idx = nodes.findIndex((x) => x.id === n.id);
              const a = ((idx / nodes.length) * 360 + angle) % 360;
              const rad = (a * Math.PI) / 180;
              const x = 200 * Math.cos(rad);
              const y = 200 * Math.sin(rad);
              return (
                <line
                  key={`l-${n.id}`}
                  x1={activeLine.x} y1={activeLine.y} x2={x} y2={y}
                  stroke="#FF7200" strokeWidth="1" strokeDasharray="2 4"
                  opacity="0.55"
                />
              );
            })}
            {/* core → active node guide line */}
            {activeNode && (
              <line
                x1={0} y1={0} x2={activeLine.x} y2={activeLine.y}
                stroke="#FF7200" strokeWidth="1.2" strokeDasharray="3 5"
                opacity="0.7"
              />
            )}
          </svg>

          {/* orbit rings */}
          <div className="ai-orbit-ring ai-orbit-ring-outer" />
          <div className="ai-orbit-ring ai-orbit-ring-mid" />
          <div className="ai-orbit-ring ai-orbit-ring-inner" />

          {/* center core */}
          <div className="ai-core">
            <div className="ai-core-pulse ai-core-pulse-1" />
            <div className="ai-core-pulse ai-core-pulse-2" />
            <div className="ai-core-inner">
              <Icon.Brain size={28} />
            </div>
            <div className="ai-core-label">{t.ai.core}</div>
          </div>

          {/* nodes — keep orbiting even when one is active */}
          {nodes.map((n, i) => {
            const a = ((i / nodes.length) * 360 + angle) % 360;
            const rad = (a * Math.PI) / 180;
            const x = 200 * Math.cos(rad);
            const y = 200 * Math.sin(rad);
            const depth = (1 + Math.sin(rad)) / 2;
            const scale = 0.78 + depth * 0.32;
            const opacity = 0.55 + depth * 0.45;
            const z = Math.round(100 + 50 * Math.cos(rad));
            const isActive = activeId === n.id;
            const isRelated = relatedIds.includes(n.id);
            const dimmed = activeId && !isActive && !isRelated;
            const NodeIcon = Icon[n.icon] || Icon.Sparkle;
            const labelBelow = Math.sin(rad) < 0;
            return (
              <div
                key={n.id}
                className={`ai-node ${isActive ? 'is-active' : ''} ${isRelated ? 'is-related' : ''} ${dimmed ? 'is-dimmed' : ''}`}
                style={{
                  transform: `translate(-50%, -50%) translate(${x}px, ${y}px) scale(${isActive ? 1.3 : scale})`,
                  opacity: isActive ? 1 : opacity,
                  zIndex: isActive ? 300 : z,
                }}
                onClick={(e) => { e.stopPropagation(); handleToggle(n.id); }}
              >
                <span className="ai-node-halo" />
                <span className="ai-node-dot">
                  <NodeIcon size={16} />
                </span>
                <span className={`ai-node-label ${labelBelow ? 'below' : 'above'}`}>
                  <span className="ai-node-code">{n.code}</span>
                  <span className="ai-node-title">{n.title}</span>
                </span>
              </div>
            );
          })}

          <div className="ai-orbit-hint">{t.ai.hint}</div>
        </div>
      </div>
    </section>
  );
}

// ---------- Testimonials ----------
function Testimonials({ t }) {
  const ref = window.useReveal();
  const [active, setActive] = useState2(0);

  useEffect2(() => {
    const id = setInterval(() => setActive((a) => (a + 1) % t.testimonials.items.length), 6000);
    return () => clearInterval(id);
  }, [t.testimonials.items.length]);

  return (
    <section className="section testimonials" id="testimonials" ref={ref} data-screen-label="08 Testimonials">
      <div className="container">
        <div className="testimonials-head">
          <div className="eyebrow reveal">{t.testimonials.eyebrow}</div>
          <h2 className="h-section reveal delay-1">{t.testimonials.title}</h2>
        </div>
        <div className="testimonials-stage reveal delay-2">
          <Icon.Quote size={48} />
          <div className="testimonial-track" style={{ transform: `translateX(-${active * 100}%)` }}>
            {t.testimonials.items.map((it, i) => (
              <div className="testimonial-slide" key={i}>
                <p className="testimonial-q">{it.q}</p>
                <div className="testimonial-author">
                  <div className="testimonial-avatar">{it.n.charAt(0)}</div>
                  <div>
                    <div className="testimonial-name">{it.n}</div>
                    <div className="testimonial-role">{it.r}</div>
                  </div>
                </div>
              </div>
            ))}
          </div>
          <div className="testimonial-dots">
            {t.testimonials.items.map((_, i) => (
              <button
                key={i}
                className={`t-dot ${active === i ? 'active' : ''}`}
                onClick={() => setActive(i)}
                aria-label={`Slide ${i + 1}`}
              />
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}

Object.assign(window, { Portfolio, Why, AI, Testimonials });
