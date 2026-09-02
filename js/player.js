import { MusicAPI } from './api.js';
import { setAmbientForTrack } from './animations.js';
import { Favorites } from './favorites.js';
import { QueueManager } from './queue.js';
import { AppState, persistPlaybackPreferences } from './state.js';
import { clamp, createId, makeTrackRecord } from './utils.js';

const MEDIA_ERR_ABORTED = 1;

function postToast(message, type = 'info') {
  AppState.set('toast', { id: createId('toast'), message, type });
}

/**
 * One global playback controller. It owns exactly one active HTMLAudioElement at a time.
 * A fresh element is created for each provider preview so late `error`, `pause`, and
 * `loadstart` events from a retired URL can never overwrite state for the next track.
 */
class GlobalPlayerController {
  constructor() {
    this.audio = null;
    this.initialized = false;
    this.loadToken = 0;
    this.userPaused = false;
    this.visualizerCanvas = null;
    this.visualizerFrame = null;
    this.playedTrackId = null;
    this.previewRetries = new Set();
  }

  initialize() {
    if (this.initialized) return;
    this.initialized = true;
    this.audio = this.createAudioElement(this.loadToken);
  }

  createAudioElement(token) {
    const audio = new Audio();
    const preferences = AppState.get();
    audio.preload = 'metadata';
    // Keep the saved volume while muted. It makes unmute restore the user's level instead
    // of leaving a subsequent preview at zero volume.
    audio.volume = preferences.volume;
    audio.muted = preferences.muted;
    audio.playbackRate = preferences.playbackRate;

    const isActive = () => this.audio === audio && token === this.loadToken;
    audio.addEventListener('loadstart', () => {
      if (!isActive() || !AppState.get().currentTrack) return;
      AppState.patch({ playerStatus: 'loading', isPlaying: false });
    });
    audio.addEventListener('loadedmetadata', () => {
      if (!isActive()) return;
      const duration = Number.isFinite(audio.duration) ? audio.duration : AppState.get().duration;
      AppState.set('duration', duration || 0);
    });
    audio.addEventListener('durationchange', () => {
      if (!isActive()) return;
      const duration = Number.isFinite(audio.duration) ? audio.duration : 0;
      if (duration) AppState.set('duration', duration);
    });
    audio.addEventListener('canplay', () => {
      if (!isActive()) return;
      const state = AppState.get();
      if (!state.isPlaying && state.playerStatus !== 'unavailable') AppState.set('playerStatus', 'paused');
    });
    audio.addEventListener('playing', () => {
      if (!isActive()) return;
      const track = AppState.get().currentTrack;
      AppState.patch({ playerStatus: 'playing', isPlaying: true });
      if (track && this.playedTrackId !== String(track.id)) {
        this.playedTrackId = String(track.id);
        Favorites.addRecentlyPlayed(track);
      }
      this.startVisualizer();
    });
    audio.addEventListener('pause', () => {
      if (!isActive() || audio.ended) return;
      const state = AppState.get();
      if (state.currentTrack && state.playerStatus !== 'loading' && state.playerStatus !== 'unavailable') {
        AppState.patch({ playerStatus: 'paused', isPlaying: false });
      }
    });
    audio.addEventListener('waiting', () => {
      if (!isActive()) return;
      if (AppState.get().currentTrack && !audio.paused) AppState.patch({ playerStatus: 'buffering', isPlaying: false });
    });
    audio.addEventListener('timeupdate', () => {
      if (isActive()) AppState.set('currentTime', audio.currentTime || 0);
    });
    audio.addEventListener('ended', () => {
      if (isActive()) this.handleEnded();
    });
    audio.addEventListener('error', () => {
      if (!isActive()) return;
      // Intentional source retirement produces MEDIA_ERR_ABORTED in some engines. It is
      // not a playback failure for the newly selected track and must never trigger retry.
      if (!audio.currentSrc || audio.error?.code === MEDIA_ERR_ABORTED) return;
      const state = AppState.get();
      const track = state.currentTrack;
      if (!track || state.playerStatus === 'unavailable') return;

      // Signed provider previews can occasionally expire between metadata resolution and
      // media loading. Refresh once automatically for this user-selected track; a second
      // failure remains an honest visible error instead of an endless reload loop.
      const retryKey = String(track.id);
      if (!this.userPaused && !this.previewRetries.has(retryKey)) {
        this.previewRetries.add(retryKey);
        window.setTimeout(() => {
          if (!isActive() || this.userPaused) return;
          this.playTrack(track, { collection: AppState.get().queue, autoplay: true, retrying: true }).catch(() => {});
        }, 0);
        return;
      }

      AppState.patch({ playerStatus: 'error', isPlaying: false });
      postToast('That preview could not play. Please try another track.', 'error');
    });
    audio.addEventListener('volumechange', () => {
      if (!isActive()) return;
      const muted = audio.muted || audio.volume === 0;
      AppState.patch({ volume: audio.volume, muted });
      persistPlaybackPreferences();
    });
    return audio;
  }

  retireAudio(audio) {
    if (!audio) return;
    try {
      audio.pause();
      audio.removeAttribute('src');
      audio.load();
    } catch (error) {
      // A browser may have already released a detached/failed media resource.
    }
  }

