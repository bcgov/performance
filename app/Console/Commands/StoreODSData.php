<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\EmployeeDemo;

class StoreODSData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:getDemoData';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $demodata = Http::withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])->withBasicAuth(env('ODS_DEMO_CLIENT_ID'),env('ODS_DEMO_CLIENT_SECRET'))->get(env('ODS_DEMO_URI_1'));

        $data = $demodata['value'];

        foreach($data as $item){
          EmployeeDemo::updateOrCreate([
            'guid' => $item['GUID'],
          ],[
            'employee_id' => $item['employee_id'],
            'empl_record' => $item['Empl_Record'],
            'employee_first_name' => $item['employee_first_name'],
            'employee_last_name' => $item['employee_last_name'],
            'employee_status' => $item['employee_status'],
            'employee_email' => $item['employee_email'],
            'classification' => $item['classification'],
            'deptid' => $item['deptid'],
            'jobcode' => $item['Jobcode'],
            'job_title' => $item['job_title'],
            'position_number' => $item['Position_number'],
            'position_start_date' => date('Y-m-d H:i:s', strtotime($item['position_start_date'])),
            'manager_id' => $item['manager_id'],
            'manager_first_name' => $item['manager_first_name'],
            'manager_last_name' => $item['manager_last_name'],
            'guid' => $item['GUID'],
            'date_posted' => date('Y-m-d H:i:s', strtotime($item['date_posted'])),
            'date_deleted' => date('Y-m-d H:i:s', strtotime($item['date_deleted'])),
            'date_updated' => date('Y-m-d H:i:s', strtotime($item['date_updated'])),
            'date_created' => date('Y-m-d H:i:s', strtotime($item['date_created'])),
          ]);
        };

        //return 0;
    }
}
