<?php
/**
 * Asset Optimization Pipeline
 * 
 * Handles minification, compression, bundling, and caching of static assets.
 * Supports CSS/JS optimization, image conversion, and CDN integration.
 * 
 * @package RfidCheckin\Core
 * @author Kralder
 */

require_once __DIR__ . '/PerformanceManager.php';
require_once __DIR__ . '/ErrorHandler.php';

class AssetOptimizer {
    private static $instance = null;
    private $performanceManager;
    private $errorHandler;
    private $config;
    private $optimizedAssets = [];
    private $assetManifest = [];
    
    // Optimization settings
    private $settings = [
        'enable_minification' => true,
        'enable_compression' => true,
        'enable_bundling' => true,
        'enable_versioning' => true,
        'enable_webp_conversion' => true,
        'cache_duration' => 31536000, // 1 year
        'compression_level' => 9,
        'image_quality' => 85
    ];
    
    private function __construct() {
        $this->performanceManager = PerformanceManager::getInstance();
        $this->errorHandler = ErrorHandler::getInstance();
        $this->loadConfiguration();
        $this->initializeOptimizer();
    }
    
    public static function getInstance(): AssetOptimizer {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Load configuration settings
     */
    private function loadConfiguration(): void {
        $this->config = [
            'assets_path' => realpath(__DIR__ . '/../assets/'),
            'optimized_path' => realpath(__DIR__ . '/../assets/optimized/'),
            'cache_path' => realpath(__DIR__ . '/../cache/assets/'),
            'manifest_file' => realpath(__DIR__ . '/../cache/asset-manifest.json')
        ];
        
        // Create directories if they don't exist
        foreach (['optimized_path', 'cache_path'] as $path) {
            if (!is_dir($this->config[$path])) {
                mkdir($this->config[$path], 0755, true);
            }
        }
    }
    
    /**
     * Initialize the asset optimizer
     */
    private function initializeOptimizer(): void {
        try {
            // Load existing asset manifest
            $this->loadAssetManifest();
            
            // Set up optimization environment
            $this->setupOptimizationEnvironment();
            
        } catch (Exception $e) {
            $this->errorHandler->log('Asset optimizer initialization failed', $e);
        }
    }
    
    /**
     * Setup optimization environment
     */
    private function setupOptimizationEnvironment(): void {
        // Enable output compression
        if ($this->settings['enable_compression'] && !ob_get_level()) {
            ob_start('ob_gzhandler');
        }
        
        // Set appropriate headers for caching
        $this->setOptimalHeaders();
    }
    
    /**
     * Set optimal caching headers
     */
    private function setOptimalHeaders(): void {
        $cacheTime = $this->settings['cache_duration'];
        
        header("Cache-Control: public, max-age={$cacheTime}, immutable");
        header("Expires: " . gmdate('D, d M Y H:i:s', time() + $cacheTime) . ' GMT');
        header("Pragma: public");
        header("Vary: Accept-Encoding");
    }
    
    /**
     * Load asset manifest
     */
    private function loadAssetManifest(): void {
        if (file_exists($this->config['manifest_file'])) {
            $content = file_get_contents($this->config['manifest_file']);
            $this->assetManifest = json_decode($content, true) ?: [];
        }
    }
    
    /**
     * Save asset manifest
     */
    private function saveAssetManifest(): void {
        file_put_contents(
            $this->config['manifest_file'],
            json_encode($this->assetManifest, JSON_PRETTY_PRINT)
        );
    }
    
    /**
     * Optimize CSS files
     */
    public function optimizeCSS(array $files): string {
        $cacheKey = 'css_bundle_' . md5(implode('|', $files));
        
        // Check if optimized version exists
        if (isset($this->assetManifest[$cacheKey])) {
            $optimizedFile = $this->assetManifest[$cacheKey];
            if (file_exists($this->config['optimized_path'] . '/' . $optimizedFile)) {
                return $optimizedFile;
            }
        }
        
        try {
            $bundledCSS = $this->bundleCSS($files);
            $minifiedCSS = $this->minifyCSS($bundledCSS);
            $optimizedCSS = $this->optimizeCSSContent($minifiedCSS);
            
            // Generate versioned filename
            $version = $this->generateAssetVersion($optimizedCSS);
            $filename = "bundle.{$version}.min.css";
            $filepath = $this->config['optimized_path'] . '/' . $filename;
            
            // Save optimized CSS
            file_put_contents($filepath, $optimizedCSS);
            
            // Update manifest
            $this->assetManifest[$cacheKey] = $filename;
            $this->saveAssetManifest();
            
            return $filename;
            
        } catch (Exception $e) {
            $this->errorHandler->log('CSS optimization failed', $e);
            return $this->fallbackCSS($files);
        }
    }
    
    /**
     * Bundle multiple CSS files
     */
    private function bundleCSS(array $files): string {
        $bundled = "/* Bundled CSS - Generated on " . date('Y-m-d H:i:s') . " */\n\n";
        
        foreach ($files as $file) {
            $filepath = $this->config['assets_path'] . '/css/' . $file;
            
            if (file_exists($filepath)) {
                $content = file_get_contents($filepath);
                $bundled .= "/* Source: {$file} */\n";
                $bundled .= $this->processCSSImports($content, dirname($filepath));
                $bundled .= "\n\n";
            }
        }
        
        return $bundled;
    }
    
    /**
     * Process CSS @import statements
     */
    private function processCSSImports(string $content, string $basePath): string {
        return preg_replace_callback(
            '/@import\s+["\']([^"\']+)["\']\s*;/',
            function($matches) use ($basePath) {
                $importFile = $basePath . '/' . $matches[1];
                if (file_exists($importFile)) {
                    return file_get_contents($importFile);
                }
                return $matches[0];
            },
            $content
        );
    }
    
    /**
     * Minify CSS content
     */
    private function minifyCSS(string $css): string {
        // Remove comments
        $css = preg_replace('/\/\*.*?\*\//s', '', $css);
        
        // Remove unnecessary whitespace
        $css = preg_replace('/\s+/', ' ', $css);
        
        // Remove whitespace around specific characters
        $css = preg_replace('/\s*([{}:;,>+~])\s*/', '$1', $css);
        
        // Remove trailing semicolons
        $css = preg_replace('/;}/', '}', $css);
        
        // Remove empty rules
        $css = preg_replace('/[^{}]*{\s*}/', '', $css);
        
        return trim($css);
    }
    
    /**
     * Optimize CSS content further
     */
    private function optimizeCSSContent(string $css): string {
        // Optimize color values
        $css = preg_replace('/#([0-9a-f])\1([0-9a-f])\2([0-9a-f])\3/i', '#$1$2$3', $css);
        
        // Convert rgb() to hex when shorter
        $css = preg_replace_callback(
            '/rgb\((\d+),\s*(\d+),\s*(\d+)\)/',
            function($matches) {
                $r = dechex((int)$matches[1]);
                $g = dechex((int)$matches[2]);
                $b = dechex((int)$matches[3]);
                return '#' . str_pad($r, 2, '0', STR_PAD_LEFT) . 
                       str_pad($g, 2, '0', STR_PAD_LEFT) . 
                       str_pad($b, 2, '0', STR_PAD_LEFT);
            },
            $css
        );
        
        // Optimize zero values
        $css = preg_replace('/\b0px\b/', '0', $css);
        $css = preg_replace('/\b0em\b/', '0', $css);
        $css = preg_replace('/\b0%\b/', '0', $css);
        
        // Optimize margin/padding shorthand
        $css = preg_replace('/margin:\s*0\s+0\s+0\s+0/', 'margin:0', $css);
        $css = preg_replace('/padding:\s*0\s+0\s+0\s+0/', 'padding:0', $css);
        
        return $css;
    }
    
    /**
     * Optimize JavaScript files
     */
    public function optimizeJS(array $files): string {
        $cacheKey = 'js_bundle_' . md5(implode('|', $files));
        
        // Check if optimized version exists
        if (isset($this->assetManifest[$cacheKey])) {
            $optimizedFile = $this->assetManifest[$cacheKey];
            if (file_exists($this->config['optimized_path'] . '/' . $optimizedFile)) {
                return $optimizedFile;
            }
        }
        
        try {
            $bundledJS = $this->bundleJS($files);
            $minifiedJS = $this->minifyJS($bundledJS);
            
            // Generate versioned filename
            $version = $this->generateAssetVersion($minifiedJS);
            $filename = "bundle.{$version}.min.js";
            $filepath = $this->config['optimized_path'] . '/' . $filename;
            
            // Save optimized JS
            file_put_contents($filepath, $minifiedJS);
            
            // Update manifest
            $this->assetManifest[$cacheKey] = $filename;
            $this->saveAssetManifest();
            
            return $filename;
            
        } catch (Exception $e) {
            $this->errorHandler->log('JavaScript optimization failed', $e);
            return $this->fallbackJS($files);
        }
    }
    
    /**
     * Bundle multiple JavaScript files
     */
    private function bundleJS(array $files): string {
        $bundled = "/* Bundled JavaScript - Generated on " . date('Y-m-d H:i:s') . " */\n\n";
        
        foreach ($files as $file) {
            $filepath = $this->config['assets_path'] . '/js/' . $file;
            
            if (file_exists($filepath)) {
                $content = file_get_contents($filepath);
                $bundled .= "/* Source: {$file} */\n";
                $bundled .= $content;
                
                // Ensure each file ends with a semicolon
                if (!preg_match('/;\s*$/', trim($content))) {
                    $bundled .= ';';
                }
                
                $bundled .= "\n\n";
            }
        }
        
        return $bundled;
    }
    
    /**
     * Minify JavaScript content
     */
    private function minifyJS(string $js): string {
        // Remove single-line comments (but preserve URLs)
        $js = preg_replace('/(?<!:)\/\/.*$/m', '', $js);
        
        // Remove multi-line comments
        $js = preg_replace('/\/\*.*?\*\//s', '', $js);
        
        // Remove unnecessary whitespace
        $js = preg_replace('/\s+/', ' ', $js);
        
        // Remove whitespace around operators and brackets
        $js = preg_replace('/\s*([=+\-*\/{}();,:])\s*/', '$1', $js);
        
        // Remove empty lines
        $js = preg_replace('/^\s*\n/m', '', $js);
        
        return trim($js);
    }
    
    /**
     * Optimize images
     */
    public function optimizeImage(string $imagePath): string {
        $originalPath = $this->config['assets_path'] . '/' . $imagePath;
        
        if (!file_exists($originalPath)) {
            return $imagePath;
        }
        
        $cacheKey = 'image_' . md5($imagePath . filemtime($originalPath));
        
        // Check if optimized version exists
        if (isset($this->assetManifest[$cacheKey])) {
            return $this->assetManifest[$cacheKey];
        }
        
        try {
            $imageInfo = getimagesize($originalPath);
            if (!$imageInfo) {
                return $imagePath;
            }
            
            $optimizedPath = $this->optimizeImageFile($originalPath, $imageInfo);
            
            // Update manifest
            $this->assetManifest[$cacheKey] = $optimizedPath;
            $this->saveAssetManifest();
            
            return $optimizedPath;
            
        } catch (Exception $e) {
            $this->errorHandler->log('Image optimization failed', $e);
            return $imagePath;
        }
    }
    
    /**
     * Optimize individual image file
     */
    private function optimizeImageFile(string $originalPath, array $imageInfo): string {
        $extension = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION));
        $filename = pathinfo($originalPath, PATHINFO_FILENAME);
        
