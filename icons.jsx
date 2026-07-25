// Font Awesome icon adapter — keeps the same API as before
// (Icon.Name with size prop) but renders <i> elements using Font Awesome 6 Free.
// CDN is loaded from the host HTML.

const faIcon = (className) => ({ size = 18, style = {}, ...rest } = {}) => (
  <i
    className={className}
    style={{
      fontSize: typeof size === 'number' ? `${size}px` : size,
      lineHeight: 1,
      display: 'inline-flex',
      alignItems: 'center',
      justifyContent: 'center',
      width: typeof size === 'number' ? `${size}px` : size,
      height: typeof size === 'number' ? `${size}px` : size,
      ...style,
    }}
    aria-hidden="true"
    {...rest}
  />
);

const Icon = {
  // Navigation & action
  Arrow: faIcon('fa-solid fa-arrow-right'),
  ArrowUpRight: faIcon('fa-solid fa-arrow-up-right-from-square'),
  Play: faIcon('fa-solid fa-play'),
  Check: faIcon('fa-solid fa-check'),
  Filter: faIcon('fa-solid fa-sliders'),

  // Services / capabilities
  Sparkle: faIcon('fa-solid fa-wand-magic-sparkles'),
  Pen: faIcon('fa-solid fa-pen-nib'),
  Code: faIcon('fa-solid fa-code'),
  Megaphone: faIcon('fa-solid fa-bullhorn'),
  Tag: faIcon('fa-solid fa-tag'),
  Share: faIcon('fa-solid fa-share-nodes'),
  Brain: faIcon('fa-solid fa-brain'),
  Robot: faIcon('fa-solid fa-robot'),
  Chart: faIcon('fa-solid fa-chart-line'),
  Bolt: faIcon('fa-solid fa-bolt'),
  Heart: faIcon('fa-solid fa-heart'),
  Handshake: faIcon('fa-solid fa-handshake'),
  Rocket: faIcon('fa-solid fa-rocket'),
  Compass: faIcon('fa-solid fa-compass'),
  Palette: faIcon('fa-solid fa-palette'),

  // Theme
  Sun: faIcon('fa-solid fa-sun'),
  Moon: faIcon('fa-solid fa-moon'),

  // Contact
  Mail: faIcon('fa-solid fa-envelope'),
  Phone: faIcon('fa-solid fa-phone'),
  MapPin: faIcon('fa-solid fa-location-dot'),
  Globe: faIcon('fa-solid fa-globe'),
  Quote: faIcon('fa-solid fa-quote-left'),
  Clock: faIcon('fa-regular fa-clock'),

  // Social / brand
  WhatsApp: faIcon('fa-brands fa-whatsapp'),
  Instagram: faIcon('fa-brands fa-instagram'),
  Facebook: faIcon('fa-brands fa-facebook-f'),
  LinkedIn: faIcon('fa-brands fa-linkedin-in'),
  Twitter: faIcon('fa-brands fa-x-twitter'),
};

window.Icon = Icon;
