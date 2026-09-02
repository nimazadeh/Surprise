import { CONFIG } from './config.js';
import { Favorites } from './favorites.js';
import { icon } from './icons.js';
import { AppState } from './state.js';
import {
  escapeAttribute,
  escapeHTML,
  formatCompactNumber,
  formatDuration,
  getImageSource,
  pluralize,
  releaseYear
} from './utils.js';

function getState() { return AppState.get(); }
function encoded(value) { return encodeURIComponent(String(value ?? '')); }

/**
 * Deezer image URLs include the physical dimensions in their pathname, for example
 * `/500x500-…`. The provider's `cover_small` value is genuinely 56 px wide, so it
 * must never be advertised as a 250 px image in srcset. Build candidates from the
 * large source instead and keep every width descriptor truthful.
 */
function responsiveArtworkSources(source, minimumWidth = 0) {
  const value = String(source || '');
  const dimensions = value.match(/\/(\d+)x(\d+)(?=-)/);
  if (!dimensions) return '';

  const sourceWidth = Math.max(Number(dimensions[1]) || 0, Number(dimensions[2]) || 0);
  if (!sourceWidth) return '';
  const widths = [160, 320, 640, 1000]
    .filter((width) => width < sourceWidth && width >= minimumWidth)
    .concat(sourceWidth);
  const uniqueWidths = [...new Set(widths)];
  return uniqueWidths.map((width) => {
    const candidate = width === sourceWidth
      ? value
      : value.replace(/\/\d+x\d+(?=-)/, `/${width}x${width}`);
    return `${escapeAttribute(candidate)} ${width}w`;
  }).join(', ');
}

function imageMarkup(item, alt, {
  className = '',
  artist = false,
  eager = false,
  sizes = '',
  minimumWidth = 0
} = {}) {
  const fallback = artist ? CONFIG.fallbackArtistArtwork : CONFIG.fallbackArtwork;
  const source = artist ? getImageSource(item, 'artist') : getImageSource(item, 'default');
  const srcset = responsiveArtworkSources(source, minimumWidth);
  const imageSizes = sizes || (artist ? '(min-width: 768px) 48vw, 62vw' : '(min-width: 1024px) 16vw, 45vw');
  const responsiveAttributes = srcset
    ? `srcset="${srcset}" sizes="${escapeAttribute(imageSizes)}"`
    : '';
  return `<img class="${escapeAttribute(className)}" src="${escapeAttribute(source)}" ${responsiveAttributes} alt="${escapeAttribute(alt)}" ${eager ? 'fetchpriority="high"' : 'loading="lazy"'} decoding="async" data-image-fallback="${escapeAttribute(fallback)}">`;
}

function sourceLabel() {
  const state = getState();
  if (state.catalogueStatus === 'loading' && state.dataSource === 'fallback') return 'Loading live catalogue · demo shown';
  if (state.catalogueStatus === 'loading' && state.dataSource === 'cache') return 'Refreshing saved metadata · Deezer';
  if (state.dataSource === 'live') return 'Live metadata · Deezer';
  if (state.dataSource === 'cache') return 'Saved metadata · Deezer';
  if (state.dataSource === 'fallback') return 'Offline demo catalogue';
  return 'Preparing catalogue';
}

function currentTrackId() { return String(getState().currentTrack?.id || ''); }

function explicitBadge(track) {
  return track?.explicit ? '<span class="explicit-badge" title="Explicit">E</span>' : '';
}

function playingBars() {
  return '<span class="is-playing-indicator" aria-label="Playing"><i></i><i></i><i></i></span>';
}

function isRouteActive(route, item) {
  if (item === 'home') return route.name === 'home';
  if (item === 'search') return route.name === 'search';
  if (item === 'artist') return route.name === 'artist';
  if (item === 'albums') return route.name === 'albums' || route.name === 'album';
  if (item === 'tracks') return route.name === 'tracks';
  if (item === 'library') return ['library', 'favorites', 'recent'].includes(route.name);
  if (item === 'favorites') return route.name === 'favorites' || (route.name === 'library' && (route.query?.tab || 'favorites') === 'favorites');
  if (item === 'recent') return route.name === 'recent' || (route.name === 'library' && route.query?.tab === 'recent');
  if (item === 'player') return route.name === 'player';
  return false;
}