  async playTrack(track, { collection = null, autoplay = true, retrying = false } = {}) {
    if (!track?.id) return false;
    this.initialize();
    const stateBefore = AppState.get();
    const normalizedTrack = makeTrackRecord(track);
    if (!retrying) this.previewRetries.delete(String(normalizedTrack.id));

    if (Array.isArray(collection) && collection.length) {
      QueueManager.replace(collection, normalizedTrack.id);
    } else if (stateBefore.queue.some((entry) => String(entry.id) === String(normalizedTrack.id))) {
      QueueManager.setCurrent(normalizedTrack.id);
    } else {
      QueueManager.replace([normalizedTrack], normalizedTrack.id);
    }

    // Advance before retiring the old source. Its queued media events are now ignored by
    // the per-element event guards rather than mutating the next track's player state.
    const token = ++this.loadToken;
    this.playedTrackId = null;
    this.userPaused = !autoplay;
    this.retireAudio(this.audio);
    AppState.patch({
      currentTrack: normalizedTrack,
      currentTime: 0,
      duration: normalizedTrack.duration || 0,
      playerStatus: 'loading',
      isPlaying: false,
      playerPanel: AppState.get().playerPanel
    });
    setAmbientForTrack(normalizedTrack);

    let playback;
    try {
      playback = await MusicAPI.getPlayback(normalizedTrack);
    } catch (error) {
      playback = null;
    }
    if (token !== this.loadToken) return false;

    if (!playback?.url) {
      AppState.patch({ playerStatus: 'unavailable', isPlaying: false });
      postToast('No authorized preview is available for this track.', 'info');
      return false;
    }

    const playableTrack = makeTrackRecord({ ...normalizedTrack, ...(playback.track || {}) });
    AppState.set('currentTrack', playableTrack);
    const audio = this.createAudioElement(token);
    this.audio = audio;
    audio.src = playback.url;
    audio.currentTime = 0;
    audio.playbackRate = AppState.get().playbackRate;
    audio.muted = AppState.get().muted;
    audio.volume = AppState.get().volume;
    audio.load();

    if (!autoplay) {
      AppState.set('playerStatus', 'paused');
      return true;
    }

    return this.play({ allowLoading: true });
  }

  async playCollection(tracks, { shuffle = false, startTrackId = null } = {}) {
    const collection = (tracks || []).filter((track) => track?.id);
    if (!collection.length) {
      postToast('There are no playable tracks in this release.', 'info');
      return false;
    }
    let selectedIndex = Math.max(0, collection.findIndex((track) => String(track.id) === String(startTrackId)));
    if (shuffle && collection.length > 1) selectedIndex = Math.floor(Math.random() * collection.length);
    if (shuffle !== AppState.get().shuffle) {
      AppState.set('shuffle', shuffle);
      persistPlaybackPreferences();
    }
    return this.playTrack(collection[selectedIndex], { collection, autoplay: true });
  }

  async play({ allowLoading = false } = {}) {
    this.initialize();
    const state = AppState.get();
    if (!state.currentTrack) {
      postToast('Choose a track to start listening.', 'info');
      return false;
    }
    // Do not launch a second provider lookup while the current selection is already
    // resolving. The internal call immediately after assigning a fresh URL is allowed
    // to start media while its metadata is still loading.
    if (state.playerStatus === 'loading' && !allowLoading) return false;
    if (!this.audio?.src || state.playerStatus === 'unavailable' || state.playerStatus === 'error') {
      // A user gesture retries tokenized provider playback without trying to evade autoplay rules.
      return this.playTrack(state.currentTrack, { collection: state.queue, autoplay: true });
    }
    try {
      this.userPaused = false;
      await this.audio.play();
      return true;
    } catch (error) {
      // A media-resource failure is handled by the element's guarded `error` listener,
      // including its one fresh-preview retry. Do not overwrite that loading state here.
      if (this.audio?.error) return false;
      AppState.patch({ playerStatus: 'paused', isPlaying: false });
      postToast('Tap play to start the authorized preview.', 'info');
      return false;
    }
  }

  pause() {
    if (!this.audio) return;
    this.userPaused = true;
    this.audio.pause();
  }

  toggle() {
    const state = AppState.get();
    return state.isPlaying ? this.pause() : this.play();
  }

  async next({ fromEnded = false } = {}) {
    const state = AppState.get();
    const nextIndex = QueueManager.getNextIndex({ shuffle: state.shuffle, repeat: state.repeat });
    if (nextIndex < 0) {
      if (fromEnded) {
        AppState.patch({ playerStatus: 'ended', isPlaying: false });
        postToast('Preview complete. Add more tracks to continue.', 'info');
      } else {
        postToast('You’re at the end of the queue.', 'info');
      }
      return false;
    }
    const nextTrack = state.queue[nextIndex];
    return this.playTrack(nextTrack, { collection: state.queue, autoplay: true });
  }

