import { CONFIG, STORAGE_KEYS } from './config.js';
import {
  getImageSource,
  makeAlbumRecord,
  makeTrackRecord,
  readStorage,
  releaseYear,
  safeExternalUrl,
  uniqueById,
  writeStorage
} from './utils.js';

/** A provider contract that keeps UI and playback independent of a vendor response shape. */
export class MusicProvider {
  async searchArtist() { throw new Error('Not implemented'); }
  async getArtist() { throw new Error('Not implemented'); }
  async getAlbums() { throw new Error('Not implemented'); }
  async getAlbum() { throw new Error('Not implemented'); }
  async getAlbumTracks() { throw new Error('Not implemented'); }
  async getTrack() { throw new Error('Not implemented'); }
  async search() { throw new Error('Not implemented'); }
  async getLyrics() { return { status: 'unavailable', synced: false, lines: [], message: 'Lyrics are currently unavailable.' }; }
  async getPlayback() { return null; }
}

/**
 * Raised when the Laravel API cannot be reached at all (network error, 5xx,
 * timeout, malformed response). Clean API answers — including enveloped 404s
 * and 429s — never raise this; the caller decides what those mean.
 */
export class ApiUnavailableError extends Error {}

function firstArtwork(raw = {}) {
  return raw.cover_xl || raw.cover_big || raw.cover_medium || raw.cover || raw.picture_xl || raw.picture_big || raw.picture_medium || raw.picture || CONFIG.fallbackArtwork;
}

function smallArtwork(raw = {}) {
  return raw.cover_small || raw.picture_small || firstArtwork(raw);
}

function normalizeArtist(raw = {}) {
  return {
    id: String(raw.id || CONFIG.deezerArtistId),
    name: raw.name || 'Shirin David',
    picture: raw.picture_xl || raw.picture_big || raw.picture_medium || raw.picture || CONFIG.fallbackArtistArtwork,
    pictureSmall: raw.picture_small || raw.picture_medium || raw.picture || CONFIG.fallbackArtistArtwork,
    albumCount: Number(raw.nb_album) || 0,
    fanCount: Number(raw.nb_fan) || 0,
    providerUrl: raw.link || '',
    source: 'deezer-public',
    bio: 'German rapper, singer and entrepreneur.'
  };
}

function normalizeAlbum(raw = {}, artist = null) {
  return makeAlbumRecord({
    id: raw.id,
    title: raw.title,
    artistName: raw.artist?.name || artist?.name || 'Shirin David',
    artistId: raw.artist?.id || artist?.id || CONFIG.deezerArtistId,
    artwork: firstArtwork(raw),
    artworkSmall: smallArtwork(raw),
    releaseDate: raw.release_date || '',
    recordType: raw.record_type || raw.type || 'album',
    trackCount: raw.nb_tracks || raw.tracks?.data?.length || (raw.record_type === 'single' ? 1 : 0),
    providerUrl: raw.link || '',
    explicit: Boolean(raw.explicit_lyrics),
    source: 'deezer-public'
  });
}

function normalizeTrack(raw = {}, context = {}) {
  const album = raw.album || context.album || {};
  const artist = raw.artist || context.artist || {};
  return makeTrackRecord({
    id: raw.id,
    title: raw.title || raw.title_short,
    artistName: artist.name || context.artistName || 'Shirin David',
    artistId: artist.id || context.artistId || CONFIG.deezerArtistId,
    albumId: album.id || context.albumId || '',
    albumTitle: album.title || context.albumTitle || '',
    artwork: firstArtwork(album),
    artworkSmall: smallArtwork(album),
    duration: raw.duration,
    preview: raw.preview || null,
    providerUrl: raw.link || '',
    explicit: Boolean(raw.explicit_lyrics || raw.explicit_content_lyrics),
    position: raw.track_position || context.position || 0,
    source: 'deezer-public'
  });
}

/**
 * Deezer's documented public endpoints expose public music metadata and time-limited 30s
 * preview URLs. JSONP is deliberately used so this pure static app does not need a proxy
 * for Deezer's inconsistent browser CORS headers.
 */
