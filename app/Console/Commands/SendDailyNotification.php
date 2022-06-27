<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendDailyNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:daily';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily email notification';
    
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

        // June 24, replaceto send 

        $toAddresses = ['james.poon@gov.bc.ca', 'james.poon@telus.com', 'myphd2@gmail.com', 'employee11@extest.gov.bc.ca', 'employee12@extest.gov.bc.ca'];
        $subject = 'ePerformance Application - schedule daily notification testing';
        $body = 'Test message -- daily notification send out from server for testing purpose, please ignore.';


        Mail::raw( $body , function($message) use($subject, $toAddresses) {
            $message->to( $toAddresses );
            $message->subject(  $subject );
        });  

         // check for failures
        if (Mail::failures()) {
            // return response showing failed emails
            $this->info('Error. Failed to sent daily notification to eligible people.');
        } else {
            $this->info('Successfully sent daily notification to eligible people.');
        }

        return 0;

    }

}
