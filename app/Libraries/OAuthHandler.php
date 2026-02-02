<?php

namespace App\Libraries;

class OAuthHandler
{
    private $httpClient;
    
    public function __construct()
    {
        // Instantiate Guzzle if it's installed; otherwise use a small built-in fallback client.
        if (class_exists('\\GuzzleHttp\\Client')) {
            $clientClass = '\\GuzzleHttp\\Client';
            $this->httpClient = new $clientClass();
        } else {
            $this->httpClient = new MinimalHttpClient();
        }
    }
    
    /**
     * Get Google OAuth URL
     */
    public function getGoogleAuthUrl()
    {
        $clientId = getenv('oauth.google.client_id');
        $redirectUri = getenv('oauth.google.redirect_uri');
        
        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'email profile',
            'access_type' => 'offline',
            'prompt' => 'consent'
        ];
        
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
    
    /**
     * Get Google user data
     */
    public function getGoogleUserData($code)
    {
        try {
            // Exchange code for access token
            $tokenResponse = $this->httpClient->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'code' => $code,
                    'client_id' => getenv('oauth.google.client_id'),
                    'client_secret' => getenv('oauth.google.client_secret'),
                    'redirect_uri' => getenv('oauth.google.redirect_uri'),
                    'grant_type' => 'authorization_code'
                ]
            ]);
            
            $tokenData = json_decode($tokenResponse->getBody(), true);
            $accessToken = isset($tokenData['access_token']) ? $tokenData['access_token'] : null;
            
            // Get user info
            $userResponse = $this->httpClient->get('https://www.googleapis.com/oauth2/v2/userinfo', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken
                ]
            ]);
            
            return json_decode($userResponse->getBody(), true);
            
        } catch (\Exception $e) {
            log_message('error', 'Google OAuth Error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get Facebook OAuth URL
     */
    public function getFacebookAuthUrl()
    {
        $appId = getenv('oauth.facebook.app_id');
        $redirectUri = getenv('oauth.facebook.redirect_uri');
        
        $params = [
            'client_id' => $appId,
            'redirect_uri' => $redirectUri,
            'scope' => 'email,public_profile',
            'response_type' => 'code'
        ];
        
        return 'https://www.facebook.com/v12.0/dialog/oauth?' . http_build_query($params);
    }
    
    /**
     * Get Facebook user data
     */
    public function getFacebookUserData($code)
    {
        try {
            // Exchange code for access token
            $tokenResponse = $this->httpClient->get('https://graph.facebook.com/v12.0/oauth/access_token', [
                'query' => [
                    'client_id' => getenv('oauth.facebook.app_id'),
                    'client_secret' => getenv('oauth.facebook.app_secret'),
                    'redirect_uri' => getenv('oauth.facebook.redirect_uri'),
                    'code' => $code
                ]
            ]);
            
            $tokenData = json_decode($tokenResponse->getBody(), true);
            $accessToken = isset($tokenData['access_token']) ? $tokenData['access_token'] : null;
            
            // Get user info
            $userResponse = $this->httpClient->get('https://graph.facebook.com/v12.0/me', [
                'query' => [
                    'fields' => 'id,name,email,picture',
                    'access_token' => $accessToken
                ]
            ]);
            
            return json_decode($userResponse->getBody(), true);
            
        } catch (\Exception $e) {
            log_message('error', 'Facebook OAuth Error: ' . $e->getMessage());
            return null;
        }
    }
}

/**
 * MinimalHttpClient provides basic get/post methods compatible with the usage in this file.
 * It is a lightweight fallback when Guzzle is not installed.
 */
class MinimalHttpClient
{
    public function post($url, array $options = [])
    {
        $headers = '';
        $body = '';

        if (isset($options['form_params']) && is_array($options['form_params'])) {
            $body = http_build_query($options['form_params']);
            $headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
        } elseif (isset($options['body'])) {
            $body = $options['body'];
        }

        if (isset($options['headers']) && is_array($options['headers'])) {
            foreach ($options['headers'] as $k => $v) {
                $headers .= $k . ': ' . $v . "\r\n";
            }
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headers,
                'content' => $body,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        return new MinimalResponse($response === false ? '' : $response);
    }

    public function get($url, array $options = [])
    {
        $query = '';
        if (isset($options['query']) && is_array($options['query'])) {
            $q = http_build_query($options['query']);
            $separator = (parse_url($url, PHP_URL_QUERY) === null) ? '?' : '&';
            $url .= $separator . $q;
        }

        $headers = '';
        if (isset($options['headers']) && is_array($options['headers'])) {
            foreach ($options['headers'] as $k => $v) {
                $headers .= $k . ': ' . $v . "\r\n";
            }
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => $headers,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        return new MinimalResponse($response === false ? '' : $response);
    }
}

/**
 * MinimalResponse wraps a body string and exposes getBody() to match Guzzle responses usage.
 */
class MinimalResponse
{
    private $body;

    public function __construct($body)
    {
        $this->body = (string) $body;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function __toString()
    {
        return $this->body;
    }
}