export class DeezerProvider extends MusicProvider {
  constructor({ baseUrl = CONFIG.deezerBaseUrl, timeout = CONFIG.requestTimeoutMs } = {}) {
    super();
    this.baseUrl = baseUrl.replace(/\/$/, '');
    this.timeout = timeout;
    this.albumCache = new Map();
    this.trackCache = new Map();
  }

  request(path, params = {}) {
    return new Promise((resolve, reject) => {
      const callbackName = `__shirinDeezer${Date.now()}${Math.random().toString(36).slice(2)}`;
      const url = new URL(`${this.baseUrl}${path}`);
      Object.entries({ ...params, output: 'jsonp', callback: callbackName }).forEach(([key, value]) => {
        if (value !== undefined && value !== null) url.searchParams.set(key, value);
      });

      const script = document.createElement('script');
      let settled = false;
      const cleanup = () => {
        window.clearTimeout(timer);
        script.remove();
        try { delete window[callbackName]; } catch (error) { window[callbackName] = undefined; }
      };
      const finish = (handler, value) => {
        if (settled) return;
        settled = true;
        cleanup();
        handler(value);
      };
      const timer = window.setTimeout(() => finish(reject, new Error('The music provider took too long to respond.')), this.timeout);

      window[callbackName] = (payload) => {
        if (payload?.error) {
          finish(reject, new Error('The music provider could not load this item.'));
        } else {
          finish(resolve, payload || {});
        }
      };
      script.async = true;
      script.src = url.toString();
      script.onerror = () => finish(reject, new Error('The music provider is unavailable.'));
      document.head.appendChild(script);
    });
  }

  async searchArtist(query) {
    const response = await this.request('/search/artist', { q: query, limit: 12 });
    return (response.data || []).map(normalizeArtist);
  }

  async getArtist(artistId) {
    const response = await this.request(`/artist/${encodeURIComponent(artistId)}`);
    return normalizeArtist(response);
  }

  async resolveArtist() {
    const results = await this.searchArtist(CONFIG.artistQuery);
    const exact = results.find((artist) => artist.name.toLowerCase() === CONFIG.artistQuery.toLowerCase());
    return exact || results[0] || this.getArtist(CONFIG.deezerArtistId);
  }

  async getAlbums(artistId) {
    const response = await this.request(`/artist/${encodeURIComponent(artistId)}/albums`, { limit: CONFIG.catalogueLimit });
    return uniqueById((response.data || []).map((album) => normalizeAlbum(album, { id: artistId, name: CONFIG.artistQuery })))
      .sort((a, b) => String(b.releaseDate).localeCompare(String(a.releaseDate)));
  }

  async getAlbum(albumId) {
    const key = String(albumId);
    if (this.albumCache.has(key)) return this.albumCache.get(key);
    const response = await this.request(`/album/${encodeURIComponent(key)}`);
    const album = normalizeAlbum(response);
    this.albumCache.set(key, album);
    return album;
  }

  async getAlbumTracks(albumId) {
    const [album, response] = await Promise.all([
      this.getAlbum(albumId),
      this.request(`/album/${encodeURIComponent(albumId)}/tracks`, { limit: CONFIG.catalogueLimit })
    ]);
    const tracks = (response.data || []).map((track) => normalizeTrack(track, {
      album,
      albumId: album.id,
      albumTitle: album.title,
      artistName: album.artistName,
      artistId: album.artistId
    }));
    return { album: { ...album, trackCount: album.trackCount || tracks.length }, tracks };
  }

  async getArtistTopTracks(artistId) {
    const response = await this.request(`/artist/${encodeURIComponent(artistId)}/top`, { limit: CONFIG.topTrackLimit });
    return uniqueById((response.data || []).map((track) => normalizeTrack(track, {
      artistName: CONFIG.artistQuery,
      artistId
    })));
  }

