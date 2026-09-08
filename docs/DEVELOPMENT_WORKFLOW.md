# SHIRIN — Global Development Workflow

> **Binding from Phase 2 onward.** Read this before starting any phase, alongside
> `docs/SESSION_HANDOFF.md`, `git log`, and `docs/ROADMAP.md`.
>
> This document consolidates the GitHub workflow rules provided by the project
> owner with the conventions already established in this repository: the
> phase-gate model (`docs/ROADMAP.md`), the deployability and release rules
> (`docs/DEPLOYMENT_PLAN.md`), the security hard rules (`docs/SECURITY_PLAN.md`),
> the CI gates (`docs/ci/backend-tests.yml`), and the PR #1 precedent
> (`Phase 1: Laravel Foundation`, squash-merged 2026-09-08).
> On conflict: direct owner instruction wins, then this document, then phase docs.

Last updated: 2026-09-08 (created pre-Phase 2, docs-only).

---

## 1. Principles

1. **`main` is always deployable.** The static frontend at repo root deploys to
   GitHub Pages; `backend/` releases follow `docs/DEPLOYMENT_PLAN.md`.
2. **No direct pushes to `main`.** Every change lands via a pull request.
3. **One phase = one branch = one pull request.**
4. **No phase starts until the previous phase's exit criteria pass**
   (`docs/ROADMAP.md`: entry criteria → deliverables → exit criteria).
5. **Never merge red.** CI green + owner approval are both required.
6. **Docs ship with code.** A phase is not done until its reports and status
   files are updated (§10).

## 2. Branching model

| Branch | Purpose | Lifetime |
|---|---|---|
| `main` | Protected, deployable, tagged releases | Permanent |
| `phase-N-short-name` (e.g. `phase-2-catalogue`) | All work for phase N | Deleted after merge |
| `docs/short-name` | Standalone global-docs change outside any phase | Deleted after merge |
| `hotfix/short-name` (off `main`) | Urgent production fix | Deleted after merge |

Rules:

- Branch from an up-to-date `main`: `git fetch origin && git checkout -b phase-N-name origin/main`.
- Phase-scoped docs belong on the phase branch — never on a side branch.
- Never commit directly to `main`. Never force-push `main` or anyone else's branch.
- Resolve conflicts by updating the feature branch (`git merge origin/main` or
  `git rebase origin/main`), never by rewriting `main`.
