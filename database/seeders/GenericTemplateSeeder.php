<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\GenericTemplate;
use Carbon\Carbon;

class GenericTemplateSeeder extends Seeder
{
  /**
   * Run the database seeds.
   *
   * @return void
   */
  public function run()
  {

    //
    // 1. Template for Converstion Sign off
    //
    $template = GenericTemplate::updateOrCreate([
      'template' => 'CONVERSATION_SIGN_OFF',
    ],[
      'description' =>  'Send out an email notification when a conversation has been signed',
      'instructional_text' => 'N/A',
      'sender' => '2',
      'subject' => 'PDP - %2 Signed-off on Your %3 Conversation',
      'body' => "<p>Hello %1,</p><p>%2 just signed-off on your %3 conversation. Please visit www.performance.gov.bc.ca to view the details.</p><p>Thank you!</p>",
    ]);

    $template->binds()->delete();

    $template->binds()->create([
      'seqno' => 0,
      'bind' => '%1', 
      'description' => 'Recipient of the email',
    ]);        
    $template->binds()->create([
      'seqno' => 1,
      'bind' => '%2', 
      'description' => 'Person who signed the conversation',
    ]);        
    $template->binds()->create([
      'seqno' => 2,
      'bind' => '%3', 
      'description' => 'Conversation topic',
    ]); 

    //
    // 2. New Goal in Goal Bank
    //
    $template = GenericTemplate::updateOrCreate([
      'template' => 'NEW_GOAL_IN_GOAL_BANK',
    ],[
      'description' =>  'Send out email notification when a new goal is added to an employee\'s goal bank',
      'instructional_text' => 'N/A',
      'sender' => '2',
      'subject' => 'PDP - A New Goal Has Been Added to Your Goal Bank',
      'body' => "<p>Hello %1,</p><p>%2 has added a %4 goal to your goal bank. The goal is called: %3.</p><p>Please log in to www.performance.gov.bc.ca to view more details and add the goal to your profile as needed.</p><p>Thanks!</p>",
    ]);

    $template->binds()->delete();

    $template->binds()->create([
      'seqno' => 0,
      'bind' => '%1', 
      'description' => 'Recipient of the email',
    ]);        
    $template->binds()->create([
      'seqno' => 1,
      'bind' => '%2', 
      'description' => 'Person who added goal to goal bank',
    ]);        
    $template->binds()->create([
      'seqno' => 2,
      'bind' => '%3', 
      'description' => 'Goal title',
    ]); 
    $template->binds()->create([
      'seqno' => 3,
      'bind' => '%4', 
      'description' => 'Mandatory or suggested status',
    ]); 


    //
    // 3. Supervisor Add a new Comment 
    //
    $template = GenericTemplate::updateOrCreate([
      'template' => 'SUPERVISOR_COMMENT_MY_GOAL',
    ], [
      'description' =>  'Send out email notification when supervisor adds a comment to employee\'s goal',
      'instructional_text' => 'You can add parameters',
      'sender' => '2',
      'subject' => 'PDP - %2 Added a Comment on One of Your Goals',
      'body' => "<p>Hello %1,</p><p>%2 added a comment on one of your goals in the Performance Development Platform.</p><p>Goal title: %3</p><p>Comment added:<br>%4</p><p>Log in to performance.gov.bc.ca to view and respond if required.</p><p>Thanks!</p>",
    ]);

    $template->binds()->delete();

    $template->binds()->create([
      'seqno' => 0,
      'bind' => '%1', 
      'description' => 'Recipient of the email',
    ]);        
    $template->binds()->create([
      'seqno' => 1,
      'bind' => '%2', 
      'description' => 'User who made the comment',
    ]);        
    $template->binds()->create([
      'seqno' => 2,
      'bind' => '%3', 
      'description' => 'Goal title',
    ]);      
    $template->binds()->create([
      'seqno' => 3,
      'bind' => '%4', 
      'description' => 'Comment added',
    ]);      
    
    //
    // 4. Employee Add a new Comment 
    //
    $template = GenericTemplate::updateOrCreate([
      'template' => 'EMPLOYEE_COMMENT_THE_GOAL',
    ], [
      'description' =>  'Send out email notification when employee adds a comment to supervisor\'s goal',
      'instructional_text' => 'You can add parameters',
      'sender' => '2',
      'subject' => 'PDP - %2 Added a Comment on One of Your Goals',
      'body' => "<p>Hello %1,</p><p>%2 added a comment on one of your goals in the Performance Development Platform.</p><p>Goal title: %3</p><p>Comment added:<br>%4</p><p>Log in to performance.gov.bc.ca to view and respond if required.</p><p>Thanks!</p>",
    ]);

    $template->binds()->delete();

    $template->binds()->create([
      'seqno' => 0,
      'bind' => '%1', 
      'description' => 'Recipient of the email',
    ]);        
    $template->binds()->create([
      'seqno' => 1,
      'bind' => '%2', 
      'description' => 'Person who added the comment',
    ]);        
    $template->binds()->create([
      'seqno' => 2,
      'bind' => '%3', 
      'description' => 'Goal title',
    ]);      
    $template->binds()->create([
      'seqno' => 3,
      'bind' => '%4', 
      'description' => 'Comment that was added',
    ]);      


    //
    // 5. Advice Schedule Conversation
    //
    
    $template = GenericTemplate::updateOrCreate([
      'template' => 'ADVICE_SCHEDULE_CONVERSATION',
    ], [
      'description' =>  'Send out email notification to schedule a conversation',
      'instructional_text' => 'You can add parameters',
      'sender' => '2',
      'subject' => 'PDP - %2 Would Like to Have a %3 Conversation With You',
      'body' => "<p>Hi %1,</p><p>%2 would like to have a %3 conversation with you in the Performance Development Platform. Please work with %2 to schedule a time in your Outlook calendar.</p><p>The deadline to complete your next performance conversation is %4.</p><p>Thank you!</p><p>www.performance.gov.bc.ca</p><p><br></p>",
    ]);

    $template->binds()->delete();

    $template->binds()->create([
      'seqno' => 0,
      'bind' => '%1', 
      'description' => 'Recipient of the email',
    ]);        
    $template->binds()->create([
      'seqno' => 1,
      'bind' => '%2', 
      'description' => 'Person who created the conversation',
    ]);        
    $template->binds()->create([
      'seqno' => 2,
      'bind' => '%3', 
      'description' => 'Topic of the conversation',
    ]);      
    $template->binds()->create([
      'seqno' => 3,
      'bind' => '%4', 
      'description' => 'Due date for recipient\'s next conversation',
    ]);         

    //
    // Template 6 : WEEKLY_OVERDUE_SUMMARY
    //
    $template = GenericTemplate::updateOrCreate([
      'template' => 'WEEKLY_OVERDUE_SUMMARY',
    ], [
      'description' =>  'Send out email notification to HR Administrator with a list of employees who are overdue for a conversation',
      'instructional_text' => 'You can add parameters',
      'sender' => '2',
      'subject' => 'PDP - Past Due Performance Conversations',
      'body' => "<p>Hello %1,</p><p>The following employees are overdue for a conversation in the Performance Development Platform:</p><p>%2</p><p><br></p>",
    ]);

    $template->binds()->delete();

    $template->binds()->create([
      'seqno' => 0,
      'bind' => '%1', 
      'description' => 'Recipient of the email',
    ]);        
    $template->binds()->create([
      'seqno' => 1,
      'bind' => '%2', 
      'description' => 'List of overdue employees: table with ID, name, email, organization, level 1, level 2, level 3, level 4, supervisor',
    ]);        

  }
}
