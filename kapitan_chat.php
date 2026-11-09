<?php
// KAPitan Bot — sariling chat system ng KAPIHAN.
// Walang external service: ang mga sagot ay galing sa database ng KAPIHAN (menu, presyo, stock), sa Settings,
// sa Store Knowledge at FAQs na isinulat ng admin. Kapag hindi alam, may ligtas na fallback — hindi nag-iimbento.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once 'connection/db.php';

/* ================================================================
   TEXT HELPERS
   ================================================================ */

function kb_norm(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $s = strtr($s, [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ñ' => 'n', '’' => "'", '‘' => "'",
    ]);
    $s = str_replace("'", '', $s);
    $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
    return trim((string)$s);
}

function kb_tokens(string $norm): array {
    return $norm === '' ? [] : array_values(array_filter(explode(' ', $norm), fn($t) => $t !== ''));
}

// Hinahanap ang phrase bilang buong salita/parirala sa loob ng " normalized text "
function kb_has(string $padded, array $phrases): bool {
    foreach ($phrases as $p) {
        $q = kb_norm($p);
        if ($q !== '' && strpos($padded, ' ' . $q . ' ') !== false) return true;
    }
    return false;
}

// Pantay na token, na may pagpapatawad sa maliit na typo para sa mahahabang salita
function kb_tok_eq(string $a, string $b): bool {
    if ($a === $b) return true;
    $la = strlen($a); $lb = strlen($b);
    if ($la < 5 || $lb < 5 || $la > 40 || $lb > 40) return false;
    $max = ($la >= 9 && $lb >= 9) ? 2 : 1;
    return abs($la - $lb) <= $max && levenshtein($a, $b) <= $max;
}

function kb_stopwords(): array {
    return ['a','an','the','is','are','am','to','of','in','on','at','for','and','or','do','does','did','you','your','i','me','my','we','our','it','this','that','with',
            'can','could','would','will','please','po','opo','ba','na','ng','sa','ang','mga','ay','ko','mo','ka','ako','ikaw','kayo','namin','natin','yung','yun','ito','iyan',
            'ano','what','how','when','where','who','which','there','any','have','has','pa','lang','din','rin','naman','nga','kasi','pwede','puwede','may','meron','mayroon','be','if','so','ba','ka'];
}

function kb_keywords(string $text): array {
    $stop = kb_stopwords();
    return array_values(array_unique(array_filter(kb_tokens(kb_norm($text)), fn($t) => !in_array($t, $stop, true) && strlen($t) >= 2)));
}

// Gaano karami ng "target" keywords ang nasa mensahe (0..1, hanggang 4 ang binibilang)
function kb_overlap(array $msgKeys, array $targetKeys): array {
    if (!$targetKeys || !$msgKeys) return [0.0, 0];
    $common = 0;
    foreach ($targetKeys as $t) {
        foreach ($msgKeys as $m) { if (kb_tok_eq($m, $t)) { $common++; break; } }
    }
    $denom = max(1, min(count($targetKeys), 4));
    return [$common / $denom, $common];
}

/* ================================================================
   KEYWORD TABLES (English / Tagalog / Taglish)
   ================================================================ */

function kb_intents(): array {
    return [
        'greet'   => ['hi', 'hello', 'hey', 'hola', 'kumusta', 'kamusta', 'musta', 'magandang umaga', 'magandang hapon', 'magandang gabi', 'magandang araw', 'good morning', 'good afternoon', 'good evening', 'uy', 'yo'],
        'thanks'  => ['thanks', 'thank you', 'thankyou', 'thx', 'tnx', 'ty', 'salamat', 'maraming salamat', 'salamat po'],
        'bye'     => ['bye', 'goodbye', 'paalam', 'see you', 'ingat', 'sige na'],
        'about'   => ['who are you', 'sino ka', 'ano ka', 'what are you', 'are you a bot', 'are you real', 'bot ba', 'tao ba', 'sino ka ba', 'your name', 'pangalan mo'],
        'help'    => ['help', 'tulong', 'what can you do', 'ano kaya mo', 'ano ang kaya mo', 'commands', 'ano pwede', 'what can i ask'],
        'hours'   => ['hours', 'opening hours', 'open', 'opening', 'close', 'closing', 'oras', 'bukas', 'sarado', 'anong oras', 'what time', 'schedule', 'until what time', 'hanggang anong oras'],
        'location'=> ['address', 'location', 'saan', 'lugar', 'nasaan', 'directions', 'paano pumunta', 'how to get there', 'where are you', 'where is the store', 'where is kapihan', 'where is your shop', 'saan kayo'],
        'contact' => ['contact', 'call', 'phone', 'phone number', 'contact number', 'tawag', 'telepono', 'email', 'text', 'messenger', 'facebook', 'hotline', 'reach you', 'cellphone number'],
        'menu'    => ['menu', 'what do you have', 'what do you sell', 'what do you serve', 'ano meron', 'ano ang meron', 'anong meron', 'ano ang ibinebenta', 'mga ibinebenta', 'offerings', 'products', 'mga produkto', 'available items', 'list of items', 'what is on the menu', 'whats on the menu', 'ano ang nasa menu', 'ano ang menu', 'show menu', 'ano ang inyong menu'],
        'price'   => ['price', 'prices', 'how much', 'magkano', 'presyo', 'cost', 'hm', 'ilan ang', 'how much is'],
        'avail'   => ['available', 'availability', 'meron', 'mayroon', 'may', 'do you have', 'have you', 'stock', 'sold out', 'ubos', 'out of stock', 'still available', 'meron ba', 'may ba', 'meron pa', 'may pa'],
        'recommend' => ['recommend', 'recommendation', 'recommendations', 'recommended', 'suggest', 'suggestion', 'suggestions', 'marerecommend', 'irerekomenda', 'irekomenda', 'rekomenda', 'mairerekomenda', 'magrekomenda', 'best seller', 'bestseller', 'best sellers', 'popular', 'top pick', 'paborito', 'favorite', 'ano masarap', 'anong masarap', 'ano bang masarap', 'ano maganda', 'anong maganda', 'ano ang masarap', 'whats good', 'what is good', 'surprise me', 'ikaw na bahala', 'bahala ka', 'ano bagay', 'ano ang bagay', 'what should i order', 'what should i get', 'what to order', 'what to drink', 'what can i order', 'ano iinumin', 'ano ang iinumin', 'ano order', 'anong order', 'pili ka', 'ano ang best', 'ano best', 'help me choose', 'help me pick', 'pumili', 'tulungan mo akong pumili', 'ano pipiliin'],
        'want'    => ['gusto', 'gusto ko', 'want', 'i want', 'craving', 'trip', 'trip ko', 'looking for', 'hanap', 'hanap ko', 'pang', 'bagay', 'mood', 'in the mood', 'feel ko', 'feel'],
        'payment' => ['payment', 'payments', 'pay', 'paying', 'bayad', 'magbayad', 'bayaran', 'gcash', 'g cash', 'maya', 'paymaya', 'cash', 'mode of payment', 'payment method', 'payment methods', 'card', 'credit card', 'debit card', 'bank', 'qr', 'e wallet', 'ewallet', 'online payment'],
        'how_order' => ['how to order', 'paano umorder', 'paano mag order', 'paano magorder', 'how do i order', 'order process', 'ordering process', 'paano bumili', 'how to place an order', 'how it works', 'paano gamitin', 'paano mag place'],
        'order_types' => ['dine in', 'take out', 'takeout', 'pick up', 'pickup', 'delivery', 'deliver', 'mag deliver', 'kakain dito', 'for here', 'to go', 'togo', 'order types', 'uri ng order'],
        'min_order' => ['minimum', 'min order', 'minimum order', 'minimum na order', 'pinakamababa'],
        'prep'    => ['how long', 'gaano katagal', 'ilang minuto', 'how many minutes', 'preparation time', 'prep time', 'matagal ba', 'waiting time', 'abutin', 'how fast'],
        'order_word' => ['order', 'orders', 'oder', 'pinorder', 'inorder'],
        'order_status' => ['my order', 'order ko', 'track', 'tracking', 'status', 'asan na', 'nasaan na', 'where is my', 'ready na', 'tapos na', 'done na', 'check my order', 'check order', 'follow up', 'update on', 'ready ba'],
    ];
}

