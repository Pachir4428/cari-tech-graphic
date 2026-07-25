// ==========================================================================
// Cari Tech Graphic — Animated extras (ContainerScroll + Shuffle Testimonials)
// Adapted from Aceternity / framer-motion examples to vanilla React + CSS
// ==========================================================================

const { motion, useScroll: fmUseScroll, useTransform: fmUseTransform } = window.framerMotion || {};

// ---------- ContainerScroll ----------
function ContainerScroll({ titleComponent, children }) {
  const containerRef = React.useRef(null);
  const [isMobile, setIsMobile] = React.useState(false);

  React.useEffect(() => {
    const check = () => setIsMobile(window.innerWidth <= 768);
    check();
    window.addEventListener('resize', check);
    return () => window.removeEventListener('resize', check);
  }, []);

  if (!motion) {
    // Fallback static render
    return (
      <div className="cs-wrap">
        <div className="cs-inner">
          <div className="cs-title">{titleComponent}</div>
          <div className="cs-card cs-card-static">
            <div className="cs-card-inner">{children}</div>
          </div>
        </div>
      </div>
    );
  }

  const { scrollYProgress } = fmUseScroll({ target: containerRef });
  const rotate = fmUseTransform(scrollYProgress, [0, 1], [20, 0]);
  const scale = fmUseTransform(scrollYProgress, [0, 1], isMobile ? [0.7, 0.9] : [1.05, 1]);
  const translate = fmUseTransform(scrollYProgress, [0, 1], [0, -100]);

  return (
    <div className="cs-wrap" ref={containerRef}>
      <div className="cs-inner" style={{ perspective: '1000px' }}>
        <motion.div className="cs-title" style={{ translateY: translate }}>
          {titleComponent}
        </motion.div>
        <motion.div
          className="cs-card"
          style={{
            rotateX: rotate,
            scale,
            boxShadow:
              '0 0 #0000004d, 0 9px 20px #0000004a, 0 37px 37px #00000042, 0 84px 50px #00000026, 0 149px 60px #0000000a, 0 233px 65px #00000003',
          }}
        >
          <div className="cs-card-inner">{children}</div>
        </motion.div>
      </div>
    </div>
  );
}

// ---------- Shuffle Testimonial Card ----------
function ShuffleTestimonialCard({ handleShuffle, testimonial, position, id, author, role }) {
  const dragRef = React.useRef(0);
  const isFront = position === 'front';
  if (!motion) return null;

  return (
    <motion.div
      style={{
        zIndex: position === 'front' ? 2 : position === 'middle' ? 1 : 0,
      }}
      animate={{
        rotate: position === 'front' ? '-6deg' : position === 'middle' ? '0deg' : '6deg',
        x: position === 'front' ? '0%' : position === 'middle' ? '33%' : '66%',
      }}
      drag={true}
      dragElastic={0.35}
      dragListener={isFront}
      dragConstraints={{ top: 0, left: 0, right: 0, bottom: 0 }}
      onDragStart={(e) => {
        dragRef.current = e.clientX || (e.touches && e.touches[0].clientX) || 0;
      }}
      onDragEnd={(e) => {
        const end = e.clientX || (e.changedTouches && e.changedTouches[0].clientX) || 0;
        if (dragRef.current - end > 150) handleShuffle();
        dragRef.current = 0;
      }}
      transition={{ duration: 0.35 }}
      className={`shuffle-card ${isFront ? 'shuffle-card-front' : ''}`}
    >
      <div className="shuffle-avatar">{(author || '?').charAt(0)}</div>
      <span className="shuffle-quote">"{testimonial}"</span>
      <span className="shuffle-author">{author}</span>
      {role && <span className="shuffle-role">{role}</span>}
    </motion.div>
  );
}

function ShuffleTestimonials({ t }) {
  const ref = window.useReveal();
  const items = t.testimonials.items.slice(0, 3).map((it, i) => ({
    id: i + 1,
    testimonial: it.q,
    author: it.n,
    role: it.r,
  }));
  const [positions, setPositions] = React.useState(['front', 'middle', 'back']);
  const handleShuffle = () => {
    setPositions((p) => {
      const np = [...p];
      np.unshift(np.pop());
      return np;
    });
  };

  return (
    <section className="section testimonials shuffle-section" id="testimonials" ref={ref} data-screen-label="08 Testimonials">
      <div className="container shuffle-grid">
        <div className="shuffle-copy">
          <div className="eyebrow reveal">{t.testimonials.eyebrow}</div>
          <h2 className="h-section reveal delay-1">{t.testimonials.title}</h2>
          <p className="lede reveal delay-2" style={{ marginTop: 16 }}>
            Arraste o cartão para o lado para ver o próximo testemunho.
          </p>
          <button className="btn btn-ghost reveal delay-3" onClick={handleShuffle} style={{ marginTop: 16 }}>
            Próximo<Icon.Arrow size={14} />
          </button>
        </div>
        <div className="shuffle-stage reveal delay-2">
          <div className="shuffle-stack">
            {items.map((it, i) => (
              <ShuffleTestimonialCard
                key={it.id}
                {...it}
                handleShuffle={handleShuffle}
                position={positions[i]}
              />
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}

Object.assign(window, { ContainerScroll, ShuffleTestimonials });
