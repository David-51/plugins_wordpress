<?php
namespace Xniris;
if (!defined('ABSPATH')) exit;
require_once __DIR__ . '/XnirisBase.php';

use Xniris\XnirisBase;
class SecretKeyManager extends XnirisBase{
    private const SALT_CONST = 'XNIRIS_API_SALT';
    private const ALGO = 'aes-256-gcm';

    public function __construct() {
        parent::__construct();
    }
    public function check_secret_key() {
        if (!defined(self::SALT_CONST)) {
            echo '<div class="notice notice-error"><h2>Xniris - Newsletter hebdo</h2><p>';
            echo '⚠️ Sécurité : ajoutez la clé dans wp-config.php :<br>';
            echo '<code>define("' . self::SALT_CONST . '", "' . esc_html(bin2hex(random_bytes(64))) . '");</code><br>';
            echo '<strong>Attention :</strong> après ajout, vos anciennes clés API seront illisibles.';
            echo '</p></div>';            
        } else if (!get_option(self::SALT_CONST)) {
            add_option(self::SALT_CONST, bin2hex(random_bytes(64)), '', false);
        }
    }

    public function get_secret_salt(): string {
        if (defined(self::SALT_CONST)) {
            return constant(self::SALT_CONST);
        }
        return get_option(self::SALT_CONST);
    }

    private function get_hex_salt(): string {
        $keyBin = hex2bin($this->get_secret_salt()); // -> s'assurer que le salt est stocké en hex
        if ($keyBin === false) {
            throw new \RuntimeException('Secret salt invalide (hex attendu).');
        }
        return $keyBin;
    }

    public function encrypt(string $plaintext): string {                

        $ivlen = openssl_cipher_iv_length(self::ALGO); // normalement 12
        $iv = random_bytes($ivlen);

        $tag = null;
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::ALGO,
            $this->get_hex_salt(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false || $tag === null || $tag === '') {
            throw new \RuntimeException('Échec du chiffrement AES-GCM.');
        }

        // concaténation : iv + tag + ciphertext
        return base64_encode($iv . $tag . $ciphertext);
    }

    public function decrypt(string $encoded): string {                

        $raw = base64_decode($encoded, true);
        if ($raw === false) {
            throw new \RuntimeException('Données chiffrées corrompues (base64 invalide).');
        }

        $ivlen = openssl_cipher_iv_length(self::ALGO); // 12
        $taglen = 16; // tag GCM = 16 octets

        if (strlen($raw) < ($ivlen + $taglen)) {
            throw new \RuntimeException('Données chiffrées trop courtes.');
        }

        $iv = substr($raw, 0, $ivlen);
        $tag = substr($raw, $ivlen, $taglen);
        $ciphertext = substr($raw, $ivlen + $taglen);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::ALGO,
            $this->get_hex_salt(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Échec du déchiffrement AES-GCM (auth tag invalide ou données corrompues).');
        }
        return $plaintext;
    }
    
    
}