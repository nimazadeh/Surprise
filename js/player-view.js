import { Favorites } from './favorites.js';
import { icon } from './icons.js';
import { AppState } from './state.js';
import { escapeAttribute, escapeHTML, formatDuration, pluralize } from './utils.js';
import { imageMarkup } from './views.js';

function getState() { return AppState.get(); }

function transportButtons({ compact = false } = {}) {
  const state = getState();
  const status = state.playerStatus;
  const isLoading = status === 'loading' || status === 'buffering';
  const playbackIcon = state.isPlaying ? 'pause' : 'play';
  const playbackLabel = state.isPlaying ? 'Pause' : status === 'unavailable' ? 'Retry authorized preview' : 'Play';
  if (compact) {
    return `<div class="mini-player__transport"><button class="icon-button" type="button" data-action="previous" aria-label="Previous track">${icon('previous')}</button><button class="icon-button mini-player__toggle" type="button" data-action="toggle-playback" aria-label="${playbackLabel}" ${isLoading ? 'aria-busy="true"' : ''}>${icon(playbackIcon)}</button><button class="icon-button" type="button" data-action="next" aria-label="Next track">${icon('next')}</button></div>`;
  }
  return `<div class="player-controls"><button class="icon-button" type="button" data-action="previous" aria-label="Previous track">${icon('previous')}</button><button class="icon-button player-controls__play ${isLoading ? 'is-buffering' : ''}" type="button" data-action="toggle-playback" aria-label="${playbackLabel}" ${isLoading ? 'aria-busy="true"' : ''}>${icon(playbackIcon)}</button><button class="icon-button" type="button" data-action="next" aria-label="Next track">${icon('next')}</button></div>`;
}

export function playerProgressPercent() {
  const state = getState();
  const duration = Number(state.duration) || 0;
  return duration ? Math.min(100, Math.max(0, state.currentTime / duration * 100)) : 0;
}

export function persistentPlayerMarkup() {
  const state = getState();
  const track = state.currentTrack;
  if (!track) return '';
  const progress = playerProgressPercent();
  const toggleIcon = state.isPlaying ? 'pause' : 'play';
  const toggleLabel = state.isPlaying ? 'Pause' : 'Play';
  return `<section class="mini-player" aria-label="Now playing: ${escapeAttribute(track.title)}">
    <button class="mini-player__art ${state.isPlaying ? 'is-playing' : ''}" type="button" data-route="/player" aria-label="Open now playing">${imageMarkup(track, `Artwork for ${track.title}`, { sizes: '56px' })}</button>
    <button class="mini-player__meta" type="button" data-route="/player" aria-label="Open now playing"><strong class="mini-player__title">${escapeHTML(track.title)}</strong><span class="mini-player__artist">${escapeHTML(track.artistName)}</span></button>
    <button class="icon-button mini-player__toggle mini-player__mobile-toggle" type="button" data-action="toggle-playback" aria-label="${toggleLabel}">${icon(toggleIcon)}</button>
    ${transportButtons({ compact: true })}
    <div class="mini-player__desktop-right"><button class="player-utility" type="button" data-action="open-player-panel" data-panel="lyrics" aria-label="Open lyrics">${icon('lyrics')}</button><button class="player-utility" type="button" data-action="open-player-panel" data-panel="queue" aria-label="Open queue">${icon('queue')}</button><button class="player-utility" type="button" data-action="toggle-mute" aria-label="${state.muted ? 'Unmute' : 'Mute'}">${icon(state.muted ? 'mute' : 'volume')}</button><label class="mini-player__volume"><span class="sr-only">Volume</span><input class="player-range" type="range" min="0" max="1" step="0.01" value="${state.muted ? 0 : state.volume}" data-control="volume" aria-label="Volume" style="--range-progress:${(state.muted ? 0 : state.volume) * 100}%"></label></div>
    <span class="mini-player__preview">${track.source === 'fallback' ? 'DEMO' : 'PREVIEW'}</span>
    <div class="mini-player__progress" aria-hidden="true"><span style="--progress:${progress}%"></span></div>
  </section>`;
}

