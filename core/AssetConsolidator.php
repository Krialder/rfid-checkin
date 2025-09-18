<?php
/**
 * Asset Consolidator - CSS/JS Optimization and Deduplication
 * 
 * Consolidates and optimizes CSS and JavaScript files by removing duplicates,
 * minifying content, and creating optimized bundles for better performance.
 * 
 * @package    RFID Check-in System
 * @subpackage Asset Management
 * @version    1.0.0
 * @author     Senior Developer Team
 */

class AssetConsolidator {
    private static $instance = null;
    private $cssFiles = [];
    private $jsFiles = [];
    private $duplicateRules = [];
    private $optimizedAssets = [];
    
    public static function getInstance(): AssetConsolidator {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Analyze CSS files for duplicates and consolidation opportunities
     */
    public function analyzeCSSFiles(): array {
        $cssDirectory = __DIR__ . '/../assets/css/';
        $cssFiles = glob($cssDirectory . '*.css');
        $analysis = [
            'total_files' => count($cssFiles),
            'total_size' => 0,
            'duplicates' => [],
            'consolidation_opportunities' => [],
            'file_details' => []
        ];
        
        foreach ($cssFiles as $file) {
            $content = file_get_contents($file);
            $size = filesize($file);
            $analysis['total_size'] += $size;
            
            $filename = basename($file);
            $analysis['file_details'][$filename] = [
                'size' => $size,
                'rules_count' => $this->countCSSRules($content),
                'selectors' => $this->extractCSSSelectors($content),
                'duplicates_found' => []
            ];
        }
        
        // Find duplicate selectors across files
        $analysis['duplicates'] = $this->findDuplicateSelectors($analysis['file_details']);
        
        return $analysis;
    }
    
    /**
     * Count CSS rules in content
     */
    private function countCSSRules($content): int {
        return preg_match_all('/\{[^}]*\}/', $content);
    }
    
    /**
     * Extract CSS selectors from content
     */
    private function extractCSSSelectors($content): array {
        preg_match_all('/([^{}]+)\s*\{/', $content, $matches);
        return array_map('trim', $matches[1]);
    }
    
    /**
     * Find duplicate selectors across files
     */
    private function findDuplicateSelectors($fileDetails): array {
        $allSelectors = [];
        $duplicates = [];
        
        foreach ($fileDetails as $filename => $details) {
            foreach ($details['selectors'] as $selector) {
                $cleanSelector = $this->normalizeSelector($selector);
                if (isset($allSelectors[$cleanSelector])) {
                    $duplicates[$cleanSelector] = [
                        'selector' => $selector,
                        'files' => array_merge($allSelectors[$cleanSelector], [$filename])
                    ];
                } else {
                    $allSelectors[$cleanSelector] = [$filename];
                }
            }
        }
        
        return array_filter($duplicates, function($duplicate) {
            return count($duplicate['files']) > 1;
        });
    }
    
    /**
     * Normalize selector for comparison
     */
    private function normalizeSelector($selector): string {
        return strtolower(preg_replace('/\s+/', ' ', trim($selector)));
    }
    
    /**
     * Consolidate CSS files
     */
    public function consolidateCSS(): array {
        $analysis = $this->analyzeCSSFiles();
        $consolidatedRules = [];
        $removedDuplicates = 0;
        
        // Create consolidated main CSS with all unique rules
        foreach ($analysis['file_details'] as $filename => $details) {
            $content = file_get_contents(__DIR__ . '/../assets/css/' . $filename);
            
            // Extract unique rules
            preg_match_all('/([^{}]+)\s*\{([^}]*)\}/', $content, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $selector = $this->normalizeSelector($match[1]);
                $rules = trim($match[2]);
                
                if (!isset($consolidatedRules[$selector])) {
                    $consolidatedRules[$selector] = [
                        'original_selector' => trim($match[1]),
                        'rules' => $rules,
                        'sources' => [$filename]
                    ];
                } else {
                    // Merge rules if different
                    if ($consolidatedRules[$selector]['rules'] !== $rules) {
                        $consolidatedRules[$selector]['rules'] = $this->mergeCSS(
                            $consolidatedRules[$selector]['rules'], 
                            $rules
                        );
                    }
                    $consolidatedRules[$selector]['sources'][] = $filename;
                    $removedDuplicates++;
                }
            }
        }
        
        return [
            'consolidated_rules' => $consolidatedRules,
            'removed_duplicates' => $removedDuplicates,
            'size_reduction' => $this->calculateSizeReduction($analysis, $consolidatedRules)
        ];
    }
    
    /**
     * Merge CSS rules intelligently
     */
    private function mergeCSS($existing, $new): string {
        $existingRules = $this->parseCSS($existing);
        $newRules = $this->parseCSS($new);
        
        // New rules override existing ones
        $merged = array_merge($existingRules, $newRules);
        
        return $this->buildCSS($merged);
    }
    
    /**
     * Parse CSS rules into array
     */
    private function parseCSS($css): array {
        $rules = [];
        $declarations = array_filter(array_map('trim', explode(';', $css)));
        
        foreach ($declarations as $declaration) {
            if (strpos($declaration, ':') !== false) {
                list($property, $value) = explode(':', $declaration, 2);
                $rules[trim($property)] = trim($value);
            }
        }
        
        return $rules;
    }
    
    /**
     * Build CSS from rules array
     */
    private function buildCSS($rules): string {
        $css = [];
        foreach ($rules as $property => $value) {
            $css[] = "$property: $value";
        }
        return implode('; ', $css);
    }
    
    /**
     * Calculate size reduction
     */
    private function calculateSizeReduction($analysis, $consolidatedRules): array {
        $originalSize = $analysis['total_size'];
        $estimatedSize = 0;
        
        foreach ($consolidatedRules as $rule) {
            $estimatedSize += strlen($rule['original_selector']) + strlen($rule['rules']) + 5; // +5 for braces and whitespace
        }
        
        return [
            'original_size' => $originalSize,
            'estimated_size' => $estimatedSize,
            'reduction_bytes' => $originalSize - $estimatedSize,
            'reduction_percent' => round((($originalSize - $estimatedSize) / $originalSize) * 100, 2)
        ];
    }
    
    /**
     * Generate consolidated CSS file
     */
    public function generateConsolidatedCSS(): bool {
        $consolidation = $this->consolidateCSS();
        
        $header = "/*!\n";
        $header .= " * RFID Check-in System - Consolidated CSS\n";
        $header .= " * Generated: " . date('Y-m-d H:i:s') . "\n";
        $header .= " * Original files: " . count(glob(__DIR__ . '/../assets/css/*.css')) . "\n";
        $header .= " * Duplicates removed: " . $consolidation['removed_duplicates'] . "\n";
        $header .= " * Size reduction: " . $consolidation['size_reduction']['reduction_percent'] . "%\n";
        $header .= " */\n\n";
        
        $css = $header;
        
        // Add CSS custom properties first
        $css .= ":root {\n";
        $css .= "  /* Consolidated CSS Variables */\n";
        $css .= "  --primary-color: #2563eb;\n";
        $css .= "  --secondary-color: #64748b;\n";
        $css .= "  --success-color: #16a34a;\n";
        $css .= "  --warning-color: #d97706;\n";
        $css .= "  --error-color: #dc2626;\n";
        $css .= "  --text-primary: #1e293b;\n";
        $css .= "  --text-secondary: #475569;\n";
        $css .= "  --bg-primary: #ffffff;\n";
        $css .= "  --bg-secondary: #f8fafc;\n";
        $css .= "  --border-color: #e2e8f0;\n";
        $css .= "  --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1);\n";
        $css .= "  --radius: 0.375rem;\n";
        $css .= "}\n\n";
        
        // Add consolidated rules
        foreach ($consolidation['consolidated_rules'] as $rule) {
            $css .= $rule['original_selector'] . " {\n";
            $css .= "  " . str_replace(';', ";\n  ", $rule['rules']) . "\n";
            $css .= "}\n\n";
        }
        
        $outputFile = __DIR__ . '/../assets/css/consolidated.css';
        return file_put_contents($outputFile, $css) !== false;
    }
    
    /**
     * Analyze JavaScript files for duplicates
     */
    public function analyzeJSFiles(): array {
        $jsDirectory = __DIR__ . '/../assets/js/';
        $jsFiles = glob($jsDirectory . '*.js');
        
        $analysis = [
            'total_files' => count($jsFiles),
            'total_size' => 0,
            'duplicate_functions' => [],
            'consolidation_opportunities' => []
        ];
        
        foreach ($jsFiles as $file) {
            $content = file_get_contents($file);
            $analysis['total_size'] += filesize($file);
            
            // Extract function names
            preg_match_all('/function\s+([a-zA-Z_$][a-zA-Z0-9_$]*)\s*\(/', $content, $matches);
            $functions = $matches[1];
            
            foreach ($functions as $functionName) {
                if (!isset($analysis['duplicate_functions'][$functionName])) {
                    $analysis['duplicate_functions'][$functionName] = [];
                }
                $analysis['duplicate_functions'][$functionName][] = basename($file);
            }
        }
        
        // Filter only actual duplicates
        $analysis['duplicate_functions'] = array_filter(
            $analysis['duplicate_functions'], 
            function($files) { return count($files) > 1; }
        );
        
        return $analysis;
    }
    
    /**
     * Generate optimization report
     */
    public function generateOptimizationReport(): array {
        $cssAnalysis = $this->analyzeCSSFiles();
        $jsAnalysis = $this->analyzeJSFiles();
        
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'css' => [
                'files_analyzed' => $cssAnalysis['total_files'],
                'total_size' => $cssAnalysis['total_size'],
                'duplicate_selectors' => count($cssAnalysis['duplicates']),
                'consolidation_potential' => $this->calculateConsolidationPotential($cssAnalysis)
            ],
            'javascript' => [
                'files_analyzed' => $jsAnalysis['total_files'],
                'total_size' => $jsAnalysis['total_size'],
                'duplicate_functions' => count($jsAnalysis['duplicate_functions']),
                'function_list' => array_keys($jsAnalysis['duplicate_functions'])
            ],
            'recommendations' => $this->generateRecommendations($cssAnalysis, $jsAnalysis)
        ];
        
        return $report;
    }
    
