## Quick context

This is a small PHP e-commerce app (no framework). Core runtime assumptions:
- Runs on XAMPP/Apache + MySQL (project lives in `htdocs/torklub`).
- Database connection and session are initialized in `config.php` (PDO, exceptions enabled).
- Frontend is plain PHP templates with inline SQL using prepared statements.

## High-level architecture
- Public storefront: `index.php`, `product.php`, `cart.php`, `checkout.php`, `place_order.php`, `thank_you.php`.
- Admin area: `admin/*` (login -> `admin/index.php`, protected by `admin/auth_check.php`).
- Data model (referenced tables): `products`, `product_variants`, `product_images`, `tags`, `product_tags`, `users`, `orders`.
- Image files and import CSVs live under `images/`.

## Important patterns & conventions (use these exactly)
- Always include `config.php` at the top of pages to get `$pdo` and `$_SESSION` (examples: `product.php`, `cart.php`).
- Admin pages include `../config.php` and then `include 'auth_check.php'` to protect the page (examples: `admin/import.php`, `admin/import_batch.php`).
- Use prepared statements with `$pdo->prepare(...)`/`execute([...])` and `fetch()`/`fetchAll()` — follow existing style and return types (PDO::FETCH_ASSOC).
- Session-based cart: `$_SESSION['cart']` maps variant_id => quantity. Cart endpoints use `cart.php?action=add|remove|update`.

## Import flow (explicit example)
- Upload CSV: open `admin/import.php` and upload a Shopify CSV export (fields used include `Handle`, `Title`, `Image Src`, `Image Position`, `Variant SKU`, `Variant Price`, `Tags`).
- Start import: `admin/import.php` triggers client-side batches that call `admin/import_batch.php?file=<tmp>&offset=<n>&limit=<batch>` which returns JSON `{processed_count}` or `error`.
- `import_batch.php` saves images to `images/` by downloading `Image Src` and inserts/updates `products`, `product_variants`, `product_images`, `tags`, `product_tags` within a transaction. Keep batch size default (50) when adding automation.

## Developer/run notes
- Local run: start XAMPP (Apache + MySQL) and load the site at http://localhost/torklub/.
- DB: `config.php` defaults to DB name `torklub` and DB user `root` with blank password (adjust for your environment).
- If debugging: enable PHP errors in `php.ini` or add `ini_set('display_errors',1); error_reporting(E_ALL);` in `config.php` for local dev.

## Where to change things safely
- Add new pages: `include 'config.php'` at top. If admin-only, include `../config.php` and `auth_check.php` (see `admin/products.php`).
- To add new columns/tables, update code that reads/writes in `import_batch.php` and `product.php` (search for column names like `seo_title`, `Variant SKU`).

## Common pitfalls for automated edits
- Don't assume a framework or routing — file-based entrypoints are used; update links and `action` query strings if you rename endpoints.
- Image URLs can be remote — `import_batch.php` uses `file_get_contents` with a UA header then writes to `images/`. Expect missing images to return null; handle null before writing DB rows.
- Many pages echo HTML directly (some product descriptions contain raw HTML from Shopify). When changing rendering, preserve existing escaping patterns: use `htmlspecialchars()` on user-supplied values but the product `description` intentionally allows HTML.

## Safety & auth
- Admin sessions: login pages set `$_SESSION['admin_user_id']` and `auth_check.php` checks for that value. Reuse this pattern when creating new admin routes.
- Passwords use PHP `password_hash()`/`password_verify()` style already; follow same functions for any user/password changes.

## Helpful file pointers (examples you can cite)
- DB + session bootstrap: `config.php`
- Product page & variant JS logic: `product.php` (uses `data-` attributes for price/sku/image)
- Cart endpoints: `cart.php` (add/remove/update flows)
- Import UI + client batching: `admin/import.php` and `admin/import_batch.php`
- Admin guards: `admin/auth_check.php`

If anything seems unclear or you'd like me to add a short checklist for adding new admin pages or for automated refactors (e.g., rename a DB column), tell me which area and I will update this file with a targeted checklist and examples.
