<?php
include_once 'inc/functions.php';
$lang = active_lang();
$filePath = __DIR__ . "/data/lang/{$lang}/kartlar/hakkimizda.json";
$aboutData = null;

if (file_exists($filePath)) {
    $json = json_decode(file_get_contents($filePath), true);
    $aboutData = $json[0] ?? null; // Get first record from top-level array
}

$hasSidebar = true;
$pageTitle = $aboutData['kart_adi'] ?? "Hakkımızda";
include_once 'inc/header.php';
?>
<style>
    .materials-page .sidebar-title {
        font-size: 3.2rem;
        margin: 0;
        position: relative;
        z-index: 2;
    }

    .sidebar-child {
        display: none;
    }

    @media (min-width: 992px) {
        .sidebar {
            overflow: visible !important;
        }

        .sidebar-child {
            display: block;
            position: absolute;
            bottom: 0;
            left: 100%;
            height: 85vh;
            pointer-events: none;
            z-index: 1001;
            transform: translateX(-29%);
            transition: all 0.3s ease;
            object-fit: contain;
            transform-origin: bottom left;
        }
    }

    @media (max-width: 1200px) and (min-width: 992px) {
        .sidebar-child {
            height: 75vh;
            transform: translateX(-90px);
        }
    }

    @media (max-width: 1400px) and (min-width: 992px) {
        .sidebar-child {
            height: 80vh;
            transform: translateX(-20%);
        }
    }

    /* --- About Page V2 Floating Style --- */
    .materials-section {
        padding: 20px 40px;
        position: relative;
        height: calc(100vh - 80px);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: radial-gradient(circle at 50% 50%, #fdfdfd 0%, #f4f7f6 100%);
    }

    .about-introduction {
        text-align: right;
        max-width: 450px;
        margin: 0 auto 5px;
        z-index: 10;
        position: relative;
    }

    .about-introduction p {
        font-size: 0.82rem;
        color: #555;
        line-height: 1.3;
        margin-bottom: 4px;
    }

    .cards-container {
        position: relative;
        flex: 1;
        width: 100%;
        height: 100%;
        perspective: 1000px;
    }

    .floating-card-wrapper {
        position: absolute;
        transition: transform 0.1s ease-out, opacity 0.8s ease;
        cursor: move;
        z-index: 5;
        user-select: none;
    }

    .about-card {
        position: relative;
        background-size: 100% 100%;
        background-repeat: no-repeat;
        background-position: center center;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        width: 100%;
        background-color: transparent;
        /* Extra styles removed to keep only the texture visible */
        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .floating-card-wrapper:hover {
        z-index: 100;
    }

    .floating-card-wrapper:hover .about-card {
        transform: scale(1.1);
    }

    .about-card h4 {
        font-weight: 700;
        font-size: 0.9rem;
        margin-bottom: 5px;
    }

    .about-card p,
    .about-card ul {
        font-size: 10px;
        color: #444;
        line-height: 1.3;
        margin-bottom: 4px;
    }

    .about-card ul {
        padding-left: 20px;
        list-style-type: disc;
    }

    /* --- Unique Text Positioning Inside Textures --- */
    /* Change these values to move text around inside each texture */

    .vision-content {
        padding-top: 38%;
        padding-left: 40px;
        padding-right: 5%;
        /* Pushes text to the left side of the texture */
        text-align: left;
    }

    .expertise-content {
        padding-top: 120px;
        padding-left: 30px;
        padding-right: 40%;
        text-align: left;
    }

    .approach-content {
        padding-top: 50px;
        padding-left: 20px;
        padding-right: 40%;
        text-align: left;
        font-size: 10px;
    }

    .commitment-content {
        padding-top: 20px;
        padding-left: 45%;
        padding-right: 20px;
        text-align: left;
    }

    .infrastructure-content {
        padding-top: 65px;
        padding-left: 40%;
        padding-right: 20px;
        text-align: left;
    }

    /* Initial sizes for the containers to match texture aspect ratios roughly */
    .card-vision {
        width: 320px;
        height: 220px;
        background-image: url('assets/img/texture-1.png');
    }

    .card-expertise {
        width: 380px;
        height: 330px;
        background-image: url('assets/img/texture-3.png');
    }

    .card-approach {
        width: 450px;
        height: 220px;
        background-image: url('assets/img/texture-2.png');
    }

    .card-commitment {
        width: 420px;
        height: 250px;
        background-image: url('assets/img/texture-4.png');
    }

    .card-infrastructure {
        width: 400px;
        height: 320px;
        background-image: url('assets/img/texture-5.png');
    }

    /* Connection Lines (Optional but cool if added later) */
    .connection-canvas {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 1;
        pointer-events: none;
        opacity: 0.2;
    }

    @media (max-width: 991px) {
        .materials-section {
            height: auto;
            padding: 40px 0;
            /* No side padding for full-width scroll */
            overflow: visible;
        }

        .cards-container {
            display: flex;
            flex-direction: row;
            /* Horizontal */
            gap: 20px;
            perspective: none;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            padding: 10px 20px 30px;
            /* Padding for the shadow and cards */
            -webkit-overflow-scrolling: touch;
        }

        .cards-container::-webkit-scrollbar {
            display: none;
            /* Hide scrollbar for cleaner look */
        }

        .floating-card-wrapper {
            position: relative !important;
            top: auto !important;
            left: auto !important;
            transform: none !important;
            width: 85vw !important;
            /* Most of the screen width */
            min-width: 85vw !important;
            height: auto !important;
            scroll-snap-align: center;
            opacity: 1 !important;
        }

        .about-card {
            background-size: 100% 100%;
            min-height: 280px;
            height: 100%;
            background-color: transparent;
        }

        /* Extra downward push for approach-content on mobile */
        .approach-content {
            padding-top: 80px !important;
        }

        .expertise-content {
            padding-top: 80px !important;
        }

        .infrastructure-content {
            padding-top: 55px !important;
        }

        .vision-content {
            padding-top: 55% !important;
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
                $title = statik('sidebar_title_about');
                echo htmlspecialchars($title);
                ?>
            </h1>
        </div>

        <img src="assets/img/sidebar-cocuk.png" alt="Child" class="sidebar-child">
        <div class="sidebar-footer">
            <?= statik('sidebar_footer') ?>
        </div>
    </div>

    <!-- Sağ İçerik -->
    <div class="main-content">

        <!-- Üst Menü -->
        <?php include_once 'inc/navbar.php'; ?>

        <section class="materials-section">
            <div class="container-fluid d-flex flex-column h-100">
                <div class="about-introduction">
                    <?php echo $aboutData['kart_aciklama'] ?? ''; ?>
                </div>

                <div class="cards-container" id="cards-container">
                    <?php
                    if (isset($aboutData['detaylar']) && is_array($aboutData['detaylar'])) {
                        foreach ($aboutData['detaylar'] as $detay) {
                            echo $detay['kart_detay_aciklama'];
                        }
                    }
                    ?>
                </div>
            </div>
        </section>

    </div>

    <!-- Bootstrap JS -->
    <script src="assets/vendor/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const container = document.getElementById('cards-container');
            const wrappers = document.querySelectorAll('.floating-card-wrapper');
            const mobileMediaQuery = window.matchMedia('(max-width: 991px)');

            let activeDraggingCard = null;
            let startMouseX, startMouseY, startCardTop, startCardLeft;
            window.mouseX = window.innerWidth / 2;
            window.mouseY = window.innerHeight / 2;

            // --- CONFIGURATION ---
            // Set to 1 to enable dragging, 0 to disable
            const isDraggable = 1;
            // ---------------------

            const isMobileView = () => mobileMediaQuery.matches;

            // Update cursor and selection style based on draggable setting
            if (isDraggable) {
                wrappers.forEach(w => {
                    w.style.cursor = 'move';
                    w.style.userSelect = 'none';
                });
            } else {
                wrappers.forEach(w => {
                    w.style.cursor = 'default';
                    w.style.userSelect = 'text'; // Allow text selection when not draggable
                });
            }

            // Handle Drag & Drop
            const handleMouseDown = (e, wrapper) => {
                if (isMobileView() || !isDraggable) return;
                if (e.target.closest('a') || e.target.closest('button')) return;

                activeDraggingCard = wrapper;
                startMouseX = e.clientX;
                startMouseY = e.clientY;
                startCardTop = parseFloat(wrapper.style.top);
                startCardLeft = parseFloat(wrapper.style.left);

                wrapper.style.transition = 'none';
                wrapper.style.zIndex = '1000';
                e.preventDefault();
            };

            wrappers.forEach(wrapper => {
                wrapper.addEventListener('mousedown', (e) => handleMouseDown(e, wrapper));
            });

            document.addEventListener('mousemove', (e) => {
                window.mouseX = e.clientX;
                window.mouseY = e.clientY;

                if (activeDraggingCard) {
                    const containerRect = container.getBoundingClientRect();
                    const deltaX = ((e.clientX - startMouseX) / containerRect.width) * 100;
                    const deltaY = ((e.clientY - startMouseY) / containerRect.height) * 100;

                    activeDraggingCard.style.top = `${startCardTop + deltaY}%`;
                    activeDraggingCard.style.left = `${startCardLeft + deltaX}%`;
                }
            });

            const logPositions = () => {
                const positions = Array.from(wrappers).map(wrapper => ({
                    class: wrapper.querySelector('.about-card').classList[1], // e.g. card-vision
                    top: wrapper.style.top,
                    left: wrapper.style.left
                }));
                console.log("--- Current Card Positions ---");
                console.table(positions);
                console.log(JSON.stringify(positions, null, 2));
            };

            document.addEventListener('mouseup', () => {
                if (!activeDraggingCard) return;
                activeDraggingCard.style.transition = 'all 0.5s cubic-bezier(0.165, 0.84, 0.44, 1)';
                activeDraggingCard.style.zIndex = '5';
                logPositions();
                activeDraggingCard = null;
            });

            // Floating animation removed - Cards are now static

            // Initial reveal animation
            wrappers.forEach((wrapper, index) => {
                wrapper.style.opacity = '0';
                wrapper.style.transform = 'scale(0.8) translateY(30px)';
                setTimeout(() => {
                    wrapper.style.opacity = '1';
                    wrapper.style.transition = 'opacity 1s cubic-bezier(0.165, 0.84, 0.44, 1)';
                    wrapper.style.transform = 'scale(1) translateY(0)';
                }, 200 + (index * 150));
            });
        });
    </script>
</body>

</html>