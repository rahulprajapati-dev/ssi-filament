<?php

namespace App\Services\DataMigration;

use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
// use App\Console\Command\DataMigration;
use App\Models\DataMigrationSyncLog;
use App\Models\User;
use App\Models\Role;

class UserSyncService
{

    protected static $dmsUserId;

    public function sync(?string $since, int $chunkSize, Command $console, int $step): array
    {
        $synced = 0;
        $skipped = 0;
        $failed = 0;

        $table = match ($step) {
            1 => 'tb_zonal_manager_master',
            2 => 'tb_state_head_master',
            3 => 'tb_34061_area_manager_master',
            default => null,
        };

        if (empty($table)) {
            $console->error('steps are required for the migration.');
            return compact('synced', 'skipped', 'failed');
        }

        $query = self::userStepQuery($since, $table);
        $total = (clone $query)->count();

        if ($total === 0) {
            $console->warn('No records found to sync.');
            return compact('synced', 'skipped', 'failed');
        }

        $bar = $console->getOutput()->createProgressBar($total);
        $bar->start();

        $start = now();

        $log = DataMigrationSyncLog::create([
            'entity' => 'users',
            'mode' => app()->runningInConsole() ? 'cron' : 'manual',
            'dry_run' => false,
            'started_at' => $start,
            'triggered_by' => auth()->user()->name ?? 'System',
        ]);
        $roles = Role::pluck('name')->toArray();
        $query->chunk($chunkSize, function ($rows) use (&$synced, &$skipped, &$failed, $console, $bar, $log, $step, $table, $roles) {

            $payload = [];
            $is_failed = false;

            foreach ($rows as $row) {
                if (empty($row->id)) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }
                $payload[] = self::userStepMapping($row, $table);
            }

            if (!empty($payload)) {
                try {
                    DB::connection('mysql')->table('users')->upsert($payload,['oms_id', 'oms_table'], ['status', 'updated_at']);

                    // Assign roles
                    $omsIds = collect($payload)->pluck('oms_id')->toArray();
                    $users = User::whereIn('oms_id', $omsIds)->where('oms_table', $table)->get();

                    foreach ($users as $user) {
                        if ($user->department && in_array($user->department, $roles)) {
                            if (!empty($user->reports_to_id))
                                user_hierarchy($user->reports_to_id, $user->id, $user->dealer_id);

                            $user->syncRoles($user->department);
                        }
                    }
                } catch (\Throwable $e) {
                    $log->update([
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                        'finished_at' => now(),
                    ]);
                    $console->error('chunk fail :' . $e->getMessage());
                    //throw $e;
                    $failed++;
                    $is_failed = true;
                }

                if (!$is_failed) {
                    $count = count($payload);
                    $synced += $count;
                    $log->increment('inserted', $count);
                }
            }

            $bar->advance(count($rows));
        });

        $log->update([
            'status' => 'success',
            'finished_at' => now(),
            'duration_ms' => (int) $start->diffInMilliseconds(now(), true),
        ]);

        $bar->finish();

        return compact('synced', 'skipped', 'failed');
    }

    public function deleteMissing(bool $dryRun, int $chunkSize, Command $console): void
    {
        $console->info('Starting delete-sync (MySQL ← MSSQL)');
    }

    public static function userStepQuery($since, $tableName)
    {
        $query = DB::connection('sqlsrv')->table($tableName)->orderBy('id');

        if ($since) {
            $query->where('modified_date', '>', $since);
        }

        return $query;
    }

    /*=================================== First Step Mapping Start ===================================*/

    public static function userStepMapping($data, $table)
    {

        $parentInfo = match ($table) {
            'tb_zonal_manager_master' => [
                'reports_to' => getAppConfig('ZONAL_HEAD_REPORTS_TO_ID'), 
                'department' =>'ZonalHead',
                'employee_code' =>$data->token_number,
            ],
            'tb_state_head_master' => [
                'reports_to' => self::getParentUser($data->zone_manager_id, 'tb_zonal_manager_master'),
                'department' => 'StateManager',
                'employee_code' => $data->token_number,
            ],
            'tb_34061_area_manager_master' => [
                'reports_to' => self::getParentUser($data->state_head_id, 'tb_state_head_master'),
                'department' => 'AreaManager',
                'employee_code' => $data->code,
            ],
            default => null,
        };

        return [
            'oms_id' => $data->id,
            'oms_table' => $table,

            'name' => $data->name,
            'email' => $data->email,
            'username' => $data->email,
            'password' => '$2y$10$nP/DEFLRh5.rMQ5yTINNbu5uWdUV4SW/GK/fKXInc6TwZUUsul3Uu',
            // 'user_type' => $data->test,
            // 'dealer_id' => $data->test,
            'employee_code' => $parentInfo['employee_code'],
            'status' => $data->visible,
            'department' => $parentInfo['department'], // (kept as-is as requested)
            'mobile' => $data->mobile,
            // 'address' => $data->test,
            // 'pincode' => $data->test,
            // 'longitude' => $data->test,
            // 'latitude' => $data->test,
            // 'avatar_image' => $data->test,
            'reports_to_id' => $parentInfo['reports_to'],
            // 'last_status_at' => $data->test,
            'city_id' => getCityStateIdByName('city_name', $data->city),
            'state_id' => getCityStateIdByName('state_name', $data->state),
            'city' => $data->city,
            'state' => $data->state,
            'zone' => $data->zone,
            // 'extra_json' => $data->test,
            'created_at' => $data->created_on ?? now(),
            'updated_at' => $data->modified_on ?? now(),
            'created_by' => 1,//$data->created_by,
            'updated_by' => 1,//$data->modified_by,
        ];
    }

    /*================================= Parent Mapping ======================================*/

    public static function getParentUser($reports_to_id, $tableName)
    {
        if (empty($reports_to_id)) {
            return null;
        }

        self::loadUserMap();

        return self::$dmsUserId[$tableName][$reports_to_id] ?? null;
    }

    protected static function loadUserMap()
    {
        if (self::$dmsUserId !== null) {
            return;
        }

        self::$dmsUserId = [];

        $users = DB::connection('mysql')->table('users')->select('id', 'oms_id', 'oms_table')->get();

        foreach ($users as $user) {
            self::$dmsUserId[$user->oms_table][$user->oms_id] = $user->id;
        }
    }

    /*================================= First Step End ======================================*/
}



// now no need to change