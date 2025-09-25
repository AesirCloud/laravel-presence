<?php

namespace AesirCloud\Presence\Drivers;

use Illuminate\Support\Facades\Http;

class WebhookPresenceRepository extends CachePresenceRepository
{
    public function heartbeat($user, array $meta = []): void
    {
        parent::heartbeat($user, $meta);
        $this->post('heartbeat', $this->payload($user, $meta));
    }

    protected function post(string $type, array $data): void
    {
        $cfg = config('presence.webhook');
        if (!($cfg['send_on'][$type] ?? true)) return;

        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        $ts = (string)now()->getTimestamp();
        $sig = hash_hmac($cfg['algo'] ?? 'sha256', $ts . '.' . $json, $cfg['secret']);

        Http::timeout($cfg['timeout'] ?? 3)
            ->retry($cfg['retries'] ?? 1, 250)
            ->withHeaders(array_merge($cfg['headers'] ?? [], [
                'Content-Type' => 'application/json',
                    $cfg['signature_header'] ?? 'X-Presence-Signature' => "t={$ts},v1={$sig}",
            ]))
            ->post($cfg['url'], $data)
            ->throw();
    }

    public function setOnline($user, array $meta = []): void
    {
        parent::setOnline($user, $meta);
        $this->post('online', $this->payload($user, $meta));
    }

    public function setOffline($user, array $meta = []): void
    {
        parent::setOffline($user, $meta);
        $this->post('offline', $this->payload($user, $meta));
    }
}
