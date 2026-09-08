import { CONFIG } from './config.js';

export function escapeHTML(value = '') {
  return String(value).replace(/[&<>'"]/g, (character) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
  }[character]));
}

export function escapeAttribute(value = '') {
  return escapeHTML(value).replace(/`/g, '&#96;');
}

export function clamp(value, min, max) {
  return Math.min(Math.max(Number(value) || 0, min), max);
}

export function formatDuration(seconds) {
  const numeric = Math.max(0, Math.floor(Number(seconds) || 0));
  const minutes = Math.floor(numeric / 60);
  const remaining = String(numeric % 60).padStart(2, '0');
  return `${minutes}:${remaining}`;
}

export function formatCompactNumber(value) {
  const number = Number(value) || 0;
  if (number >= 1000000) return `${(number / 1000000).toFixed(number >= 10000000 ? 0 : 1)}M`;
  if (number >= 1000) return `${(number / 1000).toFixed(number >= 100000 ? 0 : 1)}K`;
  return new Intl.NumberFormat('en-US').format(number);
}

export function releaseYear(value) {
  const match = String(value || '').match(/^\d{4}/);
  return match ? match[0] : '—';
}

export function debounce(callback, wait = 300) {
  let timerId;
  return (...args) => {
    window.clearTimeout(timerId);
    timerId = window.setTimeout(() => callback(...args), wait);
  };
}

export function readStorage(key, fallback) {
  try {
    const raw = window.localStorage.getItem(key);
    return raw ? JSON.parse(raw) : fallback;
  } catch (error) {
    return fallback;
  }
}

export function writeStorage(key, value) {
  try {
    window.localStorage.setItem(key, JSON.stringify(value));
    return true;
  } catch (error) {
    return false;
  }
}

export function removeStorage(key) {
  try { window.localStorage.removeItem(key); } catch (error) { /* storage may be disabled */ }
}

export function sleep(milliseconds) {
  return new Promise((resolve) => window.setTimeout(resolve, milliseconds));
}

export function makeTrackRecord(track) {
  if (!track) return null;
  return {
    id: String(track.id),
    slug: track.slug || '',
    title: track.title || 'Untitled track',
    artistName: track.artistName || 'Shirin David',
    artistId: String(track.artistId || ''),
    artistSlug: track.artistSlug || '',
    albumId: String(track.albumId || ''),
    albumTitle: track.albumTitle || '',
    albumSlug: track.albumSlug || '',
    artwork: track.artwork || CONFIG.fallbackArtwork,
    artworkSmall: track.artworkSmall || track.artwork || CONFIG.fallbackArtwork,
    duration: Number(track.duration) || 0,
    preview: track.preview || null,
    providerUrl: track.providerUrl || '',
    explicit: Boolean(track.explicit),
    position: Number(track.position) || 0,
    source: track.source || 'deezer-public'
  };
}

export function makeAlbumRecord(album) {
  if (!album) return null;
  return {
    id: String(album.id),
    slug: album.slug || '',
    title: album.title || 'Untitled release',
    artistName: album.artistName || 'Shirin David',
    artistId: String(album.artistId || ''),
    artistSlug: album.artistSlug || '',
    artwork: album.artwork || CONFIG.fallbackArtwork,
    artworkSmall: album.artworkSmall || album.artwork || CONFIG.fallbackArtwork,
    releaseDate: album.releaseDate || '',
    recordType: album.recordType || 'album',
    trackCount: Number(album.trackCount) || 0,
    providerUrl: album.providerUrl || '',
    explicit: Boolean(album.explicit),
    source: album.source || 'deezer-public'
  };
}

export function uniqueById(items = []) {
  const seen = new Set();
  return items.filter((item) => {
    const id = String(item?.id || '');
    if (!id || seen.has(id)) return false;
    seen.add(id);
    return true;
  });
}

export function getImageSource(item, size = 'default') {
  if (!item) return CONFIG.fallbackArtwork;
  if (size === 'small') return item.artworkSmall || item.pictureSmall || item.artwork || item.picture || CONFIG.fallbackArtwork;
  if (size === 'artist') return item.picture || item.artwork || CONFIG.fallbackArtistArtwork;
  return item.artwork || item.picture || CONFIG.fallbackArtwork;
}

export function isSameId(a, b) {
  return String(a || '') === String(b || '');
}

export function hashString(input = '') {
  let hash = 5381;
  for (let index = 0; index < String(input).length; index += 1) {
    hash = ((hash << 5) + hash) + String(input).charCodeAt(index);
  }
  return Math.abs(hash >>> 0);
}

export function safeExternalUrl(value) {
  try {
    const url = new URL(value);
    return ['https:', 'http:'].includes(url.protocol) ? url.href : '';
  } catch (error) {
    return '';
  }
}

export function pluralize(count, singular, plural = `${singular}s`) {
  return `${count} ${count === 1 ? singular : plural}`;
}

export function createId(prefix = 'id') {
  return `${prefix}-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
}
