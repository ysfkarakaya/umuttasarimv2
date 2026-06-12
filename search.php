<?php
include_once 'inc/functions.php';
$lang = active_lang();
$query = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

$groupedResults = [];
$totalFound = 0;

if ($query !== '') {
    $searchDir = __DIR__ . "/data/lang/{$lang}/urunler/";
    if (is_dir($searchDir)) {
        $files = scandir($searchDir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $content = json_decode(file_get_contents($searchDir . $file), true);
                if (is_array($content)) {
                    foreach ($content as $product) {
                        $searchable = ($product['urun_adi'] ?? '') . ' ' . ($product['urun_kodu'] ?? '') . ' ' . ($product['urun_aciklama'] ?? '');
                        if (mb_stripos($searchable, $query) !== false) {
                            $catName = trim((string) ($product['kat_adi'] ?? ($lang === 'tr' ? 'Diğer' : 'Other')));
                            if ($catName === '')
                                $catName = ($lang === 'tr' ? 'Diğer' : 'Other');

                            if (!isset($groupedResults[$catName])) {
                                $groupedResults[$catName] = [];
                            }
                            $groupedResults[$catName][] = $product;
                            $totalFound++;
                        }
                    }
                }
            }
        }
    }
}

$pageTitle = ($lang === 'tr' ? 'Arama Sonuçları' : 'Search Results') . ': ' . htmlspecialchars($query);
$hasSidebar = true;
include_once 'inc/header.php';
?>

<body class="<?php echo $hasSidebar ? '' : 'no-sidebar'; ?>">

    <?php if ($hasSidebar): ?>
        <!-- Sol Panel (Fixed) -->
        <div class="sidebar">
            <div>
                <div class="sidebar-logo">
                    <img src="assets/img/logo.png" alt="Logo">
                </div>
                <h1 class="sidebar-title">
                    <?php echo $lang === 'tr' ? 'Arama Sonuçları' : 'Search Results'; ?>
                </h1>
            </div>
            <div class="sidebar-footer">
                <?php echo $lang === 'tr' ? 'Aradığınız kriterlere uygun tüm ürünler aşağıda listelenmiştir.' : 'All products matching your search criteria are listed below.'; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Sağ İçerik -->
    <div class="main-content">

        <!-- Üst Menü -->
        <?php include_once 'inc/navbar.php'; ?>

        <!-- Hero Bölümü -->
        <section class="hero-section">
            <div class="hero-card">
                <div class="hero-card-content">
                    <div class="hero-header">
                        <div class="icon-box">
                            <i class="bi bi-search"></i>
                        </div>
                        <div class="hero-header-text">
                            <?php echo $lang === 'tr' ? 'Aradığınızı bulmanıza<br>yardımcı oluyoruz.' : 'Helping you find<br>what you are looking for.'; ?>
                        </div>
                    </div>
                    <h2><?php echo $lang === 'tr' ? 'Arama' : 'Search'; ?></h2>
                    <h3>"<?php echo htmlspecialchars($query); ?>"</h3>
                    <p class="description">
                        <?php
                        if ($totalFound > 0) {
                            echo "<strong>$totalFound</strong> " . ($lang === 'tr' ? 'ürün bulundu.' : 'products found.');
                        } else {
                            echo ($lang === 'tr' ? 'Maalesef sonuç bulunamadı.' : 'Unfortunately, no results found.');
                        }
                        ?>
                    </p>
                    <a href="<?= lang_url('/urunler') ?>" class="see-how"><?php echo $lang === 'tr' ? 'Tüm Ürünler' : 'All Products'; ?>
                        <i class="bi bi-chevron-right"></i></a>
                </div>
                <div class="hero-card-image">
                    <img src="assets/img/breadcrumbs.png" alt="Search">
                </div>
            </div>
        </section>

        <!-- Ürünler Listesi -->
        <section class="products-section">
            <?php if (!empty($groupedResults)): ?>
                <?php foreach ($groupedResults as $categoryName => $products): ?>
                    <div class="series-row">
                        <div class="series-label">
                            <div class="series-top"><?php echo htmlspecialchars($categoryName); ?></div>
                            <div class="series-bottom"><?php echo $lang === 'tr' ? 'Grubu' : 'Group'; ?></div>
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
                                    <a class="product-card swiper-slide" href="<?php echo htmlspecialchars($productHref); ?>">
                                        <img src="<?php echo htmlspecialchars($imageSrc); ?>"
                                            alt="<?php echo htmlspecialchars($productCode); ?>">
                                        <div class="product-info">
                                            <div class="product-dots">
                                                <div class="dot orange"></div>
                                                <div class="dot red"></div>
                                                <div class="dot blue"></div>
                                                <div class="dot yellow"></div>
                                            </div>
                                            <div class="product-main-info">
                                                <div class="product-code"><?php echo htmlspecialchars($productCode); ?></div>
                                                <div class="product-subtitle"><?php echo htmlspecialchars($productName); ?></div>
                                            </div>
                                            <div class="product-divider"></div>
                                            <div class="product-specs">
                                                <div>+2 years</div>
                                                <div>33.7m²</div>
                                                <div>67 Users</div>
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
                <div class="container py-5 text-center">
                    <i class="bi bi-search" style="font-size: 4rem; color: #eee; display: block; margin-bottom: 20px;"></i>
                    <p style="color: #999; font-size: 1.2rem;">
                        <?php echo $lang === 'tr' ? 'Aradığınız kriterlere uygun ürün bulunamadı.' : 'No products found matching your search criteria.'; ?>
                    </p>
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
</body>

</html>