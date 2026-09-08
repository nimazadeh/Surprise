import { MusicAPI, getCachedCatalogue } from './api.js';
import { installPressRipples } from './animations.js';
import { CONFIG } from './config.js';
import { PlayerController } from './player.js';
import { Router } from './router.js';
import { AppState, setOnlineStatus } from './state.js';
import { safeExternalUrl, sleep } from './utils.js';
import { UI } from './ui.js';

// Mark the interactive app as booted. The tiny HTML fallback remains available if modules fail to load.
document.documentElement.classList.add('app-ready');

let catalogueRequest = null;

function applyTheme(theme) {
  document.documentElement.dataset.theme = theme === 'midnight' ? 'midnight' : 'dynamic';
}

function setCatalogue(snapshot, { status, source, error = false } = {}) {
  AppState.patch({
    artist: snapshot.artist,
    albums: snapshot.albums || [],
    topTracks: snapshot.topTracks || [],
    catalogueStatus: status,
    dataSource: source,
    catalogueOrigin: snapshot.origin || 'deezer',
    catalogueError: error
  });
}

function withTimeout(promise, timeoutMs) {
  return new Promise((resolve, reject) => {
    const timer = window.setTimeout(() => reject(new Error('Catalogue timeout')), timeoutMs);
    Promise.resolve(promise).then(
      (value) => { window.clearTimeout(timer); resolve(value); },
      (error) => { window.clearTimeout(timer); reject(error); }
    );
  });
}

async function loadCatalogue({ force = false } = {}) {
  if (catalogueRequest && !force) return catalogueRequest;
  const task = (async () => {
    const previous = AppState.get();
    const cachedAtStart = getCachedCatalogue();

    // Never leave the first paint dependent on a third-party endpoint. A small local,
    // no-audio catalogue renders immediately, then live data transparently replaces it.
    if (!previous.artist) {
      const immediateCatalogue = cachedAtStart || MusicAPI.getFallbackCatalogue();
      setCatalogue(immediateCatalogue, {
        status: 'loading',
        source: immediateCatalogue.source === 'cache' ? 'cache' : 'fallback'
      });
      UI.renderRoute(previous.route, { keepScroll: true });
    } else {
      AppState.patch({ catalogueStatus: 'loading', catalogueError: false });
    }

    if (!navigator.onLine) {
      const cached = cachedAtStart || getCachedCatalogue();
      if (cached) {
        setCatalogue(cached, { status: 'degraded', source: 'cache' });
        AppState.set('toast', { id: `offline-cache-${Date.now()}`, message: 'You’re offline — showing saved music metadata.', type: 'info' });
      } else {
        setCatalogue(MusicAPI.getFallbackCatalogue(), { status: 'degraded', source: 'fallback' });
        AppState.set('toast', { id: `offline-demo-${Date.now()}`, message: 'You’re offline — showing the no-audio demo catalogue.', type: 'info' });
      }
      refreshContentAfterCatalogue();
      return;
    }

    try {
      const snapshot = await withTimeout(
        MusicAPI.bootstrapCatalogue(),
        CONFIG.catalogueBootstrapTimeoutMs
      );
      setCatalogue(snapshot, { status: 'ready', source: 'live' });
    } catch (error) {
      const cached = cachedAtStart || getCachedCatalogue();
      if (cached) {
        setCatalogue(cached, { status: 'degraded', source: 'cache', error: true });
        AppState.set('toast', { id: `catalogue-cache-${Date.now()}`, message: 'Live music data is unavailable — showing saved metadata.', type: 'info' });
      } else {
        setCatalogue(MusicAPI.getFallbackCatalogue(), { status: 'degraded', source: 'fallback', error: true });
        AppState.set('toast', { id: `catalogue-demo-${Date.now()}`, message: 'Live music data is unavailable — showing the no-audio demo catalogue.', type: 'info' });
      }
    }
    refreshContentAfterCatalogue();
  })();

  catalogueRequest = task;
  try { await task; } finally { catalogueRequest = null; }
}

