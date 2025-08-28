<?php
namespace Xniris;

if (!defined('ABSPATH')) exit;
require_once plugin_dir_path(__FILE__) . 'XnirisBase.php';
require_once plugin_dir_path(__FILE__) . 'SecretKeyManager.php';

class ApiKeyManager extends XnirisBase {

    private ?bool $check_api_key = null;

    public function __construct(
        private readonly SecretKeyManager $secretKeyManager,
        private readonly BrevoClient $client
        ) {
        parent::__construct();
    }

    public function get_encrypted_api_key(): string {
        $stored_options = get_option(self::OPT_KEY, []); // tout le tableau d'options
        return $stored_options['api_key'] ?? '';
    }

    public function get_decrypted_api_key(): ?string {
        $encrypted_key = $this->get_encrypted_api_key();
            
        try {
            return $this->secretKeyManager->decrypt($encrypted_key);
        } catch (\Exception $e) {
            error_log('Erreur déchiffrement clé API : ' . $e->getMessage());
            return '';
        }
    }
    public function is_valid_api_key(string $decrypted_api_key): bool {        
        try{                        
            $this->client->set_apiKey($decrypted_api_key);
            $this->client->request_test();
        }catch(\Exception $e){
            $this->check_api_key = false;
            return false;
        }        
        $this->check_api_key = true;        
        return true; 
    }
    public function test_api_key(): bool|string {
        
        $encrypted_key = $this->get_encrypted_api_key();        
        if(empty($encrypted_key)) return 'empty';
                
        $decrypted_key = $this->get_decrypted_api_key();        
        if(empty($decrypted_key)) return 'crypt_error';
        
        return $this->is_valid_api_key($decrypted_key);
    }

    
}