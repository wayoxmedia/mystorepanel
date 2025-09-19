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
    $this->acceptUrl = route(
      'invitations.accept',
      ['token' => $invitation->token]
    );
  }

  /**
   * Build the message.
   */
  public function build(): self
  {
    // Build vars depending on tenant. TODO Why fallback to 'No Role'? Check.
    $roleName   = $this->invitation->role?->name ?? 'No Role';

    if ($this->invitation->tenant) {
      // Tenant exists.
      $tenantName = $this->invitation->tenant->name;
      $subject = "You're invited to {$tenantName} on My Store App";
    } else {
      // Tenant is null? then invitation is to Platform Super Admin.
      $tenantName = 'My Store Panel';
      $subject = "Your invitation to join as {$roleName} on My Store Panel";
    }


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
