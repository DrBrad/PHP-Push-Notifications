<?php
    class JWT {
        
        function __construct(){
        }

        function generate($headers, $payload, $privateKey){
            //$headers_encoded = self::base64url_encode(json_encode($headers));
            //$payload_encoded = self::base64url_encode(json_encode($payload));
            
            $message = self::base64url_encode(json_encode($headers)).'.'.self::base64url_encode(json_encode($payload));
            //$signature = hash_hmac('SHA256', "$headers_encoded.$payload_encoded", $secret, true);
            openssl_sign($message, $signature, $privateKey, OPENSSL_ALGO_SHA256);
            //$signature_encoded = self::base64url_encode($signature);

            $components = [];
            $pos = 0;
            $size = strlen($signature);
            while ($pos < $size) {
                $constructed = (ord($signature[$pos]) >> 5) & 0x01;
                $type = ord($signature[$pos++]) & 0x1f;
                $len = ord($signature[$pos++]);
                if ($len & 0x80) {
                    $n = $len & 0x1f;
                    $len = 0;
                    while ($n-- && $pos < $size) $len = ($len << 8) | ord($signature[$pos++]);
                }
        
                if ($type == 0x03) {
                    $pos++;
                    $components[] = substr($signature, $pos, $len - 1);
                    $pos += $len - 1;
                } else if (! $constructed) {
                    $components[] = substr($signature, $pos, $len);
                    $pos += $len;
                }
            }
            foreach ($components as &$c) $c = str_pad(ltrim($c, "\x00"), 32, "\x00", STR_PAD_LEFT);
        
            return $message . '.' . self::base64url_encode(implode('', $components));
            
            //return "$headers_encoded.$payload_encoded.$signature_encoded";
        }

        function is_valid($jwt, $publicKey){
            // split the jwt
            $tokenParts = explode('.', $jwt);
            //$header = base64_decode($tokenParts[0]);
            //$payload = base64_decode($tokenParts[1]);
        
            // check the expiration time - note this will cause an error if there is no 'exp' claim in the jwt
            $expires = json_decode(base64_decode($tokenParts[1]))->exp < time();//($expires - time()) < 0;
        
            // build a signature based on the header and payload using the secret
            //$base64_url_header = self::base64url_encode($header);
            //$base64_url_payload = self::base64url_encode($payload);
    
            $signature = openssl_verify($tokenParts[0].'.'.$tokenParts[1], base64_decode($tokenParts[2]), $publicKey, OPENSSL_ALGO_SHA256);
            //$signature = base64url_encode(hash_hmac('SHA256', $tokenParts[0].'.'.$tokenParts[1], $secret, true));
        
            // verify it matches the signature provided in the jwt
            //$is_signature_valid = ($base64_url_signature === $tokenParts[2]);
            
            if($expires || !$signature){
                return false;
            }
            return true;
        }

        private function base64url_encode($data){
            return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        }

        private function base64url_decode($data){
            return base64_decode(strtr($data, '-_', '+/'));
        }
    }
?>