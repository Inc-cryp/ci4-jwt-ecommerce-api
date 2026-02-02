<?php

namespace App\Controllers\API;

use CodeIgniter\RESTful\ResourceController;
use App\Models\OrderModel;
use App\Models\OrderItemModel;
use App\Models\ProductModel;
use App\Libraries\JWTHandler;

class OrderController extends ResourceController
{
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
            log_message('error', 'OrderController::getAuthUser - ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get user orders
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
            
            $userId = $authUser->user_id;
            $userRole = $authUser->role;
            $page = $this->request->getGet('page') ?? 1;
            $limit = $this->request->getGet('limit') ?? 10;
            
            // Validate pagination
            if (!is_numeric($page) || $page < 1) {
                $page = 1;
            }
            
            if (!is_numeric($limit) || $limit < 1 || $limit > 100) {
                $limit = 10;
            }
            
            $orderModel = new OrderModel();
            
            // Admin can see all orders
            if ($userRole === 'admin') {
                $orders = $orderModel->paginate($limit, 'default', $page);
            } else {
                $orders = $orderModel->getByUser($userId, $limit, $page);
            }
            
            $pager = $orderModel->pager;
            
            // Add items to each order
            $orderItemModel = new OrderItemModel();
            foreach ($orders as &$order) {
                $order['items'] = $orderItemModel->getByOrder($order['id']);
            }
            
            return $this->respond([
                'status' => true,
                'data' => $orders,
                'pagination' => [
                    'current_page' => $pager->getCurrentPage(),
                    'total_pages' => $pager->getPageCount(),
                    'per_page' => $pager->getPerPage(),
                    'total' => $pager->getTotal()
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'OrderController::index - ' . $e->getMessage());
            return $this->fail([
                'status' => false,
                'message' => 'An error occurred while fetching orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get single order
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
                    'message' => 'Valid order ID is required'
                ], 400);
            }
            
            $userId = $authUser->user_id;
            $userRole = $authUser->role;
            
            $orderModel = new OrderModel();
            $order = $orderModel->getOrderWithItems($id);
            
            if (!$order) {
                return $this->failNotFound(
                    'Order not found'
                );
            }
            
            // Check authorization
            if ($userRole !== 'admin' && $order['user_id'] != $userId) {
                return $this->failForbidden(
                    'You are not authorized to view this order'
                );
            }
            
            return $this->respond([
                'status' => true,
                'data' => $order
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'OrderController::show - ' . $e->getMessage());
            return $this->fail([
                'status' => false,
                'message' => 'An error occurred while fetching order',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Create new order (without payment - for manual orders)
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
        
        $userId = $authUser->user_id;
        
        $rules = [
            'order_items' => 'required'
        ];
        
        if (!$this->validate($rules)) {
            return $this->fail([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => $this->validator->getErrors()
            ], 400);
        }
        
        // FIX: Convert to array if it's an object
        $orderItems = $this->request->getVar('order_items');
        
        // Convert stdClass to array
        if (is_object($orderItems)) {
            $orderItems = json_decode(json_encode($orderItems), true);
        }
        
        // Also handle if it's JSON string
        if (is_string($orderItems)) {
            $orderItems = json_decode($orderItems, true);
        }
        
        if (empty($orderItems) || !is_array($orderItems)) {
            return $this->fail([
                'status' => false,
                'message' => 'Order items must be a non-empty array'
            ], 400);
        }
        
        // Calculate total and prepare items
        $productModel = new ProductModel();
        $totalAmount = 0;
        $orderItemsData = [];
        
        foreach ($orderItems as $item) {
            // Convert item to array if it's an object
            if (is_object($item)) {
                $item = (array) $item;
            }
            
            if (!isset($item['product_id']) || !isset($item['quantity'])) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Each order item must have product_id and quantity'
                ], 400);
            }
            
            if ($item['quantity'] <= 0) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Quantity must be greater than 0'
                ], 400);
            }
            
            $product = $productModel->find($item['product_id']);
            
            if (!$product) {
                return $this->failNotFound(
                    "Product with ID {$item['product_id']} not found"
                );
            }
            
            if (!$product['is_active']) {
                return $this->fail([
                    'status' => false,
                    'message' => "Product {$product['name']} is not available"
                ], 400);
            }
            
            if (!$productModel->checkStock($product['id'], $item['quantity'])) {
                return $this->fail([
                    'status' => false,
                    'message' => "Insufficient stock for {$product['name']}. Available: {$product['stock']}"
                ], 400);
            }
            
            $subtotal = $product['price'] * $item['quantity'];
            $totalAmount += $subtotal;
            
