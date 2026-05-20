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
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">

<head>
    <meta charset="<?php echo isset($meta['charset']) ? $meta['charset'] : 'UTF-8'; ?>">
    <meta name="viewport"
        content="<?php echo isset($meta['viewport']) ? $meta['viewport'] : 'width=device-width, initial-scale=1.0'; ?>">

    <title><?php echo $titleTag; ?></title>
    <base href="https://umuttasarimv2.yk">
    <meta name="description" content="<?php echo isset($meta['description']) ? $meta['description'] : ''; ?>">
    <meta name="keywords" content="<?php echo isset($meta['keywords']) ? $meta['keywords'] : ''; ?>">
    <meta name="author" content="<?php echo isset($meta['author']) ? $meta['author'] : ''; ?>">
    <meta name="robots" content="<?php echo isset($meta['robots']) ? $meta['robots'] : 'index, follow'; ?>">

    <?php if (isset($meta['google-site-verification'])): ?>
        <meta name="google-site-verification" content="<?php echo $meta['google-site-verification']; ?>">
    <?php endif; ?>

    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo isset($og['title']) ? $og['title'] : $titleTag; ?>">
    <meta property="og:description"
        content="<?php echo isset($og['description']) ? $og['description'] : (isset($meta['description']) ? $meta['description'] : ''); ?>">
    <meta property="og:type" content="<?php echo isset($og['type']) ? $og['type'] : 'website'; ?>">
    <meta property="og:url" content="<?php echo isset($og['url']) ? $og['url'] : ''; ?>">
    <meta property="og:image" content="<?php echo isset($og['image']) ? $og['image'] : ''; ?>">
    <meta property="og:site_name" content="<?php echo isset($og['site_name']) ? $og['site_name'] : $siteTitle; ?>">

    <!-- Twitter -->
    <meta name="twitter:card"
        content="<?php echo isset($twitter['card']) ? $twitter['card'] : 'summary_large_image'; ?>">
    <meta name="twitter:title" content="<?php echo isset($twitter['title']) ? $twitter['title'] : $titleTag; ?>">
    <meta name="twitter:description"
        content="<?php echo isset($twitter['description']) ? $twitter['description'] : (isset($meta['description']) ? $meta['description'] : ''); ?>">
    <meta name="twitter:image" content="<?php echo isset($twitter['image']) ? $twitter['image'] : ''; ?>">

    <!-- Favicon & Icons -->
    <link rel="shortcut icon"
        href="<?php echo isset($config['head']['links']['favicon']) ? $config['head']['links']['favicon'] : '/favicon.ico'; ?>"
        type="image/x-icon">
    <link rel="canonical"
        href="<?php echo isset($config['head']['links']['canonical']) ? $config['head']['links']['canonical'] : ''; ?>">

    <!-- Core Assets -->
    <link href="/assets/vendor/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/fonts.css">
    <link rel="stylesheet" href="/assets/vendor/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/vendor/swiper-bundle.min.css" />
    <link rel="stylesheet" href="/assets/css/style.css">