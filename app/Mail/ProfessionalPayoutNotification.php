<?php

namespace App\Mail;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProfessionalPayoutNotification extends Mailable
{
    use Queueable, SerializesModels;

    public Payment $payment;

    /**
     * Create a new message instance.
     */
    public function __construct(Payment $payment)
    {
        $this->payment = $payment->loadMissing('recipient', 'user');
    }

    public function build()
    {
        $recipientName = optional($this->payment->recipient)->name ?? 'Profesional';
        $amount = number_format(($this->payment->amount_cents ?? 0) / 100, 2);
        $meta = $this->payment->meta ?? [];

        return $this->subject('Pago registrado en ' . config('app.name'))
            ->markdown('emails.payments.payout', [
                'payment' => $this->payment,
                'recipientName' => $recipientName,
                'amountFormatted' => $amount,
                'meta' => $meta,
            ]);
    }
}
