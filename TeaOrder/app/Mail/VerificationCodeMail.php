<?php
// app/Mail/VerificationCodeMail.php
// 会员激活验证码邮件

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $customerName;
    public string $verificationCode;
    public string $shopName;

    /**
     * 创建新的邮件实例
     */
    public function __construct(string $customerName, string $verificationCode, string $shopName = 'xx奶茶店')
    {
        $this->customerName = $customerName;
        $this->verificationCode = $verificationCode;
        $this->shopName = $shopName;
    }

    /**
     * 获取邮件信封
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "【{$this->shopName}】会员激活验证码",
        );
    }

    /**
     * 获取邮件内容定义
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-code',
            with: [
                'customerName' => $this->customerName,
                'verificationCode' => $this->verificationCode,
                'shopName' => $this->shopName,
                'date' => now()->format('Y-m-d'),
            ],
        );
    }
}