  async getTrack(trackId) {
    const key = String(trackId);
    if (this.trackCache.has(key)) return this.trackCache.get(key);
    const response = await this.request(`/track/${encodeURIComponent(key)}`);
    const track = normalizeTrack(response);
    this.trackCache.set(key, track);
    return track;
  }

  async search(query) {
    const requestSet = await Promise.allSettled([
      this.request('/search/track', { q: query, limit: CONFIG.searchLimit }),
      this.request('/search/album', { q: query, limit: CONFIG.searchLimit }),
      this.request('/search/artist', { q: query, limit: 6 })
    ]);
    const [tracksResult, albumsResult, artistsResult] = requestSet;
    if (requestSet.every((result) => result.status === 'rejected')) throw new Error('Search could not be completed.');
    const shirinName = CONFIG.artistQuery.toLowerCase();
    const isShirinTrack = (track) => {
      const primary = String(track?.artist?.name || '').toLowerCase();
      const contributors = (track?.contributors || []).some((artist) => String(artist?.name || '').toLowerCase() === shirinName);
      return primary === shirinName || contributors;
    };
    const isShirinAlbum = (album) => String(album?.artist?.name || '').toLowerCase() === shirinName;
    const isShirinArtist = (artist) => String(artist?.name || '').toLowerCase() === shirinName;
    return {
      // SHIRIN is a focused artist product: exclude unrelated catalogue matches while
      // retaining collaborations that list Shirin David as a contributor.
      tracks: tracksResult.status === 'fulfilled' ? uniqueById((tracksResult.value.data || []).filter(isShirinTrack).map((track) => normalizeTrack(track))) : [],
      albums: albumsResult.status === 'fulfilled' ? uniqueById((albumsResult.value.data || []).filter(isShirinAlbum).map((album) => normalizeAlbum(album))) : [],
      artists: artistsResult.status === 'fulfilled' ? uniqueById((artistsResult.value.data || []).filter(isShirinArtist).map(normalizeArtist)) : []
    };
  }

  async getLyrics() {
    // Deezer's public catalogue API does not expose licensed lyric text or line timings.
    return {
      status: 'unavailable',
      synced: false,
      lines: [],
      message: 'Lyrics are currently unavailable. This provider does not expose licensed lyric text through its public API.'
    };
  }

  async getPlayback(track) {
    if (!track?.id) return null;
    // Preview tokens can expire, so refresh a real provider track immediately before playback.
    try {
      // Bypass the metadata cache: provider preview URLs are short-lived.
      const response = await this.request(`/track/${encodeURIComponent(track.id)}`);
      const refreshed = normalizeTrack(response);
      this.trackCache.set(String(track.id), refreshed);
      if (!refreshed.preview) return null;
      return {
        type: 'preview',
        url: refreshed.preview,
        duration: 30,
        label: '30-second authorized Deezer preview',
        track: { ...track, ...refreshed }
      };
    } catch (error) {
      if (!track.preview) return null;
      return {
        type: 'preview',
        url: track.preview,
        duration: 30,
        label: '30-second authorized Deezer preview',
        track
      };
    }
  }
}

/**
 * Normalizers for the Laravel `/api/v1` player-shaped payloads. The API
 * resources and the provider adapter share one key set, so one normalizer
 * per type handles both sources. Owned records use their immutable slug as
 * the record id (stable for queue/favorites persistence and deep links);
 * provider records keep the provider id.
 */
function safeApiLink(value) {
  return safeExternalUrl(value) || '';
}

function normalizeApiArtist(raw = {}) {
  return {
    id: String(raw.slug || raw.id || ''),
    slug: raw.slug || '',
    name: raw.name || 'Shirin David',
    picture: raw.artwork || CONFIG.fallbackArtistArtwork,
    pictureSmall: raw.artwork || CONFIG.fallbackArtistArtwork,
    albumCount: Number(raw.albums_count) || 0,
    fanCount: 0,
    providerUrl: safeApiLink(raw.url),
    source: raw.source || 'owned',
    bio: raw.bio || 'German rapper, singer and entrepreneur.'
  };
}

