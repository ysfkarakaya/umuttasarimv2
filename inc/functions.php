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

    // if (isset($_GET['lang'])) {
    //     $requestedLang = strtolower($_GET['lang']);
    //     // Sadece desteklenen dillere izin ver (data/lang klasöründeki klasör isimleri)
    //     if (in_array($requestedLang, ['tr', 'en'])) {
    //         $_SESSION['lang'] = $requestedLang;
    //     }
    // }

    return isset($_SESSION['lang']) ? $_SESSION['lang'] : 'tr';
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
        echo '<a href="' . $sub['url'] . '" class="submenu-card ' . $colorClass . '" style="background-image: url(\'' . $bgImage . '\'); background-size: cover; background-position: center;">';
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
        echo '<a class="' . $class . '" href="' . $item['url'] . '">' . $item['title'] . '</a>';
        render_mega_menu($item);
        echo '</div>';
    } else {
        $extraClass = isset($item['class']) ? $item['class'] : $class;
        echo '<a class="' . $extraClass . '" href="' . $item['url'] . '">' . $item['title'] . '</a>';
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
        echo '<a class="nav-link d-flex justify-content-between align-items-center" data-bs-toggle="collapse" data-bs-target="#' . $id . '" href="javascript:void(0)" role="button">';
        echo $item['title'] . ' <i class="bi bi-chevron-down small"></i>';
        echo '</a>';
        echo '<div class="submenu-mega collapse" id="' . $id . '">';
        echo '<div class="submenu-grid">';
        $index = 1;
        foreach ($item['submenu'] as $sub) {
            $bgImage = "assets/bg/{$index}.webp";
            echo '<a href="' . $sub['url'] . '" class="submenu-card" style="background-image: url(\'' . $bgImage . '\'); background-size: cover; background-position: center;">';
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
        echo '<li class="nav-item"><a class="nav-link ' . $class . '" href="' . $item['url'] . '">' . $item['title'] . '</a></li>';
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
