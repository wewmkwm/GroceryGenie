# GroceryGenie Security Test Plan

This checklist captures the security-focused validation we should run across GroceryGenie’s multi-role PHP stack (customers, store owners, admins). Wherever possible, reference the live endpoints and the backing implementation for traceability.

---

## 1. Authentication & Session Management

1. **Login authentication (session-based)**  
   - Paths: `customer/customer_login.php`, `store_owner/store_owner_login.php`, `admin/admin_login.php`  
   - Steps: attempt to access role-gated URLs without logging in (`customer/customer_home.php`, `store_owner/store_owner_dashboard.php`, `admin/admin_dashboard.php`); expect redirects to the respective login page. After successful login, confirm `$_SESSION['*_id']` keys are set and required to reach restricted pages.

2. **Brute-force resiliency (rate limiting)**  
   - Simulate rapid repeated login attempts with bad credentials and confirm throttling or account lock rules (if absent, log a defect and propose mitigation such as incremental back-off or CAPTCHA after N failures).

3. **Session timeout**  
   - Reduce PHP session lifetime in `php.ini` or app config, stay idle beyond the limit, then ensure protected pages force re-authentication. Validate `logout.php` destroys sessions and cookies immediately.

4. **Session fixation**  
   - Capture session ID before login, authenticate, and verify the session ID changes (e.g., via `session_regenerate_id(true)`); otherwise mark as a gap.

5. **Remember-me / persistent cookies**  
   - If not supported, verify no sensitive data is stored client-side. If implemented later, confirm cookies are `HttpOnly`, `Secure`, and encrypted.

---

## 2. Credential Handling

1. **Password hashing**  
   - DB check: run `SELECT password FROM customers LIMIT 1` and confirm values are bcrypt/argon hashes (as used in `customer/customer_login.php` and `customer/customer_register.php`). No plaintext should exist.  

2. **Password transport**  
   - Ensure login and registration forms submit over HTTPS in production; verify no passwords get logged into PHP error logs or JS console.

3. **Reset / change password**  
   - Validate `customer/customer_reset_password.php` and any similar flows use time-bound tokens, enforce complexity, and invalidate tokens after use.

---

## 3. Input Validation & Injection Protection

1. **SQL injection**  
   - Attempt payloads like `' OR 1=1 --` in login, search (`customer/search.php`), chat (`customer/chat.php`), and admin filters (`admin/admin_reports.php`). Expect no auth bypass and error-free responses because prepared statements (`$conn->prepare`) and bound parameters are used.  
   - Run automated scanners (sqlmap) against staging; any positive finding blocks release.

2. **Cross-site scripting (XSS)**  
   - Try injecting `<script>alert(1)</script>` into recipe descriptions, chat messages, and profile fields. Confirm output is escaped via `htmlspecialchars()` in templates (`customer/customer_home.php`, `customer/customer_profile.php`). Highlight locations missing escaping.

3. **Cross-site request forgery (CSRF)**  
   - For state-changing forms (recipe CRUD, checkout, profile update), verify the `includes/security.php` helpers inject `csrf_token` fields (e.g., via `csrf_field()`) and that POST handlers call `verify_csrf_token(...)`. Any form missing the hidden token or server-side validation is a high-severity issue.

4. **File upload validation**  
   - Endpoints: `customer/customer_profile.php`, `customer/create_recipe.php`, `customer/save_recipe.php`, `store_owner/store_owner_add_item.php`, `store_owner/store_owner_edit_item.php`, `store_owner/store_owner_edit_profile.php`, and `customer/process_order_demo.php`. Attempt to upload PHP shells or oversized files; the shared `includes/upload_helper.php::gg_secure_upload()` now enforces MIME whitelists, size caps, and randomised filenames per directory. Confirm each form blocks disallowed uploads and that stored files reside in the expected `/uploads/...` paths without executable permissions.

---

## 4. Authorization & Access Control

1. **Role-based access**  
   - Confirm admins can reach only `admin/*` dashboards and store owners/customers receive HTTP 302/403 when attempting cross-role URLs. Example: try visiting `admin/admin_manage_storeowners.php` as a store owner and expect redirect to `admin_login.php`.

2. **Horizontal permission checks**  
   - Verify customers cannot view or modify other customers’ saved recipes, carts, or orders (`customer/my_orders.php`). Similarly, store owners must be limited to their own inventory and orders (`store_owner/store_owner_orders.php`). Attempt to tamper with IDs via query strings and POST bodies.

3. **API endpoints**  
   - For any `/api/` routes, ensure JWT/session validation and per-resource authorization are enforced. Attempt unauthenticated requests and check for proper 401/403 responses.

---

## 5. Transport & Infrastructure

1. **HTTPS enforcement**  
   - Verify production deployments redirect HTTP to HTTPS. Confirm HSTS headers are set (`Strict-Transport-Security`).  

2. **Secure headers**  
   - Use tools like Mozilla Observatory to check for `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, etc. Add missing headers via `.htaccess` or PHP middleware.

3. **Dependency vulnerabilities**  
   - Run `npm audit` for front-end assets and `composer audit`/`php security-checker` for PHP dependencies (if Composer is used). Track and patch CVEs.

4. **Server configuration**  
   - Confirm directory listings are disabled, error reporting is off in production, and file/folder permissions restrict write access to uploads only.

---

## 6. Sensitive Data Protection

1. **PII exposure**  
   - Inspect API responses and pages for leakage of email, phone, or address fields. Ensure only the owner/admin views sensitive details (`customer/customer_view_store_owner_info.php`).  

2. **Logging & monitoring**  
   - Review application and server logs to ensure they avoid storing passwords or full payment data. Implement monitoring/alerting for repeated failed logins or suspicious activity.

3. **Database backups**  
   - Verify backups are encrypted at rest and stored securely. Test restoration to ensure data integrity.

4. **Payment data**  
   - If payments are processed (see `customer/checkout.php`, `payments.sql`), ensure PCI-DSS alignment: no raw card data stored, tokenized flows, and TLS enforced.

---

## 7. Additional Security Scenarios

1. **DoS / resource exhaustion**  
   - Load-test expensive endpoints (`admin/admin_reports.php`, `store_owner/store_owner_sales_report.php`) to ensure they fail gracefully under heavy traffic and do not expose stack traces.

2. **Business logic abuse**  
   - Attempt to manipulate recipe prices, quantities, or order totals via client-side tweaks and ensure server-side validation recalculates totals (`customer/shopping.php`, `customer/checkout.php`).

3. **Third-party integrations**  
   - Validate API keys and secrets are stored outside version control (`.env`) and never exposed in front-end bundles.

4. **Penetration testing**  
   - Schedule periodic manual or external pen tests covering OWASP Top 10; record findings, remediation, and retest dates.

---

## Execution & Reporting

* Use Burp Suite/ZAP for manual testing, OWASP ZAP automation for CI, and documented test cases in the QA tracker.  
* Each failed test should capture: environment, exact payload, expected result, actual result, severity, and remediation owner.  
* Sign-off criteria: all high/critical issues resolved or mitigated, medium issues documented with target fix dates, and regression tests updated after each fix.

---

Keeping this plan current ensures GroceryGenie continuously protects user data and prevents privilege abuse across customer, store-owner, and admin surfaces.
