<?php

declare(strict_types=1);

namespace RfidCheckin\Middleware;

use RfidCheckin\Core\Database;

/**
 * API Authentication Middleware
 * 
 * Validates API keys for external API access
 */
class ApiAuthMiddleware
{
    /**
     * Handle the request
     */
    public function handle($request, callable $next)
    {
        // Get API key from header or query parameter
        $apiKey = $this->getApiKey();
        
        if (!$apiKey) {
            $this->sendError('API key required', 401);
            return;
        }
        
        // Validate API key
        if (!$this->validateApiKey($apiKey)) {
            $this->sendError('Invalid API key', 401);
            return;
        }
        
        // Update API key usage
        $this->updateApiKeyUsage($apiKey);
        
        // Continue to next middleware/controller
        return $next($request);
    }

    /**
     * Get API key from request
     */
    private function getApiKey(): ?string
    {
        // Check Authorization header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        // Check X-API-Key header
        if (!empty($_SERVER['HTTP_X_API_KEY'])) {
            return $_SERVER['HTTP_X_API_KEY'];
        }
        
        // Check query parameter
        if (!empty($_GET['api_key'])) {
            return $_GET['api_key'];
        }
        
        return null;
    }

    /**
     * Validate API key against database
     */
    private function validateApiKey(string $apiKey): bool
    {
        try {
            $db = Database::getInstance();
            
            $stmt = $db->prepare("SELECT id, name, permissions, is_active FROM api_keys WHERE key_value = ?");
            $stmt->execute([$apiKey]);
            $result = $stmt->fetch();
            
            if (!$result || !$result['is_active']) {
                return false;
            }
            
            // Store API key info for use in controllers
            $_SERVER['API_KEY_INFO'] = $result;
            
            return true;
            
        } catch (\Exception $e) {
            error_log("API key validation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update API key last used timestamp
     */
    private function updateApiKeyUsage(string $apiKey): void
    {
        try {
            $db = Database::getInstance();
            
            $stmt = $db->prepare("UPDATE api_keys SET last_used = NOW() WHERE key_value = ?");
            $stmt->execute([$apiKey]);
            
        } catch (\Exception $e) {
            error_log("API key update error: " . $e->getMessage());
        }
    }

    /**
     * Send JSON error response
     */
    private function sendError(string $message, int $code): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('c')
        ]);
        exit;
    }
}