function refreshContentAfterCatalogue() {
  const state = AppState.get();
  const contentRoute = state.route.name === 'player' ? state.lastContentRoute : state.route;
  UI.renderRoute(contentRoute, { keepScroll: true });
}

/**
 * Shareable hash links (#/album/{id}, #/track/{id}, #/artist/{id}) entered
 * cold — a shared URL or a reload — land on the backend SEO page when the
 * target is owned catalogue content (Phase 2.5). In-app navigation never
 * redirects mid-session, and provider-only items render in-app as before.
 */
function maybeRedirectColdHashLink(route, initial) {
  if (!initial || !MusicAPI.apiEnabled) return;

  if (route.name === 'artist' && route.params?.id) {
    MusicAPI.getArtist(route.params.id)
      .then((artist) => {
        const url = safeExternalUrl(artist?.providerUrl);
        if (artist?.source === 'owned' && url) window.location.replace(url);
      })
      .catch(() => {});
  }
}

function handleRoute(route, { initial = false } = {}) {
  const current = AppState.get();
  if (route.name === 'player') {
    AppState.patch({ route, playerExpanded: true });
  } else {
    AppState.patch({
      route,
      lastContentRoute: route,
      playerExpanded: false,
      playerPanel: null
    });
    maybeRedirectColdHashLink(route, initial);
  }
  UI.renderRoute(route, { coldEntry: initial });
}

function dismissSplash() {
  const splash = document.getElementById('splash');
  if (!splash) return;
  splash.classList.add('is-leaving');
  splash.addEventListener('transitionend', () => splash.remove(), { once: true });
  window.setTimeout(() => splash.remove(), 750);
}

function registerServiceWorker() {
  if (!('serviceWorker' in navigator)) return;

  // Arena previews are frequently rebuilt while a designer is iterating. Do not let a
  // previous preview's service-worker cache mask newly written modules in that context.
  if (location.hostname.endsWith('.e2b.app')) {
    navigator.serviceWorker.getRegistrations().then((registrations) => {
      registrations.forEach((registration) => registration.unregister());
    }).catch(() => {});
    return;
  }

  if (!CONFIG.enableServiceWorker || (!window.isSecureContext && location.hostname !== 'localhost')) return;
  window.addEventListener('load', () => {
    // The build query forces an update check even on hosts that cache an unchanged
    // `service-worker.js` pathname aggressively between GitHub Pages deployments.
    navigator.serviceWorker.register('./service-worker.js?build=7', { updateViaCache: 'none' })
      .then((registration) => registration.update())
      .catch(() => {
        // The app remains fully functional without PWA registration.
      });
  });
}

async function bootstrap() {
  const started = performance.now();
  applyTheme(AppState.get().theme);
  PlayerController.initialize();
  installPressRipples(document);
  UI.init({ onRetryCatalogue: () => loadCatalogue({ force: true }) });

  window.addEventListener('online', () => {
    setOnlineStatus(true);
    AppState.set('toast', { id: `online-${Date.now()}`, message: 'You’re back online.', type: 'success' });
  });
  window.addEventListener('offline', () => {
    setOnlineStatus(false);
    AppState.set('toast', { id: `offline-${Date.now()}`, message: 'You’re offline. Previously loaded music can still be browsed.', type: 'info' });
  });

  Router.start(handleRoute);
  registerServiceWorker();

  loadCatalogue();
  // The opening moment is intentionally cinematic, but never held hostage by a slow provider.
  await sleep(Math.max(0, 680 - (performance.now() - started)));
  dismissSplash();
}

bootstrap().catch(() => {
  // If a local module unexpectedly fails after boot starts, restore the self-contained
  // HTML fallback instead of exposing a blank document.
  document.documentElement.classList.remove('app-ready');
  const splash = document.getElementById('splash');
  if (splash) splash.remove();
});