// Mga salita na tumutukoy sa kategorya ng menu (key = category sa database)
function kb_categories(): array {
    return [
        'coffee'   => ['coffee', 'kape', 'hot coffee', 'mainit na kape', 'espresso drinks'],
        'cold'     => ['cold', 'iced', 'malamig', 'ice', 'yelo', 'cold drinks', 'iced drinks', 'iced coffee', 'cold brew', 'pampalamig'],
        'special'  => ['non coffee', 'noncoffee', 'special', 'tsokolate', 'hot chocolate', 'tea', 'milk tea', 'matcha', 'ayaw ng kape', 'walang kape', 'hindi kape', 'no coffee', 'not coffee'],
        'merienda' => ['merienda', 'meryenda', 'snack', 'snacks', 'pastry', 'pastries', 'bread', 'pandesal', 'ensaymada', 'food', 'pagkain', 'kainin', 'tinapay', 'cake', 'dessert', 'gutom', 'hungry'],
    ];
}

// Pipiliin ang pinaka-tiyak na kategorya kapag marami ang nabanggit ("malamig na kape" = cold)
function bot_pick_category(string $padded): ?string {
    $cats = kb_categories();
    foreach (['cold', 'special', 'merienda', 'coffee'] as $cat) {
        if (kb_has($padded, $cats[$cat])) return $cat;
    }
    return null;
}

function kb_cat_labels(): array {
    return ['coffee' => 'Hot Coffee', 'cold' => 'Iced Drinks', 'special' => 'Non-Coffee', 'merienda' => 'Merienda'];
}

// Mga salita ng panlasa/kagustuhan -> tag
function kb_taste_words(): array {
    return [
        'cold'      => ['cold', 'iced', 'malamig', 'ice', 'yelo', 'refreshing', 'pampalamig', 'summer', 'mainit ang panahon'],
        'hot'       => ['hot', 'mainit', 'warm', 'maginaw', 'malamig ang panahon', 'ulan'],
        'sweet'     => ['sweet', 'matamis', 'tamis', 'caramel', 'dessert', 'sugar', 'asukal', 'sweetened'],
        'strong'    => ['strong', 'matapang', 'bold', 'kick', 'puyat', 'antok', 'pampagising', 'pampa gising', 'energy', 'double shot'],
        'bitter'    => ['bitter', 'mapait', 'black', 'unsweetened', 'walang asukal', 'no sugar'],
        'creamy'    => ['creamy', 'milky', 'gatas', 'milk', 'cream', 'latte'],
        'chocolate' => ['chocolate', 'choco', 'tsokolate', 'mocha', 'cocoa'],
        'classic'   => ['classic', 'klasiko', 'simple', 'plain', 'traditional', 'regular', 'smooth', 'makinis', 'basic', 'original', 'barako'],
        'noncoffee' => ['non coffee', 'no coffee', 'ayaw ng kape', 'walang kape', 'hindi kape', 'not coffee', 'tea', 'matcha'],
        'snack'     => ['snack', 'snacks', 'merienda', 'meryenda', 'food', 'pagkain', 'kain', 'tinapay', 'pastry', 'gutom', 'hungry', 'pandesal', 'ensaymada'],
        'cheap'     => ['cheap', 'mura', 'budget', 'tipid', 'affordable', 'pinakamura', 'sulit'],
    ];
}

function bot_generic_tokens(): array {
    return ['coffee', 'kape', 'hot', 'cold', 'iced', 'ice', 'drink', 'drinks', 'shot', 'cup', 'mainit', 'malamig', 'large', 'small', 'medium', 'the', 'a', 'of'];
}

/* ================================================================
   PRIVACY: huwag mag-imbak ng sensitibong impormasyon
   ================================================================ */

function bot_is_sensitive(string $m): bool {
    if (preg_match('/(?:\d[ -]?){13,19}/', $m)) return true;                               // mukhang card number
    $kw = '(?:password|passcode|pin|otp|cvv|cvc|security code|verification code|one time password)';
    if (preg_match('/\b' . $kw . '\b/i', $m)
        && (preg_match('/\d{3,}/', $m) || preg_match('/\b' . $kw . '\b\s*(?:is|ay|ko|ang|yung|:|=)\s*\S+/i', $m))) return true;
    return false;
}

// Bago i-save: tanggalin ang email at numero ng telepono (hindi kailangan ng chatbot)
function bot_mask(string $m): string {
    $m = preg_replace('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', '[email]', $m);
    $m = preg_replace('/\+?\d[\d\s\-]{8,}\d/', '[number]', $m);
    return (string)$m;
}

/* ================================================================
   WIKA AT TONO
   ================================================================ */

function bot_lang_key(): int {
    $l = setting('bot_language');
    return $l === 'english' ? 0 : ($l === 'tagalog' ? 1 : 2);
}

