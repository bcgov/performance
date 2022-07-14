<?php

namespace App\Http\Controllers;

use App\Http\Requests\Conversation\ConversationRequest;
use App\Http\Requests\Conversation\SignoffRequest;
use App\Http\Requests\Conversation\UnSignoffRequest;
use App\Http\Requests\Conversation\UpdateRequest;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\ConversationTopic;
use App\Models\Participant;
use App\Models\EmployeeShare;
use App\Models\SharedProfile;
use App\Models\User;
use App\Models\EmployeeDemo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class ConversationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $viewType = 'conversations')
    {
        $authId = Auth::id();
        $user = User::find($authId);
        $supervisor = $user->reportingManager()->first();
        $supervisorId = (!$supervisor) ? null : $supervisor->id;
        $conversationMessage = Conversation::warningMessage();
        $conversationTopics = ConversationTopic::all();
        // $participants = Participant::all();
        $query = Conversation::with('conversationParticipants');
                
        $type = 'upcoming';
        if ($request->is('conversation/past') || $request->is('my-team/conversations/past')) {
            $query->where(function($query) use ($authId, $supervisorId, $viewType) {
                $query->where('user_id', $authId)->
                    orWhereHas('conversationParticipants', function($query) use ($authId, $supervisorId, $viewType) {
                        $query->where('participant_id', $authId);
                        if ($viewType === 'my-team')
                            $query->where('participant_id', '<>', $supervisorId);
                        return $query;
                    });
            })->whereNotNull('signoff_user_id')->whereNotNull('supervisor_signoff_id');
            //->whereDate('unlock_until', '<', Carbon::today());

            if ($request->has('user_id') && $request->user_id) {
                $user_id = $request->user_id;
                $query->where(function($query) use($user_id) {
                    $query->where('user_id', $user_id)->
                        orWhereHas('conversationParticipants', function($query) use ($user_id) {
                            $query->where('participant_id', $user_id);
                            return $query;
                        });
                });
            }
            if ($request->has('user_name') && $request->user_name) {
                $user_name = $request->user_name;
                $query->where(function ($query) use ($user_name) {
                    $query->whereHas('user', function ($query) use ($user_name) {
                        $query->where('name', 'like', "%$user_name%");
                    })
                    ->orWhereHas('conversationParticipants', function($query) use ($user_name) {
                        $query->whereHas('participant', function($query) use ($user_name) {
                            $query->where('name', 'like', "%$user_name%");
                        });
                    });
                });
            }
            if ($request->has('conversation_topic_id') && $request->conversation_topic_id) {
                $query->where('conversation_topic_id', $request->conversation_topic_id);
            }

            if ($request->has('start_date') && $request->start_date) {
                $query->whereRaw("IF(`sign_off_time` > `supervisor_signoff_time`, `sign_off_time`, `supervisor_signoff_time`) >= '$request->start_date'");
            }
            if ($request->has('end_date') && $request->end_date) {
                $query->whereRaw("IF(`sign_off_time` > `supervisor_signoff_time`, `sign_off_time`, `supervisor_signoff_time`) <= '$request->end_date'");
            }
            $myTeamQuery = clone $query;

           
            // With my Supervisor
            $sharedSupervisorIds = SharedProfile::where('shared_id', Auth::id())->with('sharedWithUser')->get()->pluck('shared_with')->toArray();
            array_push($sharedSupervisorIds, $supervisorId);
            $query->where(function($query) use ($sharedSupervisorIds) {
                $query->whereIn('user_id', $sharedSupervisorIds)->
                orWhereHas('conversationParticipants', function ($query) use ($sharedSupervisorIds) {
                    $query->whereIn('participant_id', $sharedSupervisorIds);
                });
            });
            
             // With My Team
            $myTeamQuery->whereNotIn('user_id', $sharedSupervisorIds);    
            $myTeamQuery->where('user_id', '<>', $authId);
            
            
            
            if (session()->has('existing_user_id')) {
                $tq = $myTeamQuery->toSql();
                $tb = $myTeamQuery->getBindings();
                print_r($tq);
                print_r($tb);
                exit;
            }
            
            $type = 'past';

            $conversations = $query->orderBy('id', 'DESC')->paginate(10);                       
            $myTeamConversations = $myTeamQuery->orderBy('id', 'DESC')->paginate(10);
        } else { // Upcoming
            $conversations = $query->where(function($query) use ($authId, $supervisorId, $viewType) {
                $query->where('user_id', $authId)->
                    orWhereHas('conversationParticipants', function($query) use ($authId, $supervisorId, $viewType) {
                        $query->where('participant_id', $authId);
                    });
            })->where(function($query) {
                $query->where(function($query) {
                    $query->whereNull('signoff_user_id')
                        ->orWhereNull('supervisor_signoff_id');
                })
                ->orWhere(function($query) {
                    $query->whereNotNull('signoff_user_id')
                          ->whereNotNull('supervisor_signoff_id')
                          ->whereDate('unlock_until', '>=', Carbon::today() );
                });
            });
            if ($request->has('user_id') && $request->user_id) {
                $user_id = $request->user_id;
                $query->where(function($query) use($user_id) {
                    $query->where('user_id', $user_id)->
                        orWhereHas('conversationParticipants', function($query) use ($user_id) {
                            $query->where('participant_id', $user_id);
                            return $query;
                        });
                });
            }

            if ($request->has('user_name') && $request->user_name) {
                $user_name = $request->user_name;
                $query->where(function ($query) use ($user_name) {
                    $query->whereHas('user', function ($query) use ($user_name) {
                        $query->where('name', 'like', "%$user_name%");
                    })
                    ->orWhereHas('conversationParticipants', function($query) use ($user_name) {
                        $query->whereHas('participant', function($query) use ($user_name) {
                            $query->where('name', 'like', "%$user_name%");
                        });
                    });
                });
            }
            
            if ($request->has('conversation_topic_id') && $request->conversation_topic_id) {
                $query->where('conversation_topic_id', $request->conversation_topic_id);
            }
            $myTeamQuery = clone $query;

            // get Conversations with My Team 
            $myTeamQuery->where(function ($query) use($supervisorId) {
                $query->whereDoesntHave('conversationParticipants', function ($query) use ($supervisorId) {
                    $query->where('participant_id', $supervisorId);
                });
                $query->where('user_id', '<>', $supervisorId);
            });
            
            // get Conversations with my supervisor
            $sharedSupervisorIds = SharedProfile::where('shared_id', Auth::id())->with('sharedWithUser')->get()->pluck('shared_with')->toArray();
            array_push($sharedSupervisorIds, $supervisorId);
            $query->where(function($query) use ($sharedSupervisorIds) {
                $query->whereIn('user_id', $sharedSupervisorIds)->
                orWhereHas('conversationParticipants', function ($query) use ($sharedSupervisorIds) {
                    $query->whereIn('participant_id', $sharedSupervisorIds);
                });
            });
            

            $conversations = $query->orderBy('id', 'DESC')->paginate(10);
            $myTeamConversations = $myTeamQuery->orderBy('id', 'DESC')->paginate(10);
            
        }
        
        $supervisor_conversations = array();
        foreach ($conversations as $conversation) {
            array_push($supervisor_conversations, $conversation->id);
        }

        $view = 'conversation.index';
        $reportees = $user->reportees()->get();
        $topics = ConversationTopic::all();
        if ($type === 'past') {
            $textAboveFilter = 'The list below contains all conversations that have been signed by both employee and supervisor. There is a two week period from the date of sign-off when either participant can un-sign the conversation and return it to the Open Conversations tab for further edits. Conversations marked with a locked icon have passed the two-week time period and require approval and assistance to re-open. If you need to unlock a conversation, submit an AskMyHR request to Myself > HR Software Systems Support > Performance Development Platform.';            
        } else {            
            $textAboveFilter = 'The list below contains all planned conversations that have yet to be signed-off by both employee and supervisor. Once a conversation has been signed-off by both participants, it will move to the Completed Conversations tab and become an official performance development record for the employee.';
        }
          
                
        return view($view, compact('type', 'conversations', 'myTeamConversations', 'conversationTopics', 'conversationMessage', 'viewType', 'reportees', 'topics', 'textAboveFilter', 'user', 'supervisor_conversations'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(ConversationRequest $request)
    {

        DB::beginTransaction();
        $authId = session()->has('original-auth-id') ? session()->get('original-auth-id') : Auth::id();
        $isDirectRequest = true;
        if(route('my-team.my-employee') == url()->previous()) {
            $isDirectRequest = false;
        }

        $actualOwner = $isDirectRequest ? $authId : $request->owner_id ?? $authId;

        $conversation = new Conversation();
        $conversation->conversation_topic_id = $request->conversation_topic_id;
        // $conversation->comment = $request->comment ?? '';
        $conversation->user_id = $actualOwner;
        $conversation->date = $request->date;
        $conversation->time = $request->time;
        $conversation->save();

        foreach ($request->participant_id as $key => $value) {
            ConversationParticipant::updateOrCreate([
                'conversation_id' => $conversation->id,
                'participant_id' => $value,
            ]);
        }

        if(!in_array($actualOwner, $request->participant_id)) {
            ConversationParticipant::updateOrCreate([
                'conversation_id' => $conversation->id,
                'participant_id' => $actualOwner,
            ]);
        }
        DB::commit();

        // Send a notification to all participants that you would like to schedule a conversation      
        $names = User::whereIn('employee_id', $request->participant_id)->pluck('name');

        $topic = ConversationTopic::find($request->conversation_topic_id);
        $sendMail = new \App\MicrosoftGraph\SendMail();
        $sendMail->toRecipients = $request->participant_id;
        $sendMail->sender_id = null;  // default sender is System
        $sendMail->useQueue = false;
        $sendMail->template = 'ADVICE_SCHEDULE_CONVERSATION';
        array_push($sendMail->bindvariables, implode(", ", $names->toArray() ) );
        array_push($sendMail->bindvariables, $conversation->user->name );
        array_push($sendMail->bindvariables, $conversation->topic->name );
        array_push($sendMail->bindvariables, $conversation->warningMessage()[0] );
        $response = $sendMail->sendMailWithGenericTemplate();

        if(request()->ajax()){
            return response()->json(['success' => true, 'message' => 'Conversation Created successfully']);
        }else{
            return redirect()->route('conversation.upcoming');
            /* if ($conversation->is_with_supervisor) {
                return redirect()->route('conversation.upcoming');
            } else {
                return redirect()->route('my-team.conversations.upcoming');
            } */
        }
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Conversation $conversation, Request $request)
    {
        $conversation->topics = ConversationTopic::all();
        
        $conversation_id = $conversation->id;
        $conversation->disable_signoff = false;
        
        if(!session()->has('view-profile-as')) {
            $current_user = auth()->user()->id;
            $conversation_participants = DB::table('conversation_participants')                        
                            ->where('conversation_id', $conversation_id)
                            ->where('participant_id', '<>', $current_user)
                            ->get();         
            $participant = $conversation_participants[0]->participant_id;

            $participant_is_mgr = false;
            $user_is_mgr = false; 

            //check direct report
            $check_mgr = DB::table('users')                        
                            ->where('reporting_to', $participant)
                            ->where('id', $current_user)
                            ->count(); 

            $own_conversation = false;
            if($check_mgr > 0) {
                $participant_is_mgr = true;
            } else {
                $check_mgr = DB::table('users')                        
                            ->where('reporting_to', $current_user)
                            ->where('id', $participant)
                            ->count(); 
                if($check_mgr > 0) {
                    $user_is_mgr = true;                
                } else {
                    // check shared
                    $check_mgr = DB::table('shared_profiles')                        
                            ->where('shared_id', $current_user)
                            ->where('shared_with', $participant)
                            ->count(); 
                    if($check_mgr > 0) {
                        $participant_is_mgr = true;
                    } else {
                        $user_is_mgr = true;     
                    }
                }
            }
        } else {
            $current_user = $request->session()->get('view-profile-as');
            $original_user = $request->session()->get('original-auth-id');
            $conversation_participants = DB::table('conversation_participants')                        
                            ->where('conversation_id', $conversation_id)
                            ->where('participant_id', '=', $original_user)
                            ->count();         
            if ($conversation_participants) {
                $user_is_mgr = true;
            } else {
                $user_is_mgr = false;
                $conversation->disable_signoff = true;
            }                 
        }
        
        if($user_is_mgr ==  true) {
            $view_as_supervisor = true;
        } else {
            $view_as_supervisor = false;
        }
        $request->session()->put('view_as_supervisor', $view_as_supervisor);
        $conversation->view_as_supervisor = $view_as_supervisor;
        //error_log($conversation->view_as_supervisor);

        return $conversation;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Conversation $conversation)
    {
    }

    /**
     * Update the specified resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateRequest $request, Conversation $conversation)
    {
        if ($request->field != 'conversation_participant_id') {
            $conversation->{$request->field} = $request->value;
        } elseif ($request->field == 'conversation_participant_id') {
            ConversationParticipant::where('conversation_id', $conversation->id)->delete();
            foreach ($request->value as $key => $value) {
                ConversationParticipant::updateOrCreate([
                    'conversation_id' => $conversation->id,
                    'participant_id' => $value,
                ]);
            }
        }

        $conversation->update();

        return $conversation;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Conversation $conversation)
    {
        $conversation->delete();

        return redirect()->back();
    }

    public function signOff(SignoffRequest $request, Conversation $conversation)
    {
        $view_as_supervisor = session()->get('view_as_supervisor');
        
        $authId = session()->has('original-auth-id') ? session()->get('original-auth-id') : Auth::id();
        $current_employee = DB::table('employee_demo')
                            ->select('employee_demo.employee_id')
                            ->join('users', 'employee_demo.guid', '=', 'users.guid')
                            ->where('users.id', $authId)
                            ->get();
        
        if ($current_employee[0]->employee_id != $request->employee_id) {
            return response()->json(['success' => false, 'Message' => 'Invalide Employee ID', 'data' => $conversation]);            
        }
  
        //if (!$conversation->is_with_supervisor) {
        if ($view_as_supervisor){
            $conversation->supervisor_signoff_id = $authId;
            $conversation->supervisor_signoff_time = Carbon::now();

            $conversation->supv_agree1 = $request->check_one_;
            $conversation->supv_agree2 = $request->check_two_;
            $conversation->supv_agree3 = $request->check_three_;
        } else {
            $conversation->signoff_user_id = $authId;
            $conversation->sign_off_time = Carbon::now();
            $conversation->empl_agree1 = $request->check_one;
            $conversation->empl_agree2 = $request->check_two;
            $conversation->empl_agree3 = $request->check_three;
            $conversation->team_member_agreement = $request->team_member_agreement;

            if (!$conversation->initial_signoff) {
                $conversation->initial_signoff = Carbon::now();
            }
        }
        $conversation->update();

        
        // Send a notification to all participants that you would like to schedule a conversation   
        $current_user = User::where('id', $authId)->first();

        $participants = $conversation->conversationParticipants->map(function ($item, $key) { 
                                return $item->participant; 
                        });
        $to_ids   = $participants->pluck('id')->toArray();
        $to_names = $participants->pluck('name')->toArray();

        if ($view_as_supervisor) {         
            $to_ids = array_diff( $to_ids, [ $current_user->id ] );
            $to_names = array_diff( $to_names, [ $current_user->name ] );
        } else {
            array_push($to_ids, $current_user->reporting_to );
            $to_ids = array_diff( $to_ids, [ $current_user->id ] );
            $to_names = array_diff( $to_names, [ $current_user->name ] );
        }

        $topic = ConversationTopic::find($request->conversation_topic_id);
        $sendMail = new \App\MicrosoftGraph\SendMail();
        $sendMail->toRecipients = $to_ids;
        $sendMail->sender_id = null;  // default sender is System
        $sendMail->useQueue = false;
        $sendMail->template = 'CONVERSATION_SIGN_OFF';
        array_push($sendMail->bindvariables, implode(", ", $to_names) );
        array_push($sendMail->bindvariables, $current_user->name );   //Person who signed the conversation 
        array_push($sendMail->bindvariables, $conversation->topic->name );  // Conversation topic
        $response = $sendMail->sendMailWithGenericTemplate();

        return response()->json(['success' => true, 'Message' => 'Sign Off Successfull', 'data' => $conversation]);
    }

    public function unsignOff(UnSignoffRequest $request, Conversation $conversation)
    {
        $view_as_supervisor = session()->get('view_as_supervisor');

        $authId = session()->has('original-auth-id') ? session()->get('original-auth-id') : Auth::id();
        $current_employee = DB::table('employee_demo')
                            ->select('employee_demo.employee_id')
                            ->join('users', 'employee_demo.guid', '=', 'users.guid')
                            ->where('users.id', $authId)
                            ->get();
        
        if ($current_employee[0]->employee_id != $request->employee_id) {
            return response()->json(['success' => false, 'Message' => 'Invalide Employee ID', 'data' => $conversation]);            
        }
        
        //if (!$conversation->is_with_supervisor) {
        if ($view_as_supervisor) {
            $conversation->supervisor_signoff_id = null;
            $conversation->supervisor_signoff_time = null;
        } else {
            $conversation->signoff_user_id = null;
            $conversation->sign_off_time = null;
        }
        $conversation->update();

        return
        response()->json(['success' => true, 'Message' => 'UnSign Successfull', 'data' => $conversation]);;
    }

    public function templates(Request $request, $viewType = 'conversations') {
        $authId = Auth::id();
        $user = User::find($authId);
        $query = new ConversationTopic;
        if ($request->has('search') && $request->search) {
            $query = $query->where('name', 'LIKE', "%$request->search%");
        }
        $templates = $query->get();
        $searchValue = $request->search ?? '';
        $conversationMessage = Conversation::warningMessage();
        return view('conversation.templates', compact('templates', 'searchValue', 'conversationMessage', 'viewType', 'user'));
    }

    public function templateDetail($id) {
        $authId = session()->has('original-auth-id') ? session()->get('original-auth-id') : Auth::id();
        $user = User::find($authId);
        $template = ConversationTopic::findOrFail($id);
        $allTemplates = ConversationTopic::all();
        $participants = session()->has('original-auth-id') ? User::where('id', Auth::id())->get() : $user->reportees()->get();
        $reportingManager = $user->reportingManager()->get();
        $sharedProfile = SharedProfile::where('shared_with', Auth::id())->with('sharedUser')->get()->pluck('sharedUser');
        $participants = $participants->toBase()->merge($reportingManager)->merge($sharedProfile);
 
        $adminShared=SharedProfile::select('shared_with')
        ->where('shared_id', '=', Auth::id())
        ->where('shared_item', 'like', '%2%')
        ->pluck('shared_with');
        $adminemps = User::select('users.*')
        ->whereIn('users.id', $adminShared)->get('id', 'name');
        $participants = $participants->merge($adminemps);

        return view('conversation.partials.template-detail-modal-body', compact('template','allTemplates','participants','reportingManager'));
    }
}
