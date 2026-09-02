import { MusicAPI } from './api.js';
import { STORAGE_KEYS } from './config.js';
import { setAmbientForTrack } from './animations.js';
import { Favorites } from './favorites.js';
import { icon } from './icons.js';
import { LyricsController } from './lyrics.js';
import { PlayerController } from './player.js';
import {
  fullPlayerMarkup,
  persistentPlayerMarkup,
  playerPanelMarkup,
  playerProgressPercent,
  playerStatusMessage
} from './player-view.js';
import { QueueManager } from './queue.js';
import { Router } from './router.js';
import { SearchController } from './search.js';
import { AppState } from './state.js';
import { escapeAttribute, escapeHTML, formatDuration, safeExternalUrl, uniqueById, writeStorage } from './utils.js';
import {
  albumPageMarkup,
  albumSkeleton,
  errorState,
  imageMarkup,
  mobileNavigationMarkup,
  pageHeader,
  renderAlbums,
  renderArtist,
  renderHome,
  renderLibrary,
  renderSearch,
  renderSearchResults,
  renderTracks,
  sidebarMarkup
} from './views.js';

let elements = {};
let retryCatalogue = async () => {};
let renderToken = 0;
let scheduledChrome = false;
let pendingPlayerChrome = { reconcile: false, panel: false };
let dragQueueIndex = null;

function getState() { return AppState.get(); }
function encoded(value) { return encodeURIComponent(String(value ?? '')); }
function routePath(route) {
  if (!route) return '/home';
  if (route.name === 'album') return `/album/${encoded(route.params?.id)}`;
  if (route.name === 'track') return `/track/${encoded(route.params?.id)}`;
  const query = new URLSearchParams(route.query || {}).toString();
  return `/${route.name || 'home'}${query ? `?${query}` : ''}`;
}

function renderSidebar() {
  elements.sidebar.innerHTML = sidebarMarkup();
}

function renderMobileNavigation() {
  elements.mobileNav.innerHTML = mobileNavigationMarkup();
}

function trackSurfaceKey(track) {
  if (!track) return '';
  return [
    track.id,
    track.title,
    track.artistName,
    track.albumTitle,
    track.artwork,
    track.picture,
    track.artworkSmall,
    track.pictureSmall,
    track.source
  ].map((value) => String(value || '')).join('|');
}

function setText(node, value) {
  if (node && node.textContent !== String(value || '')) node.textContent = String(value || '');
}

function updateButtonIcon(button, iconName) {
  if (!button || button.dataset.renderedIcon === iconName) return;
  button.dataset.renderedIcon = iconName;
  // Only the SVG child changes; the interactive button itself keeps focus and identity.
  button.innerHTML = icon(iconName);
}

function updatePlaybackButton(button, state) {
  if (!button) return;
  const isLoading = state.playerStatus === 'loading' || state.playerStatus === 'buffering';
  const isPlaying = Boolean(state.isPlaying);
  const label = isPlaying ? 'Pause' : state.playerStatus === 'unavailable' ? 'Retry authorized preview' : 'Play';
  button.setAttribute('aria-label', label);
  button.classList.toggle('is-buffering', isLoading && button.classList.contains('player-controls__play'));
  if (isLoading) button.setAttribute('aria-busy', 'true');
  else button.removeAttribute('aria-busy');
  updateButtonIcon(button, isPlaying ? 'pause' : 'play');
}

function updatePlayerControls(root) {
  if (!root) return;
  const state = getState();
  const volume = state.muted ? 0 : state.volume;

  root.querySelectorAll('[data-action="toggle-playback"]').forEach((button) => updatePlaybackButton(button, state));
  root.querySelectorAll('.mini-player__art, [data-player-art]').forEach((art) => art.classList.toggle('is-playing', Boolean(state.isPlaying)));

  root.querySelectorAll('[data-action="toggle-mute"]').forEach((button) => {
    button.setAttribute('aria-label', state.muted ? 'Unmute' : 'Mute');
    updateButtonIcon(button, state.muted ? 'mute' : 'volume');
  });
  root.querySelectorAll('[data-control="volume"]').forEach((input) => {
    if (document.activeElement !== input) input.value = String(volume);
    input.style.setProperty('--range-progress', `${volume * 100}%`);
  });

  root.querySelectorAll('[data-player-shuffle]').forEach((button) => {
    button.classList.toggle('is-active', Boolean(state.shuffle));
    button.setAttribute('aria-pressed', String(Boolean(state.shuffle)));
  });
  root.querySelectorAll('[data-player-repeat]').forEach((button) => {
    const active = state.repeat !== 'off';
    button.classList.toggle('is-active', active);
    button.setAttribute('aria-label', `Repeat: ${state.repeat}`);
    button.setAttribute('aria-pressed', String(active));
    updateButtonIcon(button, state.repeat === 'one' ? 'repeatOne' : 'repeat');
  });
  root.querySelectorAll('[data-player-speed]').forEach((button) => {
    const active = state.playbackRate !== 1;
    button.classList.toggle('is-active', active);
    button.setAttribute('aria-label', `Playback speed ${state.playbackRate} times`);
    setText(button, `${state.playbackRate}×`);
  });
  root.querySelectorAll('[data-player-favorite]').forEach((button) => {
    const favorite = Boolean(state.currentTrack && Favorites.isTrackFavorite(state.currentTrack.id));
    button.classList.toggle('is-active', favorite);
    button.setAttribute('aria-pressed', String(favorite));
    button.setAttribute('aria-label', `${favorite ? 'Remove from' : 'Add to'} favorites`);
  });
  root.querySelectorAll('[data-player-preview-status]').forEach((node) => setText(node, playerStatusMessage(state.currentTrack, state.playerStatus)));
}

