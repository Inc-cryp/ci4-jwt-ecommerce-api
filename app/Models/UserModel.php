<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'username',
        'email',
        'password',
        'full_name',
        'phone',
        'avatar',
        'role',
        'oauth_provider',
        'oauth_id',
        'email_verified_at',
        'is_active'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    // Validation
    protected $validationRules = [
        'username' => 'required|min_length[3]|max_length[50]|is_unique[users.username,id,{id}]',
        'email' => 'required|valid_email|is_unique[users.email,id,{id}]',
        'password' => 'required|min_length[6]',
        'full_name' => 'required|min_length[3]|max_length[100]',
    ];

    protected $validationMessages = [
        'username' => [
            'required' => 'Username is required',
            'min_length' => 'Username must be at least 3 characters',
            'is_unique' => 'Username already exists'
        ],
        'email' => [
            'required' => 'Email is required',
            'valid_email' => 'Email must be valid',
            'is_unique' => 'Email already exists'
        ],
        'password' => [
            'required' => 'Password is required',
            'min_length' => 'Password must be at least 6 characters'
        ]
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $beforeInsert = ['hashPassword'];
    protected $beforeUpdate = ['hashPassword'];

    /**
     * Hash password before insert/update
     */
    protected function hashPassword(array $data)
    {
        if (isset($data['data']['password'])) {
            $data['data']['password'] = password_hash($data['data']['password'], PASSWORD_BCRYPT);
        }
        return $data;
    }

    /**
     * Verify password
     */
    public function verifyPassword($password, $hash)
    {
        return password_verify($password, $hash);
    }

    /**
     * Find user by email
     */
    public function findByEmail($email)
    {
        return $this->where('email', $email)->first();
    }

    /**
     * Find user by OAuth
     */
    public function findByOAuth($provider, $oauthId)
    {
        return $this->where([
            'oauth_provider' => $provider,
            'oauth_id' => $oauthId
        ])->first();
    }

    /**
     * Create OAuth user
     */
    public function createOAuthUser($data)
    {
        $userData = [
            'username' => $data['email'],
            'email' => $data['email'],
            'full_name' => $data['name'],
            'password' => bin2hex(random_bytes(16)),
            'oauth_provider' => $data['provider'],
            'oauth_id' => $data['oauth_id'],
            'avatar' => $data['avatar'] ?? null,
            'email_verified_at' => date('Y-m-d H:i:s'),
            'is_active' => 1,
            'role' => 'user'
        ];

        return $this->insert($userData);
    }

    /**
     * Get user without password
     */
    public function getUserSafe($id)
    {
        $user = $this->find($id);
        if ($user) {
            unset($user['password']);
        }
        return $user;
    }
}