function navItem(label, iconName, path, active) {
  return `<button class="nav-item ${active ? 'is-active' : ''}" type="button" data-route="${escapeAttribute(path)}" ${active ? 'aria-current="page"' : ''}>${icon(iconName)}<span>${escapeHTML(label)}</span></button>`;
}

function mobileNavItem(label, iconName, path, active) {
  return `<button class="mobile-nav__item ${active ? 'is-active' : ''}" type="button" data-route="${escapeAttribute(path)}" ${active ? 'aria-current="page"' : ''}>${icon(iconName)}<span>${escapeHTML(label)}</span></button>`;
}

function sidebarMarkup() {
  const state = getState();
  const route = state.route;
  const now = state.currentTrack;
  return `
    <a class="sidebar__brand" href="#/home" aria-label="SHIRIN home">
      <span class="sidebar__brand-icon">${icon('logo')}</span>
      <span><strong class="sidebar__brand-name">SHIRIN</strong><small class="sidebar__brand-caption">Music experience</small></span>
    </a>
    <nav class="sidebar__nav" aria-label="Browse music">
      ${navItem('Home', 'home', '/home', isRouteActive(route, 'home'))}
      ${navItem('Search', 'search', '/search', isRouteActive(route, 'search'))}
      ${navItem('Artist', 'artist', '/artist', isRouteActive(route, 'artist'))}
      ${navItem('Albums', 'disc', '/albums', isRouteActive(route, 'albums'))}
      ${navItem('Tracks', 'music', '/tracks', isRouteActive(route, 'tracks'))}
    </nav>
    <div class="sidebar__divider"></div>
    <span class="sidebar__section-label">Your space</span>
    <nav class="sidebar__nav" aria-label="Your music">
      ${navItem('Favorites', 'heart', '/library?tab=favorites', isRouteActive(route, 'favorites'))}
      ${navItem('Recently played', 'history', '/library?tab=recent', isRouteActive(route, 'recent'))}
    </nav>
    <button class="sidebar__now" type="button" data-route="/player" aria-label="Open now playing">
      <span class="sidebar__now-label">Now playing</span>
      <strong class="sidebar__now-track">${escapeHTML(now?.title || 'Nothing playing')}</strong>
      <span class="sidebar__now-artist">${escapeHTML(now?.artistName || 'Choose a track')}</span>
    </button>`;
}

function mobileNavigationMarkup() {
  const route = getState().route;
  return [
    mobileNavItem('Home', 'home', '/home', isRouteActive(route, 'home')),
    mobileNavItem('Search', 'search', '/search', isRouteActive(route, 'search')),
    mobileNavItem('Library', 'library', '/library?tab=favorites', isRouteActive(route, 'library')),
    mobileNavItem('Player', 'music', '/player', isRouteActive(route, 'player'))
  ].join('');
}

function pageHeader() {
  const artist = getState().artist;
  return `<header class="page-header">
      <div class="page-header__identity">
        <button class="header-mark" type="button" data-route="/home" aria-label="Go to home">${icon('logo')}</button>
        <span class="mobile-wordmark">SHIRIN</span>
      </div>
      <div class="page-header__actions">
        ${getState().isOnline ? '' : '<span class="meta-pill">Offline</span>'}
        <button class="header-artist-link" type="button" data-route="/artist" aria-label="Open Shirin David artist page">
          ${artist ? imageMarkup(artist, 'Shirin David', { artist: true }) : ''}<span>Shirin David</span>
        </button>
      </div>
    </header>`;
}

function trackCard(track, context = 'top') {
  const active = String(track.id) === currentTrackId();
  return `<article class="track-card ${active ? 'is-playing' : ''}">
    <div class="track-card__art">
      <button class="track-card__open" type="button" data-action="play-track" data-track-id="${escapeAttribute(track.id)}" data-context="${escapeAttribute(context)}" aria-label="Play ${escapeAttribute(track.title)}">${imageMarkup(track, `Cover artwork for ${track.title}`)}</button>
      <button class="track-card__play" type="button" data-action="play-track" data-track-id="${escapeAttribute(track.id)}" data-context="${escapeAttribute(context)}" aria-label="Play ${escapeAttribute(track.title)}">${active && getState().isPlaying ? icon('pause') : icon('play')}</button>
    </div>
    <div class="track-card__body"><strong class="track-card__title">${escapeHTML(track.title)}</strong><span class="track-card__meta">${escapeHTML(track.artistName)}</span></div>
  </article>`;
}

