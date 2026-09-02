import { MusicAPI } from './api.js';
import { AppState } from './state.js';
import { debounce } from './utils.js';

class SearchControllerClass {
  constructor() {
    this.requestId = 0;
    this.debouncedSearch = debounce((query) => this.search(query), 350);
  }

  queue(query) {
    AppState.update('search', (current) => ({ ...current, query }));
    this.debouncedSearch(query);
  }

  async search(query) {
    const cleaned = String(query || '').trim();
    const requestId = ++this.requestId;
    if (cleaned.length < 2) {
      AppState.set('search', { query: cleaned, status: 'idle', results: { tracks: [], albums: [], artists: [] }, error: false });
      return;
    }
    AppState.set('search', { query: cleaned, status: 'loading', results: { tracks: [], albums: [], artists: [] }, error: false });
    try {
      const results = await MusicAPI.search(cleaned);
      if (requestId !== this.requestId) return;
      const total = results.tracks.length + results.albums.length + results.artists.length;
      AppState.set('search', { query: cleaned, status: total ? 'ready' : 'empty', results, error: false });
    } catch (error) {
      if (requestId !== this.requestId) return;
      AppState.set('search', { query: cleaned, status: 'error', results: { tracks: [], albums: [], artists: [] }, error: true });
    }
  }

  clear() {
    this.requestId += 1;
    AppState.set('search', { query: '', status: 'idle', results: { tracks: [], albums: [], artists: [] }, error: false });
  }
}

export const SearchController = new SearchControllerClass();
