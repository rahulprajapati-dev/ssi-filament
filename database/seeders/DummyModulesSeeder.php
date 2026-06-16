<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DummyModulesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now()->toDateTimeString();

        // ── 1. Modules ────────────────────────────────────────────────────────

        $modules = [
            [
                'name'           => 'lead',
                'singular_label' => 'Lead',
                'plural_label'   => 'Leads',
                'icon'           => 'heroicon-o-user-group',
                'description'    => 'Track incoming sales leads and their progress through the pipeline.',
                'is_deploy'      => false,
                'is_enable'      => false,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'name'           => 'user',
                'singular_label' => 'User',
                'plural_label'   => 'Users',
                'icon'           => 'heroicon-o-user-circle',
                'description'    => 'CRM users and contacts associated with your business.',
                'is_deploy'      => false,
                'is_enable'      => false,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'name'           => 'client',
                'singular_label' => 'Client',
                'plural_label'   => 'Clients',
                'icon'           => 'heroicon-o-building-office',
                'description'    => 'Manage client companies and their contact information.',
                'is_deploy'      => false,
                'is_enable'      => false,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'name'           => 'account',
                'singular_label' => 'Account',
                'plural_label'   => 'Accounts',
                'icon'           => 'heroicon-o-building-library',
                'description'    => 'Business accounts representing organisations or companies.',
                'is_deploy'      => false,
                'is_enable'      => false,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
        ];

        foreach ($modules as $module) {
            if (DB::table('modules')->where('name', $module['name'])->exists()) {
                $this->command->line("  Skipping module '{$module['name']}' — already exists.");
                continue;
            }
            DB::table('modules')->insert($module);
            $this->command->info("  Created module '{$module['name']}'.");
        }

        // ── 2. Fields ─────────────────────────────────────────────────────────

        $leadId   = DB::table('modules')->where('name', 'lead')->value('id');
        $userId   = DB::table('modules')->where('name', 'user')->value('id');
        $clientId = DB::table('modules')->where('name', 'client')->value('id');
        $accountId = DB::table('modules')->where('name', 'account')->value('id');

        $fields = [

            // ── Lead fields (10) ──────────────────────────────────────────────
            [$leadId, 'first_name',      'First Name',     'text',     null, true,  true,  true,  false, null,           null,                              1],
            [$leadId, 'last_name',       'Last Name',       'text',     null, true,  true,  true,  false, null,           null,                              2],
            [$leadId, 'email',           'Email',           'email',    150,  true,  true,  false, true,  null,           null,                              3],
            [$leadId, 'phone',           'Phone',           'text',     20,   false, false, false, false, null,           null,                              4],
            [$leadId, 'company',         'Company',         'text',     150,  false, true,  true,  false, null,           null,                              5],
            [$leadId, 'status',          'Status',          'select',   null, false, false, true,  false, 'new',          '[{"key":"new","value":"New"},{"key":"contacted","value":"Contacted"},{"key":"qualified","value":"Qualified"},{"key":"lost","value":"Lost"},{"key":"won","value":"Won"}]', 6],
            [$leadId, 'source',          'Lead Source',     'select',   null, false, false, false, false, 'website',      '[{"key":"website","value":"Website"},{"key":"referral","value":"Referral"},{"key":"social_media","value":"Social Media"},{"key":"cold_call","value":"Cold Call"},{"key":"other","value":"Other"}]', 7],
            [$leadId, 'assigned_to',     'Assigned To',     'text',     100,  false, true,  false, false, null,           null,                              8],
            [$leadId, 'notes',           'Notes',           'textarea', null, false, false, false, false, null,           null,                              9],
            [$leadId, 'expected_value',  'Expected Value',  'text',     50,   false, false, true,  false, null,           null,                              10],

            // ── User fields (10) ──────────────────────────────────────────────
            [$userId, 'first_name',  'First Name',  'text',    null, true,  true,  true,  false, null,      null, 1],
            [$userId, 'last_name',   'Last Name',   'text',    null, true,  true,  true,  false, null,      null, 2],
            [$userId, 'email',       'Email',       'email',   150,  true,  true,  false, true,  null,      null, 3],
            [$userId, 'phone',       'Phone',       'text',    20,   false, false, false, false, null,      null, 4],
            [$userId, 'role',        'Role',        'select',  null, false, false, true,  false, 'staff',   '[{"key":"admin","value":"Admin"},{"key":"manager","value":"Manager"},{"key":"staff","value":"Staff"},{"key":"client","value":"Client"}]', 5],
            [$userId, 'department',  'Department',  'text',    100,  false, true,  false, false, null,      null, 6],
            [$userId, 'status',      'Status',      'select',  null, false, false, true,  false, 'active',  '[{"key":"active","value":"Active"},{"key":"inactive","value":"Inactive"}]', 7],
            [$userId, 'address',     'Address',     'textarea',null, false, false, false, false, null,      null, 8],
            [$userId, 'city',        'City',        'text',    100,  false, true,  false, false, null,      null, 9],
            [$userId, 'country',     'Country',     'text',    100,  false, true,  false, false, null,      null, 10],

            // ── Client fields (10) ────────────────────────────────────────────
            [$clientId, 'company_name',    'Company Name',    'text',    150,  true,  true,  true,  false, null,      null, 1],
            [$clientId, 'contact_person',  'Contact Person',  'text',    100,  false, true,  false, false, null,      null, 2],
            [$clientId, 'email',           'Email',           'email',   150,  true,  true,  false, false, null,      null, 3],
            [$clientId, 'phone',           'Phone',           'text',    20,   false, false, false, false, null,      null, 4],
            [$clientId, 'address',         'Address',         'textarea',null, false, false, false, false, null,      null, 5],
            [$clientId, 'city',            'City',            'text',    100,  false, true,  false, false, null,      null, 6],
            [$clientId, 'country',         'Country',         'text',    100,  false, true,  false, false, null,      null, 7],
            [$clientId, 'industry',        'Industry',        'select',  null, false, false, true,  false, 'other',   '[{"key":"technology","value":"Technology"},{"key":"finance","value":"Finance"},{"key":"healthcare","value":"Healthcare"},{"key":"retail","value":"Retail"},{"key":"other","value":"Other"}]', 8],
            [$clientId, 'status',          'Status',          'select',  null, false, false, true,  false, 'prospect','[{"key":"active","value":"Active"},{"key":"inactive","value":"Inactive"},{"key":"prospect","value":"Prospect"}]', 9],
            [$clientId, 'website',         'Website',         'text',    255,  false, false, false, false, null,      null, 10],

            // ── Account fields (10) ───────────────────────────────────────────
            [$accountId, 'account_name',    'Account Name',    'text',    150,  true,  true,  true,  false, null,       null, 1],
            [$accountId, 'account_type',    'Account Type',    'select',  null, false, false, true,  false, 'prospect', '[{"key":"prospect","value":"Prospect"},{"key":"customer","value":"Customer"},{"key":"partner","value":"Partner"},{"key":"vendor","value":"Vendor"}]', 2],
            [$accountId, 'industry',        'Industry',        'select',  null, false, false, true,  false, 'other',    '[{"key":"technology","value":"Technology"},{"key":"finance","value":"Finance"},{"key":"healthcare","value":"Healthcare"},{"key":"retail","value":"Retail"},{"key":"manufacturing","value":"Manufacturing"},{"key":"other","value":"Other"}]', 3],
            [$accountId, 'phone',           'Phone',           'text',    20,   false, false, false, false, null,       null, 4],
            [$accountId, 'email',           'Email',           'email',   150,  false, true,  false, false, null,       null, 5],
            [$accountId, 'website',         'Website',         'text',    255,  false, false, false, false, null,       null, 6],
            [$accountId, 'billing_address', 'Billing Address', 'textarea',null, false, false, false, false, null,       null, 7],
            [$accountId, 'city',            'City',            'text',    100,  false, true,  false, false, null,       null, 8],
            [$accountId, 'country',         'Country',         'text',    100,  false, true,  false, false, null,       null, 9],
            [$accountId, 'status',          'Status',          'select',  null, false, false, true,  false, 'active',   '[{"key":"active","value":"Active"},{"key":"inactive","value":"Inactive"},{"key":"closed","value":"Closed"}]', 10],
        ];

        foreach ($fields as [$modId, $fieldName, $label, $type, $length, $required, $searchable, $sortable, $unique, $default, $options, $order]) {
            if (! $modId) {
                continue;
            }

            if (DB::table('module_fields')->where('module_id', $modId)->where('field_name', $fieldName)->exists()) {
                continue;
            }

            DB::table('module_fields')->insert([
                'module_id'     => $modId,
                'field_name'    => $fieldName,
                'label'         => $label,
                'type'          => $type,
                'length'        => $length,
                'required'      => $required,
                'searchable'    => $searchable,
                'sortable'      => $sortable,
                'unique_field'  => $unique,
                'default_value' => $default,
                'options'       => $options,
                'sort_order'    => $order,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        $this->command->info('  Fields seeded.');

        // ── 3. Layouts ────────────────────────────────────────────────────────

        $layouts = [

            // ── Lead layouts ──────────────────────────────────────────────────
            [
                'module_id'   => $leadId,
                'layout_type' => 'create',
                'layout_json' => json_encode([
                    ['title' => 'Personal Info', 'columns' => 2, 'fields' => ['first_name', 'last_name', 'email', 'phone', 'company']],
                    ['title' => 'Lead Details',  'columns' => 2, 'fields' => ['status', 'source', 'assigned_to', 'expected_value', 'notes']],
                ]),
            ],
            [
                'module_id'   => $leadId,
                'layout_type' => 'edit',
                'layout_json' => json_encode([
                    ['title' => 'Personal Info', 'columns' => 2, 'fields' => ['first_name', 'last_name', 'email', 'phone', 'company']],
                    ['title' => 'Lead Details',  'columns' => 2, 'fields' => ['status', 'source', 'assigned_to', 'expected_value', 'notes']],
                ]),
            ],
            [
                'module_id'   => $leadId,
                'layout_type' => 'detail',
                'layout_json' => json_encode([
                    ['title' => 'Personal Info', 'columns' => 3, 'fields' => ['first_name', 'last_name', 'email', 'phone', 'company']],
                    ['title' => 'Lead Details',  'columns' => 3, 'fields' => ['status', 'source', 'assigned_to', 'expected_value', 'notes']],
                ]),
            ],
            [
                'module_id'   => $leadId,
                'layout_type' => 'list',
                'layout_json' => json_encode([
                    ['title' => 'columns', 'columns' => 1, 'fields' => ['first_name', 'last_name', 'email', 'company', 'status', 'source', 'expected_value']],
                ]),
            ],

            // ── User layouts ──────────────────────────────────────────────────
            [
                'module_id'   => $userId,
                'layout_type' => 'create',
                'layout_json' => json_encode([
                    ['title' => 'Personal Info', 'columns' => 2, 'fields' => ['first_name', 'last_name', 'email', 'phone']],
                    ['title' => 'Work Info',     'columns' => 2, 'fields' => ['role', 'department', 'status', 'address', 'city', 'country']],
                ]),
            ],
            [
                'module_id'   => $userId,
                'layout_type' => 'edit',
                'layout_json' => json_encode([
                    ['title' => 'Personal Info', 'columns' => 2, 'fields' => ['first_name', 'last_name', 'email', 'phone']],
                    ['title' => 'Work Info',     'columns' => 2, 'fields' => ['role', 'department', 'status', 'address', 'city', 'country']],
                ]),
            ],
            [
                'module_id'   => $userId,
                'layout_type' => 'detail',
                'layout_json' => json_encode([
                    ['title' => 'Personal Info', 'columns' => 3, 'fields' => ['first_name', 'last_name', 'email', 'phone']],
                    ['title' => 'Work Info',     'columns' => 3, 'fields' => ['role', 'department', 'status', 'address', 'city', 'country']],
                ]),
            ],
            [
                'module_id'   => $userId,
                'layout_type' => 'list',
                'layout_json' => json_encode([
                    ['title' => 'columns', 'columns' => 1, 'fields' => ['first_name', 'last_name', 'email', 'role', 'department', 'status']],
                ]),
            ],

            // ── Client layouts ────────────────────────────────────────────────
            [
                'module_id'   => $clientId,
                'layout_type' => 'create',
                'layout_json' => json_encode([
                    ['title' => 'Company Info',     'columns' => 2, 'fields' => ['company_name', 'contact_person', 'email', 'phone', 'website']],
                    ['title' => 'Location & Status','columns' => 2, 'fields' => ['address', 'city', 'country', 'industry', 'status']],
                ]),
            ],
            [
                'module_id'   => $clientId,
                'layout_type' => 'edit',
                'layout_json' => json_encode([
                    ['title' => 'Company Info',     'columns' => 2, 'fields' => ['company_name', 'contact_person', 'email', 'phone', 'website']],
                    ['title' => 'Location & Status','columns' => 2, 'fields' => ['address', 'city', 'country', 'industry', 'status']],
                ]),
            ],
            [
                'module_id'   => $clientId,
                'layout_type' => 'detail',
                'layout_json' => json_encode([
                    ['title' => 'Company Info',     'columns' => 3, 'fields' => ['company_name', 'contact_person', 'email', 'phone', 'website']],
                    ['title' => 'Location & Status','columns' => 3, 'fields' => ['address', 'city', 'country', 'industry', 'status']],
                ]),
            ],
            [
                'module_id'   => $clientId,
                'layout_type' => 'list',
                'layout_json' => json_encode([
                    ['title' => 'columns', 'columns' => 1, 'fields' => ['company_name', 'contact_person', 'email', 'industry', 'status', 'website']],
                ]),
            ],

            // ── Account layouts ───────────────────────────────────────────────
            [
                'module_id'   => $accountId,
                'layout_type' => 'create',
                'layout_json' => json_encode([
                    ['title' => 'Account Info', 'columns' => 2, 'fields' => ['account_name', 'account_type', 'industry', 'phone', 'email', 'website']],
                    ['title' => 'Location',     'columns' => 2, 'fields' => ['billing_address', 'city', 'country', 'status']],
                ]),
            ],
            [
                'module_id'   => $accountId,
                'layout_type' => 'edit',
                'layout_json' => json_encode([
                    ['title' => 'Account Info', 'columns' => 2, 'fields' => ['account_name', 'account_type', 'industry', 'phone', 'email', 'website']],
                    ['title' => 'Location',     'columns' => 2, 'fields' => ['billing_address', 'city', 'country', 'status']],
                ]),
            ],
            [
                'module_id'   => $accountId,
                'layout_type' => 'detail',
                'layout_json' => json_encode([
                    ['title' => 'Account Info', 'columns' => 3, 'fields' => ['account_name', 'account_type', 'industry', 'phone', 'email', 'website']],
                    ['title' => 'Location',     'columns' => 3, 'fields' => ['billing_address', 'city', 'country', 'status']],
                ]),
            ],
            [
                'module_id'   => $accountId,
                'layout_type' => 'list',
                'layout_json' => json_encode([
                    ['title' => 'columns', 'columns' => 1, 'fields' => ['account_name', 'account_type', 'industry', 'email', 'status', 'city']],
                ]),
            ],
        ];

        foreach ($layouts as $layout) {
            if (! $layout['module_id']) {
                continue;
            }

            if (DB::table('module_layouts')->where('module_id', $layout['module_id'])->where('layout_type', $layout['layout_type'])->exists()) {
                continue;
            }

            DB::table('module_layouts')->insert([
                'module_id'   => $layout['module_id'],
                'layout_type' => $layout['layout_type'],
                'layout_json' => $layout['layout_json'],
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        $this->command->info('  Layouts seeded.');
        $this->command->info('Done. 4 modules, 40 fields, 16 layouts created.');
    }
}
