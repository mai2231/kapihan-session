# Kapihan — Setup at Hosting

PHP 7.4+ (mas maganda 8.x) + MySQL/MariaDB. Walang composer o npm na kailangan.

## A. Shared hosting (Hostinger, cPanel, atbp.)
1. Sa hosting panel: gumawa ng **MySQL database** at **user**, i-assign ang user sa database (All Privileges).
2. I-upload ang **laman** ng folder na ito sa `public_html`.
3. I-copy ang `config.sample.php` bilang `config.php` at punan ang `db_*`, `auto_create_db => false`, at `admin_initial_password`.
4. Buksan ang `https://iyong-domain/` — awtomatikong gagawa ng tables.
5. Mag-login sa `/admin/admin_login.php` (`admin` + password sa config).
6. **Products** → **Seed Default 24 Products** (o magdagdag ng sarili mong menu).
7. **Settings** → ilagay ang detalye ng store, payments, atbp. (tingnan sa ibaba).
8. I-on ang **Free SSL** sa panel, tapos i-uncomment ang HTTPS redirect sa `.htaccess`.

## B. Docker / VPS
```
# .env (katabi ng docker-compose.yml)
DB_PASS=...
DB_ROOT_PASS=...
ADMIN_PASSWORD=...
docker compose up -d --build
```
Bubukas sa `http://localhost:8080`. Pagkatapos baguhin ang code, i-run ulit ang `docker compose up -d --build`.

## C. Local (XAMPP)
`htdocs/kapihan`, i-start ang Apache + MySQL. Walang `config.php` = `root` na walang password at awtomatikong gagawa ng database. Ang unang admin account ay `admin`; ilagay ang gusto mong password sa `admin_initial_password` ng `config.php` bago ang unang bukas (kung wala, ang pansamantalang `admin123` ang gagamitin — palitan agad sa Settings → Security).

## Settings Center (Admin → Settings)
| Kategorya | Ano ang nagagawa |
|---|---|
| Store Information | Pangalan, tagline, address, contact, email, oras, Open/Closed, logo — makikita sa storefront, resibo, tracking page |
| Ordering | Online ordering ON/OFF, Dine-In / Take-Out / Pick-Up, minimum order, prep time, customer notes |
| Payments | Cash / GCash / Maya ON/OFF; account name, number, QR at payment instructions para sa GCash at Maya (lalabas bilang "Scan to Pay" sa checkout) |
| Notifications | Alerto sa bagong order (Kitchen at Admin), status alert sa customer, tunog, low-stock banner |
| KAPitan Bot | Chatbot ON/OFF, welcome message, personality (tono at wika), Store Knowledge, FAQs, privacy notice, history |
| Appearance | Logo, favicon at accent color. Ang currency ay laging Philippine Peso (₱) at hindi mapapalitan |
| Security | Palitan ang password, Last login, **Logout other sessions** |
| System | Status ng database/PHP/uploads, **Maintenance Mode** |

### KAPitan Bot (chatbot)
Sariling chatbot ng KAPIHAN — **walang external service, API key o subscription na kailangan**. Ang mga sagot ay galing sa:
1. Menu, presyo, availability at stock sa database (laging kasalukuyan — kapag binago mo ang presyo sa Product Management, iyon na agad ang sasabihin ng bot)
2. Store Information at Payments/Ordering settings (oras, address, bayad, order types, minimum order, prep time)
3. **Store Knowledge** at **FAQs** na isinulat mo sa Admin → Settings → KAPitan Bot
4. Ligtas na fallback: *"I’m not sure about that yet. Please contact KAPIHAN staff for assistance."* — hindi ito nag-iimbento.

Paalala: rule-based ito, kaya mas maganda ang sagot kapag kumpleto ang FAQs at Store Knowledge mo. Tumitingin ito sa mga keyword sa English, Tagalog at Taglish; hindi nito naiintindihan ang bawat posibleng tanong. Tingnan ang **KAPitan Bot** sa sidebar para malaman kung anong mga tanong ang hindi nasagot at idagdag ang mga iyon bilang FAQ.

