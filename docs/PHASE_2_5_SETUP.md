# SHIRIN — Phase 2.5 Setup Deltas

Date: 2026-09-08 · Applies on top of `docs/PHASE_1_SETUP.md`. No schema
changes, no new daemons, no new host requirements beyond `ext-curl`
(already on the DEPLOYMENT_PLAN checklist — Guzzle needs it).

## 1. New backend environment variables (`.env.example` updated)

| Variable | Default | Purpose |
|---|---|---|
| `FRONTEND_ORIGINS` | `http://localhost:5500,http://127.0.0.1:5500` | Comma-separated origins allowed to call `/api/v1` (CORS). **Production: add the GitHub Pages URL** (e.g. `https://<user>.github.io`). Empty = no cross-origin API access. |
| `DEEZER_ENABLED` | `true` | Server-side kill switch for the external provider adapter (separate from the runtime `features.catalogue_provider` setting). |
| `DEEZER_BASE_URL` | `https://api.deezer.com` | Provider metadata endpoint (config-only, never user input). |
| `CATALOGUE_FEATURED_PROVIDER_ID` | `7312776` | Featured-artist fallback when no owned `is_featured` artist exists. |

Runtime setting (seeded, editable via settings/UI in a later phase):
`features.catalogue_provider` (boolean, default on) — instant kill switch
for all provider calls without a redeploy.

## 2. Dependencies

- **New:** `guzzlehttp/guzzle ^7.8` (transport for the Laravel HTTP client
  used by the Deezer metadata adapter; PKG-03 in `docs/PACKAGE_AUDIT.md`).
- `composer.lock` is still pending (PKG-01): run `composer update` on
  localhost, review, commit. The lock now resolves guzzle too.

## 3. Frontend deployment switch (the one intentional flag)

`js/config.js` → `CONFIG.apiBaseUrl`:

- `''` (default): the static app behaves exactly as before Phase 2.5 —
  direct Deezer JSONP, no backend dependency. GitHub Pages stays on this
  until the backend is live.
- `'https://your-backend.example'`: the player boots from
  `/api/v1/catalogue/featured`, search goes through `/api/v1/search`,
  owned content is primary, provider metadata is merged server-side, and
  cold hash links to owned items land on the backend SEO pages. The API
  must have `FRONTEND_ORIGINS` set to the Pages origin, or the browser
  will block the calls.

This is a public configuration value (no secrets in the static client).

## 4. CI

The backend test workflow is **staged** at `docs/ci/backend-tests.yml`.
The Phase 2.5 agent sandbox could not push it live — GitHub rejects
workflow-file pushes from its credential (missing `workflows`
permission). The owner activates it after merge, from a credential that
may push workflows:

```bash
git checkout main && git pull
mkdir -p .github/workflows
cp docs/ci/backend-tests.yml .github/workflows/backend-tests.yml
git add .github/workflows/backend-tests.yml
git commit -m "ci: activate backend tests"
git push
```

Once active it runs on push/PR touching `backend/**`: PHP 8.3 +
extensions, `composer install`, `vendor/bin/pint --test`, the
hardcoded-owner-ID grep gate, `php artisan test --parallel`. Until
`composer.lock` lands, install behaves as update (note kept in the
workflow file). Until activation, run the suite locally
(`php artisan test`).

## 5. Local quickstart (delta)

```bash
cd backend
composer install            # or: composer update → commit composer.lock
cp .env.example .env        # if fresh
php artisan key:generate
php artisan migrate --seed  # no new migrations; settings row added
php artisan shirin:install  # OWNER account (interactive)
php artisan serve           # http://localhost:8000

# separate terminal — static frontend on an allowed origin:
cd /path/to/repo
python3 -m http.server 5500
# then edit js/config.js → apiBaseUrl: 'http://localhost:8000'
```

Verify: `http://localhost:8000/up` → 200; `http://localhost:8000/api/v1/ping`
→ envelope; `http://localhost:5500` → home loads via
`/api/v1/catalogue/featured` (check the network tab).

## 6. Rollback

Code-only phase: revert the phase commits. `features.catalogue_provider`
can be switched off instantly (no redeploy) to disable all provider calls;
`apiBaseUrl: ''` restores the direct-provider frontend; CORS revert closes
the cross-origin surface. No migration rollback required.
