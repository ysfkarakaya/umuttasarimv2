<?php
$hasSidebar = true;

include_once 'inc/functions.php';

function load_product_detail_data($lang, $sefUrl)
{
    if ($sefUrl === '') {
        return [];
    }

    $filePath = __DIR__ . "/data/lang/{$lang}/urun-detay/{$sefUrl}.json";
    if (!file_exists($filePath)) {
        return [];
    }

    $data = json_decode(file_get_contents($filePath), true);
    return is_array($data) ? $data : [];
}

function normalize_product_asset_path($path, $fallback = 'assets/img/park1.png')
{
    $path = trim((string) $path);
    if ($path === '') {
        return $fallback;
    }

    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (in_array($extension, ['dwg', 'dxf', 'pdf'], true)) {
        return $fallback;
    }

    return '/' . ltrim(str_replace('\\', '/', $path), '/');
}

function resolve_product_model_src(array $detailData, array $fallbackData = [])
{
    $modelPath = trim((string) ($detailData['360_obje'] ?? $fallbackData['360_obje'] ?? ''));
    if ($modelPath === '') {
        return '';
    }

    $normalized = str_replace('\\', '/', $modelPath);
    if (preg_match('#^https?://#i', $normalized)) {
        return $normalized;
    }

    return '/' . ltrim($normalized, '/');
}

$lang = active_lang();
$sefUrl = isset($_GET['sef_url']) ? trim((string) $_GET['sef_url']) : '';
$sefUrl = preg_replace('/[^a-zA-Z0-9_-]/', '', $sefUrl);

if ($sefUrl === '') {
    $sefUrl = 'eko-01';
}

$productData = load_product_detail_data($lang, $sefUrl);

if (empty($productData) && $sefUrl !== 'eko-01') {
    $sefUrl = 'eko-01';
    $productData = load_product_detail_data($lang, $sefUrl);
}

$activeProductName = trim((string) ($productData['urun_adi'] ?? 'Ürün'));
$activeCategoryName = trim((string) ($productData['kat_adi'] ?? ''));
$activeProductImage = normalize_product_asset_path($productData['kapak_resmi'] ?? '');
$activeProductModel = resolve_product_model_src($productData);

$sliderProducts = [];
$seenProductSlugs = [];

