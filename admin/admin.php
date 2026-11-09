<?php
require_once '../connection/db.php';
app_session_start();

// --- AUTH GUARD: kailangan naka-login bago makapasok dito ---
if (!admin_session_valid($pdo)) {
    header('Location: admin_login.php');
    exit();
}

// --- CSRF: lahat ng POST ay kailangan may tamang token ---
csrf_check();

// Handle Tab Switching
$tab = $_GET['tab'] ?? 'products';

// --- HELPER: ligtas na pag-upload ng product image (JPG/JPEG/PNG/WEBP, hanggang 4MB, random na filename) ---
function handleProductImageUpload(): ?string {
    return save_uploaded_image('image_upload', 'products', 4 * 1024 * 1024);
}

// --- ACTION HANDLERS ---

// 1. Seed Default 24 Products
if (isset($_POST['action']) && $_POST['action'] === 'seed_defaults') {
    $defaults = [
        ['barako', 'Brewed Barako', 'coffee', 90.00, 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?q=80&w=400&auto=format&fit=crop', 'Batangas dark roast, strong & bold.'],
        ['espresso', 'Espresso Shot', 'coffee', 95.00, 'https://images.unsplash.com/photo-1510591509098-f4fdc6d0ff04?q=80&w=400&auto=format&fit=crop', 'Two shots, rich local blend.'],
        ['americano', 'Americano', 'coffee', 110.00, 'https://images.unsplash.com/photo-1551030173-122aabc4489c?q=80&w=400&auto=format&fit=crop', 'Espresso stretched with hot water.'],
        ['latte', 'Caffè Latte', 'coffee', 135.00, 'https://images.unsplash.com/photo-1534778101976-62847782c213?q=80&w=400&auto=format&fit=crop', 'Espresso with silky steamed milk.'],
        ['cappuccino', 'Cappuccino', 'coffee', 135.00, 'https://images.unsplash.com/photo-1572442388796-11668a67e53d?q=80&w=400&auto=format&fit=crop', 'Equal parts espresso, milk, and foam.'],
        ['cortado', 'Cortado', 'coffee', 125.00, 'https://images.unsplash.com/photo-1514432324607-a09d9b4aefdd?q=80&w=400&auto=format&fit=crop', 'Espresso cut with warm milk.'],
        ['spanishlatte', 'Spanish Latte', 'coffee', 145.00, 'https://images.unsplash.com/photo-1541167760496-1628856ab772?q=80&w=400&auto=format&fit=crop', 'Espresso with steamed milk & condensed milk.'],
        ['mocha', 'Caffè Mocha', 'coffee', 150.00, 'https://images.unsplash.com/photo-1578314675249-a6910f80cc4e?q=80&w=400&auto=format&fit=crop', 'Espresso with local tablea chocolate & milk.'],
        ['icedbarako', 'Iced Barako', 'cold', 105.00, 'https://images.unsplash.com/photo-1517701604599-bb29b565090c?q=80&w=400&auto=format&fit=crop', 'Slow-dripped Barako served over ice.'],
        ['icedlatte', 'Iced Latte', 'cold', 145.00, 'https://images.unsplash.com/photo-1461023058943-07fcbe16d735?q=80&w=400&auto=format&fit=crop', 'Fresh espresso & fresh milk on ice.'],
        ['icedspanish', 'Iced Spanish Latte', 'cold', 155.00, 'https://images.unsplash.com/photo-1517701604599-bb29b565090c?q=80&w=400&auto=format&fit=crop', 'Sweetened espresso over iced milk.'],
        ['icedcaramel', 'Iced Caramel Macchiato', 'cold', 160.00, 'https://images.unsplash.com/photo-1485808191679-5f86510681a2?q=80&w=400&auto=format&fit=crop', 'Espresso, vanilla milk & caramel drizzle.'],
        ['coldbrew', '12hr Cold Brew', 'cold', 150.00, 'https://images.unsplash.com/photo-1517701604599-bb29b565090c?q=80&w=400&auto=format&fit=crop', 'Steeped overnight, smooth & low acidity.'],
        ['vanillacoldbrew', 'Vanilla Cold Foam Brew', 'cold', 165.00, 'https://images.unsplash.com/photo-1517701604599-bb29b565090c?q=80&w=400&auto=format&fit=crop', 'Cold brew topped with sweet vanilla cream.'],
        ['salabat', 'Ginger Salabat', 'special', 85.00, 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?q=80&w=400&auto=format&fit=crop', 'Traditional hot ginger tea with honey.'],
        ['tsokolate', 'Tsokolate Ah', 'special', 120.00, 'https://images.unsplash.com/photo-1542990253-0d0f5be5f0ed?q=80&w=400&auto=format&fit=crop', 'Rich local Tablea cacao drink.'],
        ['icedmatcha', 'Iced Matcha Latte', 'special', 150.00, 'https://images.unsplash.com/photo-1536256263959-770b48d82b0a?q=80&w=400&auto=format&fit=crop', 'Uji matcha whisked with fresh milk.'],
        ['calamansitea', 'Iced Calamansi Tea', 'special', 95.00, 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?q=80&w=400&auto=format&fit=crop', 'Brewed black tea infused with real calamansi.'],
        ['pandesal', 'Pan de Sal Set', 'merienda', 75.00, 'https://images.unsplash.com/photo-1589367920969-ab8e050bbb04?q=80&w=400&auto=format&fit=crop', 'Served fresh and warm with butter.'],
        ['putobumbong', 'Puto Bumbong', 'merienda', 110.00, 'https://images.unsplash.com/photo-1626132647523-66f5bf380027?q=80&w=400&auto=format&fit=crop', 'Steamed purple rice with muscovado & coconut.'],
        ['bibingka', 'Special Bibingka', 'merienda', 130.00, 'https://images.unsplash.com/photo-1608198093002-ad4e005484ec?q=80&w=400&auto=format&fit=crop', 'Baked rice cake with salted egg & cheese.'],
        ['turon', 'Turon Bites (3pcs)', 'merienda', 85.00, 'https://images.unsplash.com/photo-1589367920969-ab8e050bbb04?q=80&w=400&auto=format&fit=crop', 'Crispy banana rolls drizzled with caramel.'],
        ['ensaymada', 'Cheese Ensaymada', 'merienda', 95.00, 'https://images.unsplash.com/photo-1509440159596-0249088772ff?q=80&w=400&auto=format&fit=crop', 'Soft buttery pastry topped with grated cheese.'],
        ['cassavacake', 'Cassava Cake', 'merienda', 100.00, 'https://images.unsplash.com/photo-1608198093002-ad4e005484ec?q=80&w=400&auto=format&fit=crop', 'Traditional baked cassava with custard topping.']
    ];

    $stmt = $pdo->prepare("INSERT INTO products (id, name, category, price, image, description) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), category=VALUES(category), price=VALUES(price), image=VALUES(image), description=VALUES(description)");
    foreach ($defaults as $p) {
        $stmt->execute($p);
    }
    header("Location: admin.php?tab=products&msg=seeded");
    exit();
}

// 2. Add New Product
if (isset($_POST['action']) && $_POST['action'] === 'add_product') {
    $id = preg_replace('/[^a-z0-9]/', '', strtolower((string)($_POST['name'] ?? ''))) . bin2hex(random_bytes(3));
    $name = trim((string)($_POST['name'] ?? ''));
    $category = in_array($_POST['category'] ?? '', ['coffee','cold','special','merienda'], true) ? $_POST['category'] : 'coffee';
    $price = $_POST['price'] ?? '';
    $description = (string)($_POST['description'] ?? '');
    $stockRaw = trim((string)($_POST['stock_qty'] ?? ''));
    $stock = $stockRaw === '' ? null : max(0, intval($stockRaw));
    $active = !empty($_POST['is_active']) ? 1 : 0;
    $uploaded = null;

    try {
        if ($name === '' || !is_numeric($price) || $price < 0 || $price > 100000) throw new Exception('Please enter a product name and a valid price.');
        $uploaded = handleProductImageUpload();
        $image = $uploaded ?? (product_clean_url($_POST['image'] ?? '') ?? '');   // walang larawan = placeholder

        $stmt = $pdo->prepare("INSERT INTO products (id, name, category, price, image, description, stock_qty, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id, $name, $category, $price, $image, $description, $stock, $active]);
        header("Location: admin.php?tab=products&msg=added");
    } catch (Throwable $e) {
        if ($uploaded) delete_uploaded_image($uploaded);   // huwag mag-iwan ng ulilang file
        error_log('add_product: ' . $e->getMessage());
        $safe = ($e instanceof PDOException) ? 'The product could not be saved. Please try again.' : $e->getMessage();
        header("Location: admin.php?tab=products&msg=upload_error&err=" . urlencode($safe));
    }
    exit();
}

// 3. Edit Product — ang kasalukuyang larawan ay galing sa database, hindi sa browser, kaya hindi ito nabubura nang hindi sinasadya
if (isset($_POST['action']) && $_POST['action'] === 'edit_product') {
    $id = (string)($_POST['id'] ?? '');
    $name = trim((string)($_POST['name'] ?? ''));
    $category = in_array($_POST['category'] ?? '', ['coffee','cold','special','merienda'], true) ? $_POST['category'] : 'coffee';
    $price = $_POST['price'] ?? '';
    $description = (string)($_POST['description'] ?? '');
    $stockRaw = trim((string)($_POST['stock_qty'] ?? ''));
    $stock = $stockRaw === '' ? null : max(0, intval($stockRaw));
    $active = !empty($_POST['is_active']) ? 1 : 0;
    $uploaded = null;

    try {
        if ($name === '' || !is_numeric($price) || $price < 0 || $price > 100000) throw new Exception('Please enter a product name and a valid price.');
        $cur = $pdo->prepare("SELECT image FROM products WHERE id = ?");
        $cur->execute([$id]);
        $row = $cur->fetch();
        if (!$row) throw new Exception('That product no longer exists.');
        $current = (string)$row['image'];

        $uploaded = handleProductImageUpload();
        $url = product_clean_url($_POST['image'] ?? '');
        if ($uploaded !== null) $image = $uploaded;                              
        elseif ($url !== null && $url !== $current) $image = $url;               
        elseif (!empty($_POST['remove_image'])) $image = '';                     
        else $image = $current;                                                 

        $stmt = $pdo->prepare("UPDATE products SET name = ?, category = ?, price = ?, image = ?, description = ?, stock_qty = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$name, $category, $price, $image, $description, $stock, $active, $id]);
        if ($image !== $current) delete_uploaded_image($current);               
        header("Location: admin.php?tab=products&msg=updated");
    } catch (Throwable $e) {
        if ($uploaded) delete_uploaded_image($uploaded);
        error_log('edit_product: ' . $e->getMessage());
        $safe = ($e instanceof PDOException) ? 'The product could not be saved. Please try again.' : $e->getMessage();
        header("Location: admin.php?tab=products&msg=upload_error&err=" . urlencode($safe));
    }
    exit();
}

// 4. Delete Product
if (isset($_POST['action']) && $_POST['action'] === 'delete_product') {
    $id = (string)($_POST['id'] ?? '');
    try {
        $cur = $pdo->prepare("SELECT image FROM products WHERE id = ?");
        $cur->execute([$id]);
        $old = (string)$cur->fetchColumn();
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
        delete_uploaded_image($old);
        header("Location: admin.php?tab=products&msg=deleted");
    } catch (Throwable $e) {
        error_log('delete_product: ' . $e->getMessage());
        header("Location: admin.php?tab=products&msg=upload_error&err=" . urlencode('The product could not be deleted. Please try again.'));
    }
    exit();
}

// 5. Update Order Status
if (isset($_POST['action']) && $_POST['action'] === 'update_order_status') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $status = strtolower((string)($_POST['status'] ?? ''));
    try {
        if (!in_array($status, ['pending', 'brewing', 'completed', 'cancelled'], true)) {
            throw new Exception('Invalid status');
        }
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$status, $order_id]);
        header("Location: admin.php?tab=orders&msg=status_updated");
    } catch (Exception $e) {
        header("Location: admin.php?tab=orders&msg=status_error");
    }
    exit();
}

// 5a. Payment status: nananatiling "pending" ang GCash/Maya hanggang i-verify ng admin
if (isset($_POST['action']) && $_POST['action'] === 'set_payment_status') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $ps = (($_POST['payment_status'] ?? '') === 'paid') ? 'paid' : 'pending';
    try {
        $pdo->prepare("UPDATE orders SET payment_status = ?, payment_verified_at = " . ($ps === 'paid' ? 'NOW()' : 'NULL') . " WHERE id = ?")->execute([$ps, $order_id]);
        header("Location: admin.php?tab=orders&msg=payment_updated");
    } catch (Throwable $e) {
        error_log('set_payment_status: ' . $e->getMessage());
        header("Location: admin.php?tab=orders&msg=status_error");
    }
    exit();
}

// 5b. KAPitan Bot conversations: burahin ang isa, o linisin ang luma
if (isset($_POST['action']) && $_POST['action'] === 'kapitan_delete') {
    $cid = (int)($_POST['id'] ?? 0);
    try {
        $pdo->prepare("DELETE FROM kapitan_conversations WHERE id = ?")->execute([$cid]);
        $_SESSION['admin_flash'] = ['type' => 'success', 'title' => 'Deleted', 'message' => 'Conversation #' . $cid . ' was deleted.'];
    } catch (Throwable $e) {
        error_log('kapitan_delete: ' . $e->getMessage());
        $_SESSION['admin_flash'] = ['type' => 'error', 'title' => 'Couldn’t delete', 'message' => 'Unable to delete the conversation. Please try again.'];
    }
    header('Location: admin.php?tab=kapitan');
    exit();
}
if (isset($_POST['action']) && $_POST['action'] === 'kapitan_clear_old') {
    $days = (int)($_POST['days'] ?? 0);
    if (!in_array($days, [7, 30, 90, 180], true)) $days = 30;
    try {
        $del = $pdo->prepare("DELETE FROM kapitan_conversations WHERE updated_at < (NOW() - INTERVAL ? DAY)");
        $del->execute([$days]);
        $_SESSION['admin_flash'] = ['type' => 'success', 'title' => 'Cleared', 'message' => $del->rowCount() . ' conversation(s) older than ' . $days . ' days were deleted.'];
    } catch (Throwable $e) {
        error_log('kapitan_clear_old: ' . $e->getMessage());
        $_SESSION['admin_flash'] = ['type' => 'error', 'title' => 'Couldn’t clear', 'message' => 'Unable to clear old conversations. Please try again.'];
    }
    header('Location: admin.php?tab=kapitan');
    exit();
}

// 6. Settings Center — isang handler para sa lahat ng seksyon (AJAX o normal POST)
$settingsError = null;
$settingsSuccess = null;

function settings_clean(string $key, int $max): string {
    $v = trim((string)($_POST[$key] ?? ''));
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    return mb_substr($v, 0, $max);
}
function settings_flag(string $key): string { return !empty($_POST[$key]) ? '1' : '0'; }

// Ina-apply ang upload/remove ng isang larawan. Nagbabalik ng lumang path na buburahin pagkatapos ma-save.
function settings_image(string $settingKey, string $fileField, string $removeField, array &$save, array &$errors): ?string {
    $old = setting($settingKey);
    try {
        $new = save_uploaded_image($fileField);
    } catch (RuntimeException $e) {
        $errors[$fileField] = $e->getMessage();
        return null;
    }
    if ($new !== null) { $save[$settingKey] = $new; return $old; }
    if (!empty($_POST[$removeField]) && $old !== '') { $save[$settingKey] = ''; return $old; }
    return null;
}

function handle_settings_save(PDO $pdo, string $section): array {
    $errors = [];
    $save = [];
    $delete = [];
    $data = [];
    $msg = 'Settings updated successfully.';

    switch ($section) {
        case 'store':
            $name = settings_clean('store_name', 60);
            $contact = settings_clean('contact_number', 30);
            $email = settings_clean('email', 120);
            $open = settings_clean('opening_time', 5);
            $close = settings_clean('closing_time', 5);
            $status = ($_POST['store_status'] ?? '') === 'closed' ? 'closed' : 'open';
            if ($name === '') $errors['store_name'] = 'Store name is required.';
            if ($contact !== '' && !preg_match('/^[0-9+()\-\s]{3,30}$/', $contact)) $errors['contact_number'] = 'Use numbers, spaces, + ( ) or - only.';
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
            $timeRe = '/^([01]\d|2[0-3]):[0-5]\d$/';
            if (!preg_match($timeRe, $open)) $errors['opening_time'] = 'Choose an opening time.';
            if (!preg_match($timeRe, $close)) $errors['closing_time'] = 'Choose a closing time.';
            if (!$errors && $open === $close) $errors['closing_time'] = 'Closing time must be different from opening time.';
            $old = settings_image('store_logo', 'store_logo_file', 'remove_store_logo', $save, $errors);
            if ($old) $delete[] = $old;
            $save += [
                'store_name' => $name, 'tagline' => settings_clean('tagline', 120), 'store_description' => str_replace("\r\n", "\n", settings_clean('store_description', 300)), 'address' => settings_clean('address', 200),
                'contact_number' => $contact, 'email' => $email, 'opening_time' => $open, 'closing_time' => $close,
                'store_status' => $status,
            ];
            break;

        case 'ordering':
            $min = str_replace(',', '', trim((string)($_POST['min_order_amount'] ?? '0')));
            $prep = trim((string)($_POST['prep_time'] ?? ''));
            if ($min === '') $min = '0';
            if (!is_numeric($min) || (float)$min < 0 || (float)$min > 100000) $errors['min_order_amount'] = 'Enter an amount from 0 to 100,000.';
            if (!ctype_digit($prep) || (int)$prep < 1 || (int)$prep > 180) $errors['prep_time'] = 'Enter minutes from 1 to 180.';
            $online = settings_flag('online_ordering');
            $types = [settings_flag('dine_in'), settings_flag('take_out'), settings_flag('pick_up')];
            if ($online === '1' && !in_array('1', $types, true)) $errors['dine_in'] = 'Enable at least one order type (Dine-In, Take-Out or Pick-Up).';
            $save += [
                'online_ordering' => $online, 'dine_in' => $types[0], 'take_out' => $types[1], 'pick_up' => $types[2],
                'min_order_amount' => $errors ? '0' : number_format((float)$min, 2, '.', ''),
                'prep_time' => $errors ? '15' : (string)(int)$prep,
                'allow_notes' => settings_flag('allow_notes'),
                'order_rules' => str_replace("\r\n", "\n", settings_clean('order_rules', 300)),
            ];
            break;

        case 'payments':
            $cash = settings_flag('pay_cash'); $gcash = settings_flag('pay_gcash'); $maya = settings_flag('pay_maya');
            if ($cash !== '1' && $gcash !== '1' && $maya !== '1') $errors['pay_cash'] = 'Enable at least one payment method.';
            $numRe = '/^[0-9+\-\s]{0,30}$/';
            foreach (['gcash' => $gcash, 'maya' => $maya] as $m => $on) {
                $label = $m === 'gcash' ? 'GCash' : 'Maya';
                $num = settings_clean($m . '_number', 30);
                if (!preg_match($numRe, $num)) $errors[$m . '_number'] = 'Use numbers, spaces, + or - only.';
                $old = settings_image($m . '_qr', $m . '_qr_file', 'remove_' . $m . '_qr', $save, $errors);
                if ($old) $delete[] = $old;
                $hasQr = isset($save[$m . '_qr']) ? $save[$m . '_qr'] !== '' : setting($m . '_qr') !== '';
                if ($on === '1' && $num === '' && !$hasQr && !isset($errors[$m . '_qr_file'])) {
                    $errors[$m . '_number'] = "Add an account number or QR code for $label, or turn it off.";
                }
                $save[$m . '_name'] = settings_clean($m . '_name', 60);
                $save[$m . '_number'] = $num;
            }
            $save += ['pay_cash' => $cash, 'pay_gcash' => $gcash, 'pay_maya' => $maya, 'pay_instructions' => str_replace("\r\n", "\n", settings_clean('pay_instructions', 300))];
            break;

        case 'notifications':
            $th = trim((string)($_POST['low_stock_threshold'] ?? ''));
            if (!ctype_digit($th) || (int)$th < 1 || (int)$th > 100) $errors['low_stock_threshold'] = 'Enter a number from 1 to 100.';
            $save += [
                'notify_new_order' => settings_flag('notify_new_order'), 'notify_status' => settings_flag('notify_status'),
                'notify_sound' => settings_flag('notify_sound'), 'notify_low_stock' => settings_flag('notify_low_stock'),
                'low_stock_threshold' => $errors ? '5' : (string)(int)$th,
            ];
            break;

        case 'bot':
            $enabled = settings_flag('bot_enabled');
            $welcome = str_replace("\r\n", "\n", settings_clean('bot_welcome', 300));
            $name = settings_clean('bot_name', 30);
            $tone = (string)($_POST['bot_tone'] ?? '');
            $lang = (string)($_POST['bot_language'] ?? '');
            if ($enabled === '1' && trim($welcome) === '') $errors['bot_welcome'] = 'Write a welcome message for the chatbot.';
            if ($name === '') $errors['bot_name'] = 'Enter a name for the chatbot.';
            if (!array_key_exists($tone, bot_tones())) $errors['bot_tone'] = 'Choose a tone.';
            if (!array_key_exists($lang, bot_languages())) $errors['bot_language'] = 'Choose a language.';
            $save += ['bot_enabled' => $enabled, 'bot_welcome' => $welcome, 'bot_name' => $name, 'bot_tone' => $tone, 'bot_language' => $lang];
            break;

        case 'bot_knowledge':
            $k = str_replace("\r\n", "\n", settings_clean('bot_knowledge', 2500));
            if (count(array_filter(array_map('trim', explode("\n", $k)))) > 60) $errors['bot_knowledge'] = 'Please keep it to 60 lines or fewer.';
            $save['bot_knowledge'] = $k;
            break;

        case 'bot_privacy':
            $days = trim((string)($_POST['bot_retention_days'] ?? ''));
            $ptext = settings_clean('bot_privacy_text', 200);
            $pon = settings_flag('bot_privacy_enabled');
            if (!ctype_digit($days) || (int)$days > 3650) $errors['bot_retention_days'] = 'Enter a number from 0 to 3650.';
            if ($pon === '1' && $ptext === '') $errors['bot_privacy_text'] = 'Write the privacy notice, or turn it off.';
            $save += ['bot_privacy_enabled' => $pon, 'bot_privacy_text' => $ptext, 'bot_retention_days' => $errors ? '90' : (string)(int)$days];
            break;

        case 'faq_save':
            $fid = (int)($_POST['id'] ?? 0);
            $fq = settings_clean('faq_question', 150);
            $fk = settings_clean('faq_keywords', 150);
            $fa = settings_clean('faq_answer', 600);
            $fon = settings_flag('faq_active') === '1' ? 1 : 0;
            if ($fq === '') $errors['faq_question'] = 'Enter the question.';
            if ($fa === '') $errors['faq_answer'] = 'Enter the answer.';
            if (!$errors && $fid > 0) {
                $pdo->prepare("UPDATE kapitan_faqs SET question = ?, keywords = ?, answer = ?, is_active = ? WHERE id = ?")->execute([$fq, $fk !== '' ? $fk : null, $fa, $fon, $fid]);
                return ['success' => true, 'message' => 'FAQ updated.', 'errors' => [], 'data' => []];
            }
            if (!$errors) {
                if ((int)$pdo->query("SELECT COUNT(*) FROM kapitan_faqs")->fetchColumn() >= 100) {
                    $errors['faq_question'] = 'You can have up to 100 FAQs.';
                } else {
                    $pdo->prepare("INSERT INTO kapitan_faqs (question, keywords, answer, is_active) VALUES (?, ?, ?, ?)")->execute([$fq, $fk !== '' ? $fk : null, $fa, $fon]);
                    return ['success' => true, 'message' => 'FAQ added.', 'errors' => [], 'data' => ['reload' => true]];
                }
            }
            break;

        case 'faq_delete':
            $pdo->prepare("DELETE FROM kapitan_faqs WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
            return ['success' => true, 'message' => 'FAQ deleted.', 'errors' => [], 'data' => ['reload' => true]];

        case 'kapitan_clear_all':
            $pdo->exec("DELETE FROM kapitan_conversations");
            return ['success' => true, 'message' => 'All KAPitan Bot conversations were deleted.', 'errors' => [], 'data' => ['reload' => true]];

        case 'appearance':
            $accent = strtoupper(settings_clean('accent_color', 7));
            if (!preg_match('/^#[0-9A-F]{6}$/', $accent)) {
                $errors['accent_color'] = 'Choose a valid color.';
            } elseif (color_luminance($accent) > 0.45) {
                $errors['accent_color'] = 'That color is too light — buttons would be hard to read. Pick a darker one.';
            }
            $old = settings_image('store_logo', 'store_logo_file', 'remove_store_logo', $save, $errors);
            if ($old) $delete[] = $old;
            $old = settings_image('favicon', 'favicon_file', 'remove_favicon', $save, $errors);
            if ($old) $delete[] = $old;
            $save += ['accent_color' => $accent];
            break;

        case 'system':
            $save['maintenance_mode'] = settings_flag('maintenance_mode');
            $msg = $save['maintenance_mode'] === '1' ? 'Maintenance mode is ON. The storefront is now closed to the public.' : 'Maintenance mode is OFF. The storefront is live.';
            break;

        case 'password':
            $current = (string)($_POST['current_password'] ?? '');
            $new = (string)($_POST['new_password'] ?? '');
            $confirm = (string)($_POST['confirm_password'] ?? '');
            $stmt = $pdo->prepare("SELECT password_hash FROM admin_users WHERE id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $hash = (string)$stmt->fetchColumn();
            if ($current === '') $errors['current_password'] = 'Enter your current password.';
            elseif (!password_verify($current, $hash)) $errors['current_password'] = 'Current password is incorrect.';
            if (strlen($new) < 8) $errors['new_password'] = 'Use at least 8 characters.';
            elseif ($new === $current) $errors['new_password'] = 'New password must be different from the current one.';
            if ($confirm !== $new) $errors['confirm_password'] = 'Passwords do not match.';
            if (!$errors) {
                // ibina-bump ang epoch: mawawala ang ibang naka-login na session, mananatili ang kasalukuyan
                $pdo->prepare("UPDATE admin_users SET password_hash = ?, session_epoch = session_epoch + 1 WHERE id = ?")
                    ->execute([password_hash($new, PASSWORD_BCRYPT), $_SESSION['admin_id']]);
                $e = $pdo->prepare("SELECT session_epoch FROM admin_users WHERE id = ?");
                $e->execute([$_SESSION['admin_id']]);
                $_SESSION['admin_epoch'] = (int)$e->fetchColumn();
                return ['success' => true, 'message' => 'Password updated. Other devices have been signed out.', 'errors' => [], 'data' => ['clear' => true]];
            }
            break;

        case 'sessions':
            $pdo->prepare("UPDATE admin_users SET session_epoch = session_epoch + 1 WHERE id = ?")->execute([$_SESSION['admin_id']]);
            $e = $pdo->prepare("SELECT session_epoch FROM admin_users WHERE id = ?");
            $e->execute([$_SESSION['admin_id']]);
            $_SESSION['admin_epoch'] = (int)$e->fetchColumn();
            return ['success' => true, 'message' => 'All other sessions have been signed out.', 'errors' => [], 'data' => []];

        default:
            return ['success' => false, 'message' => 'Unable to save settings. Please try again.', 'errors' => [], 'data' => []];
    }

    if ($errors) {
        $required = count(array_filter($errors, fn($m) => (bool)preg_match('/(required|^Choose a|^Write a|^Enter your|^Choose one)/i', $m))) > 0;
        return ['success' => false, 'message' => $required ? 'Please complete all required fields.' : reset($errors), 'errors' => $errors, 'data' => []];
    }

    settings_save($pdo, $save);
    foreach ($delete as $old) delete_uploaded_image($old);

    $data['store_logo_url'] = asset_url(setting('store_logo'), '../');
    $data['favicon_url'] = asset_url(setting('favicon'), '../');
    $data['gcash_qr_url'] = asset_url(setting('gcash_qr'), '../');
    $data['maya_qr_url'] = asset_url(setting('maya_qr'), '../');
    $data['store_name'] = setting('store_name');
    $data['accent'] = accent();
    return ['success' => true, 'message' => $msg, 'errors' => [], 'data' => $data];
}

if (isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $section = (string)($_POST['section'] ?? '');
    try {
        $result = handle_settings_save($pdo, $section);
    } catch (Throwable $e) {
        error_log('settings save [' . $section . ']: ' . $e->getMessage());
        $result = ['success' => false, 'message' => 'Unable to save settings. Please try again.', 'errors' => [], 'data' => []];
    }
    if (wants_json()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result);
        exit();
    }
    // walang JavaScript: ibalik sa page na may flash message
    $_SESSION['settings_flash'] = ['type' => $result['success'] ? 'success' : 'error', 'message' => $result['message']];
    header('Location: admin.php?tab=settings&section=' . urlencode(in_array($section, ['password', 'sessions'], true) ? 'security' : (preg_match('/^(bot|faq_|kapitan_)/', $section) ? 'bot' : $section)));
    exit();
}

// --- FETCH DATA FOR DASHBOARD ---

// Fetch Products
$products = [];
$productsError = null;
try {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC");
    $products = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('admin products: ' . $e->getMessage());
    $productsError = 'Unable to load products right now. Please try again.';
}

// Fetch Orders (with a summary of items pulled from order_items)
$orders = [];
$ordersError = null;
try {
    $stmt = $pdo->query("
        SELECT o.*,
               GROUP_CONCAT(CONCAT(oi.quantity, 'x ', oi.product_name) SEPARATOR ', ') AS items_summary
        FROM orders o
        LEFT JOIN order_items oi ON oi.order_id = o.id
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $orders = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('admin orders: ' . $e->getMessage());
    $ordersError = 'Unable to load orders right now. Please try again.';
}

// Calculate Stats
$totalProducts = count($products);
$totalOrders = count($orders);
$totalSales = 0;
foreach ($orders as $o) {
    if (strtolower($o['status'] ?? '') === 'completed') {
        $totalSales += floatval($o['total_amount'] ?? 0);
    }
}

// --- SALES REPORT DATA (tab=reports) ---
$reportFrom = $_GET['from'] ?? date('Y-m-d', strtotime('-6 days'));
$reportTo = $_GET['to'] ?? date('Y-m-d');
$reportRows = [];
$reportDaily = [];
$reportTopProducts = [];
$reportTotal = 0;
$reportCount = 0;
$reportError = null;

if ($tab === 'reports') {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM orders
            WHERE LOWER(status) = 'completed'
              AND DATE(created_at) BETWEEN ? AND ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$reportFrom, $reportTo]);
        $reportRows = $stmt->fetchAll();

        foreach ($reportRows as $r) {
            $reportTotal += floatval($r['total_amount']);
            $reportCount++;
            $day = date('Y-m-d', strtotime($r['created_at']));
            $reportDaily[$day] = ($reportDaily[$day] ?? 0) + floatval($r['total_amount']);
        }
        ksort($reportDaily);

        $stmt2 = $pdo->prepare("
            SELECT oi.product_name, SUM(oi.quantity) AS total_qty, SUM(oi.quantity * oi.price) AS total_revenue
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            WHERE LOWER(o.status) = 'completed'
              AND DATE(o.created_at) BETWEEN ? AND ?
            GROUP BY oi.product_name
            ORDER BY total_qty DESC
            LIMIT 5
        ");
        $stmt2->execute([$reportFrom, $reportTo]);
        $reportTopProducts = $stmt2->fetchAll();
    } catch (Exception $e) {
        error_log('admin reports: ' . $e->getMessage());
        $reportError = 'Unable to load the report right now. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-accent="<?= h(accent()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h(setting('store_name')) ?> Admin Dashboard</title>
<?php $__fav = asset_url(setting('favicon'), '../'); if ($__fav): ?><link rel="icon" href="<?= h($__fav) ?>"><?php endif; ?>
<style>:root{ --k-accent: <?= h(accent()) ?>; }</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Karla:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://use.fontawesome.com/releases/v6.5.1/css/all.css">
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: { cream: '#F4EADA', card: '#FBF6EC', espresso: '#241812', bark: '#3A2A20', tan: '#CBA872', copper: '<?= h(accent()) ?>' },
        fontFamily: { display: ['"Bebas Neue"', 'sans-serif'], body: ['Karla', 'sans-serif'] }
      }
    }
  }
