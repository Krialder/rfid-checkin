<?php
/**
 * Frontend and Mobile Responsiveness Tests
 * 
 * Tests frontend functionality, CSS responsiveness,
 * JavaScript integration, and mobile compatibility.
 */

class FrontendResponsivenessTest {
    private $testResults = [];
    private $frontendPath;
    private $assetsPath;
    
    public function __construct() {
        $this->frontendPath = __DIR__ . '/../../frontend';
        $this->assetsPath = __DIR__ . '/../../assets';
    }
    
    public function runTests(): array {
        echo "📱 Running Frontend and Mobile Responsiveness Tests...\n";
        
        $this->testFrontendPages();
        $this->testCSSArchitecture();
        $this->testJavaScriptIntegration();
        $this->testResponsiveDesign();
        $this->testNavigationSystem();
        $this->testAssetOptimization();
        $this->testAccessibility();
        
        return $this->testResults;
    }
    
    private function testFrontendPages(): void {
        try {
            $frontendPages = [
                'dashboard.php',
                'events.php',
                'check-ins.php',
                'analytics.php',
                'profile.php',
                'account-settings.php',
                'help.php'
            ];
            
            foreach ($frontendPages as $page) {
                $filePath = $this->frontendPath . '/' . $page;
                $this->assert(file_exists($filePath), "Frontend page exists: {$page}");
                
                if (file_exists($filePath)) {
                    $content = file_get_contents($filePath);
                    
                    // Check for proper HTML structure
                    $this->assert(strpos($content, '<!DOCTYPE html') !== false, "{$page} has proper DOCTYPE");
                    $this->assert(strpos($content, '<html') !== false, "{$page} has HTML tag");
                    $this->assert(strpos($content, '<head>') !== false, "{$page} has head section");
                    $this->assert(strpos($content, '<body>') !== false, "{$page} has body section");
                    
                    // Check for viewport meta tag (responsive design)
                    $this->assert(strpos($content, 'viewport') !== false, "{$page} has viewport meta tag");
                    
                    // Check for navigation inclusion
                    $this->assert(strpos($content, 'navigation.php') !== false, "{$page} includes navigation");
                    
                    // Check for proper PHP structure
                    $this->assert(strpos($content, '<?php') !== false, "{$page} contains PHP code");
                }
            }
            
            echo "  ✅ Frontend pages tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('Frontend Pages', false, $e->getMessage());
        }
    }
    
    private function testCSSArchitecture(): void {
        try {
            $cssFiles = [
                'main.css',
                'navigation.css',
                'dashboard.css',
                'forms.css',
                'modal.css',
                'analytics.css',
                'events.css'
            ];
            
            foreach ($cssFiles as $cssFile) {
                $filePath = $this->assetsPath . '/css/' . $cssFile;
                $this->assert(file_exists($filePath), "CSS file exists: {$cssFile}");
                
                if (file_exists($filePath)) {
                    $content = file_get_contents($filePath);
                    
                    // Check for responsive design patterns
                    $hasMediaQueries = preg_match('/@media\s*\([^)]*\)/', $content);
                    $this->assert($hasMediaQueries, "{$cssFile} contains media queries for responsiveness");
                    
                    // Check for CSS variables (design tokens)
                    $hasVariables = strpos($content, '--') !== false;
                    $this->assert($hasVariables, "{$cssFile} uses CSS custom properties");
                    
                    // Check for mobile-first patterns
                    $hasMobileFirst = preg_match('/@media\s*\([^)]*min-width[^)]*\)/', $content);
                    if ($hasMobileFirst) {
                        $this->assert(true, "{$cssFile} uses mobile-first responsive design");
                    }
                }
            }
            
            echo "  ✅ CSS architecture tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('CSS Architecture', false, $e->getMessage());
        }
    }
    
