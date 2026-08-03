// ==========================================================================
// Cari Tech Graphic — App shell
// ==========================================================================

const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "theme": "light",
  "font": "manrope",
  "cards": "solid",
  "hero": "orbital",
  "accent": "orange",
  "density": "regular"
}/*EDITMODE-END*/;

const ACCENTS = {
  orange: { a: '#F5791C', a2: '#DB640C', glow: 'rgba(245, 121, 28, 0.28)' },
  amber:  { a: '#EFA218', a2: '#CE850C', glow: 'rgba(239, 162, 24, 0.28)' },
  coral:  { a: '#F26B82', a2: '#DB3B55', glow: 'rgba(242, 107, 130, 0.28)' },
  gradient: { a: '#F5791C', a2: '#F7A24E', glow: 'rgba(247, 162, 78, 0.28)' },
};

const FONTS = {
  manrope: "'Manrope', system-ui, sans-serif",
  poppins: "'Poppins', system-ui, sans-serif",
  inter: "'Inter', system-ui, sans-serif",
};

function App() {
  const [tweaks, setTweak] = useTweaks(TWEAK_DEFAULTS);
  const [lang, setLang] = React.useState('pt');
  const [leadOpen, setLeadOpen] = React.useState(false);
  const [leadService, setLeadService] = React.useState('');
  const [serviceDetail, setServiceDetail] = React.useState(null);
  const [portfolioDetail, setPortfolioDetail] = React.useState(null);
  const [cart, setCart] = React.useState([]);
  const [cartOpen, setCartOpen] = React.useState(false);
  const [descService, setDescService] = React.useState(''); // serviço à espera da descrição
  const t = window.I18N[lang];

  const openLead = (svc = '') => { setLeadService(svc); setLeadOpen(true); };
  const addToCart = (svc) => setDescService(svc); // abre o popup de descrição antes de adicionar
  const confirmAddToCart = (desc) => {
    setCart((c) => [...c, { id: Date.now() + '-' + c.length, service: descService, desc }]);
    setDescService('');
    setCartOpen(true);
  };
  const removeFromCart = (id) => setCart((c) => c.filter((x) => x.id !== id));

  React.useEffect(() => {
    const root = document.documentElement;
    root.setAttribute('data-theme', tweaks.theme);
    root.setAttribute('data-cards', tweaks.cards);
    root.setAttribute('data-density', tweaks.density);
    const a = ACCENTS[tweaks.accent] || ACCENTS.orange;
    root.style.setProperty('--accent', a.a);
    root.style.setProperty('--accent-2', a.a2);
    root.style.setProperty('--accent-glow', a.glow);
    root.style.setProperty('--font-sans', FONTS[tweaks.font] || FONTS.manrope);
    root.style.setProperty('--font-display', FONTS[tweaks.font] || FONTS.manrope);
  }, [tweaks]);

  return (
    <>
      <Header t={t} lang={lang} setLang={setLang} theme={tweaks.theme} setTheme={(v) => setTweak('theme', v)} onStart={openLead} />
      <main>
        <Hero t={t} heroStyle={tweaks.hero} onStart={openLead} />
        <Stats t={t} />
        <About t={t} />
        <Services t={t} onView={setServiceDetail} onRequest={openLead} onAdd={addToCart} />
        <Partners t={t} />
        <Portfolio t={t} onView={setPortfolioDetail} />
        <SitesFeitos t={t} />
        <Why t={t} />
        <AI t={t} />
        <ContainerScroll
          titleComponent={
            <>
              <div className="eyebrow" style={{ color: 'var(--accent)' }}>Em destaque</div>
              <h2 className="h-section" style={{ fontSize: 'clamp(36px, 5vw, 64px)', marginTop: 12 }}>
                Veja o nosso trabalho<br />
                <span className="gradient-text">em movimento.</span>
              </h2>
            </>
          }
        >
          <div style={{
            height: '100%', width: '100%',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            background: 'linear-gradient(135deg, var(--brand-deep), var(--accent))',
            color: '#fff', fontSize: 24, fontWeight: 700,
          }}>
            <div style={{ textAlign: 'center', padding: 40 }}>
              <div style={{ fontSize: 14, opacity: 0.7, letterSpacing: '0.2em', marginBottom: 8 }}>PORTFÓLIO DESTACADO</div>
              <div style={{ fontSize: 'clamp(28px, 4vw, 56px)', fontWeight: 800, letterSpacing: '-0.02em' }}>
                Banco Horizonte · Mutuanha · Naparama
              </div>
              <div style={{ marginTop: 16, fontSize: 14, opacity: 0.7 }}>
                Marcas reais. Resultados mensuráveis.
              </div>
            </div>
          </div>
        </ContainerScroll>
        <ShuffleTestimonials t={t} />
        <Contact t={t} />
      </main>
      <Footer t={t} />
      <ChatAssistant />
      <WhatsAppFab />
      <CartFab count={cart.length} onOpen={() => setCartOpen(true)} />
      <CartModal open={cartOpen} onClose={() => setCartOpen(false)} items={cart} onRemove={removeFromCart} onClear={() => setCart([])} />
      <ServiceDescModal open={!!descService} onClose={() => setDescService('')} service={descService} onConfirm={confirmAddToCart} />
      <LeadModal open={leadOpen} onClose={() => setLeadOpen(false)} t={t} prefilledService={leadService} />
      <ServiceModal open={!!serviceDetail} service={serviceDetail} onClose={() => setServiceDetail(null)} onRequest={openLead} t={t} />
      <PortfolioModal open={!!portfolioDetail} item={portfolioDetail} onClose={() => setPortfolioDetail(null)} onRequest={openLead} />
      <TweaksPanel title="Tweaks">
        <TweakSection title="Tema">
          <TweakRadio
            label="Modo"
            value={tweaks.theme}
            onChange={(v) => setTweak('theme', v)}
            options={[{ value: 'light', label: 'Claro' }, { value: 'dark', label: 'Escuro' }]}
          />
          <TweakSelect
            label="Cor de acento"
            value={tweaks.accent}
            onChange={(v) => setTweak('accent', v)}
            options={[
              { value: 'orange', label: 'Laranja' },
              { value: 'amber', label: 'Âmbar' },
              { value: 'coral', label: 'Coral' },
              { value: 'gradient', label: 'Gradient' },
            ]}
          />
        </TweakSection>
        <TweakSection title="Tipografia">
          <TweakRadio
            label="Família"
            value={tweaks.font}
            onChange={(v) => setTweak('font', v)}
            options={[
              { value: 'manrope', label: 'Manrope' },
              { value: 'poppins', label: 'Poppins' },
              { value: 'inter', label: 'Inter' },
            ]}
          />
        </TweakSection>
        <TweakSection title="Layout">
          <TweakRadio
            label="Hero"
            value={tweaks.hero}
            onChange={(v) => setTweak('hero', v)}
            options={[
              { value: 'orbital', label: 'Orbital' },
              { value: 'mosaic', label: 'Mosaico' },
              { value: 'minimal', label: 'Minimal' },
            ]}
          />
          <TweakRadio
            label="Estilo de cards"
            value={tweaks.cards}
            onChange={(v) => setTweak('cards', v)}
            options={[
              { value: 'solid', label: 'Sólido' },
              { value: 'glass', label: 'Glass' },
              { value: 'border', label: 'Borda' },
            ]}
          />
          <TweakRadio
            label="Densidade"
            value={tweaks.density}
            onChange={(v) => setTweak('density', v)}
            options={[
              { value: 'cozy', label: 'Compacto' },
              { value: 'regular', label: 'Regular' },
              { value: 'spacious', label: 'Espaçoso' },
            ]}
          />
        </TweakSection>
      </TweaksPanel>
    </>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