- **Arena sandbox mapping:** sandbox sessions are locked to a single session
  branch (`arena/<id>-surprise`). That branch *stands in for* the phase branch
  for the session: do not create other branches there, and open the PR from the
  session branch, noting the mapping in the PR body (PR #1 precedent). If a
  quality-gate follow-up cannot get its own PR on the locked branch, its commits
  ride the same phase PR and are disclosed in a PR comment.

## 3. Commit conventions

Conventional Commits — `type(scope): imperative subject` (≤ 72 chars):

| Type | Use for |
|---|---|
| `feat` | New feature / module |
| `fix` | Bug fix |
| `docs` | Documentation only |
| `test` | Tests |
| `refactor` | Behavior-preserving restructure |
| `chore` | Tooling, config, maintenance |
| `ci` | CI workflows |
| `perf` / `style` / `build` / `revert` | As conventionally defined |

Scopes in use: `backend`, `web`, `auth`, `admin`, `api`, `settings`,
`security`, `db`, `docs`, `ci`, `phase-N`, `handoff`, `workflow`.

Rules:

- One logical change per commit; the body explains *why*, not just *what*.
- Preserve `Co-authored-by` trailers on agent-assisted commits.
- **Never commit:** secrets, `.env` (only `.env.example`), `vendor/`,
  `node_modules/`, storage logs, large binaries, or copyrighted audio.
  `composer.lock` **MUST** be committed (see `docs/PACKAGE_AUDIT.md` PKG-01);
  `vendor/` MUST NOT be (`.gitignore`).

## 4. Pull request rules

- Open **one PR per phase**, early as a draft, marked ready when the exit
  criteria are met. Title: `Phase N: <Name>` (e.g. `Phase 2: Catalogue + player on API`).
- Base is always `main`. Docs-only and hotfix PRs follow the same gates at
  smaller scale.
- Body follows the PR #1 template:

```markdown
## Summary
<what this phase delivers, in 2–4 sentences>

## Changes
- <area>: <what changed>

## Database changes
- <migration names + purpose, or "None">

## Packages added
- <name + version + why, or "None">

## Tests
- <suites added/updated, execution status incl. honest disclosure if CI-only>

## Decisions
- <architecture/dependency decisions taken this phase>

## Known limitations
- <intentional gaps + which later phase each lands in>
```

- PR checklist (all must hold before requesting review):
  - [ ] CI is green (§5) — do not request review on red.
  - [ ] Tests added/updated for new flows; escalation suites for auth/admin changes.
  - [ ] No hardcoded owner IDs; role-based authorization only (§6).
  - [ ] No secrets committed; migrations additive-only (§7).
  - [ ] Frontend preservation verified where applicable:
    `git diff main...HEAD -- index.html css/ js/ assets/ manifest.json service-worker.js`
    must be empty for backend-only phases.
  - [ ] Phase docs + `ROADMAP.md` + `PROJECT_STATUS.md` + `SESSION_HANDOFF.md` updated (§10).

## 5. CI and merge gates

CI template: `docs/ci/backend-tests.yml` (the owner activates it at
`.github/workflows/`; triggers on push/PR touching `backend/**`):

1. Checkout + PHP 8.3 with required extensions.
2. `composer install`.
3. `vendor/bin/pint --test` (style gate).
4. Hardcoded-owner-ID grep gate (review-blocking on match).
5. `php artisan test --parallel` (full suite).

Merge gates (ALL required):

1. CI green on the PR head commit.
2. Owner approval (code-owner review), all review threads resolved.
3. PR body complete per §4 template; exit criteria of `docs/ROADMAP.md` met.
4. Docs synchronized (§10); no scope expansion beyond the approved phase plan
   without owner sign-off.

**If CI fails:** fix on the branch and re-run — do not merge red, do not
bypass gates, do not merge with failing required checks.

## 6. Code review checklist

Every reviewer (human or agent self-review) verifies:

- **Authorization:** Spatie roles/permissions, `Gate::before` owner bypass via
  `hasRole('owner')`. `if ($user->id === 1)` (or equivalents) is a
  **review-blocking violation** — the CI grep gate enforces this.
- **Validation & output:** FormRequests on writes, `$fillable` allow-lists,
  Blade `{{ }}` escaping (no `{!! !!}` except allow-listed purifier), search
  input escaped, Eloquent bindings only.
- **API contract:** `{success, data, message[, code, errors]}` envelope,
  versioned `/api/v1`, Sanctum abilities least-privilege, signed stream URLs
  with short expiry.
- **i18n:** every user-facing string in both `fa` (default, RTL) and `en`
  (fallback, LTR); lang-key parity; `<html lang dir>` + `hreflang` correct.
- **Behavior:** CSRF on all forms, rate limits on auth/search/downloads,
  banned-user blocks at login *and* middleware, secure session flags,
  security headers middleware intact, friendly error pages (no internal leaks).
- **Tests:** new flows covered by feature tests; horizontal/vertical escalation
  cases for library/admin changes; flag on/off matrix for gated modules.
- **Style:** Pint clean; naming consistent with existing services/commands.

## 7. Database change rules

- Additive migrations only — never edit a migration that has been merged or
  applied anywhere; fix forward with a new migration.
- Timestamped descriptive names; every release note includes a
  `migrate:rollback --step` plan for its migrations.
- Seeders for roles/permissions and settings/flags; OWNER accounts only via
  interactive `php artisan shirin:install` — never default passwords, never
  committed credentials.
- SQL must stay MySQL/MariaDB-compatible (no Postgres-only constructs);
  suites run on SQLite in CI, verified on MySQL before release.
- **Licensing gate (R-01, `docs/RISKS.md`):** the music-licensing decision must
  resolve before ANY owned audio publishes (Phase 2+). No exceptions.

## 8. Package rules

- Minimal, justified dependencies only; every addition is disclosed in the PR
  body (`Packages added`) with name, version constraint, and rationale.
- Candidates must be shared-host safe (no daemons, no required extensions
  beyond the host checklist in `docs/DEPLOYMENT_PLAN.md`) until the VPS
  migration (Phase 7).
- `composer.lock` committed with every dependency change; run `composer audit`
  and resolve advisories before merge.
- Follow-ups (e.g. lock generation when the sandbox has no packagist egress)
  are tracked explicitly in the phase report and closed before/at merge.

## 9. Release process

1. **Merge:** squash-merge the phase PR into `main` via GitHub (PR #1
   precedent), then delete the phase branch.
2. **Tag:** release tags `v1.x` with a `CHANGELOG.md` entry; `APP_VERSION`
   surfaced in the admin footer.
3. **Deploy:** static root ships via GitHub Pages; backend follows the
   shared-host runbook (`docs/DEPLOYMENT_PLAN.md` §2: docroot, `.env`,
   `migrate --force`, caches, cron, `/up` + `/.env` verification), and from
   Phase 7 the VPS runbook instead.
4. **Verify post-merge:** run the manual checklist pattern from
   `docs/PHASE_1_TEST_REPORT.md` §C (migrate/seed clean, OWNER flows,
   auth round-trip, locale switch, API envelopes, production URL probes)
   on localhost/staging before announcing the release.
5. **Kill-switch discipline:** feature flags (`settings` table via
   `FeatureFlagService`) allow instant module disable without redeploy —
   keep every flag-gated module cleanly killable.

## 10. Docs and handoff requirements (definition of done)

Each phase delivers, on its branch, in `docs/`:

| Document | Contents |
|---|---|
| `PHASE_N_IMPLEMENTATION.md` | What was built, files/migrations/packages, decisions, limitations |
| `PHASE_N_TEST_REPORT.md` | Suites + honest execution disclosure; localhost/staging manual checklist |
| `PHASE_N_SECURITY_CHECK.md` | Self-review against `SECURITY_PLAN.md`, pass/deferred tables, verdict |
| `PHASE_N_DATABASE_REPORT.md` | Schema after migrate, seeders, rollback plan (when schema changes) |
| `PHASE_N_SETUP.md` | Setup/runbook deltas (when setup changes) |

And updates the living files:

- `docs/ROADMAP.md` — phase status + exit verdict.
- `docs/PROJECT_STATUS.md` — completed items, architecture, DB, packages,
  tests, next phase, warnings.
- `docs/SESSION_HANDOFF.md` — the next session's entry point: current state,
  completed work, binding decisions, files added/modified, tests, next phase,
  warnings. It must be accurate enough that a session with **no chat history**
  can continue from `SESSION_HANDOFF.md` + `git log` + `ROADMAP.md` alone.

## 11. Forbidden actions

- Pushing directly to `main`; force-pushing `main` or shared branches.
- Merging red, bypassing CI, or merging without owner approval.
- Committing secrets, `.env`, `vendor/`, or credentials of any kind.
- Hardcoding owner/user identity checks (`user_id == 1` or equivalents).
- Editing applied migrations; publishing owned audio before R-01 resolves.
- Downloading, caching, proxying, or redistributing copyrighted audio;
  fabricating, scraping, or displaying unlicensed lyrics; bypassing DRM or
  subscription gates (see `README.md` playback/lyrics policy).
- Expanding phase scope mid-phase without owner approval.

## 12. Quick reference

```bash
# Start a phase
git fetch origin
git checkout -b phase-N-short-name origin/main

# Work: conventional commits, e.g.
git commit -m "feat(catalogue): add dual-source track model"

# Keep current
git merge origin/main   # resolve conflicts on your branch

# Pre-push gates (backend)
cd backend && vendor/bin/pint --test && php artisan test
! grep -rn --include='*.php' -E '(user_id|userId|\bid\b)\s*===?\s*1[^0-9]' app/ routes/ database/

# Frontend preservation proof (backend-only phases)
git diff main...HEAD --stat -- index.html css/ js/ assets/ manifest.json service-worker.js
# ^ must be empty

# Ship
git push -u origin phase-N-short-name
gh pr create --base main --title "Phase N: <Name>" --body-file /tmp/pr-body.md
# CI green + owner approval → squash-merge → delete branch → tag v1.x
```