    private function testJavaScriptIntegration(): void {
        try {
            $jsFiles = [
                'dashboard.js',
                'events.js',
                'login.js',
                'rfid-scanner.js'
            ];
            
            foreach ($jsFiles as $jsFile) {
                $filePath = $this->assetsPath . '/js/' . $jsFile;
                $this->assert(file_exists($filePath), "JavaScript file exists: {$jsFile}");
                
                if (file_exists($filePath)) {
                    $content = file_get_contents($filePath);
                    
                    // Check for modern JavaScript patterns
                    $hasStrictMode = strpos($content, "'use strict'") !== false || strpos($content, '"use strict"') !== false;
                    $this->assert($hasStrictMode, "{$jsFile} uses strict mode");
                    
                    // Check for error handling
                    $hasErrorHandling = strpos($content, 'try') !== false || strpos($content, 'catch') !== false;
                    $this->assert($hasErrorHandling, "{$jsFile} includes error handling");
                    
                    // Check for proper function declarations
                    $hasFunctions = preg_match('/function\s+\w+\s*\(/', $content) || preg_match('/\w+\s*=>\s*/', $content);
                    $this->assert($hasFunctions, "{$jsFile} contains function definitions");
                }
            }
            
            // Test asset helper integration
            $assetHelperPath = __DIR__ . '/../../core/AssetHelper.php';
            $this->assert(file_exists($assetHelperPath), 'AssetHelper exists for optimized loading');
            
            echo "  ✅ JavaScript integration tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('JavaScript Integration', false, $e->getMessage());
        }
    }
    
    private function testResponsiveDesign(): void {
        try {
            // Test main CSS for responsive patterns
            $mainCSSPath = $this->assetsPath . '/css/main.css';
            
            if (file_exists($mainCSSPath)) {
                $content = file_get_contents($mainCSSPath);
                
                // Check for common responsive breakpoints
                $breakpoints = ['768px', '1024px', '1200px'];
                foreach ($breakpoints as $breakpoint) {
                    $hasBreakpoint = strpos($content, $breakpoint) !== false;
                    $this->assert($hasBreakpoint, "Main CSS includes {$breakpoint} breakpoint");
                }
                
                // Check for flexible layouts
                $hasFlexbox = strpos($content, 'display: flex') !== false || strpos($content, 'display:flex') !== false;
                $hasGrid = strpos($content, 'display: grid') !== false || strpos($content, 'display:grid') !== false;
                $this->assert($hasFlexbox || $hasGrid, 'Main CSS uses modern layout methods (flexbox or grid)');
                
                // Check for responsive images
                $hasResponsiveImages = strpos($content, 'max-width: 100%') !== false || strpos($content, 'max-width:100%') !== false;
                $this->assert($hasResponsiveImages, 'Main CSS includes responsive image styles');
            }
            
            // Test navigation responsiveness
            $navCSSPath = $this->assetsPath . '/css/navigation.css';
            if (file_exists($navCSSPath)) {
                $content = file_get_contents($navCSSPath);
                
                // Check for mobile navigation patterns
                $hasMobileNav = strpos($content, 'hamburger') !== false || strpos($content, 'mobile') !== false;
                $this->assert($hasMobileNav, 'Navigation CSS includes mobile-specific styles');
            }
            
            echo "  ✅ Responsive design tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('Responsive Design', false, $e->getMessage());
        }
    }
    
    private function testNavigationSystem(): void {
        try {
            $navigationPath = __DIR__ . '/../../includes/navigation.php';
            $this->assert(file_exists($navigationPath), 'Navigation component exists');
            
            if (file_exists($navigationPath)) {
                $content = file_get_contents($navigationPath);
                
                // Check for role-based navigation
                $hasRoleCheck = strpos($content, 'isAdmin') !== false || strpos($content, 'hasRole') !== false;
                $this->assert($hasRoleCheck, 'Navigation includes role-based access');
                
                // Check for accessibility features
                $hasARIA = strpos($content, 'aria-') !== false;
                $this->assert($hasARIA, 'Navigation includes ARIA accessibility attributes');
                
                // Check for mobile menu
                $hasMobileMenu = strpos($content, 'mobile') !== false || strpos($content, 'hamburger') !== false;
                $this->assert($hasMobileMenu, 'Navigation includes mobile menu functionality');
                
                // Check for user dropdown
                $hasUserDropdown = strpos($content, 'dropdown') !== false;
                $this->assert($hasUserDropdown, 'Navigation includes user dropdown');
            }
            
            echo "  ✅ Navigation system tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('Navigation System', false, $e->getMessage());
        }
    }
    
