<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\SharedProfile;

class Conversation extends Model
{
    use HasFactory, SoftDeletes;

    protected $with = ['topic', 'conversationParticipants', 'conversationParticipants.participant'];
    protected $appends = ['c_date', 'c_time', 'questions', 'date_time', 'is_current_user_participant', 'is_with_supervisor', 'last_sign_off_date', 'is_locked'];

    protected $casts = [
        'date' => 'datetime:Y-m-d',
        'time' => 'datetime:H:i:s',
        'sign_off_time' => 'datetime:Y-m-d',
        'supervisor_signoff_time' => 'datetime:Y-m-d',
        'initial_signoff' => 'datetime:Y-m-d',
        'unlock_until' => 'datetime:Y-m-d'
    ];

    public function topic()
    {
        return $this->belongsTo('App\Models\ConversationTopic', 'conversation_topic_id', 'id');
    }
    public function conversationParticipants()
    {
        return $this->hasMany('App\Models\ConversationParticipant');
    }

    public function getIsLockedAttribute() {
        if (!$this->initial_signoff) {
            return false;
        }
        return $this->initial_signoff->addDays(14)->isPast();
    }

    public function getInfoComment1Attribute() {
        if($this->attributes['info_comment1'] === null) return '';
        return $this->attributes['info_comment1'];
    }

    public function getInfoComment2Attribute() {
        if($this->attributes['info_comment2'] === null) return '';
        return $this->attributes['info_comment2'];
    }

    public function getInfoComment3Attribute() {
        if($this->attributes['info_comment3'] === null) return '';
        return $this->attributes['info_comment3'];
    }

    public function getInfoComment4Attribute() {
        if($this->attributes['info_comment4'] === null) return '';
        return $this->attributes['info_comment4'];
    }

    public function getInfoComment5Attribute() {
        if($this->attributes['info_comment5'] === null) return '';
        return $this->attributes['info_comment5'];
    }

    // If conversation is with
    public function getIsWithSupervisorAttribute() {
        return $this->isWithSupervisor();
    }

    private function isWithSupervisor($userID = null) {
        if ($userID === null) {
            $checkForOriginalUser = true;
            $authId = ($checkForOriginalUser && session()->has('original-auth-id')) ? session()->get('original-auth-id') : Auth::id();
        } else {
            $authId = $userID;
        }
        $user = User::find($authId);
        $sharing = SharedProfile::find($authId);
        $reportingManager = $user ? $user->reportingManager()->first() : null;
        
        //check sharing manager
        $sharingManagers =  DB::table('shared_profiles')                        
                            ->where('shared_id', $authId)
                            ->get()->toArray(); 
        $sharing = array();
        foreach ($sharingManagers as $sharingManager) {
            array_push($sharing, $sharingManager->shared_with);
        }                
        if (!$reportingManager && count($sharing) == 0) {
            return false;
        }
        
        foreach ($this->conversationParticipants->toArray() as $cp) {
            if ($cp['participant_id'] == $reportingManager->id) {
                return true;
            }
            if (in_array($cp['participant_id'], $sharing)) {
                return true;
            }
        }
        return false;
    }

    public function getCDateAttribute()
    {
        return $this->date->format('M d, Y');
    }

    public function getLastSignOffDateAttribute()
    {
        return $this->supervisor_signoff_time > $this->sign_off_time ? $this->sign_off_time : $this->supervisor_signoff_time;
    }

    public function getCTimeAttribute()
    {
        return $this->time->format('h:i A');
    }

    public function getDateTimeAttribute()
    {
        return Carbon::parse($this->date->format('M d, Y') .' '. $this->time->format('h:i A')); // $this->time->format('h:i A');
    }

    public function getQuestionsAttribute()
    {
        // return Config::get('global.conversation.topic.' . $this->conversation_topic_id . '.questions');
        return ConversationTopic::find($this->conversation_topic_id)->question_html;
    }

    // Should not be used.
    public static function hasNotDoneAtleastOnceIn4Months()
    {
        $latestPastConversation = self::latestPastConversation();
        if ($latestPastConversation) {
            return $latestPastConversation->date_time->addDays(122)->isPast();
        }
        return true;
    }

    // Should not be used.
    public static function hasNotYetScheduledConversation($user_id)
    {
        return !self::where('user_id', $user_id)->count() > 0;
    }

