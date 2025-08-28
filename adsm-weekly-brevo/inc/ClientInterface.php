<?php

namespace Xniris;
if (!defined('ABSPATH')) exit;
interface ClientInterface {
    
    public function request(string $endpoint, array $body = [], string $method = 'GET'): ?array;
    public function request_test(): ?array;
    
    public function set_apiKey(string $apiKey): void;
    public function get_senders(): array;

    public function create_list($name): array;

    public function create_test_list(): array;
    public function get_lists(): array;
    public function get_list_details($list_id);
    public function get_contacts_list($list_id): array;
    public function get_contact(mixed $identifiant): array;

    public function create_contact($email, $attributes = []): array;
    public function add_contacts_to_list(array $contact_ids, int $list_id): array;
    public function delete_contacts_from_list(array $contact_ids, int $list_id): array;
    public function create_campaign($options, $subject, $html, $test = false):?array;
    public function send_campaign_now($campaign_id, $test = false);

}