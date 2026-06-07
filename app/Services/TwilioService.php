<?php

namespace App\Services;

use Twilio\Rest\Client;

class TwilioService
{
    private Client $client;

    private string $from;

    public function __construct()
    {
        $this->client = new Client(
            config('services.twilio.api_key'),
            config('services.twilio.api_secret'),
            config('services.twilio.sid'),
        );

        $this->from = config('services.twilio.from');
    }

    public function sendSms(string $to, string $message): void
    {
        $this->client->messages->create($to, [
            'from' => $this->from,
            'body' => $message,
        ]);
    }
}
