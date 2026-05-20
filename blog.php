<?php
include_once 'inc/functions.php';
$lang = active_lang();
$menu_items = get_menu_config($lang);

$blog_file = __DIR__ . "/data/lang/{$lang}/kartlar/blog.json";
if (!file_exists($blog_file)) {
    // Fallback or handle missing sync
    $blog_posts = [];
    $blog_header = ['kart_adi' => 'Blog', 'kart_aciklama' => ''];
} else {
    $blog_all = json_decode(file_get_contents($blog_file), true);
    $blog_header = $blog_all[0] ?? ['kart_adi' => 'Blog', 'kart_aciklama' => ''];
    $blog_posts = $blog_header['detaylar'] ?? [];
}

$pageTitle = $blog_header['kart_adi'];
$hasSidebar = true;
include_once 'inc/header.php';
?>

<style>
    @media (min-width: 992px) {
        body.blog-page {
            overflow: hidden;
            height: 100vh;
        }

        body.blog-page .main-content {
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: #0d1117 !important;
            /* Main content itself is not zoomed to keep menu standard */
        }


    }

    .blog-page-container {
        zoom: 0.82;
        /* Zoom moved here so it doesn't affect the menu */
        background: #0d1117;
        /* Dark background from image */
        flex: 1;
        min-height: calc(100vh / 0.82);
        padding: 20px 60px;
        position: relative;
        overflow: hidden;
        color: #fff;
        display: flex;
        flex-direction: column;
        width: 100%;
    }

    /* Background Decoration */
    .blog-bg-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: url('/upload/blog-bg.jpg');
        /* Updated as per user request */
        background-size: cover;
        background-position: center;
        opacity: 0.25;
        pointer-events: none;
        z-index: 1;
    }

    /* Hero Section Content */
    .blog-hero-content {
        position: relative;
        z-index: 10;
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 60px;
        width: 100%;
        max-width: 1200px;
        margin: 60px auto 30px auto;
    }



    .hero-left-title {
        flex: 0 0 auto;
        text-align: right;
        display: flex;
        flex-direction: column;
        justify-content: center;
        /* padding-right: 40px; */
    }



    .hero-left-title h2 {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1.1;
        margin: 0;
        color: #fff;
    }

    .hero-left-title .highlight {
        color: #8cc34b;
        /* Lime Green from image */
        display: block;
        font-size: 2.2rem;
    }

    .hero-right-desc {
        flex: 0 1 550px;
        border-left: 4px solid #8cc34b;
        padding-left: 40px;
    }



    .hero-right-desc p {
        font-size: 0.85rem;
        line-height: 1.4;
        margin-bottom: 6px;
        color: rgba(255, 255, 255, 0.95);
    }

    .hero-right-desc .highlight-text {
        color: #f7b731;
        /* Gold/Orange from image */
        font-weight: 600;
        display: block;
    }

    /* Search Bar */
    .blog-search-wrapper {
        display: flex;
        background: #eee;
        border-radius: 50px;
        overflow: hidden;
        margin-top: 15px;
        max-width: 340px;
        /* Slightly increased to prevent text clipping */
        height: 44px;
        align-items: center;
    }

    .blog-search-wrapper input {
        border: none;
        background: transparent;
        padding: 0 15px 0 20px;
        flex: 1;
        min-width: 0;
        /* Important for flex-shrink */
        font-size: 0.9rem;
        color: #333;
        outline: none;
    }

    .blog-search-wrapper button {
        background: linear-gradient(to right, #d81b60, #ec407a);
        border: none;
        padding: 0 25px;
        height: 100%;
        color: #fff;
        font-weight: 700;
        font-size: 0.95rem;
        cursor: pointer;
        transition: 0.3s;
        border-radius: 0 50px 50px 0;
        display: flex;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
    }

    .blog-search-wrapper button:hover {
        opacity: 0.9;
    }

    /* 3D Stacked Perspectives Slider */
    .blog-slider-container {
        position: relative;
        z-index: 10;
        flex-grow: 1;
        /* Take up remaining space in 100vh */
        display: flex;
        align-items: center;
        justify-content: center;
        perspective: 2000px;
        /* More depth */
        /* margin-top: 10px; */
        min-height: 0;
        /* Allow shrinking */
    }

    .slider-arrow {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 45px;
        height: 45px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        cursor: pointer;
        z-index: 100;
        transition: 0.3s;
        backdrop-filter: blur(5px);
        opacity: 0.5;
    }

    .slider-arrow:hover {
        opacity: 1;
        background: rgba(216, 27, 96, 0.3);
    }

    .arrow-prev {
        left: -40px;
    }

    .arrow-next {
        right: -40px;
    }

    /* Card Styling */
    .blog-card-perspective {
        position: absolute;
        width: 340px;
        height: 520px;
        background: #fff;
        border-radius: 28px;
        overflow: hidden;
        color: #333;
        box-shadow: 0 40px 80px rgba(0, 0, 0, 0.5);
        transition: all 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
        transform-origin: center center;
        display: flex;
        flex-direction: column;
        user-select: none;
        cursor: pointer;
    }

    .blog-card-perspective .card-header-img {
        width: 100%;
        height: 180px;
        overflow: hidden;
    }

    .blog-card-perspective .card-header-img img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .blog-card-perspective .card-body-content {
        padding: 25px;
        flex-grow: 1;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .blog-card-perspective h3 {
        font-size: 1.25rem;
        font-weight: 700;
        color: #8cc34b;
        /* Lime green from image */
        margin-bottom: 20px;
        min-height: 2.8rem;
        line-height: 1.2;
    }

    .blog-card-perspective p {
        font-size: 0.82rem;
        color: #666;
        line-height: 1.5;
        margin: 0;
        white-space: pre-wrap;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 10;
        -webkit-box-orient: vertical;
    }

    .card-footer-action {
        margin-top: auto;
        padding: 15px 25px;
        text-align: right;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        font-weight: 700;
        color: #bbb;
        font-size: 1.1rem;
        transition: 0.3s;
    }

    .card-footer-action i {
        color: #d81b60;
        font-size: 1.8rem;
    }

    .blog-card-perspective:hover .card-footer-action {
        color: #333;
    }

    /* Stack Positions (JS Will Manage Classes) */
    .card-stack-1 {
        transform: translateX(320px) scale(0.65) rotateY(-55deg);
        opacity: 0.2;
        z-index: 10;
        cursor: pointer;
    }

    .card-stack-2 {
        transform: translateX(220px) scale(0.75) rotateY(-45deg);
        opacity: 0.4;
        z-index: 20;
        cursor: pointer;
    }

    .card-stack-3 {
        transform: translateX(120px) scale(0.85) rotateY(-35deg);
        opacity: 0.7;
        z-index: 30;
        cursor: pointer;
    }

    .card-stack-4 {
        transform: translateX(0) scale(1) rotateY(0deg);
        opacity: 1;
        z-index: 50;
        cursor: default;
    }

    /* Active Card */
    .card-stack-5 {
        transform: translateX(-120px) scale(0.85) rotateY(35deg);
        opacity: 0.7;
        z-index: 30;
    }

    .card-stack-6 {
        transform: translateX(-220px) scale(0.75) rotateY(45deg);
        opacity: 0.4;
        z-index: 20;
    }

    .card-stack-7 {
        transform: translateX(-320px) scale(0.65) rotateY(55deg);
        opacity: 0.2;
        z-index: 10;
    }

    .card-hidden {
        transform: translateX(0) scale(0.4);
        opacity: 0;
        z-index: 0;
        pointer-events: none;
    }




    @media (max-width: 991px) {
        .blog-page-container {
            zoom: 1;
            padding: 80px 15px 40px 15px;
            min-height: auto;
            overflow-y: auto;
        }

        .blog-hero-content {
            flex-direction: column;
            gap: 20px;
            text-align: center;
            margin: 0;
        }

        .hero-left-title {
            padding-right: 0;
            text-align: center;
        }

        .hero-left-title h2 {
            font-size: 1.5rem;
            text-align: center;
        }

        .hero-left-title .highlight {
            font-size: 1.7rem;
        }

        .blog-search-wrapper {
            margin: 15px auto;
        }

        .hero-right-desc {
            border-left: none;
            padding-left: 0;
            border-top: 4px solid #8cc34b;
            padding-top: 20px;
            max-width: 100%;
            flex: none;
        }

        .blog-slider-container {
            height: 480px;
        }

        .blog-card-perspective {
            width: 280px;
            height: 420px;
        }

        .arrow-prev {
            left: 10px;
        }

        .arrow-next {
            right: 10px;
        }
    }





    .blog-page .sidebar-title {
        font-size: 3.2rem;
        margin: 0;
        position: relative;
        z-index: 2;
        text-transform: capitalize;
    }

</style>

<body class="blog-page">

    <!-- Sol Panel (Fixed) -->
    <div class="sidebar">
        <div>
            <div class="sidebar-logo">
                <img src="assets/img/logo.png" alt="Logo">
            </div>
            <h1 class="sidebar-title">
                <?php echo $blog_header['kart_adi']; ?>
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

        <section class="blog-page-container">
            <div class="blog-bg-overlay"></div>

            <div class="blog-hero-content">
                <div class="hero-left-title">
                    <h2>
                        <?= statik('blog_hero_title') ?>
                        <span class="highlight"><?= statik('blog_hero_subtitle') ?></span>
                    </h2>
                </div>
                <div class="hero-right-desc">
                    <?php echo $blog_header['kart_aciklama']; ?>

                    <div class="blog-search-wrapper">
                        <input type="text" placeholder="Bloglarda ara..." id="blog-search">
                        <button type="button" onclick="filterBlogs()">Ara</button>
                    </div>
                </div>
            </div>

            <div class="blog-slider-container" id="blog-slider">
                <div class="slider-arrow arrow-prev" onclick="moveSlider(-1)">
                    <i class="bi bi-chevron-left"></i>
                </div>
                <div class="slider-arrow arrow-next" onclick="moveSlider(1)">
                    <i class="bi bi-chevron-right"></i>
                </div>

                <!-- Cards will be rendered and managed by JS classes -->
                <?php foreach ($blog_posts as $index => $post): ?>
                    <div class="blog-card-perspective" id="card-<?php echo $index; ?>"
                        onclick="goToCard(<?php echo $index; ?>)"
                        data-title="<?php echo htmlspecialchars($post['kart_detay_adi']); ?>">
                        <div class="card-header-img">
                            <img src="/<?php echo ltrim($post['kart_detay_gorsel'], '/'); ?>"
                                alt="<?php echo $post['kart_detay_adi']; ?>"
                                onerror="this.src='https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?q=80&w=800'">
                        </div>
                        <div class="card-body-content">
                            <h3><?php echo $post['kart_detay_adi']; ?></h3>
                            <p><?php echo strip_tags($post['kart_detay_aciklama']); ?></p>
                        </div>
                        <a href="blog/<?php echo seo_name($post['kart_detay_adi']); ?>" class="card-footer-action">
                            <?= statik('blog_read_more') ?> <i class="bi bi-arrow-right-circle-fill"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

    </div>

    <!-- Bootstrap JS -->
    <script src="/assets/vendor/bootstrap.bundle.min.js"></script>
    <script>
        let cards = Array.from(document.querySelectorAll('.blog-card-perspective'));
        let activeIndex = 0;

        function updateSlider() {
            cards.forEach((card, idx) => {
                card.className = 'blog-card-perspective';

                let diff = idx - activeIndex;

                // Circular handling if needed, but here we cap or handle as a stack
                if (diff === 0) {
                    card.classList.add('card-stack-4');
                } else if (diff === -1) {
                    card.classList.add('card-stack-3');
                } else if (diff === -2) {
                    card.classList.add('card-stack-2');
                } else if (diff <= -3) {
                    card.classList.add('card-stack-1');
                    if (diff < -3) card.classList.add('card-hidden');
                } else if (diff === 1) {
                    card.classList.add('card-stack-5');
                } else if (diff === 2) {
                    card.classList.add('card-stack-6');
                } else if (diff >= 3) {
                    card.classList.add('card-stack-7');
                    if (diff > 3) card.classList.add('card-hidden');
                }
            });
        }

        function moveSlider(dir) {
            activeIndex = Math.max(0, Math.min(cards.length - 1, activeIndex + dir));
            updateSlider();
        }

        function goToCard(idx) {
            activeIndex = idx;
            updateSlider();
        }

        function filterBlogs() {
            let query = document.getElementById('blog-search').value.toLowerCase();
            let filteredCards = [];

            cards.forEach((card, idx) => {
                let title = card.getAttribute('data-title').toLowerCase();
                if (title.includes(query)) {
                    card.style.display = 'flex';
                    filteredCards.push(card);
                } else {
                    card.style.display = 'none';
                    card.className = 'blog-card-perspective card-hidden';
                }
            });

            // Update indices and layout for filtered results if needed
            // For now, we'll just show/hide. Re-indexing the slider while keeping 3D is complex,
            // but we can at least show the matches.
            if (filteredCards.length > 0) {
                // If query is empty, reset to original state
                if (query === "") {
                    activeIndex = 0;
                    updateSlider();
                } else {
                    // For simplicity, just make the first match active
                    activeIndex = cards.indexOf(filteredCards[0]);
                    updateSlider();
                }
            }
        }

        // Live Search
        document.getElementById('blog-search').addEventListener('input', filterBlogs);

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            // Check for search query in URL
            const urlParams = new URLSearchParams(window.location.search);
            const query = urlParams.get('q');
            if (query) {
                document.getElementById('blog-search').value = query;
                filterBlogs();
            } else {
                updateSlider();
            }
        });
    </script>
</body>

</html>