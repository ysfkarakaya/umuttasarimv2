<?php
/**
 * Dynamic Multilingual XML Sitemap Generator
 * Serves clean SEO paths for TR, EN, and RU with xhtml:link alternates.
 */

// Set content type header
header("Content-Type: application/xml; charset=utf-8");

$languages = ['tr', 'en', 'ru'];
$defaultLang = 'tr';

// Detect base URL dynamically or fallback to primary domain
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'] ?? 'umuttasarim.com';
$baseUrl = $protocol . "://" . $host;

// If we are on production, force https and the clean domain
if (strpos($host, 'umuttasarim.com') !== false) {
    $baseUrl = 'https://umuttasarim.com';
}

$staticPages = [
    '' => ['priority' => '1.0', 'changefreq' => 'daily'], // Homepage
    'hakkimizda' => ['priority' => '0.8', 'changefreq' => 'weekly'],
    'tasarim' => ['priority' => '0.8', 'changefreq' => 'weekly'],
    'kalite-politikasi' => ['priority' => '0.8', 'changefreq' => 'weekly'],
    'materyaller' => ['priority' => '0.8', 'changefreq' => 'weekly'],
    'doga' => ['priority' => '0.8', 'changefreq' => 'weekly'],
    'sertifikalar' => ['priority' => '0.8', 'changefreq' => 'weekly'],
    'oduller' => ['priority' => '0.8', 'changefreq' => 'weekly'],
    'kariyer' => ['priority' => '0.8', 'changefreq' => 'weekly'],
    'referanslar' => ['priority' => '0.8', 'changefreq' => 'weekly'],
    'blog' => ['priority' => '0.8', 'changefreq' => 'weekly'],
    'iletisim' => ['priority' => '0.8', 'changefreq' => 'weekly'],
];

// Helper functions to retrieve file modification times
function getStaticPageLastmod($slug, $languages) {
    $lastmod = 0;
    foreach ($languages as $lang) {
        $jsonFile = __DIR__ . "/data/lang/{$lang}/sayfalar/{$slug}.json";
        if (file_exists($jsonFile)) {
            $lastmod = max($lastmod, filemtime($jsonFile));
        }
    }
    
    $phpFile = __DIR__ . '/' . ($slug === '' ? 'index' : $slug) . '.php';
    if (file_exists($phpFile)) {
        $lastmod = max($lastmod, filemtime($phpFile));
    }
    
    return $lastmod > 0 ? $lastmod : time();
}

function getCategoryLastmod($slug, $languages) {
    $lastmod = 0;
    foreach ($languages as $lang) {
        $jsonFile = __DIR__ . "/data/lang/{$lang}/urunler/{$slug}.json";
        if (file_exists($jsonFile)) {
            $lastmod = max($lastmod, filemtime($jsonFile));
        }
    }
    return $lastmod > 0 ? $lastmod : time();
}

function getProductLastmod($slug, $languages) {
    $lastmod = 0;
    foreach ($languages as $lang) {
        $jsonFile = __DIR__ . "/data/lang/{$lang}/urun-detay/{$slug}.json";
        if (file_exists($jsonFile)) {
            $lastmod = max($lastmod, filemtime($jsonFile));
        }
    }
    return $lastmod > 0 ? $lastmod : time();
}

function getBlogLastmod($slug, $languages) {
    $lastmod = 0;
    foreach ($languages as $lang) {
        $jsonFile = __DIR__ . "/data/lang/{$lang}/blog/{$slug}.json";
        if (file_exists($jsonFile)) {
            $lastmod = max($lastmod, filemtime($jsonFile));
        }
    }
    return $lastmod > 0 ? $lastmod : time();
}

// Gather all category slugs from directories
$categorySlugs = [];
foreach ($languages as $lang) {
    $dir = __DIR__ . "/data/lang/{$lang}/urunler";
    if (is_dir($dir)) {
        $files = glob($dir . '/*.json');
        if (is_array($files)) {
            foreach ($files as $file) {
                $slug = basename($file, '.json');
                $categorySlugs[$slug] = true;
            }
        }
    }
}
$categorySlugs = array_keys($categorySlugs);

// Gather all product slugs from directories
$productSlugs = [];
foreach ($languages as $lang) {
    $dir = __DIR__ . "/data/lang/{$lang}/urun-detay";
    if (is_dir($dir)) {
        $files = glob($dir . '/*.json');
        if (is_array($files)) {
            foreach ($files as $file) {
                $slug = basename($file, '.json');
                $productSlugs[$slug] = true;
            }
        }
    }
}
$productSlugs = array_keys($productSlugs);

