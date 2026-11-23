<?php
/**
 * Gemini API Proxy untuk Modul Ajar Generator — SMAIT Al Haraki
 * Menyimpan API Key secara aman di sisi server agar tidak terlihat di kode HTML.
 */

// =========================================================================
// 1. KONFIGURASI UTAMA (WAJIB DIISI)
// =========================================================================
$GEMINI_API_KEY = 'AQ.Ab8RN6LX1FoZ2nmezhMyRWeF2-53A5Wc33Xm8bZBs1UAqIeSrQ';
$GEMINI_MODEL   = 'gemini-2.5-flash'; // Alternatif: 'gemini-2.0-flash'
$ALLOWED_DOMAIN = 'ibnusyifa.site';

// =========================================================================
// 2. HEADER & VALIDASI KEAMANAN DOMAIN
// =========================================================================
header('Content-Type: application/json; charset=utf-8');

$origin  = $_SERVER['HTTP_ORIGIN']  ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';

// Izinkan request hanya dari domain ibnusyifa.site (termasuk www / http / https)
$isAllowed = false;
if ($origin !== '' && preg_match('/^https?:\/\/(www\.)?' . preg_quote($ALLOWED_DOMAIN, '/') . '$/i', $origin)) {
    header("Access-Control-Allow-Origin: $origin");
    $isAllowed = true;
} elseif ($referer !== '' && preg_match('/^https?:\/\/(www\.)?' . preg_quote($ALLOWED_DOMAIN, '/') . '(\/|$)/i', $referer)) {
    $isAllowed = true;
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Tangani preflight request browser
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => ['message' => 'Metode tidak diizinkan. Gunakan POST.']]);
    exit;
}

if (!$isAllowed) {
    http_response_code(403);
    echo json_encode(['error' => ['message' => 'Akses ditolak: domain tidak diizinkan.']]);
    exit;
}

if (empty($GEMINI_API_KEY) || $GEMINI_API_KEY === 'AQ.Ab8RN6I8hzhEZwf-PP2a0x72FDk9eoPbsCc3EdShYlSlfBMCvA') {
    http_response_code(500);
    echo json_encode(['error' => ['message' => 'API Key Gemini belum diatur di file ai-proxy.php.']]);
    exit;
}

// =========================================================================
// 3. BACA PAYLOAD DARI FRONTEND
// =========================================================================
$rawInput = file_get_contents('php://input');
$data     = json_decode($rawInput, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'Format JSON tidak valid.']]);
    exit;
}

// Mendukung format payload Gemini standar ({ contents: [...] }) maupun { prompt: "..." }
if (isset($data['contents'])) {
    $payload = $data;
} elseif (!empty($data['prompt'])) {
    $payload = [
        'contents' => [
            ['parts' => [['text' => (string)$data['prompt']]]]
        ],
        'generationConfig' => [
            'responseMimeType' => 'application/json'
        ]
    ];
} else {
    http_response_code(400);
    echo json_encode(['error' => ['message' => 'Payload prompt atau contents tidak ditemukan.']]);
    exit;
}

// =========================================================================
// 4. TERUSKAN REQUEST KE GOOGLE GEMINI API VIA CURL
// =========================================================================
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$GEMINI_MODEL}:generateContent?key=" . urlencode($GEMINI_API_KEY);

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 45,
    CURLOPT_SSL_VERIFYPEER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(502);
    echo json_encode(['error' => ['message' => 'Gagal menghubungi server Gemini: ' . $curlErr]]);
    exit;
}

http_response_code($httpCode ?: 200);
echo $response;
