<?php

declare(strict_types=1);

namespace RfidCheckin\Middleware;

/**
 * Middleware Interface
 * 
 * Defines the contract for all middleware components in the request/response pipeline.
 * Middleware can inspect, modify, or terminate the request before it reaches the controller.
 * 
 * @package RfidCheckin\Middleware
 * @version 1.0.0
 * @author Senior Development Team
 */
interface MiddlewareInterface
{
    /**
     * Handle the request through the middleware
     * 
     * @param callable $next The next middleware in the pipeline
     * @return mixed Response or continuation to next middleware
     */
    public function handle(callable $next);
}
