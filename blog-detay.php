<?php
include_once 'inc/functions.php';
$lang = active_lang();
$menu_items = get_menu_config($lang);

$slug = $_GET['name'] ?? '';
$blog_detail_file = __DIR__ . "/data/lang/{$lang}/blog/{$slug}.json";

if (empty($slug) || !file_exists($blog_detail_file)) {
    header("Location: /blog");
    exit;
}

$post = json_decode(file_get_contents($blog_detail_file), true);

// Get main blog header for title etc.
$blog_file = __DIR__ . "/data/lang/{$lang}/kartlar/blog.json";
$blog_header = ['kart_adi' => 'Blog'];
if (file_exists($blog_file)) {
    $blog_all = json_decode(file_get_contents($blog_file), true);
    $blog_header = $blog_all[0] ?? ['kart_adi' => 'Blog'];
}

$pageTitle = $post['kart_detay_adi'] . ' | ' . $blog_header['kart_adi'];
$hasSidebar = true;
include_once 'inc/header.php';
?>

<style>
    @media (min-width: 992px) {
        body.blog-detail-page {
            overflow: hidden;
            height: 100vh;
        }

        body.blog-detail-page .main-content {
            height: 100vh;
            display: flex;
            flex-direction: column;
            background: #0d1117 !important;
        }
    }

    .blog-detail-container {
        zoom: 0.82;
        background: #0d1117;
        flex: 1;
        min-height: calc(100vh / 0.82);
        padding: 20px 60px;
        position: relative;
        color: #fff;
        display: flex;
        flex-direction: column;
        width: 100%;
        overflow: hidden;
    }

    /* Background Decoration (Same as blog.php) */
    .blog-bg-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: url('/upload/blog-bg.jpg');
        background-size: cover;
        background-position: center;
        opacity: 0.25;
        pointer-events: none;
        z-index: 1;
    }

    /* Hero Section Content (Exactly like blog.php) */
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

    /* Search Bar (Same as blog.php) */
    .blog-search-wrapper {
        display: flex;
        background: #eee;
        border-radius: 50px;
        overflow: hidden;
        margin-top: 15px;
        max-width: 340px;
        height: 44px;
        align-items: center;
    }

    .blog-search-wrapper input {
        border: none;
        background: transparent;
        padding: 0 15px 0 20px;
        flex: 1;
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
        border-radius: 0 50px 50px 0;
    }

    /* Detail Card (Radius Container) */
    .detail-card {
        position: relative;
        z-index: 10;
        background: #fff;
        border-radius: 30px;
        box-shadow: 0 40px 100px rgba(0, 0, 0, 0.4);
        max-width: 1200px;
        width: 100%;
        margin: 0 auto 50px auto;
        flex: 1;
        max-height: 700px;
        overflow: hidden;
        /* Clips the inner scrollbar and content to preserve radius */
    }

    /* Inner Scrollable Area */
    .detail-card-inner {
        width: 100%;
        height: 100%;
        padding: 50px 60px;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #8cc34b #f4f4f4;
    }

    .detail-card-inner::-webkit-scrollbar {
        width: 8px;
    }

    .detail-card-inner::-webkit-scrollbar-track {
        background: #f4f4f4;
        border-radius: 0;
    }

    .detail-card-inner::-webkit-scrollbar-thumb {
        background: #8cc34b;
        border-radius: 10px;
    }

    .detail-header h1 {
        font-size: 2.2rem;
        font-weight: 800;
        color: #333;
        /* Darker for better visibility */
        text-transform: uppercase;
        margin-bottom: 5px;
        line-height: 1.1;
    }

    .detail-header .subtitle {
        font-size: 1.1rem;
        font-weight: 700;
        color: #8cc34b;
        /* Theme green highlight */
        text-transform: uppercase;
        margin-bottom: 35px;
        display: block;
        letter-spacing: 1px;
    }

    .detail-content {
        font-size: 1rem;
        line-height: 1.7;
        color: #555;
        /* Neutral professional gray */
    }

    .detail-content p {
        margin-bottom: 20px;
        text-align: justify;
    }

    .content-row {
        display: flex;
        gap: 40px;
        align-items: flex-start;
        margin-bottom: 40px;
    }

    .content-text {
        flex: 1;
    }

    .content-img {
        width: 450px;
        border-radius: 40px;
        overflow: hidden;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    .content-img img {
        width: 100%;
        display: block;
    }

    .highlight-bold {
        font-weight: 700;
        color: #444;
    }

    .detail-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 40px;
        padding-top: 30px;
        border-top: 1px solid #eee;
    }

    .back-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        color: #8cc34b;
        font-weight: 700;
        font-size: 1.1rem;
        transition: 0.3s;
    }

    .back-btn:hover {
        transform: translateX(-5px);
    }

    .devam-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
        color: #d81b60;
        font-weight: 700;
        font-size: 1.1rem;
    }

    .devam-btn i {
        font-size: 1.8rem;
    }

    @media (max-width: 991px) {
        .blog-detail-container {
            zoom: 1;
            padding: 80px 15px 40px 15px;
            min-height: auto;
            overflow-y: auto;
        }

        .detail-card {
            padding: 0;
            overflow-y: visible;
            max-height: none;
            height: auto;
            margin-bottom: 40px;
            border-radius: 20px;
        }

        .detail-card-inner {
            padding: 30px 20px;
            height: auto;
            overflow-y: visible;
        }

        .detail-header h1 {
            font-size: 1.6rem;
            text-align: center;
        }

        .detail-header .subtitle {
            font-size: 0.9rem;
            text-align: center;
            margin-bottom: 25px;
        }

        .detail-content {
            font-size: 1rem;
            line-height: 1.6;
        }

        .content-row {
            gap: 20px;
        }

        .content-img {
            border-radius: 20px;
        }

        .blog-hero-content {
            flex-direction: column;
            gap: 20px;
            text-align: center;
            margin-top: 0;
        }

        .hero-left-title {
            padding-right: 0;
            text-align: center;
        }

        .hero-left-title h2 {
            font-size: 1.5rem;
        }

        .hero-left-title .highlight {
            font-size: 1.7rem;
        }

        .hero-right-desc {
            border-left: none;
            padding-left: 0;
            border-top: 4px solid #8cc34b;
            padding-top: 20px;
            max-width: 100%;
            flex: none;
        }


    }


    .blog-detail-page .sidebar-title {
        font-size: 3.2rem;
        margin: 0;
        position: relative;
        z-index: 2;
        text-transform: capitalize;
    }

