<?php
/**
 * Trafik Kazası Tutanak Analiz Sistemi - API İşleyici
 *
 * Bu dosya yüklenen görselleri AI servisine gönderir ve analiz sonucunu döndürür.
 * API anahtarları yalnızca sunucu tarafında kalır, frontend'e asla açılmaz.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Geçersiz istek yöntemi.'], 405);
}

// CSRF kontrolü
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!validateCSRF($csrfToken)) {
    jsonResponse(['success' => false, 'error' => 'Güvenlik doğrulaması başarısız.'], 403);
}

// Session kontrolü
if (empty($_SESSION['upload_session'])) {
    jsonResponse(['success' => false, 'error' => 'Geçerli bir yükleme oturumu bulunamadı. Lütfen önce dosyalarınızı yükleyin.'], 400);
}

$uploadSession = $_SESSION['upload_session'];

// Oturum zaman aşımı kontrolü (30 dakika)
if ((time() - $uploadSession['time']) > 1800) {
    cleanupTempFiles(array_column($uploadSession['files'], 'path'));
    unset($_SESSION['upload_session']);
    jsonResponse(['success' => false, 'error' => 'Yükleme oturumu zaman aşımına uğradı. Lütfen dosyalarınızı tekrar yükleyin.'], 408);
}

// Taraf beyanlarını al
$tarafA = trim($_POST['taraf_a'] ?? '');
$tarafB = trim($_POST['taraf_b'] ?? '');

// Görselleri hazırla
$images = [];
$filePaths = [];

foreach ($uploadSession['files'] as $file) {
    if (!file_exists($file['path'])) {
        continue;
    }
    $images[] = [
        'base64'   => imageToBase64($file['path'], $file['mime']),
        'mime'     => $file['mime'],
        'original' => $file['original'],
    ];
    $filePaths[] = $file['path'];
}

if (empty($images)) {
    jsonResponse(['success' => false, 'error' => 'Yüklenen dosyalar bulunamadı.'], 404);
}

// AI analiz promptu
$systemPrompt = getAnalysisSystemPrompt();
$userPrompt = getAnalysisUserPrompt($tarafA, $tarafB);

// AI API çağrısı
try {
    $result = callAIProvider($images, $systemPrompt, $userPrompt);

    // Başarılı analiz logu
    logAnalysis($uploadSession['id'], AI_PROVIDER, count($images), 'success');

    // Geçici dosyaları temizle (KVKK uyumu)
    cleanupTempFiles($filePaths);
    unset($_SESSION['upload_session']);

    jsonResponse([
        'success'  => true,
        'analysis' => $result,
        'metadata' => [
            'provider'    => AI_PROVIDER,
            'image_count' => count($images),
            'timestamp'   => date('Y-m-d H:i:s'),
        ],
    ]);
} catch (Exception $e) {
    logError('AI API hatası: ' . $e->getMessage());
    logAnalysis($uploadSession['id'], AI_PROVIDER, count($images), 'error', $e->getMessage());

    // Hata durumunda da dosyaları temizle
    cleanupTempFiles($filePaths);
    unset($_SESSION['upload_session']);

    jsonResponse([
        'success' => false,
        'error'   => 'Analiz sırasında bir hata oluştu. Lütfen tekrar deneyin.',
    ], 500);
}

// ============================================================
// YARDIMCI FONKSİYONLAR
// ============================================================

/**
 * Sistem promptunu döndürür.
 */
function getAnalysisSystemPrompt(): string
{
    return <<<'PROMPT'
Sen bir trafik kazası analiz uzmanısın. Türkiye Karayolları Trafik Kanunu ve ilgili mevzuat hakkında derin bilgiye sahipsin.

Görevin, sana verilen kaza tespit tutanağı fotoğrafları, kaza yeri fotoğrafları, araç hasar fotoğrafları ve taraf beyanlarını analiz ederek aşağıdaki formatta bir rapor hazırlamaktır.

Analiz yaparken şu kriterleri göz önünde bulundur:
- 2918 sayılı Karayolları Trafik Kanunu
- Trafik kazası kusur oranı kriterleri
- Tutanak krokisindeki araç pozisyonları ve yol işaretleri
- Araçlardaki hasar noktaları ve çarpışma açıları
- Taraf beyanlarındaki tutarlılık ve çelişkiler

Raporunu mutlaka aşağıdaki bölümlerden oluşacak şekilde hazırla:

1. TARAF BEYANLARI
Tutanaktan veya kullanıcıdan alınan taraf beyanlarını özetle.

2. OLAYININ MUHTEMEL OLUŞ ŞEKLİ
Tüm verileri değerlendirerek kazanın nasıl meydana geldiğini açıkla.

3. İHLAL EDİLEN TRAFİK KURALLARI
Her taraf için ihlal edilen trafik kurallarını ve ilgili kanun maddelerini belirt.

4. ARAÇLARIN KUSUR DEĞERLENDİRMESİ
Her bir tarafın kusur durumunu gerekçeleriyle açıkla.

5. TAHMİNİ KUSUR ORANLARI
Her taraf için yüzde olarak tahmini kusur oranını ver.

Önemli: Bu analiz bir ön değerlendirmedir ve hukuki bağlayıcılığı yoktur. Kesin kusur tespiti yetkili merciler tarafından yapılır.
PROMPT;
}

/**
 * Kullanıcı promptunu oluşturur.
 */
