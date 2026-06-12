<?php

/**
 * Mevcut aktif dili döndürür.
 * Session'da lang değeri yoksa varsayılan olarak 'tr' döner.
 * 
 * @return string
 */
function active_lang()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_GET['lang'])) {
        $requestedLang = strtolower($_GET['lang']);
        // tr, en ve ru dillerini destekliyoruz
        if (in_array($requestedLang, ['tr', 'en', 'ru'])) {
            $_SESSION['lang'] = $requestedLang;
        }
    } else {
        // Fallback: Path kontrolü
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $parts = explode('/', trim($uri, '/'));
        if (!empty($parts[0]) && in_array(strtolower($parts[0]), ['tr', 'en', 'ru'])) {
            $_SESSION['lang'] = strtolower($parts[0]);
        }
    }

    return isset($_SESSION['lang']) ? $_SESSION['lang'] : 'tr';
}

/**
 * URL'lerin başına mevcut aktif dil ön ekini ekler (örneğin /tr/hakkimizda).
 * 
 * @param string $path
 * @return string
 */
function lang_url($path)
{
    $path = trim((string)$path);
    if ($path === '' || $path === '#' || $path === 'javascript:void(0)' || $path === 'javascript:void()') {
        return $path;
    }
    
    // Harici URL'leri elleme
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $lang = active_lang();
    $cleanPath = ltrim($path, '/');
    
    // Çift dil eklenmesini engelle
    if (preg_match('/^(tr|en|ru)\b/i', $cleanPath)) {
        return '/' . $cleanPath;
    }
    
    if ($cleanPath === '') {
        return '/' . $lang . '/';
    }
    
    return '/' . $lang . '/' . $cleanPath;
}

/**
 * Dil geçiş menüsü için temiz URL üretir.
 * 
 * @param string $targetLang
 * @return string
 */
function get_lang_switch_url($targetLang)
{
    $currentScript = basename($_SERVER['SCRIPT_NAME']);
    $path = '';
    if ($currentScript === 'urun-detay.php') {
        $path = '/' . $targetLang . '/urun/' . ($_GET['sef_url'] ?? '');
    } elseif ($currentScript === 'urunler.php') {
        $path = '/' . $targetLang . '/urunler/' . ($_GET['slug'] ?? '');
    } elseif ($currentScript === 'blog-detay.php') {
        $path = '/' . $targetLang . '/blog/' . ($_GET['name'] ?? '');
    } elseif ($currentScript === 'sayfa-detay.php') {
        $path = '/' . $targetLang . '/' . ($_GET['slug'] ?? '');
    } elseif ($currentScript === 'index.php') {
        $path = '/' . $targetLang . '/';
    } else {
        $cleanName = basename($currentScript, '.php');
        $path = '/' . $targetLang . '/' . $cleanName;
    }

    // Diğer parametreleri koru (lang, sef_url, slug, name hariç)
    $params = $_GET;
    unset($params['lang'], $params['sef_url'], $params['slug'], $params['name']);
    if (!empty($params)) {
        $path .= '?' . http_build_query($params);
    }
    return $path;
}

/**
 * Dil dosyasını (config.json) yükler ve içeriğini dizi olarak döndürür.
 * 
 * @param string $lang Dil kodu (tr, en vb.)
 * @return array
 */
function get_language_config($lang = null)
{
    if (!$lang) {
        $lang = active_lang();
    }

    $path = __DIR__ . "/../data/lang/{$lang}/config.json";

    if (file_exists($path)) {
        $json = file_get_contents($path);
        return json_decode($json, true);
    }

    return [];
}

/**
 * Menü dosyasını (menu.json) yükler ve içeriğini dizi olarak döndürür.
 * 
 * @param string $lang Dil kodu (tr, en vb.)
 * @return array
 */
function get_menu_config($lang = null)
{
    if (!$lang) {
        $lang = active_lang();
    }

    $path = __DIR__ . "/../data/lang/{$lang}/menu.json";

    if (file_exists($path)) {
        $json = file_get_contents($path);
        return json_decode($json, true);
    }

    return [];
}

/**
 * Kategoriler dosyasını (kategoriler.json) yükler ve içeriğini dizi olarak döndürür.
 * 
 * @param string $lang Dil kodu (tr, en vb.)
 * @return array
 */
function get_categories_config($lang = null)
{
    if (!$lang) {
        $lang = active_lang();
    }

    $path = __DIR__ . "/../data/lang/{$lang}/kategoriler/kategoriler.json";

    if (file_exists($path)) {
        $json = file_get_contents($path);
        return json_decode($json, true);
    }

    return [];
}