function normalizeApiAlbum(raw = {}) {
  return makeAlbumRecord({
    id: String(raw.slug || raw.id || ''),
    slug: raw.slug || '',
    title: raw.title,
    artistName: raw.artist_name || 'Shirin David',
    artistId: String(raw.artist_slug || raw.artist_name || ''),
    artistSlug: raw.artist_slug || '',
    artwork: raw.artwork || CONFIG.fallbackArtwork,
    artworkSmall: raw.artwork || CONFIG.fallbackArtwork,
    releaseDate: raw.release_date || '',
    recordType: raw.type || 'album',
    trackCount: Number(raw.tracks_count) || 0,
    providerUrl: safeApiLink(raw.url),
    explicit: false,
    source: raw.source || 'owned'
  });
}

function normalizeApiTrack(raw = {}) {
  return makeTrackRecord({
    id: String(raw.slug || raw.id || ''),
    slug: raw.slug || '',
    title: raw.title,
    artistName: raw.artist_name || 'Shirin David',
    artistId: String(raw.artist_slug || raw.artist_name || ''),
    artistSlug: raw.artist_slug || '',
    albumId: String(raw.album_slug || raw.album_title || ''),
    albumTitle: raw.album_title || '',
    albumSlug: raw.album_slug || '',
    artwork: raw.artwork || CONFIG.fallbackArtwork,
    artworkSmall: raw.artwork || CONFIG.fallbackArtwork,
    duration: Number(raw.duration) || 0,
    preview: raw.preview || null,
    providerUrl: safeApiLink(raw.url),
    explicit: false,
    position: Number(raw.track_number) || 0,
    source: raw.source || 'owned'
  });
}

/**
 * SHIRIN stays a focused artist product in provider-supplied results: keep
 * the featured artist's items, drop unrelated provider matches. Owned rows
 * are curated by the platform owner and are never filtered here.
 */
function shirinFocusFilter(items = [], kind = 'track') {
  const needle = CONFIG.artistQuery.toLowerCase();
  return items.filter((item) => {
    if (String(item.source || '') !== 'deezer') return true;
    if (kind === 'artist') return String(item.name || '').toLowerCase() === needle;
    return String(item.artistName || '').toLowerCase() === needle;
  });
}

/**
 * The Laravel backend provider (Phase 2.5): the owned catalogue is the
 * primary source and the backend merges provider metadata behind its own
 * flag. If the API itself is unreachable, calls fall back to the direct
 * Deezer JSONP provider so the static app never regresses.
 */
export class ShirinApiProvider extends MusicProvider {
  constructor({ baseUrl = CONFIG.apiBaseUrl, timeout = CONFIG.apiRequestTimeoutMs, deezer = null } = {}) {
    super();
    this.baseUrl = String(baseUrl || '').replace(/\/+$/, '');
    this.timeout = timeout;
    this.deezer = deezer;
    this.albumCache = new Map();
    this.albumTracksCache = new Map();
    this.trackCache = new Map();
  }

  get enabled() {
    return Boolean(this.baseUrl);
  }

