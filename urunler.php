<?php
$hasSidebar = true; // Sidebar'ı kapatmak için false yapın

include_once 'inc/functions.php';
$lang = active_lang();

$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
$slug = preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
$dynamicSeries = [];
$activeCategoryName = '';
$pageImage = '';

if ($slug !== '') {
    $productsFilePath = __DIR__ . "/data/lang/{$lang}/urunler/{$slug}.json";

    if (file_exists($productsFilePath)) {
        $productsJson = json_decode(file_get_contents($productsFilePath), true);

        if (is_array($productsJson)) {
            // Extract category name from the first valid product
            foreach ($productsJson as $p) {
                if (isset($p['kat_adi']) && $p['kat_adi'] !== '') {
                    $activeCategoryName = $p['kat_adi'];
                    break;
                }
            }

            foreach ($productsJson as $productItem) {
                if (!is_array($productItem)) {
                    continue;
                }

                if (isset($productItem['urun_durum']) && (int) $productItem['urun_durum'] !== 1) {
                    continue;
                }

                $seriesName = trim((string) ($productItem['seri_adi'] ?? 'Diğer'));
                if ($seriesName === '') {
                    $seriesName = 'Diğer';
                }

                if (!isset($dynamicSeries[$seriesName])) {
                    $dynamicSeries[$seriesName] = [];
                }

                $dynamicSeries[$seriesName][] = $productItem;
            }
        }
    }

    // Kategorinin görselini kategoriler.json dosyasından bulalım
    $categories = get_categories_config($lang);
    if (is_array($categories)) {
        foreach ($categories as $cat) {
            if (isset($cat['kat_sef_url']) && $cat['kat_sef_url'] === $slug) {
                $categoryImg = trim((string) ($cat['kat_resim2'] ?? $cat['kat_resim'] ?? ''));
                if ($categoryImg !== '') {
                    $pageImage = $categoryImg;
                }
                break;
            }
        }
    }
}

$hasDynamicSeries = !empty($dynamicSeries);

$pageCanonical = 'https://umuttasarim.com/' . $lang . '/urunler/' . $slug;

// Dinamik meta açıklaması
if ($activeCategoryName !== '') {
    $pageDescription = $activeCategoryName . " " . ($lang === 'tr' ? 'kategorisindeki çocuk oyun grupları, kent mobilyaları ve peyzaj elemanları.' : 'children playground equipment, urban furniture and landscaping elements.');
    $pageKeywords = implode(', ', array_filter([$activeCategoryName, "oyun grupları", "kent mobilyaları", "peyzaj elemanları", "tasarım çözümleri", "umut tasarım"]));
} else {
    $pageDescription = ($lang === 'tr' ? 'Umut Tasarım çocuk oyun grupları, kent mobilyaları ve peyzaj elemanları üreticisi.' : 'Umut Tasarim children playground equipment, urban furniture and landscaping manufacturer.');
    $pageKeywords = "oyun grupları, kent mobilyaları, peyzaj elemanları, umut tasarım";
}

$pageTitle = $activeCategoryName !== '' ? $activeCategoryName : statik('products_page_title', 'Ürünler');
include_once 'inc/header.php';
?>