function updateSidebarNowPlaying() {
  const state = getState();
  const sidebar = elements.sidebar;
  if (!sidebar) return;
  const title = sidebar.querySelector('.sidebar__now-track');
  const artist = sidebar.querySelector('.sidebar__now-artist');
  if (!title || !artist) return;
  setText(title, state.currentTrack?.title || 'Nothing playing');
  setText(artist, state.currentTrack?.artistName || 'Choose a track');
}

function patchArtwork(container, markup) {
  if (!container) return;
  const template = document.createElement('template');
  template.innerHTML = markup.trim();
  const nextImage = template.content.firstElementChild;
  const image = container.querySelector('img');
  if (!(nextImage instanceof HTMLImageElement)) return;
  if (!(image instanceof HTMLImageElement)) {
    // This fallback is used only for an incomplete/corrupt shell, never during normal
    // state changes. Existing player images are attribute-patched below.
    container.innerHTML = markup;
    return;
  }

  const nextAttributes = new Set([...nextImage.attributes].map((attribute) => attribute.name));
  [...image.attributes].forEach((attribute) => {
    if (!nextAttributes.has(attribute.name)) image.removeAttribute(attribute.name);
  });
  [...nextImage.attributes].forEach((attribute) => {
    if (image.getAttribute(attribute.name) !== attribute.value) image.setAttribute(attribute.name, attribute.value);
  });
}

function patchPersistentTrack(player, track) {
  if (!player || !track) return;
  const key = trackSurfaceKey(track);
  if (player.dataset.trackSurfaceKey === key) return;
  player.dataset.trackSurfaceKey = key;
  player.setAttribute('aria-label', `Now playing: ${track.title}`);

  const artwork = player.querySelector('.mini-player__art');
  if (artwork) {
    artwork.setAttribute('aria-label', `Open now playing: ${track.title}`);
    // Artwork intentionally changes per track; both the shell and image node stay mounted.
    patchArtwork(artwork, imageMarkup(track, `Artwork for ${track.title}`, { sizes: '56px' }));
  }
  const meta = player.querySelector('.mini-player__meta');
  if (meta) meta.setAttribute('aria-label', `Open now playing: ${track.title}`);
  setText(player.querySelector('.mini-player__title'), track.title);
  setText(player.querySelector('.mini-player__artist'), track.artistName);
  setText(player.querySelector('.mini-player__preview'), track.source === 'fallback' ? 'DEMO' : 'PREVIEW');
}

function renderPersistentPlayer() {
  if (!elements.persistent) return;
  const track = getState().currentTrack;
  const player = elements.persistent.querySelector('.mini-player');
  if (!track) {
    if (player) elements.persistent.replaceChildren();
    return;
  }
  if (!player) {
    // A shell is mounted only once, when a track first becomes active.
    elements.persistent.innerHTML = persistentPlayerMarkup();
    const mountedPlayer = elements.persistent.querySelector('.mini-player');
    if (mountedPlayer) mountedPlayer.dataset.trackSurfaceKey = trackSurfaceKey(track);
  } else {
    patchPersistentTrack(player, track);
  }
  updatePlayerControls(elements.persistent);
  updatePlayerProgress();
}

function playerPanelKey(state) {
  if (!state.playerPanel) return '';
  if (state.playerPanel === 'lyrics') {
    const lyrics = state.lyrics || {};
    const lines = (lyrics.lines || []).map((line) => `${line.time || ''}:${line.text || ''}`).join('|');
    return ['lyrics', lyrics.trackId, lyrics.status, lyrics.synced, lyrics.message, lines].map((value) => String(value || '')).join('~');
  }
  const queue = (state.queue || []).map((track) => trackSurfaceKey(track)).join('|');
  return `queue~${state.currentTrack?.id || ''}~${queue}`;
}

function renderPlayerPanel({ force = false } = {}) {
  const root = elements.overlay?.querySelector('[data-player-panel-root]');
  if (!root) return;
  const state = getState();
  const key = playerPanelKey(state);
  if (!force && root.dataset.panelKey === key) return;
  // Panels are the only replaceable sub-region of the full player. This preserves every
  // transport, utility control, artwork wrapper and visualizer canvas behind the panel.
  root.innerHTML = state.playerPanel ? playerPanelMarkup(state.playerPanel) : '';
  root.dataset.panelKey = key;
}

