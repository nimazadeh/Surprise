# SHIRIN — Risk Register (Phase 0)

> Read before approving Phase 1. Severity = Likelihood × Impact (H/M/L).

| ID | Risk | L | I | Mitigation (design response) |
|---|---|---|---|---|
| **R-01** | **Music rights infringement.** Hosting/streaming/downloads of copyrighted music without licenses exposes the owner to takedowns, fines, account termination. | H | H | **License-first rule:** no owned audio published without documented rights; launch catalogue = licensed/original/permissioned works only; Deezer previews stay provider-served (never proxied/cached); DMCA-style takedown flow + `uploads.review` human gate; legal review gate before Phase 8. |
| R-02 | Shared-hosting ceilings (CPU, execution time, disk, no FFmpeg/Redis). | H | M | Shared-first design (file cache, DB queue+cron, getID3, local disk); honest degraded states; VPS runbook ready; quotas + alarms. |
| R-03 | Upload abuse (malware, piracy, NSFW covers). | M | H | Quarantine + human review, MIME sniff, re-encode covers, AV hook, quotas, audit trail, reporter flow. |
| R-04 | Scope creep: Lab + downloads + ads + apps at once. | H | M | Flag-gated phases; V1 = core+admin+ads+downloads-disabled-capable; Lab FFmpeg explicitly deferred to VPS. |
| R-05 | OWNER lockout or compromise. | L | H | Protected role + 2FA-ready + break-glass recovery procedure (sealed, offline) + admin-login alerts + audit logs. |
| R-06 | Data loss (DB/media on shared host). | M | H | Backup runbook from Phase 1 (exports), automated on VPS; media rows reference checksums; tested restores. |
| R-07 | RTL regression on mature LTR CSS. | M | M | Logical properties + token-driven RTL layer; per-release RTL/LTR screenshot checklist. |
| R-08 | SEO underperformance (ex-hash SPA history). | M | M | SSR Blade + slugs + JSON-LD + sitemap + hash-redirect map; Search Console from Phase 1. |
| R-09 | Performance: N+1 catalogue queries, uncached hot reads. | M | M | Eager-load policy, counter caches, `Cache::remember` on reads, query-count assertions in tests. |
| R-10 | Third-party dependence (Deezer API changes/CORS). | M | M | Adapter interface; owned catalogue is primary; enrichment failures degrade to owned-only. |
| R-11 | Payment/subscription complexity (if ever added). | L | M | **Out of scope:** revenue = ads; premium = admin-granted tiers, no billing in V1. Revisit only with provider + legal review. |
| R-12 | Solo-developer bus factor. | M | M | Docs-first (this folder), seeded installers, runbooks, conventions over cleverness. |

## Acceptance

Phase 1 begins only after the owner explicitly accepts **R-01** with a licensing plan
(what audio may be hosted, who approves it, and the takedown contact/procedure).
All other risks are handled by the designs in this folder.
