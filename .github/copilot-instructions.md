# TT SHOP agent instructions

## Project overview

- This is a procedural PHP 7/8 storefront for `TT SHOP`, not a framework application.
- Run it on XAMPP with Apache and MySQL. The project root is the document root and pages are requested directly, such as `index.php` and `giohang.php`.
- Database access is centralized in `connect.php`; the database name is `ttshop`, host is `127.0.0.1`, and the default local MySQL port is `3306`.
- The public storefront is at the repository root. The separate admin application is in `admin/`.
- Shared browser behavior is in `script.js`, `validate.js`, and page-specific inline JavaScript. Shared PHP validation is in `validate_helpers.php`.

## Repository map

- `index.php`, `header.php`, `footer.php`, `products.php`: public storefront shell and product sections.
- `category.php`, `chitietsanpham.php`: product browsing and product details.
- `add_to_cart.php`, `them_vao_gio.php`, `giohang.php`, `update_cart.php`, `remove_cart.php`: cart operations.
- `thanhtoan.php`, `phuongthucthanhtoan.php`, `xuly_dathang.php`, `dathangthanhcong.php`: checkout and order creation.
- `dangky.php`, `xulydangky.php`, `dangnhap.php`, `xulydangnhap.php`, `taikhoan.php`, `capnhat_tk.php`: customer registration, login, and account management.
- `quenmatkhau.php`, `xulyquenmatkhau.php`, `datlaimatkhau.php`, `xulydatlaimatkhau.php`, `reset_helpersemail.php`, `sql/`: password reset flow.
- `admin/check_admin.php`, `admin/login.php`, `admin/*.php`: admin authentication and CRUD screens.
- `csrf.php`: shared CSRF token helpers.
- `connect.php`: shared mysqli connection and database timezone setup.
- `validate_helpers.php`: phone, name, email, company, tax code, address, and password validation helpers.
- `style*.css`: page-specific CSS. Preserve or update the matching stylesheet instead of moving styles into a shared stylesheet unless requested.
- `images/` and `imagescategory/`: uploaded and static product assets.
- `.history/` contains historical snapshots. Do not edit or reuse files from `.history/` for implementation.

## Before making changes

- Identify every public and admin page that depends on the session keys or database tables being changed.
- Trace the full request flow: form/page -> PHP handler -> mysqli statement -> redirect or JSON response -> browser update.
- For cart changes, preserve the session cart shape:

  ```text
  $_SESSION['cart'][$productId] = {
      id, name, price, old_price, img, qty
  }
  ```

- For customer changes, preserve existing session keys where used:
  `is_login`, `user_id`, `user_ho`, `user_name`, `user_email`, `user_sdt`, `user_ngaysinh`, `user_diachi`.
- For admin changes, require `$_SESSION['admin_login'] === true` through `admin/check_admin.php`.
- Prefer surgical changes in the existing file that owns the behavior. Do not introduce a framework or large architectural refactor.

## PHP conventions

- Save PHP and HTML as UTF-8 and use Vietnamese UI text consistently.
- Start sessions with:

  ```php
  if (session_status() === PHP_SESSION_NONE) {
      session_start();
  }
  ```

- Use `require_once __DIR__ . '/connect.php';` and `require_once __DIR__ . '/validate_helpers.php';` for shared root dependencies. Use `../connect.php` for dependencies from `admin/`.
- Use `mysqli_prepare()`/`bind_param()` for SQL that contains request or database data. Never concatenate user input into SQL.
- Check `prepare()`, `execute()`, and affected rows where existing patterns do so.
- Reuse prepared statements when performing repeated inserts/updates.
- Use transactions for operations that must be atomic, as in order and order-detail creation.
- Roll back transactions and log server-side details on failures. Do not expose SQL, credentials, stack traces, or internal exception text to users.
- Keep public validation messages friendly and centralized in `validation_messages()`/`validation_message()` when they belong to validation behavior.
- Normalize and validate request input at the server boundary, even if the browser already validates it.
- Cast numeric values deliberately and enforce minimum quantities at one where the cart or checkout accepts quantities.
- Use `htmlspecialchars()` for dynamic text placed in HTML.
- Use `password_hash()` and `password_verify()` for passwords. Call `session_regenerate_id(true)` after a successful login.
- On auth or privilege changes, clear stale session keys where appropriate and redirect with `exit()`.
- Preserve existing redirect and flash-session patterns; do not replace them with JSON unless the endpoint is explicitly AJAX.

## Security and configuration

- Never print, commit, or modify credentials. `config_mail.php` is ignored and may contain real mail credentials; use `config_mail.example.php` as the template.
- Do not move credentials into source-controlled files or log password/reset email details.
- Add CSRF protection to state-changing POST handlers that change account, address, or order data. Use `csrf_token()` and `csrf_check()` from `csrf.php`.
- Keep `admin/check_admin.php` before any admin mutation or result page.
- Ensure customer resources are scoped to `$_SESSION['user_id']`, especially orders, addresses, reviews, and account updates.
- Protect file uploads: validate file type/size/server context, use an independent generated filename, and never trust the original filename or file extension alone.
- Do not weaken password, phone, email, company, tax-code, or reset-token validation.

## Frontend conventions

- Product identifiers and route parameters are passed through PHP and often exposed with `data-*` attributes or query strings.
- AJAX cart endpoints should retain JSON fields such as `status`, `message`, `cart_count`, `cart_total`, `qty`, and `item_total` unless a coordinated change updates every caller.
- Preserve `fetch()` request and JSON response patterns for cart operations.
- Use existing DOM classes (`.add-cart`, `.buy-now`, `.cart-plus`, `.cart-minus`, and quantity boxes) unless changing both markup and JavaScript together.
- Cache-bust existing styles with the established `?v=...` pattern when changing CSS that is commonly browser-cached.
- Do not introduce npm dependencies or frontend build tooling.

## Database and migrations

- Database assumptions are MySQL/MariaDB with the `mysqli` extension and `utf8mb4`.
- Main tables used by the application include `users`, `admins`, `products`, `category_products`, `donhang`, `chitiet_donhang`, `dia_chi`, and password-reset tables.
- Do not assume a schema field exists until it is confirmed in SQL or a live query; several migrations are maintained in `sql/`.
- Put schema changes in `sql/` with an `CREATE TABLE IF NOT EXISTS` or safe additive migration pattern where practical.
- Avoid destructive migrations. Preserve existing column names and Vietnamese domain values unless the task explicitly changes the schema.

## Validation and testing

- There is no Composer configuration, package manifest, or automated test suite in this repository.
- Before finishing, run the smallest relevant checks:

  ```powershell
  php -l C:\xampp\htdocs\TTSHOP-main\file.php
  php -l C:\xampp\htdocs\TTSHOP-main\admin\file.php
  ```

- After PHP syntax checks, start or verify Apache and MySQL through XAMPP and manually test the affected flow in a browser.
- For database changes, verify both the success path and rollback/constraint path.
- For cart or checkout changes, test quantity minimums, totals, login requirements, empty cart behavior, and both order header/detail persistence.
- For auth changes, test invalid credentials, successful login, logout, stale sessions, and redirect boundaries.
- For admin changes, test direct URL access without an admin session.
- Do not claim browser or database validation succeeded unless it was actually run.
