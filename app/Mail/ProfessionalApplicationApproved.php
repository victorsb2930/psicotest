<?php

namespace App\Mail;

use App\Models\ProfessionalApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProfessionalApplicationApproved extends Mailable
{
    use Queueable, SerializesModels;

    public ProfessionalApplication $application;

    public function __construct(ProfessionalApplication $application)
    {
        $this->application = $application;
    }

    public function build()
    {
        return $this->subject('Tu solicitud profesional fue aprobada')
            ->view('emails.professional_approved')
            ->with(['application' => $this->application]);
    }
}
