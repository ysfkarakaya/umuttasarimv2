<?php
header('Content-Type: application/json');

$allowedExtensions = ['dwg', 'dxf'];
$maxSize = 100 * 1024 * 1024; // 100MB

$uploadDir = __DIR__ . '/files/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'error' => 'Geçersiz istek.']);
    exit;
}

$file = $_FILES['file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Dosya yükleme hatası: ' . $file['error']]);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExtensions)) {
    echo json_encode(['success' => false, 'error' => 'Sadece DWG ve DXF dosyaları desteklenmektedir.']);
    exit;
}

if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'Dosya boyutu 100 MB\'ı geçemez.']);
    exit;
}

$safeName = preg_replace('/[^a-zA-Z0-9._\-]/', '_', basename($file['name']));
$finalName = time() . '_' . $safeName;
$destination = $uploadDir . $finalName;

if (move_uploaded_file($file['tmp_name'], $destination)) {
    echo json_encode([
        'success'  => true,
        'filename' => $finalName,
        'original' => $file['name'],
        'ext'      => $ext,
        'size'     => $file['size'],
        'url'      => '/dwg-viewer/files/' . rawurlencode($finalName),
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Dosya kaydedilemedi.']);
}
