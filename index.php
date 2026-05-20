<?php
$hasSidebar = false;
$pageTitle = "";
include_once 'inc/header.php';
?>

<style>
    /* Desktop layout optimization */
    @media (min-width: 992px) {
        .hero-section {
            flex: 1;
            display: flex !important;
            flex-direction: column;
            justify-content: space-between;
            padding: 4rem 3rem 2rem 3rem !important;
            /* Balanced padding */
            overflow: hidden;
            position: relative;
        }

        .hero-card {
            margin: auto !important;
            /* Centers it in the available flex space */
            max-width: 900px;
            width: 90%;
            min-height: 430px;
            padding: 1.8rem 3.5rem 2.5rem 3.5rem;
            border-radius: 10px;
            box-shadow: 0 40px 80px rgba(0, 0, 0, 0.12);
        }

        .hero-card-content {
            padding-right: 48%;
        }

        .hero-card-image {
            width: 550px;
            height: 300px;
            right: -60px;
            box-shadow: 20px 40px 80px rgba(0, 0, 0, 0.4);
            border-radius: 30px;
        }

        .yt-lazy-wrapper {
            width: 420px;
            height: 320px;
            right: -60px;
            border-radius: 30px;
        }

        .hero-card h2 {
            font-size: 1.8rem;
            margin-bottom: 0.4rem;
        }

        .hero-card h3 {
            font-size: 1.4rem;
            /* margin-bottom: 1.2rem; */
        }

        .hero-card .description {
            font-size: 1rem;
            max-width: 400px;
            margin-bottom: 1.8rem;
            line-height: 1.5;
        }

        .hero-header {
            margin-bottom: 1.5rem;
        }

        .hero-header-text {
            font-size: 1.3rem;
            line-height: 1.4;
        }

        .icon-box img {
            width: 65px;
        }

        .see-how {
            font-size: 1.15rem;
        }

        .hero-categories {
            margin-top: 0 !important;
            padding-bottom: 2rem;
        }

        .category-icon {
            height: 150px !important;
        }

        .category-label {
            font-size: 1rem;
        }
    }
</style>
</head>

<body class="<?php echo $hasSidebar ? '' : 'no-sidebar'; ?> index-page">

    <?php if ($hasSidebar): ?>
        <!-- Sol Panel (Fixed) -->
        <div class="sidebar">
            <div>
                <!-- Örnek Logo SVG -->
                <div class="sidebar-logo">
                    <img src="assets/img/logo.png" alt="Logo">
                </div>
                <h1 class=" sidebar-title">
                    Ticari & Kamusal Oyun Grubu Ekipmanları
                </h1>
            </div>
            <div class="sidebar-footer">
                Oyun ürünlerimiz dört temel kategoriye ayrılmıştır. Aşağıdan keşfedebilirsiniz.
            </div>
        </div>
    <?php endif; ?>

    <!-- Ana İçerik -->
    <div class="main-content">

        <?php
        $isIndex = true;
        include_once 'inc/navbar.php';
        ?>

        <!-- Hero Bölümü (Görseldeki içerikle) -->
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
                    <!--
                        preload="none" → sayfa yüklenene kadar video indirilmez.
                        poster        → video yerine önce bu resim gösterilir.
                        Sayfa hazır olunca JS preload'u açar ve play() tetikler.
                    -->
                    <video id="heroVideo" poster="assets/img/breadcrumbs.png" preload="none" muted loop playsinline
                        style="width:100%;height:100%;object-fit:cover;display:block;border-radius:inherit;">
                        <source src="assets/video.mp4" type="video/mp4">
                    </video>
                </div>
            </div>

            <script>
                window.heroBackgrounds = <?php
                $bgs = [];
                if ($heroCard && !empty($heroCard['detaylar'])) {
                    foreach ($heroCard['detaylar'] as $detay) {
                        $bgs[] = [
                            'image' => $detay['kart_detay_gorsel'],
                            'name' => $detay['kart_detay_adi']
                        ];
                    }
                }
                echo json_encode($bgs);
                ?>;
            </script>

            <!-- Hero Alt Kategoriler -->
            <div class="hero-categories">
                <?php
                $categories = get_categories_config($lang);
                foreach ($categories as $index => $cat):
                    $words = explode(' ', $cat['kat_adi'], 2);
                    $label = $words[0];
                    $sublabel = isset($words[1]) ? $words[1] : '';
                    $bgIndex = $index + 1;
                    ?>
                    <div class="category-item">
                        <div class="category-icon">
                            <img src="<?= $cat['kat_resim'] ?>" class="img-normal" alt="<?= $cat['kat_adi'] ?>">
                            <img src="<?= $cat['kat_resim2'] ?>" class="img-hover" alt="<?= $cat['kat_adi'] ?>">
                        </div>
                        <div class="category-label"><?= $label ?></div>
                        <?php if ($sublabel): ?>
                            <div class="category-sublabel"><?= $sublabel ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Ürünler Listesi -->

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
            /* Sayfa hazır olunca indirmeye başla ve oynat */
            video.preload = 'auto';
            video.play().catch(function () {
                /* Bazı tarayıcılar sessiz bile olsa play() reddedebilir, tekrar dene */
                video.muted = true;
                video.play();
            });
        });
    </script>
</body>

</html>