function bot_strings(): array {
    // [English, Tagalog, Taglish]
    return [
        'hello' => ["Hi! I'm %1\$s ☕ Welcome to %2\$s! How can I help you today?", "Kumusta! Ako si %1\$s ☕ Maligayang pagdating sa %2\$s! Ano ang maitutulong ko sa iyo?", "Hi! Ako si %1\$s ☕ Welcome sa %2\$s! Ano'ng maitutulong ko sa'yo today?"],
        'thanks' => ["You're welcome! Let me know if you need anything else ☕", "Walang anuman! Sabihin mo lang kung may kailangan ka pa ☕", "Walang anuman! Message ka lang ulit if may kailangan ka pa ☕"],
        'bye' => ["Thanks for chatting! See you at %s ☕", "Salamat sa pakikipag-usap! Hanggang sa muli sa %s ☕", "Salamat! See you sa %s ☕"],
        'about' => ["I'm %1\$s, the chat assistant of %2\$s. I can help with our menu, prices, store hours, payments and your orders ☕", "Ako si %1\$s, ang chat assistant ng %2\$s. Matutulungan kita sa menu, presyo, oras ng bukas, bayad at mga order mo ☕", "Ako si %1\$s, ang chat assistant ng %2\$s. I can help with the menu, prices, store hours, payments at orders mo ☕"],
        'help' => ["I can help with:\n• Menu, prices and what's available\n• Recommendations\n• Store hours, address and contact\n• Payment methods and how to order\n• Checking your latest order", "Matutulungan kita sa:\n• Menu, presyo at kung ano ang available\n• Mga rekomendasyon\n• Oras ng bukas, address at contact\n• Paraan ng bayad at paano mag-order\n• Pag-check ng pinakahuling order mo", "Matutulungan kita sa:\n• Menu, prices at kung ano ang available\n• Recommendations\n• Store hours, address at contact\n• Payment methods at paano mag-order\n• Pag-check ng latest order mo"],
        'hours' => ["We're open daily from %1\$s. Right now we're %2\$s.", "Bukas kami araw-araw mula %1\$s. Sa ngayon, %2\$s kami.", "Open kami daily from %1\$s. Sa ngayon, %2\$s kami."],
        'st_open' => ['open', 'bukas', 'open'], 'st_closed' => ['closed', 'sarado', 'closed'],
        'location' => ["You can find us at: %s.", "Matatagpuan kami sa: %s.", "Located kami sa: %s."],
        'no_location' => ["I don't have our address on file yet. Please contact KAPIHAN staff.", "Wala pa akong address na nakatala. Pakikontak ang KAPIHAN staff.", "Wala pa akong address na naka-save. Please contact KAPIHAN staff."],
        'contact' => ["You can reach us at %s.", "Maaabot mo kami sa %s.", "You can reach us at %s."],
        'no_contact' => ["I don't have a contact number on file yet. Please visit us in store.", "Wala pa akong contact number na nakatala. Pumunta na lang sa tindahan.", "Wala pa akong contact number na naka-save. Pasyal ka na lang sa store."],
        'menu' => ["Here's what we serve:\n%s\nTap “View Menu” to see everything with photos.", "Ito ang mga mayroon kami:\n%s\nPindutin ang “View Menu” para makita lahat kasama ang larawan.", "Ito ang meron kami:\n%s\nTap “View Menu” para makita lahat with photos."],
        'menu_empty' => ["Our menu isn't available right now. Please check back soon.", "Wala pang menu sa ngayon. Balik ka ulit mamaya.", "Wala pang menu ngayon. Balik ka ulit later."],
        'cat' => ["Our %1\$s:\n%2\$s", "Ang aming %1\$s:\n%2\$s", "Our %1\$s:\n%2\$s"],
        'cat_empty' => ["We don't have anything in that category right now.", "Wala pa kaming nakalagay sa kategoryang iyan ngayon.", "Wala pa kaming items sa category na yan ngayon."],
        'price_one' => ["%1\$s is %2\$s.", "%2\$s ang %1\$s.", "%1\$s is %2\$s."],
        'price_many' => ["Here are the prices:\n%s", "Ito ang mga presyo:\n%s", "Ito ang prices:\n%s"],
        'avail_yes' => ["Yes, %1\$s is available — %2\$s.", "Meron kaming %1\$s — %2\$s.", "Yes, may %1\$s kami — %2\$s."],
        'avail_no' => ["Sorry, %s is sold out right now.", "Pasensya na, ubos na ang %s ngayon.", "Sorry, sold out na ang %s ngayon."],
        'sold_out' => ['sold out', 'ubos na', 'sold out'],
        'which_item' => ["Which item do you mean? Tap “View Menu” to see everything, or tell me the name.", "Alin ang tinutukoy mo? Pindutin ang “View Menu” o sabihin ang pangalan.", "Alin yung tinutukoy mo? Tap “View Menu” or sabihin mo ang name."],
        'rec' => ["If you'd like %1\$s, try our %2\$s (%3\$s) ☕", "Kung gusto mo ng %1\$s, subukan mo ang %2\$s natin (%3\$s) ☕", "Kung gusto mo ng %1\$s, try mo ang %2\$s natin (%3\$s) ☕"],
        'rec_more' => ["Other good picks:\n%s", "Iba pang magandang pagpipilian:\n%s", "Other good picks:\n%s"],
        'rec_ask' => ["Tell me what you feel like — hot or cold, sweet or strong — and I'll suggest something.", "Sabihin mo kung ano ang gusto mo — mainit o malamig, matamis o matapang — at magrerekomenda ako.", "Sabihin mo lang kung anong trip mo — hot or cold, sweet or strong — and I'll suggest something."],
        'rec_none' => ["I couldn't find a good match right now. Tap “View Menu” to browse everything.", "Wala akong makitang akma ngayon. Pindutin ang “View Menu” para makita lahat.", "Wala akong makitang match ngayon. Tap “View Menu” para makita lahat."],
        't_classic' => ['a smooth, classic coffee', 'makinis at klasikong kape', 'smooth at classic na coffee'],
        't_cold' => ['something cold', 'malamig na inumin', 'something cold'],
        't_hot' => ['something hot', 'mainit na inumin', 'something hot'],
        't_sweet' => ['something sweet', 'matamis', 'something sweet'],
        't_strong' => ['a strong coffee', 'matapang na kape', 'matapang na coffee'],
        't_bitter' => ['a bold, bitter coffee', 'mapait at matapang na kape', 'bold at mapait na coffee'],
        't_creamy' => ['something creamy', 'creamy na inumin', 'something creamy'],
        't_chocolate' => ['something chocolatey', 'tsokolate', 'something chocolatey'],
        't_noncoffee' => ['a non-coffee drink', 'inuming walang kape', 'non-coffee drink'],
        't_snack' => ['a snack', 'meryenda', 'merienda'],
        't_cheap' => ['something budget-friendly', 'mura at sulit', 'budget-friendly'],
        't_any' => ['something tasty', 'masarap', 'something tasty'],
        'pay_all' => ["We accept: %s.", "Tumatanggap kami ng: %s.", "We accept: %s."],
        'pay_ewallet' => [" For %s, the account details and QR code appear at checkout once you choose it.", " Para sa %s, makikita ang account details at QR code sa checkout kapag pinili mo ito.", " Para sa %s, lalabas ang account details at QR code sa checkout kapag pinili mo."],
        'pay_yes' => ["Yes, we accept %s. The details appear at checkout.", "Oo, tumatanggap kami ng %s. Makikita ang detalye sa checkout.", "Yes, tumatanggap kami ng %s. Makikita ang details sa checkout."],
        'pay_no' => ["%1\$s isn't available right now. We accept: %2\$s.", "Hindi available ang %1\$s sa ngayon. Tumatanggap kami ng: %2\$s.", "Hindi available ang %1\$s ngayon. We accept: %2\$s."],
        'pay_none' => ["Payment options aren't available right now. Please contact KAPIHAN staff.", "Walang available na paraan ng bayad sa ngayon. Pakikontak ang KAPIHAN staff.", "Walang available na payment options ngayon. Please contact KAPIHAN staff."],
        'how_order' => ["Ordering is easy:\n1. Tap “View Menu” and add your drinks to the cart.\n2. Open your cart, enter your name, then choose %s and a payment method.\n3. Tap Place Order — you'll get a tracking link right away.", "Madali lang mag-order:\n1. Pindutin ang “View Menu” at idagdag sa cart ang gusto mo.\n2. Buksan ang cart, ilagay ang pangalan, tapos piliin ang %s at paraan ng bayad.\n3. Pindutin ang Place Order — makakakuha ka agad ng tracking link.", "Easy lang mag-order:\n1. Tap “View Menu” at i-add sa cart ang gusto mo.\n2. Open ang cart, ilagay ang name, tapos piliin ang %s at payment method.\n3. Tap Place Order — may tracking link ka agad."],
        'types' => ["You can choose: %s.", "Maaari kang pumili ng: %s.", "Puwede kang pumili ng: %s."],
        'types_none' => ["Online ordering isn't available right now.", "Hindi available ang online ordering sa ngayon.", "Hindi available ang online ordering ngayon."],
        'closed_note' => [" Note: online ordering is currently unavailable.", " Paalala: hindi available ang online ordering sa ngayon.", " Note: hindi available ang online ordering ngayon."],
        'min_yes' => ["The minimum order is %s.", "Ang minimum na order ay %s.", "Ang minimum order ay %s."],
        'min_no' => ["There's no minimum order.", "Walang minimum na order.", "Walang minimum order."],
        'prep' => ["Orders usually take about %d minutes to prepare.", "Karaniwang tumatagal ng mga %d minuto ang paghahanda ng order.", "Usually mga %d minutes ang prep ng order."],
        'order_status' => ["Your order #ORD-%1\$d is %2\$s.", "Ang order mo na #ORD-%1\$d ay %2\$s.", "Ang order mo na #ORD-%1\$d ay %2\$s."],
        'os_pending' => ['waiting in the queue', 'nasa pila', 'nasa queue'],
        'os_brewing' => ['being prepared', 'ginagawa na', 'ginagawa na'],
        'os_completed' => ['ready for pickup — enjoy', 'handa na — enjoy', 'ready na — enjoy'],
        'os_cancelled' => ['cancelled', 'kinansela', 'cancelled'],
        'os_eta' => [" Estimated preparation: about %d minutes.", " Tinatayang paghahanda: mga %d minuto.", " Estimated prep: mga %d minutes."],
        'order_none' => ["I can't find a recent order on this device. Once you place an order, tap “Check My Order” anytime to track it. For older orders, please ask our staff.", "Wala akong makitang kamakailang order sa device na ito. Kapag nakapag-order ka na, pindutin ang “Check My Order” anumang oras para ma-track ito. Para sa mas lumang order, magtanong sa staff.", "Wala akong makitang recent order sa device na ito. Kapag nakapag-order ka na, tap “Check My Order” anytime para ma-track. Para sa older orders, ask our staff."],
        'sensitive' => ["For your safety, please don't share passwords, PINs, OTPs or card numbers here. KAPIHAN staff will never ask for them.", "Para sa iyong seguridad, huwag magbahagi dito ng password, PIN, OTP o card number. Hindi hihingin ito ng KAPIHAN staff.", "For your safety, huwag mag-share dito ng password, PIN, OTP o card number. Hindi iyan hihingin ng KAPIHAN staff."],
        'knowledge' => ["Here's what I know:\n%s", "Ito ang alam ko:\n%s", "Ito ang alam ko:\n%s"],
        'fallback' => ["I’m not sure about that yet. Please contact KAPIHAN staff for assistance.", "Hindi pa ako sigurado tungkol diyan. Pakikontak na lang ang KAPIHAN staff para sa tulong.", "Hindi pa ako sure diyan. Please contact KAPIHAN staff for assistance."],
        'fallback_contact' => [" You can reach them at %s.", " Maaabot mo sila sa %s.", " You can reach them at %s."],
    ];
}

