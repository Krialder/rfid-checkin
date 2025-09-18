<?php

namespace App\Services;

use App\Core\ConfigurationService;
use App\Core\LoggingService;
use Exception;

/**
 * Asset Optimization Service
 * 
 * Provides comprehensive asset optimization including:
 * - CSS and JavaScript minification
 * - Image optimization and compression
 * - Asset bundling and concatenation
 * - Version management and cache busting
 * - CDN integration and asset delivery
 * - Compression (Gzip/Brotli) support
 */
class AssetOptimizationService
{
    private static ?self $instance = null;
    private ConfigurationService $config;
    private LoggingService $logger;
    private string $assetDirectory;
    private string $optimizedDirectory;
    private array $optimizationStats = [];
    
    private function __construct()
    {
        $this->config = ConfigurationService::getInstance();
        $this->logger = LoggingService::getInstance();
        $this->assetDirectory = $this->config->get('assets.source_directory', __DIR__ . '/../../assets');
        $this->optimizedDirectory = $this->config->get('assets.optimized_directory', __DIR__ . '/../../assets/optimized');
        $this->initializeDirectories();
        $this->initializeOptimizationStats();
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Optimize all assets
     */
    public function optimizeAllAssets(): array
    {
        try {
            $results = [
                'css' => $this->optimizeCSS(),
                'javascript' => $this->optimizeJavaScript(),
                'images' => $this->optimizeImages(),
                'fonts' => $this->optimizeFonts(),
                'bundles' => $this->createAssetBundles(),
                'manifest' => $this->generateAssetManifest()
            ];
            
            $this->updateOptimizationStats($results);
            
            $this->logger->info('Asset optimization completed', [
                'results' => $results,
                'total_savings' => $this->calculateTotalSavings($results)
            ]);
            
            return [
                'success' => true,
                'results' => $results,
                'savings' => $this->calculateTotalSavings($results),
                'stats' => $this->optimizationStats
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Asset optimization failed', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Optimize CSS files
     */
    public function optimizeCSS(): array
    {
        try {
            $cssFiles = $this->findFiles($this->assetDirectory . '/css', '*.css');
            $results = [];
            
            foreach ($cssFiles as $file) {
                $originalSize = filesize($file);
                $content = file_get_contents($file);
                
                // Minify CSS
                $minifiedContent = $this->minifyCSS($content);
                
                // Apply additional optimizations
                $optimizedContent = $this->optimizeCSSContent($minifiedContent);
                
                // Generate output filename
                $relativePath = str_replace($this->assetDirectory . '/css/', '', $file);
                $outputFile = $this->optimizedDirectory . '/css/' . $this->generateOptimizedFilename($relativePath);
                
                // Ensure output directory exists
                $outputDir = dirname($outputFile);
                if (!is_dir($outputDir)) {
                    mkdir($outputDir, 0755, true);
                }
                
                // Write optimized file
                file_put_contents($outputFile, $optimizedContent);
                
                // Create compressed versions
                $this->createCompressedVersions($outputFile, $optimizedContent);
                
                $optimizedSize = strlen($optimizedContent);
                $savings = $originalSize - $optimizedSize;
                $savingsPercent = round(($savings / $originalSize) * 100, 2);
                
                $results[] = [
                    'file' => $relativePath,
                    'original_size' => $originalSize,
                    'optimized_size' => $optimizedSize,
                    'savings' => $savings,
                    'savings_percent' => $savingsPercent,
                    'output_file' => str_replace($this->optimizedDirectory . '/', '', $outputFile)
                ];
            }
            
            return $results;
            
        } catch (Exception $e) {
            $this->logger->error('CSS optimization failed', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Optimize JavaScript files
     */
    public function optimizeJavaScript(): array
    {
        try {
            $jsFiles = $this->findFiles($this->assetDirectory . '/js', '*.js');
            $results = [];
            
            foreach ($jsFiles as $file) {
                $originalSize = filesize($file);
                $content = file_get_contents($file);
                
                // Minify JavaScript
                $minifiedContent = $this->minifyJavaScript($content);
                
                // Apply additional optimizations
                $optimizedContent = $this->optimizeJavaScriptContent($minifiedContent);
                
                // Generate output filename
                $relativePath = str_replace($this->assetDirectory . '/js/', '', $file);
                $outputFile = $this->optimizedDirectory . '/js/' . $this->generateOptimizedFilename($relativePath);
                
                // Ensure output directory exists
                $outputDir = dirname($outputFile);
                if (!is_dir($outputDir)) {
                    mkdir($outputDir, 0755, true);
                }
                
                // Write optimized file
                file_put_contents($outputFile, $optimizedContent);
                
                // Create compressed versions
                $this->createCompressedVersions($outputFile, $optimizedContent);
                
                $optimizedSize = strlen($optimizedContent);
                $savings = $originalSize - $optimizedSize;
                $savingsPercent = round(($savings / $originalSize) * 100, 2);
                
                $results[] = [
                    'file' => $relativePath,
                    'original_size' => $originalSize,
                    'optimized_size' => $optimizedSize,
                    'savings' => $savings,
                    'savings_percent' => $savingsPercent,
                    'output_file' => str_replace($this->optimizedDirectory . '/', '', $outputFile)
                ];
            }
            
            return $results;
            
        } catch (Exception $e) {
            $this->logger->error('JavaScript optimization failed', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Optimize image files
     */
    public function optimizeImages(): array
    {
        try {
            $imageExtensions = ['*.jpg', '*.jpeg', '*.png', '*.gif', '*.svg', '*.webp'];
            $imageFiles = [];
            
            foreach ($imageExtensions as $extension) {
                $files = $this->findFiles($this->assetDirectory . '/images', $extension);
                $imageFiles = array_merge($imageFiles, $files);
            }
            
            $results = [];
            
            foreach ($imageFiles as $file) {
                $originalSize = filesize($file);
                $imageInfo = getimagesize($file);
                $mimeType = $imageInfo['mime'] ?? 'unknown';
                
                // Generate output filename
                $relativePath = str_replace($this->assetDirectory . '/images/', '', $file);
                $outputFile = $this->optimizedDirectory . '/images/' . $relativePath;
                
                // Ensure output directory exists
                $outputDir = dirname($outputFile);
                if (!is_dir($outputDir)) {
                    mkdir($outputDir, 0755, true);
                }
                
                // Optimize based on image type
                $optimizedSize = $this->optimizeImageFile($file, $outputFile, $mimeType);
                
                // Generate WebP version if supported
                $webpFile = $this->generateWebPVersion($file, $outputFile);
                
                $savings = $originalSize - $optimizedSize;
                $savingsPercent = $originalSize > 0 ? round(($savings / $originalSize) * 100, 2) : 0;
                
                $results[] = [
                    'file' => $relativePath,
                    'mime_type' => $mimeType,
                    'dimensions' => $imageInfo[0] . 'x' . $imageInfo[1],
                    'original_size' => $originalSize,
                    'optimized_size' => $optimizedSize,
                    'savings' => $savings,
                    'savings_percent' => $savingsPercent,
                    'output_file' => str_replace($this->optimizedDirectory . '/', '', $outputFile),
                    'webp_version' => $webpFile ? str_replace($this->optimizedDirectory . '/', '', $webpFile) : null
                ];
            }
            
            return $results;
            
        } catch (Exception $e) {
            $this->logger->error('Image optimization failed', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Optimize font files
     */
    public function optimizeFonts(): array
    {
        try {
            $fontExtensions = ['*.woff', '*.woff2', '*.ttf', '*.otf', '*.eot'];
            $fontFiles = [];
            
            foreach ($fontExtensions as $extension) {
                $files = $this->findFiles($this->assetDirectory . '/fonts', $extension);
                $fontFiles = array_merge($fontFiles, $files);
            }
            
            $results = [];
            
            foreach ($fontFiles as $file) {
                $originalSize = filesize($file);
                
                // Generate output filename
                $relativePath = str_replace($this->assetDirectory . '/fonts/', '', $file);
                $outputFile = $this->optimizedDirectory . '/fonts/' . $relativePath;
                
                // Ensure output directory exists
                $outputDir = dirname($outputFile);
                if (!is_dir($outputDir)) {
                    mkdir($outputDir, 0755, true);
                }
                
                // For fonts, we mainly copy and compress
                copy($file, $outputFile);
                
                // Create compressed versions
                $content = file_get_contents($file);
                $this->createCompressedVersions($outputFile, $content);
                
                $results[] = [
                    'file' => $relativePath,
                    'original_size' => $originalSize,
                    'optimized_size' => $originalSize, // Fonts don't get optimized much
                    'savings' => 0,
                    'savings_percent' => 0,
                    'output_file' => str_replace($this->optimizedDirectory . '/', '', $outputFile)
                ];
            }
            
            return $results;
            
        } catch (Exception $e) {
            $this->logger->error('Font optimization failed', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Create asset bundles
     */
    public function createAssetBundles(): array
    {
        try {
            $bundles = $this->config->get('assets.bundles', [
                'app' => [
                    'css' => ['main.css', 'dashboard.css', 'forms.css'],
                    'js' => ['main.js', 'dashboard.js', 'forms.js']
                ],
                'admin' => [
                    'css' => ['admin-tools.css', 'analytics.css'],
                    'js' => ['admin.js', 'analytics.js']
                ]
            ]);
            
            $results = [];
            
            foreach ($bundles as $bundleName => $bundleConfig) {
                $bundleResult = [
                    'name' => $bundleName,
                    'css' => null,
                    'js' => null
                ];
                
                // Create CSS bundle
                if (!empty($bundleConfig['css'])) {
                    $bundleResult['css'] = $this->createCSSBundle($bundleName, $bundleConfig['css']);
                }
                
                // Create JS bundle
                if (!empty($bundleConfig['js'])) {
                    $bundleResult['js'] = $this->createJSBundle($bundleName, $bundleConfig['js']);
                }
                
                $results[] = $bundleResult;
            }
            
            return $results;
            
        } catch (Exception $e) {
            $this->logger->error('Asset bundling failed', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Generate asset manifest for cache busting
     */
    public function generateAssetManifest(): array
    {
        try {
            $manifest = [];
            
            // Scan optimized directory for all assets
            $allFiles = $this->findFiles($this->optimizedDirectory, '*', true);
            
            foreach ($allFiles as $file) {
                $relativePath = str_replace($this->optimizedDirectory . '/', '', $file);
                $hash = $this->generateFileHash($file);
                $filesize = filesize($file);
                
                $manifest[$relativePath] = [
                    'hash' => $hash,
                    'size' => $filesize,
                    'modified' => filemtime($file),
                    'versioned_name' => $this->generateVersionedFilename($relativePath, $hash)
                ];
            }
            
            // Write manifest file
            $manifestFile = $this->optimizedDirectory . '/manifest.json';
            file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT));
            
            return $manifest;
            
        } catch (Exception $e) {
            $this->logger->error('Asset manifest generation failed', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * Get asset URL with version hash
     */
    public function getAssetUrl(string $assetPath): string
    {
        $manifestFile = $this->optimizedDirectory . '/manifest.json';
        
        if (!file_exists($manifestFile)) {
            return $this->config->get('assets.base_url', '/assets') . '/' . $assetPath;
        }
        
        $manifest = json_decode(file_get_contents($manifestFile), true);
        
        if (isset($manifest[$assetPath])) {
            $versionedName = $manifest[$assetPath]['versioned_name'];
            return $this->config->get('assets.base_url', '/assets') . '/' . $versionedName;
        }
        
        return $this->config->get('assets.base_url', '/assets') . '/' . $assetPath;
    }
    
    /**
     * Serve optimized asset with appropriate headers
     */
    public function serveAsset(string $assetPath): void
    {
        $filePath = $this->optimizedDirectory . '/' . $assetPath;
        
        if (!file_exists($filePath)) {
            http_response_code(404);
            return;
        }
        
        $mimeType = $this->getMimeType($filePath);
        $lastModified = filemtime($filePath);
        $etag = '"' . md5($lastModified . filesize($filePath)) . '"';
        
        // Set caching headers
        header('Content-Type: ' . $mimeType);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
        header('ETag: ' . $etag);
        header('Cache-Control: public, max-age=31536000'); // 1 year
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
        
        // Check if client has cached version
        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        
        if (($ifModifiedSince && strtotime($ifModifiedSince) >= $lastModified) ||
            ($ifNoneMatch && $ifNoneMatch === $etag)) {
            http_response_code(304);
            return;
        }
        
        // Check for compressed version
        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        
        if (strpos($acceptEncoding, 'br') !== false && file_exists($filePath . '.br')) {
            header('Content-Encoding: br');
            readfile($filePath . '.br');
        } elseif (strpos($acceptEncoding, 'gzip') !== false && file_exists($filePath . '.gz')) {
            header('Content-Encoding: gzip');
            readfile($filePath . '.gz');
        } else {
            readfile($filePath);
        }
    }
    
    /**
     * Get optimization statistics
     */
    public function getOptimizationStats(): array
    {
        return [
            'stats' => $this->optimizationStats,
            'cache_info' => $this->getCacheInfo(),
            'cdn_info' => $this->getCDNInfo(),
            'performance_metrics' => $this->getPerformanceMetrics()
        ];
    }
    
    /**
     * Clear optimized assets
     */
    public function clearOptimizedAssets(): bool
    {
        try {
            $this->removeDirectory($this->optimizedDirectory);
            $this->initializeDirectories();
            
            $this->logger->info('Optimized assets cleared');
            return true;
            
        } catch (Exception $e) {
            $this->logger->error('Failed to clear optimized assets', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    // Private helper methods
    
    private function initializeDirectories(): void
    {
        $directories = [
            $this->optimizedDirectory,
            $this->optimizedDirectory . '/css',
            $this->optimizedDirectory . '/js',
            $this->optimizedDirectory . '/images',
            $this->optimizedDirectory . '/fonts'
        ];
        
        foreach ($directories as $directory) {
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
        }
    }
    
    private function initializeOptimizationStats(): void
    {
        $this->optimizationStats = [
            'total_files_processed' => 0,
            'total_original_size' => 0,
            'total_optimized_size' => 0,
            'total_savings' => 0,
            'last_optimization' => null,
            'optimization_count' => 0
        ];
    }
    
    private function findFiles(string $directory, string $pattern, bool $recursive = false): array
    {
        if (!is_dir($directory)) {
            return [];
        }
        
        $files = [];
        
        if ($recursive) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $files[] = $file->getPathname();
                }
            }
        } else {
            $files = glob($directory . '/' . $pattern);
        }
        
        return $files ?: [];
    }
    
    private function minifyCSS(string $content): string
    {
        // Remove comments
        $content = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $content);
        
        // Remove whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Remove unnecessary spaces
        $content = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $content);
        
        // Remove trailing semicolon before closing brace
        $content = preg_replace('/;(\s*})/', '$1', $content);
        
        // Remove leading/trailing whitespace
        $content = trim($content);
        
        return $content;
    }
    
    private function optimizeCSSContent(string $content): string
    {
        // Convert hex colors to shorter format
        $content = preg_replace('/#([0-9a-fA-F])\1([0-9a-fA-F])\2([0-9a-fA-F])\3/', '#$1$2$3', $content);
        
        // Remove unnecessary quotes
        $content = preg_replace('/url\((["\'])([^)]*)\1\)/', 'url($2)', $content);
        
        // Optimize font weights
        $content = str_replace(['font-weight:normal', 'font-weight:bold'], ['font-weight:400', 'font-weight:700'], $content);
        
        return $content;
    }
    
    private function minifyJavaScript(string $content): string
    {
        // This is a basic minification - in production, use a proper JS minifier
        
        // Remove single-line comments
        $content = preg_replace('/\/\/.*$/m', '', $content);
        
        // Remove multi-line comments
        $content = preg_replace('/\/\*[\s\S]*?\*\//', '', $content);
        
        // Remove extra whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Remove spaces around operators
        $content = preg_replace('/\s*([=+\-*\/%;,\(\){}\[\]<>!&|:?])\s*/', '$1', $content);
        
        // Remove trailing semicolons before }
        $content = preg_replace('/;(\s*})/', '$1', $content);
        
        return trim($content);
    }
    
    private function optimizeJavaScriptContent(string $content): string
    {
        // Basic optimizations - in production, use proper JS optimizer
        
        // Replace boolean literals
        $content = str_replace(['===true', '!==false', '==true', '!=false'], ['', '', '', ''], $content);
        
        return $content;
    }
    
    private function optimizeImageFile(string $inputFile, string $outputFile, string $mimeType): int
    {
        try {
            switch ($mimeType) {
                case 'image/jpeg':
                    return $this->optimizeJPEG($inputFile, $outputFile);
                    
                case 'image/png':
                    return $this->optimizePNG($inputFile, $outputFile);
                    
                case 'image/gif':
                    return $this->optimizeGIF($inputFile, $outputFile);
                    
                case 'image/svg+xml':
                    return $this->optimizeSVG($inputFile, $outputFile);
                    
                default:
                    // Just copy the file
                    copy($inputFile, $outputFile);
                    return filesize($outputFile);
            }
        } catch (Exception $e) {
            // Fallback to copying
            copy($inputFile, $outputFile);
            return filesize($outputFile);
        }
    }
    
    private function optimizeJPEG(string $inputFile, string $outputFile): int
    {
        if (!extension_loaded('gd')) {
            copy($inputFile, $outputFile);
            return filesize($outputFile);
        }
        
        $image = imagecreatefromjpeg($inputFile);
        if (!$image) {
            copy($inputFile, $outputFile);
            return filesize($outputFile);
        }
        
        // Apply optimization settings
        $quality = $this->config->get('assets.jpeg_quality', 85);
        imagejpeg($image, $outputFile, $quality);
        imagedestroy($image);
        
        return filesize($outputFile);
    }
    
    private function optimizePNG(string $inputFile, string $outputFile): int
    {
        if (!extension_loaded('gd')) {
            copy($inputFile, $outputFile);
            return filesize($outputFile);
        }
        
        $image = imagecreatefrompng($inputFile);
        if (!$image) {
            copy($inputFile, $outputFile);
            return filesize($outputFile);
        }
        
        // Apply optimization settings
        $compression = $this->config->get('assets.png_compression', 6);
        imagepng($image, $outputFile, $compression);
        imagedestroy($image);
        
        return filesize($outputFile);
    }
    
    private function optimizeGIF(string $inputFile, string $outputFile): int
    {
        // GIF optimization is limited with GD
        copy($inputFile, $outputFile);
        return filesize($outputFile);
    }
    
    private function optimizeSVG(string $inputFile, string $outputFile): int
    {
        $content = file_get_contents($inputFile);
        
        // Basic SVG optimization
        $content = preg_replace('/>\s+</', '><', $content); // Remove whitespace between tags
        $content = preg_replace('/\s+/', ' ', $content); // Collapse whitespace
        $content = trim($content);
        
        file_put_contents($outputFile, $content);
        return filesize($outputFile);
    }
    
    private function generateWebPVersion(string $inputFile, string $outputFile): ?string
    {
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            return null;
        }
        
        $imageInfo = getimagesize($inputFile);
        $mimeType = $imageInfo['mime'] ?? '';
        
        $image = null;
        
        switch ($mimeType) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($inputFile);
                break;
            case 'image/png':
                $image = imagecreatefrompng($inputFile);
                break;
            default:
                return null;
        }
        
        if (!$image) {
            return null;
        }
        
        $webpFile = preg_replace('/\.(jpe?g|png)$/i', '.webp', $outputFile);
        $quality = $this->config->get('assets.webp_quality', 80);
        
        if (imagewebp($image, $webpFile, $quality)) {
            imagedestroy($image);
            return $webpFile;
        }
        
        imagedestroy($image);
        return null;
    }
    
    private function createCompressedVersions(string $file, string $content): void
    {
        // Create Gzip version
        if (function_exists('gzencode')) {
            file_put_contents($file . '.gz', gzencode($content, 9));
        }
        
        // Create Brotli version (if extension is available)
        if (function_exists('brotli_compress')) {
            file_put_contents($file . '.br', brotli_compress($content));
        }
    }
    
    private function generateOptimizedFilename(string $filename): string
    {
        $pathInfo = pathinfo($filename);
        $basename = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? '';
        
        return $basename . '.min.' . $extension;
    }
    
    private function createCSSBundle(string $bundleName, array $cssFiles): array
    {
        $bundleContent = '';
        $originalSize = 0;
        $processedFiles = [];
        
        foreach ($cssFiles as $cssFile) {
            $filePath = $this->optimizedDirectory . '/css/' . $this->generateOptimizedFilename($cssFile);
            
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                $bundleContent .= $content . "\n";
                $originalSize += strlen($content);
                $processedFiles[] = $cssFile;
            }
        }
        
        if (empty($bundleContent)) {
            return ['error' => 'No CSS files found for bundle'];
        }
        
        // Further optimize the bundle
        $bundleContent = $this->minifyCSS($bundleContent);
        
        // Write bundle file
        $bundleFile = $this->optimizedDirectory . '/css/' . $bundleName . '.bundle.css';
        file_put_contents($bundleFile, $bundleContent);
        
        // Create compressed versions
        $this->createCompressedVersions($bundleFile, $bundleContent);
        
        $bundleSize = strlen($bundleContent);
        $savings = $originalSize - $bundleSize;
        $savingsPercent = $originalSize > 0 ? round(($savings / $originalSize) * 100, 2) : 0;
        
        return [
            'bundle_name' => $bundleName . '.bundle.css',
            'files_included' => $processedFiles,
            'original_size' => $originalSize,
            'bundle_size' => $bundleSize,
            'savings' => $savings,
            'savings_percent' => $savingsPercent
        ];
    }
    
    private function createJSBundle(string $bundleName, array $jsFiles): array
    {
        $bundleContent = '';
        $originalSize = 0;
        $processedFiles = [];
        
        foreach ($jsFiles as $jsFile) {
            $filePath = $this->optimizedDirectory . '/js/' . $this->generateOptimizedFilename($jsFile);
            
            if (file_exists($filePath)) {
                $content = file_get_contents($filePath);
                $bundleContent .= $content . ";\n"; // Add semicolon to prevent issues
                $originalSize += strlen($content);
                $processedFiles[] = $jsFile;
            }
        }
        
        if (empty($bundleContent)) {
            return ['error' => 'No JS files found for bundle'];
        }
        
        // Further optimize the bundle
        $bundleContent = $this->minifyJavaScript($bundleContent);
        
        // Write bundle file
        $bundleFile = $this->optimizedDirectory . '/js/' . $bundleName . '.bundle.js';
        file_put_contents($bundleFile, $bundleContent);
        
        // Create compressed versions
        $this->createCompressedVersions($bundleFile, $bundleContent);
        
        $bundleSize = strlen($bundleContent);
        $savings = $originalSize - $bundleSize;
        $savingsPercent = $originalSize > 0 ? round(($savings / $originalSize) * 100, 2) : 0;
        
        return [
            'bundle_name' => $bundleName . '.bundle.js',
            'files_included' => $processedFiles,
            'original_size' => $originalSize,
            'bundle_size' => $bundleSize,
            'savings' => $savings,
            'savings_percent' => $savingsPercent
        ];
    }
    
    private function generateFileHash(string $file): string
    {
        return substr(md5_file($file), 0, 8);
    }
    
    private function generateVersionedFilename(string $filename, string $hash): string
    {
        $pathInfo = pathinfo($filename);
        $basename = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? '';
        $directory = $pathInfo['dirname'] !== '.' ? $pathInfo['dirname'] . '/' : '';
        
        return $directory . $basename . '.' . $hash . '.' . $extension;
    }
    
    private function getMimeType(string $file): string
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            'eot' => 'application/vnd.ms-fontobject'
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
    
    private function updateOptimizationStats(array $results): void
    {
        $totalFiles = 0;
        $totalOriginalSize = 0;
        $totalOptimizedSize = 0;
        
        foreach ($results as $category => $categoryResults) {
            if (is_array($categoryResults)) {
                foreach ($categoryResults as $result) {
                    if (isset($result['original_size']) && isset($result['optimized_size'])) {
                        $totalFiles++;
                        $totalOriginalSize += $result['original_size'];
                        $totalOptimizedSize += $result['optimized_size'];
                    }
                }
            }
        }
        
        $this->optimizationStats = [
            'total_files_processed' => $totalFiles,
            'total_original_size' => $totalOriginalSize,
            'total_optimized_size' => $totalOptimizedSize,
            'total_savings' => $totalOriginalSize - $totalOptimizedSize,
            'last_optimization' => time(),
            'optimization_count' => $this->optimizationStats['optimization_count'] + 1
        ];
    }
    
    private function calculateTotalSavings(array $results): array
    {
        $totalOriginalSize = 0;
        $totalOptimizedSize = 0;
        
        foreach ($results as $category => $categoryResults) {
            if (is_array($categoryResults)) {
                foreach ($categoryResults as $result) {
                    if (isset($result['original_size']) && isset($result['optimized_size'])) {
                        $totalOriginalSize += $result['original_size'];
                        $totalOptimizedSize += $result['optimized_size'];
                    }
                }
            }
        }
        
        $totalSavings = $totalOriginalSize - $totalOptimizedSize;
        $savingsPercent = $totalOriginalSize > 0 ? round(($totalSavings / $totalOriginalSize) * 100, 2) : 0;
        
        return [
            'total_original_size' => $totalOriginalSize,
            'total_optimized_size' => $totalOptimizedSize,
            'total_savings' => $totalSavings,
            'savings_percent' => $savingsPercent,
            'size_reduction' => $this->formatBytes($totalSavings)
        ];
    }
    
    private function getCacheInfo(): array
    {
        return [
            'cache_strategy' => 'file_based_with_versioning',
            'cache_duration' => '1 year',
            'compression_enabled' => function_exists('gzencode'),
            'brotli_enabled' => function_exists('brotli_compress')
        ];
    }
    
    private function getCDNInfo(): array
    {
        return [
            'cdn_enabled' => $this->config->get('assets.cdn.enabled', false),
            'cdn_url' => $this->config->get('assets.cdn.url', ''),
            'cdn_regions' => $this->config->get('assets.cdn.regions', [])
        ];
    }
    
    private function getPerformanceMetrics(): array
    {
        return [
            'optimization_count' => $this->optimizationStats['optimization_count'],
            'last_optimization' => $this->optimizationStats['last_optimization'],
            'total_savings' => $this->formatBytes($this->optimizationStats['total_savings']),
            'average_compression_ratio' => $this->optimizationStats['total_original_size'] > 0 ? 
                round(($this->optimizationStats['total_optimized_size'] / $this->optimizationStats['total_original_size']) * 100, 2) : 0
        ];
    }
    
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen($bytes) - 1) / 3);
        
        return sprintf('%.2f %s', $bytes / pow(1024, $factor), $units[$factor] ?? 'GB');
    }
    
    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir()) {
                rmdir($fileinfo->getRealPath());
            } else {
                unlink($fileinfo->getRealPath());
            }
        }
        
        rmdir($directory);
    }
}
