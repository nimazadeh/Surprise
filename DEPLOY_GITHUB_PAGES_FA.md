# انتشار SHIRIN روی GitHub Pages — بدون نیاز به برنامه‌نویسی

این پروژه یک سایت **کاملاً Static** است؛ یعنی فقط HTML، CSS و JavaScript دارد.
بنابراین برای انتشار آن به Python، Node.js، سرور اختصاصی یا API Key نیاز نداری.

پس از انتشار، دوستت فقط این آدرس را باز می‌کند:

```text
https://YOUR-USERNAME.github.io/REPOSITORY-NAME/
```

مثال:

```text
https://sara123.github.io/shirin-music-experience/
```

> پروژه به صورت Hash Routing ساخته شده است؛ بنابراین لینک‌هایی مثل `#/albums` و `#/player` روی GitHub Pages بدون تنظیم سرور کار می‌کنند.

---

## روش پیشنهادی: GitHub Pages

### 1. فایل آماده را دانلود و Extract کن

فایل زیر برای آپلود آماده شده است:

```text
shirin-music-experience-github-pages.zip
```

آن را از Workspace دانلود کن و از حالت ZIP خارج کن.

پس از Extract باید این موارد را در پوشه ببینی:

```text
index.html
manifest.json
service-worker.js
README.md
css/
js/
assets/
```

**مهم:** فایل `index.html` باید مستقیماً در ریشه‌ی Repository قرار بگیرد، نه داخل یک پوشه‌ی اضافه.

---

### 2. یک Repository عمومی در GitHub بساز

1. وارد [github.com](https://github.com) شو و Login کن.
2. از بالا سمت راست روی **+** بزن و **New repository** را انتخاب کن.
3. این اطلاعات را وارد کن:
   - Repository name: `shirin-music-experience`
   - Visibility: **Public**
4. روی **Create repository** بزن.

---

### 3. فایل‌ها را بدون استفاده از Git آپلود کن

در Repository تازه‌ساخته‌شده:

1. روی **Add file** → **Upload files** بزن.
2. محتویات پوشه‌ی Extract‌شده را Drag & Drop کن:
   - `index.html`
   - پوشه‌های `css`، `js` و `assets`
   - `manifest.json`
   - `service-worker.js`
   - فایل‌های Markdown در صورت تمایل
3. پایین صفحه روی **Commit changes** بزن.

> خود فایل ZIP را آپلود نکن؛ ابتدا آن را Extract کن و سپس محتویاتش را آپلود کن.

---

### 4. GitHub Pages را فعال کن

1. در Repository به تب **Settings** برو.
2. از منوی سمت چپ، **Pages** را انتخاب کن.
3. در بخش **Build and deployment**:
   - Source را روی **Deploy from a branch** بگذار.
   - Branch را روی `main` قرار بده.
   - Folder را روی `/(root)` بگذار.
4. روی **Save** بزن.

GitHub معمولاً ظرف یک تا چند دقیقه سایت را منتشر می‌کند.

---

### 5. آدرس سایت را بردار و برای دوستت بفرست

بعد از انتشار، در همان صفحه‌ی **Settings → Pages**، آدرس سایت را می‌بینی.

اگر نام Repository این باشد:

```text
shirin-music-experience
```

و نام کاربری GitHub تو این باشد:

```text
sara123
```

لینک نهایی این است:

```text
https://sara123.github.io/shirin-music-experience/
```

دوستت برای دیدن سایت به GitHub Account یا دانش فنی احتیاج ندارد؛ فقط لینک را باز می‌کند.

---

## Python فقط برای تست لوکال است

این دستور:

```bash
python3 -m http.server 5500
```

فقط زمانی لازم است که بخواهی سایت را روی لپ‌تاپ خودت، قبل از انتشار، ببینی.

برای GitHub Pages یا بازدید دوستت، **هیچ Python یا برنامه‌ای لازم نیست**.

---

## نکات مهم این نسخه

- داده‌ی آلبوم‌ها و ترک‌ها در مرورگر بازدیدکننده از Deezer دریافت می‌شود.
- اگر API در دسترس نباشد، برنامه به یک کاتالوگ Demo محلی و بدون صوت برمی‌گردد؛ صفحه خالی یا Loading دائمی نمی‌ماند.
- پخش فقط از Preview مجاز Deezer انجام می‌شود، نه فایل کامل موسیقی.
- هیچ API Key یا Secret در فایل‌ها وجود ندارد.
- GitHub Pages برای این پروژه مناسب است، چون سایت به Backend نیاز ندارد.

---

## اگر سایت بعد از آپدیت، نسخه‌ی قدیمی را نشان داد

به دلیل Cache مرورگر یا Service Worker ممکن است لازم باشد یک بار Refresh کامل انجام بدهی:

- Windows / Linux: `Ctrl + Shift + R`
- macOS: `Cmd + Shift + R`

سپس دوباره لینک GitHub Pages را باز کن.

---

## راه سریع‌تر برای فقط یک دمو

اگر فقط می‌خواهی خیلی سریع یک لینک قابل اشتراک بسازی و Repository نمی‌خواهی، می‌توانی پوشه‌ی پروژه را روی یک سرویس Static Hosting مانند **Netlify** یا **Cloudflare Pages** Drag & Drop کنی. اما برای نگهداری نسخه‌ها، آپدیت راحت و داشتن یک لینک پایدار، GitHub Pages گزینه‌ی پیشنهادی است.
