<?php
$hasSidebar = true;
?>
<?php
$pageTitle = "Referanslar";
include_once 'inc/header.php';

// Referanslar verisini dil dosyasına göre yükle
$referanslar_path = "data/lang/{$lang}/referanslar/referanslar.json";
$referanslar = [];
if (file_exists($referanslar_path)) {
    $referanslar = json_decode(file_get_contents($referanslar_path), true);
}

// URL'den gelen parametreleri al (Haritadan yönlendirme için)
$initialRef = isset($_GET['ref']) ? (int) $_GET['ref'] : 0;
$initialProj = isset($_GET['proj']) ? (int) $_GET['proj'] : 0;
?>
<!-- Bootstrap 5 CSS -->
<link href="assets/vendor/bootstrap.min.css" rel="stylesheet">
<!-- Century Gothic Fonts -->
<style>
    @font-face {
        font-family: 'CenturyGothic';
        src: url('assets/fonts/centurygothic.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
    }

    @font-face {
        font-family: 'CenturyGothic';
        src: url('assets/fonts/centurygothic_bold.ttf') format('truetype');
        font-weight: bold;
        font-style: normal;
    }

    * {
        font-family: 'CenturyGothic', sans-serif !important;
    }
</style>
<!-- Bootstrap Icons -->
<!-- Swiper CSS -->
<!-- GLightbox CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css" />
<!-- Custom Style -->
<style>
    /* Materials Page Premium Styles */
    :root {
        --primary-pink: #e91e63;
        --primary-purple: #7b1fa2;
        --text-dark: #333;
        --text-muted: #888;
        --bg-light: #f4f7f6;
    }

    .materials-section {
        position: relative;
        height: 100vh;
        background: url("assets/img/ref-page-bg.png") center/cover no-repeat;
        overflow: hidden;
        padding: 2vh 4vw;
        display: flex;
        align-items: center;
    }

    .map-bg {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: url("assets/img/overlay.png") center/cover no-repeat;
        opacity: 0.1;
        z-index: 1;
        pointer-events: none;
    }

    .reference-detail-container {
        position: relative;
        z-index: 5;
        max-width: 100%;
        padding: 0;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 2vh;
        height: 100%;
        width: 100%;
        justify-content: space-between;
    }

    /* Top Header Row */
    .top-row {
        display: flex;
        justify-content: center;
        align-items: center;
        position: relative;
        padding-bottom: 5px;
        flex-shrink: 0;
    }

    .park-title-box {
        display: flex;
        align-items: center;
        gap: 15px;
        font-size: 1.4rem;
        font-weight: 500;
        color: #555;
        letter-spacing: -0.5px;
    }

    .pink-arrow {
        display: inline-block;
        width: 0;
        height: 0;
        border-style: solid;
    }

    .arrow-left {
        border-width: 8px 12px 8px 0;
        border-color: transparent var(--primary-pink) transparent transparent;
        cursor: pointer;
    }

    .triple-arrow {
        display: flex;
        gap: 4px;
        cursor: pointer;
    }

    .triple-arrow span {
        width: 0;
        height: 0;
        border-style: solid;
        border-width: 6px 0 6px 9px;
        border-color: transparent transparent transparent var(--primary-pink);
        opacity: 0.3;
    }

    .triple-arrow span:first-child {
        opacity: 1;
    }

    .triple-arrow span:nth-child(2) {
        opacity: 0.6;
    }

    .triple-arrow span:nth-child(3) {
        opacity: 0.3;
    }

    .go-to-map-badge {
        position: absolute;
        top: 0;
        right: 0;
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 8px;
    }

    .map-btn {
        background: linear-gradient(135deg, var(--primary-pink) 0%, var(--primary-purple) 100%);
        color: white;
        padding: 12px 20px;
        border-radius: 18px;
        text-decoration: none;
        box-shadow: 0 10px 20px rgba(233, 30, 99, 0.2);
        transition: all 0.3s;
    }

    .map-btn:hover {
        transform: translateY(-3px);
        color: white;
        box-shadow: 0 15px 25px rgba(233, 30, 99, 0.3);
    }

    .map-icon-wrapper {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .map-icon-wrapper i {
        font-size: 2.2rem;
        line-height: 1;
    }

    .go-text {
        font-size: 0.75rem;
        font-weight: 700;
        margin-top: 2px;
    }

    .coords {
        font-size: 0.8rem;
        color: #888;
        text-align: right;
        line-height: 1.2;
        font-weight: 600;
    }

    /* Gallery Row */
    .gallery-row-wrapper {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        position: relative;
        width: 100%;
    }

    .gallery-row {
        display: grid;
        grid-template-columns: 1fr 1.1fr 1fr;
        gap: 15px;
        flex: 0.8;
        perspective: 1200px;
        padding: 2px 0;
        align-items: center;
        max-height: 28vh;
        min-height: 0;
        cursor: grab;
        user-select: none;
        overflow: hidden;
        /* Prevent vertical overflow */
    }

    .gallery-row:active {
        cursor: grabbing;
    }

    .gallery-card {
        display: none;
        /* Hide all by default, show only first 3 via nth-child */
        padding: 0;
        overflow: hidden;
        transition: all 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
        transform-style: preserve-3d;
        aspect-ratio: 1 / 1;
        width: 100%;
        position: relative;
    }

    .gallery-row .gallery-card:nth-child(1),
    .gallery-row .gallery-card:nth-child(2),
    .gallery-row .gallery-card:nth-child(3) {
        display: block;
    }

    .gallery-card::after {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: linear-gradient(45deg,
                transparent 0%,
                rgba(255, 255, 255, 0.1) 45%,
                rgba(255, 255, 255, 0.3) 50%,
                rgba(255, 255, 255, 0.1) 55%,
                transparent 100%);
        transform: rotate(-45deg);
        pointer-events: none;
        opacity: 0.6;
    }

    .gallery-card img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center center;
        display: block;
        /* border-radius: 50px; */
        transition: transform 10s cubic-bezier(0.22, 0.61, 0.36, 1), 
                   object-position 10s cubic-bezier(0.22, 0.61, 0.36, 1);
    }

    .gallery-card:hover img {
        transform: scale(1.1);
        object-position: 55% 45%;
    }

    @keyframes floatingCard {

        0%,
        100% {
            transform: translateY(0) rotateY(var(--rot, 0deg)) scale(var(--sc, 1));
        }

        50% {
            transform: translateY(-15px) rotateY(var(--rot, 0deg)) scale(var(--sc, 1));
        }
    }



    /* Reference Image 3D Perspective - Enhanced Animations */
    .gallery-row .gallery-card:nth-child(1) {
        --rot: 30deg;
        --sc: 0.88;
        transform: rotateY(var(--rot)) scale(var(--sc));
        transform-origin: center center;
        opacity: 0.9;
        z-index: 1;
        /* filter: blur(1.5px) grayscale(20%); */
    }

    .gallery-row .gallery-card:nth-child(3) {
        --rot: -30deg;
        --sc: 0.88;
        transform: rotateY(var(--rot)) scale(var(--sc));
        transform-origin: center center;
        opacity: 0.9;
        z-index: 1;
        /* filter: blur(1.5px) grayscale(20%); */
    }

    .gallery-row .gallery-card.main-card {
        --rot: 0deg;
        --sc: 1.05;
        transform: scale(var(--sc)) translateZ(80px);
        z-index: 10;
        opacity: 1;
        filter: blur(0) grayscale(0);
        /* box-shadow: 0 50px 100px rgba(233, 30, 99, 0.15); */
        animation-delay: -3s;
    }

    .nav-arrow {
        width: 45px;
        height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: transform 0.3s;
    }

    .left-nav {
        transform: translateX(80px);
        /* Pulling in from left */
    }

    .left-nav:hover {
        transform: translateX(80px) scale(1.15);
    }

    .right-nav {
        transform: translateX(-80px);
        /* Pulling in from right */
    }

    .right-nav:hover {
        transform: translateX(-80px) scale(1.15);
    }

    .nav-arrow img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    /* .nav-arrow:hover {
            transform: scale(1.15);
        } */

    /* Logo Swiper - 3D Perspective Arc */
    .logo-swiper-section {
        position: relative;
        padding: 10px 0 0px;
        flex-shrink: 0;
        overflow: visible;
        transform: scale(0.8);
        transform-origin: center center;
    }

    .logo-sphere-swiper {
        padding: 40px 0 50px !important;
        overflow: visible !important;
        width: 100%;
        margin: 0;
    }

    .logo-sphere-swiper .swiper-wrapper {
        /* Important: disable default flex alignment to allow individual Y transforms */
        align-items: center;
    }

    .logo-sphere-swiper .swiper-slide {
        transition: transform 0.5s ease, opacity 0.5s ease, filter 0.5s ease;
        transform-origin: center center;
        cursor: pointer;
    }

    .logo-sphere {
        width: clamp(100px, 11vw, 160px);
        height: clamp(100px, 11vw, 160px);
        background: url("assets/img/ref-bg.png") center/cover no-repeat;
        border-radius: 50%;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12), 0 5px 15px rgba(0, 0, 0, 0.08);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 22px;
        position: relative;
        margin: 0 auto;
        transition: transform 0.5s ease, box-shadow 0.5s ease;
    }

    .logo-sphere::after {
        display: none;
    }

    .logo-sphere img {
        max-width: 85%;
        max-height: 85%;
        object-fit: contain;
        filter: drop-shadow(0 3px 6px rgba(0, 0, 0, 0.08));
    }

    .nav-curved-arrow {
        position: absolute;
        bottom: 5px;
        left: 50%;
        transform: translateX(-50%);
        width: 100%;
        display: flex;
        justify-content: space-between;
        align-items: center;
        z-index: 200;
        /* Must be higher than logo sphere z-index (100) */
        pointer-events: none;
        /* Let clicks pass to children */
    }

    .curved-line {
        flex: 1;
        height: 40px;
        border-bottom: 2px solid #e0e0e0;
        border-radius: 0 0 50% 50%;
        margin: 0 20px;
        opacity: 0.5;
    }

    .swipe-arrow {
        width: 80px;
        height: 80px;
        cursor: pointer;
        transition: transform 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: auto;
        /* Required to override parent's pointer-events: none */
        z-index: 210;
    }

    .swipe-arrow img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .swipe-arrow:hover {
        transform: scale(1.15);
    }

    /* Bottom Row */
    .bottom-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        /* Balanced vertical alignment */
        margin-top: -30px;
        /* Reducing gap manually */
        padding-bottom: 2vh;
        flex-shrink: 0;
        width: 100%;
    }

    .apps-label {
        font-size: 1rem;
        color: #999;
        margin-bottom: 5px;
    }

    .city-name-wrapper {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .city-name {
        font-size: 2.22rem;
        font-weight: 400;
        color: var(--primary-pink);
        line-height: 1.1;
    }

    .triple-arrow.left span {
        border-width: 6px 9px 6px 0;
        border-color: transparent var(--primary-pink) transparent transparent;
    }

    .product-series-box {
        display: flex;
        gap: 30px;
        align-items: center;
    }

    .series-info {
        text-align: right;
        display: flex;
        flex-direction: column;
        gap: 1px;
        color: #777;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .series-info strong {
        font-size: 1rem;
        color: #444;
        margin-bottom: 3px;
    }

    /* Stacked Card Slider - Perspective Layout */
    .stacked-product-slider {
        position: relative;
        width: 300px;
        /* Increased width for horizontal spread */
        height: 120px;
        cursor: pointer;
        margin-left: 10px;
    }

    .stacked-card {
        position: absolute;
        width: 140px;
        height: 105px;
        background: #fff;
        border-radius: 20px;
        /* Softer corners like reference */
        padding: 12px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        transition: all 0.6s cubic-bezier(0.23, 1, 0.32, 1);
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid rgba(0, 0, 0, 0.03);
    }

    .stacked-card img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }

    /* Horizontal Perspective Positions */
    .card-1 {
        z-index: 5;
        transform: translateX(0) scale(1);
        opacity: 1;
    }

    .card-2 {
        z-index: 4;
        transform: translateX(75px) scale(0.92);
        opacity: 0.85;
    }

    .card-3 {
        z-index: 3;
        transform: translateX(145px) scale(0.85);
        opacity: 0.65;
    }

    /* Responsive */
    /* Responsive Design System */
    @media (max-width: 1400px) {
        .gallery-row-wrapper {
            max-width: 800px;
            gap: 40px;
        }

        .left-nav {
            transform: translateX(40px);
        }

        .left-nav:hover {
            transform: translateX(40px) scale(1.1);
        }

        .right-nav {
            transform: translateX(-40px);
        }

        .right-nav:hover {
            transform: translateX(-40px) scale(1.1);
        }
    }

    @media (max-width: 1200px) {
        .sidebar {
            display: none !important;
        }

        .main-content {
            margin-left: 0;
            width: 100%;
        }

        .materials-section {
            height: auto;
            min-height: 100vh;
            overflow-y: auto;
            padding: 40px 20px;
        }

        .reference-detail-container {
            height: auto;
            gap: 40px;
        }

        .top-row {
            flex-direction: column;
            gap: 20px;
            text-align: center;
        }

        .go-to-map-badge {
            position: static;
            align-items: center;
        }

        .coords {
            text-align: center;
        }

        .gallery-row-wrapper {
            max-width: 100%;
            gap: 15px;
        }

        .left-nav,
        .right-nav {
            transform: none !important;
            position: absolute;
            top: 50%;
            z-index: 100;
        }

        .left-nav {
            left: -10px;
        }

        .right-nav {
            right: -10px;
        }
    }

    @media (max-width: 992px) {
        .park-title-box {
            font-size: 1.2rem;
        }

        .city-name {
            font-size: 1.7rem;
        }

        .logo-swiper-section {
            height: 180px;
        }

        .logo-sphere {
            width: 105px;
            height: 105px;
        }

        .bottom-row {
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 35px;
            padding-bottom: 30px;
        }

        .series-info {
            text-align: center;
        }

        .product-series-box {
            flex-direction: column;
            gap: 15px;
            width: 100%;
        }

        .stacked-product-slider {
            margin-left: 0;
            transform: scale(0.85);
            width: 240px;
        }
    }

    @media (max-width: 768px) {
        .materials-section {
            padding: 20px 10px;
            height: auto;
            min-height: 100vh;
            overflow-y: auto;
        }

        .reference-detail-container {
            height: auto;
            gap: 30px;
        }

        .top-row {
            flex-direction: column;
            gap: 15px;
        }

        .go-to-map-badge {
            position: static;
            align-items: center;
        }

        .reference-detail-container {
            height: auto;
            gap: 50px;
            /* Increased gap */
        }

        .gallery-row-wrapper {
            justify-content: center;
            gap: 0;
            margin-bottom: 20px;
            /* Force distance from slider */
        }

        /* Useful Gallery: Focus on 1 image, hide arrows */
        .gallery-row {
            grid-template-columns: 1fr;
            gap: 0;
            width: 100%;
            margin: 0 auto;
            flex: none;
        }

        .gallery-card:not(.main-card) {
            display: none;
        }

        .gallery-card.main-card {
            transform: none !important;
            width: 100%;
        }

        .gallery-card img {
            border-radius: 20px;
        }

        .nav-arrow {
            display: none !important;
        }

        /* Hide Gallery Arrows */
        .nav-curved-arrow {
            display: none !important;
        }

        .logo-swiper-section {
            margin-top: 30px;
            /* Added spacing from top on mobile */
        }

        .logo-sphere {
            width: 90px;
            height: 90px;
            padding: 15px;
        }

        .city-name {
            font-size: 1.5rem;
        }

        .stacked-product-slider {
            width: 130px;
            transform: scale(0.8);
            margin: 0 auto;
        }

        .card-1 {
            transform: none;
        }

        .card-2 {
            transform: translate(15px, 10px) scale(0.9);
        }

        .card-3 {
            transform: translate(30px, 20px) scale(0.8);
        }
    }

    .card-2 {
        transform: translateX(50px) scale(0.9);
    }

    .card-3 {
        transform: translateX(95px) scale(0.8);
    }

    /* Tighten gallery arrows on mobile */
    .left-nav,
    .right-nav {
        top: 40%;
    }

    .left-nav {
        left: -15px;
    }

    .right-nav {
        right: -15px;
    }
    }