// Gather all blog post slugs from directories
$blogSlugs = [];
foreach ($languages as $lang) {
    $dir = __DIR__ . "/data/lang/{$lang}/blog";
    if (is_dir($dir)) {
        $files = glob($dir . '/*.json');
        if (is_array($files)) {
            foreach ($files as $file) {
                $slug = basename($file, '.json');
                if ($slug === 'blog') {
                    continue;
                }
                $blogSlugs[$slug] = true;
            }
        }
    }
}
$blogSlugs = array_keys($blogSlugs);

// XML URL container
$urls = [];

// Helper function to build alternate nodes
function addMultilingualUrl(&$urls, $type, $slug, $languages, $defaultLang, $baseUrl, $priority, $changefreq, $lastmod) {
    foreach ($languages as $lang) {
        $loc = '';
        if ($type === 'static') {
            $loc = ($slug === '') ? "{$baseUrl}/{$lang}/" : "{$baseUrl}/{$lang}/{$slug}";
        } elseif ($type === 'category') {
            $loc = "{$baseUrl}/{$lang}/urunler/{$slug}";
        } elseif ($type === 'product') {
            $loc = "{$baseUrl}/{$lang}/urun/{$slug}";
        } elseif ($type === 'blog') {
            $loc = "{$baseUrl}/{$lang}/blog/{$slug}";
        }

        $alternates = [];
        foreach ($languages as $altLang) {
            $altLoc = '';
            if ($type === 'static') {
                $altLoc = ($slug === '') ? "{$baseUrl}/{$altLang}/" : "{$baseUrl}/{$altLang}/{$slug}";
            } elseif ($type === 'category') {
                $altLoc = "{$baseUrl}/{$altLang}/urunler/{$slug}";
            } elseif ($type === 'product') {
                $altLoc = "{$baseUrl}/{$altLang}/urun/{$slug}";
            } elseif ($type === 'blog') {
                $altLoc = "{$baseUrl}/{$altLang}/blog/{$slug}";
            }
            $alternates[$altLang] = $altLoc;
        }

        // Set default mapping to Turkish language equivalent
        $defaultLoc = '';
        if ($type === 'static') {
            $defaultLoc = ($slug === '') ? "{$baseUrl}/{$defaultLang}/" : "{$baseUrl}/{$defaultLang}/{$slug}";
        } elseif ($type === 'category') {
            $defaultLoc = "{$baseUrl}/{$defaultLang}/urunler/{$slug}";
        } elseif ($type === 'product') {
            $defaultLoc = "{$baseUrl}/{$defaultLang}/urun/{$slug}";
        } elseif ($type === 'blog') {
            $defaultLoc = "{$baseUrl}/{$defaultLang}/blog/{$slug}";
        }
        $alternates['x-default'] = $defaultLoc;

        $urls[] = [
            'loc' => $loc,
            'lastmod' => date('Y-m-d', $lastmod),
            'changefreq' => $changefreq,
            'priority' => $priority,
            'alternates' => $alternates
        ];
    }
}

// 1. Generate URLs for Static Pages
foreach ($staticPages as $slug => $meta) {
    addMultilingualUrl($urls, 'static', $slug, $languages, $defaultLang, $baseUrl, $meta['priority'], $meta['changefreq'], getStaticPageLastmod($slug, $languages));
}

// 2. Generate URLs for Product Categories
foreach ($categorySlugs as $slug) {
    addMultilingualUrl($urls, 'category', $slug, $languages, $defaultLang, $baseUrl, '0.8', 'weekly', getCategoryLastmod($slug, $languages));
}

// 3. Generate URLs for Individual Products
foreach ($productSlugs as $slug) {
    addMultilingualUrl($urls, 'product', $slug, $languages, $defaultLang, $baseUrl, '0.7', 'weekly', getProductLastmod($slug, $languages));
}

// 4. Generate URLs for Blog Posts
foreach ($blogSlugs as $slug) {
    addMultilingualUrl($urls, 'blog', $slug, $languages, $defaultLang, $baseUrl, '0.7', 'weekly', getBlogLastmod($slug, $languages));
}

// Output XML
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
<?php foreach ($urls as $url): ?>
    <url>
        <loc><?php echo htmlspecialchars($url['loc'], ENT_XML1, 'UTF-8'); ?></loc>
        <lastmod><?php echo $url['lastmod']; ?></lastmod>
        <changefreq><?php echo $url['changefreq']; ?></changefreq>
        <priority><?php echo $url['priority']; ?></priority>
<?php foreach ($url['alternates'] as $langCode => $altLink): ?>
        <xhtml:link rel="alternate" hreflang="<?php echo htmlspecialchars($langCode, ENT_XML1, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($altLink, ENT_XML1, 'UTF-8'); ?>"/>
<?php endforeach; ?>
    </url>
<?php endforeach; ?>
</urlset>