function patchFullPlayerTrack(player, track) {
  if (!player || !track) return;
  const key = trackSurfaceKey(track);
  if (player.dataset.trackSurfaceKey === key) return;
  player.dataset.trackSurfaceKey = key;
  player.dataset.playerTrackId = String(track.id);

  const backdrop = player.querySelector('[data-player-backdrop]');
  patchArtwork(backdrop, imageMarkup(track, '', { eager: true, sizes: '(min-width: 768px) 640px, 100vw', minimumWidth: 640 }));
  const artwork = player.querySelector('[data-player-art]');
  patchArtwork(artwork, imageMarkup(track, `Artwork for ${track.title}`, { eager: true, sizes: '(min-width: 768px) 640px, 100vw', minimumWidth: 640 }));
  setText(player.querySelector('[data-player-album]'), track.albumTitle || 'Shirin David');
  setText(player.querySelector('[data-player-title]'), track.title);
  setText(player.querySelector('[data-player-artist]'), track.artistName);

  const favorite = player.querySelector('[data-player-favorite]');
  if (favorite) favorite.dataset.trackId = String(track.id);
  const share = player.querySelector('[data-action="share-track"]');
  if (share) {
    share.dataset.trackId = String(track.id);
    share.setAttribute('aria-label', `Share ${track.title}`);
  }
}

function renderPlayerOverlay() {
  if (!elements.overlay) return;
  const state = getState();
  const player = elements.overlay.querySelector('.full-player');
  if (!state.playerExpanded) {
    if (player) {
      PlayerController.attachVisualizer(null);
      elements.overlay.replaceChildren();
    }
    return;
  }

  // Switching between the empty and active layouts is structural. A track-to-track switch
  // is not: it is patched below so pressing next never tears down the full player.
  const hasTrackLayout = Boolean(player?.querySelector('[data-player-title]'));
  const needsShell = !player || hasTrackLayout !== Boolean(state.currentTrack);
  if (needsShell) {
    elements.overlay.innerHTML = fullPlayerMarkup();
    const mountedPlayer = elements.overlay.querySelector('.full-player');
    if (state.currentTrack && mountedPlayer) mountedPlayer.dataset.trackSurfaceKey = trackSurfaceKey(state.currentTrack);
    const panelRoot = mountedPlayer?.querySelector('[data-player-panel-root]');
    if (panelRoot) panelRoot.dataset.panelKey = playerPanelKey(state);
    PlayerController.attachVisualizer(mountedPlayer?.querySelector('#player-visualizer') || null);
  } else if (state.currentTrack) {
    patchFullPlayerTrack(player, state.currentTrack);
  }

  renderPlayerPanel();
  updatePlayerControls(elements.overlay);
  updatePlayerProgress();
}

function knownTracks() {
  const state = getState();
  const fromAlbums = Object.values(state.albumTracks || {}).flat();
  const searchTracks = state.search?.results?.tracks || [];
  return uniqueById([
    state.currentTrack,
    ...state.topTracks,
    ...fromAlbums,
    ...searchTracks,
    ...state.favorites.tracks,
    ...state.recentlyPlayed,
    ...state.queue
  ].filter(Boolean));
}

function findKnownTrack(trackId) {
  return knownTracks().find((track) => String(track.id) === String(trackId)) || null;
}

function findKnownAlbum(albumId) {
  const state = getState();
  const searchAlbums = state.search?.results?.albums || [];
  return [...state.albums, ...searchAlbums, ...state.favorites.albums].find((album) => String(album.id) === String(albumId)) || null;
}

async function resolveTrack(trackId, source = 'deezer-public') {
  const known = findKnownTrack(trackId);
  if (known) return known;
  try { return await MusicAPI.getTrack(trackId, source); }
  catch (error) { return null; }
}

async function loadAlbumTracks(albumId, source = null) {
  const state = getState();
  if (state.albumTracks[String(albumId)]?.length) return { album: findKnownAlbum(albumId), tracks: state.albumTracks[String(albumId)] };
  const knownAlbum = findKnownAlbum(albumId);
  const providerSource = source || knownAlbum?.source || (String(albumId).startsWith('fallback-') ? 'fallback' : 'deezer-public');
  const result = await MusicAPI.getAlbumTracks(albumId, providerSource);
  if (!result?.album) throw new Error('Release not found');
  const hydratedAlbum = { ...knownAlbum, ...result.album, trackCount: result.album.trackCount || result.tracks.length };
  const latest = getState();
  const albums = latest.albums.some((album) => String(album.id) === String(albumId))
    ? latest.albums.map((album) => String(album.id) === String(albumId) ? hydratedAlbum : album)
    : latest.albums;
  AppState.patch({
    albums,
    activeAlbum: hydratedAlbum,
    albumTracks: { ...latest.albumTracks, [String(albumId)]: result.tracks }
  });
  return { album: hydratedAlbum, tracks: result.tracks };
}

async function collectionForTrack(track, context = '') {
  const state = getState();
  if (context.startsWith('album:')) {
    const albumId = context.slice('album:'.length);
    try { return (await loadAlbumTracks(albumId, track.source)).tracks; }
    catch (error) { return [track]; }
  }
  if (context === 'top') return state.topTracks;
  if (context === 'search') return state.search.results.tracks;
  if (context === 'favorites') return state.favorites.tracks;
  if (context === 'recent') return state.recentlyPlayed;
  if (context === 'queue') return state.queue;
  return [track];
}

function setButtonBusy(button, busy) {
  if (!button) return;
  button.classList.toggle('is-busy', busy);
  button.disabled = busy;
}

