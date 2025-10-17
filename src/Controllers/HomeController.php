<?php

declare(strict_types=1);

namespace RfidCheckin\Controllers;

/**
 * Home Controller
 * 
 * Handles the homepage and basic public routes
 */
class HomeController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * Show homepage
     */
    public function index(): void
    {
        try {
            $user = $this->getCurrentUser();
            
            if ($user) {
                // If logged in, redirect to dashboard
                header('Location: /dashboard');
                exit;
            }
            
            // Show homepage for non-authenticated users
            echo $this->render('home', [
                'title' => 'RFID Check-in System',
                'user' => $user
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Homepage error', [
                'error' => $e->getMessage()
            ]);
            $this->renderError('Unable to load homepage');
        }
    }
}