</style>
</head>

<body class="materials-page">

    <div class="sidebar">
        <div>
            <div class="sidebar-logo">
                <img src="assets/img/logo.png" alt="Logo">
            </div>
            <h1 class="sidebar-title">
                <?= statik('sidebar_title_references') ?>
            </h1>
        </div>
        <div class="sidebar-footer">
            <?= statik('sidebar_footer') ?>
        </div>
    </div>

    <!-- Sağ İçerik -->
    <div class="main-content">

        <!-- Üst Menü -->
        <?php include_once 'inc/navbar.php'; ?>

        <section class="materials-section">
            <div class="map-bg"></div>

            <div class="reference-detail-container">
                <!-- Top Header Row -->
                <div class="top-row">
                    <div class="park-title-box">
                        <span class="pink-arrow arrow-left"></span>
                        <span
                            id="project-title"><?= isset($referanslar[0]['projeler'][0]['proje_adi']) ? $referanslar[0]['projeler'][0]['proje_adi'] : '...' ?></span>
                        <div class="triple-arrow"><span></span><span></span><span></span></div>
                    </div>
                    <div class="go-to-map-badge">
                        <?php
                        $initLat = isset($referanslar[0]['projeler'][0]['lat']) ? $referanslar[0]['projeler'][0]['lat'] : '';
                        $initLng = isset($referanslar[0]['projeler'][0]['lng']) ? $referanslar[0]['projeler'][0]['lng'] : '';
                        ?>
                        <a href="harita?lat=<?= $initLat ?>&lng=<?= $initLng ?>" class="map-btn" target="_blank">
                            <div class="map-icon-wrapper">
                                <i class="bi bi-geo-alt-fill"></i>
                                <div class="go-text"><?= statik('go_to_map') ?></div>
                            </div>
                        </a>
                        <div class="coords">
                            <?= isset($referanslar[0]['projeler'][0]['lat']) ? $referanslar[0]['projeler'][0]['lat'] : '...' ?><br>
                            <?= isset($referanslar[0]['projeler'][0]['lng']) ? $referanslar[0]['projeler'][0]['lng'] : '...' ?>
                        </div>
                    </div>
                </div>

                <!-- Gallery Row -->
                <div class="gallery-row-wrapper">
                    <div class="nav-arrow left-nav"><img src="assets/img/right-arrow2.png" alt="Prev"></div>
                    <div class="gallery-row">
                        <?php
                        $first_project_images = isset($referanslar[0]['projeler'][0]['proje_gorselleri']) ? $referanslar[0]['projeler'][0]['proje_gorselleri'] : [];
                        $total_images = count($first_project_images);
                        foreach ($first_project_images as $index => $img):
                            // If only 1 image, it should be main-card. If 2 or more, 2nd one is main-card.
                            $isMain = ($total_images === 1 && $index === 0) || ($total_images > 1 && $index === 1);
                            ?>
                            <div class="gallery-card<?= $isMain ? ' main-card' : '' ?>">
                                <a href="<?= $img['gorsel'] ?>" class="glightbox" data-gallery="gallery-projeler">
                                    <img src="<?= $img['gorsel'] ?>" alt="<?= $img['gorsel_adi'] ?>">
                                </a>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($first_project_images)): ?>
                            <div class="gallery-card"><img src="assets/img/park2.png" alt="Reference 1"></div>
                            <div class="gallery-card main-card"><img src="assets/img/park2.png" alt="Reference 2"></div>
                            <div class="gallery-card"><img src="assets/img/park2.png" alt="Reference 3"></div>
                        <?php endif; ?>
                    </div>
                    <div class="nav-arrow right-nav"><img src="assets/img/left-arrow2.png" alt="Next"></div>
                </div>

                <!-- Logo Swiper -->
                <div class="logo-swiper-section">
                    <div class="swiper logo-sphere-swiper">
                        <div class="swiper-wrapper">
                            <?php foreach ($referanslar as $index => $ref): ?>
                                <div class="swiper-slide" data-index="<?= $index ?>">
                                    <div class="logo-sphere">
                                        <img src="<?= $ref['logo'] ?>" alt="<?= $ref['adi'] ?>">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="nav-curved-arrow">
                        <div class="swipe-arrow prev-logo"><img src="assets/img/left-arrow.png" alt="Prev"></div>
                        <div class="curved-line"></div>
                        <div class="swipe-arrow next-logo"><img src="assets/img/right-arrow.png" alt="Next"></div>
                    </div>
                </div>

                <!-- Bottom Row -->
                <div class="bottom-row">
                    <div class="applications-container">
                        <div class="apps-label"><?= statik('applications_label') ?></div>
                        <div class="city-name-wrapper">
                            <span
                                class="city-name"><?= isset($referanslar[0]['adi']) ? $referanslar[0]['adi'] : '...' ?></span>
                            <div class="triple-arrow"><span></span><span></span><span></span></div>
                        </div>
                    </div>

                    <div class="product-series-box">
                        <div class="series-info">
                            <?php
                            $first_urun_gorselleri = isset($referanslar[0]['projeler'][0]['urun_gorselleri']) ? $referanslar[0]['projeler'][0]['urun_gorselleri'] : [];
                            if (!empty($first_urun_gorselleri)):
                                echo "<strong>" . statik('product_series_label') . "</strong>";
                                foreach ($first_urun_gorselleri as $ug) {
                                    if ($ug['gorsel_adi'])
                                        echo "<span>{$ug['gorsel_adi']}</span>";
                                }
                            endif; ?>
                        </div>
                        <div class="stacked-product-slider">
                            <?php foreach ($first_urun_gorselleri as $index => $ug): ?>
                                <div class="stacked-card card-<?= $index + 1 ?>"><img src="<?= $ug['gorsel'] ?>"
                                        alt="<?= $ug['gorsel_adi'] ?>"></div>
                            <?php endforeach; ?>
                            <?php if (empty($first_urun_gorselleri)): ?>
                                <div class="stacked-card card-1"><img src="assets/img/materyal-1.png" alt=""></div>
                                <div class="stacked-card card-2"><img src="assets/img/materyal-2.png" alt=""></div>
                                <div class="stacked-card card-3"><img src="assets/img/materyal-3.png" alt=""></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </div>

    <!-- Bootstrap JS -->
    <script src="assets/vendor/bootstrap.bundle.min.js"></script>
    <script src="assets/vendor/swiper-bundle.min.js"></script>
    <!-- GLightbox JS -->
    <script src="https://cdn.jsdelivr.net/gh/mcstudios/glightbox/dist/js/glightbox.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Lightbox Initialization
            let lightbox = GLightbox({ selector: '.glightbox' });

            const translations = {
                product_series_label: "<?= statik('product_series_label') ?>"
            };

            // Referans verilerini JS'ye aktar
            const referencesData = <?= json_encode($referanslar) ?>;
            let currentRefIndex = <?= $initialRef ?>;
            let currentProjIndex = <?= $initialProj ?>;

            function updateProjectDetails(refIndex, projIndex = 0) {
                const ref = referencesData[refIndex];
                if (!ref) return;

                const projectCount = ref.projeler ? ref.projeler.length : 0;

                // Index bounds check and loop
                if (projIndex >= projectCount) projIndex = 0;
                if (projIndex < 0) projIndex = projectCount - 1;

                currentRefIndex = refIndex;
                currentProjIndex = projIndex;

                const project = ref.projeler && ref.projeler.length > 0 ? ref.projeler[currentProjIndex] : null;

                // City/Reference Name update
                const cityName = document.querySelector('.city-name');
                if (cityName) cityName.textContent = ref.adi;

                // Visibility of arrows
                const prevProjArrow = document.querySelector('.arrow-left');
                const nextProjArrow = document.querySelector('.triple-arrow');
                if (prevProjArrow && nextProjArrow) {
                    if (projectCount > 1) {
                        prevProjArrow.style.display = 'inline-block';
                        nextProjArrow.style.display = 'flex';
                    } else {
                        prevProjArrow.style.display = 'none';
                        nextProjArrow.style.display = 'none';
                    }
                }

                if (project) {
                    // Project Title update
                    const titleSpan = document.getElementById('project-title');
                    if (titleSpan) titleSpan.textContent = project.proje_adi;

                    // Gallery Row update
                    const galleryRow = document.querySelector('.gallery-row');
                    if (galleryRow && project.proje_gorselleri) {
                        galleryRow.innerHTML = '';
                        const totalImgs = project.proje_gorselleri.length;
                        project.proje_gorselleri.forEach((img, idx) => {
                            const card = document.createElement('div');
                            const isMain = (totalImgs === 1 && idx === 0) || (totalImgs > 1 && idx === 1);
                            card.className = 'gallery-card' + (isMain ? ' main-card' : '');
                            card.innerHTML = `<a href="${img.gorsel}" class="glightbox" data-gallery="gallery-projeler">
                                <img src="${img.gorsel}" alt="${img.gorsel_adi || project.proje_adi}">
                            </a>`;
                            galleryRow.appendChild(card);
                        });
                        // Refresh Lightbox
                        if (lightbox) lightbox.reload();

                        // Update Coords
                        const coordsDiv = document.querySelector('.coords');
                        const mapBtn = document.querySelector('.map-btn');
                        if (coordsDiv && project.lat && project.lng) {
                            coordsDiv.innerHTML = `${project.lat}<br>${project.lng}`;
                        }
                        if (mapBtn && project.lat && project.lng) {
                            mapBtn.href = `harita?lat=${project.lat}&lng=${project.lng}`;
                        }
                    }

                    // Product Series Info update
                    const seriesInfo = document.querySelector('.series-info');
                    if (seriesInfo && project.urun_gorselleri) {
                        seriesInfo.innerHTML = `<strong>${translations.product_series_label}</strong>`;
                        project.urun_gorselleri.forEach(ug => {
                            if (ug.gorsel_adi) {
                                const span = document.createElement('span');
                                span.textContent = ug.gorsel_adi;
                                seriesInfo.appendChild(span);
                            }
                        });
                    }

                    // Stacked Slider update
                    const stackedSlider = document.querySelector('.stacked-product-slider');
                    if (stackedSlider && project.urun_gorselleri) {
                        stackedSlider.innerHTML = '';
                        project.urun_gorselleri.forEach((ug, idx) => {
                            const card = document.createElement('div');
                            card.className = `stacked-card card-${idx + 1}`;
                            card.innerHTML = `<img src="${ug.gorsel}" alt="${ug.gorsel_adi || ''}">`;
                            stackedSlider.appendChild(card);
                        });
                    }
                }
            }

            // Project Change Listeners
            document.querySelector('.arrow-left').addEventListener('click', () => {
                updateProjectDetails(currentRefIndex, currentProjIndex - 1);
            });
            document.querySelector('.triple-arrow').addEventListener('click', () => {
                updateProjectDetails(currentRefIndex, currentProjIndex + 1);
            });

            // Simple Gallery Rotation
            const galleryCards = document.querySelectorAll('.gallery-row .gallery-card');
            const leftNav = document.querySelector('.left-nav');
            const rightNav = document.querySelector('.right-nav');

            function rotateGallery(dir) {
                const gallery = document.querySelector('.gallery-row');
                const cards = gallery.querySelectorAll('.gallery-card');
                if (cards.length < 2) return;

                if (dir === 'next') {
                    gallery.appendChild(gallery.firstElementChild);
                } else {
                    gallery.prepend(gallery.lastElementChild);
                }

                // Update main-card class
                const updatedCards = gallery.querySelectorAll('.gallery-card');
                updatedCards.forEach(c => c.classList.remove('main-card'));
                // 2nd element is always main card if there are at least 2
                if (updatedCards.length >= 2) {
                    updatedCards[1].classList.add('main-card');
                } else {
                    updatedCards[0].classList.add('main-card');
                }
            }

            leftNav.addEventListener('click', () => rotateGallery('prev'));
            rightNav.addEventListener('click', () => rotateGallery('next'));

            // Improved Drag/Swipe Support for Gallery
            let startX = 0;
            let currentX = 0;
            let isDragging = false;
            const galleryRow = document.querySelector('.gallery-row');
            const galleryWrapper = document.querySelector('.gallery-row-wrapper');

            const handleDragStart = (x) => {
                startX = x;
                isDragging = true;
                galleryRow.style.transition = 'none';
                galleryRow.querySelectorAll('.gallery-card').forEach(card => {
                    card.style.transition = 'none';
                });
            };

            const handleDragMove = (x) => {
                if (!isDragging) return;
                currentX = x;
                const diff = currentX - startX;

                // Visual feedback: subtle translation during drag
                const moveX = diff * 0.4;
                galleryRow.style.transform = `translateX(${moveX}px)`;

                // Add a slight tilt/skew for more life
                const rotation = diff * 0.02;
                galleryRow.style.transform += ` rotateY(${rotation}deg)`;
            };

            const handleDragEnd = (x) => {
                if (!isDragging) return;
                const diff = startX - x;

                // Reset with a smooth transition
                galleryRow.style.transition = 'all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1)';
                galleryRow.style.transform = 'translateX(0) rotateY(0deg)';

                // Reset internal card transitions
                galleryRow.querySelectorAll('.gallery-card').forEach(card => {
                    card.style.transition = 'all 0.8s cubic-bezier(0.34, 1.56, 0.64, 1)';
                });

                if (Math.abs(diff) > 80) { // Threshold for change
                    if (diff > 0) rotateGallery('next');
                    else rotateGallery('prev');
                }
                isDragging = false;
            };

            // Touch Events
            galleryRow.addEventListener('touchstart', (e) => handleDragStart(e.touches[0].clientX), { passive: true });
            galleryWrapper.addEventListener('touchmove', (e) => handleDragMove(e.touches[0].clientX), { passive: true });
            galleryRow.addEventListener('touchend', (e) => handleDragEnd(e.changedTouches[0].clientX), { passive: true });

            // Mouse Events
            galleryRow.addEventListener('mousedown', (e) => {
                // Don't prevent default here to allow clicking anchors, 
                // but track if we moved enough to call it a drag.
                handleDragStart(e.clientX);
            });
            window.addEventListener('mousemove', (e) => {
                if (isDragging) handleDragMove(e.clientX);
            });
            window.addEventListener('mouseup', (e) => {
                if (isDragging) handleDragEnd(e.clientX);
            });

            // Logo Swiper with 3D Perspective Arc Effect
            const logoSwiper = new Swiper('.logo-sphere-swiper', {
                slidesPerView: 9,
                centeredSlides: true,
                loop: true,
                watchSlidesProgress: true,
                slideToClickedSlide: true,
                spaceBetween: -40,
                speed: 600,
                navigation: {
                    nextEl: '.next-logo',
                    prevEl: '.prev-logo',
                },
                initialSlide: <?= $initialRef ?>,
                on: {
                    init: function () {
                        updateProjectDetails(currentRefIndex, currentProjIndex);
                    },
                    slideChange: function () {
                        if (this.initialized && this.realIndex !== currentRefIndex) {
                            updateProjectDetails(this.realIndex, 0);
                        }
                    },
                    progress: function () {
                        const slides = this.slides;
                        for (let i = 0; i < slides.length; i++) {
                            const slideProgress = slides[i].progress;
                            const absProgress = Math.abs(slideProgress);
                            const direction = slideProgress === 0 ? 0 : (slideProgress > 0 ? 1 : -1);

                            // --- 3D Perspective Arc Calculation ---
                            // Scale: center=1.4, edges shrink dramatically like reference image
                            let scale = Math.max(0.32, 1.38 - (absProgress * 0.2));

                            // TranslateX: piecewise compression to keep outer-most slides right behind previous ones
                            // First levels keep readable spread, deeper levels get tightly stacked
                            const nearDepth = Math.min(absProgress, 2.2);
                            const farDepth = Math.max(absProgress - 2.2, 0);
                            let translateX = -direction * ((nearDepth * 30) + (farDepth * 1));

                            // Keep logos on a straight line (no circular/arc motion)
                            let translateY = 0;

                            // Z-index: center on top
                            let zIndex = 100 - Math.round(absProgress * 10);

                            // Opacity: center=1, gentle fade
                            let opacity = Math.max(0.3, 1 - (absProgress * 0.15));

                            // Blur: depth of field effect
                            let blur = Math.min(absProgress * 0.9, 3.2);

                            // Hide slides beyond visible range
                            if (absProgress > 4.5) {
                                opacity = 0;
                            }

                            slides[i].style.transform = `translateX(${translateX}px) translateY(${translateY}px) scale(${scale})`;
                            slides[i].style.zIndex = zIndex;
                            slides[i].style.opacity = opacity;
                            slides[i].style.filter = `blur(${blur}px)`;
                        }
                    },
                    setTransition: function (speed) {
                        const slides = this.slides;
                        for (let i = 0; i < slides.length; i++) {
                            slides[i].style.transition = `transform ${speed}ms ease, opacity ${speed}ms ease, filter ${speed}ms ease`;
                        }
                    }
                },
                breakpoints: {
                    320: { slidesPerView: 5, spaceBetween: -28 },
                    768: { slidesPerView: 7, spaceBetween: -38 },
                    1024: { slidesPerView: 9, spaceBetween: -48 }
                }
            });

            // Click any logo and bring it to center (loop-safe)
            logoSwiper.on('click', function (swiper) {
                if (!swiper.clickedSlide) return;
                const realIndex = parseInt(swiper.clickedSlide.getAttribute('data-swiper-slide-index'), 10);
                if (!Number.isNaN(realIndex)) {
                    swiper.slideToLoop(realIndex, 600);
                }
            });

            // Simple Stacked Card Rotation
            const stackedCards = document.querySelectorAll('.stacked-card');
            let currentStackIndex = 0;

            function rotateStack() {
                stackedCards.forEach((card, index) => {
                    card.classList.remove('card-1', 'card-2', 'card-3');
                    let position = (index - currentStackIndex + 3) % 3;
                    card.classList.add(`card-${position + 1}`);
                });
                currentStackIndex = (currentStackIndex + 1) % 3;
            }

            // Click on stacked slider to rotate
            document.querySelector('.stacked-product-slider').addEventListener('click', rotateStack);

            // Auto rotate every 3 seconds
            setInterval(rotateStack, 3000);
        });
    </script>
</body>

</html>