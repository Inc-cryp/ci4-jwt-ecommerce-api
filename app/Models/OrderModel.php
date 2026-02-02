<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderModel extends Model
{
    protected $table = 'orders';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $protectFields = true;
    protected $allowedFields = [
        'order_number',
        'user_id',
        'total_amount',
        'status',
        'payment_status',
        'payment_method',
        'snap_token',
        'payment_url',
        'notes'
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    /**
     * Generate unique order number
     */
    public function generateOrderNumber()
    {
        return 'ORD-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    /**
     * Get orders by user
     */
    public function getByUser($userId, $perPage = 10, $page = 1)
    {
        return $this->where('user_id', $userId)
                    ->orderBy('created_at', 'DESC')
                    ->paginate($perPage, 'default', $page);
    }

    /**
     * Get order with items
     */
    public function getOrderWithItems($orderId)
    {
        $order = $this->find($orderId);
        
        if (!$order) {
            return null;
        }
        
        $orderItemModel = new \App\Models\OrderItemModel();
        $order['items'] = $orderItemModel->getByOrder($orderId);
        
        return $order;
    }

    /**
     * Update order status
     */
    public function updateOrderStatus($orderId, $status)
    {
        return $this->update($orderId, ['status' => $status]);
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus($orderNumber, $paymentStatus, $additionalData = [])
    {
        $order = $this->where('order_number', $orderNumber)->first();
        
        if (!$order) {
            return false;
        }
        
        $updateData = array_merge([
            'payment_status' => $paymentStatus
        ], $additionalData);
        
        // If payment is success, update order status
        if ($paymentStatus === 'success') {
            $updateData['status'] = 'processing';
        } else if ($paymentStatus === 'failed') {
            $updateData['status'] = 'cancelled';
        }
        
        return $this->update($order['id'], $updateData);
    }
}