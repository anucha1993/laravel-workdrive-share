<?php

namespace App\Mail;

use App\Models\ShareGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/** อีเมลสรุปลิงก์ไฟล์ที่แชร์ให้ผู้รับ แยกตามหมวด */
class ShareSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param Collection<string, Collection<int, \App\Models\SharePick>> $items หมวด => รายการ */
    public function __construct(public ShareGroup $group, public Collection $items)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "เอกสารที่แชร์ให้คุณ: {$this->group->name}");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.share-summary');
    }
}
