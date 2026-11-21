<?php

namespace App\Notifications\Api;

use App\Models\EmailVerificationCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * アプリ（API）向けの、メール認証コードと認証リンクを知らせる通知。
 * Laravel 標準の認証メールはリンクだけで、アプリの会員では使えないため、別に用意している。
 */
class VerificationCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $code, public string $verificationUrl) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('メールアドレスの確認コード')
            ->line('アプリの画面に、次の確認コードを入力してください。')
            ->line("確認コード: {$this->code}")
            ->line('このコードは'.EmailVerificationCode::EXPIRES_IN_MINUTES.'分間有効です。')
            ->line('アプリを使っている端末でこのメールを開いている場合は、次のボタンからも認証できます。')
            ->action('メールアドレスを認証する', $this->verificationUrl)
            ->line('心当たりがない場合は、このメールを破棄してください。');
    }
}