function bot_t(string $key, array $args = []): string {
    $all = bot_strings();
    $i = (int)bot_lang_key();
    $tpl = $all[$key][$i] ?? ($all[$key][0] ?? '');
    return $args ? vsprintf($tpl, $args) : $tpl;
}

// Tono: professional = walang emoji; friendly = may kaunting ☕ sa maiikling sagot
// Totoo kung ang mensahe ay halos puro phrase lang (hal. "hi po", "salamat po") at walang ibang tanong
function bot_only_phrase(string $padded, array $phrases): bool {
    $rest = $padded;
    foreach ($phrases as $p) {
        $q = kb_norm($p);
        if ($q !== '') $rest = str_replace(' ' . $q . ' ', '  ', $rest);
    }
    $filler = ['po', 'opo', 'kapitan', 'bot', 'kapihan', 'kuya', 'ate', 'sir', 'maam', 'mam', 'pre', 'there', 'all', 'everyone', 'guys', 'kayo', 'you', 'so', 'much', 'very', 'again', 'na', 'lang', 'naman', 'din', 'ulit'];
    return count(array_diff(kb_tokens(kb_norm($rest)), $filler)) === 0;
}

function bot_tone(string $text): string {
    $tone = setting('bot_tone');
    if ($tone === 'professional') {
        $text = str_replace(['☕', '!'], ['', '.'], $text);
        return trim((string)preg_replace('/[ \t]+(?=\n|$)/', '', $text));
    }
    if ($tone === 'friendly' && strpos($text, '☕') === false && strlen($text) < 220 && strpos($text, "\n") === false) {
        return rtrim($text) . ' ☕';
    }
    return $text;
}

