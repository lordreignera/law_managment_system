<?php

namespace App\Notifications;

use App\Models\NotificationTemplate;
use App\Notifications\Channels\SmsChannel;
use App\Support\Branding;
use App\Support\Notifications\NotificationCatalog;
use App\Support\Sms\SmsMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

abstract class TemplateNotification extends Notification
{
    private ?array $resolvedTemplate = null;

    abstract protected function eventKey(): string;

    abstract protected function context(): array;

    public function notificationEventKey(): string
    {
        return $this->eventKey();
    }

    protected function action(): ?array
    {
        return null;
    }

    protected function additionalMailLines(): array
    {
        return [];
    }

    private function template(): array
    {
        return $this->resolvedTemplate ??= NotificationTemplate::where('event_key', $this->eventKey())->first()?->toArray()
            ?? (NotificationCatalog::definitions()[$this->eventKey()] + ['is_active' => true]);
    }

    public function via(object $notifiable): array
    {
        $template = $this->template();
        if (! $template['is_active']) {
            return [];
        }

        return [match ($template['channel']) {
            'email' => 'mail',
            'sms' => SmsChannel::class,
            default => 'database',
        }];
    }

    public function toArray(object $notifiable): array
    {
        $context = $this->context() + ['recipient_name' => $notifiable->name ?? '', 'company_name' => Branding::companyName()];
        $template = $this->template();

        return [
            'event_key' => $this->eventKey(),
            'subject' => NotificationCatalog::render($template['subject'] ?? '', $context),
            'body' => NotificationCatalog::render($template['body'], $context),
            'url' => $this->action()['url'] ?? null,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $content = $this->toArray($notifiable);
        $message = (new MailMessage)->subject($content['subject'])->greeting('Hello '.($notifiable->name ?? '').',');
        foreach (preg_split('/\r\n|\r|\n/', $content['body']) as $line) {
            if (trim($line) !== '') {
                $message->line($line);
            }
        }
        foreach ($this->additionalMailLines() as $line) {
            $message->line($line);
        }
        if ($action = $this->action()) {
            $message->action($action['label'], $action['url']);
        }

        return $message->salutation('Regards, '.Branding::companyName());
    }

    public function toSms(object $notifiable): SmsMessage
    {
        return SmsMessage::make($this->toArray($notifiable)['body']);
    }
}