function albumCard(album) {
  const meta = `${releaseYear(album.releaseDate)} · ${album.trackCount ? pluralize(album.trackCount, 'track') : (album.recordType || 'release')}`;
  return `<article class="album-card">
    <div class="album-card__art">
      <button class="album-card__open album-card__open--art" type="button" data-route="/album/${encoded(album.id)}" aria-label="Open ${escapeAttribute(album.title)}">${imageMarkup(album, `Artwork for ${album.title}`)}</button>
      <button class="album-card__play" type="button" data-action="play-album" data-album-id="${escapeAttribute(album.id)}" aria-label="Play ${escapeAttribute(album.title)}">${icon('play')}</button>
    </div>
    <button class="album-card__body album-card__open" type="button" data-route="/album/${encoded(album.id)}" aria-label="Open ${escapeAttribute(album.title)}"><strong class="album-card__title">${escapeHTML(album.title)}</strong><span class="album-card__meta">${escapeHTML(meta)}</span></button>
  </article>`;
}

function trackRow(track, { index = null, context = 'top', showArtwork = true, showAlbum = true } = {}) {
  const active = String(track.id) === currentTrackId();
  const playLabel = active && getState().isPlaying ? `Pause ${track.title}` : `Play ${track.title}`;
  const number = active && getState().isPlaying ? playingBars() : (index !== null ? String(index + 1).padStart(2, '0') : icon('play'));
  const meta = showAlbum && track.albumTitle ? `${track.artistName} · ${track.albumTitle}` : track.artistName;
  return `<article class="track-row ${active ? 'is-playing' : ''}">
    <button class="track-row__number" type="button" data-action="play-track" data-track-id="${escapeAttribute(track.id)}" data-context="${escapeAttribute(context)}" aria-label="${escapeAttribute(playLabel)}">${number}</button>
    <button class="track-row__main" type="button" data-action="play-track" data-track-id="${escapeAttribute(track.id)}" data-context="${escapeAttribute(context)}" aria-label="${escapeAttribute(playLabel)}">
      ${showArtwork ? `<span class="track-row__cover">${imageMarkup(track, '', { className: '' })}</span>` : ''}
      <span class="track-row__text"><strong class="track-row__title">${escapeHTML(track.title)} ${explicitBadge(track)}</strong><span class="track-row__meta">${escapeHTML(meta)}</span></span>
    </button>
    <span class="track-row__right"><span class="track-row__duration">${escapeHTML(formatDuration(track.duration))}</span><button class="icon-button icon-button--small icon-button--bare track-row__menu" type="button" data-action="track-menu" data-track-id="${escapeAttribute(track.id)}" aria-label="More options for ${escapeAttribute(track.title)}">${icon('more')}</button></span>
  </article>`;
}

function trackList(tracks, options = {}) {
  if (!tracks?.length) return '';
  return `<div class="track-list ${options.compact ? 'compact-list' : ''}">${tracks.map((track, index) => trackRow(track, { ...options, index: options.numbered === false ? null : index })).join('')}</div>`;
}

function emptyState(title, description, iconName = 'music', action = '') {
  return `<section class="empty-state"><span class="empty-state__icon">${icon(iconName)}</span><h2>${escapeHTML(title)}</h2><p>${escapeHTML(description)}</p>${action}</section>`;
}

function errorState(title = 'We couldn’t load the music right now.', description = 'Please try again.') {
  return `<section class="error-state"><span class="error-state__icon">${icon('alert')}</span><h2>${escapeHTML(title)}</h2><p>${escapeHTML(description)}</p><button class="button button--primary" type="button" data-action="retry-catalog">${icon('refresh')} Try again</button></section>`;
}

function albumSkeleton(count = 6) {
  return `<div class="skeleton-grid">${Array.from({ length: count }, () => '<div><div class="skeleton skeleton--art"></div><div class="skeleton skeleton--line"></div><div class="skeleton skeleton--line short"></div></div>').join('')}</div>`;
}

function renderHomeLoading() {
  return `<section class="page home-page">${pageHeader()}<div class="skeleton skeleton--hero"></div><section class="content-lane"><div class="section-heading"><h2>Popular tracks</h2></div>${albumSkeleton(4)}</section></section>`;
}