  async request(path, params = {}) {
    if (!this.enabled) {
      throw new ApiUnavailableError('The SHIRIN API base URL is not configured.');
    }
    const url = new URL(`${this.baseUrl}/api/v1${path}`);
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') url.searchParams.set(key, value);
    });
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), this.timeout);
    try {
      const response = await fetch(url.toString(), {
        signal: controller.signal,
        headers: { Accept: 'application/json' }
      });
      if (response.status >= 500) {
        throw new ApiUnavailableError('The SHIRIN API is temporarily unavailable.');
      }
      return await response.json();
    } catch (error) {
      if (error instanceof ApiUnavailableError) throw error;
      // Network failures, aborts and unparseable bodies are all "unavailable".
      throw new ApiUnavailableError('The SHIRIN API could not be reached.');
    } finally {
      window.clearTimeout(timer);
    }
  }

  /** One round trip for the home screen; throws ApiUnavailableError when unusable. */
  async getFeaturedCatalogue() {
    const payload = await this.request('/catalogue/featured');
    const data = payload?.success ? payload.data : null;
    if (!data?.artist) {
      // No featured artist resolvable (owned empty and provider off/down):
      // signal unusable so the fallback chain can supply the catalogue.
      throw new ApiUnavailableError('The SHIRIN API has no featured catalogue yet.');
    }
    return {
      artist: normalizeApiArtist(data.artist),
      albums: (data.albums || []).map(normalizeApiAlbum),
      topTracks: (data.tracks || []).map(normalizeApiTrack)
    };
  }

  /** Hash-link resolution (#/album/{id} → owned SEO page or provider item). */
  async resolveHash(type, id) {
    if (!this.enabled || !type || !id) return null;
    const payload = await this.request('/resolve', { type, provider_id: String(id) }).catch(() => null);
    return payload?.success ? payload.data : null;
  }

  async searchArtist(query) {
    const payload = await this.request('/search', { q: query, type: 'artist', limit: 12 });
    const rows = payload?.success ? (payload.data?.artists || []) : [];
    return shirinFocusFilter(rows.map(normalizeApiArtist), 'artist');
  }

  async getArtist(artistId) {
    const direct = await this.request(`/artists/${encodeURIComponent(artistId)}`).catch(() => null);
    if (direct?.success) return normalizeApiArtist(direct.data);
    const resolved = await this.request('/resolve', { type: 'artist', provider_id: artistId }).catch(() => null);
    return resolved?.data?.matched && resolved.data.item ? normalizeApiArtist(resolved.data.item) : null;
  }

  async getAlbums(artistId) {
    const nested = await this.request(`/artists/${encodeURIComponent(artistId)}/albums`).catch(() => null);
    if (nested?.success) return (nested.data || []).map(normalizeApiAlbum);
    // Provider-addressed artists fall back to the direct JSONP transport.
    return this.deezer ? this.deezer.getAlbums(artistId) : [];
  }

  async getArtistTopTracks(artistId) {
    const nested = await this.request(`/artists/${encodeURIComponent(artistId)}/tracks`).catch(() => null);
    if (nested?.success) return (nested.data || []).map(normalizeApiTrack);
    const resolved = await this.request('/resolve', { type: 'artist', provider_id: artistId }).catch(() => null);
    return resolved?.data?.matched && Array.isArray(resolved.data.tracks)
      ? resolved.data.tracks.map(normalizeApiTrack)
      : [];
  }

  async getAlbum(albumId) {
    const key = String(albumId);
    if (this.albumCache.has(key)) return this.albumCache.get(key);
    const direct = await this.request(`/albums/${encodeURIComponent(key)}`).catch(() => null);
    if (direct?.success) {
      const album = normalizeApiAlbum(direct.data);
      this.albumCache.set(key, album);
      return album;
    }
    const resolved = await this.request('/resolve', { type: 'album', provider_id: key }).catch(() => null);
    if (resolved?.data?.matched && resolved.data.item) {
      const album = normalizeApiAlbum(resolved.data.item);
      if (Array.isArray(resolved.data.tracks)) {
        this.albumTracksCache.set(key, resolved.data.tracks.map(normalizeApiTrack));
      }
      this.albumCache.set(key, album);
      return album;
    }
    return null;
  }

  async getAlbumTracks(albumId) {
    const key = String(albumId);
    if (this.albumTracksCache.has(key)) {
      return { album: await this.getAlbum(key), tracks: this.albumTracksCache.get(key) };
    }
    const nested = await this.request(`/albums/${encodeURIComponent(key)}/tracks`).catch(() => null);
    if (nested?.success) {
      const albumDirect = await this.request(`/albums/${encodeURIComponent(key)}`).catch(() => null);
      const album = albumDirect?.success ? normalizeApiAlbum(albumDirect.data) : null;
      const tracks = (nested.data || []).map(normalizeApiTrack);
      this.albumTracksCache.set(key, tracks);
      if (album) this.albumCache.set(key, album);
      return { album, tracks };
    }
    const resolved = await this.request('/resolve', { type: 'album', provider_id: key }).catch(() => null);
    if (resolved?.data?.matched && resolved.data.item) {
      const album = normalizeApiAlbum(resolved.data.item);
      const tracks = (resolved.data.tracks || []).map(normalizeApiTrack);
      this.albumTracksCache.set(key, tracks);
      this.albumCache.set(key, album);
      return { album, tracks };
    }
    throw new Error('Release not found');
  }

  async getTrack(trackId) {
    const key = String(trackId);
    if (this.trackCache.has(key)) return this.trackCache.get(key);
    const direct = await this.request(`/tracks/${encodeURIComponent(key)}`).catch(() => null);
    if (direct?.success) {
      const track = normalizeApiTrack(direct.data);
      this.trackCache.set(key, track);
      return track;
    }
    const resolved = await this.request('/resolve', { type: 'track', provider_id: key }).catch(() => null);
    if (resolved?.data?.matched && resolved.data.item) {
      const track = normalizeApiTrack(resolved.data.item);
      this.trackCache.set(key, track);
      return track;
    }
    return null;
  }

  async search(query) {
    const payload = await this.request('/search', { q: query, limit: CONFIG.searchLimit }).catch(() => null);
    const data = payload?.success ? payload.data : null;
    if (!data) throw new Error('Search could not be completed.');
    return {
      tracks: shirinFocusFilter((data.tracks || []).map(normalizeApiTrack), 'track'),
      albums: shirinFocusFilter((data.albums || []).map(normalizeApiAlbum), 'album'),
      artists: shirinFocusFilter((data.artists || []).map(normalizeApiArtist), 'artist')
    };
  }

  async getLyrics() {
    // Same honest state as the direct provider: no licensed lyric text exists.
    return {
      status: 'unavailable',
      synced: false,
      lines: [],
      message: 'Lyrics are currently unavailable. No licensed lyric source is configured.'
    };
  }

  async getPlayback(track) {
    if (!track?.id) return null;
    // Owned tracks have no audio until licensing resolves (R-01).
    if (track.source === 'owned') return null;
    try {
      const resolved = await this.request('/resolve', { type: 'track', provider_id: track.id }).catch(() => null);
      const item = resolved?.data?.matched ? resolved.data.item : null;
      if (item?.preview) {
        const refreshed = normalizeApiTrack(item);
        this.trackCache.set(String(track.id), refreshed);
        return {
          type: 'preview',
          url: refreshed.preview,
          duration: 30,
          label: '30-second authorized Deezer preview',
          track: { ...track, ...refreshed }
        };
      }
    } catch (error) {
      // Fall through to the cached preview, if any.
    }
    if (track.preview) {
      return {
        type: 'preview',
        url: track.preview,
        duration: 30,
        label: '30-second authorized Deezer preview',
        track
      };
    }
    return null;
  }
}