function showToast(toast) {
  if (!toast?.id || !elements.toast) return;
  if (elements.toast.dataset.lastToast === toast.id) return;
  elements.toast.dataset.lastToast = toast.id;
  const iconName = toast.type === 'error' ? 'alert' : toast.type === 'success' ? 'check' : 'info';
  const node = document.createElement('div');
  node.className = `toast toast--${toast.type || 'info'}`;
  node.innerHTML = `${icon(iconName)}<span>${escapeHTML(toast.message)}</span>`;
  elements.toast.appendChild(node);
  window.setTimeout(() => {
    node.classList.add('is-leaving');
    node.addEventListener('animationend', () => node.remove(), { once: true });
  }, 3300);
  window.setTimeout(() => {
    if (getState().toast?.id === toast.id) AppState.set('toast', null);
  }, 3600);
}

function closeSheet() {
  elements.sheet.innerHTML = '';
  document.body.classList.remove('modal-open');
}

function openTrackMenu(track) {
  if (!track) return;
  const favorite = Favorites.isTrackFavorite(track.id);
  const albumAction = track.albumId ? `<button class="sheet-action" type="button" data-action="view-track-album" data-album-id="${escapeAttribute(track.albumId)}">${icon('album')} View album</button>` : '';
  const providerUrl = safeExternalUrl(track.providerUrl);
  elements.sheet.innerHTML = `<div class="sheet-backdrop" data-dismiss-sheet><section class="bottom-sheet" role="dialog" aria-modal="true" aria-labelledby="track-menu-title" data-sheet-content><div class="sheet-handle"></div><header class="sheet-header"><h2 id="track-menu-title">Track options</h2><button class="icon-button icon-button--small" type="button" data-close-sheet aria-label="Close track options">${icon('close')}</button></header><div class="sheet-track">${imageMarkup(track, `Artwork for ${track.title}`)}<div class="sheet-track__text"><strong>${escapeHTML(track.title)}</strong><span>${escapeHTML(track.artistName)}</span></div></div><div class="sheet-actions"><button class="sheet-action" type="button" data-action="play-track" data-track-id="${escapeAttribute(track.id)}" data-context="menu">${icon('play')} Play now</button><button class="sheet-action" type="button" data-action="play-next" data-track-id="${escapeAttribute(track.id)}">${icon('next')} Play next</button><button class="sheet-action" type="button" data-action="add-queue" data-track-id="${escapeAttribute(track.id)}">${icon('queue')} Add to queue</button><button class="sheet-action" type="button" data-action="toggle-track-favorite" data-track-id="${escapeAttribute(track.id)}">${icon('heart')} ${favorite ? 'Remove from favorites' : 'Add to favorites'}</button>${albumAction}<button class="sheet-action" type="button" data-action="share-track" data-track-id="${escapeAttribute(track.id)}">${icon('share')} Share</button>${providerUrl ? `<a class="sheet-action" href="${escapeAttribute(providerUrl)}" target="_blank" rel="noopener noreferrer">${icon('external')} Open in Deezer</a>` : ''}</div></section></div>`;
  document.body.classList.add('modal-open');
}

async function copyText(text) {
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(text);
      return true;
    }
    const field = document.createElement('textarea');
    field.value = text;
    field.style.position = 'fixed';
    field.style.opacity = '0';
    document.body.appendChild(field);
    field.select();
    const result = document.execCommand('copy');
    field.remove();
    return result;
  } catch (error) {
    return false;
  }
}

async function shareItem(item, kind) {
  if (!item) return;
  const path = kind === 'album' ? `/album/${encoded(item.id)}` : `/track/${encoded(item.id)}`;
  const url = safeExternalUrl(item.providerUrl) || `${window.location.origin}${window.location.pathname}#${path}`;
  const title = kind === 'album' ? item.title : `${item.title} — ${item.artistName}`;
  try {
    if (navigator.share) {
      await navigator.share({ title, text: `Listen to ${title} in SHIRIN.`, url });
      AppState.set('toast', { id: `share-${Date.now()}`, message: 'Share sheet opened.', type: 'success' });
      return;
    }
  } catch (error) {
    // User cancellation should be quiet.
    if (error?.name === 'AbortError') return;
  }
  const copied = await copyText(url);
  AppState.set('toast', { id: `copy-${Date.now()}`, message: copied ? 'Link copied to clipboard.' : 'Couldn’t copy the link.', type: copied ? 'success' : 'error' });
}

function refreshVisibleLibrary() {
  const route = getState().route;
  if (route.name === 'library' || route.name === 'favorites' || route.name === 'recent') renderRoute(route, { keepScroll: true });
}

function updatePlayerProgress() {
  const state = getState();
  const percent = playerProgressPercent();
  document.querySelectorAll('[data-control="seek"]').forEach((input) => {
    const max = Math.max(1, state.duration || state.currentTrack?.duration || 30);
    input.max = String(max);
    input.value = String(Math.min(state.currentTime, max));
    input.style.setProperty('--range-progress', `${percent}%`);
  });
  document.querySelectorAll('.mini-player__progress > span').forEach((node) => node.style.setProperty('--progress', `${percent}%`));
  const current = document.getElementById('player-current-time');
  const duration = document.getElementById('player-duration');
  if (current) current.textContent = formatDuration(state.currentTime);
  if (duration) duration.textContent = formatDuration(state.duration || state.currentTrack?.duration || 0);
}

