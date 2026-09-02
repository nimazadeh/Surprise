const DEFAULT_ROUTE = { name: 'home', params: {}, query: {} };
let changeHandler = null;

function parseQuery(search = '') {
  return Object.fromEntries(new URLSearchParams(search).entries());
}

export function parseRoute(hash = window.location.hash) {
  const cleanHash = hash.replace(/^#/, '') || '/home';
  const [rawPath, rawQuery = ''] = cleanHash.split('?');
  const segments = rawPath.replace(/^\/+|\/+$/g, '').split('/').filter(Boolean);
  const query = parseQuery(rawQuery);
  const name = segments[0] || 'home';

  if (name === 'album' && segments[1]) return { name: 'album', params: { id: decodeURIComponent(segments[1]) }, query };
  if (name === 'track' && segments[1]) return { name: 'track', params: { id: decodeURIComponent(segments[1]) }, query };
  if (['home', 'search', 'artist', 'albums', 'tracks', 'library', 'favorites', 'recent', 'player'].includes(name)) {
    return { name, params: {}, query };
  }
  return DEFAULT_ROUTE;
}

export const Router = {
  start(onChange) {
    changeHandler = onChange;
    window.addEventListener('hashchange', () => this.notify());
    this.notify();
  },

  notify() {
    const route = parseRoute();
    if (changeHandler) changeHandler(route);
  },

  navigate(path, { replace = false } = {}) {
    const normalized = path.startsWith('/') ? path : `/${path}`;
    const hash = `#${normalized}`;
    if (window.location.hash === hash) {
      this.notify();
      return;
    }
    if (replace) {
      window.history.replaceState(null, '', hash);
      this.notify();
    } else {
      window.location.hash = hash;
    }
  },

  current() { return parseRoute(); }
};
