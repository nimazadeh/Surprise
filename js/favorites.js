import { STORAGE_KEYS, CONFIG } from './config.js';
import { makeAlbumRecord, makeTrackRecord, writeStorage } from './utils.js';
import { AppState } from './state.js';

function sameId(item, id) { return String(item?.id) === String(id); }

function persistFavorites(favorites) {
  writeStorage(STORAGE_KEYS.favoritesTracks, favorites.tracks);
  writeStorage(STORAGE_KEYS.favoritesAlbums, favorites.albums);
  writeStorage(STORAGE_KEYS.favoriteArtist, favorites.artist);
}

export const Favorites = {
  isTrackFavorite(trackId) {
    return AppState.get().favorites.tracks.some((track) => sameId(track, trackId));
  },

  isAlbumFavorite(albumId) {
    return AppState.get().favorites.albums.some((album) => sameId(album, albumId));
  },

  toggleTrack(track) {
    if (!track?.id) return false;
    const favorites = AppState.get().favorites;
    const exists = this.isTrackFavorite(track.id);
    const tracks = exists
      ? favorites.tracks.filter((item) => !sameId(item, track.id))
      : [makeTrackRecord(track), ...favorites.tracks].slice(0, 100);
    const next = { ...favorites, tracks };
    AppState.set('favorites', next);
    persistFavorites(next);
    return !exists;
  },

  toggleAlbum(album) {
    if (!album?.id) return false;
    const favorites = AppState.get().favorites;
    const exists = this.isAlbumFavorite(album.id);
    const albums = exists
      ? favorites.albums.filter((item) => !sameId(item, album.id))
      : [makeAlbumRecord(album), ...favorites.albums].slice(0, 100);
    const next = { ...favorites, albums };
    AppState.set('favorites', next);
    persistFavorites(next);
    return !exists;
  },

  toggleArtist() {
    const favorites = AppState.get().favorites;
    const next = { ...favorites, artist: !favorites.artist };
    AppState.set('favorites', next);
    persistFavorites(next);
    return next.artist;
  },

  addRecentlyPlayed(track) {
    if (!track?.id) return;
    const saved = makeTrackRecord(track);
    const history = AppState.get().recentlyPlayed.filter((item) => !sameId(item, saved.id));
    history.unshift(saved);
    const limited = history.slice(0, CONFIG.maxRecentTracks);
    AppState.set('recentlyPlayed', limited);
    writeStorage(STORAGE_KEYS.recentlyPlayed, limited);
  },

  clearRecentlyPlayed() {
    AppState.set('recentlyPlayed', []);
    writeStorage(STORAGE_KEYS.recentlyPlayed, []);
  }
};
