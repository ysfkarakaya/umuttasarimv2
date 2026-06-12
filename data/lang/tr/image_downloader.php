<?php
// data/lang/tr/image_downloader.php

/**
 * Bu script, bulunduğu dizindeki ve alt dizinlerdeki tüm .json dosyalarını tarar,
 * 'upload/' ile başlayan değerleri bulur ve bu dosyaları ana dizindeki 
 * ilgili klasörlere indirir.
 */

// İndirilecek kaynak ana URL (Sonunda / olmalı)
$url = 'https://v2.umutapp.com/';

// Sadece görsel dosyalarını (jpg, jpeg, png, gif, webp, svg) indirmek için true yapın
$onlyImages = true;

// Proje ana dizini (data/lang/tr/ dizininden 3 seviye yukarı)
$projectRoot = realpath(__DIR__ . '/../../..');

// Browser çıktısı için header
header('Content-Type: text/html; charset=utf-8');

set_time_limit(0); // Büyük indirmeler için zaman sınırını kaldır
ini_set('memory_limit', '512M');

/**
 * Verilen dosya yolunu (upload/...) uzak sunucudan indirip yerele kaydeder.
 */
function download_resource($path, $sourceUrl, $rootDir)
{
    // URL segmentlerini urlencode ederek boşluk ve özel karakterleri güvenli hale getiriyoruz.
    $pathSegments = explode('/', ltrim($path, '/'));
    $encodedSegments = array_map('rawurlencode', $pathSegments);
    $encodedPath = implode('/', $encodedSegments);

    // URL'den indirilecek tam adres
    $remoteUrl = rtrim($sourceUrl, '/') . '/' . $encodedPath;

    // Kaydedilecek yerel tam yol (yerel dosya sisteminde urlencode kullanılmaz)
    $localPath = $rootDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

    if (file_exists($localPath)) {
        echo "<span style='color: #888;'>[MEVCUT] {$path}</span><br>";
        return;
    }

    // Klasör yapısını oluştur
    $dir = dirname($localPath);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0777, true)) {
            echo "<span style='color: red;'>[HATA] Klasör oluştlamadı: {$dir}</span><br>";
            return;
        }
    }

    // Veriyi çek (cURL daha güvenli ve esnektir)
    $ch = curl_init($remoteUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200 && $data) {
        if (file_put_contents($localPath, $data)) {
            echo "<span style='color: green;'>[İNDİRİLDİ] {$path}</span><br>";
        } else {
            echo "<span style='color: red;'>[HATA] Yazma hatası (İzinleri kontrol edin): {$path}</span><br>";
        }
    } else {
        echo "<span style='color: red;'>[HATA] Bulunamadı (HTTP {$httpCode}): {$remoteUrl}</span><br>";
    }

    // Sunucuyu yormamak için kısa bir bekleme
    usleep(100000); // 0.1 saniye
}

/**
 * JSON içindeki diziyi/objeyi tarayıp upload/ ile başlayanları bulur.
 */
function process_json_data($data, $sourceUrl, $rootDir, $onlyImages)
{
    if (is_array($data) || is_object($data)) {
        foreach ($data as $key => $value) {
            if (is_string($value) && strpos($value, 'upload/') === 0) {
                if ($onlyImages) {
                    $ext = strtolower(pathinfo($value, PATHINFO_EXTENSION));
                    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico'];
                    if (in_array($ext, $allowedExts)) {
                        download_resource($value, $sourceUrl, $rootDir);
                    }
                } else {
                    download_resource($value, $sourceUrl, $rootDir);
                }
            } else {
                process_json_data($value, $sourceUrl, $rootDir, $onlyImages);
            }
        }
    }
}

echo "<html><body style='font-family: \"Cascadia Code\", \"Consolas\", monospace; font-size: 13px; background: #1e1e1e; color: #d4d4d4; padding: 20px; line-height: 1.6;'>";
echo "<h2 style='color: #569cd6;'>Görsel Aktarım & Senkronizasyon (JSON based)</h2>";
echo "<b>Kaynak URL:</b> <span style='color: #ce9178;'>{$url}</span><br>";
echo "<b>Hedef Dizin:</b> <span style='color: #ce9178;'>{$projectRoot}</span><br>";
echo "<b>Sadece Görseller:</b> <span style='color: " . ($onlyImages ? '#9cdcfe' : '#f44747') . ";'>" . ($onlyImages ? 'AKTİF' : 'PASİF') . "</span><br><hr style='border: 0; border-top: 1px solid #333;'>";

if (!$projectRoot || !is_dir($projectRoot)) {
    die("<span style='color: #f44336;'>[KRİTİK HATA] Proje ana dizini tespit edilemedi!</span>");
}

try {
    $directory = new RecursiveDirectoryIterator(__DIR__);
    $iterator = new RecursiveIteratorIterator($directory);

    foreach ($iterator as $info) {
        // Sadece .json dosyalarını işle ve bu scriptin kendisini atla
        if ($info->isFile() && $info->getExtension() === 'json') {
            echo "<b style='color: #4ec9b0;'>İşleniyor:</b> " . str_replace(__DIR__, '', $info->getPathname()) . "<br>";
            $content = file_get_contents($info->getPathname());
            $json = json_decode($content, true);

            if ($json) {
                process_json_data($json, $url, $projectRoot, $onlyImages);
            } else {
                echo "<span style='color: #d19a66;'>[UYARI] Geçersiz JSON formatı: " . $info->getFilename() . "</span><br>";
            }
            echo "<br>";
        }
    }
} catch (Exception $e) {
    echo "<span style='color: red;'>[HATA] " . $e->getMessage() . "</span><br>";
}

echo "<hr style='border: 0; border-top: 1px solid #333;'><h3 style='color: #6a9955;'>İşlem Tamamlandı.</h3>";
echo "</body></html>";
