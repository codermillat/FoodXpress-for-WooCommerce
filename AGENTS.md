# AGENTS.md — FoodXpress for WooCommerce

> Context for AI coding agents (OpenCode, Codex, Cursor, Aider, Devin, Gemini CLI, Claude Code, etc.).
> Read this before touching the codebase. The repo is **public and open source** (GPL-3.0-or-later since v1.2.1); the user (MD Millat Hosen) is the sole maintainer.

---

## 0. TL;DR

- **Project:** FoodXpress for WooCommerce — a delivery-management plugin for single-restaurant WooCommerce stores
- **Current version:** **v1.3.4** (2026-08-18 — v1.2.2–1.2.19: Zomato/Swiggy-style checkout, coordinates-only fee engine + saved-address defaults, WC-compat hardening, dead-code cleanup, privacy integration, config health warning, region-neutral defaults, full-flow audit fixes, 16-minor cleanup pass, REST `floatval` callback PHP 8 fatal fix, blocks-checkout `register_fields` moved to `woocommerce_blocks_loaded` for WC 11.0+, map picker on block themes via `render_block` on `woocommerce/checkout`, `is_store_open()` moved to `FXW_Store_Hours` (loaded every request), Leaflet fallback (no API key required). **v1.3.0: checkout UX cleanup** — double-charging removed (Shipping Method API sole charge), country/state/city/postcode hidden + relabelled `address_1` field ("Flat / Floor / Block / Society / Tower"), landmark label dedup, removed obsolete `fxw_enable_extra_delivery_fee` setting. **v1.3.1: blocks Store API namespace** — `woocommerce_store_api_register_update_callback` registers `foodxpress` on `woocommerce_blocks_loaded` (callback persists `customer_lat`/`customer_lng` and recomputes shipping + totals); without this registration the block checkout threw "There is no such namespace registered: foodxpress." every time the picker called `extensionCartUpdate`. **v1.3.2: live block-API detection** — picker no longer caches whether `wc.blocksCheckout.extensionCartUpdate` exists; it sniffs on every pin drag because `wc.blocksCheckout` may not be wired in at init time. **v1.3.3: critical error on block checkout** — `$order->set_address_2()` does not exist in WC 11 (`set_billing_address_2()` / `set_shipping_address_2()` are the only address-2 setters); the order persistence path took the dead call once the FXW block path reached it (post-v1.3.0 the block checkout hit Place Order 500; classic checkout never did). Removed. **v1.3.4: shipping method provider-aware** — the previous gate `if ( empty( $options['fxw_google_maps_api_key'] ) ) return;` made `calculate_shipping()` bail on every Leaflet/OSM install (no Google key needed), so the rate was never re-added and the Order summary stayed stale on pin drag. Replaced with `FXW_Map_Providers::supports( 'distance', $options )`. Repo public, GPL-3.0-or-later. See CHANGELOG.md)
- **In progress:** **Phase 1** of an 8-phase backport — porting 17 premium features from two archived sibling repos (`RestroReach` and `restaurant-delivery-manager`, both archived on GitHub but not deleted)
- **User profile:** Freelance web developer. The plugin is a **general open-source release for everyone** (GPL-3.0-or-later) — not tied to any specific restaurant or client. It is the **primary project**; everything else on the machine is secondary.
- **Repo location:** `~/Desktop/FoodXpress-for-WooCommerce/` (moved here from `~/.minimax-agent/projects/repo-merge-analysis/fx/` on 2026-08-17 so non-Mavis tools can access it directly)
- **Remote:** `https://github.com/codermillat/FoodXpress-for-WooCommerce` (public since v1.2.1, GPL-3.0-or-later)
- **Test runner:** `php tests/FXWTestRunner.php` → must report **136/136 pass** before any commit

---

## 1. What the plugin does

A complete delivery-management layer for single-restaurant WooCommerce stores:

- **Map-based checkout** — Google Maps location picker on the WooCommerce checkout, distance-based delivery validation, dynamic fee calculation, ETA-aware shipping label
- **Custom order statuses** — `fxw-in-kitchen` → `fxw-assigned` → `fxw-picked-up` → completed
- **Delivery agent role** — `delivery_boy` user role with its own mobile dashboard
- **Order tracking + reorder** — shortcodes for customers
- **Receipt printing** — thermal-printer-friendly templates
- **WooCommerce email integration** — 3 custom email classes (In Kitchen, Driver Assigned, Picked Up)