function renderHome() {
  const state = getState();
  if (state.catalogueStatus === 'loading' && !state.artist) return renderHomeLoading();
  if (state.catalogueStatus === 'error' && !state.artist) return `<section class="page">${pageHeader()}${errorState()}</section>`;

  const artist = state.artist;
  const albums = state.albums || [];
  const topTracks = state.topTracks || [];
  const featured = albums[0];
  const albumReleases = albums.filter((album) => album.recordType !== 'single').slice(0, 12);
  const singles = albums.filter((album) => album.recordType === 'single');
  const recent = state.recentlyPlayed.slice(0, 8);
  const favorites = state.favorites.tracks.slice(0, 8);
  const artistArt = artist ? imageMarkup(artist, 'Shirin David portrait', { artist: true, eager: true }) : '<span class="hero__fallback-monogram">SD</span>';

  return `<section class="page home-page">
    ${pageHeader()}
    <section class="hero" aria-labelledby="home-title">
      <div class="hero__art">${artistArt}</div>
      <div class="hero__content">
        <span class="eyebrow">The Shirin David experience</span>
        <h1 id="home-title">Shirin<br>David</h1>
        <p class="hero__copy">${escapeHTML(artist?.bio || 'German rapper, singer and entrepreneur.')}</p>
        <div class="home-stat-row" aria-label="Artist metadata">
          <span class="home-stat"><strong>${escapeHTML(artist?.albumCount ? String(artist.albumCount) : '—')}</strong><span>available releases</span></span>
          <span class="home-stat"><strong>${escapeHTML(artist?.fanCount ? formatCompactNumber(artist.fanCount) : 'Live')}</strong><span>${artist?.fanCount ? 'Deezer fans' : 'catalogue ready'}</span></span>
        </div>
        <div class="hero__actions">
          <button class="button button--primary button--wide" type="button" data-action="play-popular">${icon('play')} Play popular</button>
          <button class="button button--secondary" type="button" data-route="/artist">Explore artist</button>
        </div>
        <span class="hero__foot"><i class="dot" aria-hidden="true"></i>Fan-made · Authorized previews where available</span>
      </div>
    </section>

    <section class="content-lane" aria-labelledby="popular-title">
      <div class="section-heading"><h2 id="popular-title">Popular tracks</h2><p>${escapeHTML(sourceLabel())}</p></div>
      ${topTracks.length ? `<div class="rail" aria-label="Popular tracks">${topTracks.slice(0, 12).map((track) => trackCard(track, 'top')).join('')}</div>` : emptyState('Tracks are on their way', 'Refresh the catalogue to load popular Shirin David tracks.', 'music')}
    </section>

    ${featured ? `<section class="content-lane" aria-labelledby="latest-title">
      <div class="section-heading"><h2 id="latest-title">Latest release</h2><button class="button button--ghost button--small" type="button" data-route="/albums">View all</button></div>
      <article class="featured-release">
        <div class="featured-release__art">${imageMarkup(featured, `Artwork for ${featured.title}`, { eager: true })}</div>
        <div class="featured-release__content">
          <span class="eyebrow">${escapeHTML(featured.recordType || 'Release')} · ${escapeHTML(releaseYear(featured.releaseDate))}</span>
          <h3 class="featured-release__title">${escapeHTML(featured.title)}</h3>
          <p class="featured-release__meta">${escapeHTML(featured.trackCount ? pluralize(featured.trackCount, 'track') : 'Open the release')}</p>
          <div class="featured-release__actions"><button class="button button--primary" type="button" data-action="play-album" data-album-id="${escapeAttribute(featured.id)}">${icon('play')} Play</button><button class="button button--secondary" type="button" data-route="/album/${encoded(featured.id)}">View release</button></div>
        </div>
      </article>
    </section>` : ''}

    <section class="content-lane" aria-labelledby="albums-title">
      <div class="section-heading"><h2 id="albums-title">Albums & releases</h2><button class="button button--ghost button--small" type="button" data-route="/albums">Browse all</button></div>
      ${albumReleases.length ? `<div class="album-grid">${albumReleases.slice(0, 12).map(albumCard).join('')}</div>` : albumSkeleton(6)}
    </section>

    <section class="content-lane" aria-labelledby="singles-title">
      <div class="section-heading"><h2 id="singles-title">Singles & features</h2><p>${singles.length ? `${singles.length} releases` : 'Selected tracks'}</p></div>
      ${trackList(topTracks.slice(0, 6), { context: 'top', numbered: false, compact: true }) || emptyState('More releases are coming', 'There are no singles in the current catalogue response.', 'disc')}
    </section>

    ${recent.length ? `<section class="content-lane" aria-labelledby="recent-title"><div class="section-heading"><h2 id="recent-title">Listen again</h2><button class="button button--ghost button--small" type="button" data-route="/library?tab=recent">See history</button></div><div class="rail">${recent.map((track) => trackCard(track, 'recent')).join('')}</div></section>` : ''}
    ${favorites.length ? `<section class="content-lane" aria-labelledby="favorite-title"><div class="section-heading"><h2 id="favorite-title">Your favorites</h2><button class="button button--ghost button--small" type="button" data-route="/library?tab=favorites">Open library</button></div><div class="rail">${favorites.map((track) => trackCard(track, 'favorites')).join('')}</div></section>` : ''}

    <footer class="footer-note">This independent, fan-made experience is not affiliated with Shirin David. Catalogue metadata and permitted 30-second previews are supplied by <a href="https://www.deezer.com" target="_blank" rel="noopener noreferrer">Deezer</a> when available. No music files are stored or redistributed.</footer>
  </section>`;
}

