# Vendor / Owner acquisition checklist — iEX Admin Vue SPA

## Required product identity
- Product: **iEXExchanger**
- Version: **11.0.5** (Press-release)
- Surface: **Admin panel Vue SPA** (not the public Angular/Next exchanger UI)
- Live compiled entry: `resources/vuejs/main.ts` → `public/build/assets/main-DKBLW3qr.js`
- Live admin URL: `https://app.exswaping.com/iexadmin/`

## Prefer (in order)
1. **Full original application package** for 11.0.5 that includes admin frontend source (best).
2. Dedicated **admin frontend source archive** matching the build that produced manifest dated ~2026-02-22 / main bundle mtime ~2026-03-27.
3. Minimum file set (if full package unavailable).

## Minimum files to request
```
resources/vuejs/                    # entire tree
package.json
package-lock.json  OR  yarn.lock  OR  pnpm-lock.yaml
vite.config.ts   OR  vite.config.js  OR  vite.config.mjs
Any custom patches under resources/vuejs not in stock 11.0.5
Build instructions (exact Node version, npm/pnpm/yarn, build command)
Exact release/archive name + checksum if available
```

## Must prove after receipt (before any production deploy)
- [ ] Contains `resources/vuejs/main.ts`
- [ ] Contains orders UI (e.g. OrdersContainer / orders list pages)
- [ ] `npm/pnpm/yarn install` + documented build succeeds
- [ ] Generated Vite `manifest.json` has entry `resources/vuejs/main.ts`
- [ ] Built routes/API paths align with current `routes/admin-frontend.php` (`/iexadmin/frontend-api/...`)
- [ ] Compatible with backend **15d2dd1** (orders soft-delete / Прибыль overlays)

## Explicitly NOT acceptable as canonical
- Reconstructed/decompiled code from minified `main-*.js`
- Public site Next.js repo (`exswaping-owned-frontend` / `armcoincrypto/exswaping-frontend`)
- Public Angular `dist/exchanger` update zips (`iex-update-exswaping.com-front.zip`)
- Laravel vendor vite stubs (`vendor/laravel/*/vite.config.js`)

## Vendor contacts (from local package metadata)
- Vendor: iEXExchanger FZCO
- Site: https://iexexchanger.com
- Docs: https://docs.iexexchanger.com
