<?php
    class JWK {
        
        public static $curveOID = '06082a8648ce3d030107';//'1.2.840.10045.3.1.7';
        private $jwk;

        function __construct(){
        }

        public function create($jwk = null){
            if($jwk == null){
                $keyPair = openssl_pkey_new([
                    'digest_alg' => 'sha256',
                    'private_key_type' => OPENSSL_KEYTYPE_EC,
                    'curve_name' => 'prime256v1', // P-256 curve
                ]);
    
                $privateKeyDetails = openssl_pkey_get_details($keyPair);
    
                $this->jwk = [
                    'x' => hex2bin(str_pad(bin2hex($privateKeyDetails['ec']['x']), 64, '0', STR_PAD_LEFT)),
                    'y' => hex2bin(str_pad(bin2hex($privateKeyDetails['ec']['y']), 64, '0', STR_PAD_LEFT)),
                    'd' => hex2bin(str_pad(bin2hex($privateKeyDetails['ec']['d']), 64, '0', STR_PAD_LEFT))
                ];
                return;
            }

            $this->jwk = $jwk;
        }

        public function public_to_pem(){
            $der = base64_encode(hex2bin('3059301306072a8648ce3d0201'.self::$curveOID.'03420004'.bin2hex($this->jwk['x'].$this->jwk['y'])));
    
            $pem = '-----BEGIN PUBLIC KEY-----'.PHP_EOL;
            $pem .= chunk_split($der, 64, PHP_EOL);
            $pem .= '-----END PUBLIC KEY-----';

            return openssl_pkey_get_public($pem);
        }

        public function private_to_pem(){
            $der = base64_encode(hex2bin('308187020100301306072a8648ce3d0201'.self::$curveOID.'046d306b0201010420'.bin2hex($this->jwk['d']).'a14403420004'.bin2hex($this->jwk['x'].$this->jwk['y'])));

            $pem = '-----BEGIN EC PRIVATE KEY-----'.PHP_EOL;
            $pem .= chunk_split($der, 64, PHP_EOL);
            $pem .= '-----END EC PRIVATE KEY-----';

            return openssl_pkey_get_private($pem);
        }

        //PROLLY FIX THIS
        public function public_to_base64(){
            //return self::base64url_encode(0x04.$this->jwk['x'].$this->jwk['y']);
            return self::base64url_encode(hex2bin('04'.bin2hex($this->jwk['x'].$this->jwk['y'])));
        }

        public function private_to_base64(){
            return self::base64url_encode($this->jwk['d']);
        }

        public function getXandY(){
            return [
                $this->jwk['x'],
                $this->jwk['y']
            ];
        }

        public function getD(){
            return $this->jwk['d'];
        }

        public function decode_public_key($key){
            $key = bin2hex(self::base64url_decode($key));

            if(mb_substr($key, 0, 2, '8bit') !== '04'){
                throw new Exception('Invalid data: only uncompressed keys are supported.');
            }

            return [
                hex2bin(mb_substr($key, 2, 64, '8bit')),
                hex2bin(mb_substr($key, 66, null, '8bit')),
            ];
        }

        public function decode_private_key($key){
            return self::base64url_decode($key);
        }

        private function base64url_encode($data){
            return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        }

        private function base64url_decode($data){
            return base64_decode(strtr($data, '-_', '+/'));
        }
    }
?>