export function playerPanelMarkup(panel) {
  const state = getState();
  if (panel === 'lyrics') {
    const lyrics = state.lyrics;
    let body = '';
    if (lyrics.status === 'loading') {
      body = '<div class="lyrics-unavailable"><span class="loading-spinner"></span><p>Looking for licensed lyrics…</p></div>';
    } else if (lyrics.lines?.length) {
      body = `<div class="lyrics-viewer" id="lyrics-viewer" aria-live="polite">${lyrics.lines.map((line, index) => `<p class="lyric-line ${index === state.activeLyricIndex ? 'is-active' : index < state.activeLyricIndex ? 'is-past' : ''}" data-lyric-index="${index}">${escapeHTML(line.text)}</p>`).join('')}</div>`;
    } else {
      body = `<div class="lyrics-unavailable"><span class="lyrics-unavailable__icon">${icon('lyrics')}</span><h3>Lyrics are currently unavailable.</h3><p>${escapeHTML(lyrics.message || 'No licensed lyric text was returned for this track. We never fabricate lyrics or timing.')}</p></div>`;
    }
    return `<div class="player-panel-backdrop" data-dismiss-player-panel></div><section class="player-panel" role="region" aria-label="Lyrics" data-panel-content><div class="player-panel__handle"></div><header class="player-panel__header"><div><h2>Lyrics</h2><p>${lyrics.synced ? 'Synchronized lyrics' : 'Not synchronized'}</p></div><button class="icon-button icon-button--small" type="button" data-close-player-panel aria-label="Close lyrics">${icon('close')}</button></header>${body}</section>`;
  }

  const queue = state.queue;
  const current = state.currentTrack;
  const queueRows = queue.map((track, index) => `<article class="queue-row ${String(track.id) === String(current?.id) ? 'is-current' : ''}" draggable="true" data-queue-index="${index}">
    <span class="queue-row__grip" aria-hidden="true">${icon('grip')}</span>
    <button class="queue-row__art" type="button" data-action="queue-play" data-track-id="${escapeAttribute(track.id)}" aria-label="Play ${escapeAttribute(track.title)}">${imageMarkup(track, '')}</button>
    <button class="queue-row__text" type="button" data-action="queue-play" data-track-id="${escapeAttribute(track.id)}" aria-label="Play ${escapeAttribute(track.title)}"><strong>${escapeHTML(track.title)}</strong><span>${escapeHTML(track.artistName)}</span></button>
    <span class="queue-row__buttons"><button class="icon-button icon-button--small icon-button--bare" type="button" data-action="queue-move" data-from="${index}" data-to="${index - 1}" aria-label="Move ${escapeAttribute(track.title)} up" ${index === 0 ? 'disabled' : ''}>${icon('up')}</button><button class="icon-button icon-button--small icon-button--bare" type="button" data-action="queue-move" data-from="${index}" data-to="${index + 1}" aria-label="Move ${escapeAttribute(track.title)} down" ${index === queue.length - 1 ? 'disabled' : ''}>${icon('down')}</button><button class="icon-button icon-button--small icon-button--bare" type="button" data-action="queue-remove" data-track-id="${escapeAttribute(track.id)}" aria-label="Remove ${escapeAttribute(track.title)} from queue">${icon('close')}</button></span>
  </article>`).join('');
  return `<div class="player-panel-backdrop" data-dismiss-player-panel></div><section class="player-panel" role="region" aria-label="Queue" data-panel-content><div class="player-panel__handle"></div><header class="player-panel__header"><div><h2>Queue</h2><p>${queue.length ? `${pluralize(queue.length, 'track')} · Drag to reorder` : 'Add tracks from any menu'}</p></div><button class="icon-button icon-button--small" type="button" data-close-player-panel aria-label="Close queue">${icon('close')}</button></header>${queue.length ? `<div class="queue-summary"><span>${current ? `Now playing: ${escapeHTML(current.title)}` : 'Choose a track to begin'}</span><button class="button button--ghost button--small" type="button" data-action="queue-clear">${icon('trash')} Clear</button></div><div class="queue-list">${queueRows}</div>` : '<div class="queue-empty">Your queue is empty.<br>Add a track to keep the music moving.</div>'}</section>`;
}

export function playerStatusMessage(track, status = getState().playerStatus) {
  if (track?.source === 'fallback') return 'Offline demo metadata — no audio is included.';
  if (status === 'unavailable') return 'No authorized preview is available for this track.';
  if (status === 'error') return 'This preview could not be played. Tap play to retry.';
  return 'Authorized 30-second Deezer preview when available.';
}