function renderArtist() {
  const state = getState();
  const artist = state.artist;
  if (!artist) return renderHomeLoading();
  const releases = state.albums.filter((album) => album.recordType !== 'single').slice(0, 6);
  const popular = state.topTracks.slice(0, 8);
  const favored = state.favorites.artist;

  return `<section class="page artist-page">
    ${pageHeader()}
    <section class="artist-hero" aria-labelledby="artist-title">
      <div class="artist-hero__art">${imageMarkup(artist, 'Shirin David portrait', { artist: true, eager: true })}</div>
      <div class="artist-hero__content">
        <span class="eyebrow">Artist profile</span>
        <h1 id="artist-title">Shirin David <span class="verified" aria-label="Verified artist">${icon('check')}</span></h1>
        <p class="artist-hero__bio">${escapeHTML(artist.bio || 'German rapper, singer and entrepreneur.')}</p>
        <div class="artist-stats">
          <span class="stat-pill">${icon('disc')} ${escapeHTML(String(artist.albumCount || state.albums.length))} releases</span>
          ${artist.fanCount ? `<span class="stat-pill">${icon('headPhones')} ${escapeHTML(formatCompactNumber(artist.fanCount))} fans</span>` : ''}
          <span class="stat-pill">${icon('sparkles')} ${state.dataSource === 'live' ? 'Live catalogue' : 'Available catalogue'}</span>
        </div>
        <div class="hero__actions"><button class="button button--primary" type="button" data-action="play-popular">${icon('play')} Play popular</button><button class="button button--secondary" type="button" data-action="toggle-artist-favorite" aria-pressed="${favored}">${icon('heart')} ${favored ? 'Following' : 'Follow artist'}</button></div>
      </div>
    </section>
    <section class="content-lane"><div class="section-heading"><h2>Popular</h2><p>Most played in the provider catalogue</p></div>${trackList(popular, { context: 'top' }) || albumSkeleton(4)}</section>
    <section class="content-lane"><div class="section-heading"><h2>Albums</h2><button class="button button--ghost button--small" type="button" data-route="/albums">All releases</button></div><div class="album-grid">${releases.map(albumCard).join('')}</div></section>
    <section class="content-lane two-up" aria-label="Artist information"><article class="featured-release"><div class="featured-release__content"><span class="eyebrow">Listen responsibly</span><h3 class="featured-release__title">Preview-first.</h3><p class="featured-release__meta">This experience uses only provider-authorized preview playback. Open a release in Deezer for the full listening experience.</p></div></article><article class="featured-release"><div class="featured-release__content"><span class="eyebrow">Fan-made project</span><h3 class="featured-release__title">Built for discovery.</h3><p class="featured-release__meta">Save favorites, build a local queue and return to your listening history — all on your device.</p></div></article></section>
  </section>`;
}

function recordTypeLabel(type) {
  const normalized = String(type || 'release').toLowerCase();
  return normalized === 'ep' ? 'EP' : normalized.charAt(0).toUpperCase() + normalized.slice(1);
}

