<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable
{
  use Queueable, SerializesModels;

  public Invitation $invitation;
  public string $acceptUrl;

  /**
   * Create a new message instance.
   */
  public function __construct(Invitation $invitation)
  {
    $this->invitation = $invitation;
    $this->acceptUrl = route('invitations.accept', ['token' => $invitation->token]);
  }

  /**
   * Build the message.
   */
  public function build(): self
  {
    $tenantName = optional($this->invitation->tenant)->name ?? config('app.name');
    $roleName   = optional($this->invitation->role)->name;
    $subject    = "You're invited to {$tenantName} on My Store App";

    return $this->subject($subject)
      ->view('emails.invitations.invite')
      ->with([
        'invitation' => $this->invitation,
        'acceptUrl'  => $this->acceptUrl,
        'tenantName' => $tenantName,
        'roleName'   => $roleName,
        'expiresAt'  => $this->invitation->expires_at,
      ]);
  }
}
