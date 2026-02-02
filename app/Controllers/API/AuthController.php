<?php

namespace App\Controllers\API;

use CodeIgniter\RESTful\ResourceController;
use App\Models\UserModel;
use App\Libraries\JWTHandler;
use App\Libraries\OAuthHandler;

class AuthController extends ResourceController
{
    protected $modelName = 'App\Models\UserModel';
    protected $format = 'json';
    
    private $jwt;
    private $oauth;
    
    public function __construct()
    {
        $this->jwt = new JWTHandler();
        $this->oauth = new OAuthHandler();
    }
    
    /**
     * Get authenticated user from token
     */
    private function getAuthUser()
    {
        try {
            $token = $this->jwt->getTokenFromHeader();
            
            if (!$token) {
                return null;
            }
            
            $validated = $this->jwt->validateToken($token);
            
            if (!$validated['success']) {
                return null;
            }
            
            return $validated['data'];
        } catch (\Exception $e) {
            log_message('error', 'AuthController::getAuthUser - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Register new user
     */
    public function register()
    {
        try {
            $rules = [
                'username' => 'required|min_length[3]|max_length[50]|is_unique[users.username]',
                'email' => 'required|valid_email|is_unique[users.email]',
                'password' => 'required|min_length[6]',
                'full_name' => 'required|min_length[3]|max_length[100]'
            ];
            
            if (!$this->validate($rules)) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Validation errors',
                    'errors' => $this->validator->getErrors()
                ], 400);
            }
            
            $data = [
                'username' => $this->request->getVar('username'),
                'email' => $this->request->getVar('email'),
                'password' => $this->request->getVar('password'),
                'full_name' => $this->request->getVar('full_name'),
                'phone' => $this->request->getVar('phone'),
                'role' => 'user',
                'is_active' => 1
            ];
            
            $userId = $this->model->insert($data);
            
            if (!$userId) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Failed to create user',
                    'errors' => $this->model->errors()
                ], 500);
            }
            
            $user = $this->model->getUserSafe($userId);
            
            if (!$user) {
                return $this->fail([
                    'status' => false,
                    'message' => 'User created but failed to retrieve user data'
                ], 500);
            }
            
            $token = $this->jwt->generateToken($user['id'], $user['email'], $user['role']);
            
            return $this->respondCreated([
                'status' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => getenv('jwt.expire') ?: 3600,
                    'user' => $user
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'AuthController::register - ' . $e->getMessage());
            return $this->fail([
                'status' => false,
                'message' => 'An error occurred during registration',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Login user
     */
    public function login()
    {
        try {
            $rules = [
                'email' => 'required|valid_email',
                'password' => 'required'
            ];
            
            if (!$this->validate($rules)) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Validation errors',
                    'errors' => $this->validator->getErrors()
                ], 400);
            }
            
            $email = $this->request->getVar('email');
            $password = $this->request->getVar('password');
            
            $user = $this->model->findByEmail($email);
            
            if (!$user) {
                return $this->failUnauthorized(
                    
                    'Invalid email or password'
                );
            }
            
            if (!$this->model->verifyPassword($password, $user['password'])) {
                return $this->failUnauthorized(
                    
                    'Invalid email or password'
                );
            }
            
            if (!$user['is_active']) {
                return $this->failForbidden(
                    
                    'Account is not active. Please contact administrator.'
                );
            }
            
            unset($user['password']);
            $token = $this->jwt->generateToken($user['id'], $user['email'], $user['role']);
            
            return $this->respond([
                'status' => true,
                'message' => 'Login successful',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => getenv('jwt.expire') ?: 3600,
                    'user' => $user
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'AuthController::login - ' . $e->getMessage());
            return $this->fail([
                'status' => false,
                'message' => 'An error occurred during login',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get current user info
     */
    public function me()
    {
        try {
            $authUser = $this->getAuthUser();
            
            if (!$authUser) {
                return $this->failUnauthorized(
                    
                    'Invalid or expired token'
                );
            }
            
            $userId = $authUser->user_id;
            $user = $this->model->getUserSafe($userId);
            
            if (!$user) {
                return $this->failNotFound(
                    'User not found'
                );
            }
            
            return $this->respond([
                'status' => true,
                'data' => $user
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'AuthController::me - ' . $e->getMessage());
            return $this->fail([
                'status' => false,
                'message' => 'An error occurred while fetching user data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh token
     */
    public function refresh()
    {
        try {
            $token = $this->jwt->getTokenFromHeader();
            
            if (!$token) {
                return $this->failUnauthorized(
                    'Token not provided'
                );
            }
            
            $result = $this->jwt->refreshToken($token);
            
            if (!$result['success']) {
                return $this->failUnauthorized(
                    'Failed to refresh token',
                );
            }
            
            return $this->respond([
                'status' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $result['token'],
                    'token_type' => 'Bearer',
                    'expires_in' => getenv('jwt.expire') ?: 3600
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'AuthController::refresh - ' . $e->getMessage());
            return $this->fail([
                'status' => false,
                'message' => 'An error occurred while refreshing token',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Logout user
     */
    public function logout()
    {
        try {
            // Verify token first
            $authUser = $this->getAuthUser();
            
            if (!$authUser) {
                return $this->failUnauthorized(
                    
                    'Invalid or expired token'
                );
            }
            
            // In production, you might want to:
            // 1. Blacklist the token
            // 2. Clear Redis cache for this user
            // 3. Log the logout event
            
            return $this->respond([
                'status' => true,
                'message' => 'Logout successful'
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'AuthController::logout - ' . $e->getMessage());
            return $this->fail([
                'status' => false,
                'message' => 'An error occurred during logout',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Google OAuth login
     */
    public function googleLogin()
    {
        try {
            $authUrl = $this->oauth->getGoogleAuthUrl();
            
            if (!$authUrl) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Failed to generate Google OAuth URL. Check your configuration.'
                ], 500);
            }
            
            return redirect()->to($authUrl);
            
        } catch (\Exception $e) {
            log_message('error', 'AuthController::googleLogin - ' . $e->getMessage());
            return $this->fail([
                'status' => false,
                'message' => 'An error occurred during Google OAuth initialization',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Google OAuth callback
     */
    public function googleCallback()
    {
        try {
            $code = $this->request->getGet('code');
            
            if (!$code) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Authorization code not provided'
                ], 400);
            }
            
            $userData = $this->oauth->getGoogleUserData($code);
            
            if (!$userData) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Failed to get user data from Google'
                ], 500);
            }
            
            // Check if user already exists
            $user = $this->model->findByOAuth('google', $userData['id']);
            
            if (!$user) {
                // Check if email already exists with different provider
                $existingUser = $this->model->findByEmail($userData['email']);
                
                if ($existingUser && !$existingUser['oauth_provider']) {
                    return $this->fail([
                        'status' => false,
                        'message' => 'Email already registered. Please login with email and password.'
                    ], 409);
                }
                
                // Create new user
                $userId = $this->model->createOAuthUser([
                    'email' => $userData['email'],
                    'name' => $userData['name'],
                    'avatar' => $userData['picture'] ?? null,
                    'provider' => 'google',
                    'oauth_id' => $userData['id']
                ]);
                
                if (!$userId) {
                    return $this->fail([
                        'status' => false,
                        'message' => 'Failed to create user',
                        'errors' => $this->model->errors()
                    ], 500);
                }
                
                $user = $this->model->getUserSafe($userId);
            } else {
                unset($user['password']);
            }
            
            if (!$user) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Failed to retrieve user data'
                ], 500);
            }
            
            $token = $this->jwt->generateToken($user['id'], $user['email'], $user['role']);
            
            return $this->respond([
                'status' => true,
                'message' => 'Login with Google successful',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => getenv('jwt.expire') ?: 3600,
                    'user' => $user
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'AuthController::googleCallback - ' . $e->getMessage());
            return $this->fail([
                'status' => false,
                'message' => 'An error occurred during Google OAuth callback',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Facebook OAuth login
     */
    public function facebookLogin()
    {
        try {
            $authUrl = $this->oauth->getFacebookAuthUrl();
            
            if (!$authUrl) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Failed to generate Facebook OAuth URL. Check your configuration.'
                ], 500);
            }
            
            return redirect()->to($authUrl);
            
        } catch (\Exception $e) {
            log_message('error', 'AuthController::facebookLogin - ' . $e->getMessage());
            return $this->fail([
                'status' => false,
                'message' => 'An error occurred during Facebook OAuth initialization',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Facebook OAuth callback
     */
    public function facebookCallback()
    {
        try {
            $code = $this->request->getGet('code');
            
            if (!$code) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Authorization code not provided'
                ], 400);
            }
            
            $userData = $this->oauth->getFacebookUserData($code);
            
            if (!$userData) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Failed to get user data from Facebook'
                ], 500);
            }
            
            // Check if user already exists
            $user = $this->model->findByOAuth('facebook', $userData['id']);
            
            if (!$user) {
                // Check if email already exists with different provider
                $existingUser = $this->model->findByEmail($userData['email']);
                
                if ($existingUser && !$existingUser['oauth_provider']) {
                    return $this->fail([
                        'status' => false,
                        'message' => 'Email already registered. Please login with email and password.'
                    ], 409);
                }
                
                // Create new user
                $userId = $this->model->createOAuthUser([
                    'email' => $userData['email'],
                    'name' => $userData['name'],
                    'avatar' => $userData['picture']['data']['url'] ?? null,
                    'provider' => 'facebook',
                    'oauth_id' => $userData['id']
                ]);
                
                if (!$userId) {
                    return $this->fail([
                        'status' => false,
                        'message' => 'Failed to create user',
                        'errors' => $this->model->errors()
                    ], 500);
                }
                
                $user = $this->model->getUserSafe($userId);
            } else {
                unset($user['password']);
            }
            
            if (!$user) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Failed to retrieve user data'
                ], 500);
            }
            
            $token = $this->jwt->generateToken($user['id'], $user['email'], $user['role']);
            
            return $this->respond([
                'status' => true,
                'message' => 'Login with Facebook successful',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => getenv('jwt.expire') ?: 3600,
                    'user' => $user
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'AuthController::facebookCallback - ' . $e->getMessage());
            return $this->fail([
                'status' => false,
                'message' => 'An error occurred during Facebook OAuth callback',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}