/* ================================================================
   PRODUCT KNOWLEDGE (galing sa database — laging kasalukuyan)
   ================================================================ */

function bot_money(float $n): string {
    return peso($n);
}

function bot_load_products(PDO $pdo): array {
    try {
        $rows = $pdo->query("SELECT id, name, category, price, description, stock_qty FROM products WHERE is_active = 1 ORDER BY name ASC LIMIT 300")->fetchAll();
    } catch (Throwable $e) {
        error_log('kapitan products: ' . $e->getMessage());
        return [];
    }
    foreach ($rows as &$r) {
        $r['price'] = (float)$r['price'];
        $r['sold_out'] = $r['stock_qty'] !== null && (int)$r['stock_qty'] <= 0;
    }
    unset($r);
    return $rows;
}

function bot_line(array $p): string {
    return '• ' . $p['name'] . ' — ' . bot_money($p['price']) . ($p['sold_out'] ? ' (' . bot_t('sold_out') . ')' : '');
}

function bot_find_products(array $products, array $tokens): array {
    $generic = bot_generic_tokens();
    $padded = ' ' . implode(' ', $tokens) . ' ';

    // 1) Binanggit ang buong pangalan ng produkto (hal. "iced latte", "americano")
    $exact = [];
    foreach ($products as $p) {
        $nn = kb_norm($p['name']);
        if ($nn !== '' && strpos($padded, ' ' . $nn . ' ') !== false) $exact[] = $p;
    }
    if ($exact) return array_slice($exact, 0, 6);

    // 2) Bahagyang pangalan (hal. "latte", "barako"): lahat ng magkakalapit ang tugma
    $scored = [];
    $best = -1000;
    foreach ($products as $p) {
        $nameTok = array_values(array_unique(kb_tokens(kb_norm($p['name']))));
        $distinct = array_values(array_diff($nameTok, $generic));
        if (!$distinct) $distinct = $nameTok;
        $matched = 0;
        foreach ($distinct as $nt) {
            foreach ($tokens as $mt) {
                if (in_array($mt, $generic, true)) continue;
                if (kb_tok_eq($mt, $nt)) { $matched++; break; }
            }
        }
        if ($matched === 0) continue;
        $score = $matched * 10 - 2 * (count($distinct) - $matched);
        foreach ($nameTok as $nt) {
            if (in_array($nt, $generic, true) && in_array($nt, $tokens, true)) $score += 3;
        }
        $scored[] = [$p, $score];
        if ($score > $best) $best = $score;
    }
    $hits = [];
    foreach ($scored as $row) { if ($row[1] >= $best - 3) $hits[] = $row[0]; }
    return array_slice($hits, 0, 6);
}

function bot_hits(string $text, array $words): int {
    $n = 0;
    foreach ($words as $w) { if (kb_has($text, [$w])) $n++; }
    return $n;
}

// tag => lakas (mas mataas = mas bagay). Galing sa kategorya, pangalan at description ng produkto sa database.
function bot_tags(array $p): array {
    $text = ' ' . kb_norm($p['name'] . ' ' . $p['description']) . ' ';
    $cat = $p['category'];
    $drink = in_array($cat, ['coffee', 'cold'], true);
    $tags = [];
    $cold = ($cat === 'cold' ? 3 : 0) + bot_hits($text, ['iced', 'cold', 'malamig', 'ice']);
    if ($cold > 0) $tags['cold'] = $cold;
    $hot = ($cat === 'coffee' ? 3 : 0) + bot_hits($text, ['hot', 'mainit']);
    if ($hot > 0 && !isset($tags['cold'])) $tags['hot'] = $hot;
    $map = [
        'sweet'     => [['caramel', 'vanilla', 'mocha', 'chocolate', 'spanish', 'condensed', 'sweet', 'matamis', 'ube', 'hazelnut', 'honey', 'syrup', 'brown sugar'], false],
        'creamy'    => [['latte', 'cappuccino', 'milk', 'cream', 'flat white', 'macchiato', 'spanish', 'gatas'], false],
        'chocolate' => [['mocha', 'chocolate', 'choco', 'cocoa', 'tsokolate'], false],
        'strong'    => [['espresso', 'barako', 'double', 'strong', 'bold', 'matapang', 'ristretto', 'doppio'], true],
        'bitter'    => [['americano', 'black', 'barako', 'espresso', 'brewed', 'drip', 'bitter', 'mapait'], true],
        'classic'   => [['americano', 'brewed', 'barako', 'espresso', 'drip', 'classic', 'regular'], true],
    ];
    foreach ($map as $tag => $def) {
        if ($def[1] && !$drink) continue;          // strong/bitter/classic ay para lang sa kape
        $h = bot_hits($text, $def[0]);
        if ($h > 0) $tags[$tag] = $h;
    }
    if ($cat === 'special') $tags['noncoffee'] = 3;
    if ($cat === 'merienda') $tags['snack'] = 3;
    return $tags;
}

function bot_taste_from(string $padded): array {
    $found = [];
    foreach (kb_taste_words() as $tag => $words) {
        if (kb_has($padded, $words)) $found[] = $tag;
    }
    return $found;
}

function bot_recommend(array $products, array $wanted): array {
    $avail = array_values(array_filter($products, fn($p) => !$p['sold_out']));
    if (!$avail) return [];
    $prices = array_map(fn($p) => $p['price'], $avail);
    sort($prices);
    $median = $prices[(int)floor(count($prices) / 2)];
    $default = !$wanted;
    if ($default) $wanted = ['classic'];
    $scored = [];
    foreach ($avail as $p) {
        $tags = bot_tags($p);
        $score = 0;
        foreach ($wanted as $w) {
            if ($w === 'cheap') { $score += ($p['price'] <= $median) ? 3 : 0; continue; }
            $score += min(4, $tags[$w] ?? 0);
        }
        // magkasalungat na kahilingan
        if (in_array('cold', $wanted, true) && !isset($tags['cold'])) $score -= 6;
        if (in_array('hot', $wanted, true) && isset($tags['cold'])) $score -= 6;
        if (in_array('noncoffee', $wanted, true) && !isset($tags['noncoffee'])) $score -= 6;
        if (in_array('snack', $wanted, true) && !isset($tags['snack'])) $score -= 6;
        if (!in_array('snack', $wanted, true) && isset($tags['snack'])) $score -= 3;   // inumin muna maliban kung hiniling
        if ($default) {
            if (($p['category'] ?? '') === 'coffee') $score += 1;                       // mainit na kape muna kung walang hiling
            if (kb_has(' ' . kb_norm($p['name']) . ' ', ['americano'])) $score += 2;    // klasikong sanggunian ng café
        }
        if (in_array('cheap', $wanted, true)) $score -= $p['price'] / 1000;
        $scored[] = ['p' => $p, 's' => $score];
    }
    usort($scored, function ($a, $b) {
        if ($a['s'] == $b['s']) return $a['p']['price'] <=> $b['p']['price'];
        return $b['s'] <=> $a['s'];
    });
    if ($scored[0]['s'] <= 0) return [];
    $out = [];
    foreach ($scored as $row) {
        if ($row['s'] < $scored[0]['s'] - 2 || count($out) >= 3) break;
        $out[] = $row['p'];
    }
    return $out;
}

