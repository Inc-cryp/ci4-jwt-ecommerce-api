<?php

namespace App\Libraries;

use Exception;

class JWTHandler
{
    private $secretKey;
    private $algorithm;
    private $expire;
    
    public function __construct()
    {
        $this->secretKey = getenv('jwt.secret') ?: 'your-secret-key';
        $this->algorithm = getenv('jwt.algorithm') ?: 'HS256';
        $this->expire = getenv('jwt.expire') ?: 300;
    }

    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode($data)
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    private function sign($message)
    {
        if ($this->algorithm !== 'HS256') {
            throw new Exception('Unsupported algorithm: ' . $this->algorithm);
        }
        return hash_hmac('sha256', $message, $this->secretKey, true);
    }
    
    /**
     * Generate JWT token
     */
    public function generateToken($userId, $email, $role = 'user')
    {
        $issuedAt = time();
        $expire = $issuedAt + $this->expire;
        
        $header = [
            'alg' => $this->algorithm,
            'typ' => 'JWT'
        ];

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'iss' => base_url(),
            'data' => [
                'user_id' => $userId,
                'email' => $email,
                'role' => $role
            ]
        ];
        
        $base64UrlHeader = $this->base64UrlEncode(json_encode($header));
        $base64UrlPayload = $this->base64UrlEncode(json_encode($payload));
        $signature = $this->sign($base64UrlHeader . '.' . $base64UrlPayload);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        return $base64UrlHeader . '.' . $base64UrlPayload . '.' . $base64UrlSignature;
    }
    
    /**
     * Validate and decode JWT token
     */
    public function validateToken($token)
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                throw new Exception('Invalid token format');
            }

            list($headerB64, $payloadB64, $sigB64) = $parts;

            $headerJson = $this->base64UrlDecode($headerB64);
            $payloadJson = $this->base64UrlDecode($payloadB64);
            $signature = $this->base64UrlDecode($sigB64);

            $header = json_decode($headerJson);
            $payload = json_decode($payloadJson);

            if (!$header || !$payload) {
                throw new Exception('Invalid token payload');
            }

            if (!isset($header->alg) || $header->alg !== $this->algorithm) {
                throw new Exception('Invalid token algorithm');
            }

            $expectedSig = $this->sign($headerB64 . '.' . $payloadB64);

            if (!hash_equals($expectedSig, $signature)) {
                throw new Exception('Invalid token signature');
            }

            if (isset($payload->exp) && time() > $payload->exp) {
                throw new Exception('Token expired');
            }

            return [
                'success' => true,
                'data' => $payload->data
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get token from header
     */
    public function getTokenFromHeader()
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (empty($authHeader)) {
            return null;
        }
        
        // Bearer token format
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Refresh token
     */
    public function refreshToken($oldToken)
    {
        $validated = $this->validateToken($oldToken);
        
        if (!$validated['success']) {
            return [
                'success' => false,
                'message' => 'Invalid token'
            ];
        }
        
        $data = $validated['data'];
        $newToken = $this->generateToken(
            $data->user_id,
            $data->email,
            $data->role
        );
        
        return [
            'success' => true,
            'token' => $newToken
        ];
    }
}