function renderAlbums() {
  const state = getState();
  const filter = state.route.query?.filter || 'all';
  const allAlbums = state.albums;
  const releases = filter === 'albums' ? allAlbums.filter((album) => album.recordType === 'album')
    : filter === 'singles' ? allAlbums.filter((album) => album.recordType === 'single')
      : allAlbums;
  const chips = [['all', 'All releases'], ['albums', 'Albums'], ['singles', 'Singles']];
  return `<section class="page catalog-page">
    ${pageHeader()}
    <header class="catalog-header"><div><span class="eyebrow">Catalogue</span><h1>Every era, in one place.</h1></div><p>Live release data is loaded from a replaceable provider layer, so this catalogue can grow without being hardcoded into the interface.</p></header>
    <div class="search-suggestions" aria-label="Release filters">${chips.map(([value, label]) => `<button class="filter-chip ${filter === value ? 'is-active' : ''}" type="button" data-route="/albums?filter=${value}" aria-pressed="${filter === value}">${escapeHTML(label)}</button>`).join('')}</div>
    <section class="content-lane"><div class="section-heading"><h2>${escapeHTML(filter === 'all' ? 'All releases' : filter === 'albums' ? 'Albums' : 'Singles')}</h2><p>${escapeHTML(`${releases.length} ${releases.length === 1 ? 'release' : 'releases'}`)}</p></div>${releases.length ? `<div class="album-grid">${releases.map(albumCard).join('')}</div>` : emptyState('No releases found', 'Try another catalogue filter or refresh the music data.', 'disc')}</section>
  </section>`;
}

function albumPageMarkup(album, tracks = null, { loading = false, failed = false } = {}) {
  const favored = Favorites.isAlbumFavorite(album.id);
  return `<section class="page album-detail-page">
    ${pageHeader()}
    <button class="button button--ghost button--small" type="button" data-route="/albums">${icon('back')} All releases</button>
    <section class="album-detail__masthead content-lane" aria-labelledby="album-title">
      <div class="album-detail__cover">${imageMarkup(album, `Artwork for ${album.title}`, { eager: true })}</div>
      <div class="album-detail__identity">
        <span class="eyebrow">${escapeHTML(recordTypeLabel(album.recordType))} · ${escapeHTML(releaseYear(album.releaseDate))}</span>
        <h1 id="album-title">${escapeHTML(album.title)}</h1>
        <p>${escapeHTML(album.artistName || 'Shirin David')}</p>
        <p class="album-detail__context">${escapeHTML(album.trackCount ? pluralize(album.trackCount, 'track') : 'Release details loading')} · ${escapeHTML(sourceLabel())}</p>
        <div class="album-detail__actions">
          <button class="button button--primary" type="button" data-action="play-album" data-album-id="${escapeAttribute(album.id)}">${icon('play')} Play</button>
          <button class="button button--secondary" type="button" data-action="shuffle-album" data-album-id="${escapeAttribute(album.id)}">${icon('shuffle')} Shuffle</button>
          <button class="icon-button ${favored ? 'is-active' : ''}" type="button" data-action="toggle-album-favorite" data-album-id="${escapeAttribute(album.id)}" aria-label="${favored ? 'Remove' : 'Add'} ${escapeAttribute(album.title)} ${favored ? 'from' : 'to'} favorites" aria-pressed="${favored}">${icon('heart')}</button>
          <button class="icon-button" type="button" data-action="share-album" data-album-id="${escapeAttribute(album.id)}" aria-label="Share ${escapeAttribute(album.title)}">${icon('share')}</button>
        </div>
      </div>
    </section>
    <section class="content-lane" aria-labelledby="album-tracklist-title">
      <div class="section-heading"><h2 id="album-tracklist-title">Tracklist</h2><p>${loading ? 'Loading tracks' : tracks ? pluralize(tracks.length, 'track') : ''}</p></div>
      ${loading ? `<div class="track-list">${Array.from({ length: Math.max(4, Math.min(album.trackCount || 6, 8)) }, () => '<div class="track-row"><div class="skeleton" style="width:32px;height:32px;border-radius:50%"></div><div><div class="skeleton skeleton--line"></div><div class="skeleton skeleton--line short"></div></div></div>').join('')}</div>` : failed ? errorState('We couldn’t load this release.', 'Please check your connection and try again.') : tracks?.length ? trackList(tracks, { context: `album:${album.id}`, showArtwork: false }) : emptyState('No tracklist is available', 'This provider did not return tracks for this release.', 'music')}
    </section>
    <p class="provider-note">${icon('info')} Playback uses an authorized 30-second Deezer preview when one is supplied. Full-length streaming is not provided by this static fan project.</p>
  </section>`;
}