            $orderItemsData[] = [
                'product_id' => $product['id'],
                'product_name' => $product['name'],
                'product_price' => $product['price'],
                'quantity' => $item['quantity'],
                'subtotal' => $subtotal
            ];
        }
        
        // Create order
        $orderModel = new OrderModel();
        $orderNumber = $orderModel->generateOrderNumber();
        
        $orderData = [
            'order_number' => $orderNumber,
            'user_id' => $userId,
            'total_amount' => $totalAmount,
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => $this->request->getVar('payment_method') ?? 'manual',
            'notes' => $this->request->getVar('notes')
        ];
        
        $orderId = $orderModel->insert($orderData);
        
        if (!$orderId) {
            return $this->fail([
                'status' => false,
                'message' => 'Failed to create order',
                'errors' => $orderModel->errors()
            ], 500);
        }
        
        // Create order items
        foreach ($orderItemsData as &$itemData) {
            $itemData['order_id'] = $orderId;
        }
        
        $orderItemModel = new OrderItemModel();
        if (!$orderItemModel->createBulk($orderItemsData)) {
            // Rollback order
            $orderModel->delete($orderId);
            return $this->fail([
                'status' => false,
                'message' => 'Failed to create order items'
            ], 500);
        }
        
        // Update product stock
        foreach ($orderItems as $item) {
            // Convert to array if object
            if (is_object($item)) {
                $item = (array) $item;
            }
            $productModel->updateStock($item['product_id'], $item['quantity']);
        }
        
        $order = $orderModel->getOrderWithItems($orderId);
        
        return $this->respondCreated([
            'status' => true,
            'message' => 'Order created successfully',
            'data' => $order
        ]);
        
    } catch (\Exception $e) {
        log_message('error', 'OrderController::create - ' . $e->getMessage());
        return $this->fail([
            'status' => false,
            'message' => 'An error occurred while creating order',
            'error' => $e->getMessage()
        ], 500);
    }
}
    
    /**
     * Cancel order
     */
    public function cancel($id = null)
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
                    'message' => 'Valid order ID is required'
                ], 400);
            }
            
            $userId = $authUser->user_id;
            $userRole = $authUser->role;
            
            $orderModel = new OrderModel();
            $order = $orderModel->find($id);
            
            if (!$order) {
                return $this->failNotFound(
                    'Order not found'
                );
            }
            
            // Check authorization
            if ($userRole !== 'admin' && $order['user_id'] != $userId) {
                return $this->failForbidden(
                    'You are not authorized to cancel this order'
                );
            }
            
            // Check if order can be cancelled
            if (!in_array($order['status'], ['pending', 'processing'])) {
                return $this->fail([
                    'status' => false,
                    'message' => "Cannot cancel order with status: {$order['status']}"
                ], 400);
            }
            
            // Update order status
            $updated = $orderModel->update($id, [
                'status' => 'cancelled'
            ]);
            
            if (!$updated) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Failed to cancel order'
                ], 500);
            }
            
            // Restore product stock
            $orderItemModel = new OrderItemModel();
            $items = $orderItemModel->getByOrder($id);
            
            $productModel = new ProductModel();
            foreach ($items as $item) {
                $product = $productModel->find($item['product_id']);
                if ($product) {
                    $productModel->update($product['id'], [
                        'stock' => $product['stock'] + $item['quantity']
                    ]);
                }
            }
            
            return $this->respond([
                'status' => true,
                'message' => 'Order cancelled successfully'
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'OrderController::cancel - ' . $e->getMessage());
            return $this->fail([
                'status' => false,
                'message' => 'An error occurred while cancelling order',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update order status (Admin only)
     */
    public function updateStatus($id = null)
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
                    'Only administrators can update order status'
                );
            }
            
            if (!$id || !is_numeric($id)) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Valid order ID is required'
                ], 400);
            }
            
            $orderModel = new OrderModel();
            $order = $orderModel->find($id);
            
            if (!$order) {
                return $this->failNotFound(
                    'Order not found'
                );
            }
            
            $rules = [
                'status' => 'required|in_list[pending,processing,shipped,delivered,cancelled]'
            ];
            
            if (!$this->validate($rules)) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Validation errors',
                    'errors' => $this->validator->getErrors()
                ], 400);
            }
            
            $status = $this->request->getVar('status');
            
            // Validate status transition
            $currentStatus = $order['status'];
            $validTransitions = [
                'pending' => ['processing', 'cancelled'],
                'processing' => ['shipped', 'cancelled'],
                'shipped' => ['delivered', 'cancelled'],
                'delivered' => [],
                'cancelled' => []
            ];
            
            if (!in_array($status, $validTransitions[$currentStatus])) {
                return $this->fail([
                    'status' => false,
                    'message' => "Cannot change order status from '{$currentStatus}' to '{$status}'"
                ], 400);
            }
            
            $updated = $orderModel->update($id, ['status' => $status]);
            
            if (!$updated) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Failed to update order status'
                ], 500);
            }
            
            return $this->respond([
                'status' => true,
                'message' => 'Order status updated successfully',
                'data' => [
                    'order_id' => $id,
                    'old_status' => $currentStatus,
                    'new_status' => $status
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'OrderController::updateStatus - ' . $e->getMessage());
            return $this->fail([
                'status' => false,
                'message' => 'An error occurred while updating order status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}