  async previous() {
    this.initialize();
    if (this.audio?.currentTime > 3) {
      this.seek(0);
      return this.play();
    }
    const state = AppState.get();
    const previousIndex = QueueManager.getPreviousIndex();
    if (previousIndex < 0) {
      this.seek(0);
      postToast('This is the first track in the queue.', 'info');
      return false;
    }
    return this.playTrack(state.queue[previousIndex], { collection: state.queue, autoplay: true });
  }

  seek(seconds) {
    if (!this.audio || !Number.isFinite(Number(seconds))) return;
    const duration = Number.isFinite(this.audio.duration) ? this.audio.duration : AppState.get().duration;
    if (!duration) return;
    const safeTime = clamp(seconds, 0, duration);
    this.audio.currentTime = safeTime;
    AppState.set('currentTime', safeTime);
  }

  setVolume(value) {
    this.initialize();
    const volume = clamp(value, 0, 1);
    this.audio.muted = false;
    this.audio.volume = volume;
    AppState.patch({ volume, muted: volume === 0 });
    persistPlaybackPreferences();
  }

  toggleMute() {
    this.initialize();
    const state = AppState.get();
    const muted = !state.muted;
    this.audio.muted = muted;
    if (!muted && this.audio.volume === 0) this.audio.volume = state.volume || .82;
    AppState.set('muted', muted);
    persistPlaybackPreferences();
  }

  toggleShuffle() {
    AppState.set('shuffle', !AppState.get().shuffle);
    persistPlaybackPreferences();
    postToast(AppState.get().shuffle ? 'Shuffle is on.' : 'Shuffle is off.', 'info');
  }

  cycleRepeat() {
    const order = ['off', 'all', 'one'];
    const current = AppState.get().repeat;
    const next = order[(order.indexOf(current) + 1) % order.length];
    AppState.set('repeat', next);
    persistPlaybackPreferences();
    postToast(next === 'off' ? 'Repeat is off.' : next === 'all' ? 'Repeat queue is on.' : 'Repeat track is on.', 'info');
  }

  cyclePlaybackRate() {
    const rates = [.75, 1, 1.25, 1.5];
    const current = AppState.get().playbackRate;
    const next = rates[(rates.indexOf(current) + 1) % rates.length];
    this.initialize();
    this.audio.playbackRate = next;
    AppState.set('playbackRate', next);
    persistPlaybackPreferences();
    postToast(`Preview speed: ${next}×`, 'info');
  }

  addToQueue(track, { next = false } = {}) {
    QueueManager.add(track, { next });
    postToast(next ? 'Added to play next.' : 'Added to your queue.', 'success');
  }

  async handleEnded() {
    const state = AppState.get();
    if (state.repeat === 'one' && state.currentTrack) {
      this.seek(0);
      await this.play();
      return;
    }
    await this.next({ fromEnded: true });
  }

  attachVisualizer(canvas) {
    this.visualizerCanvas = canvas || null;
    if (!canvas) {
      this.stopVisualizer();
      return;
    }
    this.startVisualizer();
  }

  startVisualizer() {
    if (!this.visualizerCanvas || this.visualizerFrame || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const canvas = this.visualizerCanvas;
    const context = canvas.getContext('2d');
    if (!context) return;

    const draw = () => {
      if (!this.visualizerCanvas || !canvas.isConnected) {
        this.stopVisualizer();
        return;
      }
      const rect = canvas.getBoundingClientRect();
      const ratio = Math.min(window.devicePixelRatio || 1, 2);
      const width = Math.max(1, Math.floor(rect.width * ratio));
      const height = Math.max(1, Math.floor(rect.height * ratio));
      if (canvas.width !== width || canvas.height !== height) {
        canvas.width = width;
        canvas.height = height;
      }
      context.clearRect(0, 0, width, height);
      const isPlaying = AppState.get().isPlaying;
      const playbackTime = this.audio?.currentTime || 0;
      const bars = window.matchMedia('(max-width: 480px)').matches ? 13 : 18;
      const gap = width * .022;
      const barWidth = (width - gap * (bars - 1)) / bars;
      for (let index = 0; index < bars; index += 1) {
        // Decorative motion intentionally stays on Canvas rather than routing third-party
        // audio through Web Audio. Native media output remains reliable on Safari, Chrome,
        // Firefox, and any authorized provider that supports ordinary HTML5 audio playback.
        const phase = playbackTime * 5.2 + index * .68;
        const amplitude = isPlaying ? .18 + Math.abs(Math.sin(phase)) * .22 : .03;
        const barHeight = Math.max(2 * ratio, height * amplitude);
        const x = index * (barWidth + gap);
        const y = height - barHeight;
        context.fillStyle = `rgba(255, 240, 248, ${isPlaying ? .68 : .18})`;
        context.beginPath();
        context.roundRect(x, y, barWidth, barHeight, barWidth / 2);
        context.fill();
      }
      this.visualizerFrame = window.requestAnimationFrame(draw);
    };
    this.visualizerFrame = window.requestAnimationFrame(draw);
  }

  stopVisualizer() {
    if (this.visualizerFrame) window.cancelAnimationFrame(this.visualizerFrame);
    this.visualizerFrame = null;
  }
}

export const PlayerController = new GlobalPlayerController();
