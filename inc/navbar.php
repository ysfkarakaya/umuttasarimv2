<?php
/**
 * Navbar component
 * Supports both Index (split) and Standard layouts.
 */
$lang = active_lang();
$menu_items = get_menu_config($lang);
$isIndexPage = isset($isIndex) && $isIndex === true;
?>

<?php if ($isIndexPage): ?>
    <!-- Index Navbar (Split Layout) -->
    <nav class="navbar custom-nav header-split sticky-top">
        <div class="container-fluid">
            <div class="nav-split-container">
                <!-- Sol Menü -->
                <div class="nav-half left d-none d-lg-flex">
                    <?php
                    if (isset($menu_items['home']))
                        render_nav_item($menu_items['home']);
                    if (isset($menu_items['institutional']))
                        render_nav_item($menu_items['institutional']);
                    if (isset($menu_items['products']))
                        render_nav_item($menu_items['products']);
                    ?>
                </div>

                <!-- Ortalanmış Logo -->
                <a href="index.php" class="header-logo-badge">
                    <img src="assets/img/logo2.png" alt="Logo">
                </a>

                <!-- Sağ Grup (Menü + İkonlar + Buton) -->
                <div class="nav-half right d-none d-lg-flex">
                    <?php
                    if (isset($menu_items['referanslar']))
                        render_nav_item($menu_items['referanslar']);
                    if (isset($menu_items['blog']))
                        render_nav_item($menu_items['blog']);
                    if (isset($menu_items['kariyer']))
                        render_nav_item($menu_items['kariyer']);
                    if (isset($menu_items['portal']))
                        render_nav_item($menu_items['portal']);
                    if (isset($menu_items['support']))
                        render_nav_item($menu_items['support']);
                    ?>

                    <!-- Menü yanındaki ikonlar -->
                    <div class="nav-icons">
                        <!-- Arama Kutusu -->
                        <div class="nav-search-container">
                            <form action="search.php" method="GET" class="nav-search-form">
                                <input type="text" name="q" class="nav-search-input"
                                    placeholder="<?php echo $lang === 'tr' ? 'Ürün ara...' : 'Search products...'; ?>"
                                    required>
                                <button type="submit" class="nav-search-btn">
                                    <i class="bi bi-search"></i>
                                </button>
                            </form>
                        </div>
                        <!-- Dil Seçeneği Submenu -->
                        <div class="nav-item has-submenu lang-switcher">
                            <i class="bi bi-globe"></i>
                            <div class="submenu-mega lang-menu">
                                <div class="lang-grid">
                                    <a href="?lang=tr" class="lang-option <?php echo $lang === 'tr' ? 'active' : ''; ?>">
                                        <span class="lang-text">TR</span>
                                        <span class="lang-name">Türkçe</span>
                                    </a>
                                    <a href="?lang=en" class="lang-option <?php echo $lang === 'en' ? 'active' : ''; ?>">
                                        <span class="lang-text">EN</span>
                                        <span class="lang-name">English</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (isset($menu_items['contact']))
                        render_nav_item($menu_items['contact'], 'nav-link contact-btn ms-3'); ?>
                </div>

                <!-- Mobil Toggle -->
                <button class="navbar-toggler d-lg-none ms-auto" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarNavUnified">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>

            <!-- Mobil Menü Konteynırı -->
            <div class="collapse navbar-collapse d-lg-none" id="navbarNavUnified">
                <ul class="navbar-nav py-3">
                    <?php
                    foreach ($menu_items as $key => $item) {
                        render_mobile_nav_item($item, 'mob' . ucfirst($key));
                    }
                    ?>
                </ul>
            </div>
        </div>
    </nav>
