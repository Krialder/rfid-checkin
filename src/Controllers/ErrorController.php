<?php

declare(strict_types=1);

namespace RfidCheckin\Controllers;

/**
 * Error Controller
 * 
 * Handles error pages and HTTP error responses
 * 
 * @package RfidCheckin\Controllers
 */
class ErrorController extends BaseController
{
    /**
     * Show 403 Forbidden page
     */
    public function forbidden(): void
    {
        http_response_code(403);
        echo $this->render('error/403', [
            'title' => '403 - Forbidden',
            'message' => 'You do not have permission to access this resource.'
        ]);
    }

    /**
     * Show 404 Not Found page
     */
    public function notFound(): void
    {
        http_response_code(404);
        echo $this->render('error/404', [
            'title' => '404 - Page Not Found',
            'message' => 'The page you are looking for could not be found.'
        ]);
    }

    /**
     * Show 500 Internal Server Error page
     */
    public function serverError(): void
    {
        http_response_code(500);
        echo $this->render('error/500', [
            'title' => '500 - Internal Server Error',
            'message' => 'An internal server error occurred.'
        ]);
    }
}
?>