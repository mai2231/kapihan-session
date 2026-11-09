# KAPIHAN — Testing report

Petsa ng huling pagsubok: ayon sa build na ito. **Hindi nagagamit ng environment kung saan ginawa ang pagsusuri ang PHP, MySQL, Docker at isang browser**, kaya ang mga ito ay *hindi* nasubukan nang tuwiran.

## Pasado (aktwal na tumakbo)
| Pagsubok | Resulta |
|---|---|
| Storefront script sa Node (mock na page at server): cart, stock cap, sync ng menu, presyo, checkout, validation | 8 / 8 |
| KAPitan Bot widget at chat flow (history, quick actions, link sa tracking, disable/enable) | 14 / 14 |
| Menu error vs empty, retry, ₱ format, QR panel, GCash reference, name validation, unavailable item | 11 / 11 |
| Bot engine (Python na kopya na kinukuha ang totoong keyword tables at templates mula sa `kapitan_chat.php`) — halos 80 na tanong sa English, Tagalog at Taglish, FAQ, knowledge, privacy | Pasado |
| SQL vs schema: 86 statements sa lahat ng PHP; walang maling table o column | Pasado (4 false positive ng heuristic, na-verify) |
| Balanse ng bracket at `if/endif` sa lahat ng PHP; syntax ng JavaScript | Pasado |
| Walang native na `alert()`, `confirm()`, `prompt()`; walang pangalan ng external AI provider o API key saanman | Pasado |
| Regex ng `.htaccess` laban sa `.env`, `config.php`, atbp. | Pasado (hindi sa mismong Apache) |
| Paghahambing sa orihinal na ZIP: 85% ng linya ng `index.php` ay hindi nagalaw; 27/27 element IDs at 3/3 section ay nananatili | Pasado |

## Hindi nasubukan (kailangan mong subukan)
1. **`php -l`** sa bawat PHP file: `docker compose exec web sh -c 'find /var/www/html -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"'` — dapat walang lumabas.
2. Aktwal na migration sa totoong MySQL (Admin → Settings → System → "Database structure" ay dapat "Up to date (v7)").
3. Login, logout, idle timeout at password reset.
4. Pag-upload at pag-edit ng larawan ng produkto; larawan sa admin at storefront.
5. Pag-order mula simula hanggang dulo, kasama ang stock at transaction.
6. Pagbabago ng payment status sa Customer Orders.
7. Pag-save ng chat sa database at ang pagbasa nito sa admin.
8. Hitsura sa desktop, tablet at mobile; browser console.
9. `.env` ay 403 sa `http://localhost:8080/.env`.
10. Hindi pa tuwirang nasubukan ang interaksiyon sa totoong GCash/Maya; manual ang verification, walang payment gateway.

## Alam na limitasyon
- Rule-based ang KAPitan Bot: hindi nito naiintindihan ang bawat tanong; kailangan itong turuan ng FAQs at Store Knowledge.
- Walang customer accounts, kaya anonymous ang chat at guest checkout lang.
- Walang audit log at walang pamamahala ng kategorya (apat na kategorya ang nakatakda); walang search box sa storefront (may category tabs).
- Isang admin role lang.