<?php else: ?>
    <!-- Standard Navbar -->
    <nav class="navbar navbar-expand-lg custom-nav sticky-top">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <a class="navbar-brand d-lg-none" href="index.php">
                <img src="assets/img/logo2.png" alt="Logo" style="height: 70px; width: auto;">
            </a>
            <div class="d-none d-lg-flex w-100 align-items-center">
                <ul class="navbar-nav me-auto align-items-center">
                    <?php
                    foreach ($menu_items as $key => $item) {
                        if ($key === 'contact')
                            continue;
                        render_nav_item($item);
                    }
                    ?>
                </ul>
                <div class="nav-icons">
                    <!-- Arama Kutusu -->
                    <div class="nav-search-container">
                        <form action="search.php" method="GET" class="nav-search-form">
                            <input type="text" name="q" class="nav-search-input"
                                placeholder="<?php echo $lang === 'tr' ? 'Ürün ara...' : 'Search products...'; ?>" required>
                            <button type="submit" class="nav-search-btn">
                                <i class="bi bi-search"></i>
                            </button>
                        </form>
                    </div>
                    <div class="nav-item has-submenu lang-switcher">
                        <i class="bi bi-globe"></i>
                        <div class="submenu-mega lang-menu">
                            <div class="lang-grid">
                                <a href="?lang=tr" class="lang-option <?php echo $lang === 'tr' ? 'active' : ''; ?>">
                                    <span class="lang-text">TR</span>
                                    <span class="lang-name">Türkçe</span>
                                </a>
                                <a href="?lang=en" class="lang-option <?php echo $lang === 'en' ? 'active' : ''; ?>">
                                    <span class="lang-text">EN</span>
                                    <span class="lang-name">English</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if (isset($menu_items['contact']))
                    render_nav_item($menu_items['contact'], 'nav-link contact-btn ms-3'); ?>
            </div>
            <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarNavUnified">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse d-lg-none w-100" id="navbarNavUnified">
                <ul class="navbar-nav py-3 px-3">
                    <?php
                    foreach ($menu_items as $key => $item) {
                        render_mobile_nav_item($item, 'mob' . ucfirst($key));
                    }
                    ?>
                </ul>
            </div>
        </div>
    </nav>
<?php endif; ?>

<style>
    .nav-search-container {
        position: relative;
        display: flex;
        align-items: center;
        /* margin-right: 15px; */
    }

    .nav-search-form {
        display: flex;
        align-items: center;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 30px;
        padding: 2px 5px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        width: 40px;
        overflow: hidden;
    }

    .nav-search-form:focus-within,
    .nav-search-form.active {
        width: 220px;
        background: rgba(255, 255, 255, 0.15);
        border-color: #d81b60;
        box-shadow: 0 0 15px rgba(216, 27, 96, 0.2);
    }

    .nav-search-input {
        background: transparent;
        border: none;
        outline: none;
        color: #333;
        font-size: 0.85rem;
        width: 0;
        padding: 0;
        transition: all 0.3s ease;
        opacity: 0;
    }

    .nav-search-form:focus-within .nav-search-input,
    .nav-search-form.active .nav-search-input {
        width: 170px;
        padding: 0 10px;
        opacity: 1;
    }

    .nav-search-input::placeholder {
        color: #999;
    }

    .nav-search-btn {
        background: transparent;
        border: none;
        color: #333;
        cursor: pointer;
        font-size: 1.1rem;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        transition: color 0.3s ease;
    }

    .nav-search-btn:hover {
        color: #d81b60;
    }

    /* Split Header (Index) - Often needs white icons if on dark hero, but user reports invisible so defaulting to dark or themed */
    .header-split .nav-search-btn,
    .header-split .nav-search-input {
        color: #333;
        /* Default to dark as user suggested it's invisible (white on white) */
    }

    /* .header-split .nav-search-form {
        background: rgba(0, 0, 0, 0.05);
        border-color: rgba(0, 0, 0, 0.1);
    } */

    .header-split .nav-search-form:focus-within {
        background: #fff;
        border-color: #d81b60;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const searchForms = document.querySelectorAll('.nav-search-form');
        const searchBtns = document.querySelectorAll('.nav-search-btn');

        searchBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const form = btn.closest('.nav-search-form');
                const input = form.querySelector('.nav-search-input');

                if (!form.classList.contains('active')) {
                    e.preventDefault();
                    form.classList.add('active');
                    setTimeout(() => input.focus(), 100);
                } else if (input.value.trim() === '') {
                    e.preventDefault();
                    form.classList.remove('active');
                }
            });
        });

        // Handle Enter key explicitly in input
        document.querySelectorAll('.nav-search-input').forEach(input => {
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    const form = input.closest('.nav-search-form');
                    if (input.value.trim() === '') {
                        e.preventDefault();
                        form.classList.remove('active');
                    }
                    // If not empty, browser will naturally submit the form
                }
            });
        });

        // Close search if clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.nav-search-container')) {
                searchForms.forEach(form => form.classList.remove('active'));
            }
        });
    });
</script>