</script>
</head>
<body class="bg-[#F3F4F6] text-espresso font-body min-h-screen flex">

  <!-- SIDEBAR -->
  <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-30 hidden lg:hidden"></div>
  <aside id="adminSidebar" class="w-64 bg-espresso text-cream flex flex-col flex-shrink-0 fixed lg:static inset-y-0 left-0 z-40 -translate-x-full lg:translate-x-0 transition-transform duration-200 overflow-y-auto">
    <div class="p-6 flex items-center gap-3 border-b border-white/10">
      <span class="js-store-logo w-10 h-10 rounded-full bg-copper grid place-items-center font-display text-xl overflow-hidden flex-shrink-0"><?php $__logo = asset_url(setting('store_logo'), '../'); if ($__logo): ?><img src="<?= h($__logo) ?>" alt="" class="w-full h-full object-cover"><?php else: ?>☕<?php endif; ?></span>
      <div class="min-w-0">
        <h1 class="js-store-name font-display text-2xl tracking-wide leading-none truncate"><?= h(strtoupper(setting('store_name'))) ?></h1>
        <span class="text-xs text-tan font-semibold tracking-wider uppercase">ADMIN PANEL</span>
      </div>
    </div>
    
    <nav class="flex-1 p-4 space-y-1">
      <a href="admin.php?tab=products" class="flex items-center gap-3 px-4 py-3 rounded-xl font-semibold text-sm transition-colors <?= $tab === 'products' ? 'bg-copper text-cream' : 'text-cream/70 hover:bg-white/5 hover:text-cream' ?>">
        <i class="fa-solid fa-mug-hot"></i> Products Management
      </a>
      <a href="admin.php?tab=orders" class="flex items-center gap-3 px-4 py-3 rounded-xl font-semibold text-sm transition-colors <?= $tab === 'orders' ? 'bg-copper text-cream' : 'text-cream/70 hover:bg-white/5 hover:text-cream' ?>">
        <i class="fa-solid fa-receipt"></i> Customer Orders
      </a>
      <a href="kitchen.php" target="_blank" class="flex items-center gap-3 px-4 py-3 rounded-xl font-semibold text-sm text-cream/70 hover:bg-white/5 hover:text-cream transition-colors">
        <i class="fa-solid fa-kitchen-set"></i> Kitchen View <i class="fa-solid fa-arrow-up-right-from-square text-xs ml-auto"></i>
      </a>
      <a href="admin.php?tab=reports" class="flex items-center gap-3 px-4 py-3 rounded-xl font-semibold text-sm transition-colors <?= $tab === 'reports' ? 'bg-copper text-cream' : 'text-cream/70 hover:bg-white/5 hover:text-cream' ?>">
        <i class="fa-solid fa-chart-line"></i> Sales Report
      </a>
      <a href="admin.php?tab=kapitan" class="flex items-center gap-3 px-4 py-3 rounded-xl font-semibold text-sm transition-colors <?= $tab === 'kapitan' ? 'bg-copper text-cream' : 'text-cream/70 hover:bg-white/5 hover:text-cream' ?>">
        <i class="fa-solid fa-comments"></i> KAPitan Bot
      </a>
      <a href="admin.php?tab=settings" class="flex items-center gap-3 px-4 py-3 rounded-xl font-semibold text-sm transition-colors <?= $tab === 'settings' ? 'bg-copper text-cream' : 'text-cream/70 hover:bg-white/5 hover:text-cream' ?>">
        <i class="fa-solid fa-gear"></i> Settings
      </a>
      <a href="../index.php" target="_blank" class="flex items-center gap-3 px-4 py-3 rounded-xl font-semibold text-sm text-tan hover:bg-white/5 transition-colors mt-6">
        <i class="fa-solid fa-store"></i> View Storefront <i class="fa-solid fa-arrow-up-right-from-square text-xs ml-auto"></i>
      </a>
    </nav>

    <div class="p-4 border-t border-white/10">
      <p class="text-xs text-cream/50 mb-2 px-1">Naka-login: <span class="text-cream font-semibold"><?= htmlspecialchars($_SESSION['admin_username'] ?? '') ?></span></p>
      <a href="admin_logout.php" class="flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl font-semibold text-xs bg-white/5 hover:bg-rose-600 transition-colors">
        <i class="fa-solid fa-right-from-bracket"></i> Logout
      </a>
      <p class="text-[10px] text-cream/30 text-center mt-3">Kapihan POS v<?= h(KAPIHAN_VERSION) ?> &copy; <?= date('Y') ?></p>
    </div>
  </aside>

  <!-- MAIN CONTENT AREA -->
  <main class="flex-1 flex flex-col min-w-0 overflow-y-auto">

    <div class="lg:hidden sticky top-0 z-20 bg-espresso text-cream flex items-center gap-3 px-4 py-3">
      <button type="button" id="sidebarToggle" class="w-9 h-9 rounded-lg bg-white/10 grid place-items-center" aria-label="Open menu" aria-controls="adminSidebar"><i class="fa-solid fa-bars"></i></button>
      <span class="js-store-name font-display text-xl tracking-wide truncate"><?= h(strtoupper(setting('store_name'))) ?></span>
    </div>

    <?php
      $msg = $_GET['msg'] ?? '';
      $messages = [
        'seeded'         => ['ok', 'The 24 default products were added.'],
        'added'          => ['ok', 'Product added.'],
        'updated'        => ['ok', 'Product updated.'],
        'deleted'        => ['ok', 'Product deleted.'],
        'upload_error'   => ['error', htmlspecialchars($_GET['err'] ?? 'The product could not be saved. Please try again.')],
        'status_updated' => ['ok', 'Order status updated.'],
        'payment_updated' => ['ok', 'Payment status updated.'],
        'status_error'   => ['error', 'The order status could not be updated. Please try again.'],
      ];
    ?>
    <?php
      $__me = $pdo->prepare("SELECT password_hash FROM admin_users WHERE id = ?");
      $__me->execute([$_SESSION['admin_id']]);
      $__hash = (string)$__me->fetchColumn();
      if ($__hash !== '' && password_verify('admin123', $__hash)):
    ?>
    <div class="mx-8 mt-6 px-4 py-3 rounded-xl text-sm font-semibold bg-amber-50 text-amber-800 border border-amber-200">
      ⚠️ Your admin password is still the initial one. Please change it in Settings → Security before going live.
    </div>
    <?php endif; ?>
    <?php
      if ($tab !== 'settings' && setting_on('notify_low_stock')) {
          try {
              $lowStmt = $pdo->prepare("SELECT name, stock_qty FROM products WHERE stock_qty IS NOT NULL AND stock_qty <= ? ORDER BY stock_qty ASC, name ASC LIMIT 8");
              $lowStmt->execute([max(1, (int)setting('low_stock_threshold'))]);
              $lowItems = $lowStmt->fetchAll();
          } catch (Throwable $e) { $lowItems = []; }
          if ($lowItems):
    ?>
    <div class="mx-4 sm:mx-8 mt-6 px-4 py-3 rounded-xl text-sm bg-amber-50 text-amber-900 border border-amber-200 flex flex-wrap items-center gap-x-3 gap-y-1">
      <span class="font-semibold"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Low stock / unavailable:</span>
      <span class="min-w-0"><?= h(implode(', ', array_map(fn($i) => $i['name'] . ((int)$i['stock_qty'] === 0 ? ' (sold out)' : ' (' . (int)$i['stock_qty'] . ' left)'), $lowItems))) ?></span>
      <?php if ($tab !== 'products'): ?><a href="admin.php?tab=products" class="font-semibold underline ml-auto">Manage products</a><?php endif; ?>
    </div>
    <?php endif; } ?>
    <?php
      if ($tab === 'products') {
          $missingImgs = products_missing_images($pdo);
          if ($missingImgs):
    ?>
    <div class="mx-4 sm:mx-8 mt-6 px-4 py-3 rounded-xl text-sm bg-amber-50 text-amber-900 border border-amber-200">
      <span class="font-semibold"><i class="fa-solid fa-image mr-1"></i> <?= count($missingImgs) ?> product image<?= count($missingImgs) > 1 ? 's are' : ' is' ?> missing from the server:</span>
      <?= h(implode(', ', array_slice($missingImgs, 0, 8))) ?><?= count($missingImgs) > 8 ? ' …' : '' ?>.
      Their records are kept — upload the image again with Edit.
    </div>
    <?php endif; } ?>
    <?php if ($msg && isset($messages[$msg])): ?>
    <div class="mx-8 mt-6 px-4 py-3 rounded-xl text-sm font-semibold <?= $messages[$msg][0] === 'ok' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-rose-50 text-rose-700 border border-rose-100' ?>">
      <?= $messages[$msg][1] ?>
    </div>
    <?php endif; ?>

    <?php if ($productsError): ?>
    <div class="mx-8 mt-6 px-4 py-3 rounded-xl text-sm font-semibold bg-rose-50 text-rose-700 border border-rose-100">
      Database error (products): <?= htmlspecialchars($productsError) ?>
    </div>
    <?php endif; ?>

    <?php if ($ordersError): ?>
    <div class="mx-8 mt-6 px-4 py-3 rounded-xl text-sm font-semibold bg-rose-50 text-rose-700 border border-rose-100">
      Database error (orders): <?= htmlspecialchars($ordersError) ?>
    </div>
    <?php endif; ?>

    <!-- TOP STATS BAR -->
    <div class="p-8 pb-4">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
          <div>
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Total Sales (Completed)</p>
            <p class="font-display text-3xl tracking-wide mt-1 text-espresso"><?= h(cur()) ?><?= number_format($totalSales, 2) ?></p>
          </div>
          <div class="w-12 h-12 rounded-full bg-amber-50 text-amber-600 grid place-items-center text-xl"><i class="fa-solid fa-wallet"></i></div>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
          <div>
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Total Orders</p>
            <p class="font-display text-3xl tracking-wide mt-1 text-espresso"><?= $totalOrders ?></p>
          </div>
          <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-600 grid place-items-center text-xl"><i class="fa-solid fa-bag-shopping"></i></div>
        </div>

        <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm flex items-center justify-between">
          <div>
            <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Active Products</p>
            <p class="font-display text-3xl tracking-wide mt-1 text-espresso"><?= count(array_filter($products, fn($x) => (int)($x['is_active'] ?? 1) === 1)) ?></p>
          </div>
          <div class="w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 grid place-items-center text-xl"><i class="fa-solid fa-mug-saucer"></i></div>
        </div>
      </div>
    </div>

    <!-- TAB 1: PRODUCTS MANAGEMENT -->
    <?php if ($tab === 'products'): ?>
    <div class="p-8 pt-4 space-y-8">
      
      <!-- ADD / SEED PRODUCT FORM -->
      <div class="bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
          <h2 class="font-display text-2xl tracking-wide text-espresso flex items-center gap-2">
            <i class="fa-solid fa-plus text-copper text-lg"></i> Magdagdag ng Bagong Product
          </h2>

          <?php if ($totalProducts === 0): ?>
          <form method="POST">