/**
 * Small, deliberately separate offline demo catalogue. It only exists for graceful layout
 * and local feature testing when a live provider cannot be reached; it contains no audio.
 */
class FallbackProvider extends MusicProvider {
  constructor() {
    super();
    this.artist = {
      id: 'fallback-shirin',
      name: 'Shirin David',
      picture: CONFIG.fallbackArtistArtwork,
      pictureSmall: CONFIG.fallbackArtistArtwork,
      albumCount: 4,
      fanCount: 0,
      providerUrl: '',
      source: 'fallback',
      bio: 'German rapper, singer and entrepreneur.'
    };
    this.albums = [
      { id: 'fallback-sab', title: 'Schlau aber blond', releaseDate: '2024-02-14', recordType: 'album', trackCount: 4 },
      { id: 'fallback-bbr', title: 'Bitches brauchen Rap', releaseDate: '2021-11-19', recordType: 'album', trackCount: 3 },
      { id: 'fallback-supersize', title: 'Supersize', releaseDate: '2019-09-20', recordType: 'album', trackCount: 3 },
      { id: 'fallback-single', title: 'Lächel doch mal', releaseDate: '2024-05-03', recordType: 'single', trackCount: 1 }
    ].map((album) => makeAlbumRecord({
      ...album,
      artistName: 'Shirin David',
      artistId: this.artist.id,
      artwork: CONFIG.fallbackArtwork,
      artworkSmall: CONFIG.fallbackArtwork,
      source: 'fallback'
    }));
    this.tracksByAlbum = {
      'fallback-sab': ['iconic', 'schlau aber blond', 'bauch beine po', 'liebe ist auch keine Lösung'],
      'fallback-bbr': ['Bitches brauchen Rap', 'Lieben wir', 'On Off'],
      'fallback-supersize': ['Gib ihm', 'Brillis', 'Fliegst du mit'],
      'fallback-single': ['Lächel doch mal']
    };
  }

