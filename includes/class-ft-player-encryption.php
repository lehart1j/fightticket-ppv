<?php
class FT_Player_Encryption {
    private $key;
    private $cipher = 'aes-256-cbc';

    public function __construct() {
        $this->key = $this->get_encryption_key();
    }

    private function get_encryption_key() {
        $key = get_option('ft_player_encryption_key');
        if (!$key) {
            $key = bin2hex(random_bytes(32));
            update_option('ft_player_encryption_key', $key);
        }
        return hex2bin($key);
    }

    public function encrypt($data) {
        $ivlen = openssl_cipher_iv_length($this->cipher);
        $iv = openssl_random_pseudo_bytes($ivlen);
        $encrypted = openssl_encrypt($data, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    public function decrypt($data) {
        $data = base64_decode($data);
        $ivlen = openssl_cipher_iv_length($this->cipher);
        $iv = substr($data, 0, $ivlen);
        $encrypted = substr($data, $ivlen);
        return openssl_decrypt($encrypted, $this->cipher, $this->key, OPENSSL_RAW_DATA, $iv);
    }

    private function is_encrypted($data) {
        // Check if the data looks like base64 encoded encrypted data
        if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $data)) {
            return false;
        }

        // Try to decode and check length
        $decoded = base64_decode($data);
        if ($decoded === false) {
            return false;
        }

        // Check if decoded length is at least IV length + 16 bytes (minimum AES block)
        $min_length = openssl_cipher_iv_length($this->cipher) + 16;
        return strlen($decoded) >= $min_length;
    }

    // Helper function to re-encrypt data in new format
    public function reencrypt($data) {
        $decrypted = $this->decrypt($data);
        if (!empty($decrypted)) {
            return $this->encrypt($decrypted);
        }
        return '';
    }
} 