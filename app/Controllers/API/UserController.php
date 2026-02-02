<?php

namespace App\Controllers\API;

use CodeIgniter\RESTful\ResourceController;
use App\Libraries\JWTHandler;

class UserController extends ResourceController
{
    protected $modelName = 'App\Models\UserModel';
    protected $format = 'json';
    
    private $jwt;
    
    public function __construct()
    {
        $this->jwt = new JWTHandler();
    }
    
    /**
     * Get authenticated user from token
     */
    private function getAuthUser()
    {
        $token = $this->jwt->getTokenFromHeader();
        
        if (!$token) {
            return null;
        }
        
        $validated = $this->jwt->validateToken($token);
        
        if (!$validated['success']) {
            return null;
        }
        
        return $validated['data'];
    }
    
    /**
     * Get all users (Admin only)
     */
    public function index()
    {
        $page = $this->request->getGet('page') ?? 1;
        $limit = $this->request->getGet('limit') ?? 10;
        
        $users = $this->model->paginate($limit, 'default', $page);
        $pager = $this->model->pager;
        
        foreach ($users as &$user) {
            unset($user['password']);
        }
        
        return $this->respond([
            'status' => true,
            'data' => $users,
            'pagination' => [
                'current_page' => $pager->getCurrentPage(),
                'total_pages' => $pager->getPageCount(),
                'per_page' => $pager->getPerPage(),
                'total' => $pager->getTotal()
            ]
        ]);
    }
    
    /**
     * Get single user (Admin only)
     */
    public function show($id = null)
    {
        $user = $this->model->getUserSafe($id);
        
        if (!$user) {
            return $this->failNotFound('User not found');
        }
        
        return $this->respond([
            'status' => true,
            'data' => $user
        ]);
    }
    
    /**
     * Get current user profile
     */
    public function profile()
    {
        $authUser = $this->getAuthUser();
        
        if (!$authUser) {
            return $this->failUnauthorized('Invalid or expired token');
        }
        
        $userId = $authUser->user_id;
        $user = $this->model->getUserSafe($userId);
        
        if (!$user) {
            return $this->failNotFound('User not found');
        }
        
        return $this->respond([
            'status' => true,
            'data' => $user
        ]);
    }
    
    /**
     * Update user profile
     */
    public function updateProfile()
    {
        $authUser = $this->getAuthUser();
        
        if (!$authUser) {
            return $this->failUnauthorized('Invalid or expired token');
        }
        
        $userId = $authUser->user_id;
        $user = $this->model->find($userId);
        
        if (!$user) {
            return $this->failNotFound('User not found');
        }
        
        $data = [];
        
        if ($this->request->getVar('full_name')) {
            $data['full_name'] = $this->request->getVar('full_name');
        }
        if ($this->request->getVar('phone')) {
            $data['phone'] = $this->request->getVar('phone');
        }
        if ($this->request->getVar('avatar')) {
            $data['avatar'] = $this->request->getVar('avatar');
        }
        
        if (empty($data)) {
            return $this->fail('No data to update', 400);
        }
        
        // Update with ID
        $this->model->update($userId, $data);
        
        $updatedUser = $this->model->getUserSafe($userId);
        
        return $this->respond([
            'status' => true,
            'message' => 'Profile updated successfully',
            'data' => $updatedUser
        ]);
    }
    
    /**
     * Change password
     */
    public function changePassword()
    {
        $authUser = $this->getAuthUser();
        
        if (!$authUser) {
            return $this->failUnauthorized('Invalid or expired token');
        }
        
        $userId = $authUser->user_id;
        
        $rules = [
            'current_password' => 'required',
            'new_password' => 'required|min_length[6]',
            'confirm_password' => 'required|matches[new_password]'
        ];
        
        if (!$this->validate($rules)) {
            return $this->fail($this->validator->getErrors(), 400);
        }
        
        $user = $this->model->find($userId);
        
        if (!$user) {
            return $this->failNotFound('User not found');
        }
        
        if (!$this->model->verifyPassword($this->request->getVar('current_password'), $user['password'])) {
            return $this->fail('Current password is incorrect', 400);
        }
        
        $this->model->update($userId, [
            'password' => $this->request->getVar('new_password')
        ]);
        
        return $this->respond([
            'status' => true,
            'message' => 'Password changed successfully'
        ]);
    }
    
    /**
     * Delete user (Admin only)
     */
    public function delete($id = null)
    {
        $authUser = $this->getAuthUser();
        
        if (!$authUser) {
            return $this->failUnauthorized('Invalid or expired token');
        }
        
        $user = $this->model->find($id);
        
        if (!$user) {
            return $this->failNotFound('User not found');
        }
        
        if ($id == $authUser->user_id) {
            return $this->fail('Cannot delete your own account', 400);
        }
        
        $this->model->delete($id);
        
        return $this->respondDeleted([
            'status' => true,
            'message' => 'User deleted successfully'
        ]);
    }
}