/*
 * Cari Tech Graphic — Service Worker (PWA)
 * ---------------------------------------------------------------------------
 * Torna o site instalável como aplicação e melhora a robustez em ligações
 * fracas (comum em Moçambique), SEM nunca guardar em cache dados dinâmicos:
 *
 *   - Pedidos .php (APIs) e métodos != GET  → SEMPRE rede (nunca cache).
 *   - Navegações (páginas HTML)             → rede primeiro, cache de reserva.
 *   - Recursos estáticos (css/js/img/fonts) → cache com actualização em fundo
 *                                             (stale-while-revalidate).
 *
 * A versão do cache muda a cada lançamento para forçar a limpeza do antigo.
 */
'use strict';

const CACHE = 'ctg-v115';
const APP_SHELL = [
  './',
  './index.html',
  './sobre.html',
  './entrar.html',
  './cliente.html',
  './offline.html',
  './manifest.webmanifest',
  './assets/icons/icon-192.png',
  './assets/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((c) => c.addAll(APP_SHELL).catch(() => null)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((nomes) => Promise.all(nomes.filter((n) => n !== CACHE).map((n) => caches.delete(n))))
      .then(() => self.clients.claim())
  );
});

function ehEstatico(url) {
  return /\.(?:css|js|mjs|png|jpg|jpeg|gif|webp|svg|ico|woff2?|ttf|eot)$/i.test(url.pathname);
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  const url = new URL(req.url);

  // Só tratamos GET do mesmo domínio. Tudo o resto (POST, APIs, CDN) segue normal.
  if (req.method !== 'GET' || url.origin !== self.location.origin) return;
  // Nunca interferir com PHP (dados dinâmicos): admin, cliente-api, demo, etc.
  if (url.pathname.endsWith('.php')) return;

  // Navegações (páginas): rede primeiro; se falhar, cache; por fim, offline.html.
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req)
        .then((resp) => {
          const copia = resp.clone();
          caches.open(CACHE).then((c) => c.put(req, copia)).catch(() => {});
          return resp;
        })
        .catch(() => caches.match(req).then((r) => r || caches.match('./offline.html')))
    );
    return;
  }

  // Recursos estáticos: responde do cache e actualiza em segundo plano.
  if (ehEstatico(url)) {
    event.respondWith(
      caches.match(req).then((cacheado) => {
        const rede = fetch(req)
          .then((resp) => {
            if (resp && resp.status === 200) {
              const copia = resp.clone();
              caches.open(CACHE).then((c) => c.put(req, copia)).catch(() => {});
            }
            return resp;
          })
          .catch(() => cacheado);
        return cacheado || rede;
      })
    );
  }
});
