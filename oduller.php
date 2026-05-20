<?php
$pageTitle = "Ödüller";
include_once 'inc/header.php';

// Fetch dynamic content from JSON
$lang = active_lang();
$filePath = __DIR__ . "/data/lang/{$lang}/kartlar/oduller.json";
$awards = [];
if (file_exists($filePath)) {
    $json = json_decode(file_get_contents($filePath), true);
    if (!empty($json)) {
        $awards = $json[0]['detaylar'] ?? [];
    }
}

// Initial values for the info box
$firstAward = $awards[0] ?? null;
$initialTitle = $firstAward ? $firstAward['kart_detay_adi'] : '';
$initialDesc = $firstAward ? $firstAward['kart_detay_aciklama'] : '';
?>

<style>
    .materials-section {
        padding: 30px 60px;
        position: relative;
        height: calc(100vh - 80px);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: #fff;
    }

    /* Top Information Area */
    .award-info-box {
        margin-bottom: 20px;
        min-height: 200px;
    }

    .award-title-group h2 {
        font-size: 1.4rem;
        font-weight: 800;
        color: #830051;
        /* Darker magenta from image */
        text-transform: uppercase;
        margin: 0;
        line-height: 1.1;
    }

    .award-description {
        margin-top: 15px;
        max-width: 800px;
    }

    .award-description p {
        font-size: 0.72rem;
        color: #666;
        line-height: 1.4;
        margin-bottom: 8px;
    }

    .award-description .highlight {
        color: #830051;
        font-weight: 700;
    }

    /* Images Area */
    .awards-display {
        display: flex;
        align-items: flex-end;
        gap: 15px;
        margin-bottom: 30px;
        position: relative;
        z-index: 10;
    }

    /* The Connector Line Container */
    .connector-container {
        position: absolute;
        top: -35px;
        /* Height of the diagonal jump */
        left: -60px;
        /* Match section padding */
        width: calc(100% + 60px);
        height: 35px;
        pointer-events: none;
        z-index: 5;
    }

    #connector-line {
        position: absolute;
        top: 0;
        left: 0;
        height: 100%;
        border-top: 3px solid #830051;
        border-right: 3px solid #830051;
        transform-origin: top right;
        transform: skewX(-45deg);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .award-card {
        flex: 1;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        aspect-ratio: 1/1;
        /* background: #fff; */
        /* padding: 10px; */
        /* box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); */
        transform: perspective(1000px) rotateY(-5deg);
    }

    .award-card:hover,
    .award-card.active {
        transform: perspective(1000px) rotateY(0deg) scale(1.05);
        z-index: 5;
        /* box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12); */
    }

    .award-card img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    /* Active highlight for the selected card */
    .award-card.active {
        /* border: 2px solid #830051; */
    }

    /* Timeline Area */
    .timeline-container {
        margin-top: auto;
        position: relative;
        padding-bottom: 40px;
    }

    .timeline-line {
        height: 4px;
        background: #830051;
        width: 100%;
        position: absolute;
        top: 20px;
        left: 0;
    }

    .timeline-items {
        display: flex;
        justify-content: space-between;
        position: relative;
        z-index: 2;
    }

    .timeline-point {
        display: flex;
        flex-direction: column;
        align-items: center;
        cursor: pointer;
    }

    .point-dot {
        width: 14px;
        height: 14px;
        background: #830051;
        border-radius: 50%;
        margin-top: 13px;
        position: relative;
        transition: all 0.3s ease;
    }

    .timeline-point.active .point-dot {
        background: #830051;
    }

    /* Outer Circle for Active */
    .timeline-point.active .point-dot::before {
        content: "";
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 36px;
        height: 36px;
        border: 2px solid #830051;
        border-radius: 50%;
        background: #fff;
        z-index: -1;
    }

    .point-year {
        font-size: 1.1rem;
        font-weight: 700;
        color: #830051;
        margin-top: 15px;
        transition: all 0.3s ease;
    }

    /* Triangle Arrow for Active */
    .timeline-point.active::after {
        content: "";
        width: 0;
        height: 0;
        border-left: 10px solid transparent;
        border-right: 10px solid transparent;
        border-bottom: 12px solid #830051;
        margin-top: 10px;
    }

    /* Hidden data for JS */
    .award-data {
        display: none;
    }

    @media (max-width: 991px) {
        .connector-container {
            display: none;
        }

        .materials-section {
            height: auto;
            padding: 40px 20px;
            overflow: visible;
        }

        .awards-display {
            flex-wrap: wrap;
        }

        .award-card {
            flex: 0 0 calc(50% - 15px);
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
                <?= statik('sidebar_title_awards') ?>
            </h1>
        </div>
        <div class="sidebar-footer">
            <?= statik('sidebar_footer') ?>
        </div>
    </div>

    <!-- Sağ İçerik -->
    <div class="main-content">

        <!-- Üst Menü -->
        <!-- Üst Menü -->
        <?php include_once 'inc/navbar.php'; ?>

        <section class="materials-section">
            <!-- Top Info Area (Target for Click) -->
            <div class="award-info-box" id="award-info">
                <div class="award-title-group">
                    <h2 id="info-titles"><?= $initialTitle ?></h2>
                </div>
                <div class="award-description" id="info-desc">
                    <?= $initialDesc ?>
                </div>
            </div>

            <!-- Awards Image Scroll/Grid -->
            <div class="awards-display" id="awards-gallery">
                <div class="connector-container">
                    <div id="connector-line"></div>
                </div>
                <!-- Cards for the current year -->
                <?php foreach ($awards as $index => $award): ?>
                    <div class="award-card <?= $index === 0 ? 'active' : '' ?>" data-id="<?= $index + 1 ?>">
                        <img src="<?= $award['kart_detay_gorsel'] ?>" alt="<?= htmlspecialchars($award['kart_detay_adi']) ?>">
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Timeline -->
            <div class="timeline-container" style="display: none;">
                <div class="timeline-line"></div>
                <div class="timeline-items">
                    <div class="timeline-point active" data-year="2023">
                        <div class="point-dot"></div>
                        <div class="point-year">2023</div>
                    </div>
                    <div class="timeline-point" data-year="2022">
                        <div class="point-dot"></div>
                        <div class="point-year">2022</div>
                    </div>
                    <div class="timeline-point" data-year="2021">
                        <div class="point-dot"></div>
                        <div class="point-year">2021</div>
                    </div>
                    <div class="timeline-point" data-year="2014">
                        <div class="point-dot"></div>
                        <div class="point-year">2014</div>
                    </div>
                </div>
            </div>
        </section>

    </div>

    <!-- Hidden Data Storage -->
    <div class="award-data" id="award-content">
        <?php foreach ($awards as $index => $award): ?>
            <div data-ref="<?= $index + 1 ?>">
                <h2 class="title"><?= $award['kart_detay_adi'] ?></h2>
                <div class="desc">
                    <?= $award['kart_detay_aciklama'] ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="assets/vendor/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const cards = document.querySelectorAll('.award-card');
            const timelinePoints = document.querySelectorAll('.timeline-point');
            const infoTitles = document.getElementById('info-titles');
            const infoDesc = document.getElementById('info-desc');
            const awardContent = document.getElementById('award-content');
            const connectorLine = document.getElementById('connector-line');
            const gallery = document.getElementById('awards-gallery');

            function updateConnector(activeCard) {
                const cardRect = activeCard.getBoundingClientRect();
                const galleryRect = gallery.getBoundingClientRect();

                // Calculate the right edge of the card relative to the gallery
                const rightPos = cardRect.right - galleryRect.left;

                // Add padding offset to reach the very left edge of the section
                const totalWidth = rightPos + 43; // 60px padding - ~17px skew offset
                connectorLine.style.width = totalWidth + 'px';
            }

            // Initial update
            setTimeout(() => {
                const activeCard = document.querySelector('.award-card.active');
                if (activeCard) updateConnector(activeCard);
            }, 100);

            // Handle Award Card Clicks
            cards.forEach(card => {
                card.addEventListener('click', () => {
                    // Update Active Card
                    cards.forEach(c => c.classList.remove('active'));
                    card.classList.add('active');

                    // Update Connector Line Position
                    updateConnector(card);

                    // Update Info Area
                    const id = card.getAttribute('data-id');
                    const source = awardContent.querySelector(`[data-ref="${id}"]`);

                    if (source) {
                        infoTitles.innerHTML = source.querySelector('.title').innerHTML;
                        infoDesc.innerHTML = source.querySelector('.desc').innerHTML;
                    }
                });
            });

            // Handle Timeline Clicks (Simplified logic for demo)
            timelinePoints.forEach(point => {
                point.addEventListener('click', () => {
                    timelinePoints.forEach(p => p.classList.remove('active'));
                    point.classList.add('active');

                    // In a real app, this would filter/reload the images above
                });
            });
        });
    </script>
</body>

</html>