function updateActiveLyric() {
  const state = getState();
  const index = state.activeLyricIndex;
  document.querySelectorAll('[data-lyric-index]').forEach((line) => {
    const lineIndex = Number(line.dataset.lyricIndex);
    line.classList.toggle('is-active', lineIndex === index);
    line.classList.toggle('is-past', lineIndex < index);
  });
  if (index >= 0) {
    const line = document.querySelector(`[data-lyric-index="${index}"]`);
    if (line) line.scrollIntoView({ block: 'center', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  }
}

function syncMountedPlayerChrome() {
  // This deliberately contains no markup generation. It is safe to call for every
  // transport-state change without ever remounting the mini player or full player.
  updatePlayerControls(elements.persistent);
  updatePlayerControls(elements.overlay);
  updatePlayerProgress();
  updateSidebarNowPlaying();
}

function schedulePlayerChrome({ reconcile = false, panel = false } = {}) {
  pendingPlayerChrome.reconcile ||= reconcile;
  pendingPlayerChrome.panel ||= panel;
  if (scheduledChrome) return;
  scheduledChrome = true;
  window.requestAnimationFrame(() => {
    scheduledChrome = false;
    const work = pendingPlayerChrome;
    pendingPlayerChrome = { reconcile: false, panel: false };

    // Reconciliation is reserved for a new track or opening/closing the full-player
    // layer. Buttons, progress, volume, speed, shuffle, repeat and favorites never
    // reach either renderer, so those interactions cannot rebuild player chrome.
    if (work.reconcile) {
      renderPersistentPlayer();
      renderPlayerOverlay();
    } else if (work.panel) {
      renderPlayerPanel();
    }
    syncMountedPlayerChrome();
  });
}

async function handleAction(actionElement) {
  const action = actionElement.dataset.action;
  const dataset = actionElement.dataset;
  if (!action) return;

  switch (action) {
    case 'play-popular': {
      const tracks = getState().topTracks;
      await PlayerController.playCollection(tracks);
      break;
    }
    case 'play-track': {
      const track = await resolveTrack(dataset.trackId);
      if (!track) {
        AppState.set('toast', { id: `missing-${Date.now()}`, message: 'That track is no longer available.', type: 'error' });
        break;
      }
      const collection = await collectionForTrack(track, dataset.context || '');
      await PlayerController.playTrack(track, { collection, autoplay: true });
      closeSheet();
      break;
    }
    case 'play-album':
    case 'shuffle-album': {
      setButtonBusy(actionElement, true);
      try {
        const album = findKnownAlbum(dataset.albumId);
        const { tracks } = await loadAlbumTracks(dataset.albumId, album?.source);
        await PlayerController.playCollection(tracks, { shuffle: action === 'shuffle-album' });
      } catch (error) {
        AppState.set('toast', { id: `album-error-${Date.now()}`, message: 'That release could not be played right now.', type: 'error' });
      } finally {
        setButtonBusy(actionElement, false);
      }
      break;
    }
    case 'toggle-playback':
      await PlayerController.toggle();
      break;
    case 'previous':
      await PlayerController.previous();
      break;
    case 'next':
      await PlayerController.next();
      break;
    case 'toggle-shuffle':
      PlayerController.toggleShuffle();
      break;
    case 'cycle-repeat':
      PlayerController.cycleRepeat();
      break;
    case 'cycle-speed':
      PlayerController.cyclePlaybackRate();
      break;
    case 'toggle-mute':
      PlayerController.toggleMute();
      break;
    case 'toggle-track-favorite': {
      const track = await resolveTrack(dataset.trackId);
      if (!track) break;
      const added = Favorites.toggleTrack(track);
      AppState.set('toast', { id: `favorite-${Date.now()}`, message: added ? 'Saved to favorites.' : 'Removed from favorites.', type: added ? 'success' : 'info' });
      refreshVisibleLibrary();
      schedulePlayerChrome();
      break;
    }
    case 'toggle-album-favorite': {
      const album = findKnownAlbum(dataset.albumId);
      if (!album) break;
      const added = Favorites.toggleAlbum(album);
      AppState.set('toast', { id: `album-favorite-${Date.now()}`, message: added ? 'Release saved to favorites.' : 'Release removed from favorites.', type: added ? 'success' : 'info' });
      refreshVisibleLibrary();
      if (getState().route.name === 'album') renderRoute(getState().route, { keepScroll: true });
      break;
    }
    case 'toggle-artist-favorite': {
      const following = Favorites.toggleArtist();
      AppState.set('toast', { id: `artist-favorite-${Date.now()}`, message: following ? 'You’re following Shirin David locally.' : 'Artist removed from your local favorites.', type: following ? 'success' : 'info' });
      renderRoute(getState().route, { keepScroll: true });
      break;
    }
    case 'track-menu': {
      const track = await resolveTrack(dataset.trackId);
      if (track) openTrackMenu(track);
      break;
    }
    case 'play-next': {
      const track = await resolveTrack(dataset.trackId);
      if (track) PlayerController.addToQueue(track, { next: true });
      closeSheet();
      break;
    }
    case 'add-queue': {
      const track = await resolveTrack(dataset.trackId);
      if (track) PlayerController.addToQueue(track);
      closeSheet();
      break;
    }
    case 'view-track-album':
      closeSheet();
      Router.navigate(`/album/${encoded(dataset.albumId)}`);
      break;
    case 'share-track': {
      const track = await resolveTrack(dataset.trackId);
      await shareItem(track, 'track');
      break;
    }
    case 'share-album':
      await shareItem(findKnownAlbum(dataset.albumId), 'album');
      break;
    case 'open-player-panel': {
      const panel = dataset.panel === 'lyrics' ? 'lyrics' : 'queue';
      AppState.set('playerPanel', panel);
      if (panel === 'lyrics') LyricsController.load(getState().currentTrack);
      if (getState().route.name !== 'player') Router.navigate('/player');
      else renderPlayerOverlay();
      break;
    }
    case 'close-player-panel':
      closePlayerPanelImmediately();
      break;
    case 'close-player':
      AppState.patch({ playerExpanded: false, playerPanel: null });
      Router.navigate(routePath(getState().lastContentRoute), { replace: true });
      break;
    case 'queue-play': {
      const track = await resolveTrack(dataset.trackId);
      if (track) await PlayerController.playTrack(track, { collection: getState().queue, autoplay: true });
      break;
    }
    case 'queue-remove':
      QueueManager.remove(dataset.trackId);
      AppState.set('toast', { id: `queue-remove-${Date.now()}`, message: 'Removed from queue.', type: 'info' });
      break;
    case 'queue-move':
      QueueManager.move(Number(dataset.from), Number(dataset.to));
      break;
    case 'queue-clear':
      QueueManager.clear();
      AppState.set('toast', { id: `queue-clear-${Date.now()}`, message: 'Queue cleared.', type: 'info' });
      break;
    case 'clear-recent':
      Favorites.clearRecentlyPlayed();
      AppState.set('toast', { id: `recent-clear-${Date.now()}`, message: 'Listening history cleared.', type: 'info' });
      refreshVisibleLibrary();
      break;
    case 'set-theme': {
      const theme = dataset.theme === 'midnight' ? 'midnight' : 'dynamic';
      AppState.set('theme', theme);
      document.documentElement.dataset.theme = theme;
      writeStorage(STORAGE_KEYS.theme, theme);
      if (theme === 'midnight') {
        document.documentElement.style.setProperty('--ambient-rgb', '53, 54, 76');
        document.documentElement.style.setProperty('--ambient-rgb-alt', '24, 31, 49');
      } else {
        document.documentElement.style.removeProperty('--ambient-rgb');
        document.documentElement.style.removeProperty('--ambient-rgb-alt');
        if (getState().currentTrack) setAmbientForTrack(getState().currentTrack);
      }
      AppState.set('toast', { id: `theme-${Date.now()}`, message: theme === 'midnight' ? 'Midnight ambience selected.' : 'Dynamic artwork ambience selected.', type: 'success' });
      refreshVisibleLibrary();
      break;
    }
    case 'clear-search': {
      SearchController.clear();
      const input = document.getElementById('music-search');
      if (input) { input.value = ''; input.focus(); }
      renderSearchResults();
      break;
    }
    case 'search-suggestion': {
      const input = document.getElementById('music-search');
      if (input) {
        input.value = dataset.query || '';
        input.focus();
        SearchController.queue(input.value);
      }
      break;
    }
    case 'retry-catalog':
      await retryCatalogue();
      break;
    case 'close-sheet':
      closeSheet();
      break;
    default:
      break;
  }
}

function closePlayerPanelImmediately() {
  if (!getState().playerPanel) return;
  AppState.set('playerPanel', null);
  renderPlayerPanel({ force: true });
}

/**
 * Modal close controls have their own listener on persistent roots. This deliberately
 * bypasses generic document action delegation, so an SVG/path click inside a close button
 * cannot be confused with a backdrop or a re-rendered panel.
 */
function handleModalLayerClick(event) {
  const target = event.target;
  if (!(target instanceof Element)) return;

  if (elements.sheet?.contains(target)) {
    if (target.closest('[data-close-sheet]') || target.matches('.sheet-backdrop[data-dismiss-sheet]')) {
      event.preventDefault();
      event.stopPropagation();
      closeSheet();
    }
    return;
  }

  if (elements.overlay?.contains(target)
    && (target.closest('[data-close-player-panel]') || target.matches('.player-panel-backdrop[data-dismiss-player-panel]'))) {
    event.preventDefault();
    event.stopPropagation();
    closePlayerPanelImmediately();
  }
}

function handleClick(event) {
  const target = event.target;
  // Backdrop metadata is separate from ordinary action dispatch. The persistent modal-root
  // listener above handles close buttons and direct backdrop clicks before this delegate.
  if (target instanceof Element && target.matches('.sheet-backdrop[data-dismiss-sheet]')) {
    event.preventDefault();
    closeSheet();
    return;
  }
  if (target instanceof Element && target.matches('.player-panel-backdrop[data-dismiss-player-panel]')) {
    event.preventDefault();
    closePlayerPanelImmediately();
    return;
  }
  if (target instanceof Element && target.closest('[data-skip-link]')) {
    event.preventDefault();
    elements.main?.focus({ preventScroll: false });
    return;
  }
  const routed = target instanceof Element ? target.closest('[data-route]') : null;
  if (routed) {
    event.preventDefault();
    Router.navigate(routed.dataset.route);
    return;
  }
  const actionElement = target instanceof Element ? target.closest('[data-action]') : null;
  if (!actionElement) return;
  event.preventDefault();
  handleAction(actionElement).catch(() => {
    AppState.set('toast', { id: `action-error-${Date.now()}`, message: 'Something went wrong. Please try again.', type: 'error' });
  });
}

function handleInput(event) {
  const target = event.target;
  if (target.id === 'music-search') {
    SearchController.queue(target.value);
    const clearButton = target.parentElement?.querySelector('[data-action="clear-search"]');
    if (clearButton) clearButton.hidden = !target.value;
    return;
  }
  if (target.dataset.control === 'seek') {
    PlayerController.seek(Number(target.value));
    target.style.setProperty('--range-progress', `${playerProgressPercent()}%`);
  }
  if (target.dataset.control === 'volume') {
    PlayerController.setVolume(Number(target.value));
    target.style.setProperty('--range-progress', `${Number(target.value) * 100}%`);
  }
}

function handleKeyDown(event) {
  if (event.key !== 'Escape') return;
  if (elements.sheet.innerHTML) {
    closeSheet();
    return;
  }
  if (getState().playerPanel) {
    AppState.set('playerPanel', null);
    return;
  }
  if (getState().playerExpanded) handleAction({ dataset: { action: 'close-player' } });
}

let playerSwipeStart = null;
function handlePointerDown(event) {
  const player = event.target.closest('[data-player-swipe]');
  if (!player || event.target.closest('button, input, [data-panel-content]')) return;
  playerSwipeStart = { y: event.clientY, x: event.clientX };
}
function handlePointerUp(event) {
  if (!playerSwipeStart) return;
  const deltaY = event.clientY - playerSwipeStart.y;
  const deltaX = Math.abs(event.clientX - playerSwipeStart.x);
  playerSwipeStart = null;
  if (deltaY > 92 && deltaX < 90 && getState().playerExpanded) handleAction({ dataset: { action: 'close-player' } });
}

function handleDragStart(event) {
  const row = event.target.closest('[data-queue-index]');
  if (!row) return;
  dragQueueIndex = Number(row.dataset.queueIndex);
  event.dataTransfer.effectAllowed = 'move';
  event.dataTransfer.setData('text/plain', String(dragQueueIndex));
}
function handleDragOver(event) {
  if (!event.target.closest('[data-queue-index]')) return;
  event.preventDefault();
  event.dataTransfer.dropEffect = 'move';
}
function handleDrop(event) {
  const row = event.target.closest('[data-queue-index]');
  if (!row || dragQueueIndex === null) return;
  event.preventDefault();
  QueueManager.move(dragQueueIndex, Number(row.dataset.queueIndex));
  dragQueueIndex = null;
}

async function renderAlbumRoute(route, token) {
  let album = findKnownAlbum(route.params.id);
  if (!album) {
    elements.main.innerHTML = `<section class="page">${pageHeader()}<div class="content-lane">${albumSkeleton(1)}</div></section>`;
    try { album = await MusicAPI.getAlbum(route.params.id); }
    catch (error) { album = null; }
  }
  if (token !== renderToken) return;
  if (!album) {
    elements.main.innerHTML = `<section class="page">${pageHeader()}${errorState('We couldn’t find this release.', 'It may no longer be available from the music provider.')}</section>`;
    return;
  }
  AppState.set('activeAlbum', album);
  const cached = getState().albumTracks[String(album.id)];
  elements.main.innerHTML = albumPageMarkup(album, cached, { loading: !cached });
  if (cached) return;
  try {
    const result = await loadAlbumTracks(album.id, album.source);
    if (token !== renderToken) return;
    elements.main.innerHTML = albumPageMarkup(result.album, result.tracks);
  } catch (error) {
    if (token !== renderToken) return;
    elements.main.innerHTML = albumPageMarkup(album, null, { failed: true });
  }
}

async function renderTrackRoute(route, token) {
  let track = findKnownTrack(route.params.id);
  if (!track) {
    elements.main.innerHTML = `<section class="page">${pageHeader()}<div class="content-lane">${albumSkeleton(1)}</div></section>`;
    track = await resolveTrack(route.params.id);
  }
  if (token !== renderToken) return;
  if (!track) {
    elements.main.innerHTML = `<section class="page">${pageHeader()}${errorState('We couldn’t find this track.', 'It may no longer be available from the music provider.')}</section>`;
    return;
  }
  elements.main.innerHTML = `<section class="page track-detail-page">${pageHeader()}<button class="button button--ghost button--small" type="button" data-route="/tracks">${icon('back')} Tracks</button><section class="album-detail__masthead content-lane"><div class="album-detail__cover">${imageMarkup(track, `Artwork for ${track.title}`, { eager: true })}</div><div class="album-detail__identity"><span class="eyebrow">Track</span><h1>${escapeHTML(track.title)}</h1><p>${escapeHTML(track.artistName)}</p><p class="album-detail__context">${escapeHTML(track.albumTitle || 'Shirin David')} · ${escapeHTML(formatDuration(track.duration))}</p><div class="album-detail__actions"><button class="button button--primary" type="button" data-action="play-track" data-track-id="${escapeAttribute(track.id)}" data-context="track">${icon('play')} Play preview</button><button class="button button--secondary" type="button" data-action="toggle-track-favorite" data-track-id="${escapeAttribute(track.id)}">${icon('heart')} Favorite</button><button class="icon-button" type="button" data-action="share-track" data-track-id="${escapeAttribute(track.id)}" aria-label="Share track">${icon('share')}</button></div></div></section><p class="provider-note">${icon('info')} Authorised preview playback is used only when supplied by the configured provider.</p></section>`;
}

export async function renderRoute(route, { keepScroll = false } = {}) {
  const token = ++renderToken;
  renderSidebar();
  renderMobileNavigation();
  renderPersistentPlayer();

  if (route.name === 'player') {
    renderPlayerOverlay();
    return;
  }

  renderPlayerOverlay();
  switch (route.name) {
    case 'artist':
      elements.main.innerHTML = renderArtist();
      break;
    case 'albums':
      elements.main.innerHTML = renderAlbums();
      break;
    case 'album':
      await renderAlbumRoute(route, token);
      break;
    case 'tracks':
      elements.main.innerHTML = renderTracks();
      break;
    case 'track':
      await renderTrackRoute(route, token);
      break;
    case 'search':
      elements.main.innerHTML = renderSearch();
      renderSearchResults();
      window.setTimeout(() => document.getElementById('music-search')?.focus(), 60);
      break;
    case 'library':
    case 'favorites':
    case 'recent':
      elements.main.innerHTML = renderLibrary();
      break;
    case 'home':
    default:
      elements.main.innerHTML = renderHome();
      break;
  }
  if (!keepScroll) {
    window.requestAnimationFrame(() => {
      window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
      elements.main?.focus({ preventScroll: true });
    });
  }
}

function onStateChange(state, changed) {
  if (changed.has('toast') && state.toast) showToast(state.toast);
  if (changed.has('isOnline')) elements.offline.hidden = state.isOnline;
  if (changed.has('currentTime')) {
    updatePlayerProgress();
    LyricsController.sync(state.currentTime);
  }
  if (changed.has('activeLyricIndex')) updateActiveLyric();
  if (changed.has('search') && state.route.name === 'search') renderSearchResults();

  const trackChanged = changed.has('currentTrack');
  const expandedChanged = changed.has('playerExpanded');
  const panelChanged = changed.has('playerPanel');
  const panelContentChanged = state.playerPanel === 'lyrics'
    ? changed.has('lyrics')
    : state.playerPanel === 'queue'
      ? changed.has('queue') || changed.has('queueIndex') || trackChanged
      : false;

  if (trackChanged) {
    LyricsController.reset();
    if (state.playerPanel === 'lyrics' && state.currentTrack) LyricsController.load(state.currentTrack);
  }

  const chromeKeys = ['isPlaying', 'playerStatus', 'duration', 'volume', 'muted', 'shuffle', 'repeat', 'playbackRate', 'favorites'];
  if (trackChanged || expandedChanged || panelChanged || panelContentChanged || chromeKeys.some((key) => changed.has(key))) {
    schedulePlayerChrome({
      reconcile: trackChanged || expandedChanged,
      panel: panelChanged || panelContentChanged
    });
  }
}

function attachImageFallbacks() {
  document.addEventListener('error', (event) => {
    const image = event.target;
    if (!(image instanceof HTMLImageElement)) return;
    const fallback = image.dataset.imageFallback;
    if (!fallback || image.dataset.didFallback) return;
    image.dataset.didFallback = 'true';
    // A failed responsive candidate can otherwise continue winning over `src`.
    image.removeAttribute('srcset');
    image.removeAttribute('sizes');
    image.src = fallback;
  }, true);
}

export const UI = {
  init({ onRetryCatalogue } = {}) {
    elements = {
      main: document.getElementById('main-content'),
      sidebar: document.getElementById('sidebar'),
      mobileNav: document.getElementById('mobile-nav'),
      persistent: document.getElementById('persistent-player'),
      overlay: document.getElementById('overlay-root'),
      sheet: document.getElementById('sheet-root'),
      toast: document.getElementById('toast-root'),
      offline: document.getElementById('offline-indicator')
    };
    retryCatalogue = onRetryCatalogue || retryCatalogue;
    // These roots stay mounted even as their sheet/panel contents change.
    elements.sheet?.addEventListener('click', handleModalLayerClick);
    elements.overlay?.addEventListener('click', handleModalLayerClick);
    document.addEventListener('click', handleClick);
    document.addEventListener('input', handleInput);
    document.addEventListener('keydown', handleKeyDown);
    document.addEventListener('pointerdown', handlePointerDown, { passive: true });
    document.addEventListener('pointerup', handlePointerUp, { passive: true });
    document.addEventListener('dragstart', handleDragStart);
    document.addEventListener('dragover', handleDragOver);
    document.addEventListener('drop', handleDrop);
    attachImageFallbacks();
    AppState.subscribe(onStateChange);
    elements.offline.hidden = getState().isOnline;
    renderSidebar();
    renderMobileNavigation();
    renderPersistentPlayer();
  },

  renderRoute,
  closeSheet,
  refresh() { return renderRoute(getState().route, { keepScroll: true }); }
};