  makeTracks(albumId) {
    const album = this.albums.find((entry) => entry.id === String(albumId));
    if (!album) return [];
    return (this.tracksByAlbum[album.id] || []).map((title, index) => makeTrackRecord({
      id: `${album.id}-${index + 1}`,
      title,
      artistName: 'Shirin David',
      artistId: this.artist.id,
      albumId: album.id,
      albumTitle: album.title,
      artwork: CONFIG.fallbackArtwork,
      artworkSmall: CONFIG.fallbackArtwork,
      duration: [108, 130, 134, 156][index] || 150,
      preview: null,
      providerUrl: '',
      explicit: false,
      position: index + 1,
      source: 'fallback'
    }));
  }

  async searchArtist(query) { return this.artist.name.toLowerCase().includes(String(query).toLowerCase()) ? [this.artist] : []; }
  async getArtist() { return this.artist; }
  async getAlbums() { return this.albums; }
  async getAlbum(id) { return this.albums.find((album) => album.id === String(id)) || null; }
  async getAlbumTracks(id) { const album = await this.getAlbum(id); return { album, tracks: this.makeTracks(id) }; }
  getArtistTopTracks() { return this.albums.flatMap((album) => this.makeTracks(album.id)).slice(0, CONFIG.topTrackLimit); }
  async getTrack(id) { return this.albums.flatMap((album) => this.makeTracks(album.id)).find((track) => track.id === String(id)) || null; }
  async getPlayback() { return null; }
  async search(query) {
    const needle = String(query).toLowerCase().trim();
    const tracks = this.albums.flatMap((album) => this.makeTracks(album.id)).filter((track) => `${track.title} ${track.artistName} ${track.albumTitle}`.toLowerCase().includes(needle));
    const albums = this.albums.filter((album) => `${album.title} ${album.artistName}`.toLowerCase().includes(needle));
    const artists = this.artist.name.toLowerCase().includes(needle) ? [this.artist] : [];
    return { tracks, albums, artists };
  }
}

const deezerFallbackProvider = new DeezerProvider();
const apiProvider = new ShirinApiProvider({ deezer: deezerFallbackProvider });
const fallbackProvider = new FallbackProvider();
// API-first when a base URL is configured; the direct JSONP provider is the
// automatic fallback and the default when apiBaseUrl is empty.
const liveProvider = apiProvider.enabled ? apiProvider : deezerFallbackProvider;

function cacheSnapshot(snapshot) {
  writeStorage(STORAGE_KEYS.catalogue, { savedAt: Date.now(), ...snapshot });
}

export function getCachedCatalogue() {
  const cached = readStorage(STORAGE_KEYS.catalogue, null);
  if (!cached?.artist || !Array.isArray(cached.albums) || !Array.isArray(cached.topTracks)) return null;
  const age = Date.now() - Number(cached.savedAt || 0);
  if (age > CONFIG.catalogueCacheTtlMs) return null;
  return {
    artist: cached.artist,
    albums: cached.albums,
    topTracks: cached.topTracks,
    source: 'cache',
    origin: cached.origin || 'deezer'
  };
}