**Roadmap (8 phases, ~8 weeks):** see [`docs/ROADMAP.md`](./docs/ROADMAP.md).

| # | Phase | Status | Release |
|---|---|---|---|
| 0 | Refactor & hygiene (checkout split, gitignore, license, AI-bloat cleanup) | ✅ Done | **v1.2.0** |
| 1 | Data layer + migration (8 `fxw_*` tables, models, RR/RDM import) | 📋 Next | v1.3.0 |
| 2 | Mobile agent PWA (manifest, service worker, offline) | 📋 | v1.4.0 |
| 3 | GPS tracking engine (REST, battery-aware 45s/135s) | 📋 | v1.5.0 |
| 4 | COD payments + cash reconciliation | 📋 | v1.6.0 |
| 5 | Multi-channel notifications — **email + browser push only at launch** (NO SMS) | 📋 | v1.7.0 |
| 6 | Analytics & BI — Chart.js, CSV export, cron reports | 📋 | v1.8.0 |
| 7 | Owner-controlled outlet model — Google Maps primary, **Leaflet fallback** when no API key | 📋 | v1.9.0 |
| 8 | Polish, tests, docs, CI matrix | 📋 | v2.0.0 |

---

## 2. Tech stack

| Layer | Version | Notes |
|---|---|---|
| PHP | 7.4 minimum (8.0 target in v2.0) | `Requires PHP: 7.4` |
| WordPress | 6.0+ | `Requires at least: 6.0` |
| WooCommerce | 7.0+ (tested 9.4) | `WC requires at least: 7.0` |
| HPOS | Custom order tables **compatible** + **opt-out of cart-checkout-blocks** | Declared via `FeaturesUtil::declare_compatibility` in main file |
| JavaScript | jQuery + vanilla ES6 | No build step — plain `.js` files in `assets/js/` |
| Maps | Google Maps API primary, Leaflet fallback (Phase 7) | `fxw_google_maps_api_key` setting |
| Database | MySQL via `$wpdb` | No PDO wrapper. **Always use `$wpdb->prepare()`** |
| DI | None. Plain `new` + static factories. No container. | |
| AJAX security | Per-handler `wp_create_nonce()` + `current_user_can()`. **No central wrapper.** | |
| Cron | WordPress `wp_schedule_event` only. **No external schedulers.** | |
| Tests | `FXWTestRunner.php` static checks. PHPUnit+WP test suite planned for Phase 8. | |

---

## 3. File structure (post-v1.2.0)

