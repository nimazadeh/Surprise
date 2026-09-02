import { STORAGE_KEYS } from './config.js';
import { makeTrackRecord, uniqueById, writeStorage } from './utils.js';
import { AppState } from './state.js';

function persistQueue(queue) {
  writeStorage(STORAGE_KEYS.queue, queue.map(makeTrackRecord).filter(Boolean));
}

export const QueueManager = {
  get items() { return AppState.get().queue; },

  replace(tracks, activeTrackId = null) {
    const queue = uniqueById((tracks || []).map(makeTrackRecord).filter(Boolean));
    const index = activeTrackId === null ? -1 : queue.findIndex((track) => String(track.id) === String(activeTrackId));
    AppState.patch({ queue, queueIndex: index });
    persistQueue(queue);
    return queue;
  },

  setCurrent(trackId) {
    const index = AppState.get().queue.findIndex((track) => String(track.id) === String(trackId));
    AppState.set('queueIndex', index);
    return index;
  },

  add(track, { next = false } = {}) {
    const item = makeTrackRecord(track);
    if (!item) return;
    const state = AppState.get();
    const existingIndex = state.queue.findIndex((entry) => String(entry.id) === item.id);
    const queue = [...state.queue];
    if (existingIndex !== -1) queue.splice(existingIndex, 1);
    const insertAt = next && state.queueIndex >= 0 ? state.queueIndex + 1 : queue.length;
    queue.splice(insertAt, 0, item);
    const currentId = state.currentTrack?.id;
    const currentIndex = queue.findIndex((entry) => String(entry.id) === String(currentId));
    AppState.patch({ queue, queueIndex: currentIndex });
    persistQueue(queue);
  },

  remove(trackId) {
    const state = AppState.get();
    const removedIndex = state.queue.findIndex((track) => String(track.id) === String(trackId));
    const queue = state.queue.filter((track) => String(track.id) !== String(trackId));
    let queueIndex = state.queueIndex;
    if (removedIndex !== -1 && removedIndex < queueIndex) queueIndex -= 1;
    if (removedIndex === queueIndex && !queue.length) queueIndex = -1;
    AppState.patch({ queue, queueIndex });
    persistQueue(queue);
  },

  clear() {
    AppState.patch({ queue: [], queueIndex: -1 });
    persistQueue([]);
  },

  move(fromIndex, toIndex) {
    const state = AppState.get();
    const queue = [...state.queue];
    if (fromIndex < 0 || fromIndex >= queue.length || toIndex < 0 || toIndex >= queue.length || fromIndex === toIndex) return;
    const [item] = queue.splice(fromIndex, 1);
    queue.splice(toIndex, 0, item);
    const currentId = state.currentTrack?.id;
    const queueIndex = queue.findIndex((track) => String(track.id) === String(currentId));
    AppState.patch({ queue, queueIndex });
    persistQueue(queue);
  },

  getNextIndex({ shuffle = false, repeat = 'off' } = {}) {
    const state = AppState.get();
    const { queue, queueIndex } = state;
    if (!queue.length) return -1;
    if (repeat === 'one' && queueIndex >= 0) return queueIndex;
    if (shuffle && queue.length > 1) {
      let candidate = queueIndex;
      while (candidate === queueIndex) candidate = Math.floor(Math.random() * queue.length);
      return candidate;
    }
    const next = queueIndex + 1;
    if (next < queue.length) return next;
    return repeat === 'all' ? 0 : -1;
  },

  getPreviousIndex() {
    const { queue, queueIndex } = AppState.get();
    if (!queue.length) return -1;
    if (queueIndex > 0) return queueIndex - 1;
    return AppState.get().repeat === 'all' ? queue.length - 1 : -1;
  }
};
