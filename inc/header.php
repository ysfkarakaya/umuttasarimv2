<?php
include_once __DIR__ . '/functions.php';
$lang = active_lang();
$config = get_language_config($lang);
$menu_items = get_menu_config($lang);

// Sayfa başlığı ayarı
$siteTitle = isset($config['head']['title']) ? $config['head']['title'] : 'Umut Tasarım';
if (isset($pageTitle) && !empty($pageTitle)) {
    $titleTag = $pageTitle . ' | ' . $siteTitle;
} else {
    $titleTag = $siteTitle;
}

// Meta değerleri
$meta = isset($config['head']['meta']) ? $config['head']['meta'] : [];
$og = isset($config['head']['og']) ? $config['head']['og'] : [];
$twitter = isset($config['head']['twitter']) ? $config['head']['twitter'] : [];

// Dinamik/sayfa-bazlı görsel override
if (isset($pageImage) && !empty($pageImage)) {
    $absolutePageImage = $pageImage;
    if (strpos($absolutePageImage, 'http') !== 0) {
        $absolutePageImage = 'https://umuttasarim.com/' . ltrim($absolutePageImage, '/');
    }
    $og['image'] = $absolutePageImage;
    $twitter['image'] = $absolutePageImage;
}

// Dinamik Canonical URL hesaplama
if (!isset($pageCanonical) || empty($pageCanonical)) {
    $currentScript = basename($_SERVER['SCRIPT_NAME']);
    if ($currentScript === 'index.php') {
        $pageCanonical = 'https://umuttasarim.com/' . $lang . '/';
    } elseif ($currentScript === 'blog.php') {
        $pageCanonical = 'https://umuttasarim.com/' . $lang . '/blog';
    } else {
        $pageCanonical = 'https://umuttasarim.com/' . $lang . '/' . basename($currentScript, '.php');
    }
}

// Alternatif dil URL'leri (hreflang) hesaplama
$alternateUrls = [];
$current_script = basename($_SERVER['SCRIPT_NAME']);
$langs = ['tr', 'en', 'ru'];
foreach ($langs as $l) {
    if ($current_script === 'urun-detay.php') {
        $alternateUrls[$l] = 'https://umuttasarim.com/' . $l . '/urun/' . ($_GET['sef_url'] ?? 'eko-01');
    } elseif ($current_script === 'urunler.php') {
        $alternateUrls[$l] = 'https://umuttasarim.com/' . $l . '/urunler/' . ($_GET['slug'] ?? '');
    } elseif ($current_script === 'blog-detay.php') {
        $alternateUrls[$l] = 'https://umuttasarim.com/' . $l . '/blog/' . ($_GET['name'] ?? '');
    } elseif ($current_script === 'sayfa-detay.php') {
        $alternateUrls[$l] = 'https://umuttasarim.com/' . $l . '/' . ($_GET['slug'] ?? 'kalite-politikasi');
    } elseif ($current_script === 'index.php') {
        $alternateUrls[$l] = 'https://umuttasarim.com/' . $l . '/';
    } else {
        $cleanName = basename($current_script, '.php');
        $alternateUrls[$l] = 'https://umuttasarim.com/' . $l . '/' . $cleanName;
    }
}

// Meta değerleri dinamik override
$pageDesc = (isset($pageDescription) && !empty($pageDescription)) ? $pageDescription : ($meta['description'] ?? '');
$pageKeys = (isset($pageKeywords) && !empty($pageKeywords)) ? $pageKeywords : ($meta['keywords'] ?? '');

// Open Graph URL'i canonical ile eşitle
$og['url'] = $pageCanonical;
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">

<head>
    <meta charset="<?php echo isset($meta['charset']) ? $meta['charset'] : 'UTF-8'; ?>">
    <meta name="viewport"
        content="<?php echo isset($meta['viewport']) ? $meta['viewport'] : 'width=device-width, initial-scale=1.0'; ?>">

    <title><?php echo $titleTag; ?></title>
    <base href="<?php echo (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/'; ?>">
    <meta name="description" content="<?php echo htmlspecialchars($pageDesc, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($pageKeys, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="<?php echo isset($meta['author']) ? $meta['author'] : ''; ?>">
    <meta name="robots" content="<?php echo isset($meta['robots']) ? $meta['robots'] : 'index, follow'; ?>">

    <?php if (isset($meta['google-site-verification'])): ?>
        <meta name="google-site-verification" content="<?php echo $meta['google-site-verification']; ?>">
    <?php endif; ?>

    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo isset($og['title']) ? $og['title'] : $titleTag; ?>">
    <meta property="og:description"
        content="<?php echo isset($og['description']) ? $og['description'] : $pageDesc; ?>">
    <meta property="og:type" content="<?php echo isset($og['type']) ? $og['type'] : 'website'; ?>">
    <meta property="og:url" content="<?php echo isset($og['url']) ? $og['url'] : $pageCanonical; ?>">
    <meta property="og:image" content="<?php echo isset($og['image']) ? $og['image'] : ''; ?>">
    <meta property="og:site_name" content="<?php echo isset($og['site_name']) ? $og['site_name'] : $siteTitle; ?>">

    <!-- Twitter -->
    <meta name="twitter:card"
        content="<?php echo isset($twitter['card']) ? $twitter['card'] : 'summary_large_image'; ?>">
    <meta name="twitter:title" content="<?php echo isset($twitter['title']) ? $twitter['title'] : $titleTag; ?>">
    <meta name="twitter:description"
        content="<?php echo isset($twitter['description']) ? $twitter['description'] : $pageDesc; ?>">
    <meta name="twitter:image" content="<?php echo isset($twitter['image']) ? $twitter['image'] : ''; ?>">

    <!-- Favicon & Icons -->
    <link rel="icon"
        href="<?php echo isset($config['head']['links']['favicon']) ? $config['head']['links']['favicon'] : '/assets/img/logo.png'; ?>"
        type="image/png">
    <link rel="canonical" href="<?php echo htmlspecialchars($pageCanonical, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="alternate" hreflang="tr" href="<?php echo htmlspecialchars($alternateUrls['tr'], ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="alternate" hreflang="en" href="<?php echo htmlspecialchars($alternateUrls['en'], ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="alternate" hreflang="ru" href="<?php echo htmlspecialchars($alternateUrls['ru'], ENT_QUOTES, 'UTF-8'); ?>" />
    <link rel="alternate" hreflang="x-default" href="<?php echo htmlspecialchars($alternateUrls['tr'], ENT_QUOTES, 'UTF-8'); ?>" />

    <!-- Structured Data (JSON-LD Schemas) -->
    <?php if (isset($pageSchema) && !empty($pageSchema)): ?>
        <script type="application/ld+json">
        <?php echo json_encode($pageSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
        </script>
    <?php elseif (isset($config['head']['schema']) && !empty($config['head']['schema']) && basename($_SERVER['SCRIPT_NAME']) === 'index.php'): ?>
        <script type="application/ld+json">
        <?php echo json_encode($config['head']['schema'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
        </script>
    <?php endif; ?>

    <!-- Core Assets -->
    <link href="/assets/vendor/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/fonts.css">
    <link rel="stylesheet" href="/assets/vendor/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/vendor/swiper-bundle.min.css" />
    <link rel="stylesheet" href="/assets/css/style.css">