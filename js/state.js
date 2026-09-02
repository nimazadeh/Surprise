import { CONFIG, STORAGE_KEYS } from './config.js';
import { clamp, readStorage, writeStorage } from './utils.js';

const savedPlayback = readStorage(STORAGE_KEYS.playback, {});
const savedQueue = readStorage(STORAGE_KEYS.queue, []);
const savedTheme = readStorage(STORAGE_KEYS.theme, 'dynamic');

const initialState = {
  route: { name: 'home', params: {}, query: {} },
  lastContentRoute: { name: 'home', params: {}, query: {} },
  catalogueStatus: 'loading', // loading | ready | degraded | error
  catalogueError: false,
  dataSource: 'loading', // live | cache | fallback
  artist: null,
  albums: [],
  topTracks: [],
  albumTracks: {},
  activeAlbum: null,
  search: { query: '', status: 'idle', results: { tracks: [], albums: [], artists: [] }, error: false },
  favorites: {
    tracks: readStorage(STORAGE_KEYS.favoritesTracks, []),
    albums: readStorage(STORAGE_KEYS.favoritesAlbums, []),
    artist: Boolean(readStorage(STORAGE_KEYS.favoriteArtist, false))
  },
  recentlyPlayed: readStorage(STORAGE_KEYS.recentlyPlayed, []),
  queue: Array.isArray(savedQueue) ? savedQueue : [],
  queueIndex: -1,
  currentTrack: null,
  playerStatus: 'idle', // idle | loading | playing | paused | buffering | ended | error | unavailable
  isPlaying: false,
  currentTime: 0,
  duration: 0,
  volume: clamp(savedPlayback.volume ?? 0.82, 0, 1),
  muted: Boolean(savedPlayback.muted),
  shuffle: Boolean(savedPlayback.shuffle),
  repeat: ['off', 'all', 'one'].includes(savedPlayback.repeat) ? savedPlayback.repeat : 'off',
  playbackRate: [0.75, 1, 1.25, 1.5].includes(savedPlayback.playbackRate) ? savedPlayback.playbackRate : 1,
  playerExpanded: false,
  playerPanel: null,
  lyrics: { status: 'idle', trackId: null, synced: false, lines: [], message: '' },
  activeLyricIndex: -1,
  isOnline: navigator.onLine,
  theme: savedTheme === 'midnight' ? 'midnight' : 'dynamic',
  toast: null
};

class Store {
  constructor(seed) {
    this.state = seed;
    this.listeners = new Set();
  }

  get() { return this.state; }

  set(key, value) {
    if (Object.is(this.state[key], value)) return;
    this.state[key] = value;
    this.emit(new Set([key]));
  }

  patch(values = {}) {
    const changed = new Set();
    Object.entries(values).forEach(([key, value]) => {
      if (!Object.is(this.state[key], value)) {
        this.state[key] = value;
        changed.add(key);
      }
    });
    if (changed.size) this.emit(changed);
  }

  update(key, updater) {
    this.set(key, updater(this.state[key]));
  }

  subscribe(listener) {
    this.listeners.add(listener);
    return () => this.listeners.delete(listener);
  }

  emit(changed) {
    this.listeners.forEach((listener) => {
      try { listener(this.state, changed); } catch (error) { console.error('State subscriber failed', error); }
    });
  }
}

export const AppState = new Store(initialState);

export function persistPlaybackPreferences() {
  const state = AppState.get();
  writeStorage(STORAGE_KEYS.playback, {
    volume: state.volume,
    muted: state.muted,
    shuffle: state.shuffle,
    repeat: state.repeat,
    playbackRate: state.playbackRate
  });
}

export function setOnlineStatus(isOnline) {
  AppState.set('isOnline', Boolean(isOnline));
}

export function resetTransientPlayerState() {
  AppState.patch({
    currentTime: 0,
    duration: 0,
    isPlaying: false,
    playerStatus: 'idle',
    activeLyricIndex: -1
  });
}

export const APP_LIMITS = Object.freeze({
  maxRecentTracks: CONFIG.maxRecentTracks
});
