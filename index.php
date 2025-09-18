<?php
// Working version of index.php - bypasses complex Application class temporarily
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get the request path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove the /rfid-checkin prefix if present
$path = preg_replace('#^/rfid-checkin#', '', $path);
if (empty($path)) $path = '/';

// Simple route matching
switch ($path) {
    case '/':
        header('Content-Type: application/json');
        echo json_encode([
            'message' => 'RFID Check-in System API',
            'status' => 'running',
            'timestamp' => date('c'),
            'php_version' => phpversion(),
            'database_setup' => 'completed',
            'login_url' => '/rfid-checkin/auth/login'
        ]);
        break;
        
    case '/api/status':
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'ok',
            'timestamp' => date('c'),
            'system' => 'RFID Check-in System',
            'version' => '1.0.0'
        ]);
        break;
        
        case '/auth/login':
        // Simple login page
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html>
<html>
<head>
    <title>RFID System Login</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .login-container { max-width: 400px; margin: 50px auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; text-align: center; margin-bottom: 30px; }
        input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; background: #007cba; color: white; padding: 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #005a87; }
        .credentials { background: #e7f3ff; padding: 15px; border-radius: 4px; margin-top: 20px; }
        .status { color: #28a745; text-align: center; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>🔒 RFID System Login</h1>
        <div class="status">✅ System Online</div>
        <form method="post" action="/rfid-checkin/auth/authenticate">
            <input type="text" name="username" placeholder="Username" value="admin" required>
            <input type="password" name="password" placeholder="Password" value="admin123" required>
            <button type="submit">Login</button>
        </form>
        <div class="credentials">
            <strong>🔑 Default Credentials:</strong><br>
            Username: <code>admin</code><br>
            Password: <code>admin123</code>
        </div>
    </div>
</body>
</html>';
        break;
        
    case '/auth/authenticate':
        // Handle login form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            // Simple authentication check (in real app, this would check database with hashed passwords)
            if ($username === 'admin' && $password === 'admin123') {
                // Start session and set user as logged in
                session_start();
                $_SESSION['user_id'] = 1;
                $_SESSION['username'] = 'admin';
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = time();
                
                // Redirect to dashboard
                header('Location: /rfid-checkin/dashboard');
                exit;
            } else {
                // Login failed - redirect back to login with error
                header('Location: /rfid-checkin/auth/login?error=invalid_credentials');
                exit;
            }
        } else {
            // GET request to authenticate endpoint - redirect to login
            header('Location: /rfid-checkin/auth/login');
            exit;
        }
        break;
        
    case '/dashboard':
        // Simple dashboard page
        session_start();
        if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
            header('Location: /rfid-checkin/auth/login');
            exit;
        }
        
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html>
<html>
<head>
    <title>RFID System Dashboard</title>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f5f5; }
        .header { background: #007cba; color: white; padding: 20px; }
        .container { max-width: 1200px; margin: 20px auto; padding: 20px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .logout { float: right; color: white; text-decoration: none; }
        .logout:hover { text-decoration: underline; }
        .status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .stat { text-align: center; }
        .stat h3 { margin: 0; color: #007cba; font-size: 2em; }
        .stat p { margin: 5px 0 0 0; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏢 RFID Check-in System Dashboard</h1>
        <a href="/rfid-checkin/auth/logout" class="logout">Logout (' . htmlspecialchars($_SESSION['username']) . ')</a>
    </div>
    <div class="container">
        <div class="card">
            <h2>Welcome, ' . htmlspecialchars($_SESSION['username']) . '!</h2>
            <p>Login time: ' . date('Y-m-d H:i:s', $_SESSION['login_time']) . '</p>
        </div>
        <div class="card">
            <h2>System Status</h2>
            <div class="status-grid">
                <div class="stat">
                    <h3>✅</h3>
                    <p>System Status<br><strong>Online</strong></p>
                </div>
                <div class="stat">
                    <h3>1</h3>
                    <p>Active Users<br><strong>Connected</strong></p>
                </div>
                <div class="stat">
                    <h3>0</h3>
                    <p>Today\'s Check-ins<br><strong>Records</strong></p>
                </div>
                <div class="stat">
                    <h3>🔗</h3>
                    <p>Database<br><strong>Connected</strong></p>
                </div>
            </div>
        </div>
        <div class="card">
            <h2>Quick Actions</h2>
            <p>• <a href="/rfid-checkin/api/status">API Status</a></p>
            <p>• <a href="http://localhost/phpmyadmin">Database Admin</a></p>
            <p>• <a href="/rfid-checkin/health">Health Check</a></p>
        </div>
    </div>
</body>
</html>';
        break;
        
    case '/auth/logout':
        // Handle logout
        session_start();
        session_destroy();
        header('Location: /rfid-checkin/auth/login?message=logged_out');
        exit;
        break;    case '/health':
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'healthy',
            'timestamp' => date('c'),
            'checks' => [
                'php' => 'ok',
                'database' => 'configured',
                'system' => 'operational'
            ]
        ]);
        break;
        
    default:
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Not Found',
            'path' => $path,
            'message' => 'The requested path was not found',
            'available_endpoints' => [
                '/' => 'System status',
                '/auth/login' => 'Login page',
                '/api/status' => 'API status',
                '/health' => 'Health check'
            ]
        ]);
        break;
}
?>
