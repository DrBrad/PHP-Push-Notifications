<?php
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);

    header('Content-Type: application/json; charset=utf-8');

    function hkdf($salt, $ikm, $info, $length){
        $prk = hash_hmac('sha256', $ikm, $salt, true);
        return mb_substr(hash_hmac('sha256', $info.chr(1), $prk, true), 0, $length, '8bit');
    }


    function base64url_encode($data){
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    function base64url_decode($data){
        return base64_decode(strtr($data, '-_', '+/'));
    }

    // Assuming you have a database connection established

    // Function to send a push notification
    function sendPushNotification($subscription, $payload){
        $parse = parse_url($subscription->endpoint);
        $url = $parse['scheme'].'://'.$parse['host'];//.pathinfo(parse_url($parse['path'], PHP_URL_PATH))['dirname'];

        require_once 'notifications/jwk.php';
    
        if(!file_exists('vapid.json')){
            throw new Exception('Vapid not defined.');
        }

        $keys = json_decode(file_get_contents('vapid.json'), true);

        $applicationJWK = new JWK();
        [$x, $y] = $applicationJWK->decode_public_key($keys['publicKey']);
        $applicationJWK->create(
            [
                'x' => $x,
                'y' => $y,
                'd' => $applicationJWK->decode_private_key($keys['privateKey'])
            ]
        );


        require_once 'notifications/jwt.php';
        $jwt = new JWT();

        $expiration = time() + (12 * 60 * 60);  // 12 hours
        $token = $jwt->generate(
            [
                'alg' => 'ES256',
                'typ' => 'JWT',
            ],
            [
                'aud' => $url,
                'exp' => $expiration,
                'sub' => 'mailto:your_email@example.com',
            ],
            $applicationJWK->private_to_pem()
        );

        print_r($token);
        echo PHP_EOL.PHP_EOL;

        $localJWK = new JWK();
        $localJWK->create();

        $userJWK = new JWK();
        [$x, $y] = $userJWK->decode_public_key($subscription->keys->p256dh);
        $userJWK->create(
            [
                'x' => $x,
                'y' => $y
            ]
        );

        $sharedSecret = openssl_pkey_derive($userJWK->public_to_pem(), $localJWK->private_to_pem(), 256);
        $sharedSecret = str_pad($sharedSecret, 32, chr(0), STR_PAD_LEFT);


        $decodedLocalPublicKey = base64url_decode($localJWK->public_to_base64());

        $salt = random_bytes(16);

        //CORRECT
        $prk = hkdf(base64url_decode($subscription->keys->auth), $sharedSecret, 'WebPush: info'.chr(0).base64url_decode($subscription->keys->p256dh).$decodedLocalPublicKey, 32);


        //CORRECT
        $cek = hkdf($salt, $prk, 'Content-Encoding: aes128gcm'.chr(0), 16);

        //CORRECT
        $nonce = hkdf($salt, $prk, 'Content-Encoding: nonce'.chr(0), 12);


        //$payload = 'Hello, world!';
        $payloadLength = mb_strlen($payload, '8bit');
        $paddingLength = 0;

        $payload = str_pad($payload.chr(2), $paddingLength+$payloadLength, chr(0), STR_PAD_RIGHT);

        $tag = '';
        $cipherText = openssl_encrypt($payload, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        $cipherText .= $tag;

        $cipherText = $salt.pack('N*', 4096).pack('C*', mb_strlen($decodedLocalPublicKey, '8bit')).$decodedLocalPublicKey.$cipherText;

        echo 'CIT: '.base64url_encode($cipherText).PHP_EOL.PHP_EOL;

        $headers = [
            'TTL: '.(60*60*12), //12-24 hours
            'Urgency: normal', //<very-low | low | normal | high>
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'Content-Length: '.mb_strlen($cipherText, '8bit'),
            'Authorization: WebPush '.$token,
            //'Encryption: salt='.base64url_encode($salt),
            //'Authorization: vapid t='.$token.',k='.$publicKey,
            //'Authorization: key=' . $subscription->keys->auth,
            'Crypto-Key: p256ecdsa='.$keys['publicKey'],//.';dh='.$localJWK->public_to_base64(),//$subscription->keys->auth,
            //'Crypto-Key: p256ecdsa='.$subscription->keys->p256dh.';dh='.$subscription->keys->auth,//$subscription->keys->p256dh,
            //'Content-Type: application/json',
        ];

        

        $options = [
            CURLOPT_URL => $subscription->endpoint,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $cipherText,
            CURLOPT_RETURNTRANSFER => true,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, $options);
        $result = curl_exec($ch);
    
        if ($result === false) {
            echo 'Error: ' . curl_error($ch) . PHP_EOL;
        } else {
            echo 'Push notification sent successfully!' . PHP_EOL;
        }

        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        echo $httpcode.PHP_EOL;

        print_r($result);
    }
    
    // Example payload
    $notificationPayload = [
        'title' => 'Netflix',
        'body' => 'Rick and Morty Season 7 is now on FlixBox',
        'icon' => '/favicon.svg',
        'image' => '41ebac8d48cc6a14bd93cb29d3681e10b89d96d7e3c5f99155b7a235ffb4ce94',
        'url' => '6418ebe766fa17796725f656'
    ];

    if(file_exists('endpoints.json')){
        $subscriptions = json_decode(file_get_contents('endpoints.json'));

        // Send push notifications to all stored subscriptions
        foreach ($subscriptions as $subscription) {
            sendPushNotification($subscription, json_encode($notificationPayload));
        }
    }

?>