/* ================================================================
   MAIN REPLY ENGINE
   ================================================================ */

function bot_answer_faq(array $faqs, array $msgKeys, string $padded, float $minScore): ?string {
    $best = null; $bestScore = 0.0;
    foreach ($faqs as $f) {
        $score = 0.0;
        if (trim((string)$f['keywords']) !== '') {
            foreach (explode(',', (string)$f['keywords']) as $kw) {
                $q = kb_norm($kw);
                if ($q !== '' && strpos($padded, ' ' . $q . ' ') !== false) { $score = max($score, 1.5); }
            }
        }
        $qn = kb_norm((string)$f['question']);
        if ($qn !== '' && strpos($padded, ' ' . $qn . ' ') !== false) $score = max($score, 2.0);
        [$ov, $common] = kb_overlap($msgKeys, kb_keywords((string)$f['question'] . ' ' . (string)$f['keywords']));
        if ($common >= 1) $score = max($score, $ov);
        if ($score > $bestScore) { $bestScore = $score; $best = $f; }
    }
    return ($best && $bestScore >= $minScore) ? (string)$best['answer'] : null;
}

function bot_answer_knowledge(string $knowledge, array $msgKeys): ?string {
    if (!$msgKeys || trim($knowledge) === '') return null;
    $lines = array_slice(array_values(array_filter(array_map('trim', preg_split('/\R/', $knowledge)), fn($l) => strlen($l) >= 3)), 0, 60);
    $best = null; $bestCov = 0.0; $bestCommon = 0;
    foreach ($lines as $line) {
        $lineKeys = kb_keywords($line);
        $common = 0; $maxLen = 0;
        foreach ($msgKeys as $m) {
            foreach ($lineKeys as $t) {
                if (kb_tok_eq($m, $t)) { $common++; $maxLen = max($maxLen, strlen($m)); break; }
            }
        }
        $cov = $common / count($msgKeys);
        if ($common >= 1 && ($cov > $bestCov || ($cov == $bestCov && $common > $bestCommon))) {
            if ($cov >= 0.6 && ($common >= 2 || $maxLen >= 5)) { $best = $line; $bestCov = $cov; $bestCommon = $common; }
        }
    }
    return $best;
}