<?= csrf_field() ?>
            <input type="hidden" name="action" value="seed_defaults">
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs px-4 py-2.5 rounded-xl transition-colors flex items-center gap-2 shadow-sm">
              <i class="fa-solid fa-seedling"></i> Seed Default 24 Products into Database
            </button>
          </form>
          <?php endif; ?>
        </div>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
<?= csrf_field() ?>
          <input type="hidden" name="action" value="add_product">
          <div class="grid md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Pangalan ng Product</label>
              <input type="text" name="name" required placeholder="hal. Iced Spanish Latte" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-copper">
            </div>
            <div>
              <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Presyo (<?= h(cur()) ?>)</label>
              <input type="number" step="0.01" name="price" required placeholder="145" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-copper">
            </div>
          </div>

          <div class="grid md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Category</label>
              <select name="category" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-copper">
                <option value="coffee">Hot Coffee</option>
                <option value="cold">Iced Drinks</option>
                <option value="special">Non-Coffee</option>
                <option value="merienda">Merienda</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Stock Quantity <span class="font-normal normal-case text-gray-400">(blangko = walang limitasyon)</span></label>
              <input type="number" min="0" step="1" name="stock_qty" placeholder="hal. 50" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-copper">
            </div>
          </div>

          <div class="grid md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Image URL <span class="font-normal normal-case text-gray-400">(optional)</span></label>
              <input type="url" name="image" placeholder="https://..." class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-copper">
            </div>
            <div>
              <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Or upload an image <span class="font-normal normal-case text-gray-400">(JPG, PNG, WEBP · max 4MB)</span></label>
              <input type="file" name="image_upload" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-copper file:text-white file:text-xs file:font-semibold focus:outline-none">
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Description / Note</label>
            <input type="text" name="description" placeholder="Maikling paglalarawan sa kape" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-copper">
          </div>

          <label class="inline-flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
            <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300"> Show on the storefront
          </label>

          <button type="submit" class="bg-copper hover:bg-amber-800 text-white font-semibold text-sm px-6 py-2.5 rounded-xl transition-colors">
            Save Product
          </button>
        </form>
      </div>

      <!-- PRODUCTS TABLE -->
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
          <h2 class="font-display text-2xl tracking-wide">Listahan ng mga Products</h2>
          <div class="flex items-center gap-3">
            <input type="text" id="productSearch" onkeyup="filterTable('productSearch','productsTable')" placeholder="Maghanap ng product..." class="bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-copper w-48">
            <span class="text-xs font-bold text-gray-400 uppercase whitespace-nowrap"><?= $totalProducts ?> Total Items</span>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse" id="productsTable">
            <thead>
              <tr class="bg-gray-50 text-[11px] uppercase font-bold text-gray-400 border-b border-gray-100">
                <th class="p-4 pl-6">Image</th>
                <th class="p-4">Pangalan</th>
                <th class="p-4">Category</th>
                <th class="p-4">Presyo</th>
                <th class="p-4">Stock</th>
                <th class="p-4 pr-6 text-right">Aksyon</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
              <?php if (empty($products)): ?>
              <tr>
                <td colspan="6" class="p-8 text-center text-gray-400">
                  Walang laman ang products list. Mag-add gamit ang form sa taas o i-click ang Seed Button.
                </td>
              </tr>
              <?php else: foreach ($products as $p): $p['image_url'] = product_image_url($p['image'] ?? '', '../'); ?>
              <tr class="hover:bg-gray-50/50 transition-colors">
                <td class="p-4 pl-6">
                  <?php $imgUrl = $p['image_url'] ?? ''; ?>
                  <img src="<?= h($imgUrl !== '' ? $imgUrl : product_placeholder()) ?>" alt="<?= h($p['name']) ?>" loading="lazy"
                       onerror="this.onerror=null;this.src='<?= product_placeholder() ?>'"
                       class="w-12 h-12 rounded-full object-cover border border-gray-200 bg-white">
                </td>
                <td class="p-4 font-semibold text-espresso">
                  <?= htmlspecialchars($p['name']) ?>
                  <?php if (isset($p['is_active']) && !(int)$p['is_active']): ?><span class="ml-1 text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-gray-200 text-gray-500 align-middle">Hidden</span><?php endif; ?>
                  <p class="text-xs font-normal text-gray-400"><?= htmlspecialchars($p['description'] ?? '') ?></p>
                </td>
                <td class="p-4">
                  <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold uppercase bg-amber-50 text-amber-700">
                    <?= htmlspecialchars($p['category']) ?>
                  </span>
                </td>
                <td class="p-4 font-bold text-copper"><?= h(cur()) ?><?= number_format($p['price'], 2) ?></td>
                <td class="p-4">
                  <?php if ($p['stock_qty'] === null): ?>
                    <span class="text-xs text-gray-400">Unlimited</span>
                  <?php elseif ((int)$p['stock_qty'] <= 0): ?>
                    <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-700">Out of Stock</span>
                  <?php elseif ((int)$p['stock_qty'] <= 5): ?>
                    <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-700"><?= (int)$p['stock_qty'] ?> left</span>
                  <?php else: ?>
                    <span class="text-xs font-semibold text-gray-600"><?= (int)$p['stock_qty'] ?></span>
                  <?php endif; ?>
                </td>
                <td class="p-4 pr-6 text-right space-x-2">
                  <button onclick="openEditModal(<?= h(json_encode($p)) ?>)" class="px-3 py-1.5 text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors">Edit</button>
                  <form method="POST" class="inline" data-confirm="This product will be permanently removed from the menu." data-confirm-title="Delete this product?" data-confirm-label="Delete" data-confirm-danger="1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="id" value="<?= h($p['id']) ?>">
                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg transition-colors">Delete</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
    <?php endif; ?>

    <!-- TAB 2: ORDERS MANAGEMENT -->
    <?php if ($tab === 'orders'): ?>
    <div class="p-8 pt-4">
      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
          <h2 class="font-display text-2xl tracking-wide">Customer Orders</h2>
          <div class="flex items-center gap-3">
            <input type="text" id="orderSearch" onkeyup="filterTable('orderSearch','ordersTable')" placeholder="Maghanap ng order o pangalan..." class="bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-copper w-56">
            <span class="text-xs font-bold text-gray-400 uppercase whitespace-nowrap"><?= $totalOrders ?> Orders Recorded</span>
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse" id="ordersTable">
            <thead>
              <tr class="bg-gray-50 text-[11px] uppercase font-bold text-gray-400 border-b border-gray-100">
                <th class="p-4 pl-6">Order ID</th>
                <th class="p-4">Customer Name</th>
                <th class="p-4">Items Ordered</th>
                <th class="p-4">Total Amount</th>
                <th class="p-4">Payment</th>
                <th class="p-4">Date & Time</th>
                <th class="p-4">Status</th>
                <th class="p-4 pr-6 text-right">Update Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
              <?php if (empty($orders)): ?>
              <tr>
                <td colspan="8" class="p-8 text-center text-gray-400">Walang orders na natatanggap pa sa kasalukuyan.</td>
              </tr>
              <?php else: foreach ($orders as $o): ?>
              <tr class="hover:bg-gray-50/50 transition-colors">
                <td class="p-4 pl-6 font-bold text-gray-500">#<?= $o['id'] ?></td>
                <td class="p-4 font-semibold text-espresso"><?= htmlspecialchars($o['customer_name'] ?? 'Guest') ?>
                  <span class="ml-1 text-[10px] font-bold uppercase bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full align-middle"><?= h(['dine_in' => 'Dine-In', 'take_out' => 'Take-Out', 'pick_up' => 'Pick-Up'][$o['order_type'] ?? 'pick_up'] ?? 'Pick-Up') ?></span>
                  <?php if (!empty($o['notes'])): ?><span class="block text-xs font-normal italic text-gray-400 mt-0.5">“<?= h($o['notes']) ?>”</span><?php endif; ?></td>
                <td class="p-4 text-xs text-gray-600 max-w-xs">
                  <?= htmlspecialchars($o['items_summary'] ?? 'N/A') ?>
                </td>
                <td class="p-4 font-bold text-copper"><?= h(cur()) ?><?= number_format($o['total_amount'] ?? 0, 2) ?></td>
                <td class="p-4">
                  <?php
                    $pm = strtolower($o['payment_method'] ?? 'cash');
                    $pmLabel = ['cash' => 'Cash', 'gcash' => 'GCash', 'maya' => 'Maya'][$pm] ?? ucfirst($pm);
                  ?>
                  <span class="inline-block px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700"><?= $pmLabel ?></span>
                  <?php
                    $isPaid = strtolower((string)($o['payment_status'] ?? 'pending')) === 'paid';
                    $oid = (int)$o['id'];
                  ?>
                  <div class="mt-1.5 flex items-center gap-2 flex-wrap">
                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold uppercase <?= $isPaid ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>"><?= $isPaid ? 'Paid' : ($pm === 'cash' ? 'Unpaid' : 'Pending') ?></span>
                    <?php if (strtolower((string)($o['status'] ?? '')) !== 'cancelled'): ?>
                    <form method="POST" class="inline"
                          <?php if (!$isPaid): ?>data-confirm="Confirm that you received the <?= h($pmLabel) ?> payment of <?= h(cur() . number_format((float)($o['total_amount'] ?? 0), 2)) ?> for order #<?= $oid ?>." data-confirm-title="Mark as paid?" data-confirm-label="Mark as paid"<?php endif; ?>>
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="set_payment_status">
                      <input type="hidden" name="order_id" value="<?= $oid ?>">
                      <input type="hidden" name="payment_status" value="<?= $isPaid ? 'pending' : 'paid' ?>">
                      <button type="submit" class="text-[11px] font-semibold text-copper hover:underline"><?= $isPaid ? 'Undo' : ($pm === 'cash' ? 'Mark paid' : 'Verify payment') ?></button>
                    </form>
                    <?php endif; ?>
                  </div>
                </td>
                <td class="p-4 text-xs text-gray-400"><?= $o['created_at'] ?? 'N/A' ?></td>
                <td class="p-4">
                  <?php 
                    $st = strtolower($o['status'] ?? 'pending');
                    $badge = 'bg-gray-100 text-gray-600';
                    if($st === 'completed') $badge = 'bg-emerald-100 text-emerald-700';
                    if($st === 'brewing') $badge = 'bg-amber-100 text-amber-700';
                    if($st === 'cancelled') $badge = 'bg-rose-100 text-rose-700';
                  ?>
                  <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold uppercase <?= $badge ?>">
                    <?= ucfirst($st) ?>
                  </span>
                </td>
                <td class="p-4 pr-6 text-right">
                  <div class="inline-flex items-center gap-1.5">
                    <a href="../track.php?order_id=<?= $o['id'] ?>" target="_blank" class="px-2.5 py-1.5 text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors" title="Buksan ang tracking page ng customer">
                      <i class="fa-solid fa-qrcode"></i>
                    </a>
                    <a href="../receipt.php?order_id=<?= $o['id'] ?>" target="_blank" class="px-2.5 py-1.5 text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition-colors" title="I-print ang resibo">
                      <i class="fa-solid fa-print"></i>
                    </a>
                    <form method="POST" class="inline-flex gap-1">
