<?php
include_once 'inc/functions.php';
$lang = active_lang();
$filePath = __DIR__ . "/data/lang/{$lang}/kartlar/tasarim.json";
$designData = null;
$designItems = [];

if (file_exists($filePath)) {
    $data = json_decode(file_get_contents($filePath), true);
    $designData = $data[0] ?? null;
    $designItems = $designData['detaylar'] ?? [];
}

$hasSidebar = true;
$pageTitle = $designData['kart_adi'] ?? "Tasarım";
include_once 'inc/header.php';
?>
<style>
    /* Materials Page Specific Styles */
    .materials-page .sidebar-title {
        font-size: 3.2rem;
        line-height: 1.1;
        margin: 0;
    }

    /* Materials Section Styling */
    .materials-section {
        padding: 40px 0 0 0;
        position: relative;
        min-height: 100vh;
        height: 100%;
        overflow: hidden;
        background-image: url('assets/img/tasarim-bg.png');
        background-size: cover;
        background-position: center;
        display: flex;
        align-items: flex-start;
    }

    .materials-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(to right, rgba(0, 0, 0, 0.6) 0%, rgba(0, 0, 0, 0.2) 100%);
        z-index: 1;
    }

    .materials-section .container-fluid {
        position: relative;
        z-index: 2;
        padding: 0 2%;
    }

    .main-heading {
        font-family: 'Poppins', sans-serif;
        /* font-weight: 800; */
        font-size: 1.8rem;
        line-height: 1.15;
        text-align: right;
        color: #fff;
        text-transform: uppercase;
        margin: 0;
        letter-spacing: -0.2px;
        max-width: 400px;
    }

    .text-pink {
        color: #f43f5e;
    }

    .text-blue {
        color: #06b6d4;
    }

    .vertical-divider {
        width: 2px;
        height: 140px;
        background-color: #fff;
        margin: 0 30px;
        opacity: 0.9;
    }

    .design-content-right {
        color: rgba(255, 255, 255, 0.95);
        font-family: 'Poppins', sans-serif;
        font-size: 1rem;
        line-height: 1.5;
        max-width: 750px;
        border-left: 4px solid;
        padding-left: 15px;
    }


    .design-content-right p {
        margin-bottom: 8px;
    }

    .title-highlight {
        font-size: 0.88rem;
        font-weight: 700;
        color: #fff;
        margin-bottom: 3px !important;
    }

    .bold-text {
        font-weight: 700;
        color: #fff;
    }

    /* Mobile Adjustments - vertical divider hide */
    @media (max-width: 1199px) {
        .vertical-divider {
            display: none;
        }
    }

    /* Desktop: keep overflow hidden, single viewport */
    @media (min-width: 1200px) {
        body.materials-page {
            overflow: hidden !important;
        }

        .materials-page .main-content {
            height: 100vh !important;
            overflow: hidden !important;
        }

        .materials-section {
            min-height: 100vh;
            height: 100%;
            overflow: hidden;
        }
    }

    /* Mobile: allow scrolling */
    @media (max-width: 1199px) {

        html,
        body.materials-page {
            overflow-x: hidden !important;
            overflow-y: auto !important;
        }

        .materials-page .main-content {
            height: auto !important;
            min-height: auto;
            overflow-x: hidden !important;
            overflow-y: visible !important;
        }

        .materials-section {
            min-height: auto;
            height: auto;
            overflow: hidden;
            padding: 80px 0 40px 0;
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }

        .materials-section .container-fluid {
            padding: 0 15px;
            flex-shrink: 0;
        }

        .main-heading {
            font-size: 1.4rem;
            text-align: center;
            margin-bottom: 20px;
            max-width: 100%;
        }

        .design-content-right {
            text-align: center;
            font-size: 0.85rem;
            max-width: 100%;
            margin-bottom: 10px;
            border-top: 1px solid rgba(255, 255, 255, 0.3);
            padding-top: 15px;
            border-left: none;
            padding-left: 0;
        }

    }

    /* Process Styling (Internal) */
    .process-container {
        position: absolute;
        bottom: 100px;
        left: 0;
        width: 100%;
        z-index: 5;
        padding: 0 5%;
    }


    .process-grid {
        display: flex;
        justify-content: center;
        gap: 20px;
        align-items: center;
        position: relative;
        max-width: 910px;
        margin: 0 auto;
        min-height: 196px;
    }


    .process-step-wrapper {
        position: relative;
        flex: 1;
        display: flex;
        justify-content: center;
        z-index: 2;
        transition: transform 0.1s ease-out;
    }

    .process-card {
        width: 154px;
        height: 154px;
        background-size: contain;
        background-position: center;
        background-repeat: no-repeat;
        border-radius: 18px;
        position: relative;
        display: flex;
        flex-direction: column;
        transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        cursor: pointer;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.1);
        padding: 12px 8px;
    }

    /* Odd Steps: Title Top */
    .process-step-wrapper:nth-child(odd) .process-card,
    .process-swiper-mobile .swiper-slide:nth-child(odd) .process-card {
        justify-content: flex-start;
    }

    .process-step-wrapper:nth-child(odd) .process-card::before,
    .process-swiper-mobile .swiper-slide:nth-child(odd) .process-card::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(to bottom, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0) 60%);
        z-index: 1;
        opacity: 0.7;
    }

    /* Even Steps: Title Bottom */
    .process-step-wrapper:nth-child(even) .process-card,
    .process-swiper-mobile .swiper-slide:nth-child(even) .process-card {
        justify-content: flex-end;
    }

    .process-step-wrapper:nth-child(even) .process-card::before,
    .process-swiper-mobile .swiper-slide:nth-child(even) .process-card::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(to top, rgba(0, 0, 0, 0.8) 0%, rgba(0, 0, 0, 0) 60%);
        z-index: 1;
        opacity: 0.7;
    }

    .process-card:hover {
        transform: scale(1.1) translateY(-10px) rotate(3deg);
        box-shadow: 0 20px 40px -15px rgba(244, 63, 94, 0.4);
        border-color: rgba(244, 63, 94, 0.5);
    }

    .process-card:hover::before {
        opacity: 0.9;
    }


    .step-content {
        position: relative;
        z-index: 2;
        width: 100%;
        text-align: left;
    }


    .step-title {
        font-size: 0.7rem;
        /* font-weight: 700; */
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #fff;
        text-shadow: 0 1px 3px rgba(0, 0, 0, 0.8);
        margin: 0;
        line-height: 1.2;
        width: 98px;
        word-wrap: break-word;
    }



    /* Connecting Lines */
    .process-step-wrapper:not(:last-child)::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 60%;
        width: 80%;
        height: 15px;
        border-bottom: 1px solid rgba(244, 63, 94, 0.1);
        border-right: 1px solid rgba(244, 63, 94, 0.1);
        z-index: 1;
        pointer-events: none;
    }

    .process-step-wrapper:nth-child(odd):not(:last-child)::after {
        top: 50%;
        border-radius: 0 0 15px 0;
        height: 20px;
    }

    .process-step-wrapper:nth-child(even):not(:last-child)::after {
        top: 50%;
        transform: translateY(-100%);
        border-top: 2px solid rgba(244, 63, 94, 0.15);
        border-bottom: none;
        border-radius: 0 15px 0 0;
        height: 20px;
    }

    /* Mobile: process section as swiper slider */
    @media (max-width: 1199px) {
        .process-container {
            position: relative;
            bottom: auto;
            left: auto;
            width: 100%;
            padding: 25px 0 15px 0;
            flex-shrink: 0;
            overflow: visible;
            /* Allow swiper to overflow if needed */
            z-index: 10;
        }

        .process-grid {
            display: none;
        }

        .process-swiper-mobile {
            display: block;
            width: 100%;
            padding: 0 10px 45px 10px;
            overflow: hidden;
        }

        .process-swiper-mobile .swiper-slide {
            display: flex;
            justify-content: center;
        }


        .process-swiper-mobile .process-card {
            width: 196px;
            height: 196px;
            padding: 15px;
            border-radius: 24px;
        }

        .process-swiper-mobile .step-title {
            font-size: 0.8rem;
            line-height: 1.2;
            width: 140px;
            word-wrap: break-word;
        }



        .process-swiper-mobile .swiper-pagination {
            bottom: 5px;
        }

        .process-swiper-mobile .swiper-pagination-bullet {
            background: rgba(255, 255, 255, 0.5);
            opacity: 1;
            width: 8px;
            height: 8px;
            transition: all 0.3s ease;
        }

        .process-swiper-mobile .swiper-pagination-bullet-active {
            background: #f43f5e;
            width: 24px;
            border-radius: 4px;
        }
    }

    /* Desktop: hide mobile swiper */
    @media (min-width: 1200px) {
        .process-swiper-mobile {
            display: none;
        }
    }
