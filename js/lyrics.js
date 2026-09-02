import { MusicAPI } from './api.js';
import { AppState } from './state.js';

class LyricsControllerClass {
  constructor() {
    this.loadToken = 0;
    this.lastTrackId = null;
  }

  async load(track) {
    if (!track?.id) {
      AppState.patch({ lyrics: { status: 'idle', trackId: null, synced: false, lines: [], message: '' }, activeLyricIndex: -1 });
      return;
    }
    if (this.lastTrackId === String(track.id) && AppState.get().lyrics.status !== 'idle') return;
    this.lastTrackId = String(track.id);
    const token = ++this.loadToken;
    AppState.patch({
      lyrics: { status: 'loading', trackId: String(track.id), synced: false, lines: [], message: '' },
      activeLyricIndex: -1
    });
    try {
      const result = await MusicAPI.getLyrics(track);
      if (token !== this.loadToken) return;
      const lines = Array.isArray(result?.lines) ? result.lines.filter((line) => line?.text) : [];
      AppState.patch({
        lyrics: {
          status: result?.status || (lines.length ? 'ready' : 'unavailable'),
          trackId: String(track.id),
          synced: Boolean(result?.synced),
          lines,
          message: result?.message || (lines.length ? '' : 'Lyrics are currently unavailable.')
        },
        activeLyricIndex: -1
      });
    } catch (error) {
      if (token !== this.loadToken) return;
      AppState.patch({
        lyrics: {
          status: 'unavailable',
          trackId: String(track.id),
          synced: false,
          lines: [],
          message: 'Lyrics are currently unavailable.'
        },
        activeLyricIndex: -1
      });
    }
  }

  sync(time) {
    const lyrics = AppState.get().lyrics;
    if (!lyrics.synced || !lyrics.lines.length) return;
    let activeIndex = -1;
    lyrics.lines.forEach((line, index) => {
      if (Number(line.time) <= time) activeIndex = index;
    });
    if (activeIndex !== AppState.get().activeLyricIndex) AppState.set('activeLyricIndex', activeIndex);
  }

  reset() {
    this.lastTrackId = null;
    this.loadToken += 1;
    AppState.patch({ lyrics: { status: 'idle', trackId: null, synced: false, lines: [], message: '' }, activeLyricIndex: -1 });
  }
}

export const LyricsController = new LyricsControllerClass();