function renderTracks() {
  const tracks = getState().topTracks;
  return `<section class="page tracks-page">
    ${pageHeader()}
    <header class="catalog-header"><div><span class="eyebrow">Track collection</span><h1>Press play on a moment.</h1></div><p>A live selection from Shirin David’s public provider catalogue, ready for a local queue or permitted preview playback.</p></header>
    <section class="content-lane"><div class="section-heading"><h2>Top tracks</h2><button class="button button--secondary button--small" type="button" data-action="play-popular">${icon('play')} Play all</button></div>${tracks.length ? trackList(tracks, { context: 'top' }) : albumSkeleton(5)}</section>
  </section>`;
}

function searchLoadingMarkup() {
  return `<div class="search-results"><span class="loading-inline"><span class="loading-spinner"></span>Searching the music catalogue</span><div class="content-lane">${albumSkeleton(3)}</div></div>`;
}

function renderSearchResults() {
  const container = document.getElementById('search-results');
  if (!container) return;
  const { search } = getState();
  const { status, results, query } = search;
  if (status === 'idle') {
    container.innerHTML = emptyState('Find a release, track or artist', 'Try a Shirin David song title, album name or collaborator. Search waits briefly while you type.', 'search', '<button class="button button--secondary" type="button" data-action="search-suggestion" data-query="Shirin David">Search Shirin David</button>');
    return;
  }
  if (status === 'loading') {
    container.innerHTML = searchLoadingMarkup();
    return;
  }
  if (status === 'error') {
    container.innerHTML = errorState('Search is unavailable right now.', 'Please try another search in a moment.');
    return;
  }
  if (status === 'empty') {
    container.innerHTML = emptyState('No music found', `We couldn’t find anything for “${query}”. Try a track, album or artist name.`, 'search');
    return;
  }
  const artistResults = results.artists || [];
  const albumResults = results.albums || [];
  const trackResults = results.tracks || [];
  container.innerHTML = `
    ${artistResults.length ? `<section class="search-results__group"><div class="section-heading"><h2>Artists</h2><p>${artistResults.length} results</p></div><div class="two-up">${artistResults.slice(0, 4).map((artist) => `<button class="search-result-artist" type="button" data-route="${artist.name.toLowerCase() === 'shirin david' ? '/artist' : '/search'}"><span>${imageMarkup(artist, `${artist.name} artist image`, { artist: true })}</span><span class="search-result-artist__body"><strong class="search-result-artist__name">${escapeHTML(artist.name)} ${artist.name.toLowerCase() === 'shirin david' ? `<span class="verified">${icon('check')}</span>` : ''}</strong><span class="search-result-artist__meta">${artist.albumCount ? `${artist.albumCount} releases` : 'Artist result'}</span></span>${icon('back', 'search-result-artist__arrow')}</button>`).join('')}</div></section>` : ''}
    ${albumResults.length ? `<section class="search-results__group"><div class="section-heading"><h2>Albums & releases</h2><p>${albumResults.length} results</p></div><div class="rail">${albumResults.map(albumCard).join('')}</div></section>` : ''}
    ${trackResults.length ? `<section class="search-results__group"><div class="section-heading"><h2>Tracks</h2><p>${trackResults.length} results</p></div>${trackList(trackResults, { context: 'search' })}</section>` : ''}
    ${!artistResults.length && !albumResults.length && !trackResults.length ? emptyState('No music found', `We couldn’t find anything for “${query}”.`, 'search') : ''}`;
}

function renderSearch() {
  const search = getState().search;
  return `<section class="page search-page">
    ${pageHeader()}
    <header class="search-intro"><span class="eyebrow">Search</span><h1>What do you want to hear?</h1><p>Search a track, release, artist or collaborator in Shirin David’s provider catalogue.</p></header>
    <label class="sr-only" for="music-search">Search music</label>
    <div class="search-input-wrap">${icon('search')}<input id="music-search" class="search-input" type="search" value="${escapeAttribute(search.query)}" placeholder="Search Shirin David…" autocomplete="off" enterkeyhint="search"><button class="icon-button icon-button--small ${search.query ? '' : 'is-hidden'}" type="button" data-action="clear-search" aria-label="Clear search" ${search.query ? '' : 'hidden'}>${icon('close')}</button></div>
    <div class="search-suggestions" aria-label="Search suggestions"><button class="search-chip" type="button" data-action="search-suggestion" data-query="Shirin David">Shirin David</button><button class="search-chip" type="button" data-action="search-suggestion" data-query="Bitches brauchen Rap">Bitches brauchen Rap</button><button class="search-chip" type="button" data-action="search-suggestion" data-query="Bauch Beine Po">Bauch Beine Po</button></div>
    <div id="search-results" class="search-results"></div>
  </section>`;
}

