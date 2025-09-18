<?php
/**
 * Asset Helper - Optimized asset loading utilities
 * 
 * Provides helper functions for loading optimized assets with
 * automatic minification, bundling, and caching.
 */

require_once __DIR__ . '/AssetOptimizer.php';

class AssetHelper {
    private static $assetOptimizer = null;
    
    /**
     * Get asset optimizer instance
     */
    private static function getOptimizer(): AssetOptimizer {
        if (self::$assetOptimizer === null) {
            self::$assetOptimizer = AssetOptimizer::getInstance();
        }
        return self::$assetOptimizer;
    }
    
    /**
     * Load optimized CSS files
     * 
     * @param array $files CSS files to load
     * @param bool $critical Whether this is critical CSS
     * @return string HTML link tags
     */
    public static function loadCSS(array $files, bool $critical = false): string {
        $optimizer = self::getOptimizer();
        $optimizedFile = $optimizer->optimizeCSS($files);
        
        if (!$optimizedFile) {
            // Fallback to individual files
            $html = '';
            foreach ($files as $file) {
                $html .= "<link rel='stylesheet' href='/rfid-checkin/assets/css/{$file}'>\n";
            }
            return $html;
        }
        
        $url = $optimizer->getAssetUrl($optimizedFile);
        $rel = $critical ? 'preload' : 'stylesheet';
        $onload = $critical ? " onload=\"this.onload=null;this.rel='stylesheet'\"" : '';
        
        return "<link rel='{$rel}' href='{$url}'{$onload}>\n";
    }
    
    /**
     * Load optimized JavaScript files
     * 
     * @param array $files JS files to load
     * @param bool $defer Whether to defer loading
     * @return string HTML script tags
     */
    public static function loadJS(array $files, bool $defer = true): string {
        $optimizer = self::getOptimizer();
        $optimizedFile = $optimizer->optimizeJS($files);
        
        if (!$optimizedFile) {
            // Fallback to individual files
            $html = '';
            $deferAttr = $defer ? ' defer' : '';
            foreach ($files as $file) {
                $html .= "<script src='/rfid-checkin/assets/js/{$file}'{$deferAttr}></script>\n";
            }
            return $html;
        }
        
        $url = $optimizer->getAssetUrl($optimizedFile);
        $deferAttr = $defer ? ' defer' : '';
        
        return "<script src='{$url}'{$deferAttr}></script>\n";
    }
    
    /**
     * Generate preload headers for critical assets
     * 
     * @return string HTML preload tags
     */
    public static function generatePreloads(): string {
        $optimizer = self::getOptimizer();
        $preloads = $optimizer->getCriticalAssetPreloads();
        
        $html = '';
        foreach ($preloads as $preload) {
            $html .= "<link rel='{$preload['rel']}' href='{$preload['href']}' as='{$preload['as']}'>\n";
        }
        
        return $html;
    }
    
    /**
     * Optimize and get image URL
     * 
     * @param string $imagePath Path to image
     * @return string Optimized image URL
     */
    public static function optimizeImage(string $imagePath): string {
        $optimizer = self::getOptimizer();
        $optimizedPath = $optimizer->optimizeImage($imagePath);
        
        return '/rfid-checkin/assets/optimized/' . $optimizedPath;
    }
    
    /**
     * Get asset with WebP fallback
     * 
     * @param string $imagePath Path to image
     * @return string HTML picture element with WebP support
     */
    public static function responsiveImage(string $imagePath, string $alt = '', array $attributes = []): string {
        $optimizer = self::getOptimizer();
        $optimizedPath = $optimizer->optimizeImage($imagePath);
        
        // Build attributes string
        $attrString = '';
        foreach ($attributes as $key => $value) {
            $attrString .= " {$key}='{$value}'";
        }
        
        $webpPath = str_replace(pathinfo($optimizedPath, PATHINFO_EXTENSION), 'webp', $optimizedPath);
        $fallbackUrl = '/rfid-checkin/assets/optimized/' . $optimizedPath;
        $webpUrl = '/rfid-checkin/assets/optimized/' . $webpPath;
        
        return "
            <picture>
                <source srcset='{$webpUrl}' type='image/webp'>
                <img src='{$fallbackUrl}' alt='{$alt}'{$attrString}>
            </picture>
        ";
    }
    
    /**
     * Generate critical CSS inline
     * 
     * @param array $files Critical CSS files
     * @return string Inline CSS
     */
    public static function inlineCSS(array $files): string {
        $optimizer = self::getOptimizer();
        $optimizedFile = $optimizer->optimizeCSS($files);
        
        if ($optimizedFile) {
            $cssPath = realpath(__DIR__ . '/../assets/optimized/' . $optimizedFile);
            if (file_exists($cssPath)) {
                $css = file_get_contents($cssPath);
                return "<style>{$css}</style>\n";
            }
        }
        
        return '';
    }
    
    /**
     * Get asset manifest for debugging
     * 
     * @return array Asset manifest data
     */
    public static function getManifest(): array {
        $manifestPath = realpath(__DIR__ . '/../cache/asset-manifest.json');
        if (file_exists($manifestPath)) {
            return json_decode(file_get_contents($manifestPath), true) ?: [];
        }
        return [];
    }
}

// Global helper functions for templates
if (!function_exists('loadCSS')) {
    function loadCSS(array $files, bool $critical = false): string {
        return AssetHelper::loadCSS($files, $critical);
    }
}

if (!function_exists('loadJS')) {
    function loadJS(array $files, bool $defer = true): string {
        return AssetHelper::loadJS($files, $defer);
    }
}

if (!function_exists('optimizeImage')) {
    function optimizeImage(string $imagePath): string {
        return AssetHelper::optimizeImage($imagePath);
    }
}

if (!function_exists('responsiveImage')) {
    function responsiveImage(string $imagePath, string $alt = '', array $attributes = []): string {
        return AssetHelper::responsiveImage($imagePath, $alt, $attributes);
    }
}

if (!function_exists('generatePreloads')) {
    function generatePreloads(): string {
        return AssetHelper::generatePreloads();
    }
}
?>
