<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUtf8Encoding
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Ensure JSON responses include charset=UTF-8
        if ($response instanceof \Illuminate\Http\JsonResponse || 
            $response->headers->get('Content-Type') === 'application/json' ||
            str_contains($response->headers->get('Content-Type', ''), 'application/json')) {
            
            $contentType = $response->headers->get('Content-Type', 'application/json');
            
            // Add charset=UTF-8 if not already present
            if (!str_contains($contentType, 'charset')) {
                $response->headers->set('Content-Type', $contentType . '; charset=UTF-8');
            } else {
                // Ensure charset is UTF-8
                $contentType = preg_replace('/charset=[^;]+/i', 'charset=UTF-8', $contentType);
                $response->headers->set('Content-Type', $contentType);
            }
        }

        return $response;
    }
}