// Nagbabalik ng ['text' => string, 'ctx' => array, 'link' => ?array]
function bot_reply(PDO $pdo, string $message, array $ctx, ?array $order): array {
    $n = kb_norm($message);
    $padded = ' ' . $n . ' ';
    $tokens = kb_tokens($n);
    $msgKeys = kb_keywords($message);
    $I = kb_intents();
    $store = setting('store_name');
    $botName = setting('bot_name') !== '' ? setting('bot_name') : 'KAPitan Bot';
    $res = ['text' => '', 'ctx' => $ctx, 'link' => null, 'raw' => false];
    $done = function (string $text, ?array $newCtx = null) use (&$res, $ctx) {
        $res['text'] = $text;
        $res['ctx'] = $newCtx === null ? $ctx : $newCtx;
        return $res;
    };

    if ($n === '') return $done(bot_t('fallback'));

    if (bot_is_sensitive($message)) return $done(bot_t('sensitive'));

    $products = bot_load_products($pdo);
    $faqs = [];
    try {
        $faqs = $pdo->query("SELECT question, keywords, answer FROM kapitan_faqs WHERE is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 100")->fetchAll();
    } catch (Throwable $e) { error_log('kapitan faqs: ' . $e->getMessage()); }

    // 0) FAQ na malakas ang tugma (isinulat ng admin) — nauuna para masunod ang gusto ng admin
    $faqStrong = bot_answer_faq($faqs, $msgKeys, $padded, 1.0);
    if ($faqStrong !== null) { $res['raw'] = true; return $done($faqStrong); }

    // Bati/salamat/paalam: sasagutin agad kung iyon lang ang sinabi; kung may kasamang tanong, ituloy ang tanong
    $hadGreeting = kb_has($padded, $I['greet']);
    $hadThanks = kb_has($padded, $I['thanks']);
    $hadBye = kb_has($padded, $I['bye']);
    if ($hadGreeting && bot_only_phrase($padded, $I['greet'])) return $done(bot_t('hello', [$botName, $store]));
    if ($hadThanks && bot_only_phrase($padded, $I['thanks'])) return $done(bot_t('thanks'));
    if ($hadBye && bot_only_phrase($padded, $I['bye'])) return $done(bot_t('bye', [$store]));
    if (kb_has($padded, $I['about'])) return $done(bot_t('about', [$botName, $store]));
    if (kb_has($padded, $I['help']) && count($tokens) <= 3) return $done(bot_t('help'));

    // 1) Order tracking — gamit ang token ng customer, hindi nakikita ang order ng ibang tao
    $wantsOrder = kb_has($padded, $I['order_word']) && kb_has($padded, $I['order_status']);
    if ($wantsOrder) {
        if ($order) {
            $st = $order['status'];
            $word = bot_t('os_' . ($st === 'completed' ? 'completed' : ($st === 'brewing' ? 'brewing' : ($st === 'cancelled' ? 'cancelled' : 'pending'))));
            $text = bot_t('order_status', [(int)$order['id'], $word]);
            if ($st === 'pending' || $st === 'brewing') $text .= bot_t('os_eta', [(int)setting('prep_time')]);
            $res['link'] = ['label' => 'Track order #ORD-' . (int)$order['id'], 'url' => 'track.php?order_id=' . (int)$order['id'] . '&t=' . rawurlencode((string)$order['token'])];
            return $done($text);
        }
        return $done(bot_t('order_none'));
    }

    // 2) Bayad
    if (kb_has($padded, $I['payment'])) {
        $enabled = enabled_payments();
        $asked = null;
        foreach (['gcash' => ['gcash', 'g cash'], 'maya' => ['maya', 'paymaya'], 'cash' => ['cash']] as $key => $words) {
            if (kb_has($padded, $words)) { $asked = $key; break; }
        }
        if (!$enabled) return $done(bot_t('pay_none'));
        $list = implode(', ', array_values($enabled));
        if ($asked !== null && isset($enabled[$asked])) return $done(bot_t('pay_yes', [$enabled[$asked]]));
        if ($asked !== null) return $done(bot_t('pay_no', [['gcash' => 'GCash', 'maya' => 'Maya', 'cash' => 'Cash'][$asked], $list]));
        $text = bot_t('pay_all', [$list]);
        $wallets = array_values(array_filter([$enabled['gcash'] ?? null, $enabled['maya'] ?? null]));
        if ($wallets) $text .= bot_t('pay_ewallet', [implode(' and ', $wallets)]);
        if ($wallets && setting('pay_instructions') !== '') $text .= "\n" . setting('pay_instructions');
        return $done($text);
    }

    // 3) Oras, lokasyon, contact
    if (kb_has($padded, $I['hours'])) {
        $status = bot_t(setting('store_status') === 'open' ? 'st_open' : 'st_closed');
        return $done(bot_t('hours', [store_open_label(), $status]));
    }
    if (kb_has($padded, $I['location'])) {
        return $done(setting('address') !== '' ? bot_t('location', [setting('address')]) : bot_t('no_location'));
    }
    if (kb_has($padded, $I['contact'])) {
        $parts = array_filter([setting('contact_number'), setting('email')], fn($v) => $v !== '');
        return $done($parts ? bot_t('contact', [implode(' / ', $parts)]) : bot_t('no_contact'));
    }

    // 4) Paano mag-order, order types, minimum, prep time
    if (kb_has($padded, $I['how_order'])) {
        $types = enabled_order_types();
        $text = bot_t('how_order', [$types ? implode(' / ', array_values($types)) : '—']);
        [$open] = ordering_state();
        if (!$open) $text .= bot_t('closed_note');
        return $done($text);
    }
    if (kb_has($padded, $I['order_types'])) {
        $types = enabled_order_types();
        return $done($types ? bot_t('types', [implode(', ', array_values($types))]) : bot_t('types_none'));
    }
    if (kb_has($padded, $I['min_order'])) {
        $min = (float)setting('min_order_amount');
        return $done($min > 0 ? bot_t('min_yes', [bot_money($min)]) : bot_t('min_no'));
    }
    if (kb_has($padded, $I['prep'])) return $done(bot_t('prep', [(int)setting('prep_time')]));

    // 5) Menu, presyo, availability, rekomendasyon
    $named = bot_find_products($products, $tokens);
    $wantsPrice = kb_has($padded, $I['price']);
    $wantsAvail = kb_has($padded, $I['avail']);
    $wantsRec = kb_has($padded, $I['recommend']);
    $taste = bot_taste_from($padded);
    $wantsWord = kb_has($padded, $I['want']);
    $bothTemp = in_array('hot', $taste, true) && in_array('cold', $taste, true);   // "hot or cold?" = wala pang pinipili
    if ($bothTemp) $taste = array_values(array_diff($taste, ['hot', 'cold']));

    if ($named && !$wantsRec) {
        $ids = array_map(fn($p) => (int)$p['id'], $named);
        $newCtx = ['products' => array_map(fn($p) => $p['id'], $named)];
        if (count($named) === 1) {
            $p = $named[0];
            if ($p['sold_out']) return $done(bot_t('avail_no', [$p['name']]), $newCtx);
            $text = $wantsPrice ? bot_t('price_one', [$p['name'], bot_money($p['price'])]) : bot_t('avail_yes', [$p['name'], bot_money($p['price'])]);
            $desc = trim((string)$p['description']);
            if ($desc !== '' && !$wantsPrice) $text .= ' ' . mb_substr($desc, 0, 120);
            return $done($text, $newCtx);
        }
        return $done(bot_t('price_many', [implode("\n", array_map('bot_line', $named))]), $newCtx);
    }

    $catHit = $bothTemp ? null : bot_pick_category($padded);

    // Admin-defined na FAQ at Store Knowledge: mas tiyak kaysa sa pangkalahatang listahan ng menu
    if (!$wantsRec) {
        $faqSoft = bot_answer_faq($faqs, $msgKeys, $padded, 0.5);
        if ($faqSoft !== null) { $res['raw'] = true; return $done($faqSoft); }
        $know = bot_answer_knowledge((string)setting('bot_knowledge'), $msgKeys);
        if ($know !== null) { $res['raw'] = true; return $done(bot_t('knowledge', [$know])); }
    }

    // "Magkano?" na walang binanggit na produkto — gamitin ang huling pinag-usapan
    if ($wantsPrice && !empty($ctx['products'])) {
        $byId = [];
        foreach ($products as $p) $byId[(string)$p['id']] = $p;
        $prev = [];
        foreach ($ctx['products'] as $pid) { if (isset($byId[(string)$pid])) $prev[] = $byId[(string)$pid]; }
        if (count($prev) === 1) return $done(bot_t('price_one', [$prev[0]['name'], bot_money($prev[0]['price'])]));
        if ($prev) return $done(bot_t('price_many', [implode("\n", array_map('bot_line', $prev))]));
    }
    if ($wantsPrice && !$wantsRec && $catHit === null) return $done(bot_t('which_item'));

    // Rekomendasyon: malinaw na hiniling, o "gusto ko" + panlasa, o maikling mensahe na panlasa lang ("something sweet")
    if ($wantsRec || $bothTemp || ($wantsWord && $taste) || ($taste && $catHit === null && count($tokens) <= 6)) {
        if (!$products) return $done(bot_t('menu_empty'));
        $picks = bot_recommend($products, $taste);
        if (!$picks) return $done(bot_t('rec_none'));
        $mainTaste = $taste ? $taste[0] : 'classic';
        $tasteText = bot_t('t_' . $mainTaste);
        $text = bot_t('rec', [$tasteText, $picks[0]['name'], bot_money($picks[0]['price'])]);
        if (count($picks) > 1) $text .= "\n" . bot_t('rec_more', [implode("\n", array_map('bot_line', array_slice($picks, 1)))]);
        if (!$taste) $text .= "\n" . bot_t('rec_ask');
        return $done($text, ['products' => [$picks[0]['id']]]);
    }

    // Kategorya (hal. "iced drinks", "may merienda ba?")
    if ($catHit !== null) {
        $items = array_values(array_filter($products, fn($p) => $p['category'] === $catHit));
        if (!$items) return $done(bot_t('cat_empty'));
        $items = array_slice($items, 0, 10);
        return $done(bot_t('cat', [kb_cat_labels()[$catHit], implode("\n", array_map('bot_line', $items))]), []);
    }

    if (kb_has($padded, $I['menu'])) {
        if (!$products) return $done(bot_t('menu_empty'));
        $counts = [];
        foreach ($products as $p) $counts[$p['category']] = ($counts[$p['category']] ?? 0) + 1;
        $lines = [];
        foreach (kb_cat_labels() as $cat => $label) { if (!empty($counts[$cat])) $lines[] = '• ' . $label . ' (' . $counts[$cat] . ')'; }
        foreach ($counts as $cat => $c) { if (!isset(kb_cat_labels()[$cat])) $lines[] = '• ' . ucfirst((string)$cat) . ' (' . $c . ')'; }
        return $done(bot_t('menu', [implode("\n", $lines)]));
    }

    // 7) Pagtanong tungkol sa availability na walang malinaw na produkto
    if ($wantsAvail && !$products) return $done(bot_t('menu_empty'));

    if ($hadThanks) return $done(bot_t('thanks'));
    if ($hadGreeting) return $done(bot_t('hello', [$botName, $store]));
    if ($hadBye) return $done(bot_t('bye', [$store]));

    // 8) Ligtas na fallback — hindi nag-iimbento
    $text = bot_t('fallback');
    $contact = setting('contact_number');
    if ($contact !== '') $text .= bot_t('fallback_contact', [$contact]);
    return $done($text);
}

