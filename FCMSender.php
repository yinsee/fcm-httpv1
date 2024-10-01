<?php

namespace App\Services;

class FCMSender
{
    private $credentials;
    public function __construct()
    {
        // Path to your service account credentials JSON file
        $googleCredentialsPath = env('GOOGLE_APPLICATION_CREDENTIALS');

        // Read and decode the JSON credentials
        $this->credentials = json_decode(file_get_contents($googleCredentialsPath), true);
    }

    // Function to base64 encode without padding (JWT requirement)
    public function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function getBearer()
    {

        // JWT header
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        // JWT claim set (payload)
        $now = time();
        $expiry = $now + 3600; // Token valid for 1 hour
        $audience = $this->credentials['token_uri'];

        $claims = [
            'iss' => $this->credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging', // Adjust scopes as needed
            'aud' => $audience,
            'iat' => $now,
            'exp' => $expiry,
        ];

        // Encode header and claims
        $encodedHeader = $this->base64UrlEncode(json_encode($header));
        $encodedClaims = $this->base64UrlEncode(json_encode($claims));

        // Create the signature
        $privateKey = $this->credentials['private_key'];
        $dataToSign = $encodedHeader . '.' . $encodedClaims;
        openssl_sign($dataToSign, $signature, $privateKey, 'SHA256');

        // Encode the signature
        $encodedSignature = $this->base64UrlEncode($signature);

        // Combine header, payload, and signature to form the JWT
        $jwt = $encodedHeader . '.' . $encodedClaims . '.' . $encodedSignature;

        // cURL request to Google's OAuth 2.0 token endpoint
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->credentials['token_uri']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        // Decode and display the access token from the response
        $responseData = json_decode($response, true);
        if (isset($responseData['access_token'])) {
            $accessToken = $responseData['access_token'];
            // echo 'Access Token: ' . $accessToken;
        } else {
            throw new \Exception('Error fetching access token: ' . $response);
        }
        return $accessToken;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function send($topic, $title, $body, $sound, $channel)
    {

        $data = [
            'message' => [
                'topic' => $topic,

                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],

                // 'data' => [],

                'android' => [
                    'notification' => [
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'channel_id' => $channel,
                        'sound' => $sound,
                    ],
                ],

                'apns' => [
                    'payload' => [
                        'aps' => [
                            'category' => 'FLUTTER_NOTIFICATION_CLICK',
                            'sound' => $sound,
                        ],
                    ],
                ],
                // 'data' => [],
            ],
        ];
        $ch = curl_init();
        $token = $this->getBearer();
        curl_setopt($ch, CURLOPT_URL, 'https://fcm.googleapis.com/v1/projects/' . $this->credentials['project_id'] . '/messages:send');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); //Post Fields
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Authorization: Bearer ' . $token));
        $output = curl_exec($ch);
        curl_close($ch);

        // write payload and respond to log file at storage/logs/fcm.log
        file_put_contents(storage_path('logs/fcm.log'), '[' . date('c') . '] Request: ' . json_encode($data, JSON_PRETTY_PRINT), FILE_APPEND);
        file_put_contents(storage_path('logs/fcm.log'), '[' . date('c') . '] Respond: ' . $output, FILE_APPEND);

        return $output;
    }
}