        // Generate optimized filename
        $optimizedFilename = $filename . '_optimized.' . $extension;
        $optimizedPath = $this->config['optimized_path'] . '/' . $optimizedFilename;
        
        // Load original image
        $image = $this->loadImage($originalPath, $imageInfo[2]);
        if (!$image) {
            return basename($originalPath);
        }
        
        // Apply optimizations
        $optimizedImage = $this->applyImageOptimizations($image, $imageInfo);
        
        // Save optimized image
        $this->saveOptimizedImage($optimizedImage, $optimizedPath, $extension);
        
        // Create WebP version if enabled
        if ($this->settings['enable_webp_conversion'] && function_exists('imagewebp')) {
            $webpPath = $this->config['optimized_path'] . '/' . $filename . '_optimized.webp';
            imagewebp($optimizedImage, $webpPath, $this->settings['image_quality']);
        }
        
        imagedestroy($image);
        imagedestroy($optimizedImage);
        
        return $optimizedFilename;
    }
    
    /**
     * Load image resource
     */
    private function loadImage(string $path, int $type) {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($path);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($path);
            default:
                return false;
        }
    }
    
    /**
     * Apply image optimizations
     */
    private function applyImageOptimizations($image, array $imageInfo) {
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        
        // Create optimized image
        $optimized = imagecreatetruecolor($width, $height);
        
        // Preserve transparency for PNG
        if ($imageInfo[2] === IMAGETYPE_PNG) {
            imagealphablending($optimized, false);
            imagesavealpha($optimized, true);
            $transparent = imagecolorallocatealpha($optimized, 0, 0, 0, 127);
            imagefill($optimized, 0, 0, $transparent);
        }
        
        // Copy and resample
        imagecopyresampled($optimized, $image, 0, 0, 0, 0, $width, $height, $width, $height);
        
        return $optimized;
    }
    
    /**
     * Save optimized image
     */
    private function saveOptimizedImage($image, string $path, string $extension): void {
        switch (strtolower($extension)) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($image, $path, $this->settings['image_quality']);
                break;
            case 'png':
                // PNG compression level (0-9)
                $pngQuality = 9 - round(($this->settings['image_quality'] / 100) * 9);
                imagepng($image, $path, $pngQuality);
                break;
            case 'gif':
                imagegif($image, $path);
                break;
        }
    }
    
    /**
     * Generate asset version hash
     */
    private function generateAssetVersion(string $content): string {
        return substr(md5($content), 0, 8);
    }
    
    /**
     * Generate fallback CSS for error cases
     */
    private function fallbackCSS(array $files): string {
        // Return first valid CSS file as fallback
        foreach ($files as $file) {
            if (file_exists($this->config['assets_path'] . '/css/' . $file)) {
                return '../css/' . $file;
            }
        }
        return '';
    }
    
    /**
     * Generate fallback JS for error cases
     */
    private function fallbackJS(array $files): string {
        // Return first valid JS file as fallback
        foreach ($files as $file) {
            if (file_exists($this->config['assets_path'] . '/js/' . $file)) {
                return '../js/' . $file;
            }
        }
        return '';
    }
    
    /**
     * Get asset URL with proper versioning
     */
    public function getAssetUrl(string $asset): string {
        $basePath = '/rfid-checkin/assets/optimized/';
        
        if (isset($this->assetManifest[$asset])) {
            return $basePath . $this->assetManifest[$asset];
        }
        
        return '/rfid-checkin/assets/' . $asset;
    }
    
    /**
     * Preload critical assets
     */
    public function getCriticalAssetPreloads(): array {
        $preloads = [];
        
        // Preload critical CSS
        $criticalCSS = $this->optimizeCSS(['main.css', 'navigation.css']);
        if ($criticalCSS) {
            $preloads[] = [
                'rel' => 'preload',
                'href' => $this->getAssetUrl($criticalCSS),
                'as' => 'style'
            ];
        }
        
        // Preload critical JavaScript
        $criticalJS = $this->optimizeJS(['dashboard.js']);
        if ($criticalJS) {
            $preloads[] = [
                'rel' => 'preload',
                'href' => $this->getAssetUrl($criticalJS),
                'as' => 'script'
            ];
        }
        
        return $preloads;
    }
    
    /**
     * Get optimization statistics
     */
    public function getOptimizationStats(): array {
        $stats = [
            'total_optimized_assets' => count($this->assetManifest),
            'css_bundles' => 0,
            'js_bundles' => 0,
            'optimized_images' => 0,
            'total_size_saved' => 0
        ];
        
        foreach ($this->assetManifest as $key => $file) {
            if (strpos($key, 'css_bundle_') === 0) {
                $stats['css_bundles']++;
            } elseif (strpos($key, 'js_bundle_') === 0) {
                $stats['js_bundles']++;
            } elseif (strpos($key, 'image_') === 0) {
                $stats['optimized_images']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Clear optimization cache
     */
    public function clearCache(): bool {
        try {
            // Clear optimized files
            $files = glob($this->config['optimized_path'] . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            
            // Clear manifest
            $this->assetManifest = [];
            $this->saveAssetManifest();
            
            return true;
            
        } catch (Exception $e) {
            $this->errorHandler->log('Failed to clear asset cache', $e);
            return false;
        }
    }
}
