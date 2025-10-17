<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFID Check-in System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 40px;
            background: #f8f9fa;
            text-align: center;
        }
        .container {
            max-width: 600px;
            margin: 100px auto;
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #007bff;
            margin-bottom: 20px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 10px;
        }
        .btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 RFID Check-in System</h1>
        <p>Welcome to the RFID Check-in System. The application is now working correctly!</p>
        
        <?php if (isset($user) && $user): ?>
            <p>Welcome back, <strong><?= htmlspecialchars($user['name']) ?></strong>!</p>
            <a href="/dashboard" class="btn">Go to Dashboard</a>
            <a href="/logout" class="btn">Logout</a>
        <?php else: ?>
            <p>Please log in to access the system.</p>
            <a href="/login" class="btn">Login</a>
        <?php endif; ?>
        
        <hr style="margin: 30px 0;">
        <p><small>System is running on: <?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'localhost') ?></small></p>
    </div>
</body>
</html>