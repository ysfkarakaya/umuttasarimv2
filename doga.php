<?php
$pageTitle = "Doğa";
include_once 'inc/header.php';
?>
<style>
    /* Materials Page Specific Styles */
    .materials-page .sidebar-title {
        font-size: 2.5rem;
        line-height: 1.1;
        font-size: clamp(2rem, 3.2vw, 2.8rem) !important;
        margin-top: 50px;
    }

    .materials-section {
        padding: 40px;
        position: relative;
        min-height: 80vh;
        height: 100%;
        overflow: hidden;
    }

    .sustainability-section {
        background: url('assets/img/doga-bg.png') center center/cover no-repeat;
        isolation: isolate;
    }

    .sustainability-section::before {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(90deg, rgba(255, 255, 255, 0.35) 0%, rgba(255, 255, 255, 0.08) 45%, rgba(255, 255, 255, 0) 100%);
        z-index: -1;
    }

    .sustainability-top {
        display: flex;
        align-items: flex-start;
        justify-content: center;
        gap: 11px;
        width: 100%;
        max-width: 520px;
        margin: 0 auto;
    }

    .sustainability-title {
        font-weight: 700;
        font-size: 1.25rem;
        line-height: 1;
        letter-spacing: -0.5px;
        text-transform: uppercase;
        color: #3c3c3c;
        margin: 0;
        text-align: right;
        position: relative;
        min-width: 175px;
        flex: 0 0 175px;
        padding-right: 18px;
    }

    .sustainability-title::after {
        content: "";
        position: absolute;
        top: 0;
        right: 0;
        width: 2px;
        height: 100%;
        background: #7ec242;
    }

    .sustainability-title span {
        color: #7ec242;
        white-space: nowrap;
    }

    .sustainability-intro {
        max-width: 364px;
        flex: 1;
        min-width: 0;
        font-size: 0.575rem;
        color: #3f3f3f;
        line-height: 1.4;
        margin-top: 4px;
    }

    .sustainability-intro strong {
        font-weight: 700;
    }

    .policy-cards {
        position: relative;
        height: 370px;
        margin-top: 32px;
        max-width: 560px;
        margin-left: auto;
        margin-right: 36%;
    }

    .policy-card {
        position: absolute;
        left: 100px;
        top: 125px;
        width: 245px;
        border-radius: 20px;
        border: 5px solid #f1f1f1;
        background: rgba(255, 255, 255, 0.95);
        overflow: hidden;
        box-shadow: 0 14px 30px rgba(0, 0, 0, 0.2);
        opacity: 0;
        pointer-events: none;
        transition: left 0.55s ease, top 0.55s ease, width 0.55s ease, transform 0.55s ease, opacity 0.45s ease;
    }

    .policy-card img {
        width: 100%;
        height: 120px;
        object-fit: cover;
        display: block;
    }

    .policy-content {
        padding: 10px 11px 12px;
        font-size: 0.72rem;
        line-height: 1.3;
        color: #595959;
    }

    .policy-content h3 {
        font-size: 0.8rem;
        line-height: 1.2;
        margin: 0 0 5px;
        color: #83bc4d;
        font-weight: 700;
    }

    .policy-content p {
        margin: 0;
    }

    .policy-card.slot-bottom {
        left: -10px;
        top: 150px;
        transform: rotate(-15deg);
        z-index: 0;
        opacity: 0.90;
        pointer-events: auto;
    }

    .policy-card.slot-bottom .policy-content,
    .policy-card.slot-left .policy-content {
        font-size: 0.62rem;
        line-height: 1.25;
    }

    .policy-card.slot-left {
        left: 100px;
        top: 125px;
        transform: rotate(-10deg);
        z-index: 1;
        opacity: 0.95;
        pointer-events: auto;
    }

    .policy-card.slot-middle {
        left: 240px;
        top: 92px;
        transform: rotate(-4deg);
        z-index: 2;
        opacity: 0.98;
        pointer-events: auto;
    }

    .policy-card.slot-right {
        left: 370px;
        top: 10px;
        transform: rotate(0deg);
        z-index: 3;
        opacity: 1;
        pointer-events: auto;
    }

    .policy-arrow {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 4;
        overflow: visible;
    }

    .policy-arrow path {
        fill: none;
        stroke: #7ec242;
        stroke-width: 3;
        stroke-linecap: round;
        stroke-linejoin: round;
    }

    .globe-visual {
        position: absolute;
        right: 0;
        top: 240px;
        width: 200px;
        max-width: 38vw;
        z-index: 0;
        pointer-events: none;
    }

    .specs-badge {
        position: absolute;
        top: 30px;
        left: 30px;
        width: 105px;
        height: 105px;
        background: linear-gradient(135deg, #e91e63 0%, #7b1fa2 100%);
        border-radius: 9px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 19px;
        color: #fff;
        z-index: 20;
        box-shadow: 0 11px 26px rgba(233, 30, 99, 0.25);
        transition: all 0.4s ease;
    }

    .specs-badge::before {
        content: "";
        position: absolute;
        top: 15px;
        right: 15px;
        border-right: 5px solid #fff;
        border-top: 5px solid #fff;
        border-left: 5px solid transparent;
        border-bottom: 5px solid transparent;
        opacity: 0.8;
    }

    .specs-badge .badge-top {
        font-size: 0.71rem;
        font-weight: 300;
        margin-bottom: -1px;
        opacity: 0.9;
    }

    .specs-badge .badge-bottom {
        font-size: 1.35rem;
        font-weight: 700;
        line-height: 1.1;
        letter-spacing: -1px;
    }

    /* Responsive Design */
    @media (max-width: 991px) {
        .materials-section {
            padding: 20px;
            overflow: hidden;
        }

        .sidebar-title {
            font-size: 1.8rem !important;
        }

        .sustainability-top {
            flex-direction: column;
            gap: 6px;
            margin: 0;
        }

        .sustainability-title {
            padding-right: 0;
            padding-bottom: 6px;
            min-width: auto;
            font-size: 1.6rem;
        }

        .sustainability-title::after {
            top: auto;
            right: auto;
            left: 0;
            bottom: 0;
            width: 100%;
            height: 2px;
        }

        .sustainability-intro {
            font-size: 0.9rem;
            max-width: 100%;
        }

        /* Yatay scroll-snap slider */
        .policy-cards {
            display: flex;
            flex-direction: row;
            flex-wrap: nowrap;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            gap: 14px;
            height: auto;
            max-width: 100%;
            margin: 20px 0 0;
            padding: 10px 4px 16px;
            margin-right: 0;
        }

        .policy-cards::-webkit-scrollbar {
            display: none;
        }

        .policy-card {
            position: relative !important;
            left: auto !important;
            top: auto !important;
            transform: none !important;
            flex: 0 0 260px;
            width: 260px !important;
            opacity: 1 !important;
            pointer-events: auto !important;
            scroll-snap-align: start;
        }

        .policy-card.slot-bottom .policy-content,
        .policy-card.slot-left .policy-content {
            font-size: 0.72rem;
            line-height: 1.3;
        }

        .policy-arrow {
            display: none;
        }

        .globe-visual {
            position: relative;
            right: 0;
            top: auto;
            width: min(320px, 80%);
            max-width: none;
            display: block;
            margin: 18px 0 0 auto;
        }
    }

    @media (max-width: 768px) {
        .specs-badge {
            position: relative !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: auto !important;
            aspect-ratio: 1/1;
            padding: 12px !important;
            min-width: 70px;
            border-radius: 8px !important;
            box-shadow: 0 10px 20px rgba(233, 30, 99, 0.2);
        }

        .specs-badge .badge-top {
            font-size: 0.6rem !important;
        }

        .specs-badge .badge-bottom {
            font-size: 0.9rem !important;
        }

        .specs-badge::before {
            top: 10px !important;
            right: 10px !important;
            border-right: 4px solid #fff !important;
            border-top: 4px solid #fff !important;
            border-left: 4px solid transparent !important;
            border-bottom: 4px solid transparent !important;
        }

        .materials-section {
            overflow: hidden;
        }

        .policy-card {
            flex: 0 0 220px;
            width: 220px !important;
        }

        .policy-card img {
            height: 140px;
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
                <?= statik('sidebar_title_sustainability') ?>
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

        <?php
        // Fetch dynamic content from JSON
        $lang = active_lang();
        $jsonPath = __DIR__ . "/data/lang/{$lang}/kartlar/doga.json";
        $cards = [];
        $pageIntro = "";
        if (file_exists($jsonPath)) {
            $data = json_decode(file_get_contents($jsonPath), true);
            if (!empty($data)) {
                $cards = isset($data[0]['detaylar']) ? $data[0]['detaylar'] : [];
            }
        }

        // Define slot classes for dynamic positioning
        $slots = count($cards) >= 4
            ? ['slot-bottom', 'slot-left', 'slot-middle', 'slot-right']
            : ['slot-left', 'slot-middle', 'slot-right'];
        ?>

        <section class="materials-section sustainability-section">
            <div class="sustainability-top">
                <?= $data[0]['kart_aciklama'] ?>
            </div>

            <div class="policy-cards">
                <?php foreach ($cards as $index => $card): ?>
                    <?php
                    $slotClass = isset($slots[$index]) ? $slots[$index] : '';
                    $imgUrl = !empty($card['kart_detay_gorsel']) ? $card['kart_detay_gorsel'] : 'assets/img/placeholder.png';
                    ?>
                    <article class="policy-card <?php echo $slotClass; ?>">
                        <img src="<?php echo $imgUrl; ?>" alt="<?php echo $card['kart_detay_adi']; ?>">
                        <div class="policy-content">
                            <h3><?php echo $card['kart_detay_adi']; ?></h3>
                            <p><?php echo $card['kart_detay_aciklama']; ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <svg class="policy-arrow" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 480"
                preserveAspectRatio="none">
                <path d="M 615 280 C 660 320, 720 300, 790 310" />
            </svg>

            <img class="globe-visual" src="assets/img/doga.png" alt="Sustainability globe">
        </section>

    </div>

    <!-- Bootstrap JS -->
    <script src="assets/vendor/bootstrap.bundle.min.js"></script>
    <!-- Swiper JS -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const policyCards = document.querySelectorAll('.policy-cards .policy-card');
            if (policyCards.length < 2) return; // Need at least 2 for rotation

            const allSlots = ['slot-bottom', 'slot-left', 'slot-middle', 'slot-right'];
            const slots = policyCards.length >= 4
                ? ['slot-bottom', 'slot-left', 'slot-middle', 'slot-right']
                : ['slot-left', 'slot-middle', 'slot-right'];
            let order = Array.from(policyCards);

            const applySlots = () => {
                order.forEach((card, index) => {
                    allSlots.forEach(s => card.classList.remove(s));
                    if (slots[index]) card.classList.add(slots[index]);
                });
            };

            const rotateCards = () => {
                order.push(order.shift());
                applySlots();
            };

            applySlots();

            if (window.innerWidth > 991 && order.length >= 3) {
                setInterval(rotateCards, 3200);
            }

            order.forEach((card) => {
                card.addEventListener('click', () => {
                    if (window.innerWidth <= 991) return;
                    while (order[Math.min(slots.length - 1, order.length - 1)] !== card) {
                        order.push(order.shift());
                    }
                    applySlots();
                });
            });
        });
    </script>
</body>

</html>