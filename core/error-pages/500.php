<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Error - RFID Check-in System</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
    <style>
        .error-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--error-color) 0%, var(--warning-color) 100%);
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
        
        .error-actions {
            display: flex;
            gap: var(--space-4);
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .error-id {
            margin-top: var(--space-8);
            padding-top: var(--space-4);
            border-top: 1px solid var(--border-color);
            font-size: var(--font-size-sm);
            color: var(--text-muted);
        }
        
        .debug-info {
            margin-top: var(--space-8);
            padding: var(--space-6);
            background: var(--bg-secondary);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            text-align: left;
            font-family: var(--font-family-mono);
            font-size: var(--font-size-sm);
        }
        
        .debug-info h4 {
            color: var(--error-color);
            margin-bottom: var(--space-3);
            font-family: var(--font-family-sans);
        }
        
        .stack-trace {
            max-height: 300px;
            overflow-y: auto;
            background: var(--bg-primary);
            padding: var(--space-4);
            border-radius: var(--radius-sm);
            margin-top: var(--space-3);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-card">
            <div class="error-icon">⚠️</div>
            
            <h1 class="error-title">System Error</h1>
            
            <p class="error-message">
                We're sorry, but something went wrong while processing your request. 
                Our technical team has been automatically notified and is working to resolve this issue.
            </p>
            
            <div class="error-actions">
                <a href="javascript:history.back()" class="btn btn-secondary">
                    ← Go Back
                </a>
                <a href="/" class="btn btn-primary">
                    🏠 Return Home
                </a>
                <button onclick="window.location.reload()" class="btn btn-ghost">
                    🔄 Try Again
                </button>
            </div>
            
            <?php if (isset($isDev) && $isDev && isset($exception)): ?>
                <div class="debug-info">
                    <h4>🐛 Debug Information</h4>
                    <p><strong>Error:</strong> <?php echo htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>File:</strong> <?php echo htmlspecialchars($exception->getFile(), ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>Line:</strong> <?php echo $exception->getLine(); ?></p>
                    
                    <div class="stack-trace">
                        <strong>Stack Trace:</strong><br>
                        <?php echo nl2br(htmlspecialchars($exception->getTraceAsString(), ENT_QUOTES, 'UTF-8')); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (isset($errorId)): ?>
                <div class="error-id">
                    <strong>Error ID:</strong> <?php echo htmlspecialchars($errorId, ENT_QUOTES, 'UTF-8'); ?>
                    <br>
                    <small>Please reference this ID when contacting support.</small>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Auto-refresh after 30 seconds (optional)
        setTimeout(function() {
            const refreshBtn = document.querySelector('button[onclick*="reload"]');
            if (refreshBtn) {
                refreshBtn.style.background = 'var(--primary-color)';
                refreshBtn.style.color = 'var(--text-light)';
                refreshBtn.innerHTML = '🔄 Auto-refreshing...';
                
                setTimeout(function() {
                    window.location.reload();
                }, 2000);
            }
        }, 30000);
    </script>
</body>
</html>
