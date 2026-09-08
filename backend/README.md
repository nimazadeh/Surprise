# SHIRIN Backend (Laravel 12)

Phase 1 foundation (auth, RBAC, admin shell, settings + feature flags, API
v1 contract, bilingual fa/en web layer, storage foundation), Phase 2 music
domain (catalogue DB + admin CMS + API reads + SEO pages + covers), and
Phase 2.5 catalogue delivery (dual-source provider layer, merged search,
featured + resolve, nested endpoints, public catalogue index pages, CORS
for the static frontend).

Full setup guide: [`../docs/PHASE_1_SETUP.md`](../docs/PHASE_1_SETUP.md) ·
Phase 2.5 deltas: [`../docs/PHASE_2_5_SETUP.md`](../docs/PHASE_2_5_SETUP.md)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan shirin:install   # interactive OWNER creation
php artisan serve
```

Tests: `php artisan test` · Style: `vendor/bin/pint`
CI: staged at `../docs/ci/backend-tests.yml` (Pint + hardcoded-ID gate +
full suite on push/PR touching `backend/**`); activate by copying to
`.github/workflows/` — the agent sandbox cannot push workflow files.

Public API surface (v1, enveloped): `ping`, `meta/flags`, `auth/me`,
`search`, `resolve`, `catalogue/featured`, and catalogue reads
(`artists`/`albums`/`tracks` + nested `artists/{slug}/albums|tracks`,
`albums/{slug}/tracks`). Public web: `/artists`, `/albums`, `/tracks`,
`/genres/{slug}` + admin CMS at `/admin`. See
[`../docs/PHASE_2_5_REPORT.md`](../docs/PHASE_2_5_REPORT.md) for behavior
(merge rules, throttles, governance).
