<?php
include_once 'inc/functions.php';
$lang = active_lang();
$filePath = __DIR__ . "/data/lang/{$lang}/iletisim/iletisim.json";
$contactData = null;

if (file_exists($filePath)) {
    $json = json_decode(file_get_contents($filePath), true);
    $contactData = $json[0] ?? null; 
}

$hasSidebar = true;
$pageTitle = $contactData['page_title'] ?? "İletişim";
include_once 'inc/header.php';
?>
<style>
    /* Premium Contact Page Styles */
    .materials-page .sidebar-title {
        font-size: 3.2rem;
        line-height: 1.1;
        margin: 0;
    }

    .contact-hero-section {
        position: relative;
        min-height: 85vh;
        height: 100%;
        background-image: url('assets/img/ref-page-bg.png');
        background-size: cover;
        background-position: center;
        overflow: hidden;
        display: flex;
        align-items: center;
    }

    .contact-hero-section::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, rgba(0, 0, 0, 0.85) 0%, rgba(0, 0, 0, 0.4) 100%);
        z-index: 1;
    }

    .contact-content-wrapper {
        position: relative;
        z-index: 5;
        width: 100%;
        padding: 32px;
    }

    /* Scaling factor 80% applied */
    .hero-text-col {
        color: #fff;
        opacity: 0;
        transform: translateX(-30px);
        animation: fadeInLeft 1s forwards;
    }

    .hero-subtitle {
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 3.2px;
        color: #e91e63;
        margin-bottom: 12px;
        display: block;
    }

    .hero-title {
        font-size: 2.4rem;
        font-weight: 900;
        line-height: 1;
        margin-bottom: 16px;
        text-transform: uppercase;
    }

    .hero-desc {
        font-size: 0.8rem;
        line-height: 1.6;
        color: rgba(255, 255, 255, 0.7);
        max-width: 400px;
        margin-bottom: 32px;
    }

    /* Info Cards */
    .contact-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
        margin-top: 32px;
    }

    .info-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        padding: 20px;
        transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    .info-card:hover {
        background: rgba(233, 30, 99, 0.1);
        border-color: rgba(233, 30, 99, 0.3);
        transform: translateY(-8px);
    }

    .info-card i {
        font-size: 1.6rem;
        color: #e91e63;
        margin-bottom: 12px;
        display: block;
    }

    .info-card h4 {
        font-size: 0.88rem;
        font-weight: 700;
        color: #fff;
        margin-bottom: 8px;
    }

    .info-card p {
        font-size: 0.68rem;
        color: rgba(255, 255, 255, 0.6);
        margin: 0;
        line-height: 1.4;
        word-break: break-word;
    }

    .info-card p a {
        color: inherit;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .info-card p a:hover {
        color: #e91e63 !important;
    }

    /* Form Section */
    .form-col-wrapper {
        opacity: 0;
        transform: translateX(30px);
        animation: fadeInRight 1s 0.3s forwards;
    }

    .premium-form-card {
        background: rgba(255, 255, 255, 0.98);
        border-radius: 24px;
        padding: 28px;
        box-shadow: 0 32px 64px rgba(0, 0, 0, 0.25);
        border: 1px solid #eee;
    }

    .form-header {
        margin-bottom: 16px;
    }

    .form-header h3 {
        font-size: 1.44rem;
        font-weight: 800;
        color: #333;
        margin-bottom: 4px;
    }

    .form-header p {
        font-size: 0.72rem;
        color: #888;
    }

    .form-dark .form-label {
        font-size: 0.6rem;
        font-weight: 700;
        text-transform: uppercase;
        color: #999;
        margin-bottom: 6px;
    }

    .form-dark .form-control, .form-dark .form-select {
        background: #f8f9fa;
        border: 1px solid #eee;
        border-radius: 10px;
        padding: 11px 16px;
        font-size: 0.72rem;
        transition: all 0.3s;
    }

    .form-dark .form-control:focus {
        background: #fff;
        border-color: #e91e63;
        box-shadow: 0 8px 20px rgba(233, 30, 99, 0.08);
    }

    .submit-premium-btn {
        width: 100%;
        background: linear-gradient(135deg, #e91e63 0%, #7b1fa2 100%);
        color: white;
        border: none;
        padding: 12px;
        border-radius: 10px;
        font-weight: 800;
        letter-spacing: 1.2px;
        text-transform: uppercase;
        box-shadow: 0 10px 24px rgba(233, 30, 99, 0.2);
        transition: all 0.4s;
        margin-top: 12px;
    }

    .submit-premium-btn:hover {
        transform: translateY(-4px);
        box-shadow: 0 15px 35px rgba(233, 30, 99, 0.35);
    }

    @keyframes fadeInLeft {
        to { opacity: 1; transform: translateX(0); }
    }

    @keyframes fadeInRight {
        to { opacity: 1; transform: translateX(0); }
    }

    /* Mobile */
    @media (max-width: 991px) {
        .contact-hero-section {
            padding: 80px 0 40px;
            height: auto;
        }
        .hero-title {
            font-size: 2.2rem;
        }
    }

    /* Social Media Styling */
    .social-media-container {
        margin-top: 36px;
    }

    .social-title {
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 3.2px;
        color: #e91e63;
        display: block;
        margin-bottom: 12px;
    }

    .social-icon-btn {
        width: 45px;
        height: 45px;
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 1.25rem;
        text-decoration: none;
        transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    .social-icon-btn:hover {
        color: #fff !important;
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
    }

    .social-icon-btn.facebook:hover {
        background: #1877f2;
        border-color: #1877f2;
        box-shadow: 0 10px 20px rgba(24, 119, 242, 0.35);
    }

    .social-icon-btn.instagram:hover {
        background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
        border-color: transparent;
        box-shadow: 0 10px 20px rgba(220, 39, 67, 0.35);
    }

    .social-icon-btn.youtube:hover {
        background: #ff0000;
        border-color: #ff0000;
        box-shadow: 0 10px 20px rgba(255, 0, 0, 0.35);
    }

    .social-icon-btn.linkedin:hover {
        background: #0077b5;
        border-color: #0077b5;
        box-shadow: 0 10px 20px rgba(0, 119, 181, 0.35);
    }

    .social-icon-btn.pinterest:hover {
        background: #bd081c;
        border-color: #bd081c;
        box-shadow: 0 10px 20px rgba(189, 8, 28, 0.35);
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
                <?= statik('sidebar_title_contact') ?>
            </h1>
        </div>
        <div class="sidebar-footer">
            <?= statik('sidebar_footer') ?>
        </div>
    </div>

    <div class="main-content">

        <!-- Üst Menü -->
        <?php include_once 'inc/navbar.php'; ?>

        <section class="contact-hero-section">
            <div class="container-fluid contact-content-wrapper">
                <div class="row align-items-center">
                    <div class="col-lg-6 hero-text-col">
                        <span class="hero-subtitle"><?php echo $contactData['intro_subtitle']; ?></span>
                        <h2 class="hero-title"><?php echo $contactData['intro_title']; ?></h2>
                        <p class="hero-desc"><?php echo $contactData['intro_desc']; ?></p>

                        <div class="contact-info-grid">
                            <?php foreach ($contactData['contact_info'] as $info): ?>
                                <div class="info-card">
                                    <i class="bi <?php echo $info['icon']; ?>"></i>
                                    <h4><?php echo $info['title']; ?></h4>
                                    <p><?php echo $info['value']; ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Social Media Links -->
                        <div class="social-media-container">
                            <span class="social-title"><?= statik('contact_social_media') ?></span>
                            <div class="social-icons-wrapper d-flex gap-3 mt-2">
                                <a href="https://www.facebook.com/umuttasarim" target="_blank" title="Facebook" class="social-icon-btn facebook">
                                    <i class="bi bi-facebook"></i>
                                </a>
                                <a href="https://instagram.com/umuttasarimltd" target="_blank" title="Instagram" class="social-icon-btn instagram">
                                    <i class="bi bi-instagram"></i>
                                </a>
                                <a href="https://www.youtube.com/@umuttasarimltd" target="_blank" title="YouTube" class="social-icon-btn youtube">
                                    <i class="bi bi-youtube"></i>
                                </a>
                                <a href="https://www.linkedin.com/company/umuttasarim/" target="_blank" title="LinkedIn" class="social-icon-btn linkedin">
                                    <i class="bi bi-linkedin"></i>
                                </a>
                                <a href="https://www.pinterest.com/umuttasarimltd/" target="_blank" title="Pinterest" class="social-icon-btn pinterest">
                                    <i class="bi bi-pinterest"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5 offset-lg-1 form-col-wrapper">
                        <div class="premium-form-card">
                            <div class="form-header">
                                <h3><?php echo $contactData['form_title']; ?></h3>
                                <p><?= statik('contact_form_subtitle') ?></p>
                            </div>

                            <form id="contactForm" action="iletisim_islem.php" method="POST" class="form-dark">
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label class="form-label"><?php echo $contactData['form_fields']['name_label']; ?></label>
                                        <input type="text" class="form-control" name="ad_soyad" required placeholder="<?= statik('career_form_placeholder_name') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?php echo $contactData['form_fields']['email_label']; ?></label>
                                        <input type="email" class="form-control" name="eposta" required placeholder="<?= statik('career_form_placeholder_email') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?php echo $contactData['form_fields']['subject_label']; ?></label>
                                        <input type="text" class="form-control" name="konu" placeholder="<?= statik('contact_form_placeholder_subject') ?>">
                                    </div>
                                    <div class="col-12 mb-3">
                                        <label class="form-label"><?php echo $contactData['form_fields']['message_label']; ?></label>
                                        <textarea class="form-control" name="mesaj" rows="4" required placeholder="<?= statik('contact_form_placeholder_message') ?>"></textarea>
                                    </div>
                                </div>
                                <button type="submit" class="submit-premium-btn"><?= statik('contact_form_submit') ?></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </div>

    <!-- Scripts -->
    <script src="assets/vendor/bootstrap.bundle.min.js"></script>
</body>
</html>
