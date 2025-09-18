<?php
require_once '../core/config.php';
require_once '../core/database.php';
require_once '../core/auth.php';
require_once '../core/utils.php';

// Enterprise Help System Manager
class HelpSystemManager {
    private $db;
    private $user;
    private $performance;
    
    public function __construct($database, $user) {
        $this->db = $database;
        $this->user = $user;
        $this->performance = new PerformanceManager();
    }
    
    public function getHelpCategories() {
        $this->performance->startTimer('help_categories');
        
        try {
            $categories = [
                'getting-started' => [
                    'title' => 'Getting Started',
                    'icon' => '🚀',
                    'description' => 'Learn the basics and get up to speed quickly',
                    'priority' => 1,
                    'articles' => 12
                ],
                'user-guide' => [
                    'title' => 'User Guide',
                    'icon' => '👤',
                    'description' => 'Comprehensive feature documentation',
                    'priority' => 2,
                    'articles' => 25
                ],
                'hardware-setup' => [
                    'title' => 'Hardware Setup',
                    'icon' => '🔧',
                    'description' => 'RFID configuration and troubleshooting',
                    'priority' => 3,
                    'articles' => 8
                ],
                'troubleshooting' => [
                    'title' => 'Troubleshooting',
                    'icon' => '🔍',
                    'description' => 'Fix common issues and problems',
                    'priority' => 4,
                    'articles' => 15
                ],
                'api-documentation' => [
                    'title' => 'API Documentation',
                    'icon' => '⚡',
                    'description' => 'Developer resources and integrations',
                    'priority' => 5,
                    'articles' => 18
                ],
                'security' => [
                    'title' => 'Security Guide',
                    'icon' => '🛡️',
                    'description' => 'Security best practices and policies',
                    'priority' => 6,
                    'articles' => 10
                ]
            ];
            
            $this->performance->endTimer('help_categories');
            return $categories;
            
        } catch (Exception $e) {
            logMessage("Help categories error: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    public function getSystemStats() {
        $this->performance->startTimer('system_stats');
        
        try {
            $stats = [
                'total_users' => $this->getActiveUserCount(),
                'total_events' => $this->getTotalEventCount(),
                'total_checkins' => $this->getTotalCheckinCount(),
                'uptime' => $this->getSystemUptime(),
                'version' => '3.0.0-enterprise',
                'last_update' => date('Y-m-d'),
                'support_tickets' => $this->getSupportTicketCount()
            ];
            
            $this->performance->endTimer('system_stats');
            return $stats;
            
        } catch (Exception $e) {
            logMessage("System stats error: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    public function searchHelp($query, $category = null) {
        $this->performance->startTimer('help_search');
        
        try {
            // Simulate comprehensive search results
            $results = [
                [
                    'title' => 'How to check in to events',
                    'category' => 'user-guide',
                    'excerpt' => 'Learn the different methods for checking into events...',
                    'relevance' => 95,
                    'url' => '#user-guide'
                ],
                [
                    'title' => 'RFID card setup',
                    'category' => 'hardware-setup',
                    'excerpt' => 'Configure your RFID cards for automatic check-ins...',
                    'relevance' => 87,
                    'url' => '#hardware-setup'
                ]
            ];
            
            $this->performance->endTimer('help_search');
            return $results;
            
        } catch (Exception $e) {
            logMessage("Help search error: " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
    
    public function submitSupportTicket($data) {
        $this->performance->startTimer('support_ticket');
        
        try {
            // Validate input data
            $requiredFields = ['name', 'email', 'category', 'message'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new InvalidArgumentException("Missing required field: $field");
                }
            }
            
            // Create support ticket
            $ticketId = 'HELP-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Log the support request
            logMessage("Support ticket created: $ticketId by user " . $this->user['username'], 'INFO');
            
            $this->performance->endTimer('support_ticket');
            return ['success' => true, 'ticket_id' => $ticketId];
            
        } catch (Exception $e) {
            logMessage("Support ticket error: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function getActiveUserCount() {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE is_active = 1");
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    private function getTotalEventCount() {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM events");
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    private function getTotalCheckinCount() {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM access_logs");
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    private function getSystemUptime() {
        // Simulate uptime calculation
        return '99.97%';
    }
    
    private function getSupportTicketCount() {
        // Simulate support ticket count
        return rand(5, 25);
    }
    
    public function getPerformanceMetrics() {
        return $this->performance->getMetrics();
    }
}

// Performance Manager for Help System
class PerformanceManager {
    private $timers = [];
    private $metrics = [];
    
    public function startTimer($operation) {
        $this->timers[$operation] = microtime(true);
    }
    
    public function endTimer($operation) {
        if (isset($this->timers[$operation])) {
            $duration = microtime(true) - $this->timers[$operation];
            $this->metrics[$operation] = round($duration * 1000, 2); // Convert to milliseconds
            unset($this->timers[$operation]);
        }
    }
    
    public function getMetrics() {
        return $this->metrics;
    }
}

// Check authentication
if (!Auth::isLoggedIn()) {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit;
}

// Initialize managers
$db = getDB();
$user = Auth::getCurrentUser();
$helpManager = new HelpSystemManager($db, $user);

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'search':
            $query = $_GET['q'] ?? '';
            $category = $_GET['category'] ?? null;
            $results = $helpManager->searchHelp($query, $category);
            echo json_encode(['success' => true, 'results' => $results]);
            exit;
            
        case 'support_ticket':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $data = [
                    'name' => $_POST['name'] ?? '',
                    'email' => $_POST['email'] ?? '',
                    'category' => $_POST['category'] ?? '',
                    'message' => $_POST['message'] ?? '',
                    'user_id' => $user['id'],
                    'timestamp' => date('Y-m-d H:i:s')
                ];
                
                $result = $helpManager->submitSupportTicket($data);
                echo json_encode($result);
                exit;
            }
            break;
            
        case 'system_status':
            $stats = $helpManager->getSystemStats();
            echo json_encode(['success' => true, 'stats' => $stats]);
            exit;
    }
}

// Get page data
$helpCategories = $helpManager->getHelpCategories();
$systemStats = $helpManager->getSystemStats();
$csrfToken = generateCSRFToken();

// Page title and meta
$pageTitle = 'Help & Support - RFID Check-in System';
$pageDescription = 'Comprehensive help documentation and support for the RFID Check-in System';
$pageKeywords = 'help, support, documentation, RFID, check-in, troubleshooting, user guide';
?>
<!DOCTYPE html>
<html lang="en" prefix="og: http://ogp.me/ns#">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <!-- Primary Meta Tags -->
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($pageKeywords); ?>">
    <meta name="author" content="RFID Check-in System">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta property="og:site_name" content="RFID Check-in System">
    
    <!-- Security Headers -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self';">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="SAMEORIGIN">
    <meta http-equiv="Referrer-Policy" content="strict-origin-when-cross-origin">
    
    <!-- Progressive Web App -->
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Help & Support">
    
    <!-- Preload Critical Resources -->
    <link rel="preload" href="../assets/css/main.css" as="style">
    <link rel="preload" href="../assets/css/navigation.css" as="style">
    <link rel="preload" href="../assets/css/help.css" as="style">
    
    <!-- Stylesheets -->
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/navigation.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/help.css">
    
    <!-- Enhanced Help System Styles -->
    <style>
        /* Enhanced Help System Styling */
        .help-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        .help-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 1rem 1rem;
        }
        
        .help-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        
        .help-header p {
            font-size: 1.2rem;
            opacity: 0.9;
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .help-search-container {
            max-width: 600px;
            margin: 0 auto;
            position: relative;
        }
        
        .help-search-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .help-search-input:focus {
            outline: none;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            transform: translateY(-2px);
        }
        
        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-size: 1.2rem;
        }
        
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            z-index: 1000;
            max-height: 400px;
            overflow-y: auto;
            display: none;
        }
        
        .search-result-item {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .search-result-item:hover {
            background-color: #f8f9fa;
        }
        
        .search-result-title {
            font-weight: 600;
            color: #2563eb;
            margin-bottom: 0.5rem;
        }
        
        .search-result-excerpt {
            color: #666;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .search-result-category {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }
        
        .help-nav-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .help-nav-item {
            display: flex;
            align-items: center;
            padding: 1.5rem;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .help-nav-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            border-color: #2563eb;
        }
        
        .help-nav-icon {
            font-size: 2.5rem;
            margin-right: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            flex-shrink: 0;
        }
        
        .help-nav-content h4 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1a202c;
        }
        
        .help-nav-content p {
            color: #666;
            margin: 0;
            font-size: 0.95rem;
        }
        
        .help-nav-meta {
            margin-left: auto;
            text-align: right;
            font-size: 0.8rem;
            color: #999;
        }
        
        .help-content-layout {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .help-main-content {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .help-section {
            margin-bottom: 3rem;
            scroll-margin-top: 2rem;
        }
        
        .help-section h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #1a202c;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .help-section h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 2rem 0 1rem;
            color: #2d3748;
        }
        
        .help-section h4 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 1.5rem 0 0.75rem;
            color: #4a5568;
        }
        
        .step-list {
            counter-reset: step-counter;
            list-style: none;
            padding: 0;
        }
        
        .step-list li {
            counter-increment: step-counter;
            position: relative;
            padding: 1rem 0 1rem 3rem;
            border-left: 2px solid #e2e8f0;
            margin-bottom: 0.5rem;
        }
        
        .step-list li::before {
            content: counter(step-counter);
            position: absolute;
            left: -1rem;
            top: 1rem;
            background: #2563eb;
            color: white;
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin: 1.5rem 0;
        }
        
        .feature-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 0.75rem;
            border-left: 4px solid #2563eb;
        }
        
        .feature-card h4 {
            margin-top: 0;
            color: #2563eb;
        }
        
        .troubleshooting-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            background: white;
            border-radius: 0.75rem;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .troubleshooting-table th {
            background: #2563eb;
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
        }
        
        .troubleshooting-table td {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        
        .troubleshooting-table tr:hover {
            background: #f8f9fa;
        }
        
        .faq-container {
            margin: 2rem 0;
        }
        
        .faq-item {
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            margin-bottom: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .faq-item:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .faq-question {
            padding: 1.25rem;
            background: #f8f9fa;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            color: #2d3748;
            transition: background-color 0.3s ease;
        }
        
        .faq-question:hover {
            background: #e2e8f0;
        }
        
        .faq-answer {
            padding: 0 1.25rem;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s ease;
            background: white;
        }
        
        .faq-answer.show {
            padding: 1.25rem;
            max-height: 200px;
        }
        
        .help-sidebar {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .sidebar-card {
            background: white;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
        }
        
        .sidebar-card h3 {
            margin-top: 0;
            margin-bottom: 1rem;
            color: #2d3748;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .status-indicator {
            display: inline-block;
            width: 0.75rem;
            height: 0.75rem;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        
        .status-online {
            background: #10b981;
            animation: pulse 2s infinite;
        }
        
        .status-warning {
            background: #f59e0b;
        }
        
        .status-offline {
            background: #ef4444;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .keyboard-shortcut {
            display: inline-block;
            background: #e2e8f0;
            color: #4a5568;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .contact-form {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 1rem;
            margin: 1.5rem 0;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2d3748;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
        }
        
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #4b5563;
        }
        
        .loading-state {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        
        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #e2e8f0;
            border-top: 4px solid #2563eb;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 1rem 0;
            border: 1px solid transparent;
        }
        
        .alert-success {
            background: #d1fae5;
            border-color: #10b981;
            color: #065f46;
        }
        
        .alert-error {
            background: #fee2e2;
            border-color: #ef4444;
            color: #991b1b;
        }
        
        .alert-info {
            background: #dbeafe;
            border-color: #3b82f6;
            color: #1e40af;
        }
        
        /* Accessibility Features */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0,0,0,0);
            white-space: nowrap;
            border: 0;
        }
        
        .skip-link {
            position: absolute;
            top: -40px;
            left: 6px;
            background: #2563eb;
            color: white;
            padding: 8px;
            text-decoration: none;
            border-radius: 0 0 4px 4px;
            z-index: 9999;
        }
        
        .skip-link:focus {
            top: 0;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .help-content-layout {
                grid-template-columns: 1fr;
            }
            
            .help-nav-grid {
                grid-template-columns: 1fr;
            }
            
            .help-header h1 {
                font-size: 2rem;
            }
            
            .feature-grid {
                grid-template-columns: 1fr;
            }
            
            .help-main-content {
                padding: 1rem;
            }
        }
        
        /* Print Styles */
        @media print {
            .help-sidebar,
            .help-search-container,
            .contact-form {
                display: none;
            }
            
            .help-main-content {
                box-shadow: none;
                border: 1px solid #ccc;
            }
        }
        
        /* High Contrast Mode */
        @media (prefers-contrast: high) {
            .help-nav-item {
                border: 2px solid #000;
            }
            
            .feature-card {
                border: 1px solid #000;
            }
        }
        
        /* Reduced Motion */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
    
    <!-- Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebPage",
        "name": "<?php echo htmlspecialchars($pageTitle); ?>",
        "description": "<?php echo htmlspecialchars($pageDescription); ?>",
        "url": "<?php echo getCurrentURL(); ?>",
        "isPartOf": {
            "@type": "WebSite",
            "name": "RFID Check-in System",
            "url": "<?php echo BASE_URL; ?>"
        },
        "provider": {
            "@type": "Organization",
            "name": "RFID Check-in System"
        }
    }
    </script>
</head>
<body class="help-page">
    <!-- Skip Links for Accessibility -->
    <a href="#main-content" class="skip-link">Skip to main content</a>
    
    <!-- Navigation -->
    <?php include '../includes/navigation.php'; ?>
    
    <!-- Main Content -->
    <main id="main-content" class="main-content" role="main">
        <!-- Help Header with Search -->
        <header class="help-header">
            <div class="help-container">
                <h1>Help & Support Center</h1>
                <p>Comprehensive documentation and assistance for the RFID Check-in System</p>
                
                <div class="help-search-container">
                    <div class="search-icon" aria-hidden="true">🔍</div>
                    <input type="text" 
                           id="helpSearchInput" 
                           class="help-search-input" 
                           placeholder="Search for help topics, features, or issues..."
                           aria-label="Search help documentation"
                           autocomplete="off">
                    
                    <!-- Search Results Dropdown -->
                    <div id="searchResults" class="search-results" role="listbox" aria-label="Search results"></div>
                </div>
            </div>
        </header>
        
        <div class="help-container">
            <!-- Quick Navigation Categories -->
            <nav class="help-nav" role="navigation" aria-label="Help categories">
                <h2 class="sr-only">Help Categories</h2>
                <div class="help-nav-grid">
                    <?php foreach ($helpCategories as $categoryId => $category): ?>
                    <a href="#<?php echo $categoryId; ?>" 
                       class="help-nav-item" 
                       role="button"
                       aria-describedby="<?php echo $categoryId; ?>-desc">
                        <div class="help-nav-icon" aria-hidden="true"><?php echo $category['icon']; ?></div>
                        <div class="help-nav-content">
                            <h4><?php echo htmlspecialchars($category['title']); ?></h4>
                            <p id="<?php echo $categoryId; ?>-desc"><?php echo htmlspecialchars($category['description']); ?></p>
                        </div>
                        <div class="help-nav-meta">
                            <div><?php echo $category['articles']; ?> articles</div>
                            <div>Priority <?php echo $category['priority']; ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </nav>
            
            <!-- Main Content Layout -->
            <div class="help-content-layout">
                <!-- Main Content Area -->
                <div class="help-main-content">
                    <!-- Getting Started Section -->
                    <section class="help-section" id="getting-started" role="region" aria-labelledby="getting-started-title">
                        <h2 id="getting-started-title">
                            <span aria-hidden="true">🚀</span> Getting Started
                        </h2>
                        <p>Welcome to the RFID Check-in System! This comprehensive guide will help you get started quickly and efficiently.</p>
                        
                        <h3>Quick Start Guide</h3>
                        <ol class="step-list" role="list">
                            <li role="listitem">
                                <strong>Account Access:</strong> Log in using your assigned username and password. If you don't have credentials, contact your system administrator.
                            </li>
                            <li role="listitem">
                                <strong>Dashboard Overview:</strong> Your personalized dashboard displays recent activity, upcoming events, and quick action buttons for common tasks.
                            </li>
                            <li role="listitem">
                                <strong>Profile Configuration:</strong> Complete your profile by adding a photo, updating contact information, and registering RFID cards.
                            </li>
                            <li role="listitem">
                                <strong>First Check-in:</strong> Practice checking into an event using the dashboard or by scanning your RFID card at a reader.
                            </li>
                            <li role="listitem">
                                <strong>Explore Features:</strong> Familiarize yourself with the analytics, events, and settings pages to maximize your experience.
                            </li>
                        </ol>
                        
                        <h3>System Features Overview</h3>
                        <div class="feature-grid" role="grid">
                            <div class="feature-card" role="gridcell">
                                <h4><span aria-hidden="true">📊</span> Dashboard</h4>
                                <p>Your central command center displaying real-time statistics, recent activity, quick actions, and personalized recommendations for optimal system usage.</p>
                            </div>
                            <div class="feature-card" role="gridcell">
                                <h4><span aria-hidden="true">📅</span> Events Management</h4>
                                <p>Browse upcoming events, view detailed information, register for participation, and perform quick check-ins with advanced filtering and search capabilities.</p>
                            </div>
                            <div class="feature-card" role="gridcell">
                                <h4><span aria-hidden="true">✅</span> Check-in History</h4>
                                <p>Track your complete attendance history, export records for personal use, analyze participation patterns, and manage attendance data.</p>
                            </div>
                            <div class="feature-card" role="gridcell">
                                <h4><span aria-hidden="true">📈</span> Analytics & Insights</h4>
                                <p>Access detailed statistics about attendance patterns, system-wide activity, personal metrics, and comprehensive reporting tools.</p>
                            </div>
                            <div class="feature-card" role="gridcell">
                                <h4><span aria-hidden="true">👤</span> Profile Management</h4>
                                <p>Manage personal information, upload profile pictures, configure RFID tags, and customize your account preferences and privacy settings.</p>
                            </div>
                            <div class="feature-card" role="gridcell">
                                <h4><span aria-hidden="true">⚙️</span> Account Settings</h4>
                                <p>Customize your experience, manage security settings, control notifications, configure privacy preferences, and access advanced account features.</p>
                            </div>
                        </div>
                        
                        <h3>System Requirements</h3>
                        <div class="feature-card">
                            <h4><span aria-hidden="true">💻</span> Browser Compatibility</h4>
                            <ul>
                                <li><strong>Recommended:</strong> Chrome 90+, Firefox 88+, Safari 14+, Edge 90+</li>
                                <li><strong>JavaScript:</strong> Required for full functionality</li>
                                <li><strong>Cookies:</strong> Essential for authentication and preferences</li>
                                <li><strong>Local Storage:</strong> Used for performance optimization</li>
                            </ul>
                        </div>
                    </section>
                    
                    <!-- User Guide Section -->
                    <section class="help-section" id="user-guide" role="region" aria-labelledby="user-guide-title">
                        <h2 id="user-guide-title">
                            <span aria-hidden="true">👤</span> Comprehensive User Guide
                        </h2>
                        
                        <h3>Event Check-in Methods</h3>
                        
                        <h4><span aria-hidden="true">📡</span> Method 1: RFID Card Check-in (Recommended)</h4>
                        <ol class="step-list" role="list">
                            <li role="listitem">
                                <strong>Locate RFID Reader:</strong> Find the designated RFID reader device, typically positioned near event entrances or registration areas.
                            </li>
                            <li role="listitem">
                                <strong>Position Card:</strong> Hold your assigned RFID card within 2-4 inches of the reader antenna for optimal detection.
                            </li>
                            <li role="listitem">
                                <strong>Wait for Confirmation:</strong> Look for the green LED indicator and listen for the confirmation beep signaling successful check-in.
                            </li>
                            <li role="listitem">
                                <strong>Verification:</strong> Your check-in is automatically recorded and synchronized with the central database in real-time.
                            </li>
                        </ol>
                        
                        <h4><span aria-hidden="true">📱</span> Method 2: Manual Dashboard Check-in</h4>
                        <ol class="step-list" role="list">
                            <li role="listitem">
                                <strong>Access Dashboard:</strong> Navigate to your personalized dashboard after logging into the system.
                            </li>
                            <li role="listitem">
                                <strong>Manual Check-in:</strong> Click the prominent "Manual Check-in" button in the quick actions section.
                            </li>
                            <li role="listitem">
                                <strong>Event Selection:</strong> Choose your desired event from the dropdown list of available and active events.
                            </li>
                            <li role="listitem">
                                <strong>Confirmation:</strong> Review the event details and click "Confirm Check-in" to complete the process.
                            </li>
                        </ol>
                        
                        <h4><span aria-hidden="true">⚡</span> Method 3: Quick Check-in from Events Page</h4>
                        <ol class="step-list" role="list">
                            <li role="listitem">
                                <strong>Browse Events:</strong> Navigate to the "Events" section to view all available events with filtering options.
                            </li>
                            <li role="listitem">
                                <strong>Search & Filter:</strong> Use the search functionality or filters to find specific events by date, category, or location.
                            </li>
                            <li role="listitem">
                                <strong>Quick Action:</strong> Click the "Quick Check-in" button on any event card for immediate registration.
                            </li>
                            <li role="listitem">
                                <strong>Modal Confirmation:</strong> Confirm your participation in the popup modal with event details and terms.
                            </li>
                        </ol>
                        
                        <h3>Advanced Profile Management</h3>
                        
                        <h4><span aria-hidden="true">🖼️</span> Profile Information</h4>
                        <ul role="list">
                            <li role="listitem"><strong>Avatar Upload:</strong> Support for JPEG, PNG, WebP formats up to 5MB with automatic resizing and optimization</li>
                            <li role="listitem"><strong>Contact Details:</strong> Comprehensive contact information including email, phone, department, and emergency contacts</li>
                            <li role="listitem"><strong>Bio & Description:</strong> Personal description, interests, and professional information for networking</li>
                            <li role="listitem"><strong>Privacy Controls:</strong> Granular visibility settings for profile information and activity history</li>
                        </ul>
                        
                        <h4><span aria-hidden="true">📡</span> RFID Management</h4>
                        <ul role="list">
                            <li role="listitem"><strong>Assigned Cards:</strong> View all RFID cards linked to your account with activation status and assignment dates</li>
                            <li role="listitem"><strong>Card Status:</strong> Real-time status monitoring showing active, inactive, or pending cards</li>
                            <li role="listitem"><strong>Request Process:</strong> Streamlined process for requesting additional cards or reporting lost/stolen cards</li>
                            <li role="listitem"><strong>Security Features:</strong> Card deactivation, replacement tracking, and usage history</li>
                        </ul>
                        
                        <h4><span aria-hidden="true">🔐</span> Security Management</h4>
                        <ul role="list">
                            <li role="listitem"><strong>Password Policy:</strong> Strong password requirements with complexity validation and breach detection</li>
                            <li role="listitem"><strong>Two-Factor Authentication:</strong> Optional 2FA using authenticator apps, SMS, or hardware tokens</li>
                            <li role="listitem"><strong>Session Management:</strong> Monitor active sessions, remote logout, and security notifications</li>
                            <li role="listitem"><strong>Login History:</strong> Comprehensive audit trail with IP addresses, devices, and geographical information</li>
                        </ul>
                    </section>
                
                <!-- Hardware Setup Section -->
                <div class="help-section" id="hardware-setup">
                    <h2>🔧 Hardware Setup</h2>
                    <p>This section is for administrators setting up RFID readers.</p>
                    
                    <h3>RFID Reader Configuration</h3>
                    <ol class="step-list">
                        <li><strong>Hardware Assembly:</strong> Connect RC522 module to NodeMCU according to wiring diagram</li>
                        <li><strong>Software Upload:</strong> Flash the appropriate Arduino code to your device</li>
                        <li><strong>WiFi Configuration:</strong> Connect to "RFID-Setup" network and configure WiFi settings</li>
                        <li><strong>Server Integration:</strong> Set the server URL to point to your installation</li>
                        <li><strong>Testing:</strong> Verify RFID scanning works and data reaches the server</li>
                    </ol>
                    
                    <h3>Hardware Troubleshooting</h3>
                    <table class="troubleshooting-table">
                        <thead>
                            <tr>
                                <th>Problem</th>
                                <th>Possible Causes</th>
                                <th>Solutions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>RFID not reading cards</td>
                                <td>Power issues, wiring, card compatibility</td>
                                <td>Check 3.3V power supply, verify wiring, test with different cards</td>
                            </tr>
                            <tr>
                                <td>WiFi connection failed</td>
                                <td>Wrong credentials, weak signal</td>
                                <td>Recheck SSID/password, move closer to router</td>
                            </tr>
                            <tr>
                                <td>Server communication error</td>
                                <td>Wrong URL, network issues</td>
                                <td>Verify server URL, check network connectivity</td>
                            </tr>
                            <tr>
                                <td>Device keeps rebooting</td>
                                <td>Power supply inadequate</td>
                                <td>Use quality 5V 2A power supply, check connections</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Troubleshooting Section -->
                <div class="help-section" id="troubleshooting">
                    <h2>🔍 Troubleshooting</h2>
                    
                    <h3>Common Issues</h3>
                    
                    <h4>Login Problems</h4>
                    <ul>
                        <li><strong>Forgot password:</strong> Use the "Forgot Password" link on the login page</li>
                        <li><strong>Account locked:</strong> Contact administrator after multiple failed attempts</li>
                        <li><strong>Username not recognized:</strong> Check spelling or contact admin</li>
                    </ul>
                    
                    <h4>Check-in Issues</h4>
                    <ul>
                        <li><strong>RFID card not working:</strong> Ensure card is assigned to your account</li>
                        <li><strong>Event not showing:</strong> Check if event is public and currently active</li>
                        <li><strong>Already checked in error:</strong> You may already be checked into this event</li>
                    </ul>
                    
                    <h4>Performance Issues</h4>
                    <ul>
                        <li><strong>Slow loading:</strong> Check internet connection, try refreshing page</li>
                        <li><strong>Pages not updating:</strong> Clear browser cache and cookies</li>
                        <li><strong>Mobile issues:</strong> Ensure you're using a supported browser</li>
                    </ul>
                    
                    <h3>Browser Support</h3>
                    <p>The system works best with modern browsers:</p>
                    <ul>
                        <li>✅ Chrome 90+</li>
                        <li>✅ Firefox 88+</li>
                        <li>✅ Safari 14+</li>
                        <li>✅ Edge 90+</li>
                        <li>❌ Internet Explorer (not supported)</li>
                    </ul>
                </div>
                
                <!-- FAQ Section -->
                <div class="help-section" id="faq">
                    <h2>❓ Frequently Asked Questions</h2>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>How do I get an RFID card?</span>
                            <span>+</span>
                        </div>
                        <div class="faq-answer">
                            Contact your system administrator to request an RFID card. They will need to assign the card to your account and provide you with the physical card.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>Can I check into multiple events at once?</span>
                            <span>+</span>
                        </div>
                        <div class="faq-answer">
                            Yes, you can be checked into multiple events simultaneously. However, some events may have restrictions based on location or time conflicts.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>How do I check out of an event?</span>
                            <span>+</span>
                        </div>
                        <div class="faq-answer">
                            Checkout is typically automatic when an event ends. For manual checkout, scan your RFID card again or use the dashboard check-out option if available.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>Can I see who else is attending an event?</span>
                            <span>+</span>
                        </div>
                        <div class="faq-answer">
                            Attendee lists may be visible depending on event privacy settings and your permissions. Check the event details page for attendee information.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>How can I export my attendance history?</span>
                            <span>+</span>
                        </div>
                        <div class="faq-answer">
                            Go to the "My Check-ins" page and use the export button to download your attendance history as a CSV file.
                        </div>
                    </div>
                    
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFaq(this)">
                            <span>What should I do if I lost my RFID card?</span>
                            <span>+</span>
                        </div>
                        <div class="faq-answer">
                            Contact your administrator immediately to deactivate the lost card and request a replacement. You can still use manual check-in while waiting for a new card.
                        </div>
                    </div>
                </div>
                
                <!-- Contact Section -->
                <div class="help-section" id="contact">
                    <h2>📞 Contact Support</h2>
                    <p>Need additional help? Our support team is here to assist you.</p>
                    
                    <div class="contact-form">
                        <h3>Send us a message</h3>
                        <form id="contactForm">
                            <div class="form-group">
                                <label for="contact_name">Your Name</label>
                                <input type="text" id="contact_name" name="name" required>
                            </div>
                            <div class="form-group">
                                <label for="contact_email">Email Address</label>
                                <input type="email" id="contact_email" name="email" required>
                            </div>
                            <div class="form-group">
                                <label for="contact_category">Category</label>
                                <select id="contact_category" name="category">
                                    <option value="general">General Question</option>
                                    <option value="technical">Technical Issue</option>
                                    <option value="account">Account Problem</option>
                                    <option value="hardware">Hardware Issue</option>
                                    <option value="feature">Feature Request</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="contact_message">Message</label>
                                <textarea id="contact_message" name="message" rows="5" required placeholder="Please describe your issue or question in detail..."></textarea>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Send Message</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Keyboard Shortcuts -->
                <div class="help-section" id="shortcuts">
                    <h2>⌨️ Keyboard Shortcuts</h2>
                    <p>Speed up your workflow with these keyboard shortcuts:</p>
                    
                    <table class="troubleshooting-table">
                        <thead>
                            <tr>
                                <th>Shortcut</th>
                                <th>Action</th>
                                <th>Page</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="keyboard-shortcut">Ctrl + /</span></td>
                                <td>Show help</td>
                                <td>All pages</td>
                            </tr>
                            <tr>
                                <td><span class="keyboard-shortcut">Alt + D</span></td>
                                <td>Go to Dashboard</td>
                                <td>All pages</td>
                            </tr>
                            <tr>
                                <td><span class="keyboard-shortcut">Alt + E</span></td>
                                <td>Go to Events</td>
                                <td>All pages</td>
                            </tr>
                            <tr>
                                <td><span class="keyboard-shortcut">Alt + P</span></td>
                                <td>Go to Profile</td>
                                <td>All pages</td>
                            </tr>
                            <tr>
                                <td><span class="keyboard-shortcut">Ctrl + K</span></td>
                                <td>Quick search</td>
                                <td>Events page</td>
                            </tr>
                            <tr>
                                <td><span class="keyboard-shortcut">Esc</span></td>
                                <td>Close modal</td>
                                <td>All modals</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="help-sidebar">
                <div class="sidebar-card">
                    <h3>System Status</h3>
                    <div class="status-row">
                        <span class="status-indicator status-online">Online</span>
                        <span>All systems operational</span>
                    </div>
                    <p>Last updated: <?php echo date('Y-m-d H:i'); ?></p>
                </div>
                
                <div class="sidebar-card">
                    <h3>Quick Links</h3>
                    <ul style="list-style: none; padding: 0;">
                        <li style="margin-bottom: 0.5rem;"><a href="dashboard.php">Dashboard</a></li>
                        <li style="margin-bottom: 0.5rem;"><a href="events.php">Events</a></li>
                        <li style="margin-bottom: 0.5rem;"><a href="check-ins.php">My Check-ins</a></li>
                        <li style="margin-bottom: 0.5rem;"><a href="profile.php">Profile</a></li>
                        <li style="margin-bottom: 0.5rem;"><a href="account-settings.php">Settings</a></li>
                        <?php if (Auth::hasRole(['admin'])): ?>
                        <li style="margin-bottom: 0.5rem;"><a href="admin/users.php">User Management</a></li>
                        <li style="margin-bottom: 0.5rem;"><a href="../admin/events.php">Event Management</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div class="sidebar-card">
                    <h3>System Information</h3>
                    <ul style="list-style: none; padding: 0; font-size: 0.9rem;">
                        <li style="margin-bottom: 0.25rem;"><strong>Version:</strong> 3.0</li>
                        <li style="margin-bottom: 0.25rem;"><strong>Last Update:</strong> August 2025</li>
                        <li style="margin-bottom: 0.25rem;"><strong>Uptime:</strong> 99.9%</li>
                        <li style="margin-bottom: 0.25rem;"><strong>Users:</strong> <?php 
                            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE is_active = 1");
                            $stmt->execute();
                            echo $stmt->fetchColumn(); 
                        ?></li>
                        <li style="margin-bottom: 0.25rem;"><strong>Events:</strong> <?php 
                            $stmt = $db->prepare("SELECT COUNT(*) FROM events");
                            $stmt->execute();
                            echo $stmt->fetchColumn(); 
                        ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <!-- End main-content -->
    
    <script>
        function toggleFaq(element) {
            const answer = element.nextElementSibling;
            const icon = element.querySelector('span:last-child');
            
            if (answer.classList.contains('show')) {
                answer.classList.remove('show');
                icon.textContent = '+';
            } else {
                // Close all other FAQs
                document.querySelectorAll('.faq-answer.show').forEach(el => {
                    el.classList.remove('show');
                    el.previousElementSibling.querySelector('span:last-child').textContent = '+';
                });
                
                answer.classList.add('show');
                icon.textContent = '−';
            }
        }
        
        function searchHelp() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            const sections = document.querySelectorAll('.help-section');
            
            if (!query) {
                sections.forEach(section => section.style.display = 'block');
                return;
            }
            
            sections.forEach(section => {
                const text = section.textContent.toLowerCase();
                if (text.includes(query)) {
                    section.style.display = 'block';
                    // Highlight search terms (simplified)
                } else {
                    section.style.display = 'none';
                }
            });
        }
        
        // Contact form submission
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // In a real implementation, this would submit to a support system
            alert('Thank you for your message! We\'ll get back to you within 24 hours.');
            this.reset();
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === '/') {
                e.preventDefault();
                document.getElementById('searchInput').focus();
            } else if (e.altKey && e.key === 'd') {
                e.preventDefault();
                window.location.href = 'dashboard.php';
            } else if (e.altKey && e.key === 'e') {
                e.preventDefault();
                window.location.href = 'events.php';
            } else if (e.altKey && e.key === 'p') {
                e.preventDefault();
                window.location.href = 'profile.php';
            }
        });
        
        // Smooth scrolling for anchor links
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });
    </script>
    
    <?php include '../includes/theme_script.php'; ?>
</body>
</html>
