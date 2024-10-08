<?php

namespace App\Console\Commands;

use DateTime;
use Carbon\Carbon;
use App\Models\User;
use App\Models\EmployeeDemo;
use App\Models\JobDataAudit;
use App\Models\JobSchedAudit;
use App\Models\SharedProfile;
use App\Models\UserReportingTo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;


class SyncUserProfile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:SyncUserProfiles {--manual}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Or Create User Profile based on Employee demography data';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {

        $switch = strtolower(env('PRCS_SYNC_USER_PROFILES'));
        $manualoverride = (strtolower($this->option('manual')) ? true : false);
        $exceptions = ''; 

        if ($switch == 'on' || $manualoverride) {

            $job = JobSchedAudit::where('job_name', $this->signature)
                ->where('status','completed')
                ->orderBy('id','desc')
                ->first();     

            $last_cutoff_time = ($job) ? $job->cutoff_time : new DateTime( '1990-01-01');

            $start_time = Carbon::now();

            $audit_id = JobSchedAudit::insertGetId(
            [
                'job_name' => $this->signature,
                'start_time' => date('Y-m-d H:i:s', strtotime($start_time)),
                'status' => 'Initiated'
            ]
            );

            $cutoff_time = Carbon::now();

            $this->SyncUserProfile($last_cutoff_time, $cutoff_time, $audit_id, $exceptions);

            $end_time = Carbon::now();
            $result = JobSchedAudit::updateOrCreate( 
                [ 
                    'id' => $audit_id 
                ] 
                , 
                [ 
                    'job_name' => $this->signature, 
                    'start_time' => date('Y-m-d H:i:s', strtotime($start_time)), 
                    'end_time' => date('Y-m-d H:i:s', strtotime($end_time)), 
                    'cutoff_time' => date('Y-m-d H:i:s', strtotime($cutoff_time)), 
                    'status' => 'Completed', 
                    'details' => $exceptions 
                ] 
            ); 

        } else {
            $start_time = Carbon::now()->format('c');
            $audit_id = JobSchedAudit::insertGetId(
            [
                'job_name' => 'command:SyncUserProfiles',
                'start_time' => date('Y-m-d H:i:s', strtotime($start_time)),
                'status' => 'Disabled'
            ]
            );
            $this->info( 'Process is currently disabled; or "PRCS_SYNC_USER_PROFILES=on" is currently missing in the .env file.');
        }

        return 0;
    }

    private function SyncUserProfile($last_sync_at, $new_sync_at, $audit_id, &$exceptions) 
    {

        $last_sync_at = '1990-01-01';       // always do the full set

        $employees = EmployeeDemo::whereNotIn('guid', ['', ' '])
            ->whereNotIn('employee_email', ['', ' '])
            ->whereNotIn('employee_id', ['', ' '])
            ->where(function ($query) use ($last_sync_at) {
                $query->whereNull('date_updated');
                $query->orWhere('date_updated', '>=', $last_sync_at );
            })
            // Line below is for testing only.
            // ->whereRaw("employee_id = 'XXXXXX'")
            ->orderBy('date_deleted')
            ->orderBy('employee_id')
            ->orderBy('job_indicator', 'desc')
            ->orderBy('empl_record')
            ->get(['employee_id', 'empl_record', 'employee_email', 'guid', 'idir',
                'employee_first_name', 'employee_last_name', 'job_indicator',
                'position_start_date', 'supervisor_emplid', 'date_updated', 'date_deleted']);


        // Step 1 : Create and Update User Profile (no update on reporting to)
        $this->info( now() );
        $this->info('Step 1 - Create and Update User Profile (but no update on reporting to)' );

        $password = Hash::make(env('SYNC_USER_PROFILE_SECRET'));
        foreach ($employees as $employee) {

            $reporting_to = null;

            // Check the user by GUID 
            $user = User::where('employee_id', $employee->employee_id)
            ->orderBy('id', 'desc')
            ->first();

            if ($user) {

                $dup_email = User::where('email', $employee->employee_email)
                ->select('id') 
                ->where('id', '!=', $user->id)
                ->first();

                if ($dup_email) {
                    $exceptions .= json_encode([ 
                        'employee_id' => $employee->employee_id,
                        'empl_record' => $employee->empl_record,
                        'employee_email' => $employee->employee_email,
                        'exception' => 'Email address already in use by UID '.$dup_email->id.'.' 
                    ]); 
                    $this->info( 'Step 1: Email address already in use by UID '.$dup_email->id.'.' ); 
                } else {

                    DB::beginTransaction();
                    try {
                        $update_flag = 0;
                        if (!$employee->date_deleted && $user->name != $employee->employee_first_name . ' ' . $employee->employee_last_name) {
                            $user->name = $employee->employee_first_name . ' ' . $employee->employee_last_name; 
                            $update_flag = 1;
                        }
                        if (!$employee->date_deleted && $user->email != $employee->employee_email) {
                            $user->email = $employee->employee_email; 
                            $update_flag += 10;
                        }
                        if (!$employee->date_deleted && date('Y-m-d',strtotime($user->joining_date)) != date('Y-m-d',strtotime($employee->position_start_date))) {
                            $user->joining_date = date('Y-m-d',strtotime($employee->position_start_date)); 
                            $update_flag += 100;
                        }
                        $active_demo = EmployeeDemo::withoutGlobalScopes()
                            ->from(\DB::raw('employee_demo AS ed2 USE INDEX (idx_employee_demo_employee_id_date_deleted)'))
                            ->join(\DB::raw('employee_demo_tree AS edt USE INDEX (employee_demo_tree_id_unique)'), function ($on1) {
                                return $on1->on('edt.id', 'ed2.orgid')
                                    ->whereNull('ed2.date_deleted');
                            })
                            ->join(\DB::raw('access_organizations AS ao USE INDEX (access_organizations_orgid_unique)'), function ($on2) {
                                return $on2->on('ao.orgid', 'edt.organization_key')
                                    ->on('ao.allow_login', \DB::raw("'Y'"));
                            })
                            ->where('ed2.employee_id', \DB::raw("'{$employee->employee_id}'"))
                            ->whereRaw('ed2.pdp_excluded = 0')
                            ->select(DB::raw(1))
                            ->first();
                        $active_found = $active_demo ? 1 : 0;
                        $acct_locked = $user->acctlock ? 1 : 0;
                        if ($active_found == $acct_locked) {
                            $user->acctlock = $active_found ? 0 : 1;  
                            $update_flag += 1000;
                        }
                        if ($update_flag > 0) {
                            $user->last_sync_at = $new_sync_at; 
                            $user->save(); 
                            $this->info( "Step 1: Updated user profile ({$user->id}) for EID # {$employee->employee_id}. Update Code: {$update_flag}" ); 
                        }

                        // Grant employee Role
                        if (!$user->hasRole('Employee')) {
                            $user->assignRole('Employee');
                        }

                        if (!$user->hasRole('Supervisor')) {
                            $this->assignSupervisorRole( $user );
                        }
                        DB::commit();
                    } catch (Exception $e) { 
                        $exceptions .= json_encode([ 
                            'employee_id' => $employee->employee_id, 
                            'empl_record' => $employee->empl_record, 
                            'employee_email' => $employee->employee_email, 
                            'exception' => 'Unable to update user profile for EID # '.$employee->employee_id.'.' 
                        ]); 
                        $this->info( 'Step 1: Unable to update user profile for EID # '.$employee->employee_id.'.' ); 
                        DB::rollback(); 
                    }

                }

            } else {

                $dup_email = User::where('email', $employee->employee_email)
                ->where('employee_id', '!=', $employee->employee_id)
                ->select('employee_id') 
                ->first();

                if ($dup_email) {
                    $exceptions .= json_encode([ 
                        'employee_id' => $employee->employee_id,
                        'empl_record' => $employee->empl_record,
                        'employee_email' => $employee->employee_email,
                        'exception' => 'Email address already in use by EID '.$dup_email->employee_id.'.' 
                    ]); 
                    $this->info( 'Step 1: Email address already in use by EID '.$dup_email->employee_id.'.' ); 
                } else {

                    DB::beginTransaction();
                    try {
                        $active_demo = EmployeeDemo::withoutGlobalScopes()
                            ->from(\DB::raw('employee_demo AS ed2 USE INDEX (idx_employee_demo_employee_id_date_deleted)'))
                            ->join(\DB::raw('employee_demo_tree AS edt USE INDEX (employee_demo_tree_id_unique)'), function ($on1) {
                                return $on1->on('edt.id', 'ed2.orgid')
                                    ->whereNull('ed2.date_deleted');
                            })
                            ->join(\DB::raw('access_organizations AS ao USE INDEX (access_organizations_orgid_unique)'), function ($on2) {
                                return $on2->on('ao.orgid', 'edt.organization_key')
                                    ->on('ao.allow_login', \DB::raw("'Y'"));
                            })
                            ->where('ed2.employee_id', \DB::raw("'{$employee->employee_id}'"))
                            ->whereRaw('ed2.pdp_excluded = 0')
                            ->select(DB::raw(1))
                            ->first();
                        $active_found = $active_demo ? 1 : 0;

                        $user = User::create([
                            'guid' => $employee->guid,
                            'name' => $employee->employee_first_name . ' ' . $employee->employee_last_name,
                            'email' => $employee->employee_email,
                            'employee_id' => $employee->employee_id,
                            'empl_record' => $employee->empl_record,
                            'joining_date' => $employee->position_start_date,
                            'password' => $password,
                            'acctlock' => $active_found ? 0 : 1,
                            'last_sync_at' => $new_sync_at,
                        ]);
                        $this->info( "Step 1: Created user profile ({$user->id}) for EID # {$employee->employee_id}." ); 
        
                        $user->assignRole('Employee');

                        // Grant 'Supervisor' Role based on ODS demo database
                        $this->assignSupervisorRole( $user );

                        DB::commit(); 
                    } catch (Exception $e) { 
                        $exceptions .= json_encode([ 
                            'employee_id' => $employee->employee_id, 
                            'empl_record' => $employee->empl_record, 
                            'employee_email' => $employee->employee_email, 
                            'exception' => 'Unable to update user profile for EID # '.$employee->employee_id.'.' 
                        ]); 
                        $this->info( 'Step 1: Unable to create user profile for EID # '.$employee->employee_id.'.' ); 
                        DB::rollback(); 
                    } 
                }

            }
        
        }

        // Step 2 : Update Reporting to
        $this->info( now() );
        $this->info('Step 2 - Update Reporting to');

        foreach ($employees as $employee) {

            $reporting_to = $this->getReportingUserId($employee, $exceptions);   
            
            $user = User::from('users')
                ->where('employee_id', $employee->employee_id)
                ->select('id', 'reporting_to', 'last_sync_at')
                ->first(); 

            if ($user) {

                if(!$user->validPreferredSupervisor()) {

                    if ($user->reporting_to != $reporting_to) {
                        $user->reporting_to = $reporting_to;
                        $user->last_sync_at = $new_sync_at;
                        $user->save();             

                        // Update Reporting Tos
                        if ($reporting_to) {
                            UserReportingTo::updateOrCreate(
                                [
                                    'user_id' => $user->id
                                ],
                                [
                                    'reporting_to_id' => $reporting_to
                                ]
                            );
                        }
                    }
                }
            } else {
                $exceptions .= json_encode([ 
                    'employee_id' => $employee->employee_id, 
                    'empl_record' => $employee->empl_record,  
                    'employee_email' => $employee->employee_email, 
                    'exception' => 'User not found by employee id, EID # '.$employee->employee_id.'.' 
                ]); 
                $this->info('Step 2: User ' . $employee->employee_email . ' - ' . $employee->employee_id . ' not found by employee id.'); 
            }
          
        }

        // Step 3 : Lock Inactivate User account
        $this->info( now() );        
        $this->info('Step 3 - Lock Out Inactivate User account');

        $users = User::where('id', '>', \DB::raw(9999))
            ->whereRaw("RIGHT(users.email, 20) <> LPAD(CONCAT('<', users.id, '><LOCKED>'), 20, '*')")
            ->whereExists(function($any_demo) {
                return $any_demo->select(DB::raw(1))->from('employee_demo AS ed1')->whereRaw("ed1.employee_id = users.employee_id");
            })
            ->whereNotExists(function($active_demo) {
                return $active_demo->select(DB::raw(2))->from('employee_demo AS ed2')->whereRaw("ed2.employee_id = users.employee_id")->whereNull('ed2.date_deleted');
            })
            ->whereRaw('users.acctlock = 0')
            ->update(['users.acctlock' => true, 'users.last_sync_at' => $new_sync_at, 'users.email' => \DB::raw("CONCAT(users.email, LPAD(CONCAT('<', users.id, '><LOCKED>'), 20, '*'))")]);

        $this->info( now() );         
 
        // Step 4 : Lock Out Users from Excluded Departments
        $this->info( now() );        
        $this->info('Step 4 - Lock Out Users from Excluded Departments');

        $users = User::where('id', '>', \DB::raw(9999))
            ->whereExists(function($any_demo) {
                return $any_demo->select(DB::raw(1))->from('employee_demo AS ed1')->whereRaw("ed1.employee_id = users.employee_id AND ed1.pdp_excluded = 1");
            })
            ->whereNotExists(function($active_demo) {
                return $active_demo->select(DB::raw(2))->from('employee_demo AS ed2')->whereRaw("ed2.employee_id = users.employee_id AND ed2.pdp_excluded = 0")->whereNull('ed2.date_deleted');
            })
            ->whereRaw('users.acctlock = 0')
            ->update(['users.acctlock' => true, 'users.last_sync_at' => $new_sync_at]);

        $this->info( now() );    
        
        // Step 5 : Update Invalid Preferred Job In User Profile
        $this->info( now() );
        $this->info('Step 5 - Update Invalid Preferred Job In User Profile');

        $users = User::where('users.acctlock', \DB::raw(0))
        ->whereNotExists(function($active_demo) {
            return $active_demo->select(\DB::raw(1))
            ->from('employee_demo AS eda')
            ->whereRaw('eda.employee_id = users.employee_id')
            ->whereRaw("eda.empl_record = users.empl_record")
            ->whereNull('eda.date_deleted')
            ->whereRaw("eda.pdp_excluded = 0");
        })
        ->whereExists(function($any_demo) {
            return $any_demo->select(\DB::raw(2))
            ->from('employee_demo AS edo')
            ->whereRaw('edo.employee_id = users.employee_id')
            ->whereRaw('edo.empl_record <> users.empl_record')
            ->whereNull('edo.date_deleted')
            ->whereRaw('edo.pdp_excluded = 0');
        })
        ->select('users.id', 'users.employee_id', 'users.empl_record')
        ->orderBy('users.id')
        ->get();

        foreach($users as $user){
            $minDemo = EmployeeDemo::where('employee_id', $user->employee_id)
            ->where('empl_record', '<>', $user->empl_record)
            ->whereNull('date_deleted')
            ->where('pdp_excluded', 0)
            ->select('empl_record')
            ->orderBy('empl_record')
            ->first();
            if($minDemo){
                User::where('id', $user->id)->update(['empl_record' => $minDemo->empl_record]);
                $this->info(" - EE{$user->employee_id} - Assigned record {$minDemo->empl_record} for Preferred Job.");
            } else {
                $this->info(" - EE{$user->employee_id} - No available active Job for Preferred Job.");
            }
        }

        $this->info( now() );    
 
        return null; 
    }

    private function getReportingUserId($employee, &$exceptions) 
    {

        $supervisor = EmployeeDemo::where('employee_id', $employee->supervisor_emplid)
            ->orderBy('job_indicator', 'desc')
            ->orderBy('empl_record')
            ->first();

        if ($supervisor) {
            $user = User::where('guid', str_replace('-', '', $supervisor->guid))->first();
            if ($user) {
                return $user->id;
            } else {
                $exceptions .= json_encode([ 
                    'employee_id' => $employee->employee_id, 
                    'empl_record' => $employee->empl_record, 
                    'employee_email' => $employee->employee_email, 
                    'exception' => 'Supervisor not SEID # '.$employee->supervisor_emplid.' for employee '.$employee->employee_id.'.' 
                ]); 
                $text = 'Supervisor Not found - ' . $employee->supervisor_emplid . ' | employee - ' . $employee->employee_id; 
                $this->info( 'Step 2: ' . $text );
                
            }
        }

        return null;

    }

    private function assignSupervisorRole(User $user)
    {

        $role = 'Supervisor';

        $isManager = false;
        $hasSharedProfile = false;

        // To determine the login user whether is manager or not 
        $mgr = User::where('reporting_to', $user->id)->first();
        $isManager = $mgr ? true : false;

        // To determine the login user whether has shared profile
        $sp = SharedProfile::where('shared_with', $user->id )->first();
        $hasSharedProfile = $sp ? true : false;

        // Assign/Rovoke Role when is manager or has shared Profile
        if ($user->hasRole($role)) {
            if (!($isManager or $hasSharedProfile)) {
                $user->removeRole($role);
            }
        } else {
            if ($isManager or $hasSharedProfile) {
                $user->assignRole($role);
            }
        }
 
        return null; 
    }

}