function libraryTabButton(tab, label, iconName, active) {
  return `<button class="filter-chip ${active ? 'is-active' : ''}" type="button" data-route="/library?tab=${tab}" aria-pressed="${active}">${icon(iconName)} ${escapeHTML(label)}</button>`;
}

function renderLibrary() {
  const state = getState();
  const validTabs = ['favorites', 'recent', 'albums', 'tracks'];
  const requested = state.route.name === 'favorites' ? 'favorites' : state.route.name === 'recent' ? 'recent' : state.route.query?.tab || 'favorites';
  const tab = validTabs.includes(requested) ? requested : 'favorites';
  let content = '';
  if (tab === 'favorites') {
    const favoriteTracks = state.favorites.tracks;
    const favoriteAlbums = state.favorites.albums;
    content = favoriteTracks.length || favoriteAlbums.length
      ? `${favoriteTracks.length ? `<section><div class="section-heading"><h2>Favorite tracks</h2><p>${favoriteTracks.length}</p></div>${trackList(favoriteTracks, { context: 'favorites', numbered: false, compact: true })}</section>` : ''}${favoriteAlbums.length ? `<section class="content-lane"><div class="section-heading"><h2>Favorite releases</h2><p>${favoriteAlbums.length}</p></div><div class="album-grid">${favoriteAlbums.map(albumCard).join('')}</div></section>` : ''}`
      : emptyState('Your favorites live here', 'Tap the heart on any track or release to make this library yours.', 'heart', '<button class="button button--primary" type="button" data-route="/albums">Browse releases</button>');
  } else if (tab === 'recent') {
    const history = state.recentlyPlayed;
    content = history.length
      ? `<div class="section-heading"><h2>Recently played</h2><button class="button button--ghost button--small" type="button" data-action="clear-recent">${icon('trash')} Clear</button></div>${trackList(history, { context: 'recent', numbered: false })}`
      : emptyState('Nothing in your history yet', 'Tracks you play will be saved on this device so you can find them again.', 'history', `<button class="button button--secondary" type="button" data-action="play-popular">${icon('play')} Play popular</button>`);
  } else if (tab === 'albums') {
    content = state.albums.length ? `<div class="album-grid">${state.albums.map(albumCard).join('')}</div>` : albumSkeleton();
  } else {
    content = state.topTracks.length ? trackList(state.topTracks, { context: 'top' }) : albumSkeleton();
  }

  return `<section class="page library-page">
    ${pageHeader()}
    <header class="catalog-header"><div><span class="eyebrow">Your library</span><h1>Made for your return.</h1></div><p>Favorites, history and playback preferences are stored locally in this browser — never audio files or private credentials.</p></header>
    <div class="library-tabs" role="tablist" aria-label="Library sections">
      ${libraryTabButton('favorites', 'Favorites', 'heart', tab === 'favorites')}
      ${libraryTabButton('recent', 'Recently played', 'history', tab === 'recent')}
      ${libraryTabButton('albums', 'Albums', 'disc', tab === 'albums')}
      ${libraryTabButton('tracks', 'Tracks', 'music', tab === 'tracks')}
    </div>
    <div class="library-content">${content}</div>
    <section class="content-lane settings-card" aria-labelledby="ambience-title"><div><span class="eyebrow">Interface ambience</span><h2 id="ambience-title">Set the mood.</h2><p>Choose whether the player responds to artwork colors or stays in a restrained midnight palette.</p></div><div class="settings-card__actions"><button class="filter-chip ${state.theme === 'dynamic' ? 'is-active' : ''}" type="button" data-action="set-theme" data-theme="dynamic" aria-pressed="${state.theme === 'dynamic'}">${icon('sparkles')} Dynamic art</button><button class="filter-chip ${state.theme === 'midnight' ? 'is-active' : ''}" type="button" data-action="set-theme" data-theme="midnight" aria-pressed="${state.theme === 'midnight'}">${icon('disc')} Midnight</button></div></section>
  </section>`;
}


export {
  albumPageMarkup,
  albumSkeleton,
  emptyState,
  errorState,
  imageMarkup,
  mobileNavigationMarkup,
  pageHeader,
  renderAlbums,
  renderArtist,
  renderHome,
  renderHomeLoading,
  renderLibrary,
  renderSearch,
  renderSearchResults,
  renderTracks,
  sidebarMarkup
};