/**
 * Anasayfa kartlarını (anasayfa.json) yükler ve içeriğini dizi olarak döndürür.
 * 
 * @param string $lang Dil kodu (tr, en vb.)
 * @return array
 */
function get_anasayfa_cards($lang = null)
{
    if (!$lang) {
        $lang = active_lang();
    }

    $path = __DIR__ . "/../data/lang/{$lang}/kartlar/anasayfa.json";

    if (file_exists($path)) {
        $json = file_get_contents($path);
        return json_decode($json, true);
    }

    return [];
}

/**
 * Statik dil dosyasını (statik.json) yükler ve içeriğini dizi olarak döndürür.
 * 
 * @param string $lang Dil kodu (tr, en vb.)
 * @return array
 */
function get_statik_config($lang = null)
{
    if (!$lang) {
        $lang = active_lang();
    }

    $path = __DIR__ . "/../data/lang/{$lang}/statik.json";

    if (file_exists($path)) {
        $json = file_get_contents($path);
        return json_decode($json, true);
    }

    return [];
}

/**
 * Sayfa açıklamaları dosyasından (sayfa_aciklamalari.json) değer döndürür.
 * 
 * @param string $key Anahtar (sayfa adı)
 * @param string $lang Dil kodu
 * @return string|null
 */
function get_sayfa_aciklamasi($key, $lang = null)
{
    if (!$lang) {
        $lang = active_lang();
    }

    static $descriptions = [];

    if (!isset($descriptions[$lang])) {
        $path = __DIR__ . "/../data/lang/{$lang}/sayfa_aciklamalari/sayfa_aciklamalari.json";
        if (!file_exists($path)) {
            // Fallback to Turkish
            $path = __DIR__ . "/../data/lang/tr/sayfa_aciklamalari/sayfa_aciklamalari.json";
        }
        if (file_exists($path)) {
            $json = file_get_contents($path);
            $descriptions[$lang] = json_decode($json, true);
        } else {
            $descriptions[$lang] = [];
        }
    }

    if (isset($descriptions[$lang][$key]) && $descriptions[$lang][$key] !== null) {
        return html_entity_decode($descriptions[$lang][$key], ENT_QUOTES, 'UTF-8');
    }

    return null;
}

/**
 * Mevcut sayfanın dinamik footer açıklamasını sayfa_aciklamalari.json dosyasından belirler.
 * 
 * @param string|null $targetLang Hedef dil kodu
 * @return string|null
 */
function get_sidebar_footer_text($targetLang = null)
{
    $script = basename($_SERVER['SCRIPT_NAME']);
    $page_key = null;

    if ($script === 'blog.php' || $script === 'blog-detay.php') {
        $page_key = 'blog';
    } elseif ($script === 'tasarim.php') {
        $page_key = 'tasarim';
    } elseif ($script === 'kalite-politikasi.php') {
        $page_key = 'kalite-politikasi';
    } elseif ($script === 'materyaller.php') {
        $page_key = 'materyaller';
    } elseif ($script === 'doga.php') {
        $page_key = 'doga';
    } elseif ($script === 'sertifikalar.php') {
        $page_key = 'sertifikalar';
    } elseif ($script === 'oduller.php') {
        $page_key = 'oduller';
    } elseif ($script === 'kariyer.php') {
        $page_key = 'kariyer';
    } elseif ($script === 'referanslar.php') {
        $page_key = 'referanslar';
    } elseif ($script === 'iletisim.php' || $script === 'harita.php') {
        $page_key = 'iletisim';
    } elseif ($script === 'sayfa-detay.php') {
        $page_key = $_GET['slug'] ?? 'kalite-politikasi';
    }

    if ($page_key !== null) {
        $desc = get_sayfa_aciklamasi($page_key, $targetLang);
        if ($desc !== null && trim($desc) !== '') {
            return $desc;
        }
    }

    return null;
}

/**
 * Statik dil dosyasından (statik.json) değer döndürür.
 * 
 * @param string $key Anahtar
 * @param string $lang Dil kodu
 * @return string
 */
function statik($key, $fallbackOrLang = null, $lang = null)
{
    $targetLang = $lang;
    $fallback = null;

    if ($targetLang === null) {
        if ($fallbackOrLang !== null && strlen($fallbackOrLang) === 2) {
            $targetLang = $fallbackOrLang;
        } else {
            $targetLang = active_lang();
            $fallback = $fallbackOrLang;
        }
    } else {
        $fallback = $fallbackOrLang;
    }

    if ($key === 'sidebar_footer') {
        $desc = get_sidebar_footer_text($targetLang);
        if ($desc !== null && trim($desc) !== '') {
            return $desc;
        }
    }

    static $translations = [];

    if (!isset($translations[$targetLang])) {
        $translations[$targetLang] = get_statik_config($targetLang);
    }

    return isset($translations[$targetLang][$key]) ? $translations[$targetLang][$key] : ($fallback ?? $key);
}

