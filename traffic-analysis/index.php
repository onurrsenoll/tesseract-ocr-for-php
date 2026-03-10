<?php
require_once __DIR__ . '/config.php';
$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-gray-50 min-h-screen">

    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-5xl mx-auto px-4 py-4 flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-600 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-xl font-bold text-gray-900">Trafik Kazası Tutanak Analiz</h1>
                <p class="text-sm text-gray-500">Yapay Zeka Destekli Kusur Analizi</p>
            </div>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-8">

        <!-- Bilgilendirme -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-8">
            <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div class="text-sm text-blue-800">
                    <p class="font-semibold mb-1">Nasıl Kullanılır?</p>
                    <ol class="list-decimal list-inside space-y-1">
                        <li>Kaza Tespit Tutanağı fotoğrafını yükleyin</li>
                        <li>Varsa kaza yeri ve araç hasar fotoğraflarını ekleyin</li>
                        <li>Taraf beyanlarını yazın (isteğe bağlı)</li>
                        <li>"Analiz Et" butonuna basın</li>
                    </ol>
                    <p class="mt-2 text-blue-600">Bu sistem bir ön değerlendirme aracıdır. Kesin kusur tespiti yetkili merciler tarafından yapılır.</p>
                </div>
            </div>
        </div>

        <!-- Ana Form -->
        <form id="analysisForm" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

            <!-- Fotoğraf Yükleme -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Fotoğraf Yükleme</h2>

                <!-- Drop Zone -->
                <div id="dropZone"
                     class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center cursor-pointer
                            hover:border-blue-400 hover:bg-blue-50 transition-colors">
                    <svg class="mx-auto w-12 h-12 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-gray-600 font-medium">Fotoğrafları buraya sürükleyin veya tıklayın</p>
                    <p class="text-gray-400 text-sm mt-1">JPG, PNG, WEBP - Maks. 20MB, en fazla <?= MAX_FILES ?> dosya</p>
                    <input type="file" id="fileInput" multiple accept=".jpg,.jpeg,.png,.webp"
                           class="hidden">
                </div>

                <!-- Yüklenen Dosya Önizlemeleri -->
                <div id="previewContainer" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 mt-4 hidden"></div>
            </div>

            <!-- Taraf Beyanları -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Taraf Beyanları <span class="text-sm font-normal text-gray-400">(İsteğe bağlı)</span></h2>
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label for="tarafA" class="block text-sm font-medium text-gray-700 mb-1">Taraf A (1. Sürücü)</label>
                        <textarea id="tarafA" name="taraf_a" rows="4"
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                                  placeholder="1. sürücünün beyanını yazın..."></textarea>
                    </div>
                    <div>
                        <label for="tarafB" class="block text-sm font-medium text-gray-700 mb-1">Taraf B (2. Sürücü)</label>
                        <textarea id="tarafB" name="taraf_b" rows="4"
                                  class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                                  placeholder="2. sürücünün beyanını yazın..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Analiz Butonu -->
            <div class="flex justify-center">
                <button type="submit" id="analyzeBtn" disabled
                        class="bg-blue-600 text-white font-semibold px-8 py-3 rounded-lg
                               hover:bg-blue-700 focus:ring-4 focus:ring-blue-200
                               disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors">
                    Analiz Et
                </button>
            </div>
        </form>

        <!-- Yükleme / Analiz Durumu -->
        <div id="progressSection" class="hidden mt-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div id="spinner" class="w-6 h-6 border-2 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                    <p id="progressText" class="text-gray-700 font-medium">Fotoğraflar yükleniyor...</p>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2">
                    <div id="progressBar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
            </div>
        </div>

        <!-- Sonuç Ekranı -->
        <div id="resultSection" class="hidden mt-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-green-50 border-b border-green-200 px-6 py-4 flex items-center gap-3">
                    <svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <h2 class="text-lg font-semibold text-green-800">Analiz Tamamlandı</h2>
                </div>
                <div id="resultContent" class="px-6 py-6 prose prose-sm max-w-none"></div>
                <div class="border-t border-gray-200 px-6 py-4 bg-gray-50 flex flex-wrap gap-3">
                    <button id="printBtn" class="text-sm text-gray-600 hover:text-gray-900 flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Yazdır
                    </button>
                    <button id="newAnalysisBtn" class="text-sm text-blue-600 hover:text-blue-800 font-medium">
                        Yeni Analiz Yap
                    </button>
                </div>
            </div>

            <!-- Uyarı -->
            <div class="mt-4 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <div class="flex items-start gap-2">
                    <svg class="w-5 h-5 text-yellow-600 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <p class="text-sm text-yellow-800">
                        Bu rapor yapay zeka tarafından oluşturulmuş bir <strong>ön değerlendirmedir</strong>.
                        Hukuki bağlayıcılığı yoktur. Kesin kusur tespiti yetkili merciler tarafından yapılır.
                    </p>
                </div>
            </div>
        </div>

        <!-- Hata Ekranı -->
        <div id="errorSection" class="hidden mt-8">
            <div class="bg-red-50 border border-red-200 rounded-lg p-6">
                <div class="flex items-start gap-3">
                    <svg class="w-6 h-6 text-red-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <h3 class="font-semibold text-red-800">Hata Oluştu</h3>
                        <p id="errorMessage" class="text-sm text-red-700 mt-1"></p>
                        <button id="retryBtn" class="mt-3 text-sm text-red-600 hover:text-red-800 font-medium underline">
                            Tekrar Dene
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer class="border-t border-gray-200 mt-16 py-6">
        <div class="max-w-5xl mx-auto px-4 text-center text-sm text-gray-400">
            <p>&copy; <?= date('Y') ?> <?= htmlspecialchars(APP_NAME) ?> v<?= APP_VERSION ?></p>
            <p class="mt-1">Bu sistem bir ön değerlendirme aracıdır. KVKK uyumlu olarak çalışır.</p>
        </div>
    </footer>

    <script src="assets/js/app.js"></script>
</body>
</html>
