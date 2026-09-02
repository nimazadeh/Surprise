import { hashString } from './utils.js';

const palettes = [
  [[205, 111, 166], [104, 91, 180]],
  [[191, 112, 208], [72, 103, 177]],
  [[230, 139, 151], [118, 77, 153]],
  [[226, 169, 112], [116, 74, 160]],
  [[112, 154, 217], [152, 86, 166]],
  [[213, 101, 138], [85, 92, 171]]
];

function applyAmbient(primary, secondary) {
  const root = document.documentElement;
  root.style.setProperty('--ambient-rgb', primary.join(', '));
  root.style.setProperty('--ambient-rgb-alt', secondary.join(', '));
}

function fallbackAmbient(seed) {
  const palette = palettes[hashString(seed) % palettes.length];
  applyAmbient(palette[0], palette[1]);
}

function sampleArtwork(source, seed) {
  if (!source || source.startsWith('assets/')) {
    fallbackAmbient(seed);
    return;
  }
  const image = new Image();
  image.crossOrigin = 'anonymous';
  image.decoding = 'async';
  image.onload = () => {
    try {
      const canvas = document.createElement('canvas');
      const size = 28;
      canvas.width = size;
      canvas.height = size;
      const context = canvas.getContext('2d', { willReadFrequently: true });
      context.drawImage(image, 0, 0, size, size);
      const pixels = context.getImageData(0, 0, size, size).data;
      let r = 0; let g = 0; let b = 0; let count = 0;
      let r2 = 0; let g2 = 0; let b2 = 0; let count2 = 0;
      for (let index = 0; index < pixels.length; index += 16) {
        const red = pixels[index];
        const green = pixels[index + 1];
        const blue = pixels[index + 2];
        const brightness = (red + green + blue) / 3;
        const saturation = Math.max(red, green, blue) - Math.min(red, green, blue);
        if (brightness > 26 && brightness < 230 && saturation > 20) {
          if ((index / 4) % 3 === 0) { r2 += red; g2 += green; b2 += blue; count2 += 1; }
          else { r += red; g += green; b += blue; count += 1; }
        }
      }
      if (!count) throw new Error('No usable artwork color');
      const primary = [Math.round(r / count), Math.round(g / count), Math.round(b / count)];
      const secondary = count2
        ? [Math.round(r2 / count2), Math.round(g2 / count2), Math.round(b2 / count2)]
        : palettes[hashString(seed) % palettes.length][1];
      applyAmbient(primary, secondary);
    } catch (error) {
      fallbackAmbient(seed);
    }
  };
  image.onerror = () => fallbackAmbient(seed);
  image.src = source;
}

export function setAmbientForTrack(track) {
  if (document.documentElement.dataset.theme === 'midnight') {
    applyAmbient([53, 54, 76], [24, 31, 49]);
    return;
  }
  const artwork = track?.artwork || track?.artworkSmall;
  fallbackAmbient(track?.id || track?.title || 'shirin');
  // Color sampling is a best-effort enhancement only; it cannot block player playback.
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) sampleArtwork(artwork, track?.id || track?.title);
}

export function installPressRipples(root = document) {
  root.addEventListener('pointerdown', (event) => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const target = event.target.closest('.button, .icon-button, .nav-item, .mobile-nav__item');
    if (!target || target.disabled) return;
    const rect = target.getBoundingClientRect();
    const ripple = document.createElement('span');
    ripple.className = 'ripple';
    ripple.style.left = `${event.clientX - rect.left}px`;
    ripple.style.top = `${event.clientY - rect.top}px`;
    target.classList.add('ripple-host');
    target.appendChild(ripple);
    ripple.addEventListener('animationend', () => ripple.remove(), { once: true });
  });
}

export function prefersReducedMotion() {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}