export const MusicAPI = {
  provider: liveProvider,
  fallbackProvider,
  apiProvider,

  /** True when the app is wired to the Laravel backend (Phase 2.5). */
  get apiEnabled() {
    return apiProvider.enabled;
  },

  async bootstrapCatalogue() {
    if (apiProvider.enabled) {
      try {
        const featured = await apiProvider.getFeaturedCatalogue();
        const snapshot = {
          artist: featured.artist,
          albums: featured.albums,
          topTracks: featured.topTracks,
          origin: 'api'
        };
        cacheSnapshot(snapshot);
        return { ...snapshot, source: 'live' };
      } catch (error) {
        // API unavailable or empty: fall through to the direct provider
        // under the same watchdog. Never leave the catalogue empty by design.
      }
    }

    const artist = await deezerFallbackProvider.resolveArtist();
    const [albums, topTracks] = await Promise.all([
      deezerFallbackProvider.getAlbums(artist.id),
      deezerFallbackProvider.getArtistTopTracks(artist.id)
    ]);
    // The artist releases endpoint omits album-level track counts. Hydrate only the
    // full-length / EP records (usually a very small set); singles are explicitly
    // marked as one-track releases by the provider response.
    const countDetails = await Promise.all(albums
      .filter((album) => !album.trackCount && album.recordType !== 'single')
      .map(async (album) => {
        try { return await deezerFallbackProvider.getAlbum(album.id); }
        catch (error) { return album; }
      }));
    const countsById = new Map(countDetails.map((album) => [String(album.id), album.trackCount]));
    const enrichedAlbums = albums.map((album) => ({ ...album, trackCount: countsById.get(String(album.id)) || album.trackCount }));
    const snapshot = { artist, albums: enrichedAlbums, topTracks, origin: 'deezer' };
    cacheSnapshot(snapshot);
    return { ...snapshot, source: 'live' };
  },

  getFallbackCatalogue() {
    return {
      artist: fallbackProvider.artist,
      albums: fallbackProvider.albums,
      topTracks: fallbackProvider.getArtistTopTracks(),
      source: 'fallback'
    };
  },

  async searchArtist(query) { return liveProvider.searchArtist(query); },
  async getArtist(id, source = 'deezer-public') { return source === 'fallback' ? fallbackProvider.getArtist(id) : liveProvider.getArtist(id); },
  async getAlbums(artistId, source = 'deezer-public') { return source === 'fallback' ? fallbackProvider.getAlbums(artistId) : liveProvider.getAlbums(artistId); },
  async getAlbum(albumId, source = 'deezer-public') { return source === 'fallback' ? fallbackProvider.getAlbum(albumId) : liveProvider.getAlbum(albumId); },
  async getAlbumTracks(albumId, source = 'deezer-public') { return source === 'fallback' || String(albumId).startsWith('fallback-') ? fallbackProvider.getAlbumTracks(albumId) : liveProvider.getAlbumTracks(albumId); },
  async getArtistTopTracks(artistId, source = 'deezer-public') { return source === 'fallback' ? fallbackProvider.getArtistTopTracks(artistId) : liveProvider.getArtistTopTracks(artistId); },
  async getTrack(trackId, source = 'deezer-public') { return source === 'fallback' || String(trackId).startsWith('fallback-') ? fallbackProvider.getTrack(trackId) : liveProvider.getTrack(trackId); },
  async search(query) {
    try { return await liveProvider.search(query); }
    catch (error) {
      // In API mode the direct provider gets one chance before the demo catalogue.
      if (apiProvider.enabled) {
        try { return await deezerFallbackProvider.search(query); }
        catch (nestedError) { /* fall through */ }
      }
      return fallbackProvider.search(query);
    }
  },
  async getLyrics(track) { return (track?.source === 'fallback' ? fallbackProvider : liveProvider).getLyrics(track?.id); },
  async getPlayback(track) { return (track?.source === 'fallback' ? fallbackProvider : liveProvider).getPlayback(track); },
  async resolveHashItem(type, id) { return apiProvider.resolveHash(type, id); },
  releaseYear
};
