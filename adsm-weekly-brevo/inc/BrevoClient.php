<?php
if (!defined('ABSPATH')) exit;
class BrevoClient {
    private string $apiKey;
    private string $apiBase = 'https://api.brevo.com/v3/';

    /**
     * Summary of __construct
     * @param string $apiKey Brevo API KEY
     */

    public function __construct(private readonly string $OPT_KEY, string $apiKey = '') {
        $this->apiKey = $apiKey;        
    }

    public function get_apiKey(): string {
        $options = get_option($this->OPT_KEY);
            $this->apiKey = $options['api_key'] ?? '';
        return $this->apiKey;
    }
    public function set_apiKey(string $apiKey): void {
        $this->apiKey = $apiKey;
    }

    public function get_apiBase(): string {
        return $this->apiBase;
    }    

    /**
     * Summary of request
     * @param string $endpoint endpoint ...
     * @param array $data
     * @param string $method default GET, GET|POST|PUT|DELETE
     * @throws \Exception
     * @return array
     */
    private function request(string $endpoint, array $body = [], string $method = 'GET'): ?array     {
        $url = $this->apiBase . ltrim($endpoint, '/');

        $args = [
            'method'  => $method,
            'headers' => [
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
                'api-key'      => $this->apiKey,
            ],
            'timeout' => 30,
        ];

        if (!empty($body)) {
            if ($method === 'GET') {
                $url = add_query_arg($body, $url);
            } else {
                $args['body'] = json_encode($body);
            }
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            // display error in admin            
            throw new \Exception('Brevo Client : ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $data = wp_remote_retrieve_body($response);
        $decoded_data = json_decode($data, true);

        if ($status_code < 200 || $status_code >= 300) {
            $error_message = isset($decoded_data['message']) ? $decoded_data['message'] : 'Unknown error';
            throw new \Exception("API Error ({$status_code}): " . $error_message);
        }

        return $decoded_data;
    }
    public function get_senders(): array {
        return $this->request( 'senders');
    }

    public function create_list($name): array {
        $payload = [
            'name'        => $name,            
        ];

        return $this->request('contacts/lists', $payload, 'POST');
    }

    public function create_test_list(): array{
        return $this->create_list('Plugin WordPress Test Liste');
    }

    public function get_lists(): array {
        return $this->request("contacts/lists/");
    }

    public function get_list_details($list_id): array {
        return $this->request("contacts/lists/$list_id");
    }

    public function get_contacts_list($list_id): array {
        return $this->request("contacts/lists/$list_id/contacts");
    }

    public function get_contact($email): array {
        $encoded_email = rawurlencode($email);
        return $this->request("contacts/$encoded_email");
    }

    /**
     * Summary of create_contact
     * @param mixed $email
     * @param mixed $attributes
     * @return array an array containing the id ['id'= int ]
     */
    public function create_contact($email, $attributes = []): array {
        $payload = [
            'email' => $email,
            'attributes' => (object)$attributes,
        ];

        return $this->request( 'contacts', $payload, 'POST');
    }

    public function add_contacts_to_list(array $contact_ids, int $list_id): array {
        $payload = [
            'ids' => $contact_ids
        ];

        return $this->request("contacts/lists/$list_id/contacts/add", $payload, 'POST');
    }

    public function delete_contacts_from_list(array $contact_ids, int $list_id): array {

        $payload = [
            'ids' => $contact_ids,
        ];
        return $this->request("contacts/lists/$list_id/contacts/remove", $payload, 'POST');
    }

    public function create_campaign($options, $subject, $html, $test = false) {
        $title = 'Weekly-news ' . date_i18n('Y-m-d H:i');
        $name = !$test ? $title : 'Test : ' . $title;
        $payload = [
            'name' => $name,
            'subject' => $subject,
            'sender' => [
                'name'  => $options['sender_name'],
                'email' => $options['sender_email'],
            ],
            'htmlContent' => $html,
            'recipients' => [
                'listIds' => [ intval($options['list_id']) ]
            ],
            'inlineImageActivation' => false,
            'mirrorActive' => false,
            // 'header' => '',
            // 'footer' => '',
            'utmCampaign' => 'asso-weekly',
            'tag' => 'asso-weekly',
            // on crée la campagne en "draft" et on l'enverra immédiatement après
            'scheduledAt' => gmdate('Y-m-d\TH:i:s\Z'), // ✅ format RFC3339 UTC
        ];
        if($test){
            // $payload['sendAtBestTime'] = true;
            unset($payload['scheduledAt']);
        }
        return $this->request('emailCampaigns', $payload, 'POST');

    }

    /**
     * 
     * @param mixed $campaign_id
     * @param mixed $test
     * @return bool
     */
    public function send_campaign_now($campaign_id, $test = false) {        
        if($test){
            $payload = ['emailTo' => ['grignon.david@gmail.com']]; // user in admin list --- CHANGE ---

            $this->request("emailCampaigns/{$campaign_id}/sendTest", $payload, 'POST');
            
        }else{
            $this->request("emailCampaigns/{$campaign_id}/sendNow", [], 'POST');
        }
        return true;
    }

}