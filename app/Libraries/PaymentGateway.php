<?php

namespace App\Libraries;

use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction;
use Midtrans\Notification;

class PaymentGateway
{
    public function __construct()
    {
        // Set Midtrans configuration
        Config::$serverKey = getenv('midtrans.server_key');
        Config::$isProduction = getenv('midtrans.is_production') === 'true';
        Config::$isSanitized = getenv('midtrans.is_sanitized') === 'true';
        Config::$is3ds = getenv('midtrans.is_3ds') === 'true';
    }
    
    /**
     * Create transaction
     */
    public function createTransaction($orderId, $amount, $customerDetails, $items)
    {
        try {
            // Check if Midtrans is configured
            $serverKey = getenv('midtrans.server_key');
            
            // If no Midtrans key, return mock response for development
            if (empty($serverKey) || $serverKey === 'your-midtrans-server-key') {
                log_message('warning', 'Midtrans not configured. Using mock response.');
                
                return [
                    'success' => true,
                    'snap_token' => 'mock-snap-token-' . uniqid(),
                    'redirect_url' => 'https://simulator.sandbox.midtrans.com/mock-payment/' . $orderId
                ];
            }
            
            // Real Midtrans integration
            Config::$serverKey = $serverKey;
            Config::$isProduction = getenv('midtrans.is_production') === 'true';
            Config::$isSanitized = getenv('midtrans.is_sanitized') === 'true';
            Config::$is3ds = getenv('midtrans.is_3ds') === 'true';

            $params = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => $amount
                ],
                'customer_details' => $customerDetails,
                'item_details' => $items,
                'enabled_payments' => [
                    'credit_card',
                    'mandiri_clickpay',
                    'cimb_clicks',
                    'bca_klikbca',
                    'bca_klikpay',
                    'bri_epay',
                    'echannel',
                    'permata_va',
                    'bca_va',
                    'bni_va',
                    'other_va',
                    'gopay',
                    'indomaret',
                    'alfamart',
                    'danamon_online',
                    'akulaku'
                ]
            ];
            
            $snapToken = Snap::getSnapToken($params);
            
            return [
                'success' => true,
                'snap_token' => $snapToken,
                'redirect_url' => Config::$isProduction 
                    ? 'https://app.midtrans.com/snap/v2/vtweb/' . $snapToken
                    : 'https://app.sandbox.midtrans.com/snap/v2/vtweb/' . $snapToken
            ];
            
        } catch (\Exception $e) {
            log_message('error', 'Midtrans Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Handle notification from Midtrans
     */
    public function handleNotification($notificationData)
    {
        try {
            $notification = new Notification();
            
            $transactionStatus = $notification->transaction_status;
            $fraudStatus = $notification->fraud_status;
            $orderId = $notification->order_id;
            $grossAmount = $notification->gross_amount;
            
            $status = 'pending';
            
            if ($transactionStatus == 'capture') {
                if ($fraudStatus == 'challenge') {
                    $status = 'challenge';
                } else if ($fraudStatus == 'accept') {
                    $status = 'success';
                }
            } else if ($transactionStatus == 'settlement') {
                $status = 'success';
            } else if ($transactionStatus == 'cancel' || 
                       $transactionStatus == 'deny' || 
                       $transactionStatus == 'expire') {
                $status = 'failed';
            } else if ($transactionStatus == 'pending') {
                $status = 'pending';
            }
            
            return [
                'success' => true,
                'order_id' => $orderId,
                'status' => $status,
                'transaction_status' => $transactionStatus,
                'fraud_status' => $fraudStatus,
                'gross_amount' => $grossAmount,
                'payment_type' => $notification->payment_type ?? null,
                'transaction_time' => $notification->transaction_time ?? null
            ];
            
        } catch (\Exception $e) {
            log_message('error', 'Notification Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check transaction status
     */
    public function checkStatus($orderId)
    {
        try {
            $status = Transaction::status($orderId);
            
            return [
                'success' => true,
                'data' => $status
            ];
            
        } catch (\Exception $e) {
            log_message('error', 'Status Check Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Cancel transaction
     */
    public function cancelTransaction($orderId)
    {
        try {
            $result = Transaction::cancel($orderId);
            
            return [
                'success' => true,
                'data' => $result
            ];
            
        } catch (\Exception $e) {
            log_message('error', 'Cancel Transaction Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}