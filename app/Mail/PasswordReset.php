<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User   $utilisateur,
        public string $motDePasseTemporaire,
        public string $urlConnexion,
    ) {}

    public function envelope(): Envelope
    {
        $appName = config('pga.branding.app_name', 'PGA Open');
        return new Envelope(subject: "{$appName} - Mot de passe r\u00e9initialis\u00e9");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset',
            with: [
                'nom'      => $this->utilisateur->nom_complet,
                'email'    => $this->utilisateur->email,
                'password' => $this->motDePasseTemporaire,
                'url'      => $this->urlConnexion,
                'appName'  => config('pga.branding.app_name', 'PGA Open'),
                'org'      => config('pga.organization.ministry', ''),
            ],
        );
    }
}