</style>

<body class="blog-detail-page">

    <!-- Sol Panel -->
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

        <section class="blog-detail-container">
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
                        <input type="text" placeholder="<?= statik('blog_search_placeholder') ?>" id="blog-search">
                        <button type="button"
                            onclick="location.href='/blog?q='+document.getElementById('blog-search').value"><?= statik('blog_search_btn') ?></button>
                    </div>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-card-inner">
                    <div class="detail-header">
                        <h1><?php echo $post['kart_detay_adi']; ?></h1>
                        <?php if (!empty($post['kart_detay_etiket'])): ?>
                            <span class="subtitle"><?php echo $post['kart_detay_etiket']; ?></span>
                        <?php else: ?>
                            <span class="subtitle"><?= statik('blog_detail_default_label') ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="detail-content">
                        <?php
                        $full_text = strip_tags($post['kart_detay_aciklama']);
                        $paragraphs = explode("\n", $post['kart_detay_aciklama']);
                        $paragraphs = array_values(array_filter($paragraphs));
                        ?>

                        <!-- First row -->
                        <div class="content-row">
                            <div class="content-text">
                                <p><?php echo ($paragraphs[0] ?? ''); ?></p>
                                <p><?php echo ($paragraphs[1] ?? ''); ?></p>
                            </div>
                            <div class="content-img">
                                <img src="/<?php echo ltrim($post['kart_detay_gorsel'], '/'); ?>" alt="Detail 1">
                            </div>
                        </div>

                        <!-- Second row -->
                        <div class="content-row" style="flex-direction: row-reverse;">
                            <div class="content-text">
                                <p><?php echo ($paragraphs[2] ?? ''); ?></p>
                                <p><?php echo ($paragraphs[3] ?? ''); ?></p>
                            </div>
                            <div class="content-img">
                                <img src="/<?php echo ltrim($post['kart_detay_gorsel'], '/'); ?>" alt="Detail 2"
                                    style="filter: hue-rotate(45deg) saturate(1.2);">
                            </div>
                        </div>

                        <?php
                        // Remaining text
                        if (count($paragraphs) > 4) {
                            for ($i = 4; $i < count($paragraphs); $i++) {
                                echo '<p>' . $paragraphs[$i] . '</p>';
                            }
                        }
                        ?>
                    </div>

                    <div class="detail-footer">
                        <a href="/blog" class="back-btn">
                            <i class="bi bi-arrow-left-circle-fill" style="font-size: 1.8rem;"></i> <?= statik('blog_detail_back') ?>
                        </a>
                        <a href="#" class="devam-btn">
                            <?= statik('blog_detail_next') ?> <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </section>

    </div>

    <!-- Bootstrap JS -->
    <script src="assets/vendor/bootstrap.bundle.min.js"></script>
</body>

</html>