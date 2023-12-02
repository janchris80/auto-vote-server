<?php

namespace App\Jobs;

use App\Models\User;
use App\Traits\DiscordTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendDiscordNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, DiscordTrait;

    private $user;
    private $fields;
    private $message;

    public function __construct($userId, $fields, $message)
    {
        $this->fields = $fields;
        $this->user = User::find($userId);
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $discordWebhookUrl = $this->user->discord_webhook_url;
        $username = $this->user->username;

        $this->autoVoteNotification(
            $discordWebhookUrl,
            $username,
            $this->message,
            $this->fields
        );
    }
}