<body class="<?php echo $hasSidebar ? '' : 'no-sidebar'; ?>">

    <?php if ($hasSidebar): ?>
        <!-- Sol Panel (Fixed) -->
        <div class="sidebar">
            <div>
                <!-- Örnek Logo SVG -->
                <div class="sidebar-logo">
                    <img src="assets/img/logo.png" alt="Logo">
                </div>
                <h1 class=" sidebar-title">
                    <?php echo htmlspecialchars($activeCategoryName !== '' ? $activeCategoryName : 'Ürünler', ENT_QUOTES, 'UTF-8'); ?>
                </h1>
            </div>
            <div class="sidebar-footer">
                <?= statik('products_sidebar_footer') ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Sağ İçerik -->
    <div class="main-content">

        <!-- Üst Menü -->
        <?php include_once 'inc/navbar.php'; ?>

        <!-- Hero Bölümü -->
        <?php
        $anasayfaCards = get_anasayfa_cards($lang);
        $heroCard = !empty($anasayfaCards) ? $anasayfaCards[0] : null;
        $defaultBg = ($heroCard && !empty($heroCard['detaylar'])) ? $heroCard['detaylar'][0]['kart_detay_gorsel'] : 'assets/img/hero/hero1.jpeg';
        ?>
        <section class="hero-section" id="hero-section" style="background-image: url('<?= $defaultBg ?>');">
            <div class="hero-card">
                <?php if ($heroCard): ?>
                    <?= $heroCard['kart_aciklama'] ?>
                <?php endif; ?>
                <!-- Dahili Video (Lazy Load) -->
                <div class="hero-card-image" id="videoWrap" style="overflow:hidden;">
                    <video id="heroVideo" poster="assets/img/breadcrumbs.png" preload="none" muted loop playsinline
                        style="width:100%;height:100%;object-fit:cover;display:block;border-radius:inherit;">
                        <source src="assets/video.mp4" type="video/mp4">
                    </video>
                </div>
            </div>
        </section>


        <!-- Ürünler Listesi -->
        <section class="products-section">
            <?php if ($hasDynamicSeries): ?>
                <?php foreach ($dynamicSeries as $seriesName => $products): ?>
                    <?php
                    $seriesTop = preg_replace('/\s+serisi$/iu', '', $seriesName);
                    $seriesTop = trim((string) $seriesTop);
                    if ($seriesTop === '') {
                        $seriesTop = $seriesName;
                    }
                    ?>
                    <div class="series-row">
                        <div class="series-label">
                            <div class="series-top">'<?php echo htmlspecialchars($seriesTop, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="series-bottom">Series</div>
                        </div>
                        <div class="product-grid swiper">
                            <div class="swiper-wrapper">
                                <?php foreach ($products as $product): ?>
                                    <?php
                                    $productName = trim((string) ($product['urun_adi'] ?? ''));
                                    $productCode = trim((string) ($product['urun_kodu'] ?? $productName));
                                    $productSlug = trim((string) ($product['sef_url'] ?? ''));
                                    $imagePath = trim((string) ($product['kapak_resmi'] ?? ''));
                                    $imageExt = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
                                    $imageSrc = $imagePath !== '' ? '/' . ltrim($imagePath, '/') : '';

                                    if ($imageSrc === '' || in_array($imageExt, ['dwg', 'dxf', 'pdf'], true)) {
                                        $imageSrc = 'assets/img/eko.png';
                                    }

                                    $productHref = $productSlug !== '' ? lang_url('/urun/' . $productSlug) : '#';
                                    ?>
                                    <a class="product-card swiper-slide"
                                        href="<?php echo htmlspecialchars($productHref, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php
                                        $vCount = isset($product['benzer_urun_sayisi']) ? (int) $product['benzer_urun_sayisi'] : 0;
                                        ?>
                                        <div class="product-image-container">
                                            <?php if ($vCount > 1): ?>
                                                <div class="product-variation-badge">
                                                    <i class="bi bi-layers"></i>
                                                    <span><?php echo $vCount; ?> <span class="variation-text"><?= statik('product_variation_text', 'Varyasyon') ?></span></span>
                                                </div>
                                            <?php endif; ?>
                                            <img src="<?php echo htmlspecialchars($imageSrc, ENT_QUOTES, 'UTF-8'); ?>"
                                                alt="<?php echo htmlspecialchars($productCode !== '' ? $productCode : $productName, ENT_QUOTES, 'UTF-8'); ?>">
                                        </div>
                                        <div class="product-info">
                                            <div class="product-dots">
                                                <div class="dot orange"></div>
                                                <div class="dot red"></div>
                                                <div class="dot blue"></div>
                                                <div class="dot yellow"></div>
                                            </div>
                                            <div class="product-main-info">
                                                <div class="product-code">
                                                    <?php echo htmlspecialchars($productCode, ENT_QUOTES, 'UTF-8'); ?>
                                                </div>
                                                <div class="product-subtitle">
                                                    <?php echo htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?>
                                                </div>
                                            </div>
                                            <div class="product-divider"></div>
                                            <div class="product-specs">
                                                <div><?php echo $product['ozellikler']['yas_grubu'] ?></div>
                                                <div>
                                                    <?php echo $product['ozellikler']['kurulum_alani'] ?>
                                                </div>
                                                <div>
                                                    <?php echo $product['ozellikler']['kapasite'] ?>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <div class="swiper-button-next"></div>
                            <div class="swiper-button-prev"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>

                <!-- ECO Serisi -->
                <div class="series-row">
                    <div class="series-label">
                        <div class="series-top">'ECO</div>
                        <div class="series-bottom">Series</div>
                    </div>
                    <div class="product-grid swiper">
                        <div class="swiper-wrapper">
                            <!-- Ürün 1 -->
                            <div class="product-card swiper-slide">
                                <img src="assets/img/eko.png" alt="ECO-01">
                                <div class="product-info">
                                    <div class="product-dots">
                                        <div class="dot orange"></div>
                                        <div class="dot red"></div>
                                        <div class="dot blue"></div>
                                        <div class="dot yellow"></div>
                                    </div>
                                    <div class="product-main-info">
                                        <div class="product-code">ECO-01</div>
                                        <div class="product-subtitle">Tower with Slides and Double Swing</div>
                                    </div>
                                    <div class="product-specs">
                                        <div>+2 years</div>
                                        <div>33.7m²</div>
                                        <div>67 Users</div>
                                    </div>
                                    <div class="product-divider"></div>
                                </div>
                            </div>
                            <!-- Ürün 2 -->
                            <div class="product-card swiper-slide">
                                <img src="assets/img/eko.png" alt="ECO-01b">
                                <div class="product-info">
                                    <div class="product-dots">
                                        <div class="dot orange"></div>
                                        <div class="dot red"></div>
                                        <div class="dot blue"></div>
                                        <div class="dot green"></div>
                                    </div>
                                    <div class="product-main-info">
                                        <div class="product-code">ECO-01b</div>
                                        <div class="product-subtitle">Double Tower with Slides and Ladder</div>
                                    </div>
                                    <div class="product-specs">
                                        <div>+2 years</div>
                                        <div>33.7m²</div>
                                        <div>67 Users</div>
                                    </div>
                                    <div class="product-divider"></div>
                                </div>
                            </div>
                            <!-- Ürün 3 -->
                            <div class="product-card swiper-slide">
                                <img src="assets/img/eko.png" alt="ECO-01c">
                                <div class="product-info">
                                    <div class="product-dots">
                                        <div class="dot orange"></div>
                                        <div class="dot red"></div>
                                        <div class="dot blue"></div>
                                        <div class="dot yellow"></div>
                                    </div>
                                    <div class="product-main-info">
                                        <div class="product-code">ECO-01c</div>
                                        <div class="product-subtitle">Tower with Slides and Bridge</div>
                                    </div>
                                    <div class="product-specs">
                                        <div>+2 years</div>
                                        <div>33.7m²</div>
                                        <div>67 Users</div>
                                    </div>
                                    <div class="product-divider"></div>
                                </div>
                            </div>
                        </div>
                        <div class="swiper-button-next"></div>
                        <div class="swiper-button-prev"></div>
                    </div>
                </div>

                <!-- MAXI Serisi -->
                <div class="series-row">
                    <div class="series-label">
                        <div class="series-top">'MAXI</div>
                        <div class="series-bottom">Series</div>
                    </div>
                    <div class="product-grid swiper">
                        <div class="swiper-wrapper">
                            <!-- Ürün 1 -->
                            <div class="product-card swiper-slide">
                                <img src="https://mir-s3-cdn-cf.behance.net/project_modules/disp/41727725890815.5634ca9392e21.png"
                                    alt="MAXI-01">
                                <div class="product-info">
                                    <div class="product-dots">
                                        <div class="dot orange"></div>
                                        <div class="dot red"></div>
                                        <div class="dot blue"></div>
                                        <div class="dot yellow"></div>
                                    </div>
                                    <div class="product-main-info">
                                        <div class="product-code">MAXI-01</div>
                                        <div class="product-subtitle">Tower with Slides</div>
                                    </div>
                                    <div class="product-specs">
                                        <div>+2 years</div>
                                        <div>33.7m²</div>
                                        <div>67 Users</div>
                                    </div>
                                    <div class="product-divider"></div>
                                </div>
                            </div>
                            <!-- Ürün 2 -->
                            <div class="product-card swiper-slide">
                                <img src="https://mir-s3-cdn-cf.behance.net/project_modules/disp/41727725890815.5634ca9392e21.png"
                                    alt="MAXI-02">
                                <div class="product-info">
                                    <div class="product-dots">
                                        <div class="dot blue"></div>
                                        <div class="dot yellow"></div>
                                        <div class="dot red"></div>
                                        <div class="dot green"></div>
                                    </div>
                                    <div class="product-main-info">
                                        <div class="product-code">MAXI-02</div>
                                        <div class="product-subtitle">Triple Tower with Slides</div>
                                    </div>
                                    <div class="product-specs">
                                        <div>+2 years</div>
                                        <div>33.7m²</div>
                                        <div>67 Users</div>
                                    </div>
                                    <div class="product-divider"></div>
                                </div>
                            </div>
                        </div>
                        <div class="swiper-button-next"></div>
                        <div class="swiper-button-prev"></div>
                    </div>
                </div>
            <?php endif; ?>

        </section>
    </div>

    <!-- Bootstrap JS -->
    <script src="assets/vendor/bootstrap.bundle.min.js"></script>
    <!-- Swiper JS -->
    <script src="assets/vendor/swiper-bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>

    <!-- Dahili Video Lazy Loader -->
    <script>
        window.addEventListener('load', function () {
            var video = document.getElementById('heroVideo');
            if (!video) return;
            video.preload = 'auto';
            video.play().catch(function () {
                video.muted = true;
                video.play();
            });
        });
    </script>
</body>

</html>