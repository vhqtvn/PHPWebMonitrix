<?php

namespace App\Framework;

use GuzzleHttp\Client;

class Notifier
{
    private static array $config = [];

    public static function setConfig(array $config)
    {
        self::$config = $config;
    }

    public static function send(string $message, array $options = []): void
    {
        self::sendTelegram($message, $options);

        if (!empty(self::$config['email'])) {
            self::sendEmail($message, $options);
        }

        if (!empty(self::$config['webhook'])) {
            self::sendWebhook($message, $options);
        }
    }

    private static function sendEmail(string $message, array $options = []): void
    {
        // Implementation for email sending
        // This is a placeholder - you would implement your email sending logic here
        $to = self::$config['email']['to'] ?? null;
        if ($to) {
            mail($to, $options['subject'] ?? 'Monitor Notification', $message);
        }
    }

    private static function sendTelegram(string $message, array $options = []): void
    {
        $client = new Client();
        $client->post("https://vhn.vn/msg:vh", [
            'json' => [
                'text' => $message,
            ] + ($options['telegram'] ?? [])
        ]);
    }

    private static function sendWebhook(string $message, array $options = []): void
    {
        $webhookUrl = self::$config['webhook']['url'] ?? null;

        if ($webhookUrl) {
            $client = new Client();
            $client->post($webhookUrl, [
                'json' => [
                    'message' => $message,
                    'options' => $options
                ]
            ]);
        }
    }
}
