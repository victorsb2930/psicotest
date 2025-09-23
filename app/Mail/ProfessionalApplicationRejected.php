<?php

namespace App\Mail;

use App\Models\ProfessionalApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProfessionalApplicationRejected extends Mailable
{
    use Queueable, SerializesModels;

    public ProfessionalApplication $application;

    public function __construct(ProfessionalApplication $application)
    {
        $this->application = $application;
    }

    public function build()
    {
        return $this->subject('Tu solicitud profesional fue rechazada')
            ->view('emails.professional_rejected')
            ->with(['application' => $this->application]);
    }
}