    private function testAssetOptimization(): void {
        try {
            // Test optimized assets directory
            $optimizedPath = $this->assetsPath . '/optimized';
            $this->assert(is_dir($optimizedPath), 'Optimized assets directory exists');
            
            // Test cache directory
            $cachePath = __DIR__ . '/../../cache/assets';
            $this->assert(is_dir($cachePath), 'Asset cache directory exists');
            
            // Test asset manifest
            $manifestPath = __DIR__ . '/../../cache/asset-manifest.json';
            if (file_exists($manifestPath)) {
                $manifest = json_decode(file_get_contents($manifestPath), true);
                $this->assert(is_array($manifest), 'Asset manifest is valid JSON');
            }
            
            // Test AssetHelper functions
            require_once __DIR__ . '/../../core/AssetHelper.php';
            $this->assert(function_exists('loadCSS'), 'loadCSS helper function exists');
            $this->assert(function_exists('loadJS'), 'loadJS helper function exists');
            $this->assert(function_exists('optimizeImage'), 'optimizeImage helper function exists');
            
            echo "  ✅ Asset optimization tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('Asset Optimization', false, $e->getMessage());
        }
    }
    
    private function testAccessibility(): void {
        try {
            // Test dashboard for accessibility
            $dashboardPath = $this->frontendPath . '/dashboard.php';
            
            if (file_exists($dashboardPath)) {
                $content = file_get_contents($dashboardPath);
                
                // Check for semantic HTML
                $hasSemanticHTML = strpos($content, '<main>') !== false || strpos($content, '<section>') !== false;
                $this->assert($hasSemanticHTML, 'Dashboard uses semantic HTML elements');
                
                // Check for alt attributes on images
                $hasAltText = strpos($content, 'alt=') !== false;
                if (strpos($content, '<img') !== false) {
                    $this->assert($hasAltText, 'Dashboard images have alt attributes');
                }
                
                // Check for proper heading structure
                $hasHeadings = strpos($content, '<h1>') !== false;
                $this->assert($hasHeadings, 'Dashboard has proper heading structure');
            }
            
            // Test navigation accessibility
            $navigationPath = __DIR__ . '/../../includes/navigation.php';
            if (file_exists($navigationPath)) {
                $content = file_get_contents($navigationPath);
                
                // Check for ARIA attributes
                $hasARIA = strpos($content, 'aria-') !== false || strpos($content, 'role=') !== false;
                $this->assert($hasARIA, 'Navigation includes ARIA accessibility attributes');
                
                // Check for keyboard navigation support
                $hasTabIndex = strpos($content, 'tabindex') !== false;
                $hasKeyboardSupport = strpos($content, 'keydown') !== false || strpos($content, 'keyup') !== false;
                $this->assert($hasTabIndex || $hasKeyboardSupport, 'Navigation supports keyboard navigation');
            }
            
            echo "  ✅ Accessibility tests passed\n";
            
        } catch (Exception $e) {
            $this->recordTest('Accessibility', false, $e->getMessage());
        }
    }
    
    private function assert($condition, $message): void {
        if ($condition) {
            $this->recordTest($message, true);
        } else {
            $this->recordTest($message, false);
            throw new Exception("Assertion failed: {$message}");
        }
    }
    
    private function recordTest($name, $passed, $error = null): void {
        $this->testResults[] = [
            'name' => $name,
            'passed' => $passed,
            'error' => $error
        ];
    }
}
?>
