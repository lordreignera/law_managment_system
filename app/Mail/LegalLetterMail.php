<?php

namespace App\Mail;

use App\Models\LegalLetter;
use Illuminate\Mail\Mailable;

class LegalLetterMail extends Mailable
{
    public function __construct(
        public LegalLetter $letter,
        public string $emailSubject,
        public string $note,
        public string $pdfContent,
        public string $pdfFilename,
    ) {}

    public function build(): static
    {
        return $this->subject($this->emailSubject)
            ->markdown('emails.legal-letter')
            ->attachData($this->pdfContent, $this->pdfFilename, ['mime' => 'application/pdf']);
    }
}
