<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fatal Error - RFID Check-in System</title>
    <style>
        :root {
            --error-color: #dc2626;
            --warning-color: #d97706;
            --card-bg: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --bg-secondary: #f8fafc;
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --radius-xl: 0.75rem;
            --space-4: 1rem;
            --space-6: 1.5rem;
            --space-8: 2rem;
            --space-12: 3rem;
            --font-size-sm: 0.875rem;
            --font-size-lg: 1.125rem;
            --font-size-3xl: 1.875rem;
            --font-weight-bold: 700;
            --line-height-relaxed: 1.625;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, var(--error-color) 0%, var(--warning-color) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--space-8);
        }
        
        .error-card {
            background: var(--card-bg);
            padding: var(--space-12);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            text-align: center;
            max-width: 600px;
            width: 100%;
        }
        
        .error-icon {
            font-size: 4rem;
            margin-bottom: var(--space-6);
        }
        
        .error-title {
            font-size: var(--font-size-3xl);
            font-weight: var(--font-weight-bold);
            color: var(--error-color);
            margin-bottom: var(--space-4);
        }
        
        .error-message {
            font-size: var(--font-size-lg);
            color: var(--text-secondary);
            margin-bottom: var(--space-8);
            line-height: var(--line-height-relaxed);
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            border: 1px solid transparent;
            border-radius: 0.375rem;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
            margin: 0 0.5rem;
        }
        
        .btn-primary {
            background-color: #2563eb;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #1d4ed8;
            transform: translateY(-1px);
        }
        
        .error-id {
            margin-top: var(--space-8);
            padding-top: var(--space-4);
            border-top: 1px solid var(--border-color);
            font-size: var(--font-size-sm);
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon">💥</div>
        
        <h1 class="error-title">Fatal System Error</h1>
        
        <p class="error-message">
            A critical system error has occurred that prevents the application from continuing. 
            Our technical team has been automatically notified. Please try refreshing the page 
            or contact system administrators if the problem persists.
        </p>
        
        <div class="error-actions">
            <button onclick="window.location.reload()" class="btn btn-primary">
                🔄 Refresh Page
            </button>
            <a href="/" class="btn btn-primary">
                🏠 Return Home
            </a>
        </div>
        
        <?php if (isset($errorId)): ?>
            <div class="error-id">
                <strong>Error ID:</strong> <?php echo htmlspecialchars($errorId, ENT_QUOTES, 'UTF-8'); ?>
                <br>
                <small>Please reference this ID when contacting support.</small>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