    /**
     * Calculate consolidation potential
     */
    private function calculateConsolidationPotential($cssAnalysis): array {
        $totalDuplicates = count($cssAnalysis['duplicates']);
        $estimatedSavings = $totalDuplicates * 100; // Rough estimate
        
        return [
            'duplicate_rules' => $totalDuplicates,
            'estimated_size_savings' => $estimatedSavings,
            'percentage_reduction' => $cssAnalysis['total_size'] > 0 ? 
                round(($estimatedSavings / $cssAnalysis['total_size']) * 100, 2) : 0
        ];
    }
    
    /**
     * Generate optimization recommendations
     */
    private function generateRecommendations($cssAnalysis, $jsAnalysis): array {
        $recommendations = [];
        
        if (count($cssAnalysis['duplicates']) > 10) {
            $recommendations[] = 'High number of duplicate CSS selectors detected. Consider consolidating stylesheets.';
        }
        
        if (count($jsAnalysis['duplicate_functions']) > 5) {
            $recommendations[] = 'Multiple duplicate JavaScript functions found. Create a shared utilities file.';
        }
        
        if ($cssAnalysis['total_size'] > 500000) { // 500KB
            $recommendations[] = 'CSS files are large. Consider minification and compression.';
        }
        
        if ($jsAnalysis['total_size'] > 1000000) { // 1MB
            $recommendations[] = 'JavaScript files are large. Consider code splitting and lazy loading.';
        }
        
        return $recommendations;
    }
}