```
FoodXpress-for-WooCommerce/
├── foodxpress-for-woocommerce.php   ← slim bootstrap, defines FXW_VERSION + FXW_PLUGIN_DIR/URL
├── uninstall.php                   ← cleanup hook
├── LICENSE.md                      ← GPL-3.0-or-later (relicensed from proprietary in v1.2.1)
├── CHANGELOG.md                    ← Keep a Changelog format
├── README.md                       ← user-facing (this is for AI, README is for humans)
├── AGENTS.md                       ← you are here
├── docs/
│   ├── ANALYSIS.md                 ← 3-repo comparison + status updates
│   ├── ROADMAP.md                  ← 8-phase backport plan (current source of truth)
│   └── archive/                    ← Nov 2025 docs from the old Local Sites install
├── includes/
│   ├── class-fxw-core.php          ← bootstrap: requires, hook wiring, admin/frontend dispatch
│   ├── class-fxw-checkout.php      ← ORCHESTRATOR: form render, field customisation, address pre-fill
│   ├── class-fxw-checkout-maps.php ← frontend map assets + get_restaurant_location AJAX + debug_status AJAX
│   ├── class-fxw-checkout-handler.php ← server: update_customer_location, validate_delivery_zone, save_*
│   ├── class-fxw-dashboard.php     ← admin deliveries dashboard orchestrator (split in v1.2.9)
│   ├── class-fxw-dashboard-render.php ← dashboard page rendering (v1.2.9)
│   ├── class-fxw-dashboard-actions.php ← dashboard form-POST + AJAX handlers (v1.2.9)
│   ├── class-fxw-settings.php      ← WooCommerce → Settings → FoodXpress page
│   ├── class-fxw-shortcodes.php    ← [fxw_track_order], [fxw_reorder]
│   ├── class-fxw-order-admin.php   ← WC order meta boxes
│   ├── class-fxw-shipping-method.php ← FX shipping method registration
│   ├── class-fxw-delivery-boy-view.php ← mobile agent dashboard
│   ├── class-fxw-roles.php         ← delivery_boy role
│   ├── class-fxw-order-statuses.php ← registers fxw-* statuses with WC
│   ├── class-fxw-admin-bar.php     ← admin bar delivery toggle
│   ├── class-fxw-reporting.php     ← delivery analytics (will grow in Phase 6)
│   ├── class-fxw-notifications.php ← email dispatch (will grow into multi-channel in Phase 5)
│   ├── class-fxw-privacy.php       ← WP/WC privacy export & erasure (added in v1.2.6)
│   ├── class-fxw-blocks-checkout.php ← blocks-checkout fields + map + order persistence (v1.2.9)
│   ├── class-fxw-store-hours.php     ← scheduled opening hours (v1.2.12)
│   ├── class-fxw-pricing.php          ← fee tiers / free threshold / minimum order (v1.2.13)
│   ├── class-fxw-checkout-address.php ← hide country/state/city/postcode + relabel address_1 (v1.3.0)
│   ├── class-fxw-config.php        ← constants (FXW_Config::DEFAULT_DELIVERY_RADIUS, etc.)
│   ├── api/
│   │   └── class-fxw-rest-checkout-controller.php  ← REST pattern reference
│   ├── services/                              ← STATELESS services, one per file
│   │   ├── class-fxw-mapping-service.php       ← Google Maps wrapper
│   │   ├── class-fxw-rate-limiter.php          ← rate limiting
│   │   ├── class-fxw-delivery-fee.php          ← cart fee + ETA label (NEW in v1.2.0)
│   │   └── class-fxw-address-validator.php     ← address completeness heuristic (NEW in v1.2.0)
│   └── emails/
│       ├── class-fxw-email-in-kitchen.php
│       ├── class-fxw-email-assigned.php
│       └── class-fxw-email-picked-up.php
├── templates/                      ← frontend templates (delivery dashboard, delivery-boy view, receipt)
├── assets/
│   ├── css/                        ← frontend.css, delivery-dashboard.css, my-account.css
│   └── js/                         ← checkout.js, delivery-dashboard.js, admin.js, admin-dashboard.js
├── tests/
│   └── FXWTestRunner.php           ← static analysis + security checks
└── skills/                         ← Claude skill content (developer reference, not plugin runtime)
```

---

## 4. Class map (what each class does)