**Conversations:** Admin → **KAPitan Bot** (sidebar) — may search, date filter, pagination, buksan ang buong usapan, burahin ang isa, o linisin ang luma. Ang customer ay kinikilala lang ng anonymous na session code. Hindi sine-save ang password, PIN, OTP o card number, at ang email at numero ng telepono ay ina-mask bago i-save. Awtomatikong binubura ang luma ayon sa *Keep conversations for (days)* (default 90).

### Maintenance Mode
Kapag ON, ang publiko ay makakakita ng "We'll be right back" page. Ang naka-login na admin ay makakapasok pa rin sa admin at storefront.

## Forgot password
Walang email na ipinapadala. Para mag-reset: ilagay sa `config.php` ang `'admin_reset_key' => 'mahabang-sikreto'` (hindi bababa sa 12 character; sa Docker: `ADMIN_RESET_KEY` sa `.env`), i-restart kung Docker, buksan ang **Forgot your password?** sa admin login, ilagay ang key at ang bagong password. Tanggalin ang key pagkatapos. Naka-off ang feature kung walang key.

## Mga larawan ng produkto
Admin → Products: mag-upload ng JPG, PNG o WEBP (hanggang 4MB) o maglagay ng URL. Kapag nag-edit ka nang hindi nag-upload, **nananatili** ang kasalukuyang larawan. Kung walang larawan o nawawala ang file, kayumangging placeholder ang lalabas — hindi sirang icon. May **Show on the storefront** toggle sa bawat produkto para itago ito nang hindi binubura.

## Seguridad ng session at pagbabayad
- Awtomatikong nagla-logout ang admin pagkatapos ng `admin_idle_minutes` (default 480 minuto = 8 oras) na walang aktibidad; puwedeng baguhin sa `config.php`.
- **Payment status:** ang bawat order ay may `pending` o `paid`. Ang GCash/Maya ay nananatiling *pending* hanggang pindutin ng admin ang **Verify payment** sa Customer Orders; walang awtomatikong verification dahil walang payment gateway.
- **Database updates:** awtomatiko at hindi mapanira (tingnan ang CHANGELOG.md). Kung hindi natuloy, makikita ito sa Settings → System bilang "Update needed".

## Mga page
| URL | Gamit |
|---|---|
| `/` | Menu at pag-order ng customer |
| `/track.php?order_id=..&t=..` | Live tracking (link/QR na may token) |
| `/receipt.php?order_id=..&t=..` | Resibo |
| `/admin/admin_login.php` | Admin login |
| `/admin/admin.php` | Dashboard: products, orders, reports, settings |
| `/admin/kitchen.php` | Kitchen board para sa staff |

## Bago i-live (checklist)
- [ ] Napalitan ang admin password (Settings → Security)
- [ ] Naka-set ang Store Information, Payments (GCash/Maya QR) at Ordering
- [ ] `debug` => false sa config.php
- [ ] HTTPS naka-on
- [ ] May backup ng database
- [ ] Nasubukan ang buong order flow sa phone

## Mga limitasyon
- Ang GCash/Maya ay **manual**: ipinapakita sa customer ang account/QR, pero walang awtomatikong pag-verify ng bayad. Kailangan ng PayMongo/Xendit para doon.
- Isa lang ang admin role at walang "forgot password" (palitan ang `password_hash` sa `admin_users` table gamit ang `password_hash()` ng PHP).
- Walang "Theme" selector: iisa ang disenyo ng storefront. Accent color, logo, favicon at currency ang puwedeng baguhin.
- Ang oras ng pagbubukas/pagsasara ay ipinapakita lang sa customer; ang **Store Status** ang nagbubukas o nagsasara ng ordering.
- Kung Nginx ang gamit (hindi Apache), hindi gagana ang `.htaccess`: i-block ang `/connection/`, `config.php`, at PHP execution sa `/uploads/`.
