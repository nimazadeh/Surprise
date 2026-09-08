# SHIRIN Backend (Laravel 12)

Phase 1 foundation: auth, RBAC, admin shell, settings + feature flags, API v1
contract, bilingual (fa/en) web layer, storage foundation.

Full setup guide: [`../docs/PHASE_1_SETUP.md`](../docs/PHASE_1_SETUP.md)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan shirin:install   # interactive OWNER creation
php artisan serve
```

Tests: `php artisan test` · Style: `vendor/bin/pint`
