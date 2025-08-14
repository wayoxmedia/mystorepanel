<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class EgleeTenantSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasTable('templates')) {
            throw new RuntimeException('Table "templates" does not exist. Run migrations first.');
        }

        $now          = now();
        $tenantSlug   = 'eglee-gourmet';
        $tenantName   = 'Eglee Gourmet';
        $templateSlug = 'eglee';
        $domain       = 'template1.test';

        DB::transaction(function () use ($now, $tenantSlug, $tenantName, $templateSlug, $domain) {
            // 1) Ensure templates exist (default + eglee)
            $defaultTemplateId = DB::table('templates')->where('slug', 'default')->value('id');
            if (!$defaultTemplateId) {
                DB::table('templates')->insertGetId([
                    'slug'       => 'default',
                    'name'       => 'Default',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $egleeTemplateId = DB::table('templates')->where('slug', $templateSlug)->value('id');
            if (!$egleeTemplateId) {
                $egleeTemplateId = DB::table('templates')->insertGetId([
                    'slug'       => $templateSlug,
                    'name'       => 'Eglee',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // 2) Upsert tenant pointing to Eglee template
            $tenant = DB::table('tenants')->where('slug', $tenantSlug)->first();

            if (!$tenant) {
                $tenantId = DB::table('tenants')->insertGetId([
                    'name'          => $tenantName,
                    'slug'          => $tenantSlug,
                    'template_id'   => $egleeTemplateId,
                    'template_slug' => $templateSlug,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            } else {
                $tenantId = (int) $tenant->id;
                DB::table('tenants')->where('id', $tenantId)->update([
                    'template_id'   => $egleeTemplateId,
                    'template_slug' => $templateSlug,
                    'updated_at'    => $now,
                ]);
            }

            // 3) Ensure site exists and points to this tenant
            $site = DB::table('sites')->where('domain', $domain)->first();

            if (!$site) {
                DB::table('sites')->insert([
                    'tenant_id'   => $tenantId,
                    'template_id' => $egleeTemplateId,
                    'domain'      => $domain,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            } else {
                $updates = [
                    'tenant_id'  => $tenantId,
                    'updated_at' => $now,
                ];

                // keep template aligned with Eglee
                if (!isset($site->template_id) || (int)$site->template_id !== (int)$egleeTemplateId) {
                    $updates['template_id'] = $egleeTemplateId;
                }

                DB::table('sites')->where('id', $site->id)->update($updates);
            }
        });
    }
}
