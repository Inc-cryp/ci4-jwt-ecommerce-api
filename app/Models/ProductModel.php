<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductModel extends Model
{
    protected $table = 'products';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'name',
        'slug',
        'description',
        'price',
        'stock',
        'category',
        'image',
        'is_active'
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    protected $validationRules = [
        'name' => 'required|min_length[3]|max_length[200]',
        'price' => 'required|numeric|greater_than[0]',
        'stock' => 'required|integer|greater_than_equal_to[0]'
    ];

    protected $validationMessages = [
        'name' => [
            'required' => 'Product name is required'
        ],
        'price' => [
            'required' => 'Price is required',
            'numeric' => 'Price must be a number',
            'greater_than' => 'Price must be greater than 0'
        ],
        'stock' => [
            'required' => 'Stock is required',
            'integer' => 'Stock must be an integer'
        ]
    ];

    protected $beforeInsert = ['generateSlug'];
    protected $beforeUpdate = ['generateSlug'];

    /**
     * Generate slug from name
     */
    protected function generateSlug(array $data)
    {
        if (isset($data['data']['name']) && !isset($data['data']['slug'])) {
            $data['data']['slug'] = url_title($data['data']['name'], '-', true);
        }
        return $data;
    }

    /**
     * Search products
     */
    public function searchProducts($keyword)
    {
        return $this->like('name', $keyword)
                    ->orLike('description', $keyword)
                    ->orLike('category', $keyword)
                    ->where('is_active', 1)
                    ->findAll();
    }

    /**
     * Get products by category
     */
    public function getByCategory($category)
    {
        return $this->where('category', $category)
                    ->where('is_active', 1)
                    ->findAll();
    }

    /**
     * Get active products with pagination
     */
    public function getActiveProducts($perPage = 10, $page = 1)
    {
        return $this->where('is_active', 1)
                    ->paginate($perPage, 'default', $page);
    }

    /**
     * Update stock
     */
    public function updateStock($productId, $quantity)
    {
        $product = $this->find($productId);
        
        if (!$product) {
            return false;
        }
        
        $newStock = $product['stock'] - $quantity;
        
        if ($newStock < 0) {
            return false;
        }
        
        return $this->update($productId, ['stock' => $newStock]);
    }

    /**
     * Check stock availability
     */
    public function checkStock($productId, $quantity)
    {
        $product = $this->find($productId);
        
        if (!$product) {
            return false;
        }
        
        return $product['stock'] >= $quantity;
    }
}