    public static function getLastConv($ignoreList = [], $user = null) {
        if ($user === null) 
            $user = Auth::user();
        $authId = $user->id;

        $lastConv = self::where(function ($query) use ($authId) {
            $query->where('user_id', $authId)->orWhereHas('conversationParticipants', function ($query) use ($authId) {
                return $query->where('participant_id', $authId);
            });
        })->whereNotNull('signoff_user_id')
        ->whereNotNull('supervisor_signoff_id')
        ->whereNotIn('id', $ignoreList)
        ->orderBy('sign_off_time', 'DESC')
        ->first();
                        
        if ($lastConv && !$lastConv->isWithSupervisor($user->id)) {
            $ignoreList[] = $lastConv->id;
            $lastConv = self::getLastConv($ignoreList, $user);
        }
        return $lastConv;
    }

    public static function warningMessage() {
        $lastConv = self::getLastConv();

        $authId = Auth::id();
        $user = User::find($authId);
        if ((session()->get('original-auth-id') == Auth::id() or session()->get('original-auth-id') == null )){
            // $msg1 = "You are required to complete a performance conversation every 4 months at minimum. You are overdue. Please complete a conversation as soon as possible.";
            // $msg2 = "Your next performance conversation is due by ";
            // $msg3 = "You must complete your first performance conversation by ";            
            $msg = "Next performance conversation is due by ";            
        } else {
            // $msg1 = $user->name . " is required to complete a performance conversation every 4 months at minimum. It is overdue. Please complete a conversation as soon as possible.";
            // $msg2 = $user->name . "'s next performance conversation is due by ";
            // $msg3 = $user->name . " must complete the first performance conversation by ";    
            $msg = "Next performance conversation is due by ";                        
        }
              
        
        if ($lastConv) {
            if ($lastConv->sign_off_time->addMonths(4)->lt(Carbon::now())) {
                $nextDueDate = $lastConv->sign_off_time->addMonths(4);    
                return [
                    //  $msg1,   
                    $msg.$nextDueDate->format('d-M-y'),
                    "danger"
                ];
            }
            $nextDueDate = $lastConv->sign_off_time->addMonths(4);
            $diff = Carbon::now()->diffInMonths($lastConv->sign_off_time->addMonths(4), false);
            return [
                // "Your last performance conversation was completed on ".$lastConv->sign_off_time->format('d-M-y').". 
                // $msg2. $lastConv->sign_off_time->addMonths(4)->format('d-M-y'),
                $msg. $lastConv->sign_off_time->addMonths(4)->format('d-M-y'),
                $diff < 0 ? "danger" : ($diff < 1 ? "warning" : "success")
            ];
        }
        $user = Auth::user();
        $nextDueDate = $user->joining_date ?  : '';
        $diff = Carbon::now()->diffInMonths($nextDueDate, false);

        if ($nextDueDate < Carbon::createFromDate(2022, 10, 14)) {
            $DDt = abs (($user->id % 10) - 1) * 5 + (($user->id % 5));
            $nextDueDate = Carbon::createFromDate(2022, 10, 14)->addDays($DDt);
        }

        /* dd([
            Carbon::now()->format('d-M-y'),
            $nextDueDate->format('d-M-y'),
            $diff
        ]); */
        return [
            // $msg3 . $nextDueDate->format('d-M-y'),
            $msg. $nextDueDate->format('d-M-y'),
            $diff < 0 ? "danger" : ($diff < 1 ? "warning" : "success")
        ];
    }

    public static function nextConversationDue($user = null) {
        if ($user === null)
            $user = Auth::user();
        $lastConv = self::getLastConv([], $user);
        $nextConvDate =  ($lastConv) ? $lastConv->sign_off_time->addMonths(4)->format('M d, Y') : (
            $user->joining_date ? $user->joining_date->addMonths(4)->format('M d, Y') : ''
        );
        if ((!$nextConvDate) || ($nextConvDate < Carbon::createFromDate(2022, 10, 14))) {
            $DDt = abs (($user->id % 10) - 1) * 5 + (($user->id % 5));
            $nextConvDate = Carbon::createFromDate(2022, 10, 14)->addDays($DDt);
        }
        return $nextConvDate;
    }

    public static function latestPastConversation()
    {
        return self::whereNotNull('signoff_user_id')->orderBy('date', 'DESC')->first();
    }

    public function getIsCurrentUserParticipantAttribute()
    {
        foreach ($this->conversationParticipants->toArray() as $cp) {
            if ($cp['participant_id'] === Auth::id())
                return true;
        }
        return false;
    }

    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getIsUnlockAttribute() {
        if (!$this->unlock_until) {
            return false;
        }
        return !($this->unlock_until->isPast());
        
    }

    public function signoff_user() {
        return $this->belongsTo(User::class, 'signoff_user_id');
    }

    public function signoff_supervisor() {
        return $this->belongsTo(User::class, 'supervisor_signoff_id');
    }


}