$appendSliderProduct = function (array $detailData, array $fallbackData = []) use (&$sliderProducts, &$seenProductSlugs) {
    $productSlug = trim((string) ($detailData['sef_url'] ?? $fallbackData['sef_url'] ?? ''));
    if ($productSlug === '' || isset($seenProductSlugs[$productSlug])) {
        return;
    }

    $productName = trim((string) ($detailData['urun_adi'] ?? $fallbackData['urun_adi'] ?? 'Ürün'));
    $productCategory = trim((string) ($detailData['kat_adi'] ?? $fallbackData['kat_adi'] ?? ''));
    $productImage = normalize_product_asset_path($detailData['kapak_resmi'] ?? $fallbackData['kapak_resmi'] ?? '');
    $productDetailText = trim(strip_tags(html_entity_decode((string) ($detailData['urun_detay'] ?? $detailData['urun_aciklama'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

    $productHotspots = [];
    foreach (($detailData['hotspotlar'] ?? []) as $hotspotItem) {
        if (!is_array($hotspotItem)) {
            continue;
        }

        $hotspotX = isset($hotspotItem['konum_x']) ? (float) $hotspotItem['konum_x'] : null;
        $hotspotY = isset($hotspotItem['konum_y']) ? (float) $hotspotItem['konum_y'] : null;

        if ($hotspotX === null || $hotspotY === null) {
            continue;
        }

        $hotspotX = max(0, min(100, $hotspotX - 1));
        $hotspotY = max(0, min(100, $hotspotY));

        $hotspotTitle = trim((string) ($hotspotItem['nokta_basligi'] ?? 'Detay'));
        $hotspotDescription = trim((string) ($hotspotItem['nokta_aciklama'] ?? ''));
        $hotspotImage = normalize_product_asset_path($hotspotItem['nokta_gorseli'] ?? '', $productImage);

        $productHotspots[] = [
            'x' => $hotspotX,
            'y' => $hotspotY,
            'title' => $hotspotTitle !== '' ? $hotspotTitle : 'Detay',
            'description' => $hotspotDescription,
            'image' => $hotspotImage,
        ];
    }

    $productColors = [];
    foreach (($detailData['renkler'] ?? []) as $colorItem) {
        if (!is_array($colorItem)) {
            continue;
        }

        $colorName = trim((string) ($colorItem['renk_adi'] ?? ''));
        $colorCode = trim((string) ($colorItem['renk_kodu'] ?? ''));

        if ($colorName === '' && $colorCode === '') {
            continue;
        }

        $productColors[] = [
            'name' => $colorName !== '' ? $colorName : 'Renk',
            'code' => $colorCode,
        ];
    }

    $productAttachments = [];
    foreach (($detailData['medya'] ?? []) as $mediaItem) {
        if (!is_array($mediaItem)) {
            continue;
        }

        $mediaCategory = trim((string) ($mediaItem['um_kategori'] ?? ''));
        if (!in_array($mediaCategory, ['Dosya', '360'], true)) {
            continue;
        }

        $mediaFile = trim((string) ($mediaItem['um_dosya'] ?? ''));
        if ($mediaFile === '') {
            continue;
        }

        $attachmentUrl = normalize_product_asset_path($mediaFile, '');
        if ($attachmentUrl === '') {
            continue;
        }

        $attachmentTitle = trim((string) ($mediaItem['um_baslik'] ?? ''));
        $extension = strtoupper((string) pathinfo($mediaFile, PATHINFO_EXTENSION));

        $productAttachments[] = [
            'name' => $attachmentTitle !== '' ? $attachmentTitle : (($extension !== '' ? $extension : 'Dosya') . ' Dosyası'),
            'url' => $attachmentUrl,
            'category' => $mediaCategory,
            'extension' => $extension,
        ];
    }

    // Only DXF files are supported for in-browser preview
    $productDrawingFile = '';
    foreach (($detailData['medya'] ?? []) as $mItem) {
        if (!is_array($mItem))
            continue;
        $mFile = trim((string) ($mItem['um_dosya'] ?? ''));
        if (strtolower(pathinfo($mFile, PATHINFO_EXTENSION)) === 'dxf') {
            $productDrawingFile = ltrim(str_replace('\\', '/', $mFile), '/');
            break;
        }
    }

    $productGeneralSpecs = [];
    $productTechnicalSpecs = [];
    foreach (($detailData['ozellikler'] ?? []) as $featureItem) {
        if (!is_array($featureItem)) {
            continue;
        }

        $featureLabel = trim((string) ($featureItem['uo_adi'] ?? ''));
        $featureValue = trim((string) ($featureItem['uoz_deger'] ?? ''));
        $featureCategory = trim((string) ($featureItem['uo_kategori'] ?? ''));

        if ($featureLabel === '' && $featureValue === '') {
            continue;
        }

        $normalizedFeature = [
            'label' => $featureLabel !== '' ? $featureLabel : 'Özellik',
            'value' => $featureValue,
        ];

        if ($featureCategory === 'Genel Özellikler') {
            $productGeneralSpecs[] = $normalizedFeature;
        }

        if (stripos($featureCategory, 'Teknik') !== false) {
            $productTechnicalSpecs[] = $normalizedFeature;
        }
    }

    $productSimilars = [];
    foreach (($detailData['benzerler'] ?? []) as $similarItem) {
        if (!is_array($similarItem)) {
            continue;
        }

        if (isset($similarItem['urun_durum']) && (int) $similarItem['urun_durum'] !== 1) {
            continue;
        }

        $similarSlug = trim((string) ($similarItem['sef_url'] ?? ''));
        if ($similarSlug === '') {
            continue;
        }

        $similarName = trim((string) ($similarItem['urun_adi'] ?? 'Ürün'));
        $similarCategory = trim((string) ($similarItem['kat_adi'] ?? ''));
        $similarImage = normalize_product_asset_path($similarItem['kapak_resmi'] ?? '', $productImage);

        $productSimilars[] = [
            'name' => $similarName !== '' ? $similarName : 'Ürün',
            'sef_url' => $similarSlug,
            'category' => $similarCategory,
            'image' => $similarImage,
        ];
    }

    $sliderProducts[] = [
        'name' => $productName !== '' ? $productName : 'Ürün',
        'sef_url' => $productSlug,
        'category' => $productCategory,
        'image' => $productImage,
        'detail' => $productDetailText,
        'model' => resolve_product_model_src($detailData, $fallbackData),
        'hotspots' => $productHotspots,
        'colors' => $productColors,
        'attachments' => $productAttachments,
        'drawing' => $productDrawingFile,
        'general_specs' => $productGeneralSpecs,
        'technical_specs' => $productTechnicalSpecs,
        'similars' => $productSimilars,
        'kat_sef_url' => trim((string) ($detailData['kat_sef_url'] ?? $fallbackData['kat_sef_url'] ?? '')),
    ];

    $seenProductSlugs[$productSlug] = true;
};

$appendSliderProduct($productData);

foreach (($productData['benzerler'] ?? []) as $similarProduct) {
    if (!is_array($similarProduct)) {
        continue;
    }

    if (isset($similarProduct['urun_durum']) && (int) $similarProduct['urun_durum'] !== 1) {
        continue;
    }

    $similarSlug = trim((string) ($similarProduct['sef_url'] ?? ''));
    if ($similarSlug === '') {
        continue;
    }

    $similarDetailData = load_product_detail_data($lang, $similarSlug);
    $appendSliderProduct($similarDetailData, $similarProduct);
}

if (empty($sliderProducts)) {
    $sliderProducts[] = [
        'name' => $activeProductName !== '' ? $activeProductName : 'Ürün',
        'sef_url' => $sefUrl,
        'category' => $activeCategoryName,
        'image' => $activeProductImage,
        'detail' => '',
        'model' => resolve_product_model_src($productData),
        'hotspots' => [],
        'colors' => [],
        'attachments' => [],
        'drawing' => '',
        'general_specs' => [],
        'technical_specs' => [],
        'similars' => [],
        'kat_sef_url' => '',
    ];
}

$initialHasModel = !empty($sliderProducts[0]['model']);

$pageTitle = $activeProductName !== '' ? $activeProductName : 'Ürün Detay';
include_once 'inc/header.php';

$sliderProductsJson = json_encode(
    $sliderProducts,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
<!-- Google Model Viewer -->
<script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/4.0.0/model-viewer.min.js"></script>

<style>
    /* Materials Page Specific Styles */
    .materials-page .sidebar-title {
        font-size: 3.2rem;
        line-height: 1.1;
        margin: 0;
    }

    .materials-section {
        padding: 40px;
        position: relative;
        min-height: 90vh;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #fff;
    }

    .specs-badge-container {
        position: absolute;
        top: 30px;
        left: 30px;
        z-index: 100;
    }

    .specs-badge {
        width: 105px;
        height: 105px;
        background: linear-gradient(135deg, #e91e63 0%, #7b1fa2 100%);
        border-radius: 9px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 19px;
        color: #fff;
        cursor: pointer;
        box-shadow: 0 11px 26px rgba(233, 30, 99, 0.25);
        transition: all 0.3s ease;
    }

    .specs-badge::after {
        content: "";
        position: absolute;
        top: 10px;
        right: 10px;
        width: 0;
        height: 0;
        border-style: solid;
        border-width: 0 15px 15px 0;
        border-color: transparent #fff transparent transparent;
        opacity: 0.3;
    }

    .specs-badge-container:hover .specs-badge {
        transform: scale(0.95);
    }

    .active-spec-slot {
        margin-top: 15px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        pointer-events: none;
    }

    .active-spec-slot .spec-card {
        width: 105px;
        height: 105px;
        box-shadow: 0 11px 26px rgba(0, 0, 0, 0.1);
        pointer-events: auto;
        opacity: 0;
        transform: translateY(-10px);
        transition: all 0.4s ease;
    }

    .active-spec-slot .spec-card.active {
        opacity: 1;
        transform: translateY(0);
    }

    /* Specs Details Window (formerly Modal) */
    .specs-modal {
        position: absolute;
        top: 0;
        left: 100%;
        margin-left: 20px;
        z-index: 2000;
        display: block;
        opacity: 0;
        visibility: hidden;
        transform: translateX(20px);
        transition: all 0.5s cubic-bezier(0.165, 0.84, 0.44, 1);
        pointer-events: none;
    }

    .specs-modal.active {
        opacity: 1;
        visibility: visible;
        transform: translateX(0);
        pointer-events: auto;
    }

    .specs-modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        /* background: rgba(0, 0, 0, 0.3); */
        backdrop-filter: blur(5px);
        z-index: -1;
    }

    .specs-modal.active .specs-modal-overlay {
        display: block;
    }

    .specs-modal-container {
        position: relative;
        width: 800px;
        background: rgba(255, 255, 255, 0.65);
        backdrop-filter: blur(25px);
        border-radius: 25px;
        padding: 30px;
        box-shadow: 0 30px 70px rgba(0, 0, 0, 0.08);
        border: 1px solid rgba(255, 255, 255, 0.5);
        display: flex;
        gap: 30px;
    }

    .specs-modal.active .specs-modal-container {
        transform: none;
    }

    .specs-modal-close {
        position: absolute;
        top: 30px;
        right: 30px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        transition: all 0.3s;
        border: none;
        color: #333;
        font-size: 1.2rem;
    }

    .specs-modal-close:hover {
        transform: rotate(90deg);
        background: #e91e63;
        color: #fff;
    }

    .modal-image-col {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
    }

    .cert-overlay-icon {
        position: absolute;
        top: 10px;
        right: 10px;
        width: 70px;
        height: 70px;
        background: linear-gradient(135deg, #e91e63 0%, #7b1fa2 100%);
        color: #fff;
        border-radius: 50%;
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        box-shadow: 0 10px 25px rgba(233, 30, 99, 0.3);
        z-index: 10;
        animation: certPulse 2s infinite ease-in-out;
    }

    @keyframes certPulse {
        0% {
            transform: scale(1);
            box-shadow: 0 10px 25px rgba(233, 30, 99, 0.3);
        }

        50% {
            transform: scale(1.05);
            box-shadow: 0 15px 35px rgba(233, 30, 99, 0.5);
        }

        100% {
            transform: scale(1);
            box-shadow: 0 10px 25px rgba(233, 30, 99, 0.3);
        }
    }

    .modal-image-col img {
        width: 100%;
        height: auto;
        max-height: 60vh;
        object-fit: contain;
    }

    .modal-info-col {
        width: 350px;
    }

    .modal-info-col h2 {
        font-size: 2rem;
        color: #9dbb6d;
        font-weight: 500;
        margin-bottom: 20px;
    }

    .dim-list {
        list-style: none;
        padding: 0;
        margin: 0;
        max-height: 360px;
        overflow-y: auto;
        overflow-x: hidden;
        padding-right: 6px;
        scrollbar-width: thin;
        scrollbar-color: rgba(233, 30, 99, 0.45) rgba(17, 24, 39, 0.08);
    }

    .dim-list::-webkit-scrollbar {
        width: 8px;
    }

    .dim-list::-webkit-scrollbar-track {
        background: rgba(17, 24, 39, 0.08);
        border-radius: 999px;
    }

    .dim-list::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, rgba(233, 30, 99, 0.8) 0%, rgba(123, 31, 162, 0.8) 100%);
        border-radius: 999px;
    }

    .dim-list::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(180deg, rgba(233, 30, 99, 0.95) 0%, rgba(123, 31, 162, 0.95) 100%);
    }

    .dim-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 0;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        color: #666;
        font-size: 1.1rem;
    }

    .dim-value {
        color: #333;
        font-weight: 500;
    }

    .color-options-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .color-option-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        padding: 14px 0;
        border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    }

    .color-option-meta {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
    }

    .color-swatch {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        border: 2px solid rgba(0, 0, 0, 0.08);
        flex: 0 0 42px;
        box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.3);
    }

    .color-option-name {
        color: #333;
        font-size: 1rem;
        font-weight: 600;
    }

    .color-option-code {
        color: #777;
        font-size: 0.9rem;
        font-weight: 500;
        text-transform: uppercase;
    }

    /* Color Sphere Carousel */
    .color-sphere-container {
        position: relative;
        height: 220px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-top: 10px;
        perspective: 1000px;
    }

    .color-sphere-wrapper {
        position: relative;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .color-sphere-item {
        position: absolute;
        width: 90px;
        height: 90px;
        border-radius: 50%;
        transition: all 0.7s cubic-bezier(0.34, 1.56, 0.64, 1);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1;
        /* Core 3D Sphere Look */
        box-shadow:
            inset -15px -15px 40px rgba(0, 0, 0, 0.4),
            inset 10px 10px 20px rgba(255, 255, 255, 0.2),
            0 15px 30px rgba(0, 0, 0, 0.2);
        border: none;
    }

    /* Highlight spot for extra 3D feel */
    .color-sphere-item::before {
        content: '';
        position: absolute;
        top: 15%;
        left: 20%;
        width: 25%;
        height: 25%;
        background: radial-gradient(circle, rgba(255, 255, 255, 0.6) 0%, rgba(255, 255, 255, 0) 80%);
        border-radius: 50%;
        z-index: 2;
    }

    /* Ground Shadow like materyaller.php */
    .color-sphere-item::after {
        content: '';
        position: absolute;
        bottom: -25px;
        left: 10%;
        width: 80%;
        height: 15px;
        background: radial-gradient(ellipse at center, rgba(0, 0, 0, 0.25) 0%, rgba(0, 0, 0, 0) 70%);
        border-radius: 50%;
        filter: blur(4px);
        z-index: -1;
        transition: all 0.7s ease;
        opacity: 0.6;
    }

    .color-sphere-item.active {
        width: 130px;
        height: 130px;
        z-index: 30;
        transform: translateX(0) translateY(15px) scale(1.1);
        filter: brightness(1.05);
    }

    .color-sphere-item.active::after {
        bottom: -35px;
        opacity: 0.4;
        transform: scale(1.2);
    }

    .color-sphere-item.prev {
        transform: translateX(-75px) translateY(-15px) scale(0.85);
        z-index: 20;
        filter: brightness(0.85);
    }

    .color-sphere-item.next {
        transform: translateX(75px) translateY(-15px) scale(0.85);
        z-index: 20;
        filter: brightness(0.85);
    }

    .color-sphere-item.prevPrev {
        transform: translateX(-130px) translateY(-35px) scale(0.7);
        z-index: 15;
        filter: brightness(0.7);
        opacity: 0.6;
    }

    .color-sphere-item.nextNext {
        transform: translateX(130px) translateY(-35px) scale(0.7);
        z-index: 15;
        filter: brightness(0.7);
        opacity: 0.6;
    }

    .color-sphere-item.hidden {
        transform: translateX(0) translateY(-50px) scale(0.5);
        opacity: 0;
        z-index: 5;
        pointer-events: none;
    }

    /* Hidden items further away to maintain curve */
    .color-sphere-item.hidden.h-prev {
        transform: translateX(-120px) translateY(-30px) scale(0.6);
        opacity: 0;
    }

    .color-sphere-item.hidden.h-next {
        transform: translateX(120px) translateY(-30px) scale(0.6);
        opacity: 0;
    }

    .color-info-display {
        text-align: center;
        margin-top: 25px;
        min-height: 55px;
    }

    .color-info-name {
        display: block;
        font-size: 1.3rem;
        font-weight: 800;
        color: #1a1a1a;
        margin-bottom: 2px;
        letter-spacing: -0.5px;
    }

    .color-info-code {
        display: block;
        font-size: 0.95rem;
        color: #e91e63;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .color-options-empty {
        color: #777;
        font-size: 0.95rem;
        line-height: 1.6;
        margin: 0;
    }

    .attachment-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .attachment-item {
        padding: 0;
    }

    .attachment-link {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        text-decoration: none;
        color: inherit;
        padding: 16px 18px;
        border-radius: 18px;
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(249, 249, 251, 0.96) 100%);
        border: 1px solid rgba(20, 20, 20, 0.06);
        box-shadow: 0 10px 26px rgba(17, 24, 39, 0.08);
        transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    }

    .attachment-link:hover {
        transform: translateY(-2px);
        box-shadow: 0 18px 34px rgba(17, 24, 39, 0.12);
        border-color: rgba(233, 30, 99, 0.18);
    }

    .attachment-visual {
        width: 52px;
        height: 52px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 52px;
        color: #fff;
        font-size: 1.25rem;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18);
    }

    .attachment-visual.file-dwg {
        background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
    }

    .attachment-visual.file-glb,
    .attachment-visual.file-360 {
        background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
    }

    .attachment-visual.file-pdf {
        background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
    }

    .attachment-visual.file-default {
        background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%);
    }

    .attachment-meta {
        min-width: 0;
        flex: 1;
    }

    .attachment-name {
        color: #1f2937;
        font-size: 1rem;
        font-weight: 700;
        line-height: 1.3;
    }

    .attachment-subtitle {
        color: #6b7280;
        font-size: 0.82rem;
        margin-top: 6px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .attachment-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 4px 8px;
        border-radius: 999px;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.5px;
        background: rgba(233, 30, 99, 0.08);
        color: #be185d;
    }

    .attachment-action {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        color: #e91e63;
        font-size: 0.82rem;
        font-weight: 700;
        white-space: nowrap;
        padding: 10px 14px;
        border-radius: 999px;
        background: rgba(233, 30, 99, 0.08);
        border: 1px solid rgba(233, 30, 99, 0.12);
        align-self: center;
    }

    .attachment-empty {
        color: #777;
        font-size: 0.95rem;
        line-height: 1.6;
        margin: 0;
    }

    .similar-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .similar-item {
        padding: 0;
    }

    .similar-link {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 12px;
        border: 1px solid rgba(20, 20, 20, 0.08);
        background: rgba(255, 255, 255, 0.95);
        border-radius: 14px;
        padding: 10px;
        text-decoration: none;
        text-align: left;
        cursor: pointer;
        transition: all 0.25s ease;
    }

    .similar-link:hover {
        border-color: rgba(233, 30, 99, 0.2);
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(17, 24, 39, 0.08);
    }

    .similar-thumb {
        width: 62px;
        height: 62px;
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid rgba(0, 0, 0, 0.06);
        flex: 0 0 62px;
    }

    .similar-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .similar-meta {
        min-width: 0;
        flex: 1;
    }

    .similar-name {
        font-size: 0.95rem;
        font-weight: 700;
        color: #1f2937;
        line-height: 1.3;
    }

    .similar-category {
        margin-top: 4px;
        color: #6b7280;
        font-size: 0.8rem;
    }

    .similar-action {
        color: #e91e63;
        font-weight: 700;
        font-size: 0.78rem;
        white-space: nowrap;
    }

    .similar-empty {
        color: #777;
        font-size: 0.95rem;
        line-height: 1.6;
        margin: 0;
    }

    .offer-form {
        display: flex;
        flex-direction: column;
        gap: 12px;
        position: relative;
        padding: 14px;
        border-radius: 18px;
        border: 1px solid rgba(233, 30, 99, 0.16);
        background: linear-gradient(165deg, rgba(255, 255, 255, 0.96) 0%, rgba(250, 241, 247, 0.9) 100%);
        box-shadow: 0 14px 36px rgba(190, 24, 93, 0.08);
        overflow: hidden;
    }

    .offer-form::before {
        content: "";
        position: absolute;
        top: -40px;
        right: -35px;
        width: 110px;
        height: 110px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(233, 30, 99, 0.16) 0%, rgba(233, 30, 99, 0) 70%);
        pointer-events: none;
    }

    .offer-head {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border-radius: 12px;
        border: 1px solid rgba(123, 31, 162, 0.15);
        background: linear-gradient(135deg, rgba(123, 31, 162, 0.1) 0%, rgba(233, 30, 99, 0.1) 100%);
    }

    .offer-head i {
        color: #be185d;
        font-size: 1rem;
    }

    .offer-head span {
        color: #7a1f6d;
        font-size: 0.82rem;
        font-weight: 700;
        letter-spacing: 0.2px;
    }

    .offer-form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .offer-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .offer-field label {
        font-size: 0.76rem;
        font-weight: 700;
        color: #8d215d;
        letter-spacing: 0.4px;
        text-transform: uppercase;
    }

    .offer-field input,
    .offer-field textarea {
        width: 100%;
        border: 1px solid rgba(123, 31, 162, 0.14);
        background: rgba(255, 255, 255, 0.82);
        color: #111827;
        border-radius: 12px;
        padding: 11px 12px;
        font-size: 0.92rem;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    }

    .offer-field input::placeholder,
    .offer-field textarea::placeholder {
        color: #9ca3af;
    }

    .offer-field input:focus,
    .offer-field textarea:focus {
        outline: none;
        border-color: rgba(233, 30, 99, 0.55);
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.14);
    }

    .offer-field--full {
        grid-column: 1 / -1;
    }

    .offer-submit {
        border: 0;
        border-radius: 12px;
        padding: 12px 14px;
        font-size: 0.9rem;
        font-weight: 700;
        color: #fff;
        background: linear-gradient(135deg, #e91e63 0%, #7b1fa2 100%);
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .offer-submit:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 24px rgba(123, 31, 162, 0.25);
    }

    .offer-note {
        margin: 0;
        color: #7b7280;
        font-size: 0.82rem;
        line-height: 1.5;
    }

    /* DXF Drawing Modal */
    .dxf-drawing-modal {
        position: fixed;
        inset: 0;
        z-index: 3100;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
        transition: opacity .3s ease, visibility .3s ease;
    }

    .dxf-drawing-modal.active {
        opacity: 1;
        visibility: visible;
    }

    .dxf-drawing-overlay {
        position: absolute;
        inset: 0;
        background: rgba(0, 0, 0, .65);
        backdrop-filter: blur(6px);
        cursor: pointer;
    }

    .dxf-drawing-container {
        position: relative;
        width: 94vw;
        height: 92vh;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 32px 80px rgba(0, 0, 0, .5);
        background: #0f1117;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .dxf-drawing-close {
        position: absolute;
        top: .75rem;
        right: .75rem;
        z-index: 10;
        background: rgba(255, 255, 255, .12);
        border: 1px solid rgba(255, 255, 255, .15);
        color: #fff;
        border-radius: 8px;
        padding: .4rem .65rem;
        font-size: 1rem;
        cursor: pointer;
        line-height: 1;
        transition: background .2s;
    }

    .dxf-drawing-close:hover {
        background: rgba(255, 255, 255, .25);
    }

    #dxf-drawing-iframe {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        border: none;
        display: block;
    }

    .dxf-drawing-no-file {
        text-align: center;
        color: #4b5563;
    }

    .dxf-drawing-no-file i {
        font-size: 3rem;
        display: block;
        margin-bottom: 1rem;
        color: #4b5563;
    }

    .dxf-drawing-no-file p {
        margin: 0 0 .5rem;
        font-size: .9rem;
        color: #6b7280;
    }

    .dxf-drawing-no-file p:last-child {
        margin-bottom: 0;
    }

    .dxf-drawing-no-file a {
        color: #6c8ef5;
        text-decoration: none;
    }

    .dxf-drawing-no-file a:hover {
        text-decoration: underline;
    }

    /* 360 Viewer Modal Styles */
    .viewer-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 3000;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
        transition: all 0.5s ease;
    }

    .viewer-modal.active {
        opacity: 1;
        visibility: visible;
    }

    .viewer-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.1);
        backdrop-filter: blur(10px);
    }

    .viewer-modal-container {
        position: relative;
        width: 90%;
        max-width: 1100px;
        height: 80vh;
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(30px);
        border-radius: 40px;
        padding: 20px;
        box-shadow: 0 50px 100px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        transform: scale(0.9) translateY(40px);
        transition: all 0.6s cubic-bezier(0.165, 0.84, 0.44, 1);
        overflow: hidden;
    }

    .viewer-modal.active .viewer-modal-container {
        transform: scale(1) translateY(0);
    }

    model-viewer {
        width: 100%;
        height: 100%;
        background-color: transparent;
    }

    .viewer-close {
        position: absolute;
        top: 30px;
        right: 30px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 10;
        border: none;
        color: #333;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .viewer-close:hover {
        background: linear-gradient(135deg, #e91e63 0%, #7b1fa2 100%);
        color: #fff;
        transform: rotate(90deg) scale(1.1);
        box-shadow: 0 15px 30px rgba(233, 30, 99, 0.3);
    }

    /* 360 Loader Styles */
    .viewer-loader {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: transparent;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 5;
        transition: opacity 0.6s ease;
    }

    .loader-circle {
        width: 80px;
        height: 80px;
        border: 4px solid rgba(233, 30, 99, 0.05);
        border-top: 4px solid #e91e63;
        border-right: 4px solid #7b1fa2;
        border-radius: 50%;
        animation: spin 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
        margin-bottom: 25px;
        position: relative;
        filter: drop-shadow(0 0 15px rgba(233, 30, 99, 0.2));
    }

    .loader-circle::after {
        content: "360°";
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 0.9rem;
        font-weight: 800;
        background: linear-gradient(135deg, #e91e63 0%, #7b1fa2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    .loader-text {
        font-size: 0.75rem;
        font-weight: 600;
        color: #666;
        letter-spacing: 2px;
        text-transform: uppercase;
    }

    model-viewer[ar-status="not-presenting"]>#poster {
        display: none;
    }

    /* model-viewer default styles to hide poster when finished */
    #poster {
        position: absolute;
        left: 0;
        right: 0;
        top: 0;
        bottom: 0;
        background-size: contain;
        background-repeat: no-repeat;
        background-position: center;
    }

    .three-sixty-logo {
        cursor: pointer;
        transition: transform 0.3s ease;
    }

    .three-sixty-logo:hover {
        /* transform: scale(1.1); */
    }

    .specs-mega-menu {
        position: absolute;
        top: 0;
        left: 100%;
        margin-left: 15px;
        width: 320px;
        background: rgba(211, 235, 251, 0.7);
        backdrop-filter: blur(20px);
        border-radius: 15px;
        padding: 10px;
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.25);
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 6px;
        opacity: 0;
        visibility: hidden;
        transform: translateX(10px);
        transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
        border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .specs-badge-container:hover .specs-mega-menu {
        opacity: 1;
        visibility: visible;
        transform: translateX(0);
    }

    .specs-badge-container.hide-menu .specs-mega-menu,
    .specs-badge-container:has(.specs-modal.active) .specs-mega-menu {
        opacity: 0 !important;
        visibility: hidden !important;
        pointer-events: none !important;
        display: none !important;
    }

    .spec-card {
        text-decoration: none !important;
        border-radius: 10px;
        padding: 8px;
        aspect-ratio: 1 / 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        position: relative;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .spec-card:hover {
        transform: scale(1.03);
        z-index: 5;
    }

    .spec-card .card-icon {
        font-size: 1.4rem;
        color: #fff;
        position: absolute;
        top: 2px;
        right: 5px;
        filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.5));
    }

    .spec-card .card-text {
        display: flex;
        flex-direction: column;
        color: white;
        position: relative;
        z-index: 2;
        margin-top: auto;
    }

    .spec-card .card-text .title {
        font-size: 0.65rem;
        font-weight: 700;
        color: white;
        line-height: 1.1;
        position: relative;
        z-index: 2;
        text-transform: uppercase;
        letter-spacing: 0.2px;
    }

    .spec-card .card-text .subtitle {
        font-size: 0.65rem;
        font-weight: 400;
        opacity: 0.9;
    }

    /*
    .spec-card::before {
        content: "";
        position: absolute;
        top: -30%;
        right: -20%;
        width: 70px;
        height: 70px;
        background: rgba(255, 255, 255, 0.08);
        border-radius: 50%;
    }
    */

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

    /* Slider Styles */
    .variant-slider-container {
        width: 100%;
        max-width: 1200px;
        position: relative;
        height: 600px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .variant-slides-wrapper {
        position: relative;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .variant-slide {
        position: absolute;
        width: 70%;
        height: auto;
        transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        opacity: 0;
        z-index: 1;
        filter: blur(10px) brightness(0.9);
        transform: scale(0.8);
        pointer-events: none;
    }

    .variant-slide.active {
        opacity: 1;
        z-index: 10;
        filter: blur(0) brightness(1);
        transform: scale(1);
        pointer-events: auto;
    }

    .variant-slide.prev {
        opacity: 0.2;
        z-index: 5;
        transform: scale(0.4) translateX(-80%);
        left: 0;
        pointer-events: auto;
        cursor: pointer;
    }

    .variant-slide.next {
        opacity: 0.2;
        z-index: 5;
        transform: scale(0.4) translateX(80%);
        right: 0;
        pointer-events: auto;
        cursor: pointer;
    }

    .variant-slide img {
        width: 100%;
        height: auto;
        object-fit: contain;
    }

    /* Hotspots */
    .hotspot {
        position: absolute;
        width: 20px;
        height: 20px;
        background: #e91e63;
        border: 2px solid #fff;
        border-radius: 50%;
        cursor: pointer;
        box-shadow: 0 0 15px rgba(233, 30, 99, 0.5);
        transition: all 0.3s ease;
        z-index: 15;
        opacity: 0;
        visibility: hidden;
        transform: scale(0.9);
        pointer-events: none;
    }

    .variant-slide.active:hover .hotspot {
        opacity: 1;
        visibility: visible;
        transform: scale(1);
        pointer-events: auto;
    }

    .hotspot:hover {
        transform: scale(1.3);
        background: #7b1fa2;
    }

    .hotspot::after {
        content: "";
        position: absolute;
        top: -4px;
        left: -4px;
        right: -4px;
        bottom: -4px;
        border: 1px solid rgba(233, 30, 99, 0.3);
        border-radius: 50%;
        animation: pulse 2s infinite;
    }

    @media (hover: none),
    (pointer: coarse) {
        .hotspot {
            opacity: 1;
            visibility: visible;
            transform: scale(1);
            pointer-events: auto;
        }
    }

    @keyframes pulse {
        0% {
            transform: scale(1);
            opacity: 1;
        }

        100% {
            transform: scale(1.5);
            opacity: 0;
        }
    }

    /* Detail Card */
    .hotspot-card {
        position: absolute;
        top: 30px;
        right: 30px;
        width: 320px;
        background: #fff;
        border: 1px solid rgba(233, 30, 99, 0.3);
        border-radius: 20px;
        padding: 20px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.5s cubic-bezier(0.165, 0.84, 0.44, 1);
        pointer-events: none;
        z-index: 40;
    }

    .hotspot-card.active {
        opacity: 1;
        transform: translateY(0);
        pointer-events: auto;
    }

    .hotspot-card .card-thumb {
        width: 100%;
        height: 180px;
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 15px;
        border: 1px solid #eee;
    }

    .hotspot-card .card-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .hotspot-card .card-title {
        font-size: 0.95rem;
        color: #333;
        line-height: 1.4;
        margin-bottom: 5px;
    }

    .hotspot-card .card-desc {
        font-size: 0.8rem;
        color: #777;
        line-height: 1.5;
    }

    /* UI Elements */
    .slider-controls {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        padding: 0 40px;
        z-index: 30;
        pointer-events: none;
    }

    .variant-info {
        pointer-events: auto;
    }

    .variant-info .info-top {
        font-size: 0.8rem;
        color: #999;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .variant-info .info-bottom {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .variant-info .info-bottom h2 {
        font-size: 2.5rem;
        font-weight: 700;
        color: #e91e63;
        margin: 0;
    }

    .variant-info .nav-arrows {
        display: flex;
        gap: 10px;
    }

    .variant-arrow {
        color: #e91e63;
        opacity: 0.3;
        font-size: 1.5rem;
        cursor: pointer;
        transition: opacity 0.3s;
    }

    .variant-arrow.active {
        opacity: 1;
    }

    .three-sixty-logo {
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 110px;
        pointer-events: auto;
    }

    .three-sixty-logo img {
        width: 100%;
        transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
    }

    .three-sixty-logo:hover img {
        transform: scale(1.1);
    }

    .main-slider-arrow {
        position: absolute;
        top: 75%;
        width: 80px;
        cursor: pointer;
        z-index: 35;
        transition: all 0.3s;
        opacity: 1;
        pointer-events: auto;
    }

    .main-slider-arrow:hover {
        opacity: 1;
        transform: scale(1.1);
    }

    .main-slider-arrow.prev-btn {
        left: 3%;
    }

    .main-slider-arrow.next-btn {
        right: 3%;
    }

    .main-slider-arrow img {
        width: 100%;
    }

    /* Comprehensive Mobile Responsiveness */
    @media (max-width: 991px) {
        .materials-section {
            padding: 10px;
            min-height: auto;
            height: 90vh;
            /* Moved up by reducing height */
            overflow: hidden;
            align-items: flex-start;
            padding-top: 80px;
        }

        .sidebar-logo {
            display: none !important;
        }

        .sidebar-title {
            margin: 0 !important;
        }

        .specs-badge-container {
            top: 15px;
            left: 15px;
            z-index: 9999;
        }

        .specs-badge,
        .active-spec-slot .spec-card {
            width: 65px;
            height: 65px;
            padding: 10px;
        }

        .specs-badge .badge-top {
            font-size: 0.45rem;
        }

        .specs-badge .badge-bottom {
            font-size: 0.8rem;
        }

        .specs-mega-menu {
            left: 10px;
            top: 85px;
            width: 280px;
            margin-left: 0;
            transform: translateY(10px);
            z-index: 10000;
        }

        .specs-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            margin-left: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 5000;
        }

        .specs-modal-container {
            width: 92% !important;
            max-height: 85vh;
            flex-direction: column;
            padding: 50px 15px 20px 15px;
            /* Increased top padding for mobile */
            gap: 15px;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.98);
            position: relative;
            z-index: 5001;
        }

        .specs-modal-close {
            top: 12px !important;
            right: 12px !important;
            width: 36px !important;
            height: 36px !important;
            font-size: 1rem !important;
        }

        .specs-modal-overlay {
            display: block !important;
            z-index: 5000;
        }

        .modal-image-col {
            width: 100%;
        }

        .modal-image-col img {
            max-height: 30vh;
        }

        .modal-info-col {
            width: 100%;
        }

        .modal-info-col h2 {
            font-size: 1.4rem;
            margin-bottom: 5px;
        }

        .dim-item {
            padding: 12px 0;
            font-size: 0.85rem;
        }

        .dim-list {
            max-height: 260px;
        }

        .variant-slider-container {
            height: 50vh;
            margin-top: -10px;
        }

        .variant-slide {
            width: 95%;
        }

        .main-slider-arrow {
            width: 40px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(5px);
            border-radius: 50%;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }

        .main-slider-arrow.prev-btn {
            left: 10px;
        }

        .main-slider-arrow.next-btn {
            right: 10px;
        }

        .main-slider-arrow:active {
            transform: translateY(-50%) scale(0.9);
        }

        .slider-controls {
            bottom: 30px;
            padding: 0 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            pointer-events: auto;
        }

        .variant-info {
            max-width: 60%;
        }

        .variant-info .info-bottom h2 {
            font-size: 1.4rem;
        }

        .three-sixty-logo {
            position: relative;
            bottom: auto;
            left: auto;
            transform: none;
            width: 70px;
            margin-left: auto;
        }

        .viewer-modal {
            z-index: 30000;
        }

        .viewer-modal-container {
            width: 95%;
            height: 75vh;
            border-radius: 25px;
            padding: 10px;
        }

        .offer-form-grid {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 576px) {
        .specs-mega-menu {
            width: calc(100vw - 20px);
        }

        .hotspot-card {
            position: fixed;
            top: 82%;
            left: 50%;
            transform: translate(-50%, -50%) translateY(20px);
            width: 92%;
            max-width: 400px;
            max-height: 90px;
            background: rgba(255, 255, 255, 0.98);
            padding: 10px;
            display: flex;
            gap: 12px;
            align-items: center;
            border-radius: 12px;
            box-shadow: 0 -10px 30px rgba(0, 0, 0, 0.1);
            min-height: 60px;
            overflow: hidden;
            opacity: 0;
            transition: all 0.5s cubic-bezier(0.165, 0.84, 0.44, 1);
            pointer-events: none;
            z-index: 2000;
        }

        .hotspot-card .card-thumb {
            width: 60px;
            height: 60px;
            margin-bottom: 0;
            border-radius: 8px;
        }

        .hotspot-card .card-title {
            font-size: 0.8rem;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .hotspot-card .card-desc {
            font-size: 0.7rem;
            line-height: 1.3;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .hotspot-card .card-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .hotspot-card.active {
            transform: translate(-50%, -50%) translateY(0);
            opacity: 1;
            pointer-events: auto;
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
                <span
                    id="sidebar-title-primary"><?php echo htmlspecialchars($activeProductName !== '' ? $activeProductName : 'Ürün', ENT_QUOTES, 'UTF-8'); ?></span><br>
                <span id="sidebar-title-secondary"><?= statik('product_detail_sidebar_subtitle') ?></span>
            </h1>
        </div>
        <div class="sidebar-footer" id="sidebar-footer-text">
            <?php echo htmlspecialchars($activeCategoryName !== '' ? $activeCategoryName : statik('product_detail_sidebar_footer_default'), ENT_QUOTES, 'UTF-8'); ?>
        </div>
    </div>

    <!-- Sağ İçerik -->
    <div class="main-content">

        <!-- Üst Menü -->
        <?php include_once 'inc/navbar.php'; ?>

        <section class="materials-section">
            <div class="specs-badge-container">
                <div class="specs-badge"
                    style="background-image: url('assets/bg/overlay.png'); background-size: cover; background-position: center;">
                    <div class="badge-top"><?= statik('product_detail_badge_top') ?></div>
                    <div class="badge-bottom"><?= statik('product_detail_badge_bottom') ?></div>
                </div>

                <div class="active-spec-slot" id="active-spec-slot">
                    <!-- Cloned card will appear here -->
                </div>

                <div class="specs-mega-menu">
                    <a href="javascript:void()" class="spec-card"
                        style="background-image: url('assets/bg/1.webp'); background-size: cover; background-position: center;"
                        data-spec-type="technical">
                        <div class="card-icon"><img src="assets/bg/Icons/icon-1.webp" alt=""
                                style="width: 30px; height: auto;"></div>
                        <div class="card-text"><span class="title"><?= statik('product_detail_spec_technical') ?></span><span class="subtitle"><?= statik('product_detail_badge_bottom') ?></span>
                        </div>
                    </a>
                    <a href="javascript:void()" class="spec-card"
                        style="background-image: url('assets/bg/2.webp'); background-size: cover; background-position: center;"
                        data-spec-type="general">
                        <div class="card-icon"><img src="assets/bg/Icons/icon-2.webp" alt=""
                                style="width: 30px; height: auto;"></div>
                        <div class="card-text"><span class="title"><?= statik('product_detail_spec_general') ?></span><span class="subtitle"><?= statik('product_detail_badge_bottom') ?></span></div>
                    </a>
                    <a href="javascript:void()" class="spec-card"
                        style="background-image: url('assets/bg/3.webp'); background-size: cover; background-position: center;"
                        data-spec-type="drawing">
                        <div class="card-icon"><img src="assets/bg/Icons/icon-3.webp" alt=""
                                style="width: 30px; height: auto;"></div>
                        <div class="card-text"><span class="title"><?= statik('product_detail_spec_technical') ?></span><span class="subtitle"><?= statik('product_detail_spec_drawing_sub') ?></span>
                        </div>
                    </a>
                    <a href="javascript:void()" class="spec-card"
                        style="background-image: url('assets/bg/4.webp'); background-size: cover; background-position: center;">
                        <div class="card-icon"><img src="assets/bg/Icons/icon-4.webp" alt=""
                                style="width: 30px; height: auto;"></div>
                        <div class="card-text"><span class="title"><?= statik('product_detail_spec_mounting_title') ?></span><span class="subtitle"><?= statik('product_detail_sidebar_subtitle') ?></span>
                        </div>
                    </a>
                    <a href="javascript:void()" class="spec-card"
                        style="background-image: url('assets/bg/5.webp'); background-size: cover; background-position: center;"
                        data-spec-type="certificate">
                        <div class="card-icon"><img src="assets/bg/Icons/icon-5.webp" alt=""
                                style="width: 30px; height: auto;"></div>
                        <div class="card-text"><span class="title"><?= statik('product_detail_spec_certificate_title') ?></span><span class="subtitle"><?= statik('product_detail_spec_certificate_sub') ?></span>
                        </div>
                    </a>
                    <a href="javascript:void()" class="spec-card"
                        style="background-image: url('assets/bg/6.webp'); background-size: cover; background-position: center;"
                        data-spec-type="offer">
                        <div class="card-icon"><img src="assets/bg/Icons/icon-6.webp" alt=""
                                style="width: 30px; height: auto;"></div>
                        <div class="card-text"><span class="title"><?= statik('product_detail_spec_offer_title') ?></span><span class="subtitle"><?= statik('product_detail_spec_offer_sub') ?></span>
                        </div>
                    </a>
                    <a href="javascript:void()" class="spec-card"
                        style="background-image: url('assets/bg/7.webp'); background-size: cover; background-position: center;"
                        data-spec-type="attachments">
                        <div class="card-icon"><img src="assets/bg/Icons/icon-7.webp" alt=""
                                style="width: 30px; height: auto;"></div>
                        <div class="card-text"><span class="title"><?= statik('product_detail_spec_attachments_title') ?></span><span class="subtitle"><?= statik('product_detail_spec_attachments_sub') ?></span>
                        </div>
                    </a>
                    <a href="javascript:void()" class="spec-card"
                        style="background-image: url('assets/bg/8.webp'); background-size: cover; background-position: center;"
                        data-spec-type="colors">
                        <div class="card-icon"><img src="assets/bg/Icons/icon-8.webp" alt=""
                                style="width: 30px; height: auto;"></div>
                        <div class="card-text"><span class="title"><?= statik('product_detail_spec_colors_title') ?></span><span class="subtitle"><?= statik('product_detail_spec_colors_sub') ?></span>
                        </div>
                    </a>
                    <a href="javascript:void()" class="spec-card"
                        style="background-image: url('assets/bg/9.webp'); background-size: cover; background-position: center;"
                        data-spec-type="similars">
                        <div class="card-icon"><img src="assets/bg/Icons/icon-9.webp" alt=""
                                style="width: 30px; height: auto;"></div>
                        <div class="card-text"><span class="title"><?= statik('product_detail_spec_similars_title') ?></span><span class="subtitle"><?= statik('product_detail_spec_similars_sub') ?></span>
                        </div>
                    </a>
                </div>

                <!-- Technical Details Window -->
                <div class="specs-modal" id="specs-modal">
                    <div class="specs-modal-overlay" onclick="closeSpecsModal()"></div>
                    <div class="specs-modal-container">
                        <button class="specs-modal-close" onclick="closeSpecsModal()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                        <div class="modal-image-col">
                            <div class="cert-overlay-icon" id="cert-overlay-icon"><i class="bi bi-patch-check-fill"></i>
                            </div>
                            <img src="<?php echo htmlspecialchars($activeProductImage, ENT_QUOTES, 'UTF-8'); ?>"
                                alt="Teknik Çizim" id="modal-spec-image">
                        </div>
                        <div class="modal-info-col">
                            <h2 id="modal-spec-title"><?= statik('product_detail_modal_dimensions') ?></h2>
                            <ul class="dim-list" id="spec-dim-list">
                                <li class="dim-item"><?= statik('product_detail_dim_length') ?> <span class="dim-value" id="dim-length">493 cm</span></li>
                                <li class="dim-item"><?= statik('product_detail_dim_width') ?> <span class="dim-value" id="dim-width">200 cm</span></li>
                                <li class="dim-item"><?= statik('product_detail_dim_height') ?> <span class="dim-value" id="dim-height">304 cm</span>
                                </li>
                                <li class="dim-item"><?= statik('product_detail_dim_fall_l') ?> <span class="dim-value" id="dim-fall-l">736
                                        cm</span>
                                </li>
                                <li class="dim-item"><?= statik('product_detail_dim_fall_w') ?> <span class="dim-value"
                                        id="dim-fall-w">486
                                        cm</span></li>
                            </ul>
                            <div id="color-options-panel" style="display:none;"></div>
                            <div id="attachment-options-panel" style="display:none;"></div>
                            <div id="offer-options-panel" style="display:none;"></div>
                            <div id="similar-options-panel" style="display:none;"></div>
                            <div id="certificate-options-panel" style="display:none;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detail Card (Global for slider) -->
            <div class="hotspot-card" id="hotspot-card">
                <div class="card-thumb">
                    <img src="https://images.unsplash.com/photo-1518709268805-4e9042af9f23?q=80&w=500" id="card-image"
                        alt="Detay">
                </div>
                <div class="card-content">
                    <h3 class="card-title" id="card-title">Metal parçalar ST 37 çelikten üretilir ve 3 aşamalı boya
                        işleminden geçer.</h3>
                    <p class="card-desc" id="card-desc">Kumlama, solvent içermeyen çinko astar ve elektrostatik toz
                        boya uygulamaları zorlu hava koşullarına karşı dayanıklıdır.</p>
                </div>
            </div>

            <div class="variant-slider-container">
                <!-- Navigation Arrows -->
                <div class="main-slider-arrow prev-btn" id="prev-btn">
                    <img src="assets/img/left-arrow.png" alt="Önceki"
                        onerror="this.src='https://cdn-icons-png.flaticon.com/512/271/271220.png'">
                </div>
                <div class="main-slider-arrow next-btn" id="next-btn">
                    <img src="assets/img/right-arrow.png" alt="Sonraki"
                        onerror="this.src='https://cdn-icons-png.flaticon.com/512/271/271228.png'">
                </div>

                <div class="variant-slides-wrapper" id="slides-wrapper">
                    <?php $slideCount = count($sliderProducts); ?>
                    <?php foreach ($sliderProducts as $index => $sliderProduct): ?>
                        <?php
                        $slideClass = 'hidden';
                        if ($index === 0) {
                            $slideClass = 'active';
                        } elseif ($index === 1) {
                            $slideClass = 'next';
                        } elseif ($slideCount > 2 && $index === $slideCount - 1) {
                            $slideClass = 'prev';
                        }
                        ?>
                        <div class="variant-slide <?php echo $slideClass; ?>"
                            data-name="<?php echo htmlspecialchars($sliderProduct['name'], ENT_QUOTES, 'UTF-8'); ?>"
                            data-sef-url="<?php echo htmlspecialchars($sliderProduct['sef_url'], ENT_QUOTES, 'UTF-8'); ?>">
                            <img src="<?php echo htmlspecialchars($sliderProduct['image'], ENT_QUOTES, 'UTF-8'); ?>"
                                alt="<?php echo htmlspecialchars($sliderProduct['name'], ENT_QUOTES, 'UTF-8'); ?>">

                            <?php foreach (($sliderProduct['hotspots'] ?? []) as $hotspot): ?>
                                <div class="hotspot"
                                    style="top: <?php echo htmlspecialchars((string) $hotspot['y'], ENT_QUOTES, 'UTF-8'); ?>%; left: <?php echo htmlspecialchars((string) $hotspot['x'], ENT_QUOTES, 'UTF-8'); ?>%;"
                                    data-title="<?php echo htmlspecialchars($hotspot['title'] ?? 'Detay', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-desc="<?php echo htmlspecialchars($hotspot['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    data-img="<?php echo htmlspecialchars($hotspot['image'] ?? $sliderProduct['image'], ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="slider-controls">
                    <div class="variant-info">
                        <div class="info-top">Varyantlar</div>
                        <div class="info-bottom">
                            <h2 id="variant-name">
                                <?php echo htmlspecialchars($sliderProducts[0]['name'] ?? $activeProductName, ENT_QUOTES, 'UTF-8'); ?>
                            </h2>
                            <div class="nav-arrows">
                                <i class="bi bi-caret-left-fill variant-arrow active"></i>
                                <i class="bi bi-caret-right-fill variant-arrow"></i>
                                <i class="bi bi-caret-right-fill variant-arrow" style="opacity: 0.1;"></i>
                            </div>
                        </div>
                    </div>

                    <div class="three-sixty-logo" <?php echo $initialHasModel ? '' : ' style="display:none;"'; ?>>
                        <img src="assets/img/360.png" alt="360 Görünüm"
                            onerror="this.src='https://cdn-icons-png.flaticon.com/512/3597/3597023.png'">
                    </div>
                </div>
            </div>

            <!-- DXF / DWG Drawing Modal -->
            <div class="dxf-drawing-modal" id="dxf-drawing-modal">
                <div class="dxf-drawing-overlay" id="dxf-drawing-overlay"></div>
                <div class="dxf-drawing-container">
                    <button class="dxf-drawing-close" id="dxf-drawing-close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <div class="dxf-drawing-no-file" id="dxf-drawing-no-file" style="display:none">
                        <i class="bi bi-pencil-ruler"></i>
                        <p>Bu ürün için teknik çizim dosyası henüz yüklenmemiştir.</p>
                        <p>Teknik çizime ulaşmak için lütfen <a href="/iletisim.php">iletişime geçiniz</a>.</p>
                    </div>
                    <iframe id="dxf-drawing-iframe" src="" frameborder="0" allowfullscreen
                        style="display:none"></iframe>
                </div>
            </div>

            <!-- 3D Viewer Modal -->
            <div class="viewer-modal" id="viewer-modal">
                <div class="viewer-modal-overlay" onclick="closeViewerModal()"></div>
                <div class="viewer-modal-container">
                    <button class="viewer-close" onclick="closeViewerModal()">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <!-- Google Model Viewer component -->
                    <model-viewer id="product-3d"
                        src="<?php echo htmlspecialchars($activeProductModel, ENT_QUOTES, 'UTF-8'); ?>"
                        alt="3D Ürün Modeli" auto-rotate camera-controls ar shadow-intensity="1" loading="eager"
                        reveal="auto">

                        <div slot="poster" id="poster">
                            <div class="viewer-loader">
                                <div class="loader-circle"></div>
                                <div class="loader-text">Deneyim Yükleniyor</div>
                            </div>
                        </div>
                    </model-viewer>
                </div>
            </div>
        </section>

    </div>

    <!-- Bootstrap JS -->
    <script src="assets/vendor/bootstrap.bundle.min.js"></script>
    <!-- Swiper JS -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const translations = {
                no_colors: "<?= statik('product_detail_no_colors') ?>",
                no_attachments: "<?= statik('product_detail_no_attachments') ?>",
                no_similars: "<?= statik('product_detail_no_similars') ?>",
                view_file: "<?= statik('product_detail_view_file') ?>",
                download_file: "<?= statik('product_detail_btn_download') ?>",
                technical_title: "<?= statik('product_detail_spec_technical_title') ?>",
                general_title: "<?= statik('product_detail_spec_general_title') ?>",
                certificate_modal: "<?= statik('product_detail_spec_certificate_modal') ?>",
                offer_title: "<?= statik('product_detail_spec_offer') ?>",
                attachments_title: "<?= statik('product_detail_spec_attachments') ?>",
                colors_title: "<?= statik('product_detail_spec_colors') ?>",
                similars_title: "<?= statik('product_detail_spec_similars') ?>",
                dimensions_title: "<?= statik('product_detail_modal_dimensions') ?>",
                loading: "<?= statik('product_detail_loading') ?>",
                offer_submit: "<?= statik('product_detail_offer_submit') ?>",
                offer_note: "<?= statik('product_detail_offer_note') ?>",
                no_certificate: "<?= statik('product_detail_no_certificate') ?>",
                error_certificate: "<?= statik('product_detail_error_certificate') ?>",
                sidebar_footer_default: "<?= statik('product_detail_sidebar_footer_default') ?>",
                offer_head: "<?= statik('product_detail_offer_form_title') ?>"
            };

            const sliderProducts = <?php echo $sliderProductsJson; ?>;
            const slides = Array.from(document.querySelectorAll('.variant-slide'));
            const variantName = document.getElementById('variant-name');
            const prevBtn = document.getElementById('prev-btn');
            const nextBtn = document.getElementById('next-btn');
            const card = document.getElementById('hotspot-card');
            const cardImg = document.getElementById('card-image');
            const cardTitle = document.getElementById('card-title');
            const cardDesc = document.getElementById('card-desc');
            const sidebarTitlePrimary = document.getElementById('sidebar-title-primary');
            const sidebarFooterText = document.getElementById('sidebar-footer-text');
            const modalSpecImage = document.getElementById('modal-spec-image');
            const modalSpecTitle = document.getElementById('modal-spec-title');
            const specDimList = document.getElementById('spec-dim-list');
            const defaultDimListMarkup = specDimList ? specDimList.innerHTML : '';
            const colorOptionsPanel = document.getElementById('color-options-panel');
            const attachmentOptionsPanel = document.getElementById('attachment-options-panel');
            const offerOptionsPanel = document.getElementById('offer-options-panel');
            const similarOptionsPanel = document.getElementById('similar-options-panel');
            const certificateOptionsPanel = document.getElementById('certificate-options-panel');
            const certOverlayIcon = document.getElementById('cert-overlay-icon');
            const productModel = document.getElementById('product-3d');
            const viewerModal = document.getElementById('viewer-modal');
            const specsModal = document.getElementById('specs-modal');
            const dxfDrawingModal = document.getElementById('dxf-drawing-modal');
            const dxfDrawingIframe = document.getElementById('dxf-drawing-iframe');
            const dxfDrawingNoFile = document.getElementById('dxf-drawing-no-file');
            const specsContainer = document.querySelector('.specs-badge-container');
            const specCards = document.querySelectorAll('.specs-mega-menu .spec-card');
            const activeSlot = document.getElementById('active-spec-slot');
            const threeSixtyBtn = document.querySelector('.three-sixty-logo');
            const titleSuffix = document.title.includes('|') ? ` | ${document.title.split('|').slice(1).join('|').trim()}` : '';

            let currentIndex = 0;

            const syncActiveProduct = () => {
                const activeProduct = sliderProducts[currentIndex] || null;
                if (!activeProduct) {
                    return;
                }

                variantName.textContent = activeProduct.name || '';

                if (sidebarTitlePrimary) {
                    sidebarTitlePrimary.textContent = activeProduct.name || 'Ürün';
                }

                if (sidebarFooterText) {
                    sidebarFooterText.textContent = activeProduct.category || translations.sidebar_footer_default;
                }

                if (modalSpecImage && activeProduct.image) {
                    modalSpecImage.src = activeProduct.image;
                }

                const hasModel = typeof activeProduct.model === 'string' && activeProduct.model.trim() !== '';

                if (threeSixtyBtn) {
                    threeSixtyBtn.style.display = hasModel ? '' : 'none';
                }

                if (productModel) {
                    if (hasModel) {
                        productModel.setAttribute('src', activeProduct.model);
                    } else {
                        productModel.removeAttribute('src');
                    }
                }

                if (!hasModel && viewerModal) {
                    viewerModal.classList.remove('active');
                }

                if (activeProduct.name) {
                    document.title = `${activeProduct.name}${titleSuffix}`;
                }

                if (activeProduct.sef_url) {
                    const newUrl = `/urun/${encodeURIComponent(activeProduct.sef_url)}`;
                    if (window.location.pathname !== newUrl) {
                        window.history.replaceState({ sefUrl: activeProduct.sef_url }, '', newUrl);
                    }
                }
            };

            const updateSlider = () => {
                slides.forEach((slide, index) => {
                    slide.className = 'variant-slide';
                    const total = slides.length;
                    const prev = (currentIndex - 1 + total) % total;
                    const next = (currentIndex + 1) % total;

                    if (index === currentIndex) slide.classList.add('active');
                    else if (index === prev) slide.classList.add('prev');
                    else if (index === next) slide.classList.add('next');
                    else slide.classList.add('hidden');
                });

                card.classList.remove('active');
                syncActiveProduct();
            };

            const escapeHtml = (value) => {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            };

            const clearAllModalPanels = () => {
                const panels = [
                    { panel: colorOptionsPanel },
                    { panel: attachmentOptionsPanel },
                    { panel: similarOptionsPanel },
                    { panel: offerOptionsPanel },
                    { panel: certificateOptionsPanel }
                ];

                panels.forEach(item => {
                    if (item.panel) {
                        item.panel.style.display = 'none';
                        item.panel.innerHTML = '';
                    }
                });

                if (specDimList) {
                    specDimList.style.display = 'none';
                }

                if (certOverlayIcon) {
                    certOverlayIcon.style.display = 'none';
                }
            };

            let currentColorIndex = 0;

            const updateColorSlider = () => {
                const spheres = Array.from(colorOptionsPanel.querySelectorAll('.color-sphere-item'));
                const nameDisplay = colorOptionsPanel.querySelector('.color-info-name');
                const codeDisplay = colorOptionsPanel.querySelector('.color-info-code');

                if (!spheres.length) {
                    return;
                }

                const total = spheres.length;
                const prev = (currentColorIndex - 1 + total) % total;
                const next = (currentColorIndex + 1) % total;
                const prevPrev = total > 3 ? (currentColorIndex - 2 + total) % total : -1;
                const nextNext = total > 4 ? (currentColorIndex + 2) % total : -1;

                spheres.forEach((sphere, index) => {
                    sphere.className = 'color-sphere-item';
                    if (index === currentColorIndex) {
                        sphere.classList.add('active');
                        if (nameDisplay) {
                            nameDisplay.textContent = sphere.getAttribute('data-name');
                        }
                        if (codeDisplay) {
                            codeDisplay.textContent = sphere.getAttribute('data-code');
                        }
                    } else if (index === prev) {
                        sphere.classList.add('prev');
                    } else if (index === next) {
                        sphere.classList.add('next');
                    } else if (index === prevPrev && prevPrev !== -1) {
                        sphere.classList.add('prevPrev');
                    } else if (index === nextNext && nextNext !== -1) {
                        sphere.classList.add('nextNext');
                    } else {
                        sphere.classList.add('hidden');
                    }
                });
            };

            const renderColorOptions = () => {
                const activeProduct = sliderProducts[currentIndex] || null;
                const colors = Array.isArray(activeProduct?.colors) ? activeProduct.colors : [];

                clearAllModalPanels();
                currentColorIndex = 0;

                if (modalSpecTitle) {
                    modalSpecTitle.textContent = translations.colors_title;
                }

                if (!colorOptionsPanel) {
                    return;
                }

                colorOptionsPanel.style.display = 'block';

                if (!colors.length) {
                    colorOptionsPanel.innerHTML = `<p class="color-options-empty">${translations.no_colors}</p>`;
                    return;
                }

                colorOptionsPanel.innerHTML = `
                    <div class="color-sphere-container">
                        <div class="color-sphere-wrapper">
                            ${colors.map((color, index) => `
                                <div class="color-sphere-item"
                                     style="background-color: ${escapeHtml(color.code || '#ccc')}"
                                     data-name="${escapeHtml(color.name || 'Renk')}"
                                     data-code="${escapeHtml(color.code || '')}"
                                     data-index="${index}">
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    <div class="color-info-display">
                        <span class="color-info-name"></span>
                        <span class="color-info-code"></span>
                    </div>
                `;

                const spheres = colorOptionsPanel.querySelectorAll('.color-sphere-item');
                spheres.forEach(sphere => {
                    sphere.addEventListener('click', () => {
                        currentColorIndex = parseInt(sphere.getAttribute('data-index'));
                        updateColorSlider();
                    });
                });

                updateColorSlider();
            };

            const renderAttachmentOptions = () => {
                const activeProduct = sliderProducts[currentIndex] || null;
                const attachments = Array.isArray(activeProduct?.attachments) ? activeProduct.attachments : [];

                clearAllModalPanels();

                if (modalSpecTitle) {
                    modalSpecTitle.textContent = translations.attachments_title;
                }

                if (!attachmentOptionsPanel) {
                    return;
                }

                attachmentOptionsPanel.style.display = 'block';

                if (!attachments.length) {
                    attachmentOptionsPanel.innerHTML = `<p class="attachment-empty">${translations.no_attachments}</p>`;
                    return;
                }

                attachmentOptionsPanel.innerHTML = `
                    <ul class="attachment-list">
                        ${attachments.map((attachment) => {
                    const extension = String(attachment.extension || '').toLowerCase();
                    const category = String(attachment.category || '').toLowerCase();
                    let iconClass = 'bi-file-earmark-arrow-down';
                    let visualClass = 'file-default';

                    if (extension === 'dwg') {
                        iconClass = 'bi-badge-tm';
                        visualClass = 'file-dwg';
                    } else if (extension === 'glb' || category === '360') {
                        iconClass = 'bi-badge-3d';
                        visualClass = extension === 'glb' || category === '360' ? 'file-glb' : 'file-360';
                    } else if (extension === 'pdf') {
                        iconClass = 'bi-file-earmark-pdf';
                        visualClass = 'file-pdf';
                    }

                    const isDwg = extension === 'dwg';
                    const linkUrl = isDwg ? `dwg-viewer/index.php?file=${encodeURIComponent(attachment.url || '')}` : escapeHtml(attachment.url || '#');
                    const linkTarget = isDwg ? 'target="_blank"' : '';
                    const linkDownload = isDwg ? '' : 'download';
                    const actionIcon = isDwg ? 'bi-eye-fill' : 'bi-download';
                    const actionText = isDwg ? translations.view_file : translations.download_file;

                    return `
                            <li class="attachment-item">
                                <a class="attachment-link" href="${linkUrl}" ${linkTarget} ${linkDownload}>
                                    <div class="attachment-visual ${escapeHtml(visualClass)}">
                                        <i class="bi ${escapeHtml(iconClass)}"></i>
                                    </div>
                                    <div class="attachment-meta">
                                        <div class="attachment-name">${escapeHtml(attachment.name || 'Dosya')}</div>
                                        <div class="attachment-subtitle">
                                            <span class="attachment-badge">${escapeHtml(attachment.category || 'Dosya')}</span>
                                            <span>${escapeHtml(attachment.extension ? attachment.extension : 'DOSYA')}</span>
                                        </div>
                                    </div>
                                    <span class="attachment-action"><i class="bi ${actionIcon}"></i> ${actionText}</span>
                                </a>
                            </li>
                        `;
                }).join('')}
                    </ul>
                `;
            };

            const renderSimilarOptions = () => {
                const activeProduct = sliderProducts[currentIndex] || null;
                const similars = Array.isArray(activeProduct?.similars) ? activeProduct.similars : [];

                clearAllModalPanels();

                if (modalSpecTitle) {
                    modalSpecTitle.textContent = translations.similars_title;
                }

                if (!similarOptionsPanel) {
                    return;
                }

                similarOptionsPanel.style.display = 'block';

                if (!similars.length) {
                    similarOptionsPanel.innerHTML = `<p class="similar-empty">${translations.no_similars}</p>`;
                    return;
                }

                similarOptionsPanel.innerHTML = `
                    <ul class="similar-list">
                        ${similars.map((item) => `
                            <li class="similar-item">
                                <button class="similar-link" type="button" data-similar-slug="${escapeHtml(item.sef_url || '')}">
                                    <span class="similar-thumb"><img src="${escapeHtml(item.image || '')}" alt="${escapeHtml(item.name || 'Ürün')}"></span>
                                    <span class="similar-meta">
                                        <span class="similar-name">${escapeHtml(item.name || 'Ürün')}</span>
                                        <span class="similar-category">${escapeHtml(item.category || '')}</span>
                                    </span>
                                    <span class="similar-action">${translations.view_file}</span>
                                </button>
                            </li>
                        `).join('')}
                    </ul>
                `;
            };

            const renderOfferForm = () => {
                clearAllModalPanels();

                if (modalSpecTitle) {
                    modalSpecTitle.textContent = translations.offer_title;
                }

                if (!offerOptionsPanel) {
                    return;
                }

                offerOptionsPanel.style.display = 'block';
                offerOptionsPanel.innerHTML = `
                    <form class="offer-form" id="offer-form-panel" novalidate>
                        <div class="offer-head"><i class="bi bi-stars"></i><span>${translations.offer_head}</span></div>
                        <div class="offer-form-grid">
                            <div class="offer-field">
                                <label for="offer-name">Ad</label>
                                <input id="offer-name" name="ad" type="text" autocomplete="given-name" placeholder="Adınız" required>
                            </div>
                            <div class="offer-field">
                                <label for="offer-surname">Soyad</label>
                                <input id="offer-surname" name="soyad" type="text" autocomplete="family-name" placeholder="Soyadınız" required>
                            </div>
                            <div class="offer-field offer-field--full">
                                <label for="offer-company">Firma</label>
                                <input id="offer-company" name="firma" type="text" autocomplete="organization" placeholder="Firma adı">
                            </div>
                            <div class="offer-field">
                                <label for="offer-email">Mail</label>
                                <input id="offer-email" name="mail" type="email" autocomplete="email" placeholder="ornek@mail.com" required>
                            </div>
                            <div class="offer-field">
                                <label for="offer-phone">Telefon</label>
                                <input id="offer-phone" name="telefon" type="tel" autocomplete="tel" placeholder="05xx xxx xx xx" required>
                            </div>
                            <div class="offer-field offer-field--full">
                                <label for="offer-message">Mesaj</label>
                                <textarea id="offer-message" name="mesaj" rows="4" placeholder="Talebinizi kısaca yazın" required></textarea>
                            </div>
                        </div>
                        <button type="submit" class="offer-submit"><i class="bi bi-send-fill"></i> ${translations.offer_submit}</button>
                        <p class="offer-note">${translations.offer_note}</p>
                    </form>
                `;
            };

            const renderCertificateOptions = async () => {
                const activeProduct = sliderProducts[currentIndex] || null;
                const productSef = activeProduct?.sef_url || '';
                const categorySef = activeProduct?.kat_sef_url || '';
                const currentLang = '<?php echo $lang; ?>';

                clearAllModalPanels();

                if (modalSpecTitle) {
                    modalSpecTitle.textContent = translations.certificate_modal;
                }

                if (certOverlayIcon) {
                    certOverlayIcon.style.display = 'flex';
                }

                if (!certificateOptionsPanel) {
                    return;
                }

                certificateOptionsPanel.style.display = 'block';
                certificateOptionsPanel.innerHTML = `<div class="cert-loader-container" style="text-align:center; padding: 40px;"><div class="spinner-border text-primary" role="status"></div><p style="margin-top:10px;">${translations.loading}</p></div>`;

                try {
                    let response = await fetch(`data/lang/${currentLang}/sertifikalar/${productSef}.json`);
                    if (!response.ok && categorySef) {
                        response = await fetch(`data/lang/${currentLang}/sertifikalar/${categorySef}.json`);
                    }

                    if (response.ok) {
                        const certData = await response.json();
                        certificateOptionsPanel.innerHTML = `
                            <div class="certificate-content">
                                ${certData.icerik || '<p>Sertifika içeriği bulunamadı.</p>'}
                            </div>
                        `;
                    } else {
                        certificateOptionsPanel.innerHTML = `<p class="certificate-empty">${translations.no_certificate}</p>`;
                    }
                } catch (e) {
                    console.error('Sertifika yükleme hatası:', e);
                    certificateOptionsPanel.innerHTML = `<p class="certificate-error">${translations.error_certificate}</p>`;
                }
            };

            const renderDefaultSpecContent = () => {
                clearAllModalPanels();

                if (modalSpecTitle) {
                    modalSpecTitle.textContent = translations.dimensions_title;
                }

                if (specDimList) {
                    specDimList.style.display = '';
                    specDimList.innerHTML = defaultDimListMarkup;
                }
            };

            const renderFeatureOptions = (specType) => {
                const activeProduct = sliderProducts[currentIndex] || null;
                const specs = specType === 'technical'
                    ? (Array.isArray(activeProduct?.technical_specs) ? activeProduct.technical_specs : [])
                    : (Array.isArray(activeProduct?.general_specs) ? activeProduct.general_specs : []);

                clearAllModalPanels();

                if (modalSpecTitle) {
                    modalSpecTitle.textContent = specType === 'technical' ? translations.technical_title : translations.general_title;
                }

                if (!specDimList) {
                    return;
                }

                specDimList.style.display = '';

                if (!specs.length) {
                    specDimList.innerHTML = '<li class="dim-item">Bu kategori için özellik bulunmuyor.<span class="dim-value">-</span></li>';
                    return;
                }

                specDimList.innerHTML = specs.map((item) => {
                    return `<li class="dim-item">${escapeHtml(item.label || 'Özellik')}<span class="dim-value">${escapeHtml(item.value || '-')}</span></li>`;
                }).join('');
            };

            nextBtn.addEventListener('click', () => {
                currentIndex = (currentIndex + 1) % slides.length;
                updateSlider();
            });

            prevBtn.addEventListener('click', () => {
                currentIndex = (currentIndex - 1 + slides.length) % slides.length;
                updateSlider();
            });

            // Arkaplandaki görsellere tıklama özelliği
            slides.forEach((slide, index) => {
                slide.addEventListener('click', () => {
                    if (slide.classList.contains('prev') || slide.classList.contains('next')) {
                        currentIndex = index;
                        updateSlider();
                    }
                });
            });

            // Hotspot Interactions
            document.querySelectorAll('.hotspot').forEach(hotspot => {
                const showHotspot = () => {
                    cardTitle.textContent = hotspot.getAttribute('data-title');
                    cardDesc.textContent = hotspot.getAttribute('data-desc');
                    cardImg.src = hotspot.getAttribute('data-img');
                    card.classList.add('active');
                };

                hotspot.addEventListener('mouseenter', showHotspot);
                hotspot.addEventListener('click', (e) => {
                    e.stopPropagation();
                    showHotspot();
                });
            });

            // Close card and modals on slider change or background click
            document.addEventListener('click', (e) => {
                // Hotspot card close
                if (!e.target.closest('.hotspot') && !e.target.closest('.hotspot-card')) {
                    if (card) card.classList.remove('active');
                }

                // Specs modal close on outside click
                if (specsModal && specsModal.classList.contains('active')) {
                    if (!e.target.closest('.specs-modal-container') && !e.target.closest('.spec-card')) {
                        window.closeSpecsModal();
                    }
                }

                // DXF Drawing Modal close on outside click
                if (dxfDrawingModal && dxfDrawingModal.classList.contains('active')) {
                    if (!e.target.closest('.dxf-drawing-container') && !e.target.closest('.spec-card')) {
                        window.closeDxfDrawingModal();
                    }
                }

                // 3D Viewer Modal close on outside click
                if (viewerModal && viewerModal.classList.contains('active')) {
                    if (!e.target.closest('.viewer-modal-container') && !e.target.closest('.three-sixty-logo')) {
                        window.closeViewerModal();
                    }
                }
            });

            // Specs Modal Functionality
            // (Variables already defined at top)

            // ── DXF Drawing Modal ────────────────────────────────────────
            // (Variables already defined at top)

            const openDxfDrawingModal = (fileUrl) => {
                if (fileUrl) {
                    dxfDrawingIframe.src = `/dwg-viewer/viewer.php?file=${encodeURIComponent(fileUrl)}`;
                    dxfDrawingIframe.style.display = 'block';
                    dxfDrawingNoFile.style.display = 'none';
                } else {
                    dxfDrawingIframe.src = '';
                    dxfDrawingIframe.style.display = 'none';
                    dxfDrawingNoFile.style.display = 'block';
                }
                dxfDrawingModal.classList.add('active');
            };

            window.closeDxfDrawingModal = () => {
                dxfDrawingModal.classList.remove('active');
                // Delay src clear so iframe doesn't flash before fade-out
                setTimeout(() => { dxfDrawingIframe.src = ''; }, 350);
                // Restore spec menu
                specsContainer.classList.remove('hide-menu');
                activeSlot.innerHTML = '';
            };

            document.getElementById('dxf-drawing-overlay').addEventListener('click', window.closeDxfDrawingModal);
            document.getElementById('dxf-drawing-close').addEventListener('click', window.closeDxfDrawingModal);

            window.closeSpecsModal = () => {
                specsModal.classList.remove('active');
                specsContainer.classList.remove('hide-menu');
                activeSlot.innerHTML = '';
                renderDefaultSpecContent();
            };

            // Menüden çıkış yapıldığında durumu sıfırla
            specsContainer.addEventListener('mouseleave', () => {
                if (!specsModal.classList.contains('active')) {
                    specsContainer.classList.remove('hide-menu');
                }
            });

            specCards.forEach(sCard => {
                sCard.addEventListener('click', (e) => {
                    e.preventDefault();

                    // Menü listesini anında kapat
                    specsContainer.classList.add('hide-menu');

                    // 1. Aktif slota kopyala
                    activeSlot.innerHTML = '';
                    const clone = sCard.cloneNode(true);
                    clone.classList.remove('active');
                    activeSlot.appendChild(clone);

                    setTimeout(() => {
                        clone.classList.add('active');
                    }, 10);

                    const specType = sCard.getAttribute('data-spec-type') || '';

                    // Drawing: open iframe modal, skip specs-modal
                    if (specType === 'drawing') {
                        specsContainer.classList.remove('hide-menu');
                        activeSlot.innerHTML = '';
                        const activeProduct = sliderProducts[currentIndex] || null;
                        openDxfDrawingModal(activeProduct?.drawing || '');
                        return;
                    }

                    if (specType === 'general' || specType === 'technical') {
                        renderFeatureOptions(specType);
                    } else if (specType === 'offer') {
                        renderOfferForm();
                    } else if (specType === 'colors') {
                        renderColorOptions();
                    } else if (specType === 'attachments') {
                        renderAttachmentOptions();
                    } else if (specType === 'similars') {
                        renderSimilarOptions();
                    } else if (specType === 'certificate') {
                        renderCertificateOptions();
                    } else {
                        renderDefaultSpecContent();
                    }

                    // 2. Detay penceresini aç
                    specsModal.classList.add('active');
                });
            });

            if (similarOptionsPanel) {
                similarOptionsPanel.addEventListener('click', (e) => {
                    const target = e.target.closest('[data-similar-slug]');
                    if (!target) {
                        return;
                    }

                    const similarSlug = target.getAttribute('data-similar-slug') || '';
                    if (!similarSlug) {
                        return;
                    }

                    const targetIndex = sliderProducts.findIndex((item) => item && item.sef_url === similarSlug);

                    if (targetIndex >= 0) {
                        currentIndex = targetIndex;
                        updateSlider();
                        window.closeSpecsModal();
                    } else {
                        window.location.href = `/urun/${encodeURIComponent(similarSlug)}`;
                    }
                });
            }

            document.addEventListener('submit', (e) => {
                const offerForm = e.target.closest('#offer-form-panel');
                if (!offerForm) {
                    return;
                }

                e.preventDefault();

                const hasEmptyRequired = Array.from(offerForm.querySelectorAll('[required]')).some((input) => {
                    return String(input.value || '').trim() === '';
                });

                if (hasEmptyRequired) {
                    return;
                }

                offerOptionsPanel.innerHTML = '<p class="offer-note">Talebiniz alındı. En kısa sürede size dönüş yapacağız.</p>';
            });

            // 360 Viewer Logic
            window.closeViewerModal = () => {
                viewerModal.classList.remove('active');
            };

            if (threeSixtyBtn) {
                threeSixtyBtn.addEventListener('click', () => {
                    viewerModal.classList.add('active');
                });
            }

            syncActiveProduct();
        });
    </script>
</body>

</html>