<?php
// I-copy ang file na ito bilang "config.php" at ilagay ang totoong detalye ng hosting mo.
// HUWAG i-upload sa public repo ang config.php (nasa .gitignore na).
return [
    'db_host' => 'localhost',          // madalas "localhost" — tingnan sa hosting panel
    'db_port' => 3306,
    'db_name' => 'kapihan',            // pangalan ng database na ginawa mo sa panel
    'db_user' => 'kapihan_user',
    'db_pass' => 'PALITAN_ITO',

    // true = susubukang gumawa ng database kapag wala pa (XAMPP/local). Sa shared hosting, gawing false.
    'auto_create_db' => false,

    // password ng unang admin account (username: admin). PALITAN bago ang unang bukas ng site.
    'admin_initial_password' => 'PALITAN-ITO-NG-MALAKAS-NA-PASSWORD',

    // Oras ng store (para tama ang oras ng orders at reports). Default: Pilipinas.
    'timezone' => 'Asia/Manila',


    // Awtomatikong mag-logout ang admin kapag walang aktibidad (minuto). Default: 480 (8 oras).
    'admin_idle_minutes' => 480,

    // Para sa "Forgot password?" sa admin login. Iwanang blangko = naka-off. Gawing mahabang sikreto (12+ characters)
    // kapag kailangan mong mag-reset, tapos tanggalin ulit pagkatapos.
    'admin_reset_key' => '',

    // true lang habang nagde-debug. Sa live site, laging false.
    'debug' => false,
];
