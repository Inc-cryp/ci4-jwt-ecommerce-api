<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Default route
$routes->get('/', 'Home::index');

// API Routes
$routes->group('api', ['namespace' => 'App\Controllers\API'], function($routes) {
    
    // Auth routes (public)
    $routes->group('auth', function($routes) {
        $routes->post('register', 'AuthController::register');
        $routes->post('login', 'AuthController::login');
        
        // OAuth routes
        $routes->get('google', 'AuthController::googleLogin');
        $routes->get('google/callback', 'AuthController::googleCallback');
        $routes->get('facebook', 'AuthController::facebookLogin');
        $routes->get('facebook/callback', 'AuthController::facebookCallback');
        
        // Protected auth routes
        $routes->group('', ['filter' => 'jwtauth'], function($routes) {
            $routes->post('refresh', 'AuthController::refresh');
            $routes->post('logout', 'AuthController::logout');
            $routes->get('me', 'AuthController::me');
        });
    });
    
    // Protected routes - require authentication
    $routes->group('', ['filter' => 'jwtauth'], function($routes) {
        
        // User routes
        $routes->group('users', function($routes) {
            $routes->get('profile', 'UserController::profile');
            $routes->put('profile', 'UserController::updateProfile');
            $routes->put('password', 'UserController::changePassword');
            
            // Admin only
            $routes->group('', ['filter' => 'role:admin'], function($routes) {
                $routes->get('/', 'UserController::index');
                $routes->get('(:num)', 'UserController::show/$1');
                $routes->delete('(:num)', 'UserController::delete/$1');
            });
        });
        
        // Product routes
        $routes->group('products', function($routes) {
            $routes->get('/', 'ProductController::index');
            $routes->get('(:num)', 'ProductController::show/$1');
            
            // Admin only
            $routes->group('', ['filter' => 'role:admin'], function($routes) {
                $routes->post('/', 'ProductController::create');
                $routes->put('(:num)', 'ProductController::update/$1');
                $routes->delete('(:num)', 'ProductController::delete/$1');
            });
        });
        
        // Payment routes
        $routes->group('payments', function($routes) {
            $routes->post('create', 'PaymentController::createTransaction');
            $routes->get('status/(:segment)', 'PaymentController::checkStatus/$1');
            $routes->get('history', 'PaymentController::history');
        });
        
        // Order routes
        $routes->group('orders', function($routes) {
            $routes->get('/', 'OrderController::index');
            $routes->get('(:num)', 'OrderController::show/$1');
            $routes->post('/', 'OrderController::create');
            $routes->put('(:num)/cancel', 'OrderController::cancel/$1');
            
            // Admin only
            $routes->group('', ['filter' => 'role:admin'], function($routes) {
                $routes->put('(:num)/status', 'OrderController::updateStatus/$1');
            });
        });
    });
    
    // Payment webhook (public - no auth required)
    $routes->post('payments/notification', 'PaymentController::notification');
    
});