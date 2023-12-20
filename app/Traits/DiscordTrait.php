<?php

namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

trait DiscordTrait
{
    protected function autoVoteNotification(string $webhookUrl, string $username, string $message = '', array $fields = [])
    {
        // Define colors
        $infoColor = 0x3498db; // Blue
        $warningColor = 0xf1c40f; // Yellow

        // Determine the color based on the message type
        $color = str_contains($message, 'WARNING') ? $warningColor : $infoColor;

        // Create the embed
        $embed = [
            'color' => 0x0099ff,
            'title' => 'Auto.vote Notification',
            'url' => 'https://d.buzz',
            'author' => [
                'name' => $username,
                'icon_url' => 'https://i.imgur.com/AfFp7pu.png',
                'url' => 'https://hiveblocks.com/@' . $username,
            ],
            'description' => $message ?? 'Check your setting in auto.vote',
            'thumbnail' => [
                'url' => 'https://images.hive.blog/DQmX3jshg31YHPubSaUc4cLr5rfhYK8ye4iRFKt4LDCgKTX/dBuzz%20circle%20logo.jpg',
            ],
            'fields' => $fields,
            // 'image' => [
            //     'url' => 'https://i.imgur.com/AfFp7pu.png',
            // ],
            'timestamp' => now()->toIso8601String(), // Equivalent to new Date().toISOString()
            'footer' => [
                'text' => 'Sent via auto.vote',
                'icon_url' => 'https://images.hive.blog/DQmX3jshg31YHPubSaUc4cLr5rfhYK8ye4iRFKt4LDCgKTX/dBuzz%20circle%20logo.jpg',
                // 'button' => [
                //     'text' => 'Click me!',
                //     'url' => 'https://example.com',
                // ],
            ],
        ];

        $component = [
            [
                'type' => 1,
                'components' => [
                    [
                        'style' => 5,
                        'label' => 'Visit Auto.vote',
                        'url' => 'https://d.buzz',
                        'disabled' => false,
                        'type' => 2,
                    ],
                ],
            ]
        ];

        $response = Http::post($webhookUrl, [
            'username' => 'Auto.vote - Log bot',
            'embeds' => [$embed],
            'components' => $component,
        ]);

        if ($response->failed()) {
            Log::error('Discord webhook failed: ' . $response->body());
        }

        if ($response->successful()) {
            Log::error('Discord webhook successful: ' . $response->body());
        }
    }
}
