# KAPIHAN — Repaired features, changed files and migrations

Pinagbatayan: ang orihinal na `kapihan-complete (1).zip`. Hindi ni-rebuild ang project; ang UI ay pinanatili (tingnan ang TESTING.md para sa paghahambing).

## Mga naayos at nadagdag
| Lugar | Ano ang nangyari | Ano ang ginawa |
|---|---|---|
| Menu | "Inihahanda pa ang menu" kahit may products | Tahimik na nilulunok ang DB error. Ngayon: may produkto → ipinapakita; walang aktibong produkto → empty message; may error → "Unable to load the menu" + Try again + auto-retry |
| Larawan ng produkto | Brown placeholder; nabubura ang larawan sa edit | Tamang path (`../` sa admin), pagsusuri kung umiiral ang file, ang kasalukuyang larawan ay galing sa DB at hindi sa browser, ligtas na upload (JPG/PNG/WEBP, 4MB), ulat ng nawawalang file |
| Checkout / order | — | Presyo at total ay kinukuwenta ng server mula sa DB; validation ng pangalan, cart, item (umiiral at aktibo), stock, minimum order, payment method at order type; transaction; walang native `alert()` |
| Payments | — | Logo ng GCash/Maya, "Scan to Pay" na may QR, account name at instructions mula sa Admin; `payment_status` (pending hanggang i-verify ng admin); reference `#ORD-n` |
| Admin login | Maling rejection, lumalabas ang default password | "Incorrect username or password.", walang default credentials sa login page, Forgot password (reset key), session regeneration, idle timeout, "Logout other sessions" |
| KAPitan Bot | External-AI style na config | Sariling rule-based engine; walang model/API key; conversations sa admin (search, date filter, pagination, delete); privacy masking |
| Settings | Password lang | Settings Center (Store, Ordering, Payments, Notifications, KAPitan Bot, Appearance, Security, System) |
| Currency | May USD/EUR | Fixed na Philippine Peso (₱), `₱1,250.00` ang format |
| Seguridad | Walang CSRF, may XSS sa ilang output, na-expose ang `.env` | CSRF sa lahat ng admin POST, escaping, prepared statements, `.htaccess` + `.dockerignore`, token sa tracking/resibo |
| Grammarly | Galing sa browser extension | Walang integration sa project; may opt-out attributes sa mga input |

## Mga file na binago o idinagdag
- **Core:** `connection/db.php` (config, schema at migrations, helpers), `index.php` (menu, checkout, chatbot UI), `save_order.php`, `menu.php`, `order_status.php`, `track.php`, `receipt.php`, `activity.php` (walang pagbabago).
- **Admin:** `admin/admin.php`, `admin/admin_login.php`, `admin/reset_password.php` (bago), `admin/kitchen.php`, `admin/kitchen_data.php`, `admin/kitchen_advance.php`.
- **Chatbot:** `kapitan_chat.php` (bago; pinalitan ang lumang `api.php` na sira at hindi ginagamit).
- **Assets:** `assets/kapihan-ui.js`, `assets/admin-settings.js`, `assets/img/{gcash,maya,kapihan-logo}.png`.
- **Config/Docker:** `config.sample.php`, `docker-compose.yml`, `Dockerfile`, `.dockerignore`, `.htaccess`, `database.sql`, `README.md`.

## Database migrations (awtomatiko, hindi mapanira, ligtas ulitin)
Tumatakbo sa unang request kapag mas mababa ang version sa `schema_info`. Walang `DROP`/`TRUNCATE`; `CREATE ... IF NOT EXISTS` at `ADD COLUMN` lang kung wala pa.
| v | Idinagdag |
|---|---|
| 1–2 | Base tables, pag-aayos ng lumang columns (`orders.status` → VARCHAR, `user_id` nullable) |
| 3 | `orders.public_token` (para sa tracking/resibo) |
| 4 | `settings`, `orders.order_type`, `orders.notes`, `admin_users.last_login_at / prev_login_at / session_epoch` |
| 5 | `kapitan_conversations`, `kapitan_messages` (FK, cascade), `kapitan_faqs`; tinanggal ang lumang `ai_*` settings |
| 6 | `products.is_active` (default 1); tinanggal ang `currency` setting |
| 7 | `orders.payment_status` (default `pending`), `orders.payment_verified_at`. Ang mga **natapos nang cash order** lang ang minarkahang `paid`; ang GCash/Maya ay nananatiling `pending` |

Walang product, order, account o larawan na binura o binago.
