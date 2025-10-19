<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FakeInvoice extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $subscription;
    public $payment;
    public $plan;

    /**
     * Create a new message instance.
     */
    public function __construct($user, $subscription, $payment, $plan)
    {
        $this->user = $user;
        $this->subscription = $subscription;
        $this->payment = $payment;
        $this->plan = $plan;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $m = $this->subject('Factura simulada - ' . ($this->plan->name ?? 'Plan'))
            ->view('emails.fake_invoice');

        // Try to generate a PDF invoice if Dompdf is installed
        try {
            if (class_exists(\Barryvdh\DomPDF\Facade::class) || class_exists(\Dompdf\Dompdf::class)) {
                try {
                    $pdf = app()->make('dompdf.wrapper');
                    $pdf->loadView('emails.invoice', ['user' => $this->user, 'subscription' => $this->subscription, 'payment' => $this->payment, 'plan' => $this->plan]);
                    $m->attachData($pdf->output(), 'invoice-'.$this->payment->id.'.pdf', ['mime' => 'application/pdf']);
                } catch (\Throwable $_) {
                    // fallthrough to no-pdf
                }
            }
        } catch (\Throwable $_) {}

        return $m;
    }
}
