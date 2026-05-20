<?php
header('Content-Type: application/json');

$uploadDir = __DIR__ . '/files/';
$filename  = basename($_POST['filename'] ?? '');

if ($filename === '') {
    echo json_encode(['success' => false, 'error' => 'Dosya adı belirtilmedi.']);
    exit;
}

$filePath = $uploadDir . $filename;

if (!file_exists($filePath)) {
    echo json_encode(['success' => false, 'error' => 'Dosya bulunamadı.']);
    exit;
}

if (unlink($filePath)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Dosya silinemedi.']);
}