export function fullPlayerMarkup() {
  const state = getState();
  if (!state.playerExpanded) return '';
  const track = state.currentTrack;
  if (!track) {
    return `<section class="full-player" role="dialog" aria-modal="true" aria-labelledby="empty-player-title" data-player-swipe><div class="full-player__empty"><button class="icon-button" type="button" data-action="close-player" aria-label="Close player">${icon('back')}</button><div class="full-player__empty-art">${icon('music')}</div><h1 id="empty-player-title">Your player is ready.</h1><p>Choose a Shirin David track and it will stay with you while you explore.</p><div class="full-player__empty-actions"><button class="button button--primary" type="button" data-route="/tracks">Browse tracks</button><button class="button button--secondary" type="button" data-action="open-player-panel" data-panel="queue">${icon('queue')} Open queue</button></div></div><div class="full-player__panel-root" data-player-panel-root>${state.playerPanel ? playerPanelMarkup(state.playerPanel) : ''}</div></section>`;
  }
  const favorite = Favorites.isTrackFavorite(track.id);
  const progress = playerProgressPercent();
  const statusMessage = playerStatusMessage(track);
  return `<section class="full-player" role="dialog" aria-modal="true" aria-labelledby="full-player-title" data-player-swipe data-player-track-id="${escapeAttribute(track.id)}">
    <div class="full-player__backdrop" data-player-backdrop>${imageMarkup(track, '', { eager: true, sizes: '(min-width: 768px) 640px, 100vw', minimumWidth: 640 })}</div>
    <header class="full-player__header"><button class="icon-button icon-button--bare" type="button" data-action="close-player" aria-label="Close full player">${icon('back')}</button><div class="full-player__context"><span>Now playing</span><span data-player-album>${escapeHTML(track.albumTitle || 'Shirin David')}</span></div><button class="icon-button icon-button--bare" type="button" data-action="share-track" data-track-id="${escapeAttribute(track.id)}" aria-label="Share ${escapeAttribute(track.title)}">${icon('share')}</button></header>
    <div class="full-player__body"><div class="full-player__stage">
      <div class="full-player__art-wrap"><span class="full-player__art-shadow"></span><div class="full-player__art ${state.isPlaying ? 'is-playing' : ''}" data-player-art>${imageMarkup(track, `Artwork for ${track.title}`, { eager: true, sizes: '(min-width: 768px) 640px, 100vw', minimumWidth: 640 })}</div><canvas id="player-visualizer" class="full-player__visualizer" aria-hidden="true"></canvas></div>
      <div class="full-player__track-info"><h1 id="full-player-title" class="full-player__title" data-player-title>${escapeHTML(track.title)}</h1><p class="full-player__artist" data-player-artist>${escapeHTML(track.artistName)}</p><button class="icon-button full-player__heart ${favorite ? 'is-active' : ''}" type="button" data-action="toggle-track-favorite" data-player-favorite data-track-id="${escapeAttribute(track.id)}" aria-label="${favorite ? 'Remove from' : 'Add to'} favorites" aria-pressed="${favorite}">${icon('heart')}</button></div>
      <div class="player-progress"><label class="sr-only" for="player-seek">Seek within preview</label><div class="range-wrap"><input id="player-seek" class="player-range" type="range" min="0" max="${Math.max(1, state.duration || track.duration || 30)}" value="${Math.min(state.currentTime, state.duration || track.duration || 30)}" step="0.1" data-control="seek" aria-label="Seek within preview" style="--range-progress:${progress}%"></div><div class="player-times"><time id="player-current-time">${escapeHTML(formatDuration(state.currentTime))}</time><time id="player-duration">${escapeHTML(formatDuration(state.duration || track.duration))}</time></div></div>
      ${transportButtons()}
      <div class="player-utilities"><div class="player-utilities__side"><button class="player-utility ${state.shuffle ? 'is-active' : ''}" type="button" data-action="toggle-shuffle" data-player-shuffle aria-label="Toggle shuffle" aria-pressed="${state.shuffle}">${icon('shuffle')}</button><button class="player-utility ${state.repeat !== 'off' ? 'is-active' : ''}" type="button" data-action="cycle-repeat" data-player-repeat aria-label="Repeat: ${state.repeat}" aria-pressed="${state.repeat !== 'off'}">${icon(state.repeat === 'one' ? 'repeatOne' : 'repeat')}</button></div><div class="player-utilities__side"><button class="player-utility player-speed ${state.playbackRate !== 1 ? 'is-active' : ''}" type="button" data-action="cycle-speed" data-player-speed aria-label="Playback speed ${state.playbackRate} times">${state.playbackRate}×</button><button class="player-utility" type="button" data-action="open-player-panel" data-panel="lyrics" aria-label="Open lyrics">${icon('lyrics')}</button><button class="player-utility" type="button" data-action="open-player-panel" data-panel="queue" aria-label="Open queue">${icon('queue')}</button></div></div>
      <p class="player-preview-note">${icon('info')} <span data-player-preview-status>${escapeHTML(statusMessage)}</span></p>
    </div></div>
    <div class="full-player__panel-root" data-player-panel-root>${state.playerPanel ? playerPanelMarkup(state.playerPanel) : ''}</div>
  </section>`;
}