| Class | Responsibility | Self-registers? |
|---|---|---|
| `FXW_Core` | Bootstrap. `require`s all classes, wires admin/frontend dispatches, adds rewrite rules, enqueues admin assets. | ✓ |
| `FXW_Checkout` | **Orchestrator.** Renders location picker + delivery fields on the WC checkout. Hides default billing/shipping fields. Pre-fills saved address for returning customers. | ✓ |
| `FXW_Checkout_Maps` | **Frontend map assets.** `enqueue_scripts` (Google Maps with API-key guard + localized `restaurant_center`/`radius_km`/`saved_address` params), `add_async_defer_to_maps_script`. | ✓ |
| `FXW_Checkout_Handler` | **Server-side logic.** `validate_delivery_zone` (coordinates-only, restaurant via `FXW_Mapping_Service::get_restaurant_location()`), `save_customer_address` (auto-saves the delivery profile for logged-in users), `save_delivery_details_to_order` (HPOS-aware; store-base country default). | ✓ |
| `FXW_Dashboard` | Admin order dashboard. Largest class at 593 LOC — **flagged for the same split treatment as the checkout split.** | ✓ |
| `FXW_Settings` | Native tab in WooCommerce → Settings (manage_woocommerce). | ✓ |
| `FXW_Shortcodes` | `[fxw_track_order]`, `[fxw_reorder]`, plus tracking page rewrite. | ✓ |
| `FXW_Order_Admin` | Order meta boxes (delivery details, assignment, etc.). | ✓ |
| `FXW_Shipping_Method` | Registers `foodxpress_delivery` shipping method with WC. | ✓ |
| `FXW_Delivery_Boy_View` | Mobile agent dashboard page + template loader. | ✓ |
| `FXW_Roles` | Adds `delivery_boy` role on activation. | ✓ |
| `FXW_Order_Statuses` | Registers `fxw-in-kitchen`, `fxw-assigned`, `fxw-picked-up` with WC. | ✓ |
| `FXW_Admin_Bar` | Admin bar "Delivery" toggle. | ✓ |
| `FXW_Reporting` | Delivery analytics queries (will grow into Phase 6 BI). | ✓ |
| `FXW_Notifications` | Email dispatch facade (will grow into Phase 5 multi-channel). | ✓ |
| `FXW_Privacy` | Personal-data export/erasure for WP privacy tools + WC order anonymization. | ✓ |
| `FXW_Blocks_Checkout` | Blocks-checkout support: Additional Checkout Fields + map via the_content + Store API order persistence. | ✓ |
| `FXW_Store_Hours` | Optional per-day opening schedule feeding `FXW_Checkout::is_store_open()`. | ✓ |
| `FXW_Pricing` | Admin-configurable fee tiers, free-delivery threshold, minimum order (settings extension + statics used by the rate/validation/REST paths). | ✓ |
| `FXW_Checkout_Address` | Hides WooCommerce's country/state/city/postcode fields on both classic and block checkouts (`woocommerce_get_country_locale`), relabels `address_1` to the single combined "Flat / Floor / Block / Society / Tower" field, fills hidden fields from the store base on order persistence (`woocommerce_checkout_update_order_meta`, `woocommerce_store_api_checkout_update_order_from_request`). | ✓ |
| `FXW_Dashboard_Render` / `FXW_Dashboard_Actions` | Dashboard page rendering / write handlers (v1.2.9 split). | ✓ |
| `FXW_Config` | Constants only. No instance. | n/a |
| `FXW_REST_Checkout_Controller` | Reference REST controller — follow this pattern for Phase 1+ REST endpoints. | n/a (registered by FXW_Core) |
| `FXW_Mapping_Service` | Google Maps wrapper (geocode, distance matrix). Static + instance methods. | n/a |
| `FXW_Rate_Limiter` | Rate limiting by action key. Static. | n/a |
| `FXW_Delivery_Fee` | `woocommerce_cart_calculate_fees` + `woocommerce_cart_shipping_method_full_label` hooks. | ✓ |
| `FXW_Address_Validator` | Stateless `validate_address_completeness` (heuristic). | n/a (static only) |

**Self-registration pattern:** each class with hooks has `new ClassName();` at the bottom of its file. No central wiring beyond `FXW_Core::require_once`'ing them.

---

## 5. Coding standards (MUST follow)

### PHP