/* ================================================================
   ENDPOINT
   ================================================================ */

function bot_fail(string $msg, int $http = 200) {
    http_response_code($http);
    echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') bot_fail('Invalid request.', 405);
if (maintenance_active($pdo)) bot_fail('The shop is under maintenance. Please check back soon.', 503);
if (!setting_on('bot_enabled')) bot_fail('This chat is not available right now.', 503);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) bot_fail('Invalid request.', 400);

app_session_start();
if (empty($_SESSION['kapitan_sid']) || !preg_match('/^[a-f0-9]{32}$/', (string)$_SESSION['kapitan_sid'])) {
    $_SESSION['kapitan_sid'] = bin2hex(random_bytes(16));
}
$sid = (string)$_SESSION['kapitan_sid'];
$action = (string)($input['action'] ?? 'send');

// Ang kasalukuyang usapan ng session na ito (walang ibang session ang makakakita nito)
function bot_current_conversation(PDO $pdo, string $sid, bool $create): ?array {
    $s = $pdo->prepare("SELECT id, context, TIMESTAMPDIFF(SECOND, updated_at, NOW()) AS age,
                               (SELECT COUNT(*) FROM kapitan_messages m WHERE m.conversation_id = kapitan_conversations.id) AS msgs
                        FROM kapitan_conversations WHERE session_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
    $s->execute([$sid]);
    $row = $s->fetch();
    if ($row && (int)$row['age'] < 6 * 3600 && (int)$row['msgs'] < 200) return $row;
    if ($row) $pdo->prepare("UPDATE kapitan_conversations SET status = 'closed' WHERE id = ?")->execute([$row['id']]);
    if (!$create) return null;
    $pdo->prepare("INSERT INTO kapitan_conversations (session_id) VALUES (?)")->execute([$sid]);
    return ['id' => (int)$pdo->lastInsertId(), 'context' => null, 'age' => 0, 'msgs' => 0];
}

// Paminsan-minsan lang: linisin ang luma at idle na usapan
if (mt_rand(1, 40) === 1) kapitan_purge($pdo);

try {
    if ($action === 'history') {
        $conv = bot_current_conversation($pdo, $sid, false);
        $out = [];
        if ($conv) {
            $m = $pdo->prepare("SELECT sender, message FROM kapitan_messages WHERE conversation_id = ? ORDER BY id DESC LIMIT 40");
            $m->execute([$conv['id']]);
            $out = array_reverse($m->fetchAll());
        }
        echo json_encode(['success' => true, 'messages' => $out], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action !== 'send') bot_fail('Invalid request.', 400);

    $message = trim((string)($input['message'] ?? ''));
    $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message) ?? '';
    $message = mb_substr($message, 0, 500);
    if ($message === '') bot_fail('Please type a message first.');

    // Rate limit bawat session: 1.5 segundo sa pagitan at 60 mensahe kada oras
    $now = time();
    $hits = array_values(array_filter($_SESSION['kapitan_hits'] ?? [], fn($t) => $now - $t < 3600));
    if (count($hits) >= 60) bot_fail('You have sent a lot of messages! Please try again a little later.', 429);
    if ($hits && $now - end($hits) < 1.5) bot_fail('One moment, please…', 429);
    $hits[] = $now;
    $_SESSION['kapitan_hits'] = $hits;

    // Order ng customer (kung may token na tama lang)
    $order = null;
    if (is_array($input['order'] ?? null)) {
        $oid = (int)($input['order']['id'] ?? 0);
        $tok = (string)($input['order']['token'] ?? '');
        if ($oid > 0 && $tok !== '') {
            $o = $pdo->prepare("SELECT id, status, public_token FROM orders WHERE id = ?");
            $o->execute([$oid]);
            $row = $o->fetch();
            if ($row && is_string($row['public_token']) && $row['public_token'] !== '' && hash_equals($row['public_token'], $tok)) {
                $order = ['id' => (int)$row['id'], 'status' => (string)$row['status'], 'token' => $tok];
            }
        }
    }

    $conv = bot_current_conversation($pdo, $sid, true);
    $ctx = [];
    if (!empty($conv['context'])) { $d = json_decode((string)$conv['context'], true); if (is_array($d)) $ctx = $d; }

    $sensitive = bot_is_sensitive($message);
    $stored = $sensitive ? '[Message removed for privacy]' : bot_mask($message);
    $ins = $pdo->prepare("INSERT INTO kapitan_messages (conversation_id, sender, message) VALUES (?, ?, ?)");
    $ins->execute([$conv['id'], 'customer', $stored]);

    $r = bot_reply($pdo, $message, $ctx, $order);
    $text = !empty($r['raw']) ? $r['text'] : bot_tone($r['text']);

    $ins->execute([$conv['id'], 'bot', mb_substr($text, 0, 2000)]);
    $pdo->prepare("UPDATE kapitan_conversations SET context = ?, updated_at = NOW() WHERE id = ?")
        ->execute([json_encode($r['ctx']), $conv['id']]);

    echo json_encode(['success' => true, 'reply' => $text, 'link' => $r['link']], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('kapitan_chat: ' . $e->getMessage());
    bot_fail("Sorry, I can't answer right now. Please try again in a moment.", 200);
}
