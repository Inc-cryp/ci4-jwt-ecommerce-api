<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderItemModel extends Model
{
    protected $table = 'order_items';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'order_id',
        'product_id',
        'product_name',
        'product_price',
        'quantity',
        'subtotal'
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Get items by order
     */
    public function getByOrder($orderId)
    {
        return $this->where('order_id', $orderId)->findAll();
    }

    /**
     * Create order items in bulk
     */
    public function createBulk($items)
    {
        return $this->insertBatch($items);
    }
}