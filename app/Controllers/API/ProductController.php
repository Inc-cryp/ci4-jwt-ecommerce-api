<?php

namespace App\Controllers\API;

use CodeIgniter\RESTful\ResourceController;
use App\Models\ProductModel;
use App\Libraries\JWTHandler;

class ProductController extends ResourceController
{
    protected $modelName = 'App\Models\ProductModel';
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
            log_message('error', 'ProductController::getAuthUser - ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get all products with pagination and filters
     */
    public function index()
    {
        try {
            $authUser = $this->getAuthUser();
            
            if (!$authUser) {
                return $this->failUnauthorized(
                    'Invalid or expired token'
                );
            }
            
            $page = $this->request->getGet('page') ?? 1;
            $limit = $this->request->getGet('limit') ?? 10;
            $search = $this->request->getGet('search');
            $category = $this->request->getGet('category');
            
            // Validate pagination
            if (!is_numeric($page) || $page < 1) {
                $page = 1;
            }
            
            if (!is_numeric($limit) || $limit < 1 || $limit > 100) {
                $limit = 10;
            }
            
            $builder = $this->model->where('is_active', 1);
            
            if ($search) {
                $builder->groupStart()
                        ->like('name', $search)
                        ->orLike('description', $search)
                        ->groupEnd();
            }
            
            if ($category) {
                $builder->where('category', $category);
            }
            
            $products = $builder->paginate($limit, 'default', $page);
            $pager = $this->model->pager;
            
            return $this->respond([
                'status' => true,
                'data' => $products,
                'pagination' => [
                    'current_page' => $pager->getCurrentPage(),
                    'total_pages' => $pager->getPageCount(),
                    'per_page' => $pager->getPerPage(),
                    'total' => $pager->getTotal()
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'ProductController::index - ' . $e->getMessage());
            return $this->fail([
                'status' => false,
                'message' => 'An error occurred while fetching products',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get single product
     */
    public function show($id = null)
    {
        try {
            $authUser = $this->getAuthUser();
            
            if (!$authUser) {
                return $this->failUnauthorized(
                    'Invalid or expired token'
                );
            }
            
            if (!$id || !is_numeric($id)) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Valid product ID is required'
                ], 400);
            }
            
            $product = $this->model->find($id);
            
            if (!$product) {
                return $this->failNotFound(
                    'Product not found'
                );
            }
            
            return $this->respond([
                'status' => true,
                'data' => $product
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'ProductController::show - ' . $e->getMessage());
            return $this->fail([
                'status' => false,
                'message' => 'An error occurred while fetching product',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Create new product (Admin only)
     */
    public function create()
    {
        try {
            $authUser = $this->getAuthUser();
            
            if (!$authUser) {
                return $this->failUnauthorized(
                    'Invalid or expired token'
                );
            }
            
            // Check if user is admin
            if ($authUser->role !== 'admin') {
                return $this->failForbidden(
                    'Only administrators can create products'
                );
            }
            
            $rules = [
                'name' => 'required|min_length[3]|max_length[200]',
                'price' => 'required|numeric|greater_than[0]',
                'stock' => 'required|integer|greater_than_equal_to[0]'
            ];
            
            if (!$this->validate($rules)) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Validation errors',
                    'errors' => $this->validator->getErrors()
                ], 400);
            }
            
            $data = [
                'name' => $this->request->getVar('name'),
                'description' => $this->request->getVar('description'),
                'price' => $this->request->getVar('price'),
                'stock' => $this->request->getVar('stock'),
                'category' => $this->request->getVar('category'),
                'image' => $this->request->getVar('image'),
                'is_active' => 1
            ];
            
            $productId = $this->model->insert($data);
            
            if (!$productId) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Failed to create product',
                    'errors' => $this->model->errors()
                ], 500);
            }
            
            $product = $this->model->find($productId);
            
            return $this->respondCreated([
                'status' => true,
                'message' => 'Product created successfully',
                'data' => $product
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'ProductController::create - ' . $e->getMessage());
            return $this->fail([
                'status' => false,
                'message' => 'An error occurred while creating product',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update product (Admin only)
     */
    public function update($id = null)
    {
        try {
            $authUser = $this->getAuthUser();
            
            if (!$authUser) {
                return $this->failUnauthorized(
                    'Invalid or expired token'
                );
            }
            
            // Check if user is admin
            if ($authUser->role !== 'admin') {
                return $this->failForbidden(
                    'Only administrators can update products'
                );
            }
            
            if (!$id || !is_numeric($id)) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Valid product ID is required'
                ], 400);
            }
            
            $product = $this->model->find($id);
            
            if (!$product) {
                return $this->failNotFound(
                    'Product not found'
                );
            }
            
            $data = [];
            
            if ($this->request->getVar('name')) {
                $data['name'] = $this->request->getVar('name');
            }
            if ($this->request->getVar('description') !== null) {
                $data['description'] = $this->request->getVar('description');
            }
            if ($this->request->getVar('price')) {
                if (!is_numeric($this->request->getVar('price')) || $this->request->getVar('price') <= 0) {
                    return $this->fail([
                        'status' => false,
                        'message' => 'Price must be a positive number'
                    ], 400);
                }
                $data['price'] = $this->request->getVar('price');
            }
            if ($this->request->getVar('stock') !== null) {
                if (!is_numeric($this->request->getVar('stock')) || $this->request->getVar('stock') < 0) {
                    return $this->fail([
                        'status' => false,
                        'message' => 'Stock must be a non-negative integer'
                    ], 400);
                }
                $data['stock'] = $this->request->getVar('stock');
            }
            if ($this->request->getVar('category')) {
                $data['category'] = $this->request->getVar('category');
            }
            if ($this->request->getVar('image') !== null) {
                $data['image'] = $this->request->getVar('image');
            }
            if ($this->request->getVar('is_active') !== null) {
                $data['is_active'] = $this->request->getVar('is_active') ? 1 : 0;
            }
            
            if (empty($data)) {
                return $this->fail([
                    'status' => false,
                    'message' => 'No data to update'
                ], 400);
            }
            
            $updated = $this->model->update($id, $data);
            
            if (!$updated) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Failed to update product',
                    'errors' => $this->model->errors()
                ], 500);
            }
            
            $updatedProduct = $this->model->find($id);
            
            return $this->respond([
                'status' => true,
                'message' => 'Product updated successfully',
                'data' => $updatedProduct
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'ProductController::update - ' . $e->getMessage());
            return $this->fail([
                'status' => false,
                'message' => 'An error occurred while updating product',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Delete product (Admin only)
     */
    public function delete($id = null)
    {
        try {
            $authUser = $this->getAuthUser();
            
            if (!$authUser) {
                return $this->failUnauthorized(
                    'Invalid or expired token'
                );
            }
            
            // Check if user is admin
            if ($authUser->role !== 'admin') {
                return $this->failForbidden(
                    'Only administrators can delete products'
                );
            }
            
            if (!$id || !is_numeric($id)) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Valid product ID is required'
                ], 400);
            }
            
            $product = $this->model->find($id);
            
            if (!$product) {
                return $this->failNotFound(
                    'Product not found'
                );
            }
            
            $deleted = $this->model->delete($id);
            
            if (!$deleted) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Failed to delete product'
                ], 500);
            }
            
            return $this->respondDeleted([
                'status' => true,
                'message' => 'Product deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'ProductController::delete - ' . $e->getMessage());
            return $this->fail([
                'status' => false,
                'message' => 'An error occurred while deleting product',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}