</style>
</head>

<body class="materials-page">

    <!-- Sol Panel (Fixed) -->
    <div class="sidebar">
        <div>
            <div class="sidebar-logo">
                <img src="assets/img/logo.png" alt="Logo">
            </div>
            <h1 class="sidebar-title">
                <?php
                $title = $designData['kart_adi'] ?? "Tasarım";
                $words = explode(' ', $title);
                if (count($words) > 1) {
                    echo htmlspecialchars($words[0]) . '<br><small style="font-size: 0.5em; opacity: 0.7;">' . htmlspecialchars(implode(' ', array_slice($words, 1))) . '</small>';
                } else {
                    echo htmlspecialchars($title);
                }
                ?>
            </h1>
        </div>
        <div class="sidebar-footer">
            <?php echo ($lang === 'tr') ? 'Hayal gücünüzü gerçeğe dönüştürüyoruz.' : 'We bring your imagination to life.'; ?>
        </div>
    </div>

    <!-- Sağ İçerik -->
    <div class="main-content">

        <!-- Üst Menü -->
        <?php include_once 'inc/navbar.php'; ?>

        <section class="materials-section">
            <div class="container-fluid">
                <?php echo $designData['kart_aciklama'] ?? ''; ?>
            </div>

            <!-- Process Section Merged -->
            <div class="process-container">
                <!-- Desktop: Grid with parallax -->
                <div class="process-grid">
                    <?php
                    $speeds = [0.05, 0.08, 0.03, 0.1, 0.06];
                    foreach ($designItems as $index => $item):
                        $speed = $speeds[$index % count($speeds)];
                        $imgNum = ($index % 5) + 1;
                        $imgPath = "assets/img/tasarim/{$imgNum}.png";
                        ?>
                        <!-- Step <?php echo $index + 1; ?> -->
                        <div class="process-step-wrapper parallax-item" data-speed="<?php echo $speed; ?>">
                            <div class="process-card" style="background-image: url('<?php echo $imgPath; ?>');">
                                <div class="step-content">
                                    <h3 class="step-title"><?php echo htmlspecialchars($item['kart_detay_adi']); ?></h3>
                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>

                </div>

                <!-- Mobile: Swiper Slider -->
                <div class="swiper process-swiper-mobile">
                    <div class="swiper-wrapper">
                        <?php foreach ($designItems as $index => $item):
                            $imgNum = ($index % 5) + 1;
                            $imgPath = "assets/img/tasarim/{$imgNum}.png";
                            ?>
                            <div class="swiper-slide">
                                <div class="process-card" style="background-image: url('<?php echo $imgPath; ?>');">
                                    <div class="step-content">
                                        <h3 class="step-title"><?php echo htmlspecialchars($item['kart_detay_adi']); ?></h3>
                                    </div>
                                </div>
                            </div>

                        <?php endforeach; ?>

                    </div>
                    <div class="swiper-pagination"></div>
                </div>
            </div>
        </section>

    </div>

    <!-- Bootstrap JS -->
    <script src="assets/vendor/bootstrap.bundle.min.js"></script>
    <!-- Swiper JS -->
    <script src="assets/vendor/swiper-bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Desktop: Parallax effect for process cards
            if (window.innerWidth >= 1200) {
                const parallaxItems = document.querySelectorAll('.parallax-item');

                const handleScroll = () => {
                    const scrolled = window.pageYOffset;
                    const viewHeight = window.innerHeight;

                    parallaxItems.forEach(item => {
                        const speed = item.getAttribute('data-speed');
                        const rect = item.getBoundingClientRect();

                        if (rect.top < viewHeight + 300 && rect.bottom > -300) {
                            const centerY = rect.top + rect.height / 2;
                            const screenCenter = viewHeight / 2;
                            const distanceFromCenter = centerY - screenCenter;

                            const move = distanceFromCenter * speed;
                            item.style.transform = `translateY(${move}px)`;
                        }
                    });
                };

                window.addEventListener('scroll', handleScroll);
                handleScroll();
            }

            // Mobile: Initialize Swiper for process cards
            if (window.innerWidth < 1200) {
                new Swiper('.process-swiper-mobile', {
                    slidesPerView: 2.3,
                    spaceBetween: 25,
                    centeredSlides: false,
                    grabCursor: true,
                    observer: true,
                    observeParents: true,

                    pagination: {
                        el: '.process-swiper-mobile .swiper-pagination',
                        clickable: true,
                    },
                    breakpoints: {
                        0: {
                            slidesPerView: 2.2,
                            spaceBetween: 15,
                        },
                        576: {
                            slidesPerView: 3.2,
                            spaceBetween: 25,
                        },
                        768: {
                            slidesPerView: 4,
                            spaceBetween: 25,
                        }
                    }
                });
            }
        });
    </script>
</body>

</html>