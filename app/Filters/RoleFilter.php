<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Get user from request (set by JWTAuthFilter)
        $user = $request->user ?? null;
        
        if (!$user) {
            return service('response')
                ->setJSON([
                    'status' => false,
                    'message' => 'Unauthorized access'
                ])
                ->setStatusCode(403);
        }
        
        // Check if user has required role
        $requiredRole = $arguments[0] ?? 'user';
        
        if ($user->role !== $requiredRole) {
            return service('response')
                ->setJSON([
                    'status' => false,
                    'message' => 'Insufficient permissions. Required role: ' . $requiredRole
                ])
                ->setStatusCode(403);
        }
        
        return $request;
    }
    
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
}