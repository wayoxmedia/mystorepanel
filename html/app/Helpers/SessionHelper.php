<?php

use Illuminate\Support\Facades\DB;

/**
 * Prune old sessions from the database.
 *
 * This function deletes sessions that have been inactive for longer than the
 * configured session lifetime. It is intended to be run as a scheduled task.
 *
 * @return void
 */
function pruneOldSessions(): void
{
  DB::table('sessions')->where(
    'last_activity',
    '<',
    now()->subMinutes(config('session.lifetime'))->getTimestamp()
  )->delete();
}