1. **WordPress Coding Standards** for PHP, JS, CSS. Run `phpcs` if you have it.
2. **Every PHP file:** `if (!defined('ABSPATH')) { exit; }` as the first non-comment line.
3. **Official WordPress/WooCommerce hooks and APIs ONLY — never bypass the platforms.** This applies to the entire project and all future changes: checkout-page changes, fee/tax calculations, order/customer/session data, settings, emails, uninstall — everything goes through documented hooks (`woocommerce_checkout_fields`, `woocommerce_checkout_create_order`, `woocommerce_cart_calculate_fees`, `wc_get_orders`, Settings API, `WP_REST_Controller`, `WC_Customer`/`WC_Order` CRUD, `WC_Email`, WP HTTP API, `wp_safe_redirect`, etc.). No direct writes to WooCommerce/WordPress tables, no `$_SESSION`, no raw cURL, no shims around core flows. **When unsure of the official pattern, verify against current documentation first** (Context7: `/woocommerce/woocommerce` for WC, WordPress docs for WP) — do not guess or copy unverified patterns.
4. **Every AJAX handler:** verify nonce with `wp_verify_nonce()` AND check capabilities with `current_user_can()`. **No central wrapper.** Per-handler explicit.
5. **Sanitize ALL input** with the right primitive: `sanitize_text_field()`, `absint()`, `sanitize_email()`, `sanitize_textarea_field()`, etc. **`wp_unslash()` before `sanitize_*()`** on `$_POST`/`$_GET`/`$_REQUEST`.
6. **Escape ALL output** with the right primitive: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`. Match the context.
7. **Strict comparisons only** (`===` / `!==`) — never `==` / `!=`. Critical for auth checks.
8. **Null-check `WC()->session` and `WC()->customer`** before any method call: `WC()->session ? WC()->session->get('key') : null`.
9. **All queries use `$wpdb->prepare()`** with `%s`, `%d`, `%f` placeholders. **No PDO wrapper.**
10. **HPOS-aware order access:** use `wc_get_order($id)` (never `get_post($id)` for orders) and `$order->get_meta()` / `$order->update_meta_data()` (never `get_post_meta()` / `update_post_meta()` for order meta).
11. **`wp_safe_redirect()` always has a fallback URL:** `wp_safe_redirect($referer ? $referer : admin_url())`.
12. **`get_edit_post_link()` returns null under HPOS.** Fallback: `admin_url('admin.php?page=wc-orders&action=edit&id=' . $id)`.
13. **All translatable strings via `__()` / `_e()`** with `'foodxpress'` text domain.
14. **No class over 500 LOC.** Use the same split pattern as the v1.2.0 checkout split: orchestrator keeps the public API, sibling files `require_once`'d at the top, services extracted to `includes/services/`.

### JavaScript

1. `.textContent` not `.innerHTML` for untrusted data.
2. Guard global params with `typeof varName !== 'undefined'` checks before use.
3. Null-check `response.data` before accessing `.message` / `.label`.
4. The checkout script (`assets/js/checkout.js`) reads `fxw_checkout_params` localised by PHP — never hard-code URLs or nonces.

### CSS

1. **Scope styles to plugin classes** — never style `html, body` or other global selectors.
2. Mobile-first responsive design.

### Distance Matrix data shape (Google Maps)

```php
$distance_data['distance']  // object with ->value (metres) and ->text
$distance_data['duration']  // object with ->value (seconds) and ->text
```

**Always check** `isset($distance_data['distance']) && is_object($distance_data['distance']) && isset($distance_data['distance']->value)` before accessing `->value`. Same shape for `duration`.

---

## 6. Security checklist (verify on every PR)

- [ ] Nonce verification on every form + AJAX endpoint
- [ ] Capability check (`current_user_can()`) before any state change
- [ ] Input sanitisation before processing (right primitive for the type)
- [ ] Output escaping before rendering (right primitive for the context)
- [ ] `WC()->session` null-checked before any method call
- [ ] `WC()->customer` null-checked before any method call
- [ ] Strict comparisons for all auth checks
- [ ] No `get_post()` / `get_post_meta()` for order data (use HPOS APIs)
- [ ] `wp_safe_redirect()` always has a fallback URL
- [ ] `get_edit_post_link()` fallback for HPOS contexts

---

## 7. Running tests

```bash
cd ~/Desktop/FoodXpress-for-WooCommerce   # or wherever the project lives
php tests/FXWTestRunner.php
```

Expected: `Passed: 101, Failed: 0`. The runner checks:

- File structure (all required files exist, including the 3 checkout split files)
- Code quality (no unlimited queries, nonce verification on all AJAX files)
- Plugin headers (WordPress + WooCommerce)
- Hooks & filters (registered correctly, custom statuses registered)
- Security patterns (ABSPATH check, nonce verification)

**Run the test runner after every change. It must report 118/118 before commit.**

---

## 8. MCP / external tools available

| Tool | Purpose | How to call from a CLI agent |
|---|---|---|
| **Context7** | Up-to-date library documentation (WooCommerce, WordPress, PHP) — replaces stale web search | `mcporter call context7.resolve-library-id query=... libraryName=...` and `mcporter call context7.query-docs context7CompatibleLibraryID=/owner/repo query=...` |
| **GitHub CLI** | Repo ops, releases, issue management | `gh ...` (authenticated as `codermillat`) |
| **WordPress skills** | `wordpress-pro` and `wordpress-advanced-architecture` reference content lives in `skills/` | Read files directly |
| **mcporter** | Manages all MCP server configs. `mcporter list`, `mcporter call`, `mcporter config add/remove` | Binary at `/Users/mdmillathosen/.local/bin/mcporter` |

**Context7 note for the next agent:** Context7 auth was fixed on 2026-08-17 (a fresh API key now lives in `~/.cursor/mcp.json`); use Context7 normally. If `Invalid API key` reappears, the key in that file needs replacing — verify candidates with a direct curl to `https://mcp.context7.com/mcp` before writing, and note that an MCP connection caches its key until the session restarts. Anonymous access (no Authorization header) also works at lower rate limits as a fallback. If Context7 is unavailable entirely, fall back to the WooCommerce/WordPress source code in `wp-content/plugins/woocommerce/` if available, or to the bundled `wordpress-pro` / `wordpress-advanced-architecture` skills in `skills/`.