/**
 * Mega menu içeriğini render eder.
 * 
 * @param array $item Menü objesi
 * @return void
 */
function render_mega_menu($item)
{
    if (!isset($item['submenu']) || empty($item['submenu']))
        return;

    echo '<div class="submenu-mega">';
    echo '<div class="submenu-grid">';

    $index = 1;
    foreach ($item['submenu'] as $sub) {
        $bgImage = "assets/bg/{$index}.webp";
        $colorClass = isset($sub['color']) ? "gradient-{$sub['color']}" : "";
        echo '<a href="' . lang_url($sub['url']) . '" class="submenu-card ' . $colorClass . '" style="background-image: url(\'' . $bgImage . '\'); background-size: cover; background-position: center;" title="' . htmlspecialchars($sub['title'], ENT_QUOTES, 'UTF-8') . '">';
        echo '<div class="card-icon"><i class="bi ' . $sub['icon'] . '"></i></div>';
        echo '<div class="card-text">';
        echo '<span class="title">' . $sub['title'] . '</span>';
        if (!empty($sub['subtitle'])) {
            echo '<span class="subtitle">' . $sub['subtitle'] . '</span>';
        }
        echo '</div>';
        echo '</a>';
        $index++;
    }

    echo '</div>';
    echo '</div>';
}

/**
 * Tekil bir navigasyon elemanını render eder.
 * 
 * @param array $item Menü objesi
 * @param string $class Ekstra CSS class'ı
 * @return void
 */
function render_nav_item($item, $class = 'nav-link')
{
    $hasSubmenu = isset($item['submenu']) && !empty($item['submenu']);
    $itemClass = $hasSubmenu ? 'nav-item has-submenu' : 'nav-item';

    if ($hasSubmenu) {
        echo '<div class="' . $itemClass . '">';
        echo '<a class="' . $class . '" href="' . lang_url($item['url']) . '" title="' . htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') . '">' . $item['title'] . '</a>';
        render_mega_menu($item);
        echo '</div>';
    } else {
        $extraClass = isset($item['class']) ? $item['class'] : $class;
        echo '<a class="' . $extraClass . '" href="' . lang_url($item['url']) . '" title="' . htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') . '">' . $item['title'] . '</a>';
    }
}

/**
 * Mobil navigasyon elemanını render eder.
 * 
 * @param array $item Menü objesi
 * @param string $id Mobil collapse ID'si
 * @return void
 */
function render_mobile_nav_item($item, $id)
{
    $hasSubmenu = isset($item['submenu']) && !empty($item['submenu']);

    if ($hasSubmenu) {
        echo '<li class="nav-item has-submenu">';
        echo '<a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#' . $id . '" href="javascript:void(0)" role="button" title="' . htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') . '">';
        echo $item['title'] . ' <i class="bi bi-chevron-down small"></i>';
        echo '</a>';
        echo '<div class="submenu-mega collapse" id="' . $id . '">';
        echo '<div class="submenu-grid">';
        $index = 1;
        foreach ($item['submenu'] as $sub) {
            $bgImage = "assets/bg/{$index}.webp";
            echo '<a href="' . lang_url($sub['url']) . '" class="submenu-card" style="background-image: url(\'' . $bgImage . '\'); background-size: cover; background-position: center;" title="' . htmlspecialchars($sub['title'], ENT_QUOTES, 'UTF-8') . '">';
            echo '<div class="card-icon"><i class="bi ' . $sub['icon'] . '"></i></div>';
            echo '<div class="card-text"><span class="title">' . $sub['title'] . '</span></div>';
            echo '</a>';
            $index++;
        }
        echo '</div>';
        echo '</div>';
        echo '</li>';
    } else {
        $class = isset($item['class']) ? $item['class'] : '';
        echo '<li class="nav-item"><a class="nav-link ' . $class . '" href="' . lang_url($item['url']) . '" title="' . htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') . '">' . $item['title'] . '</a></li>';
    }
}


/**
 * SEO Uyumlu URL oluşturma
 */
function seo_name($text)
{
    if (empty($text))
        return 'n-a';
    $find = array('Ç', 'Ş', 'Ğ', 'Ü', 'İ', 'Ö', 'ç', 'ş', 'ğ', 'ü', 'ö', 'ı', '+', '#', ' ', '.');
    $replace = array('c', 's', 'g', 'u', 'i', 'o', 'c', 's', 'g', 'u', 'o', 'i', 'plus', 'sharp', '-', '-');
    $text = strtolower(str_replace($find, $replace, $text));
    $text = preg_replace('/[^a-z0-9]/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}
