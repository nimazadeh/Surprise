import { escapeAttribute } from './utils.js';
const ICONS = {
  logo: '<path d="M15.8 4.8a8.3 8.3 0 1 0 3.3 13.1l-2.6-1.5a5.4 5.4 0 1 1 1.2-8.2l2.1-2.6Z"/><path d="M16.5 6h3.7v9.8a3.7 3.7 0 1 1-3.7-3.7c.7 0 1.3.2 1.8.5V9h-1.8Z"/>',
  home: '<path d="m3 10.8 9-7.2 9 7.2v8.4a1.8 1.8 0 0 1-1.8 1.8H4.8A1.8 1.8 0 0 1 3 19.2v-8.4Z"/><path d="M9 21v-6h6v6"/>',
  search: '<circle cx="10.8" cy="10.8" r="6.7"/><path d="m16 16 4.5 4.5"/>',
  library: '<path d="M5 4.5h14A1.5 1.5 0 0 1 20.5 6v14A1.5 1.5 0 0 1 19 21.5H5A1.5 1.5 0 0 1 3.5 20V6A1.5 1.5 0 0 1 5 4.5Z"/><path d="M7.5 9h9M7.5 13h9M7.5 17h5"/>',
  disc: '<circle cx="12" cy="12" r="8.6"/><circle cx="12" cy="12" r="2.2"/><path d="M17.9 6.1A8.4 8.4 0 0 0 12 3.4"/>',
  artist: '<circle cx="12" cy="7.6" r="3.3"/><path d="M5.2 20.4c.6-4 3-6.1 6.8-6.1s6.2 2.1 6.8 6.1"/>',
  music: '<path d="M8 18.2a2.8 2.8 0 1 1-2.8-2.8A2.8 2.8 0 0 1 8 18.2Z"/><path d="M8 18.2V6l10-2v11.4"/><path d="M20.8 15.4a2.8 2.8 0 1 1-2.8-2.8"/>',
  heart: '<path d="M20.8 8.6c0 5.4-8.8 10.5-8.8 10.5S3.2 14 3.2 8.6A4.7 4.7 0 0 1 12 6.2a4.7 4.7 0 0 1 8.8 2.4Z"/>',
  history: '<path d="M3.7 12a8.3 8.3 0 1 0 2.4-5.9L3.7 8.5"/><path d="M3.7 3.9v4.6h4.6M12 7v5l3.2 1.9"/>',
  play: '<path d="m8.4 5.7 10.1 6.3-10.1 6.3V5.7Z" fill="currentColor" stroke="none"/>',
  pause: '<path d="M8.3 5.6v12.8M15.7 5.6v12.8" stroke-width="2.7" stroke-linecap="round"/>',
  previous: '<path d="M6 5.5v13M18.5 6.1 9.2 12l9.3 5.9V6.1Z" fill="currentColor" stroke="none"/>',
  next: '<path d="M18 5.5v13M5.5 6.1 14.8 12l-9.3 5.9V6.1Z" fill="currentColor" stroke="none"/>',
  shuffle: '<path d="m4 7 3.2 0c2.4 0 3.2 10 5.6 10H16"/><path d="m16 14 3 3-3 3M4 17h3.2c1.1 0 1.8-2 2.6-4"/><path d="m16 4 3 3-3 3"/>',
  repeat: '<path d="M17.5 5.5H8a4 4 0 0 0-4 4v.5M6.5 18.5H16a4 4 0 0 0 4-4V14"/><path d="m15.7 2.9 2.7 2.7-2.7 2.7M8.3 21.1l-2.7-2.7 2.7-2.7"/>',
  repeatOne: '<path d="M17.5 5.5H8a4 4 0 0 0-4 4v.5M6.5 18.5H16a4 4 0 0 0 4-4V14"/><path d="m15.7 2.9 2.7 2.7-2.7 2.7M8.3 21.1l-2.7-2.7 2.7-2.7"/><path d="M12 9.2v5.6m0-5.6-1.5 1.5"/>',
  queue: '<path d="M4 6.5h11M4 12h11M4 17.5h8"/><path d="M18.5 15.5v5M16 18h5"/>',
  lyrics: '<path d="M5 4.5h14A1.5 1.5 0 0 1 20.5 6v12A1.5 1.5 0 0 1 19 19.5H5A1.5 1.5 0 0 1 3.5 18V6A1.5 1.5 0 0 1 5 4.5Z"/><path d="M7.5 9h9M7.5 12.5h6.5M7.5 16h8.5"/>',
  share: '<circle cx="18" cy="5" r="2.4"/><circle cx="6" cy="12" r="2.4"/><circle cx="18" cy="19" r="2.4"/><path d="m8.1 10.8 7.8-4.6M8.1 13.2l7.8 4.6"/>',
  more: '<circle cx="5" cy="12" r="1.2" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.2" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1.2" fill="currentColor" stroke="none"/>',
  back: '<path d="m14.5 5-7 7 7 7"/><path d="M8 12h10.5"/>',
  close: '<path d="m6 6 12 12M18 6 6 18"/>',
  volume: '<path d="M4 10v4h3l4 3V7l-4 3H4Z"/><path d="M15 9.2a4 4 0 0 1 0 5.6M17.7 6.5a7.8 7.8 0 0 1 0 11"/>',
  mute: '<path d="M4 10v4h3l4 3V7l-4 3H4Z"/><path d="m16 10 4 4m0-4-4 4"/>',
  check: '<path d="m5 12.4 4.1 4.1L19 6.7" stroke-width="2.5"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  trash: '<path d="M4.8 7h14.4M9.2 7V4.8h5.6V7m-8.3 0 .8 12.3h9.4L17.5 7M10 10.5v5.5m4-5.5v5.5"/>',
  grip: '<circle cx="9" cy="7" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="7" r="1" fill="currentColor" stroke="none"/><circle cx="9" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="9" cy="17" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="17" r="1" fill="currentColor" stroke="none"/>',
  up: '<path d="m6 14 6-6 6 6"/>',
  down: '<path d="m6 10 6 6 6-6"/>',
  external: '<path d="M13 5h6v6M11 13l8-8M19 14.5v4A1.5 1.5 0 0 1 17.5 20h-12A1.5 1.5 0 0 1 4 18.5v-12A1.5 1.5 0 0 1 5.5 5h4"/>',
  clock: '<circle cx="12" cy="12" r="8.4"/><path d="M12 7v5l3.3 2"/>',
  info: '<circle cx="12" cy="12" r="8.5"/><path d="M12 10.7v5M12 8.1h.01" stroke-width="2.2" stroke-linecap="round"/>',
  sparkles: '<path d="m12 3 1.1 4.2L17 8.3l-3.9 1.1L12 14l-1.1-4.6L7 8.3l3.9-1.1L12 3ZM18.5 14.5l.6 2.3 2.4.7-2.4.6-.6 2.4-.7-2.4-2.3-.6 2.3-.7.7-2.3ZM5.2 14l.7 2.5 2.5.7-2.5.7-.7 2.5-.7-2.5-2.5-.7 2.5-.7.7-2.5Z"/>',
  headPhones: '<path d="M4 13v-1a8 8 0 0 1 16 0v1M4 13v4.2A1.8 1.8 0 0 0 5.8 19H8v-6H5.8A1.8 1.8 0 0 0 4 14.8ZM20 13v4.2a1.8 1.8 0 0 1-1.8 1.8H16v-6h2.2a1.8 1.8 0 0 1 1.8 1.8Z"/>',
  refresh: '<path d="M20 12a8 8 0 1 1-2.3-5.7M20 4.5v4.8h-4.8"/>',
  album: '<rect x="3.7" y="4" width="16.6" height="16" rx="2"/><circle cx="12" cy="12" r="3.1"/>',
  alert: '<path d="M10.4 4.7 3.8 17a2 2 0 0 0 1.8 3h12.8a2 2 0 0 0 1.8-3L13.6 4.7a1.8 1.8 0 0 0-3.2 0Z"/><path d="M12 9v4.3m0 3h.01" stroke-width="2" stroke-linecap="round"/>'
};

export function icon(name, className = '') {
  const path = ICONS[name] || ICONS.music;
  return `<svg class="${escapeAttribute(className)}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${path}</svg>`;
}