---

## 9. Open questions (from ROADMAP §6)

These are non-blocking for the next coding tool — pick the default in brackets unless told otherwise.

| Phase | Question | Default |
|---|---|---|
| 5 | VAPID keys for Web Push — auto-generate on activation? | **Yes, with admin warning** |
| 5 | ~~SMS at launch~~ | ✅ No — email + browser only |
| 6 | Chart library | **Chart.js v4** (lightest, sufficient for the use cases) |
| 7 | ~~Single restaurant vs multi-location default~~ | ✅ Owner-controlled dashboard toggle; schema always outlet-model |
| 7 | ~~Map library~~ | ✅ Google Maps primary, Leaflet fallback (no API key) |
| 8 | Test framework — PHPUnit+WP test suite or Pest? | **PHPUnit+WP** (matches the existing `FXWTestRunner` static style) |
| 8 | Bump min PHP to 8.0? | **Yes** in v2.0 |

---

## 10. Things to NOT do (intentional non-features)

These came up in the original RR/RDM backport analysis and were explicitly **rejected** as over-engineering for FX's scale:

- ❌ **No DI container.** Use plain `new` and static factories.
- ❌ **No PDO query wrapper.** `$wpdb->prepare()` is the right abstraction in WordPress.
- ❌ **No central AJAX-security wrapper.** Per-handler nonces are clearer at this scale.
- ❌ **No auto-GitHub-issue error reporter.** Privacy footgun. Use a `wp_die()`-to-log pattern instead.
- ❌ **No ML delivery-time predictions** in v1–v2. Add in v3.x post-launch.
- ❌ **No Site Health integration tests** — overlap with WP core 6.2+ Site Health.
- ❌ **No `index.php` "intentional content" files** in every directory (RDM's security-by-obscurity anti-pattern).
- ❌ **No SMS channel at launch.** Channel interface is designed so SMS can be added later without a refactor.
- ❌ **No `vendor/` directory.** Composer is allowed for new dependencies but each dep must be justified.

---

## 11. Recent changes (Phase 0 / v1.2.0 — 2026-08-17)

**Commit:** `140b19f` · **Tag:** `v1.2.0` · **Release:** [GitHub](https://github.com/codermillat/FoodXpress-for-WooCommerce/releases/tag/v1.2.0)

- Split `class-fxw-checkout.php` (1,046 LOC) into 3 single-purpose files + 2 new services, all under 500 LOC
- Replaced 4-line `.gitignore` with comprehensive 65-line version
- Added `LICENSE.md` (originally proprietary with WP/WC carve-out; relicensed to GPL-3.0-or-later in v1.2.1 when the repo went public)
- Bumped version 1.1.0 → 1.2.0
- Updated `tests/FXWTestRunner.php` to recognize the new files
- Removed 9 AI-tool config folders + 5 AI dev docs (all recoverable in `mavis-trash`); GitHub language tag will re-rank from Python to PHP on next index
- Created this `AGENTS.md`

**All 103 tests pass.** No runtime behaviour change — all hooks, AJAX endpoints, and class names preserved. `class-fxw-checkout.php` keeps its filename and call site, so external code referencing it continues to work.

---

## 12. Coordination with Mavis (this assistant)

- Mavis (Mavis) is the primary coding agent the user has been working with
- Mavis led the 3-repo consolidation (Aug 2026) and Phase 0 (v1.2.0)
- Mavis's working notes live in `docs/ANALYSIS.md`, `docs/ROADMAP.md`, and `CHANGELOG.md`
- When the user says "I will use another coding for the nexts", they mean a different agent/tool will take the next phase (likely Phase 1: data layer + 8 new `fxw_*` tables)
- The user prefers **decisive recommendations** over hedging. If a decision has a clear default in §9, use it. If a question isn't in §9, ask before building.

---

*End of AGENTS.md. Last updated 2026-08-17 for v1.2.0.*
