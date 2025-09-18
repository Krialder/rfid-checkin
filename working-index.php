<?php
// Working version of index.php
header('Content-Type: application/json');

// Simple routing without the complex Application class
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove the /rfid-checkin prefix if present
$path = preg_replace('#^/rfid-checkin#', '', $path);

// Simple route matching
switch ($path) {
    case '/':
    case '':
        echo json_encode([
            'message' => 'RFID Check-in System API',
            'status' => 'running',
            'timestamp' => date('c'),
            'php_version' => phpversion()
        ]);
        break;
        
    case '/api/status':
        echo json_encode([
            'status' => 'ok',
            'timestamp' => date('c'),
            'system' => 'RFID Check-in System',
            'version' => '1.0.0'
        ]);
        break;
        
    case '/auth/login':
        // Simple login page
        header('Content-Type: text/html');
        echo '<!DOCTYPE html>
<html>
<head>
    <title>RFID Login</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .login-form { max-width: 400px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; }
        input { width: 100%; padding: 10px; margin: 10px 0; }
        button { background: #007cba; color: white; padding: 10px 20px; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <div class="login-form">
        <h1>RFID System Login</h1>
        <form method="post" action="/rfid-checkin/auth/login">
            <input type="text" name="username" placeholder="Username" value="admin">
            <input type="password" name="password" placeholder="Password" value="admin123">
            <button type="submit">Login</button>
        </form>
        <p><strong>Test Credentials:</strong><br>
        Username: admin<br>
        Password: admin123</p>
    </div>
</body>
</html>';
        break;
        
    default:
        http_response_code(404);
        echo json_encode([
            'error' => 'Not Found',
            'path' => $path,
            'message' => 'The requested path was not found'
        ]);
        break;
}
?>