function getAnalysisUserPrompt(string $tarafA, string $tarafB): string
{
    $prompt = "Lütfen yüklenen kaza tespit tutanağı ve kaza fotoğraflarını analiz et.\n\n";

    if (!empty($tarafA) || !empty($tarafB)) {
        $prompt .= "TARAF BEYANLARI:\n";
        if (!empty($tarafA)) {
            $prompt .= "Taraf A (1. Sürücü): $tarafA\n";
        }
        if (!empty($tarafB)) {
            $prompt .= "Taraf B (2. Sürücü): $tarafB\n";
        }
        $prompt .= "\n";
    }

    $prompt .= "Yukarıdaki verileri ve yüklenen fotoğrafları kullanarak detaylı bir kaza analiz raporu hazırla.";

    return $prompt;
}

/**
 * AI sağlayıcısına göre API çağrısı yapar.
 */
function callAIProvider(array $images, string $systemPrompt, string $userPrompt): string
{
    return match (AI_PROVIDER) {
        'gemini'  => callGemini($images, $systemPrompt, $userPrompt),
        'openai'  => callOpenAI($images, $systemPrompt, $userPrompt),
        'claude'  => callClaude($images, $systemPrompt, $userPrompt),
        default   => throw new Exception('Geçersiz AI sağlayıcısı: ' . AI_PROVIDER),
    };
}

/**
 * Google Gemini API çağrısı.
 */
function callGemini(array $images, string $systemPrompt, string $userPrompt): string
{
    if (empty(GEMINI_API_KEY)) {
        throw new Exception('Gemini API anahtarı yapılandırılmamış.');
    }

    $parts = [];

    // Görselleri ekle
    foreach ($images as $img) {
        $parts[] = [
            'inline_data' => [
                'mime_type' => $img['mime'],
                'data'      => $img['base64'],
            ],
        ];
    }

    // Metin promptunu ekle
    $parts[] = ['text' => $userPrompt];

    $payload = [
        'system_instruction' => [
            'parts' => [['text' => $systemPrompt]],
        ],
        'contents' => [
            ['parts' => $parts],
        ],
        'generationConfig' => [
            'temperature'     => 0.3,
            'maxOutputTokens' => 4096,
        ],
    ];

    $url = GEMINI_API_URL . GEMINI_MODEL . ':generateContent?key=' . GEMINI_API_KEY;

    $response = makeCurlRequest($url, $payload, [
        'Content-Type: application/json',
    ]);

    if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
        return $response['candidates'][0]['content']['parts'][0]['text'];
    }

    throw new Exception('Gemini API yanıtı beklenmeyen formatta: ' . json_encode($response));
}

/**
 * OpenAI API çağrısı.
 */
function callOpenAI(array $images, string $systemPrompt, string $userPrompt): string
{
    if (empty(OPENAI_API_KEY)) {
        throw new Exception('OpenAI API anahtarı yapılandırılmamış.');
    }

    $content = [];

    // Görselleri ekle
    foreach ($images as $img) {
        $content[] = [
            'type'      => 'image_url',
            'image_url' => [
                'url'    => 'data:' . $img['mime'] . ';base64,' . $img['base64'],
                'detail' => 'high',
            ],
        ];
    }

    // Metin promptunu ekle
    $content[] = [
        'type' => 'text',
        'text' => $userPrompt,
    ];

    $payload = [
        'model'       => OPENAI_MODEL,
        'messages'     => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $content],
        ],
        'max_tokens'  => 4096,
        'temperature' => 0.3,
    ];

    $response = makeCurlRequest(OPENAI_API_URL, $payload, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY,
    ]);

    if (isset($response['choices'][0]['message']['content'])) {
        return $response['choices'][0]['message']['content'];
    }

    throw new Exception('OpenAI API yanıtı beklenmeyen formatta: ' . json_encode($response));
}

/**
 * Claude (Anthropic) API çağrısı.
 */
function callClaude(array $images, string $systemPrompt, string $userPrompt): string
{
    if (empty(CLAUDE_API_KEY)) {
        throw new Exception('Claude API anahtarı yapılandırılmamış.');
    }

    $content = [];

    // Görselleri ekle
    foreach ($images as $img) {
        $content[] = [
            'type'   => 'image',
            'source' => [
                'type'       => 'base64',
                'media_type' => $img['mime'],
                'data'       => $img['base64'],
            ],
        ];
    }

    // Metin promptunu ekle
    $content[] = [
        'type' => 'text',
        'text' => $userPrompt,
    ];

    $payload = [
        'model'      => CLAUDE_MODEL,
        'max_tokens' => 4096,
        'system'     => $systemPrompt,
        'messages'   => [
            ['role' => 'user', 'content' => $content],
        ],
    ];

    $response = makeCurlRequest(CLAUDE_API_URL, $payload, [
        'Content-Type: application/json',
        'x-api-key: ' . CLAUDE_API_KEY,
        'anthropic-version: 2023-06-01',
    ]);

    if (isset($response['content'][0]['text'])) {
        return $response['content'][0]['text'];
    }

    throw new Exception('Claude API yanıtı beklenmeyen formatta: ' . json_encode($response));
}

/**
 * cURL ile HTTP POST isteği yapar.
 */
function makeCurlRequest(string $url, array $payload, array $headers): array
{
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $responseBody = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new Exception("cURL hatası: $error");
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception("API HTTP hatası ($httpCode): $responseBody");
    }

    $decoded = json_decode($responseBody, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('API yanıtı JSON olarak ayrıştırılamadı: ' . json_last_error_msg());
    }

    return $decoded;
}
