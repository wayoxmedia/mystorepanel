<?php

namespace App\Console\Commands;

use App\Models\Invitation;
use App\Models\AuditLog;
use Illuminate\Console\Command;
use Throwable;

class ExpireInvitations extends Command
{
  /**
   * Run with: php artisan invitations:expire
   * Optional: --dry   (no changes; just prints what would expire)
   */
  protected $signature =
    'invitations:expire {--dry : Show what would be expired without changing anything}';

  protected $description =
    'Mark pending invitations past their expiration as expired (frees seats).';

  public function handle(): int
  {
    $dryRun = (bool)$this->option('dry');
    $now = now();

    $total = 0;
    $this->info(
      (
        $dryRun
          ? '[DRY RUN] '
          : ''
      )
      .'Expiring pending invitations older than '.$now->toDateTimeString().' ...'
    );

    Invitation::query()
      ->where('status', 'pending')
      ->whereNotNull('expires_at')
      ->where('expires_at', '<=', $now)
      ->orderBy('id')
      ->chunkById(500, function ($chunk) use (&$total, $dryRun) {
        foreach ($chunk as $inv) {
          $total++;

          if ($dryRun) {
            $this->line(
              " • would expire #{$inv->id} {$inv->email} (tenant_id={$inv->tenant_id})"
            );
            continue;
          }

          $inv->status = 'expired';
          // Mantener expires_at tal cual (ya vencida); si quieres, podrías fijarla a now().
          $inv->save();

          // Audit (best-effort; si falla no rompe el comando)
          try {
            AuditLog::query()->create([
              'actor_id' => config('mystore.system_actor_id', 0), // "system"
              'action' => 'invite.expired',
              'subject_type' => Invitation::class,
              'subject_id' => $inv->id,
              'meta' => [
                'email' => $inv->email,
                'tenant_id' => $inv->tenant_id,
                'system' => true
              ],
            ]);
          } catch (Throwable $e) {
            $this->warn(
              "   (audit failed for invitation #{$inv->id}: {$e->getMessage()})"
            );
          }
        }
      });

    $msg = $dryRun
      ? "Found {$total} invitations to expire."
      : "Expired {$total} invitations.";
    $this->info($msg);

    return self::SUCCESS;
  }
}
