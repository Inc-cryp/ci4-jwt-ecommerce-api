<?php

namespace App\Controllers\API;

use CodeIgniter\RESTful\ResourceController;
use App\Models\OrderModel;
use App\Models\OrderItemModel;
use App\Models\ProductModel;
use App\Models\UserModel;
use App\Libraries\PaymentGateway;
use App\Libraries\JWTHandler;

class PaymentController extends ResourceController
{
    protected $format = 'json';
    private $payment;
    private $jwt;
    
    public function __construct()
    {
        $this->payment = new PaymentGateway();
        $this->jwt = new JWTHandler();
    }
    
    /**
     * Convert request data to array
     */
    private function toArray($data)
    {
        if (is_object($data)) {
            return json_decode(json_encode($data), true);
        }
        if (is_string($data)) {
            return json_decode($data, true);
        }
        return $data;
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
            log_message('error', 'PaymentController::getAuthUser - ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create new transaction
     */
    public function createTransaction()
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
                'order_items' => 'required',
                'payment_method' => 'required'
            ];
            
            if (!$this->validate($rules)) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Validation errors',
                    'errors' => $this->validator->getErrors()
                ], 400);
            }
            
            $orderItems = $this->toArray($this->request->getVar('order_items'));
            $paymentMethod = $this->request->getVar('payment_method');
            
            if (empty($orderItems) || !is_array($orderItems)) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Order items must be a non-empty array'
                ], 400);
            }
            
            // Get user details
            $userModel = new UserModel();
            $user = $userModel->getUserSafe($userId);
            
            if (!$user) {
                return $this->failNotFound(
                    'User not found'
                );
            }
            
            // Calculate total and prepare items
            $productModel = new ProductModel();
            $totalAmount = 0;
            $items = [];
            $orderItemsData = [];
            
            foreach ($orderItems as $itemData) {
                $item = $this->toArray($itemData);
                
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
                
                $items[] = [
                    'id' => $product['id'],
                    'price' => (int) $product['price'],
                    'quantity' => (int) $item['quantity'],
                    'name' => $product['name']
                ];
                
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
                'payment_method' => $paymentMethod,
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
                $orderModel->delete($orderId);
                return $this->fail([
                    'status' => false,
                    'message' => 'Failed to create order items'
                ], 500);
            }
            
            // Create payment transaction
            $customerDetails = [
                'first_name' => $user['full_name'],
                'email' => $user['email'],
                'phone' => $user['phone'] ?? ''
            ];
            
            $paymentResult = $this->payment->createTransaction(
                $orderNumber,
                (int) $totalAmount,
                $customerDetails,
                $items
            );
            
            if (!$paymentResult['success']) {
                $orderModel->update($orderId, [
                    'payment_status' => 'failed'
                ]);
                
                return $this->fail([
                    'status' => false,
                    'message' => 'Failed to create payment transaction',
                    'error' => $paymentResult['message']
                ], 500);
            }
            
            // Update order with payment info
            $orderModel->update($orderId, [
                'snap_token' => $paymentResult['snap_token'],
                'payment_url' => $paymentResult['redirect_url']
            ]);
            
            // Update product stock
            foreach ($orderItems as $itemData) {
                $item = $this->toArray($itemData);
                $productModel->updateStock($item['product_id'], $item['quantity']);
            }
            
            return $this->respondCreated([
                'status' => true,
                'message' => 'Transaction created successfully',
                'data' => [
                    'order_id' => $orderId,
                    'order_number' => $orderNumber,
                    'amount' => $totalAmount,
                    'snap_token' => $paymentResult['snap_token'],
                    'payment_url' => $paymentResult['redirect_url']
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'PaymentController::createTransaction - ' . $e->getMessage());
            return $this->fail([
                'status' => false,
                'message' => 'An error occurred while creating transaction',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Handle payment notification (webhook)
     */
    public function notification()
    {
        try {
            $json = file_get_contents('php://input');
            
            if (empty($json)) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Empty notification data'
                ], 400);
            }
            
            $notificationData = json_decode($json, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Invalid JSON format'
                ], 400);
            }
            
            $result = $this->payment->handleNotification($notificationData);
            
            if (!$result['success']) {
                log_message('error', 'Payment notification error: ' . $result['message']);
                return $this->fail([
                    'status' => false,
                    'message' => 'Failed to process notification',
                    'error' => $result['message']
                ], 500);
            }
            
            $orderModel = new OrderModel();
            $updated = $orderModel->updatePaymentStatus($result['order_id'], $result['status']);
            
            if (!$updated) {
                log_message('error', 'Failed to update payment status for order: ' . $result['order_id']);
            }
            
            return $this->respond([
                'status' => true,
                'message' => 'Notification processed successfully'
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'PaymentController::notification - ' . $e->getMessage());
            return $this->fail([
                'status' => false,
                'message' => 'An error occurred while processing notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Check payment status
     */
    public function checkStatus($orderNumber = null)
    {
        try {
            $authUser = $this->getAuthUser();
            
            if (!$authUser) {
                return $this->failUnauthorized(
                    'Invalid or expired token'
                );
            }
            
            if (!$orderNumber) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Order number is required'
                ], 400);
            }
            
            $orderModel = new OrderModel();
            $order = $orderModel->where('order_number', $orderNumber)->first();
            
            if (!$order) {
                return $this->failNotFound(
                    'Order not found'
                );
            }
            
            if ($authUser->role !== 'admin' && $order['user_id'] != $authUser->user_id) {
                return $this->failForbidden(
                    'You are not authorized to view this order'
                );
            }
            
            $result = $this->payment->checkStatus($orderNumber);
            
            if (!$result['success']) {
                return $this->fail([
                    'status' => false,
                    'message' => 'Failed to check payment status',
                    'error' => $result['message']
                ], 500);
            }
            
            return $this->respond([
                'status' => true,
                'data' => [
                    'order' => $order,
                    'payment_info' => $result['data']
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'PaymentController::checkStatus - ' . $e->getMessage());
            return $this->fail([
                'status' => false,
                'message' => 'An error occurred while checking payment status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get payment history
     */
    public function history()
    {
        try {
            $authUser = $this->getAuthUser();
            
            if (!$authUser) {
                return $this->failUnauthorized(
                    'Invalid or expired token'
                );
            }
            
            $userId = $authUser->user_id;
            $page = $this->request->getGet('page') ?? 1;
            $limit = $this->request->getGet('limit') ?? 10;
            
            if (!is_numeric($page) || $page < 1) {
                $page = 1;
            }
            
            if (!is_numeric($limit) || $limit < 1 || $limit > 100) {
                $limit = 10;
            }
            
            $orderModel = new OrderModel();
            
            if ($authUser->role === 'admin') {
                $orders = $orderModel->paginate($limit, 'default', $page);
            } else {
                $orders = $orderModel->getByUser($userId, $limit, $page);
            }
            
            $pager = $orderModel->pager;
            
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
            log_message('error', 'PaymentController::history - ' . $e->getMessage());
            return $this->fail([
                'status' => false,
                'message' => 'An error occurred while fetching payment history',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}