<?= csrf_field() ?>
                      <input type="hidden" name="action" value="update_order_status">
                      <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                      <select name="status" onchange="this.form.submit()" class="bg-gray-50 border border-gray-200 text-xs font-semibold rounded-lg px-2 py-1.5 focus:outline-none">
                        <option value="pending" <?= $st==='pending'?'selected':'' ?>>Pending</option>
                        <option value="brewing" <?= $st==='brewing'?'selected':'' ?>>Brewing</option>
                        <option value="completed" <?= $st==='completed'?'selected':'' ?>>Completed</option>
                        <option value="cancelled" <?= $st==='cancelled'?'selected':'' ?>>Cancelled</option>
                      </select>
                    </form>
                  </div>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- TAB 3: SALES REPORT -->
    <?php if ($tab === 'kapitan'):
      kapitan_purge($pdo);
      $kView = (int)($_GET['view'] ?? 0);
      $kq = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
      $kFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['from'] ?? '')) ? (string)$_GET['from'] : '';
      $kTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['to'] ?? '')) ? (string)$_GET['to'] : '';
      $kPage = max(1, (int)($_GET['p'] ?? 1));
      $kPer = 15;
      $kFilters = array_filter(['q' => $kq, 'from' => $kFrom, 'to' => $kTo], fn($v) => $v !== '');

      function k_code(string $sid): string { return 'S-' . strtoupper(substr($sid, 0, 6)); }
      function k_badge(string $status, int $age): string {
          $active = $status === 'open' && $age < 1800;
          return $active
            ? '<span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Active</span>'
            : '<span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-gray-200 text-gray-500">Closed</span>';
      }
    ?>
    <div class="p-4 sm:p-8 pt-4">
      <div class="max-w-6xl mx-auto">

      <?php if ($kView > 0):
        $cs = $pdo->prepare("SELECT id, session_id, status, created_at, updated_at, TIMESTAMPDIFF(SECOND, updated_at, NOW()) AS age FROM kapitan_conversations WHERE id = ?");
        $cs->execute([$kView]);
        $conv = $cs->fetch();
        $mp = max(1, (int)($_GET['mp'] ?? 1));
        $mPer = 100;
        $backQs = http_build_query(array_merge(['tab' => 'kapitan'], $kFilters, $kPage > 1 ? ['p' => $kPage] : []));
      ?>
        <a href="admin.php?<?= h($backQs) ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-gray-500 hover:text-espresso mb-4"><i class="fa-solid fa-arrow-left"></i> All conversations</a>
        <?php if (!$conv): ?>
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 text-center text-gray-400">This conversation no longer exists.</div>
        <?php else:
          $mTotal = (int)$pdo->query("SELECT COUNT(*) FROM kapitan_messages WHERE conversation_id = " . (int)$conv['id'])->fetchColumn();
          $ms = $pdo->prepare("SELECT sender, message, created_at FROM kapitan_messages WHERE conversation_id = ? ORDER BY id ASC LIMIT " . (int)$mPer . " OFFSET " . (int)(($mp - 1) * $mPer));
          $ms->execute([$conv['id']]);
          $msgs = $ms->fetchAll();
          $mPages = max(1, (int)ceil($mTotal / $mPer));
        ?>
          <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-7">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
              <div class="min-w-0">
                <h2 class="font-display text-3xl tracking-wide leading-none">Conversation #<?= (int)$conv['id'] ?></h2>
                <p class="text-sm text-gray-400 mt-1.5">
                  <?= h(k_code($conv['session_id'])) ?> · Started <?= h(date('M j, Y · g:i A', strtotime($conv['created_at']))) ?> · Last message <?= h(date('M j · g:i A', strtotime($conv['updated_at']))) ?>
                  · <?= number_format($mTotal) ?> messages <?= k_badge((string)$conv['status'], (int)$conv['age']) ?>
                </p>
              </div>
              <form method="POST" data-confirm="This conversation and all of its messages will be permanently deleted." data-confirm-title="Delete this conversation?" data-confirm-label="Delete" data-confirm-danger="1">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="kapitan_delete">
                <input type="hidden" name="id" value="<?= (int)$conv['id'] ?>">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-rose-600 border border-rose-200 hover:bg-rose-50 rounded-xl transition-colors"><i class="fa-regular fa-trash-can"></i> Delete</button>
              </form>
            </div>

            <div class="space-y-3 max-w-3xl">
              <?php foreach ($msgs as $m): $isBot = $m['sender'] === 'bot'; ?>
                <div class="flex <?= $isBot ? 'justify-start' : 'justify-end' ?>">
                  <div class="max-w-[85%] min-w-0">
                    <p class="text-[10px] font-bold uppercase text-gray-400 mb-1 <?= $isBot ? '' : 'text-right' ?>"><?= $isBot ? h(setting('bot_name')) : 'Customer' ?> · <?= h(date('g:i A', strtotime($m['created_at']))) ?></p>
                    <div class="rounded-2xl px-4 py-2.5 text-sm whitespace-pre-wrap break-words <?= $isBot ? 'bg-gray-100 text-espresso rounded-tl-md' : 'bg-espresso text-cream rounded-tr-md' ?>"><?= h($m['message']) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <?php if ($mPages > 1): ?>
              <div class="flex items-center justify-between mt-6 text-sm">
                <?php $mq = fn($n) => 'admin.php?' . h(http_build_query(array_merge(['tab' => 'kapitan', 'view' => (int)$conv['id'], 'mp' => $n], $kFilters))); ?>
                <a href="<?= $mp > 1 ? $mq($mp - 1) : '#' ?>" class="px-4 py-2 rounded-xl border border-gray-200 font-semibold <?= $mp > 1 ? 'hover:bg-gray-50' : 'opacity-40 pointer-events-none' ?>">← Earlier</a>
                <span class="text-gray-400">Page <?= $mp ?> of <?= $mPages ?></span>
                <a href="<?= $mp < $mPages ? $mq($mp + 1) : '#' ?>" class="px-4 py-2 rounded-xl border border-gray-200 font-semibold <?= $mp < $mPages ? 'hover:bg-gray-50' : 'opacity-40 pointer-events-none' ?>">Later →</a>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

      <?php else:
        $where = []; $params = [];
        if ($kq !== '') {
            $esc = addcslashes($kq, '%_\\');
            $or = ["EXISTS (SELECT 1 FROM kapitan_messages mm WHERE mm.conversation_id = c.id AND mm.message LIKE ?)", "c.session_id LIKE ?"];
            $params[] = '%' . $esc . '%';
            $params[] = strtolower($esc) . '%';
            $idq = ltrim($kq, '#');
            if (ctype_digit($idq) && strlen($idq) <= 9) { $or[] = "c.id = ?"; $params[] = (int)$idq; }
            $where[] = '(' . implode(' OR ', $or) . ')';
        }
        if ($kFrom !== '') { $where[] = "c.updated_at >= ?"; $params[] = $kFrom . ' 00:00:00'; }
        if ($kTo !== '') { $where[] = "c.updated_at <= ?"; $params[] = $kTo . ' 23:59:59'; }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $ct = $pdo->prepare("SELECT COUNT(*) FROM kapitan_conversations c $whereSql");
        $ct->execute($params);
        $kTotal = (int)$ct->fetchColumn();
        $kPages = max(1, (int)ceil($kTotal / $kPer));
        if ($kPage > $kPages) $kPage = $kPages;

        $ls = $pdo->prepare("SELECT c.id, c.session_id, c.status, c.created_at, c.updated_at, TIMESTAMPDIFF(SECOND, c.updated_at, NOW()) AS age,
                (SELECT COUNT(*) FROM kapitan_messages m WHERE m.conversation_id = c.id) AS msg_count,
                (SELECT m2.message FROM kapitan_messages m2 WHERE m2.conversation_id = c.id AND m2.sender = 'customer' ORDER BY m2.id ASC LIMIT 1) AS first_msg
            FROM kapitan_conversations c $whereSql ORDER BY c.updated_at DESC, c.id DESC LIMIT " . (int)$kPer . " OFFSET " . (int)(($kPage - 1) * $kPer));
        $ls->execute($params);
        $convs = $ls->fetchAll();
        $pageUrl = fn($n) => 'admin.php?' . h(http_build_query(array_merge(['tab' => 'kapitan'], $kFilters, $n > 1 ? ['p' => $n] : [])));
      ?>
        <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
          <div>
            <h2 class="font-display text-3xl tracking-wide leading-none">KAPitan Bot Conversations</h2>
            <p class="text-sm text-gray-400 mt-1.5">What customers ask the chatbot. <a href="admin.php?tab=settings&section=bot" class="text-copper font-semibold hover:underline">Bot settings</a></p>
          </div>
          <form method="POST" class="flex items-center gap-2" data-confirm="Conversations older than the selected age will be permanently deleted." data-confirm-title="Clear old conversations?" data-confirm-label="Clear" data-confirm-danger="1">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="kapitan_clear_old">
            <label for="kDays" class="sr-only">Older than</label>
            <select id="kDays" name="days" class="bg-white border border-gray-200 rounded-xl px-3 py-2 text-sm font-semibold focus:outline-none focus:border-copper">
              <?php foreach ([7 => '7 days', 30 => '30 days', 90 => '90 days', 180 => '180 days'] as $d => $lab): ?><option value="<?= $d ?>" <?= $d === 30 ? 'selected' : '' ?>>Older than <?= $lab ?></option><?php endforeach; ?>
            </select>
            <button type="submit" class="px-4 py-2 text-sm font-semibold text-rose-600 border border-rose-200 hover:bg-rose-50 rounded-xl transition-colors">Clear old</button>
          </form>
        </div>

        <form method="GET" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex flex-wrap items-end gap-3 mb-4">
          <input type="hidden" name="tab" value="kapitan">
          <div class="flex-1 min-w-[180px]">
            <label for="kq" class="block text-[11px] font-bold uppercase text-gray-400 mb-1">Search</label>
            <input id="kq" type="search" name="q" value="<?= h($kq) ?>" placeholder="Message text, #ID or session code" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-copper">
          </div>
          <div>
            <label for="kfrom" class="block text-[11px] font-bold uppercase text-gray-400 mb-1">From</label>
            <input id="kfrom" type="date" name="from" value="<?= h($kFrom) ?>" class="bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-copper">
          </div>
          <div>
            <label for="kto" class="block text-[11px] font-bold uppercase text-gray-400 mb-1">To</label>
            <input id="kto" type="date" name="to" value="<?= h($kTo) ?>" class="bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-copper">
          </div>
          <button type="submit" class="bg-copper hover:brightness-90 text-white font-semibold text-sm px-5 py-2.5 rounded-xl transition"><i class="fa-solid fa-magnifying-glass mr-1"></i> Filter</button>
          <?php if ($kFilters): ?><a href="admin.php?tab=kapitan" class="text-sm font-semibold text-gray-500 hover:text-espresso px-2 py-2.5">Reset</a><?php endif; ?>
        </form>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <?php if (!$convs): ?>
            <div class="p-10 text-center text-gray-400 text-sm"><?= $kFilters ? 'No conversations match your search.' : 'No conversations yet. They appear here once customers start chatting with the bot.' ?></div>
          <?php else: ?>
            <div class="overflow-x-auto">
              <table class="w-full text-sm min-w-[720px]">
                <thead class="bg-gray-50 text-left text-[11px] uppercase text-gray-400">
                  <tr><th class="px-4 py-3">ID</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Started</th><th class="px-4 py-3">Last message</th><th class="px-4 py-3">First question</th><th class="px-4 py-3 text-center">Msgs</th><th class="px-4 py-3">Status</th><th class="px-4 py-3"></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                  <?php foreach ($convs as $c):
                    $viewQs = http_build_query(array_merge(['tab' => 'kapitan', 'view' => (int)$c['id']], $kFilters, $kPage > 1 ? ['p' => $kPage] : [])); ?>
                    <tr class="hover:bg-gray-50/60">
                      <td class="px-4 py-3 font-semibold">#<?= (int)$c['id'] ?></td>
                      <td class="px-4 py-3 text-gray-500 font-mono text-xs"><?= h(k_code($c['session_id'])) ?></td>
                      <td class="px-4 py-3 text-gray-500 whitespace-nowrap"><?= h(date('M j · g:i A', strtotime($c['created_at']))) ?></td>
                      <td class="px-4 py-3 text-gray-500 whitespace-nowrap"><?= h(date('M j · g:i A', strtotime($c['updated_at']))) ?></td>
                      <td class="px-4 py-3 max-w-[260px] truncate"><?= h(mb_substr((string)$c['first_msg'], 0, 80)) ?></td>
                      <td class="px-4 py-3 text-center"><?= (int)$c['msg_count'] ?></td>
                      <td class="px-4 py-3"><?= k_badge((string)$c['status'], (int)$c['age']) ?></td>
                      <td class="px-4 py-3 text-right"><a href="admin.php?<?= h($viewQs) ?>" class="font-semibold text-copper hover:underline">Open</a></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($kPages > 1): ?>
          <div class="flex flex-wrap items-center justify-between gap-3 mt-4 text-sm">
            <span class="text-gray-400"><?= number_format($kTotal) ?> conversations · Page <?= $kPage ?> of <?= $kPages ?></span>
            <div class="flex items-center gap-1">
              <a href="<?= $kPage > 1 ? $pageUrl($kPage - 1) : '#' ?>" class="px-3 py-2 rounded-lg border border-gray-200 font-semibold <?= $kPage > 1 ? 'hover:bg-gray-50' : 'opacity-40 pointer-events-none' ?>">←</a>
              <?php for ($i = max(1, $kPage - 2); $i <= min($kPages, $kPage + 2); $i++): ?>
                <a href="<?= $pageUrl($i) ?>" class="px-3 py-2 rounded-lg border font-semibold <?= $i === $kPage ? 'bg-copper text-white border-copper' : 'border-gray-200 hover:bg-gray-50' ?>"><?= $i ?></a>
              <?php endfor; ?>
              <a href="<?= $kPage < $kPages ? $pageUrl($kPage + 1) : '#' ?>" class="px-3 py-2 rounded-lg border border-gray-200 font-semibold <?= $kPage < $kPages ? 'hover:bg-gray-50' : 'opacity-40 pointer-events-none' ?>">→</a>
            </div>
          </div>
        <?php endif; ?>

        <p class="text-[11px] text-gray-400 mt-6">Customers are identified only by an anonymous session code. No names, emails, phone numbers, passwords or card numbers are saved.</p>
      <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'reports'): ?>
    <div class="p-8 pt-4 space-y-6">

      <form method="GET" class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm flex flex-wrap items-end gap-4">
        <input type="hidden" name="tab" value="reports">
        <div>
          <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Mula</label>
          <input type="date" name="from" value="<?= htmlspecialchars($reportFrom) ?>" class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-copper">
        </div>
        <div>
          <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Hanggang</label>
          <input type="date" name="to" value="<?= htmlspecialchars($reportTo) ?>" class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-copper">
        </div>
        <button class="bg-espresso text-cream text-sm font-semibold px-6 py-2.5 rounded-xl hover:bg-black transition-colors">Tingnan</button>

        <div class="flex gap-2 ml-auto">
          <a href="admin.php?tab=reports&from=<?= date('Y-m-d') ?>&to=<?= date('Y-m-d') ?>" class="text-xs font-semibold px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600">Ngayong Araw</a>
          <a href="admin.php?tab=reports&from=<?= date('Y-m-d', strtotime('-6 days')) ?>&to=<?= date('Y-m-d') ?>" class="text-xs font-semibold px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600">7 Araw</a>
          <a href="admin.php?tab=reports&from=<?= date('Y-m-01') ?>&to=<?= date('Y-m-d') ?>" class="text-xs font-semibold px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-600">Ngayong Buwan</a>
        </div>
      </form>

      <?php if ($reportError): ?>
        <div class="bg-rose-50 text-rose-700 border border-rose-100 rounded-xl px-4 py-3 text-sm font-semibold">
          Database error: <?= htmlspecialchars($reportError) ?>
        </div>
      <?php else: ?>

      <div class="grid sm:grid-cols-3 gap-5">
        <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
          <p class="text-xs font-bold uppercase text-gray-400">Total Sales (Completed)</p>
          <p class="text-2xl font-bold mt-1"><?= h(cur()) ?><?= number_format($reportTotal, 2) ?></p>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
          <p class="text-xs font-bold uppercase text-gray-400">Completed Orders</p>
          <p class="text-2xl font-bold mt-1"><?= $reportCount ?></p>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
          <p class="text-xs font-bold uppercase text-gray-400">Average per Order</p>
          <p class="text-2xl font-bold mt-1"><?= h(cur()) ?><?= number_format($reportCount ? $reportTotal / $reportCount : 0, 2) ?></p>
        </div>
      </div>

      <div class="grid lg:grid-cols-2 gap-5">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="p-5 border-b border-gray-100">
            <h3 class="font-display text-xl tracking-wide">Sales Per Araw</h3>
          </div>
          <div class="p-5">
            <?php if (empty($reportDaily)): ?>
              <p class="text-sm text-gray-400 text-center py-6">Walang completed sales sa date range na ito.</p>
            <?php else:
              $maxDay = max($reportDaily);
              foreach ($reportDaily as $day => $amt):
                $pct = $maxDay > 0 ? round(($amt / $maxDay) * 100) : 0;
            ?>
              <div class="mb-3">
                <div class="flex justify-between text-xs font-semibold mb-1">
                  <span class="text-gray-500"><?= date('M j, Y', strtotime($day)) ?></span>
                  <span class="text-copper"><?= h(cur()) ?><?= number_format($amt, 2) ?></span>
                </div>
                <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                  <div class="h-full bg-copper rounded-full" style="width: <?= $pct ?>%"></div>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
          <div class="p-5 border-b border-gray-100">
            <h3 class="font-display text-xl tracking-wide">Top 5 Best-Sellers</h3>
          </div>
          <table class="w-full text-left text-sm">
            <thead>
              <tr class="text-[11px] uppercase font-bold text-gray-400 border-b border-gray-100">
                <th class="p-4">Product</th>
                <th class="p-4 text-right">Qty Sold</th>
                <th class="p-4 pr-5 text-right">Revenue</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <?php if (empty($reportTopProducts)): ?>
              <tr><td colspan="3" class="p-6 text-center text-gray-400">Wala pang data.</td></tr>
              <?php else: foreach ($reportTopProducts as $tp): ?>
              <tr>
                <td class="p-4 font-semibold"><?= htmlspecialchars($tp['product_name']) ?></td>
                <td class="p-4 text-right"><?= (int)$tp['total_qty'] ?></td>
                <td class="p-4 pr-5 text-right font-bold text-copper"><?= h(cur()) ?><?= number_format($tp['total_revenue'], 2) ?></td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- TAB 4: SETTINGS -->
    <?php if ($tab === 'settings'):
      $S = settings_all();
      $validSections = ['store', 'ordering', 'payments', 'notifications', 'bot', 'appearance', 'security', 'system'];
      $section = in_array($_GET['section'] ?? '', $validSections, true) ? $_GET['section'] : 'store';
      $flash = $_SESSION['settings_flash'] ?? null;
      unset($_SESSION['settings_flash']);

      $meStmt = $pdo->prepare("SELECT username, prev_login_at FROM admin_users WHERE id = ?");
      $meStmt->execute([$_SESSION['admin_id']]);
      $me = $meStmt->fetch() ?: [];

      $dbOk = true; $dbVersion = '';
      try { $dbVersion = (string)$pdo->query('SELECT VERSION()')->fetchColumn(); } catch (Throwable $e) { $dbOk = false; }
      $dbFlavor = stripos($dbVersion, 'mariadb') !== false ? 'MariaDB' : 'MySQL';
      $upDir = dirname(__DIR__) . '/uploads';
      $uploadsOk = is_dir($upDir) ? is_writable($upDir) : is_writable(dirname(__DIR__));
      $schemaVer = 0;
      try { $schemaVer = (int)$pdo->query("SELECT MAX(version) FROM schema_info")->fetchColumn(); } catch (Throwable $e) { $schemaVer = 0; }
      $schemaOk = $schemaVer === KAPIHAN_SCHEMA_VERSION;
      $missingImgsSys = products_missing_images($pdo);
      $systemOk = $dbOk && $uploadsOk && $schemaOk;

      $sections = [
        'store'         => ['Store Information', 'Name, hours, contact', 'fa-store'],
        'ordering'      => ['Ordering', 'Order types & rules', 'fa-bag-shopping'],
        'payments'      => ['Payments', 'Cash, GCash, Maya', 'fa-wallet'],
        'notifications' => ['Notifications', 'Alerts & sounds', 'fa-bell'],
        'bot'           => ['KAPitan Bot', 'Chatbot & conversations', 'fa-mug-hot'],
        'appearance'    => ['Appearance', 'Logo & accent color', 'fa-palette'],
        'security'      => ['Security', 'Password & sessions', 'fa-shield-halved'],
        'system'        => ['System', 'Status & maintenance', 'fa-server'],
      ];

      function sx_toggle(string $name, string $label, string $desc, bool $on, string $attrs = ''): void { ?>
        <label class="flex items-start justify-between gap-4 py-3.5 border-b border-gray-100 last:border-b-0 cursor-pointer">
          <span class="min-w-0">
            <span class="block font-semibold text-sm text-espresso"><?= h($label) ?></span>
            <?php if ($desc !== ''): ?><span class="block text-xs text-gray-400 mt-0.5 leading-relaxed"><?= h($desc) ?></span><?php endif; ?>
          </span>
          <span class="relative inline-flex flex-shrink-0 mt-0.5">
            <input type="checkbox" name="<?= h($name) ?>" value="1" class="sr-only peer" <?= $on ? 'checked' : '' ?> <?= $attrs ?>>
            <span class="w-11 h-6 rounded-full bg-gray-300 peer-checked:bg-copper transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-copper/40"></span>
            <span class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></span>
          </span>
        </label>
      <?php }

      function sx_field(string $name, string $label, string $value, array $o = []): void {
        $type = $o['type'] ?? 'text'; $req = !empty($o['required']); ?>
        <div class="<?= h($o['class'] ?? '') ?>">
          <label for="<?= h($o['id'] ?? 'f_' . $name) ?>" class="block text-xs font-bold uppercase text-gray-500 mb-1"><?= h($label) ?><?= $req ? ' <span class="text-rose-500">*</span>' : '' ?></label>
          <input id="<?= h($o['id'] ?? 'f_' . $name) ?>" type="<?= h($type) ?>" name="<?= h($name) ?>" value="<?= h($value) ?>"
                 placeholder="<?= h($o['placeholder'] ?? '') ?>" <?= $req ? 'required' : '' ?>
                 <?= isset($o['max']) ? 'maxlength="' . (int)$o['max'] . '"' : '' ?> <?= $o['attrs'] ?? '' ?>
                 class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-copper focus:ring-2 focus:ring-copper/20 transition">
          <?php if (!empty($o['hint'])): ?><p class="text-[11px] text-gray-400 mt-1"><?= h($o['hint']) ?></p><?php endif; ?>
          <p class="sx-err text-xs text-rose-600 mt-1 hidden" data-err="<?= h($name) ?>"></p>
        </div>
      <?php }

      function sx_image(string $label, string $fileField, string $removeField, string $url, string $hint): void {
        static $n = 0; $n++; $id = 'img' . $n; ?>
        <div class="sx-image" data-file="<?= h($fileField) ?>" data-orig-src="<?= h($url) ?>">
          <label for="<?= $id ?>" class="block text-xs font-bold uppercase text-gray-500 mb-1"><?= h($label) ?></label>
          <div class="flex items-center gap-4">
            <div class="w-20 h-20 rounded-2xl border border-dashed border-gray-300 bg-gray-50 grid place-items-center overflow-hidden flex-shrink-0">
              <img class="sx-preview w-full h-full object-contain <?= $url ? '' : 'hidden' ?>" src="<?= h($url) ?>" alt="">
              <i class="sx-placeholder fa-regular fa-image text-gray-300 text-xl <?= $url ? 'hidden' : '' ?>"></i>
            </div>
            <div class="min-w-0 flex-1">
              <input type="file" id="<?= $id ?>" name="<?= h($fileField) ?>" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                     class="sx-file w-full text-xs file:mr-2 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-copper file:text-white file:font-semibold file:cursor-pointer">
              <label class="sx-remove-wrap mt-2 items-center gap-2 text-xs text-gray-500 cursor-pointer <?= $url ? 'inline-flex' : 'hidden' ?>">
                <input type="checkbox" name="<?= h($removeField) ?>" value="1" class="sx-remove rounded border-gray-300"> Remove current image
              </label>
              <p class="text-[11px] text-gray-400 mt-1"><?= h($hint) ?></p>
              <p class="sx-err text-xs text-rose-600 mt-1 hidden" data-err="<?= h($fileField) ?>"></p>
            </div>
          </div>
        </div>
      <?php }

      function sx_head(string $title, string $desc): void { ?>
        <div class="mb-5">
          <h2 class="font-display text-3xl tracking-wide leading-none"><?= h($title) ?></h2>
          <p class="text-sm text-gray-400 mt-1.5"><?= h($desc) ?></p>
        </div>
      <?php }

      function sx_foot(string $label = 'Save Changes'): void { ?>
        <div class="flex items-center justify-end gap-2 pt-5 mt-5 border-t border-gray-100">
          <button type="reset" class="sx-reset px-4 py-2.5 text-sm font-semibold text-gray-500 hover:bg-gray-100 rounded-xl transition-colors disabled:opacity-40 disabled:pointer-events-none" disabled>Reset</button>
          <button type="submit" class="sx-save inline-flex items-center gap-2 bg-copper hover:brightness-90 text-white font-semibold text-sm px-6 py-2.5 rounded-xl transition disabled:opacity-40 disabled:cursor-not-allowed" disabled>
            <i class="sx-spin fa-solid fa-spinner fa-spin hidden"></i><span><?= h($label) ?></span>
          </button>
        </div>
      <?php }

      function sx_form_open(string $section, string $page = ''): void { ?>
        <form method="POST" action="admin.php?tab=settings&section=<?= h($page !== '' ? $page : $section) ?>" enctype="multipart/form-data" class="settings-form" data-section="<?= h($section) ?>" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_settings">
          <input type="hidden" name="section" value="<?= h($section) ?>">
      <?php }
    ?>
    <div class="p-4 sm:p-8 pt-4" data-settings-root>
      <div class="max-w-6xl mx-auto lg:flex lg:gap-8 lg:items-start">

        <!-- Mobile: dropdown -->
        <div class="lg:hidden mb-4">
          <label for="sxSelect" class="block text-xs font-bold uppercase text-gray-500 mb-1">Settings category</label>
          <select id="sxSelect" class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm font-semibold focus:outline-none focus:border-copper">
            <?php foreach ($sections as $key => $info): ?>
              <option value="<?= h($key) ?>" <?= $key === $section ? 'selected' : '' ?>><?= h($info[0]) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Desktop: category navigation -->
        <nav class="hidden lg:block w-64 flex-shrink-0 bg-white rounded-2xl border border-gray-100 shadow-sm p-3 sticky top-4" aria-label="Settings categories">
          <?php foreach ($sections as $key => $info): ?>
            <button type="button" data-sx-nav="<?= h($key) ?>" class="w-full flex items-center gap-3 text-left px-3 py-2.5 rounded-xl text-espresso hover:bg-gray-100 transition-colors">
              <span class="w-8 h-8 rounded-lg bg-black/5 grid place-items-center flex-shrink-0"><i class="fa-solid <?= h($info[2]) ?> text-sm"></i></span>
              <span class="min-w-0">
                <span class="block text-sm font-semibold leading-tight"><?= h($info[0]) ?></span>
                <span class="block text-[11px] opacity-60 leading-tight"><?= h($info[1]) ?></span>
              </span>
            </button>
          <?php endforeach; ?>
        </nav>

        <div class="flex-1 min-w-0">

          <!-- ============ STORE INFORMATION ============ -->
          <section class="sx-panel hidden bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-7" data-panel="store">
            <?php sx_head('Store Information', 'This is what customers see on the storefront, receipts and tracking page.'); sx_form_open('store'); ?>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <?php
                  sx_field('store_name', 'Store Name', $S['store_name'], ['required' => true, 'max' => 60]);
                  sx_field('tagline', 'Tagline', $S['tagline'], ['max' => 120]);
                  sx_field('address', 'Address', $S['address'], ['max' => 200, 'class' => 'sm:col-span-2']);
                ?>
                <div class="sm:col-span-2">
                  <label for="f_store_description" class="block text-xs font-bold uppercase text-gray-500 mb-1">Store Description</label>
                  <textarea id="f_store_description" name="store_description" rows="2" maxlength="300" placeholder="A short introduction shown in the storefront footer." class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-copper focus:ring-2 focus:ring-copper/20 transition"><?= h($S['store_description']) ?></textarea>
                  <p class="sx-err text-xs text-rose-600 mt-1 hidden" data-err="store_description"></p>
                </div>
                <?php
                  sx_field('contact_number', 'Contact Number', $S['contact_number'], ['type' => 'tel', 'max' => 30, 'placeholder' => '0917 123 4567']);
                  sx_field('email', 'Email', $S['email'], ['type' => 'email', 'max' => 120, 'placeholder' => 'hello@kapihan.ph']);
                  sx_field('opening_time', 'Opening Time', $S['opening_time'], ['type' => 'time', 'required' => true]);
                  sx_field('closing_time', 'Closing Time', $S['closing_time'], ['type' => 'time', 'required' => true]);
                ?>
              </div>

              <div class="mt-5">
                <p class="block text-xs font-bold uppercase text-gray-500 mb-2">Store Status</p>
                <div class="grid grid-cols-2 gap-3 max-w-md">
                  <label class="cursor-pointer">
                    <input type="radio" name="store_status" value="open" class="sr-only peer" <?= $S['store_status'] !== 'closed' ? 'checked' : '' ?>>
                    <span class="flex items-center gap-2 px-4 py-3 rounded-xl border border-gray-200 text-sm font-semibold peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-checked:text-emerald-700 peer-focus-visible:ring-2 peer-focus-visible:ring-copper/40 transition">
                      <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Open
                    </span>
                  </label>
                  <label class="cursor-pointer">
                    <input type="radio" name="store_status" value="closed" class="sr-only peer" <?= $S['store_status'] === 'closed' ? 'checked' : '' ?>>
                    <span class="flex items-center gap-2 px-4 py-3 rounded-xl border border-gray-200 text-sm font-semibold peer-checked:border-rose-500 peer-checked:bg-rose-50 peer-checked:text-rose-700 peer-focus-visible:ring-2 peer-focus-visible:ring-copper/40 transition">
                      <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span> Closed
                    </span>
                  </label>
                </div>
                <p class="text-[11px] text-gray-400 mt-2">While closed, the storefront stays visible but customers cannot place orders. Opening and closing times are shown to customers.</p>
              </div>

              <div class="mt-6">
                <?php sx_image('Store Logo', 'store_logo_file', 'remove_store_logo', asset_url($S['store_logo'], '../'), 'JPG, PNG or WEBP, up to 2MB. Shown in the storefront header and admin panel.'); ?>
              </div>
              <?php sx_foot(); ?>
            </form>
          </section>

          <!-- ============ ORDERING ============ -->
          <section class="sx-panel hidden bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-7" data-panel="ordering">
            <?php sx_head('Ordering', 'Control how customers can place orders on the storefront.'); sx_form_open('ordering'); ?>
              <div>
                <?php
                  sx_toggle('online_ordering', 'Online Ordering', 'Master switch. When off, the storefront shows that ordering is temporarily unavailable.', $S['online_ordering'] === '1');
                  sx_toggle('dine_in', 'Dine-In', 'Customers can choose Dine-In at checkout.', $S['dine_in'] === '1');
                  sx_toggle('take_out', 'Take-Out', 'Customers can choose Take-Out at checkout.', $S['take_out'] === '1');
                  sx_toggle('pick_up', 'Pick-Up', 'Customers order ahead and pick up at the counter.', $S['pick_up'] === '1');
                  sx_toggle('allow_notes', 'Customer Notes', 'Adds a notes box at checkout (e.g. “less sugar”). Notes appear on the Kitchen View.', $S['allow_notes'] === '1');
                ?>
                <p class="sx-err text-xs text-rose-600 mt-2 hidden" data-err="dine_in"></p>
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-5">
                <?php
                  sx_field('min_order_amount', 'Minimum Order Amount (' . cur() . ')', $S['min_order_amount'], ['type' => 'number', 'attrs' => 'min="0" max="100000" step="0.01" inputmode="decimal"', 'hint' => 'Set 0 for no minimum.']);
                  sx_field('prep_time', 'Default Preparation Time (minutes)', $S['prep_time'], ['type' => 'number', 'attrs' => 'min="1" max="180" step="1" inputmode="numeric"', 'hint' => 'Shown to customers after they order.']);
                ?>
              </div>
              <div class="mt-5">
                <label for="f_order_rules" class="block text-xs font-bold uppercase text-gray-500 mb-1">Order Rules</label>
                <textarea id="f_order_rules" name="order_rules" rows="2" maxlength="300" placeholder="e.g. Orders can't be cancelled once preparation starts." class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-copper focus:ring-2 focus:ring-copper/20 transition"><?= h($S['order_rules']) ?></textarea>
                <p class="text-[11px] text-gray-400 mt-1">Shown to customers at checkout, just above the Place Order button.</p>
                <p class="sx-err text-xs text-rose-600 mt-1 hidden" data-err="order_rules"></p>
              </div>
              <p class="text-xs text-gray-400 mt-4"><i class="fa-solid fa-circle-info mr-1"></i>Guest checkout is always on — KAPIHAN doesn’t use customer accounts, so customers only enter a name.</p>
              <?php sx_foot(); ?>
            </form>
          </section>

          <!-- ============ PAYMENTS ============ -->
          <section class="sx-panel hidden bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-7" data-panel="payments">
            <?php sx_head('Payments', 'Choose which payment methods appear at checkout. GCash and Maya details are shown to the customer after they pick them.'); sx_form_open('payments'); ?>
              <?php sx_toggle('pay_cash', 'Cash', 'Pay at the counter.', $S['pay_cash'] === '1'); ?>
              <p class="sx-err text-xs text-rose-600 mt-1 hidden" data-err="pay_cash"></p>

              <?php foreach (['gcash' => 'GCash', 'maya' => 'Maya'] as $m => $label): ?>
                <div class="mt-3 border border-gray-100 rounded-2xl px-4 pb-4 bg-gray-50/50">
                  <?php sx_toggle('pay_' . $m, $label, 'Let customers pay with ' . $label . '.', $S['pay_' . $m] === '1', 'data-method-toggle="' . $m . '"'); ?>
                  <div id="method_<?= $m ?>" class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-3 transition-opacity">
                    <?php
                      sx_field($m . '_name', 'Account Name', $S[$m . '_name'], ['max' => 60]);
                      sx_field($m . '_number', 'Account Number', $S[$m . '_number'], ['type' => 'tel', 'max' => 30, 'placeholder' => '0917 123 4567']);
                    ?>
                    <div class="sm:col-span-2">
                      <?php sx_image($label . ' QR Code', $m . '_qr_file', 'remove_' . $m . '_qr', asset_url($S[$m . '_qr'], '../'), 'JPG, PNG or WEBP, up to 2MB. Customers can scan this to pay.'); ?>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
              <div class="mt-5">
                <label for="f_pay_instructions" class="block text-xs font-bold uppercase text-gray-500 mb-1">Payment Instructions</label>
                <textarea id="f_pay_instructions" name="pay_instructions" rows="2" maxlength="300" placeholder="e.g. Send the exact amount, then show the payment confirmation at the counter." class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-copper focus:ring-2 focus:ring-copper/20 transition"><?= h($S['pay_instructions']) ?></textarea>
                <p class="text-[11px] text-gray-400 mt-1">Shown with the GCash and Maya QR code. KAPIHAN never asks customers for PINs, OTPs or passwords.</p>
                <p class="sx-err text-xs text-rose-600 mt-1 hidden" data-err="pay_instructions"></p>
              </div>
              <?php sx_foot(); ?>
            </form>
          </section>

          <!-- ============ NOTIFICATIONS ============ -->
          <section class="sx-panel hidden bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-7" data-panel="notifications">
            <?php sx_head('Notifications', 'Decide which alerts staff and customers get.'); sx_form_open('notifications'); ?>
              <?php
                sx_toggle('notify_new_order', 'New Order Notification', 'Shows an alert on the Kitchen View and admin pages when a new order arrives.', $S['notify_new_order'] === '1');
                sx_toggle('notify_status', 'Order Status Notification', 'Customers can get a browser notification on their tracking page when the order status changes.', $S['notify_status'] === '1');
                sx_toggle('notify_sound', 'Sound Notification', 'Plays a short beep for new orders (staff screens) and status changes (customer tracking). Browsers need one click on the page first.', $S['notify_sound'] === '1');
                sx_toggle('notify_low_stock', 'Low Stock / Unavailable Products', 'Shows a banner in the admin panel listing products that are low or out of stock.', $S['notify_low_stock'] === '1');
              ?>
              <div class="max-w-xs mt-4">
                <?php sx_field('low_stock_threshold', 'Low-stock threshold', $S['low_stock_threshold'], ['type' => 'number', 'attrs' => 'min="1" max="100" step="1" inputmode="numeric"', 'hint' => 'Products with this many left (or fewer) are flagged.']); ?>
              </div>
              <?php sx_foot(); ?>
            </form>
          </section>

          <!-- ============ KAPITAN BOT ============ -->
          <section class="sx-panel hidden space-y-6" data-panel="bot">
            <?php
              $kConv = (int)$pdo->query("SELECT COUNT(*) FROM kapitan_conversations")->fetchColumn();
              $kMsgs = (int)$pdo->query("SELECT COUNT(*) FROM kapitan_messages")->fetchColumn();
              $faqRows = $pdo->query("SELECT id, question, keywords, answer, is_active FROM kapitan_faqs ORDER BY sort_order ASC, id ASC LIMIT 100")->fetchAll();
            ?>
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-7">
              <?php sx_head('KAPitan Bot', 'Kasama mo sa bawat kape. KAPIHAN’s own chat assistant — it answers from your menu, prices, store info and the knowledge you write below.'); sx_form_open('bot'); ?>
                <?php sx_toggle('bot_enabled', 'Enable KAPitan Bot', 'When off, the chat button is hidden from the storefront.', $S['bot_enabled'] === '1'); ?>
                <div class="mt-4">
                  <label for="f_bot_welcome" class="block text-xs font-bold uppercase text-gray-500 mb-1">Welcome Message</label>
                  <textarea id="f_bot_welcome" name="bot_welcome" rows="3" maxlength="300" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-copper focus:ring-2 focus:ring-copper/20 transition"><?= h($S['bot_welcome']) ?></textarea>
                  <p class="sx-err text-xs text-rose-600 mt-1 hidden" data-err="bot_welcome"></p>
                </div>
                <p class="text-xs font-bold uppercase text-gray-400 mt-6 mb-3">Bot Personality</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                  <?php sx_field('bot_name', 'Bot Name', $S['bot_name'], ['required' => true, 'max' => 30]); ?>
                  <div>
                    <label for="f_bot_tone" class="block text-xs font-bold uppercase text-gray-500 mb-1">Tone</label>
                    <select id="f_bot_tone" name="bot_tone" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-copper focus:ring-2 focus:ring-copper/20">
                      <?php foreach (bot_tones() as $key => $label): ?><option value="<?= h($key) ?>" <?= $S['bot_tone'] === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
                    </select>
                    <p class="sx-err text-xs text-rose-600 mt-1 hidden" data-err="bot_tone"></p>
                  </div>
                  <div>
                    <label for="f_bot_language" class="block text-xs font-bold uppercase text-gray-500 mb-1">Language</label>
                    <select id="f_bot_language" name="bot_language" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-copper focus:ring-2 focus:ring-copper/20">
                      <?php foreach (bot_languages() as $key => $label): ?><option value="<?= h($key) ?>" <?= $S['bot_language'] === $key ? 'selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
                    </select>
                    <p class="sx-err text-xs text-rose-600 mt-1 hidden" data-err="bot_language"></p>
                  </div>
                </div>
                <p class="text-[11px] text-gray-400 mt-3">Menu items, prices, availability, store hours, address and payment methods always come live from your KAPIHAN data, so price changes in Product Management show up in the chat right away.</p>
                <?php sx_foot(); ?>
              </form>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-7">
              <div class="mb-4"><h3 class="font-display text-2xl tracking-wide leading-none">Store Knowledge</h3>
                <p class="text-sm text-gray-400 mt-1.5">Extra facts for the bot — policies, promos, house rules. Write one fact per line. If it isn’t written here or in your menu, the bot says it isn’t sure and points customers to staff.</p></div>
              <?php sx_form_open('bot_knowledge'); ?>
                <textarea id="f_bot_knowledge" name="bot_knowledge" rows="6" maxlength="2500" placeholder="Senior citizens and PWD get 10% off.&#10;Outside food is not allowed.&#10;We have free WiFi." class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-copper focus:ring-2 focus:ring-copper/20 transition"><?= h($S['bot_knowledge']) ?></textarea>
                <p class="sx-err text-xs text-rose-600 mt-1 hidden" data-err="bot_knowledge"></p>
                <?php sx_foot(); ?>
              </form>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-7">
              <div class="mb-4"><h3 class="font-display text-2xl tracking-wide leading-none">Frequently Asked Questions</h3>
                <p class="text-sm text-gray-400 mt-1.5">Exact answers for questions you want handled your way. Add keywords (comma-separated) to catch different ways customers ask.</p></div>

              <details class="group border border-dashed border-gray-300 rounded-2xl mb-3" <?= $faqRows ? '' : 'open' ?>>
                <summary class="cursor-pointer select-none px-4 py-3 font-semibold text-sm flex items-center gap-2"><i class="fa-solid fa-plus text-copper"></i> Add a new FAQ</summary>
                <div class="px-4 pb-4">
                  <?php sx_form_open('faq_save', 'bot'); ?>
                    <input type="hidden" name="id" value="0">
                    <div class="grid grid-cols-1 gap-3">
                      <?php sx_field('faq_question', 'Question', '', ['required' => true, 'max' => 150, 'id' => 'faq_new_question', 'placeholder' => 'Do you have WiFi?']); ?>
                      <?php sx_field('faq_keywords', 'Keywords (optional)', '', ['max' => 150, 'id' => 'faq_new_keywords', 'placeholder' => 'wifi, internet']); ?>
                      <div>
                        <label for="faq_new_answer" class="block text-xs font-bold uppercase text-gray-500 mb-1">Answer <span class="text-rose-500">*</span></label>
                        <textarea id="faq_new_answer" name="faq_answer" rows="3" maxlength="600" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-copper focus:ring-2 focus:ring-copper/20 transition"></textarea>
                        <p class="sx-err text-xs text-rose-600 mt-1 hidden" data-err="faq_answer"></p>
                      </div>
                      <?php sx_toggle('faq_active', 'Active', 'Inactive FAQs are ignored by the bot.', true); ?>
                    </div>
                    <?php sx_foot('Add FAQ'); ?>
                  </form>
                </div>
              </details>

              <?php foreach ($faqRows as $f): $fid = (int)$f['id']; ?>
                <details class="border border-gray-100 rounded-2xl mb-3 bg-gray-50/40">
                  <summary class="cursor-pointer select-none px-4 py-3 text-sm flex items-center gap-3">
                    <span class="font-semibold min-w-0 flex-1 truncate"><?= h($f['question']) ?></span>
                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full <?= $f['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-500' ?>"><?= $f['is_active'] ? 'Active' : 'Off' ?></span>
                  </summary>
                  <div class="px-4 pb-4">
                    <?php sx_form_open('faq_save', 'bot'); ?>
                      <input type="hidden" name="id" value="<?= $fid ?>">
                      <div class="grid grid-cols-1 gap-3">
                        <?php
                          sx_field('faq_question', 'Question', (string)$f['question'], ['required' => true, 'max' => 150, 'id' => 'faq' . $fid . '_q']);
                          sx_field('faq_keywords', 'Keywords (optional)', (string)($f['keywords'] ?? ''), ['max' => 150, 'id' => 'faq' . $fid . '_k']);
                        ?>
                        <div>
                          <label for="faq<?= $fid ?>_a" class="block text-xs font-bold uppercase text-gray-500 mb-1">Answer <span class="text-rose-500">*</span></label>
                          <textarea id="faq<?= $fid ?>_a" name="faq_answer" rows="3" maxlength="600" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-copper focus:ring-2 focus:ring-copper/20 transition"><?= h($f['answer']) ?></textarea>
                          <p class="sx-err text-xs text-rose-600 mt-1 hidden" data-err="faq_answer"></p>
                        </div>
                        <?php sx_toggle('faq_active', 'Active', 'Inactive FAQs are ignored by the bot.', (bool)$f['is_active']); ?>
                      </div>
                      <div class="flex items-center justify-between gap-2 pt-5 mt-5 border-t border-gray-100">
                        <button type="button" data-post-section="faq_delete" data-post-id="<?= $fid ?>" data-confirm-title="Delete this FAQ?" data-confirm-text="The bot will stop using this answer." data-confirm-label="Delete" data-confirm-danger="1"
                                class="px-4 py-2.5 text-sm font-semibold text-rose-600 hover:bg-rose-50 rounded-xl transition-colors"><i class="fa-regular fa-trash-can mr-1"></i> Delete</button>
                        <div class="flex items-center gap-2">
                          <button type="reset" class="sx-reset px-4 py-2.5 text-sm font-semibold text-gray-500 hover:bg-gray-100 rounded-xl transition-colors disabled:opacity-40 disabled:pointer-events-none" disabled>Reset</button>
                          <button type="submit" class="sx-save inline-flex items-center gap-2 bg-copper hover:brightness-90 text-white font-semibold text-sm px-6 py-2.5 rounded-xl transition disabled:opacity-40 disabled:cursor-not-allowed" disabled>
                            <i class="sx-spin fa-solid fa-spinner fa-spin hidden"></i><span>Save Changes</span></button>
                        </div>
                      </div>
                    </form>
                  </div>
                </details>
              <?php endforeach; ?>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-7">
              <div class="mb-4"><h3 class="font-display text-2xl tracking-wide leading-none">Privacy &amp; Conversation History</h3>
                <p class="text-sm text-gray-400 mt-1.5">Chats are saved so you can review customer questions. The bot never stores passwords, PINs, OTPs or card numbers, and emails and phone numbers are masked before saving.</p></div>
              <?php sx_form_open('bot_privacy'); ?>
                <?php sx_toggle('bot_privacy_enabled', 'Show privacy notice in the chat', 'A short note under the message box.', $S['bot_privacy_enabled'] === '1'); ?>
                <div class="mt-3">
                  <label for="f_bot_privacy_text" class="block text-xs font-bold uppercase text-gray-500 mb-1">Privacy Notice</label>
                  <textarea id="f_bot_privacy_text" name="bot_privacy_text" rows="2" maxlength="200" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-copper focus:ring-2 focus:ring-copper/20 transition"><?= h($S['bot_privacy_text']) ?></textarea>
                  <p class="sx-err text-xs text-rose-600 mt-1 hidden" data-err="bot_privacy_text"></p>
                </div>
                <div class="max-w-xs mt-4">
                  <?php sx_field('bot_retention_days', 'Keep conversations for (days)', $S['bot_retention_days'], ['type' => 'number', 'attrs' => 'min="0" max="3650" step="1" inputmode="numeric"', 'hint' => 'Older conversations are deleted automatically. Use 0 to keep them until you delete them.']); ?>
                </div>
                <?php sx_foot(); ?>
              </form>

              <div class="mt-6 rounded-2xl border border-gray-100 p-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-gray-500 min-w-0 flex-1"><strong class="text-espresso"><?= number_format($kConv) ?></strong> conversations · <strong class="text-espresso"><?= number_format($kMsgs) ?></strong> messages saved</p>
                <div class="flex flex-wrap gap-2">
                  <a href="admin.php?tab=kapitan" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold border border-gray-200 hover:bg-gray-50 rounded-xl transition-colors"><i class="fa-solid fa-comments"></i> View conversations</a>
                  <button type="button" data-post-section="kapitan_clear_all" data-confirm-title="Clear all conversation history?" data-confirm-text="Every saved KAPitan Bot conversation will be permanently deleted. This cannot be undone." data-confirm-label="Delete all" data-confirm-danger="1"
                          class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-rose-600 border border-rose-200 hover:bg-rose-50 rounded-xl transition-colors"><i class="fa-regular fa-trash-can"></i> Clear history</button>
                </div>
              </div>
            </div>
          </section>

          <!-- ============ APPEARANCE ============ -->
          <section class="sx-panel hidden bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-7" data-panel="appearance">
            <?php sx_head('Appearance', 'Light touches that keep the KAPIHAN look. Changes apply to the storefront, tracking page, receipts and admin.'); sx_form_open('appearance'); ?>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <?php sx_image('Store Logo', 'store_logo_file', 'remove_store_logo', asset_url($S['store_logo'], '../'), 'JPG, PNG or WEBP, up to 2MB. A square image works best.'); ?>
                <?php sx_image('Favicon', 'favicon_file', 'remove_favicon', asset_url($S['favicon'], '../'), 'The small icon in the browser tab. Square PNG, 64×64 or larger.'); ?>
              </div>

              <div class="mt-6">
                <label for="f_accent_color" class="block text-xs font-bold uppercase text-gray-500 mb-2">Accent Color</label>
                <div class="flex flex-wrap items-center gap-2">
                  <?php foreach (['#B25F2C' => 'Caramel', '#8B4A2B' => 'Mocha', '#9C3D2E' => 'Cinnamon', '#48605A' => 'Matcha', '#6B4E9B' => 'Ube', '#2F5D8A' => 'Blueberry'] as $hex => $nm): ?>
                    <button type="button" class="sx-swatch w-9 h-9 rounded-full border-2 border-white ring-1 ring-gray-200 transition-transform hover:scale-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-espresso" style="background: <?= h($hex) ?>" data-color="<?= h($hex) ?>" title="<?= h($nm) ?>" aria-label="<?= h($nm) ?>"></button>
                  <?php endforeach; ?>
                  <span class="mx-1 text-gray-300">|</span>
                  <input type="color" id="accentPicker" value="<?= h(accent()) ?>" class="w-10 h-10 rounded-lg border border-gray-200 bg-white p-0.5 cursor-pointer" aria-label="Pick a custom color">
                  <input type="text" id="f_accent_color" name="accent_color" value="<?= h(accent()) ?>" maxlength="7" class="w-28 bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono uppercase focus:outline-none focus:border-copper">
                  <span id="accentPreview" class="inline-flex items-center px-4 py-2 rounded-full text-white text-xs font-bold" style="background: <?= h(accent()) ?>">Place Order</span>
                </div>
                <p class="text-[11px] text-gray-400 mt-1.5">Used for buttons, highlights and links. Very light colors are rejected so text stays readable.</p>
                <p class="sx-err text-xs text-rose-600 mt-1 hidden" data-err="accent_color"></p>
              </div>

              <div class="mt-6 max-w-sm">
                <p class="block text-xs font-bold uppercase text-gray-500 mb-1">Currency</p>
                <div class="flex items-center gap-3 bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-sm">
                  <span class="font-bold text-copper text-lg leading-none">₱</span>
                  <span class="font-semibold flex-1">PHP — Philippine Peso</span>
                  <i class="fa-solid fa-lock text-gray-300 text-xs" title="Fixed"></i>
                </div>
                <p class="text-[11px] text-gray-400 mt-1">KAPIHAN operates in the Philippines, so every price is shown in Philippine Peso (₱). This is fixed.</p>
              </div>
              <?php sx_foot(); ?>
            </form>
          </section>

          <!-- ============ SECURITY ============ -->
          <section class="sx-panel hidden space-y-6" data-panel="security">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-7">
              <?php sx_head('Security', 'Keep your admin account safe.'); ?>
              <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div class="rounded-xl bg-gray-50 p-4"><dt class="text-[11px] font-bold uppercase text-gray-400">Signed in as</dt><dd class="font-semibold mt-1"><?= h($me['username'] ?? '') ?></dd></div>
                <div class="rounded-xl bg-gray-50 p-4"><dt class="text-[11px] font-bold uppercase text-gray-400">Last login</dt>
                  <dd class="font-semibold mt-1"><?= !empty($me['prev_login_at']) ? h(date('M j, Y · g:i A', strtotime($me['prev_login_at']))) : 'First login on record' ?></dd></div>
                <div class="rounded-xl bg-gray-50 p-4"><dt class="text-[11px] font-bold uppercase text-gray-400">Current session</dt>
                  <dd class="font-semibold mt-1"><?= !empty($_SESSION['login_at']) ? 'Started ' . h(date('M j · g:i A', (int)$_SESSION['login_at'])) : 'Active' ?></dd></div>
              </dl>
              <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-100 p-4">
                <p class="text-sm text-gray-500 min-w-0 flex-1">Signed in on another phone or computer? Sign out everywhere else. You stay signed in here.</p>
                <button type="button" data-post-section="sessions" data-confirm-title="Sign out other sessions?" data-confirm-text="Any other device signed in to this admin account will be signed out immediately." data-confirm-label="Sign them out"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold border border-gray-200 hover:bg-gray-50 rounded-xl transition-colors">
                  <i class="fa-solid fa-right-from-bracket"></i> Logout other sessions
                </button>
              </div>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-7">
              <div class="mb-5"><h3 class="font-display text-2xl tracking-wide leading-none">Change Password</h3>
                <p class="text-sm text-gray-400 mt-1.5">Use at least 8 characters. Changing it signs out your other devices.</p></div>
              <?php sx_form_open('password'); ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
                  <?php
                    sx_field('current_password', 'Current Password', '', ['type' => 'password', 'required' => true, 'class' => 'sm:col-span-2', 'attrs' => 'autocomplete="current-password"']);
                    sx_field('new_password', 'New Password', '', ['type' => 'password', 'required' => true, 'attrs' => 'autocomplete="new-password" minlength="8"']);
                    sx_field('confirm_password', 'Confirm New Password', '', ['type' => 'password', 'required' => true, 'attrs' => 'autocomplete="new-password" minlength="8"']);
                  ?>
                </div>
                <?php sx_foot('Update Password'); ?>
              </form>
            </div>
          </section>

          <!-- ============ SYSTEM ============ -->
          <section class="sx-panel hidden space-y-6" data-panel="system">
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-7">
              <?php sx_head('System', 'A quick health check of your KAPIHAN installation.'); ?>
              <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <?php
                  $rows = [
                    ['System', $systemOk ? 'Operational' : 'Needs attention', $systemOk],
                    ['Database', $dbOk ? 'Connected' : 'Not connected', $dbOk],
                    ['Database server', $dbVersion !== '' ? $dbFlavor . ' ' . $dbVersion : '—', null],
                    ['Database structure', $schemaOk ? 'Up to date (v' . $schemaVer . ')' : 'Update needed (v' . $schemaVer . ' of v' . KAPIHAN_SCHEMA_VERSION . ') — check the server error log', $schemaOk],
                    ['PHP', PHP_VERSION, null],
                    ['Application', 'KAPIHAN v' . KAPIHAN_VERSION, null],
                    ['Product images', $missingImgsSys ? count($missingImgsSys) . ' missing file(s): ' . implode(', ', array_slice($missingImgsSys, 0, 5)) : 'All image files found', !$missingImgsSys],
                    ['Uploads folder', $uploadsOk ? 'Writable' : 'Not writable — logo and QR uploads will fail', $uploadsOk],
                    ['Server time', date('M j, Y · g:i A') . ' (' . date_default_timezone_get() . ')', null],
                    ['KAPitan Bot', setting_on('bot_enabled') ? 'Enabled' : 'Disabled', null],
                  ];
                  foreach ($rows as $r): ?>
                  <div class="rounded-xl bg-gray-50 p-4">
                    <dt class="text-[11px] font-bold uppercase text-gray-400"><?= h($r[0]) ?></dt>
                    <dd class="font-semibold mt-1 flex items-center gap-2">
                      <?php if ($r[2] !== null): ?><span class="w-2 h-2 rounded-full flex-shrink-0 <?= $r[2] ? 'bg-emerald-500' : 'bg-rose-500' ?>"></span><?php endif; ?>
                      <span class="min-w-0 break-words"><?= h($r[1]) ?></span>
                    </dd>
                  </div>
                <?php endforeach; ?>
              </dl>
            </div>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-7">
              <div class="mb-3"><h3 class="font-display text-2xl tracking-wide leading-none">Maintenance Mode</h3>
                <p class="text-sm text-gray-400 mt-1.5">Closes the storefront for the public while you make changes. You (signed in as admin) can still open the admin panel and preview the storefront.</p></div>
              <?php sx_form_open('system'); ?>
                <?php sx_toggle('maintenance_mode', 'Enable Maintenance Mode', 'Customers will see a “be right back” page and cannot order.', $S['maintenance_mode'] === '1'); ?>
                <?php sx_foot(); ?>
              </form>
            </div>
          </section>

        </div><!-- /content -->
      </div>
    </div>

    <?php if ($flash): ?><script>window.__settingsFlash = <?= json_encode($flash, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script><?php endif; ?>
    <script>window.__settingsSection = <?= json_encode($section) ?>;</script>
    <?php endif; ?>

  </main>

  <!-- EDIT PRODUCT MODAL -->
  <div id="editModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center p-4 z-50">
    <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
      <div class="flex items-center justify-between border-b pb-3">
        <h3 class="font-display text-2xl">Edit Product</h3>
        <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark text-lg"></i></button>
      </div>

      <form method="POST" enctype="multipart/form-data" class="space-y-4">
<?= csrf_field() ?>
        <input type="hidden" name="action" value="edit_product">
        <input type="hidden" name="id" id="edit_id">

        <div>
          <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Pangalan ng Product</label>
          <input type="text" name="name" id="edit_name" required class="w-full bg-gray-50 border rounded-xl px-4 py-2 text-sm">
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Presyo (<?= h(cur()) ?>)</label>
            <input type="number" step="0.01" name="price" id="edit_price" required class="w-full bg-gray-50 border rounded-xl px-4 py-2 text-sm">
          </div>
          <div>
            <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Category</label>
            <select name="category" id="edit_category" class="w-full bg-gray-50 border rounded-xl px-4 py-2 text-sm">
              <option value="coffee">Hot Coffee</option>
              <option value="cold">Iced Drinks</option>
              <option value="special">Non-Coffee</option>
              <option value="merienda">Merienda</option>
            </select>
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Stock Quantity <span class="font-normal normal-case text-gray-400">(blangko = walang limitasyon)</span></label>
          <input type="number" min="0" step="1" name="stock_qty" id="edit_stock_qty" class="w-full bg-gray-50 border rounded-xl px-4 py-2 text-sm">
        </div>

        <div class="flex items-center gap-4">
          <img id="edit_preview" src="" alt="" class="w-16 h-16 rounded-full object-cover border border-gray-200 bg-white flex-shrink-0">
          <div class="text-xs text-gray-500 min-w-0">
            <p class="font-semibold text-espresso text-sm">Current image</p>
            <p>It stays as is unless you upload a new one or paste a new URL.</p>
            <label class="mt-1 inline-flex items-center gap-2 cursor-pointer"><input type="checkbox" name="remove_image" id="edit_remove_image" value="1" class="rounded border-gray-300"> Remove the image (use the placeholder)</label>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-bold uppercase text-gray-500 mb-1">New image URL <span class="font-normal normal-case text-gray-400">(optional)</span></label>
            <input type="url" name="image" id="edit_image" placeholder="https://..." class="w-full bg-gray-50 border rounded-xl px-4 py-2 text-sm">
          </div>
          <div>
            <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Or upload a new image</label>
            <input type="file" name="image_upload" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" class="w-full bg-gray-50 border rounded-xl px-2 py-1.5 text-xs file:mr-2 file:px-2.5 file:py-1 file:rounded-lg file:border-0 file:bg-copper file:text-white file:font-semibold file:cursor-pointer">
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold uppercase text-gray-500 mb-1">Description / Note</label>
          <input type="text" name="description" id="edit_description" class="w-full bg-gray-50 border rounded-xl px-4 py-2 text-sm">
        </div>

        <label class="inline-flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
          <input type="checkbox" name="is_active" id="edit_is_active" value="1" class="rounded border-gray-300"> Show on the storefront
        </label>

        <div class="flex justify-end gap-2 pt-2">
          <button type="button" onclick="closeEditModal()" class="px-4 py-2 text-xs font-semibold text-gray-500 hover:bg-gray-100 rounded-xl">Cancel</button>
          <button type="submit" class="px-5 py-2 text-xs font-semibold bg-copper text-white rounded-xl">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

<script src="../assets/kapihan-ui.js"></script>
<?php $__af = $_SESSION['admin_flash'] ?? null; unset($_SESSION['admin_flash']); if ($__af): ?>
<script>window.addEventListener('load', function () { showToast(<?= json_encode(['title' => $__af['title'] ?? '', 'message' => $__af['message'] ?? '', 'type' => $__af['type'] ?? 'info'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>); });</script>
<?php endif; ?>
<?php if ($tab === 'settings'): ?><script src="../assets/admin-settings.js"></script><?php endif; ?>
<script>
/* mobile sidebar */
(function () {
  var sb = document.getElementById('adminSidebar'), ov = document.getElementById('sidebarOverlay'), tg = document.getElementById('sidebarToggle');
  function setOpen(o) { sb.classList.toggle('-translate-x-full', !o); ov.classList.toggle('hidden', !o); }
  if (tg) tg.addEventListener('click', function () { setOpen(sb.classList.contains('-translate-x-full')); });
  if (ov) ov.addEventListener('click', function () { setOpen(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setOpen(false); });
})();

/* new-order alerts (follows Settings → Notifications) */
(function () {
  var lastId = null;
  function poll() {
    fetch('kitchen_data.php', { cache: 'no-store' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
      if (!d || !d.success) return;
      if (lastId === null) { lastId = d.latest_id; return; }
      if (d.latest_id > lastId) {
        var fresh = d.board.pending.filter(function (o) { return o.id > lastId; });
        lastId = d.latest_id;
        if (d.notify && d.notify.new_order && fresh.length) {
          showToast({ title: fresh.length > 1 ? fresh.length + ' new orders!' : 'New order!', message: fresh.map(function (o) { return '#ORD-' + o.id + ' — ' + o.customer_name; }).join(', '),
                      type: 'success', actionLabel: 'View Orders', onAction: function () { window.location.href = 'admin.php?tab=orders'; } });
          if (d.notify.sound) kBeep();
        }
      }
    }).catch(function () {});
  }
  poll(); setInterval(function () { if (!document.hidden) poll(); }, 10000);
})();
</script>
<script>
const PRODUCT_PLACEHOLDER = <?= json_encode(product_placeholder()) ?>;

function openEditModal(prod) {
  document.getElementById('edit_id').value = prod.id;
  document.getElementById('edit_name').value = prod.name;
  document.getElementById('edit_price').value = prod.price;
  document.getElementById('edit_category').value = prod.category;
  document.getElementById('edit_stock_qty').value = prod.stock_qty === null ? '' : prod.stock_qty;
  // ang URL field ay para lang sa external URL; ang lokal na upload ay nananatili sa database
  document.getElementById('edit_image').value = /^https?:\/\//i.test(prod.image || '') ? prod.image : '';
  document.getElementById('edit_remove_image').checked = false;
  document.getElementById('edit_is_active').checked = String(prod.is_active) !== '0';
  const prev = document.getElementById('edit_preview');
  prev.onerror = function () { prev.onerror = null; prev.src = PRODUCT_PLACEHOLDER; };
  prev.src = prod.image_url || PRODUCT_PLACEHOLDER;
  document.getElementById('edit_description').value = prod.description || '';
  
  const modal = document.getElementById('editModal');
  modal.classList.remove('hidden');
  modal.classList.add('flex');
}

function closeEditModal() {
  const modal = document.getElementById('editModal');
  modal.classList.add('hidden');
  modal.classList.remove('flex');
}

function filterTable(inputId, tableId) {
  const q = document.getElementById(inputId).value.trim().toLowerCase();
  const rows = document.getElementById(tableId).querySelectorAll('tbody tr');
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(q) ? '' : 'none';
  });
}
</script>

</body>
</html>