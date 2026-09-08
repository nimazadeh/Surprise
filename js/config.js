/**
 * Public runtime configuration only. No private API credentials belong in a static client.
 * Deezer's public catalogue endpoints are accessed through JSONP because their CORS policy
 * is not consistently available to static sites. Replace the provider in api.js to use
 * another lawful provider later.
 */
export const CONFIG = Object.freeze({
  appName: 'SHIRIN',
  artistQuery: 'Shirin David',
  deezerArtistId: '7312776',
  // 'shirin-api' points the player at the Laravel backend (/api/v1) with the
  // Deezer JSONP transport kept as the automatic fallback. Set apiBaseUrl at
  // deploy time (public value, no secrets); an empty string keeps the app on
  // the direct Deezer provider exactly as before.
  provider: 'shirin-api',
  apiBaseUrl: '',
  apiRequestTimeoutMs: 9000,
  deezerBaseUrl: 'https://api.deezer.com',
  requestTimeoutMs: 9000,
  // A hard UI timeout guarantees the app falls back instead of leaving a blank loader.
  catalogueBootstrapTimeoutMs: 6500,
  catalogueCacheTtlMs: 1000 * 60 * 45,
  catalogueLimit: 100,
  topTrackLimit: 18,
  searchLimit: 12,
  maxRecentTracks: 20,
  storagePrefix: 'shirin.music.v1',
  fallbackArtwork: 'assets/images/artwork-fallback.svg',
  fallbackArtistArtwork: 'assets/images/artist-fallback.svg',
  enableServiceWorker: true
});

export const STORAGE_KEYS = Object.freeze({
  favoritesTracks: `${CONFIG.storagePrefix}.favorites.tracks`,
  favoritesAlbums: `${CONFIG.storagePrefix}.favorites.albums`,
  favoriteArtist: `${CONFIG.storagePrefix}.favorite.artist`,
  recentlyPlayed: `${CONFIG.storagePrefix}.recent`,
  queue: `${CONFIG.storagePrefix}.queue`,
  playback: `${CONFIG.storagePrefix}.playback`,
  catalogue: `${CONFIG.storagePrefix}.catalogue`,
  theme: `${CONFIG.storagePrefix}.theme`
});
