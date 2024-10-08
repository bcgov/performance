<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Goal;
use App\Models\User;
use App\Models\GoalType;
use App\Models\LinkedGoal;
use App\Models\GoalComment;
use App\Models\SharedProfile;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Scopes\NonLibraryScope;
use App\DataTables\GoalsDataTable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\DashboardNotification;
use App\Http\Requests\Goals\CreateGoalRequest;
use App\Http\Requests\Goals\EditSuggestedGoalRequest;
use App\MicrosoftGraph\SendMail;
use App\Models\Tag;
use App\Models\GoalSharedWith;
use App\Models\EmployeeDemo;
use App\Models\Audit;

class GoalController extends Controller
{
    /**
    * Display a listing of the resource.
    *
    * @return \Illuminate\Http\Response
    */
    public function index(GoalsDataTable $goalDataTable, Request $request)
    {
        $authId = Auth::id();
        $goaltypes = GoalType::all()->toArray();
        $user = User::find($authId);
        $tags = Tag::all()->sortBy("name")->toArray();
        
        $request->session()->forget('is_bank');
              
        $tagsList = Tag::all()->sortBy("name")->toArray();
        array_unshift($tagsList, [
            "id" => "0",
            "name" => "Any"
        ]);   
        
        $sysstatus = \Config::get("global.status");
        
        $statusList[0]['id'] = '0';
        $statusList[0]['name'] = 'Any';
        $i = 1;
        foreach($sysstatus as $statusname => $statusitem) {
            if($statusname != 'active') {
                $statusList[$i]['id'] = $statusname;
                $statusList[$i]['name'] = ucwords($statusname);
                $i++;
            }
        }
       

        $createdBy = Goal::withoutGlobalScope(NonLibraryScope::class)
            ->where('is_library', true)
            ->whereHas('sharedWith', function($query) {
                $query->where('user_id', Auth::id());
            })
            ->with('user')
            ->groupBy('user_id')
            ->get()
            ->pluck('user')
            ->toArray();

        array_unshift($createdBy , [
            "id" => "0",
            "name" => "Any"
        ]);

        $myTeamController = new MyTeamController(); 
        $employees = $myTeamController->myEmployeesAjax();
        

        $query = Goal::with('user')
        ->with('goalType');
        $type = 'past';
                        
        $empShared=SharedProfile::select('shared_id')
        ->where('shared_with', '=', $authId)
        ->where('shared_item', 'like', '%1%')
        ->pluck('shared_id');
        $empShared = User::select('users.*')
        ->whereIn('users.id', $empShared)->get();
        $employees = $employees->merge($empShared);   
        
        if(count($employees)>0) {
            $request->session()->put('has_employees', true);
        }        
        
        $type_desc_arr = array();
        foreach($goaltypes as $goalType) {
            if(isset($goalType['description']) && isset($goalType['name'])) {                
                $item = "<b>" . $goalType['name'] . " Goals</b> ". str_replace($goalType['name'] . " Goals","",$goalType['description']);
                array_push($type_desc_arr, $item);
            }
        }
        $type_desc_str = implode('<br/><br/>',$type_desc_arr);
        $goal_types_modal = $goaltypes;

        array_unshift($goaltypes, [
            "id" => "0",
            "description" => '',
            "name" => "Any"
        ]);        

        $query = $query->leftjoin('goal_tags', 'goal_tags.goal_id', '=', 'goals.id')
        ->join('users as owner_users', 'goals.user_id', 'owner_users.id')
        ->leftjoin('tags', 'tags.id', '=', 'goal_tags.tag_id')    
        ->leftjoin('goal_types', 'goal_types.id', '=', 'goals.goal_type_id')
        ->leftJoin('goals_shared_with', 'goals_shared_with.goal_id', 'goals.id')
        ->leftJoin('users as shared_users', 'shared_users.id', 'goals_shared_with.user_id')
        ->leftJoin('employee_demo as employee', 'employee.employee_id', 'shared_users.employee_id');
        
        session()->forget('from_share');
        if ($request->is("goal/current")) {
            $type = 'current';
            $query = $query->where('goals.user_id', $authId)
                    ->where('status', '=', 'active')
                    ->select('goals.*', DB::raw('group_concat(distinct tags.name separator ", ") as tagnames')
                            ,DB::raw('group_concat(distinct goals_shared_with.user_id separator "|") as shared_user_id')
                            ,DB::raw('group_concat(distinct shared_users.name separator "|") as shared_user_name')
                            ,DB::raw('group_concat(distinct concat(employee.employee_last_name, ", ", 
                            employee.employee_first_name, " ", 
                            employee.employee_middle_name) separator "|") as employee_name')
                            ,'goal_types.name as typename');            
        } else if($request->is("goal/share")){
            $type = 'supervisor';
            session()->put('from_share', true);
            $goals = $user->sharedGoals()->paginate(8);
            return view('goal.index', compact('goals', 'type', 'goaltypes','goal_types_modal','user', 'tags', 'type_desc_str'));
        } else {
            $query = $query->where('status', '<>', 'active')
            ->select('goals.*'
                    ,'owner_users.id as owner_id'
                    ,'owner_users.name as owner_name'
                    , DB::raw('group_concat(distinct tags.name separator ", ") as tagnames')
                    ,DB::raw('group_concat(distinct goals_shared_with.user_id separator ",") as shared_user_id')
                    ,DB::raw('group_concat(distinct shared_users.name separator ",") as shared_user_name')
                    ,'goal_types.name as typename')
            ->where(function($query) use($authId) {
                $query->where('goals.user_id',$authId)
                       ->orWhere('goals_shared_with.user_id',$authId);
            });
                        
            //$query = $query->where('status', '<>', 'active')->select('goals.*', DB::raw('group_concat(distinct tags.name separator ", ") as tagnames'), 'goal_types.name as typename');
        }
        
        
        if(isset($request->title) && $request->title != ''){
            $query = $query->where('goals.title', 'LIKE', "%$request->title%");
        }
        if(isset($request->goal_type) && $request->goal_type != 0){
            $query = $query->where('goal_types.id', '=', "$request->goal_type");
        }
        if(isset($request->tag_id) && $request->tag_id != 0){
            $query = $query->where('goal_tags.tag_id', '=', "$request->tag_id");
        }
        if(isset($request->status) && $request->status != 0){
            $query = $query->where('goals.status', 'LIKE', "$request->status");
        }
        
        if (session()->get('original-auth-id') && session()->get('original-auth-id') != Auth::id()){
            $query = $query->where('goal_types.name', '<>', 'Private');
        }
        
        
        
        if(isset($request->filter_start_date) && $request->filter_start_date != ''){
            $start_date_array = explode('-', $request->filter_start_date);
            if(count($start_date_array) == 2) {
                $from = date_create(trim($start_date_array[0]));
                $to = date_create(trim($start_date_array[1]));                
                $from = date_format($from,"Y-m-d 00:00:00");
                $to = date_format($to,"Y-m-d 23:59:59");
                $query = $query->whereBetween('goals.start_date', [$from, $to]);
            }
        }
        if(isset($request->filter_target_date) && $request->filter_target_date != ''){
            $target_date_array = explode('-', $request->filter_target_date);
            if(count($target_date_array) == 2) {
                $from = date_create(trim($target_date_array[0]));
                $to = date_create(trim($target_date_array[1]));                
                $from = date_format($from,"Y-m-d 00:00:00");
                $to = date_format($to,"Y-m-d 23:59:59");
                $query = $query->whereBetween('goals.target_date', [$from, $to]);
            }
        }
        //$authId = session()->has('original-auth-id') ? session()->get('original-auth-id') : Auth::id();
        
        if(!session()->has('sortby')) {
            $sortby = '';
            $sortorder = 'ASC';
            session()->put('sortby', '');
            session()->put('sortorder', 'ASC');
        }        
        
        if(isset($request->sortby) && $request->sortby != ''){
            $session_sortby = session()->get('sortby');
            if ($session_sortby != $request->sortby) {
                session()->put('sortby', $request->sortby);
                session()->put('sortorder', 'ASC');
                $sortby = $request->sortby;
                $sortorder = 'ASC';
            } else {                 
                $sortby = $request->sortby;
                if (session()->get('sortorder') == 'ASC'){
                    $sortorder = 'DESC';
                } else {
                    $sortorder = 'ASC';
                }
                session()->put('sortorder',$sortorder);
            }                     
        } else {
            $sortby = session()->get('sortby');
            $sortorder = session()->get('sortorder');  
        }
        
        if($sortby != '') {
            $query = $query->orderBy($sortby, $sortorder);
        }
      
        $goals = $query->groupBy('id');
        $goals = $query->paginate(10);

        foreach ($goals as $goal){
            $goal->login_role = 'owner';
            if($goal->user_id != $authId){
                $goal->login_role = 'sharee';
            }
        }
        
        $from = 'goal';        
        
        return view('goal.index', compact('goals', 'type', 'goaltypes', 'goal_types_modal', 'tagsList', 'sortby', 'sortorder', 'createdBy', 'user', 'employees', 'tags', 'type_desc_str', 'statusList','from', 'authId'));
    }

    /**
    * Show the form for creating a new resource.
    *
    * @return \Illuminate\Http\Response
    */
    public function create()
    {
        $goaltypes = GoalType::all();
        return view('goal.create', compact('goaltypes'));
    }

    /**
    * Store a newly created resource in storage.
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response
    */
    public function store(CreateGoalRequest $request)
    {
        $input = $request->validated();
        $tags = '';
        $input['user_id'] = Auth::id();
        
        if(isset($input['tag_ids'])) {
            $tags = $input['tag_ids'];
            unset($input['tag_ids']);
        }
        if($input["created_goal_id"] == 0) {
            $goal = Goal::create($input);
            // Retrieve the goal_id after creating the Goal
            $goal_id = $goal->id;
        } else {
            $goal_id = $input["created_goal_id"];
            $goal = Goal::withoutGlobalScope(NonLibraryScope::class)->findOrFail($goal_id); 
            $goal->update($input);
        }
        

        if ($tags != '') {
            $actualAuthId = session()->has('original-auth-id') ? session()->get('original-auth-id') : Auth::id();
            $arrayOrigTags = [];
            $goal->tags()->sync($tags);
            $arrayNewTags = array_column($goal->tags->toArray(), 'id');
            // Insert audits for Goal Tag changes
            if(array_diff($arrayOrigTags, $arrayNewTags) or array_diff($arrayNewTags, $arrayOrigTags)) {
                // $actualAuthId = session()->has('original-auth-id') ? session()->get('original-auth-id') : Auth::id();
                $newAudit = new Audit;
                $newAudit->user_type = 'App\Models\User';
                $newAudit->user_id = $actualAuthId;
                $newAudit->original_auth_id = $newAudit->user_id;
                $newAudit->event = 'created';
                $newAudit->auditable_type = 'App\Models\GoalTags';
                $newAudit->auditable_id = $goal->id;
                $newAudit->old_values = json_encode(Tag::whereIn('id', $arrayOrigTags)->pluck('id', 'name'));
                $newAudit->new_values = json_encode(Tag::whereIn('id', $arrayNewTags)->pluck('id', 'name'));
                $newAudit->url = (empty($_SERVER['HTTPS']) ? 'http' : 'https')."://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
                $newAudit->save();
            }
        }
        return response()->json(['success' => true, 'message' => 'Goal Created successfully', 'goal_id' => $goal_id]);
    }

    /**
    * Display the specified resource.
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
    public function show($id, Request $request)
    {
        $from = $request->from;        
        // TODO: Manage Auth when we are clear with Supervisor Logic.
        $goal = Goal::withoutGlobalScope(NonLibraryScope::class)->/* where('user_id', Auth::id())
        -> */where('id', $id)
        ->with('goalType')
        ->with('comments')
        ->firstOrFail();

        //get user info and check if user comes from supervisor, employee, manager shared with employee, or goal shared with
        $authId = Auth::id();
        $can_access = false;
        if($authId == $goal->user_id) {
            $can_access = true;
        } 

        $user = User::findOrFail($goal->user_id);
        if($user->reporting_to == $authId) {
            $can_access = true;
        }         
        $shared_info = SharedProfile::select('shared_with')
                        ->where('shared_id', Auth::id())
                        ->where('shared_item', 'like', '%1%')
                        ->get();

        $shared_with = $shared_info->pluck('shared_with')->toArray();
        if(in_array($authId, $shared_with)){
            $can_access = true;
        }


        $goal_sharedWithList = GoalSharedWith::from('goals_shared_with AS gsw')
                ->where('gsw.goal_id', $goal->id)
                ->get();
        $goal_sharedWith = $goal_sharedWithList->pluck('user_id')->toArray();
        if(in_array($authId, $goal_sharedWith)){
            $can_access = true;
        }


        if($can_access) {
            $linkedGoalsIds = LinkedGoal::where('user_goal_id', $id)->pluck('supervisor_goal_id');

            $linkedGoals
            = Goal::with('goalType', 'comments')
            ->whereIn('id', $linkedGoalsIds)
            ->get();

            
            if (($goal->last_supervisor_comment == 'Y') and (($goal->user_id == session()->get('original-auth-id')) or (session()->get('original-auth-id') == null))) {

                $goal->last_supervisor_comment = 'N';
                $goal->save();
            };

            return view('goal.show', compact('goal', 'linkedGoals', 'from'));
        } else {
            echo "You don't have the right permission to access this goal.";
        }
    }

    public function getSupervisorGoals($id) {
        $goal = Goal::findOrFail($id);
        $linkedGoalsIds = LinkedGoal::where('user_goal_id', $id)->pluck('supervisor_goal_id');

        $supervisorGoals = Goal::whereIn('id', [997, 998, 999])->with('goalType')
        ->whereNotIn('id', $linkedGoalsIds)
        ->with('comments')->get();

        return view('goal.partials.supervisor-goal-content', compact('goal', 'supervisorGoals'));
    }

    /**
    * Show the form for editing the specified resource.
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
    public function edit($id, Request $request)
    {
        $from = $request->from;
        
        $goal = Goal::withoutGlobalScope(NonLibraryScope::class)
        ->where('user_id', Auth::id())
        ->where('id', $id)
        ->with('goalType')
        ->with('tags')        
        ->firstOrFail();
        
        
        $goaltypes = GoalType::all()->toArray();
        if($from == 'bank'){
            $goaltypes = GoalType::where('name', '!=', 'private')->get()->toArray();
        }


        $type_desc_arr = array();
        foreach($goaltypes as $goalType) {
            if(isset($goalType['description']) && isset($goalType['name'])) {                
                $item = "<b>" . $goalType['name'] . " Goals</b> ". str_replace($goalType['name'] . " Goals","",$goalType['description']);
                array_push($type_desc_arr, $item);
            }
        }
        $type_desc_str = implode('<br/><br/>',$type_desc_arr);
        
        
        $tags = Tag::all(["id","name", "description"])->sortBy("name")->toArray();

        return view('goal.edit', compact("goal", "goaltypes", "type_desc_str", "tags"));
        // return redirect()->route('goal.edit', $id);
    }

    /**
    * Update the specified resource in storage.
    *
    * @param  \Illuminate\Http\Request  $request
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
    public function update(Request $request, $id, $is_ajax = false)
    {        
        $goal = Goal::withoutGlobalScope(NonLibraryScope::class)->findOrFail($id); 
        if ($request->title == '' || $request->tag_ids== '') {
            if($request->title == '') {
                $request->session()->flash('title_miss', 'The title field is required');
            } elseif($request->tag_ids == '') {
                $request->session()->flash('tags_miss', 'The tags field is required');
            }                 
            return \Redirect::route('goal.edit', [$id])->with('message', " There are one or more errors on the page. Please review and try again.");
        } else {
            //$input = $request->validated();
            $input["title"] = $request->title;
            $input["start_date"] = $request->start_date;
            $input["target_date"] = $request->target_date;
            $input["what"] = $request->what;
            $input["why"] = $request->why;
            $input["how"] = $request->how;
            $input["measure_of_success"] = $request->measure_of_success;
            $input["goal_type_id"] = $request->goal_type_id;
            $input["tag_ids"] = $request->tag_ids;
            if(isset($request->is_mandatory)){
                $input["is_mandatory"] = $request->is_mandatory;
            }
        }
        
        $tags = '';
        if(isset($input['tag_ids'])) {
            $tags = $input['tag_ids'];
        } 
        unset($input['tag_ids']);        
        $goal->update($input);
        $actualAuthId = session()->has('original-auth-id') ? session()->get('original-auth-id') : Auth::id();
        if ($tags != '') {
            $arrayOrigTags = array_column($goal->tags->toArray(), 'id');
            $goal->tags()->sync($tags);
            // Insert audits for Goal Tag changes
            if(array_diff($arrayOrigTags, $request->tag_ids) or array_diff($request->tag_ids, $arrayOrigTags)) {
                $newAudit = new Audit;
                $newAudit->user_type = 'App\Models\User';
                $newAudit->user_id = $actualAuthId;
                $newAudit->original_auth_id = $newAudit->user_id;
                $newAudit->event = 'updated';
                $newAudit->auditable_type = 'App\Models\GoalTags';
                $newAudit->auditable_id = $id;
                $newAudit->old_values = json_encode(Tag::whereIn('id', $arrayOrigTags)->pluck('id', 'name'));
                $newAudit->new_values = json_encode(Tag::whereIn('id', $request->tag_ids)->pluck('id', 'name'));
                $newAudit->url = (empty($_SERVER['HTTPS']) ? 'http' : 'https')."://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
                $newAudit->save();
            }
        } else {
            $arrayOrigTags = array_column($goal->tags->toArray(), 'id');
            DB::table('goal_tags')->where('goal_id', $id)->delete();
            // Insert audits for Goal Tag changes
            if(array_diff($arrayOrigTags, $request->tag_ids) or array_diff($request->tag_ids, $arrayOrigTags)) {
                $newAudit = new Audit;
                $newAudit->user_type = 'App\Models\User';
                $newAudit->user_id = $actualAuthId;
                $newAudit->original_auth_id = $newAudit->user_id;
                $newAudit->event = 'updated';
                $newAudit->auditable_type = 'App\Models\GoalTags';
                $newAudit->auditable_id = $id;
                $newAudit->old_values = json_encode(Tag::whereIn('id', $arrayOrigTags)->pluck('id', 'name'));
                $newAudit->new_values = json_encode(Tag::whereIn('id', $request->tag_ids)->pluck('id', 'name'));
                $newAudit->url = (empty($_SERVER['HTTPS']) ? 'http' : 'https')."://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
                $newAudit->save();
            }
        }
        if($is_ajax){
            return response()->json(['success' => true, 'message' => 'Goal Updated successfully']);
        } else {
            if ($request->datatype != "auto") {
                if ($goal->is_library) {
                    return \Redirect::route('goal.library');
                } else {
                    return \Redirect::route('goal.show', [$id])->with('autosave', " Goal updated.");
                }
            } else {
                return \Redirect::route('goal.edit', [$id])->with('autosave', " Goal updated.");
            }
        }
    }

    public function getSuggestedGoal($id) {
        return $this->respondeWith(Goal::withoutGlobalScope(NonLibraryScope::class)->findOrFail($id));
    }

    public function updateSuggestedGoal(EditSuggestedGoalRequest $request, $id)
    {
        $goal = Goal::withoutGlobalScope(NonLibraryScope::class)->findOrFail($id);
        $input = $request->validated();

        $goal->update($input);

        return redirect()->route('my-team.suggested-goals');
    }

    /**
    * Remove the specified resource from storage.
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
    public function destroy($id)
    {
        $goal = Goal::withoutGlobalScope(NonLibraryScope::class)->find($id);
        if (!$goal) {
            abort(404);
        }
        if ($goal->user_id !== Auth::id()) {
            abort(403);
        }
        $arrayOrigTags = array_column($goal->tags->toArray(), 'id');
        $query1 = DB::table('goal_tags')
            ->where('goal_id', '=', $goal->id)
            ->delete();
        // Insert audits for Goal Tag changes
        $arrayNewTags = [];
        if(array_diff($arrayOrigTags, $arrayNewTags) or array_diff($arrayNewTags, $arrayOrigTags)) {
            $actualAuthId = session()->has('original-auth-id') ? session()->get('original-auth-id') : Auth::id();
            $newAudit = new Audit;
            $newAudit->user_type = 'App\Models\User';
            $newAudit->user_id = $actualAuthId;
            $newAudit->original_auth_id = $newAudit->user_id;
            $newAudit->event = 'deleted';
            $newAudit->auditable_type = 'App\Models\GoalTags';
            $newAudit->auditable_id = $id;
            $newAudit->old_values = json_encode(Tag::whereIn('id', $arrayOrigTags)->pluck('id', 'name'));
            $newAudit->new_values = json_encode(Tag::whereIn('id', $arrayNewTags)->pluck('id', 'name'));
            $newAudit->url = (empty($_SERVER['HTTPS']) ? 'http' : 'https')."://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
            $newAudit->save();
        }
        $query2 = DB::table('goal_bank_orgs')
            ->where('goal_id', '=', $goal->id)
            ->delete();
        $query3 = DB::table('goals_shared_with')
            ->where('goal_id', '=', $goal->id)
            ->delete();
        $goal->delete();
        DashboardNotification::where('notification_type', 'GB')
                                        ->where('related_id', $goal->id)
                                        ->delete();

        return redirect()->back();
    }


    private function getNohiddenGoals($filter){
        $authId = Auth::id();
        $user = User::find($authId);
        $adminGoals = Goal::withoutGlobalScopes()
            ->select('goals.id', 'goals.title', 'goals.goal_type_id', 'goals.created_at', 'goals.user_id', 'goals.is_mandatory', 'goals.display_name', 'goal_types.name as typename', 'u2.id as creator_id', 'u2.name as username', 'audits.created_at as last_mod_date', DB::raw("(SELECT group_concat(distinct tags.name separator '<br/>') FROM goal_tags LEFT JOIN tags ON tags.id = goal_tags.tag_id WHERE goal_tags.goal_id = goals.id) as tagnames"))
            ->join('goal_bank_orgs', function ($qon) {
                return $qon->on('goal_bank_orgs.goal_id', 'goals.id')
                    ->on('goal_bank_orgs.version', \DB::raw(2))
                    ->on('goal_bank_orgs.inherited', \DB::raw(0));
            })
            ->join('employee_demo', 'employee_demo.orgid', 'goal_bank_orgs.orgid')
            ->join('users', function ($qon) use ($authId) {
                return $qon->on('users.employee_id', 'employee_demo.employee_id')
                    ->on('users.id', \DB::raw($authId));
            })
            ->leftjoin('users as u2', 'u2.id', 'goals.created_by')
            ->leftjoin('goal_types', 'goal_types.id', 'goals.goal_type_id')   
            ->whereIn('goals.by_admin', [1, 2])
            ->where('goals.is_library', true) 
            ->whereNull('goals.deleted_at')
            ->leftjoin('audits', function($joinon){
                return $joinon->on('audits.auditable_id', 'goals.id')
                ->whereNotExists(function($ne){
                    return $ne->select(DB::raw(1))
                    ->from('audits as audits2')
                    ->where('audits2.auditable_type', 'audits.auditable_type')
                    ->where('audits2.auditable_id', 'audits.auditable_id')
                    ->where('audits2.id', '>', 'audits.id');
                });
            })        
            ->where('audits.auditable_type', 'like', '%App\\\\Models\\\\Goal%%')  
            // ->whereRaw('employee_demo.pdp_excluded = 0')  
            ->groupBy('goals.id', 'goals.title', 'goals.goal_type_id', 'goals.created_at', 'goals.user_id', 'u2.id', 'u2.name', 'goals.is_mandatory');
              
        // Admin List filter below
        if ($filter->has('goal_bank_mandatory') && $filter->goal_bank_mandatory !== null) {
            if ($filter->goal_bank_mandatory == "1") {
                $adminGoals = $adminGoals->where('is_mandatory', $filter->goal_bank_mandatory);
            }
            else {
                $adminGoals = $adminGoals->where(function ($adminGoals1) {
                    $adminGoals1->whereNull('is_mandatory');
                    $adminGoals1->orWhere('is_mandatory', 0);
                });
            }
        }
        if ($filter->has('goal_bank_types') && $filter->goal_bank_types) {
            $adminGoals = $adminGoals->whereHas('goalType', function($adminGoals1) use ($filter) {
                return $adminGoals1->where('goal_type_id', $filter->goal_bank_types);
            });
        }
        if ($filter->has('goal_bank_tags') && $filter->goal_bank_tags) {
            $adminGoals = $adminGoals->whereRaw("EXISTS (SELECT 1 FROM goal_tags WHERE goal_tags.goal_id = goals.id AND goal_tags.tag_id = '{$filter->goal_bank_tags}')");
        }
        if ($filter->has('goal_bank_title') && $filter->goal_bank_title) {
            $adminGoals = $adminGoals->where('goals.title', "LIKE", "%$filter->goal_bank_title%");
        }
        if ($filter->has('goal_bank_dateadd') && $filter->goal_bank_dateadd && Str::lower($filter->goal_bank_dateadd) !== 'any') {
            $dateadded = $filter->goal_bank_dateadd;
            $adminGoals = $adminGoals->whereDate('goals.created_at', '>=', $dateadded . " 00:00:00");
            $adminGoals = $adminGoals->whereDate('goals.created_at', '<=', $dateadded . " 23:59:59");
        }
        if ($filter->has('goal_bank_createdby') && $filter->goal_bank_createdby) {
            if(is_numeric($filter->goal_bank_createdby)) {
                $adminGoals = $adminGoals->where('created_by', $filter->goal_bank_createdby)->whereNull('display_name');
            } else {
                $adminGoals = $adminGoals->where('display_name', 'like',$filter->goal_bank_createdby);
            }
        }

        $adminGoalsInherited = Goal::withoutGlobalScopes()
            ->select('goals.id', 'goals.title', 'goals.goal_type_id', 'goals.created_at', 'goals.user_id', 'goals.is_mandatory', 'goals.display_name', 'goal_types.name as typename', 'u2.id as creator_id', 'u2.name as username', 'audits.created_at as last_mod_date', DB::raw("(SELECT group_concat(distinct tags.name separator '<br/>') FROM goal_tags LEFT JOIN tags ON tags.id = goal_tags.tag_id WHERE goal_tags.goal_id = goals.id) as tagnames"))
            ->join('goal_bank_orgs', function ($qon) {
                return $qon->on('goal_bank_orgs.goal_id', 'goals.id')
                    ->on('goal_bank_orgs.version', \DB::raw(2))
                    ->on('goal_bank_orgs.inherited', \DB::raw(1));
            })
            ->join('employee_demo_tree', 'employee_demo_tree.id', 'goal_bank_orgs.orgid')
            ->leftjoin('users as u2', 'u2.id', 'goals.created_by')
            ->leftjoin('goal_types', 'goal_types.id', 'goals.goal_type_id')   
            ->whereIn('goals.by_admin', [1, 2])
            ->where('goals.is_library', true)
            ->whereNull('goals.deleted_at')        
            ->where(function ($where) use ($authId) {
                return $where->whereRaw("
                        (
                            EXISTS (SELECT DISTINCT 1 FROM user_demo_jr_view ud0 WHERE ud0.user_id = {$authId} AND employee_demo_tree.level = 0 AND ud0.organization_key = employee_demo_tree.organization_key)
                            OR EXISTS (SELECT DISTINCT 1 FROM user_demo_jr_view ud1 WHERE ud1.user_id = {$authId} AND employee_demo_tree.level = 1 AND ud1.organization_key = employee_demo_tree.organization_key AND ud1.level1_key = employee_demo_tree.level1_key)
                            OR EXISTS (SELECT DISTINCT 1 FROM user_demo_jr_view ud2 WHERE ud2.user_id = {$authId} AND employee_demo_tree.level = 2 AND ud2.organization_key = employee_demo_tree.organization_key AND ud2.level1_key = employee_demo_tree.level1_key AND ud2.level2_key = employee_demo_tree.level2_key)
                            OR EXISTS (SELECT DISTINCT 1 FROM user_demo_jr_view ud3 WHERE ud3.user_id = {$authId} AND employee_demo_tree.level = 3 AND ud3.organization_key = employee_demo_tree.organization_key AND ud3.level1_key = employee_demo_tree.level1_key AND ud3.level2_key = employee_demo_tree.level2_key AND ud3.level3_key = employee_demo_tree.level3_key)
                            OR EXISTS (SELECT DISTINCT 1 FROM user_demo_jr_view ud4 WHERE ud4.user_id = {$authId} AND employee_demo_tree.level = 4 AND ud4.organization_key = employee_demo_tree.organization_key AND ud4.level1_key = employee_demo_tree.level1_key AND ud4.level2_key = employee_demo_tree.level2_key AND ud4.level3_key = employee_demo_tree.level3_key AND ud4.level4_key = employee_demo_tree.level4_key)
                        )
                    ");
            })
            ->leftjoin('audits', function($joinon){
                return $joinon->on('audits.auditable_id', 'goals.id')
                ->whereNotExists(function($ne){
                    return $ne->select(DB::raw(1))
                    ->from('audits as audits2')
                    ->where('audits2.auditable_type', 'audits.auditable_type')
                    ->where('audits2.auditable_id', 'audits.auditable_id')
                    ->where('audits2.id', '>', 'audits.id');
                });
            })
            ->where('audits.auditable_type', 'like', '%App\\\\Models\\\\Goal%%')  
        ->groupBy('goals.id', 'goals.title', 'goals.goal_type_id', 'goals.created_at', 'goals.user_id', 'u2.id', 'u2.name', 'goals.is_mandatory');
        
        // Admin List filter below
        if ($filter->has('goal_bank_mandatory') && $filter->goal_bank_mandatory !== null) {
            if ($filter->goal_bank_mandatory == "1") {
                $adminGoalsInherited = $adminGoalsInherited->where('is_mandatory', $filter->goal_bank_mandatory);
            }
            else {
                $adminGoalsInherited = $adminGoalsInherited->where(function ($adminGoals1) {
                    $adminGoals1->whereNull('is_mandatory');
                    $adminGoals1->orWhere('is_mandatory', 0);
                });
            }
        }
        if ($filter->has('goal_bank_types') && $filter->goal_bank_types) {
            $adminGoalsInherited = $adminGoalsInherited->whereHas('goalType', function($adminGoals1) use ($filter) {
                return $adminGoals1->where('goal_type_id', $filter->goal_bank_types);
            });
        }
        if ($filter->has('goal_bank_tags') && $filter->goal_bank_tags) {
            $adminGoalsInherited = $adminGoalsInherited->whereRaw("EXISTS (SELECT 1 FROM goal_tags WHERE goal_tags.goal_id = goals.id AND goal_tags.tag_id = '{$filter->goal_bank_tags}')");
        }
        if ($filter->has('goal_bank_title') && $filter->goal_bank_title) {
            $adminGoalsInherited = $adminGoalsInherited->where('goals.title', "LIKE", "%$filter->goal_bank_title%");
        }
        if ($filter->has('goal_bank_dateadd') && $filter->goal_bank_dateadd && Str::lower($filter->goal_bank_dateadd) !== 'any') {
            $dateadded = $filter->goal_bank_dateadd;
            $adminGoalsInherited = $adminGoalsInherited->whereDate('goals.created_at', '>=', $dateadded . " 00:00:00");
            $adminGoalsInherited = $adminGoalsInherited->whereDate('goals.created_at', '<=', $dateadded . " 23:59:59");
        }
        if ($filter->has('goal_bank_createdby') && $filter->goal_bank_createdby) {
            if(is_numeric($filter->goal_bank_createdby)) {
                $adminGoalsInherited = $adminGoalsInherited->where('created_by', $filter->goal_bank_createdby)->whereNull('display_name');
            } else {
                $adminGoalsInherited = $adminGoalsInherited->where('display_name', 'like',$filter->goal_bank_createdby);
            }
        }

        $query = Goal::withoutGlobalScope(NonLibraryScope::class)
        ->where('is_library', true)    
        ->whereNull('goals.deleted_at')        
        ->join('users', 'goals.user_id', '=', 'users.id')          
        ->leftjoin('users as u2', 'u2.id', '=', 'goals.created_by')
        ->leftjoin('goal_types', 'goal_types.id', '=', 'goals.goal_type_id')    
        ->leftjoin('goal_tags', 'goal_tags.goal_id', '=', 'goals.id')
        ->leftjoin('tags', 'tags.id', '=', 'goal_tags.tag_id')
        ->leftjoin('audits', function($joinon){
            return $joinon->on('audits.auditable_id', 'goals.id')
            ->whereNotExists(function($ne){
                return $ne->select(DB::raw(1))
                ->from('audits as audits2')
                ->where('audits2.auditable_type', 'audits.auditable_type')
                ->where('audits2.auditable_id', 'audits.auditable_id')
                ->where('audits2.id', '>', 'audits.id');
            });
        })
        ->where('audits.auditable_type', 'like', '%App\\\\Models\\\\Goal%%');  
        $query = $query->select('goals.id', 'goals.title', 'goals.goal_type_id', 'goals.created_at', 'goals.user_id', 'goals.is_mandatory','goals.display_name','goal_types.name as typename','u2.id as creator_id','u2.name as username', 'audits.created_at as last_mod_date', DB::raw('group_concat(distinct tags.name separator "<br/>") as tagnames'));
        
        if ($filter->has('goal_bank_mandatory') && $filter->goal_bank_mandatory !== null) {
            if ($filter->goal_bank_mandatory == "1") {
                $query = $query->where('is_mandatory', $filter->goal_bank_mandatory);
            }
            else {
                $query = $query->where(function ($query) {
                    $query->whereNull('is_mandatory');
                    $query->orWhere('is_mandatory', 0);
                });
            }
        }

        if ($filter->has('goal_bank_types') && $filter->goal_bank_types) {
            $query = $query->whereHas('goalType', function($query) use ($filter) {
                return $query->where('goal_type_id', $filter->goal_bank_types);
            });
        }
        
        if ($filter->has('goal_bank_tags') && $filter->goal_bank_tags) {
            $query = $query->where('goal_tags.tag_id', "=", "$filter->goal_bank_tags");
        }

        if ($filter->has('goal_bank_title') && $filter->goal_bank_title) {
            $query = $query->where('goals.title', "LIKE", "%$filter->goal_bank_title%");
        }

        if ($filter->has('goal_bank_dateadd') && $filter->goal_bank_dateadd && Str::lower($filter->goal_bank_dateadd) !== 'any') {
            $dateadded = $filter->goal_bank_dateadd;
            $query = $query->where('goals.created_at', '>=', $dateadded . " 00:00:00");
            $query = $query->where('goals.created_at', '<=', $dateadded . " 23:59:59");
        }

        if ($filter->has('goal_bank_createdby') && $filter->goal_bank_createdby) {
            if(is_numeric($filter->goal_bank_createdby)) {
                $query = $query->where('created_by', $filter->goal_bank_createdby)->whereNull('display_name');
            } else {
                $query = $query->where('display_name', 'like',$filter->goal_bank_createdby);
            }
        }

        $query->whereHas('sharedWith', function($query) {
            $query->where('user_id', Auth::id());
        });

        $query->groupBy('goals.id', 'goals.title', 'goals.goal_type_id', 'goals.created_at', 'goals.user_id', 'goals.is_mandatory');


        $query = $query->union($adminGoals)->union($adminGoalsInherited);
                
        $sortby = 'created_at';
        $sortorder = 'ASC';
        $query = $query->orderby($sortby, $sortorder);    
        
        $bankGoals = $query->get();


        //get hidden goals ids
        $hiddenGoals = DB::table('goal_hide')
            ->select('goal_id')      
            ->where('user_id', Auth::id())
            ->get();
        $hiddenGoal_arr = array();
        foreach($hiddenGoals as $item){
            $hiddenGoal_arr[] = $item->goal_id;
        }    
        foreach($bankGoals as $i=>$bankGoal){
            if(in_array($bankGoal->id, $hiddenGoal_arr)){
                unset($bankGoals[$i]);
            }
        }      

        return $bankGoals;

    }

    private function gethiddenGoals($filter){
        $authId = Auth::id();
        $user = User::find($authId);

        $adminGoals = Goal::withoutGlobalScopes()
            ->select('goals.id', 'goals.title', 'goals.goal_type_id', 'goals.created_at', 'goals.user_id', 'goals.is_mandatory', 'goals.display_name', 'goal_types.name as typename', 'u2.id as creator_id', 'u2.name as username', DB::raw("(SELECT group_concat(distinct tags.name separator '<br/>') FROM goal_tags LEFT JOIN tags ON tags.id = goal_tags.tag_id WHERE goal_tags.goal_id = goals.id) as tagnames"))
            ->join('goal_bank_orgs', function ($qon) {
                return $qon->on('goal_bank_orgs.goal_id', 'goals.id')
                    ->on('goal_bank_orgs.version', \DB::raw(2))
                    ->on('goal_bank_orgs.inherited', \DB::raw(0));
            })
            ->join('employee_demo', 'employee_demo.orgid', 'goal_bank_orgs.orgid')
            ->join('users', function ($qon) use ($authId) {
                return $qon->on('users.employee_id', 'employee_demo.employee_id')
                    ->on('users.id', \DB::raw($authId));
            })
            ->leftjoin('users as u2', 'u2.id', 'goals.created_by')
            ->leftjoin('goal_types', 'goal_types.id', 'goals.goal_type_id')   
            ->whereIn('goals.by_admin', [1, 2])
            ->where('goals.is_library', true)
            ->whereNull('goals.deleted_at')        
            ->groupBy('goals.id', 'goals.title', 'goals.goal_type_id', 'goals.created_at', 'goals.user_id', 'u2.id', 'u2.name', 'goals.is_mandatory');
              

        // Admin List filter for hidden items below
        if ($filter->has('goal_bank_mandatory_hidden') && $filter->goal_bank_mandatory_hidden !== null) {
            if ($filter->goal_bank_mandatory_hidden == "1") {
                $adminGoals = $adminGoals->where('is_mandatory', $filter->goal_bank_mandatory_hidden);
            }
            else {
                $adminGoals = $adminGoals->where(function ($adminGoals1) {
                    $adminGoals1->whereNull('is_mandatory');
                    $adminGoals1->orWhere('is_mandatory', 0);
                });
            }
        }
        if ($filter->has('goal_bank_types_hidden') && $filter->goal_bank_types_hidden) {
            $adminGoals = $adminGoals->whereHas('goalType', function($adminGoals1) use ($filter) {
                return $adminGoals1->where('goal_type_id', $filter->goal_bank_types_hidden);
            });
        }
        if ($filter->has('goal_bank_tags_hidden') && $filter->goal_bank_tags_hidden) {
            $adminGoals = $adminGoals->whereRaw("EXISTS (SELECT 1 FROM goal_tags WHERE goal_tags.goal_id = goals.id AND goal_tags.tag_id = '{$filter->goal_bank_tags_hidden}')");
        }
        if ($filter->has('goal_bank_title_hidden') && $filter->goal_bank_title_hidden) {
            $adminGoals = $adminGoals->where('goals.title', "LIKE", "%$filter->goal_bank_title_hidden%");
        }
        if ($filter->has('goal_bank_dateadd_hidden') && $filter->goal_bank_dateadd_hidden && Str::lower($filter->goal_bank_dateadd_hidden) !== 'any') {
            $dateadded_hidden = $filter->goal_bank_dateadd_hidden;
            $adminGoals = $adminGoals->whereDate('goals.created_at', '>=', $dateadded_hidden . " 00:00:00");
            $adminGoals = $adminGoals->whereDate('goals.created_at', '<=', $dateadded_hidden . " 23:59:59");
        }
        if ($filter->has('goal_bank_createdby_hidden') && $filter->goal_bank_createdby_hidden) {
            if(is_numeric($filter->goal_bank_createdby_hidden)) {
                $adminGoals = $adminGoals->where('created_by', $filter->goal_bank_createdby_hidden)->whereNull('display_name');
            } else {
                $adminGoals = $adminGoals->where('display_name', 'like',$filter->goal_bank_createdby_hidden);
            }
        }

        $adminGoalsInherited = Goal::withoutGlobalScopes()
            ->select('goals.id', 'goals.title', 'goals.goal_type_id', 'goals.created_at', 'goals.user_id', 'goals.is_mandatory', 'goals.display_name', 'goal_types.name as typename', 'u2.id as creator_id', 'u2.name as username', DB::raw("(SELECT group_concat(distinct tags.name separator '<br/>') FROM goal_tags LEFT JOIN tags ON tags.id = goal_tags.tag_id WHERE goal_tags.goal_id = goals.id) as tagnames"))
            ->join('goal_bank_orgs', function ($qon) {
                return $qon->on('goal_bank_orgs.goal_id', 'goals.id')
                    ->on('goal_bank_orgs.version', \DB::raw(2))
                    ->on('goal_bank_orgs.inherited', \DB::raw(1));
            })
            ->join('employee_demo_tree', 'employee_demo_tree.id', 'goal_bank_orgs.orgid')
            ->leftjoin('users as u2', 'u2.id', 'goals.created_by')
            ->leftjoin('goal_types', 'goal_types.id', 'goals.goal_type_id')   
            ->whereIn('goals.by_admin', [1, 2])
            ->where('goals.is_library', true)
            ->whereNull('goals.deleted_at')        
            ->where(function ($where) use ($authId) {
                return $where->whereRaw("
                        (
                            EXISTS (SELECT DISTINCT 1 FROM user_demo_jr_view ud0 WHERE ud0.user_id = {$authId} AND employee_demo_tree.level = 0 AND ud0.organization_key = employee_demo_tree.organization_key)
                            OR EXISTS (SELECT DISTINCT 1 FROM user_demo_jr_view ud1 WHERE ud1.user_id = {$authId} AND employee_demo_tree.level = 1 AND ud1.organization_key = employee_demo_tree.organization_key AND ud1.level1_key = employee_demo_tree.level1_key)
                            OR EXISTS (SELECT DISTINCT 1 FROM user_demo_jr_view ud2 WHERE ud2.user_id = {$authId} AND employee_demo_tree.level = 2 AND ud2.organization_key = employee_demo_tree.organization_key AND ud2.level1_key = employee_demo_tree.level1_key AND ud2.level2_key = employee_demo_tree.level2_key)
                            OR EXISTS (SELECT DISTINCT 1 FROM user_demo_jr_view ud3 WHERE ud3.user_id = {$authId} AND employee_demo_tree.level = 3 AND ud3.organization_key = employee_demo_tree.organization_key AND ud3.level1_key = employee_demo_tree.level1_key AND ud3.level2_key = employee_demo_tree.level2_key AND ud3.level3_key = employee_demo_tree.level3_key)
                            OR EXISTS (SELECT DISTINCT 1 FROM user_demo_jr_view ud4 WHERE ud4.user_id = {$authId} AND employee_demo_tree.level = 4 AND ud4.organization_key = employee_demo_tree.organization_key AND ud4.level1_key = employee_demo_tree.level1_key AND ud4.level2_key = employee_demo_tree.level2_key AND ud4.level3_key = employee_demo_tree.level3_key AND ud4.level4_key = employee_demo_tree.level4_key)
                        )
                    ");
            })
        ->groupBy('goals.id', 'goals.title', 'goals.goal_type_id', 'goals.created_at', 'goals.user_id', 'u2.id', 'u2.name', 'goals.is_mandatory');
        

        // Admin List hidden filter below
        if ($filter->has('goal_bank_mandatory_hidden') && $filter->goal_bank_mandatory_hidden !== null) {
            if ($filter->goal_bank_mandatory_hidden == "1") {
                $adminGoalsInherited = $adminGoalsInherited->where('is_mandatory', $filter->goal_bank_mandatory_hidden);
            }
            else {
                $adminGoalsInherited = $adminGoalsInherited->where(function ($adminGoals1) {
                    $adminGoals1->whereNull('is_mandatory');
                    $adminGoals1->orWhere('is_mandatory', 0);
                });
            }
        }
        if ($filter->has('goal_bank_types_hidden') && $filter->goal_bank_types_hidden) {
            $adminGoalsInherited = $adminGoalsInherited->whereHas('goalType', function($adminGoals1) use ($filter) {
                return $adminGoals1->where('goal_type_id', $filter->goal_bank_types_hidden);
            });
        }
        if ($filter->has('goal_bank_tags_hidden') && $filter->goal_bank_tags_hidden) {
            $adminGoalsInherited = $adminGoalsInherited->whereRaw("EXISTS (SELECT 1 FROM goal_tags WHERE goal_tags.goal_id = goals.id AND goal_tags.tag_id = '{$filter->goal_bank_tags_hidden}')");
        }
        if ($filter->has('goal_bank_title_hidden') && $filter->goal_bank_title_hidden) {
            $adminGoalsInherited = $adminGoalsInherited->where('goals.title', "LIKE", "%$filter->goal_bank_title_hidden%");
        }
        if ($filter->has('goal_bank_dateadd_hidden') && $filter->goal_bank_dateadd_hidden && Str::lower($filter->goal_bank_dateadd_hidden) !== 'any') {
            $dateadded_hidden = $filter->goal_bank_dateadd_hidden;
            $adminGoalsInherited = $adminGoalsInherited->whereDate('goals.created_at', '>=', $dateadded_hidden . " 00:00:00");
            $adminGoalsInherited = $adminGoalsInherited->whereDate('goals.created_at', '<=', $dateadded_hidden . " 23:59:59");
        }
        if ($filter->has('goal_bank_createdby_hidden') && $filter->goal_bank_createdby_hidden) {
            if(is_numeric($filter->goal_bank_createdby_hidden)) {
                $adminGoalsInherited = $adminGoalsInherited->where('created_by', $filter->goal_bank_createdby_hidden)->whereNull('display_name');
            } else {
                $adminGoalsInherited = $adminGoalsInherited->where('display_name', 'like',$filter->goal_bank_createdby_hidden);
            }
        }

        $query = Goal::withoutGlobalScope(NonLibraryScope::class)
        ->where('is_library', true)    
        ->whereNull('goals.deleted_at')        
        ->join('users', 'goals.user_id', '=', 'users.id')          
        ->leftjoin('users as u2', 'u2.id', '=', 'goals.created_by')
        ->leftjoin('goal_types', 'goal_types.id', '=', 'goals.goal_type_id')    
        ->leftjoin('goal_tags', 'goal_tags.goal_id', '=', 'goals.id')
        ->leftjoin('tags', 'tags.id', '=', 'goal_tags.tag_id');  
        $query = $query->select('goals.id', 'goals.title', 'goals.goal_type_id', 'goals.created_at', 'goals.user_id', 'goals.is_mandatory','goals.display_name','goal_types.name as typename','u2.id as creator_id','u2.name as username',DB::raw('group_concat(distinct tags.name separator "<br/>") as tagnames'));
        
        //hidden items
        if ($filter->has('goal_bank_mandatory_hidden') && $filter->goal_bank_mandatory_hidden !== null) {
            if ($filter->goal_bank_mandatory_hidden == "1") {
                $query = $query->where('is_mandatory', $filter->goal_bank_mandatory_hidden);
            }
            else {
                $query = $query->where(function ($query) {
                    $query->whereNull('is_mandatory');
                    $query->orWhere('is_mandatory', 0);
                });
            }
        }

        if ($filter->has('goal_bank_types_hidden') && $filter->goal_bank_types_hidden) {
            $query = $query->whereHas('goalType', function($query) use ($filter) {
                return $query->where('goal_type_id', $filter->goal_bank_types_hidden);
            });
        }
        
        if ($filter->has('goal_bank_tags_hidden') && $filter->goal_bank_tags_hidden) {
            $query = $query->where('goal_tags.tag_id', "=", "$filter->goal_bank_tags_hidden");
        }

        if ($filter->has('goal_bank_title_hidden') && $filter->goal_bank_title_hidden) {
            $query = $query->where('goals.title', "LIKE", "%$filter->goal_bank_title_hidden%");
        }

        if ($filter->has('goal_bank_dateadd_hidden') && $filter->goal_bank_dateadd_hidden && Str::lower($filter->goal_bank_dateadd_hidden) !== 'any') {
            $dateadded_hidden = $filter->goal_bank_dateadd_hidden;
            $query = $query->where('goals.created_at', '>=', $dateadded_hidden . " 00:00:00");
            $query = $query->where('goals.created_at', '<=', $dateadded_hidden . " 23:59:59");
        }

        if ($filter->has('goal_bank_createdby_hidden') && $filter->goal_bank_createdby_hidden) {
            if(is_numeric($filter->goal_bank_createdby_hidden)) {
                $query = $query->where('created_by', $filter->goal_bank_createdby_hidden)->whereNull('display_name');
            } else {
                $query = $query->where('display_name', 'like',$filter->goal_bank_createdby_hidden);
            }
        }


        $query->whereHas('sharedWith', function($query) {
            $query->where('user_id', Auth::id());
        });
        $query->groupBy('goals.id', 'goals.title', 'goals.goal_type_id', 'goals.created_at', 'goals.user_id', 'goals.is_mandatory');


        $query = $query->union($adminGoals)->union($adminGoalsInherited);
                
        $sortby = 'created_at';
        $sortorder = 'ASC';
        $query = $query->orderby($sortby, $sortorder);    
        
        $bankGoals = $query->get();

        $selectedGoals = array();

        //get hidden goals ids
        $hiddenGoals = DB::table('goal_hide')
            ->select('goal_id')      
            ->where('user_id', Auth::id())
            ->get();
        $hiddenGoal_arr = array();
        foreach($hiddenGoals as $item){
            $hiddenGoal_arr[] = $item->goal_id;
        }    
        foreach($bankGoals as $i=>$bankGoal){
            if(in_array($bankGoal->id, $hiddenGoal_arr)){
                array_push($selectedGoals, $bankGoals[$i]);
            }
        }     

        return $selectedGoals;
    }    


    public function goalBank(Request $request) {      
        
        $authId = Auth::id();
        $user = User::find($authId);
        $tags = Tag::all()->sortBy("name")->toArray();
        $tags_input = $request->tag_ids;
        $goaltypes = GoalType::where('name', '!=', 'private')->get()->toArray();

        
        
        $this->getDropdownValues($mandatoryOrSuggested, $createdBy, $goaltypes, $tagsList);
        $i = 0;
        $c = 0;

        $json_goalbanks = "";
        $bankGoals_arr = array();
        $json_goalbanks_hidden = "";        
        $hidden_goals = array();

        $bankGoals = $this->getNohiddenGoals($request);
        $bankGoals_hide = $this->gethiddenGoals($request);

        foreach($bankGoals as $item){
                $bankGoals_arr[$i]['id'] = $item->id;
                $bankGoals_arr[$i]['title'] = $item->title;
                $bankGoals_arr[$i]['goal_type_id'] = $item->goal_type_id;
                
                $date = Carbon::parse($item->created_at); // Parse the date string into a Carbon instance
                $formattedDate = $date->format('Y-m-d');
                $bankGoals_arr[$i]['created_at'] = $formattedDate;
                
                $date2 = Carbon::parse($item->last_mod_date); // Parse the date string into a Carbon instance
                $formattedModDate = $date2->format('Y-m-d');
                $bankGoals_arr[$i]['last_mod_date'] = $date2 ? $formattedModDate : null;
                
                $bankGoals_arr[$i]['user_id'] = $item->user_id;
                if($item->is_mandatory == 1){
                    $bankGoals_arr[$i]['is_mandatory'] = 'Mandatory';
                }else{
                    $bankGoals_arr[$i]['is_mandatory'] = 'Suggested';
                }
                $bankGoals_arr[$i]['display_name'] = $item->display_name;            
                
                $bankGoals_arr[$i]['typename'] = $item->typename;
                $bankGoals_arr[$i]['username'] = $item->username;
                $bankGoals_arr[$i]['tagnames'] = $item->tagnames;
                $i++;
        }
        foreach($bankGoals_hide as $item){
            $hidden_goals[$c]['id'] = $item->id;
            $hidden_goals[$c]['title'] = $item->title;
            $hidden_goals[$c]['goal_type_id'] = $item->goal_type_id;
            
            $date = Carbon::parse($item->created_at); // Parse the date string into a Carbon instance
            $formattedDate = $date->format('Y-m-d');
            $hidden_goals[$c]['created_at'] = $formattedDate;
            
            $hidden_goals[$c]['user_id'] = $item->user_id;
            if($item->is_mandatory == 1){
                $hidden_goals[$c]['is_mandatory'] = 'Mandatory';
            }else{
                $hidden_goals[$c]['is_mandatory'] = 'Suggested';
            }
            $hidden_goals[$c]['display_name'] = $item->display_name;            
            
            $hidden_goals[$c]['typename'] = $item->typename;
            $hidden_goals[$c]['username'] = $item->username;
            $hidden_goals[$c]['tagnames'] = $item->tagnames;
            $c++;
        }

        $json_goalbanks = json_encode($bankGoals_arr);   
        $json_goalbanks_hidden = json_encode($hidden_goals);   

        $sortby = 'created_at';
        $sortorder = 'ASC';
                        
        $all_adminGoals = Goal::withoutGlobalScopes()
            ->select('u2.id as creator_id', 'u2.name as username', 'goals.display_name')
            ->join('goal_bank_orgs', function ($qon) {
                return $qon->on('goal_bank_orgs.goal_id', 'goals.id')
                    ->on('goal_bank_orgs.version', \DB::raw(2))
                    ->on('goal_bank_orgs.inherited', \DB::raw(0));
            })
            ->join('employee_demo', 'employee_demo.orgid', 'goal_bank_orgs.orgid')
            ->join('users', function ($qon) use ($authId) {
                return $qon->on('users.employee_id', 'employee_demo.employee_id')
                    ->on('users.id', \DB::raw($authId));
            })
            ->leftjoin('users as u2', 'u2.id', 'goals.created_by')
            ->leftjoin('goal_types', 'goal_types.id', 'goals.goal_type_id')   
            ->whereIn('goals.by_admin', [1, 2])
            ->where('goals.is_library', true)
            ->whereNull('goals.deleted_at')           
            ->whereRaw('employee_demo.pdp_excluded = 0')       
            ->groupBy('u2.id', 'u2.name', 'goals.display_name');
        
        $all_adminGoalsInherited = Goal::withoutGlobalScopes()
            ->select('u2.id as creator_id', 'u2.name as username', 'goals.display_name')
            ->join('goal_bank_orgs', function ($qon) {
                return $qon->on('goal_bank_orgs.goal_id', 'goals.id')
                    ->on('goal_bank_orgs.version', \DB::raw(2))
                    ->on('goal_bank_orgs.inherited', \DB::raw(1));
            })
            ->join('employee_demo_tree', 'employee_demo_tree.id', 'goal_bank_orgs.orgid')
            ->leftjoin('users as u2', 'u2.id', 'goals.created_by')
            ->leftjoin('goal_types', 'goal_types.id', 'goals.goal_type_id')   
            ->whereIn('goals.by_admin', [1, 2])
            ->where('goals.is_library', true)
            ->whereNull('goals.deleted_at')        
            ->where(function ($where) use ($authId) {
                return $where->whereRaw("
                        (
                            EXISTS (SELECT DISTINCT 1 FROM user_demo_jr_view ud0 WHERE ud0.user_id = {$authId} AND employee_demo_tree.level = 0 AND ud0.organization_key = employee_demo_tree.organization_key)
                            OR EXISTS (SELECT DISTINCT 1 FROM user_demo_jr_view ud1 WHERE ud1.user_id = {$authId} AND employee_demo_tree.level = 1 AND ud1.organization_key = employee_demo_tree.organization_key AND ud1.level1_key = employee_demo_tree.level1_key)
                            OR EXISTS (SELECT DISTINCT 1 FROM user_demo_jr_view ud2 WHERE ud2.user_id = {$authId} AND employee_demo_tree.level = 2 AND ud2.organization_key = employee_demo_tree.organization_key AND ud2.level1_key = employee_demo_tree.level1_key AND ud2.level2_key = employee_demo_tree.level2_key)
                            OR EXISTS (SELECT DISTINCT 1 FROM user_demo_jr_view ud3 WHERE ud3.user_id = {$authId} AND employee_demo_tree.level = 3 AND ud3.organization_key = employee_demo_tree.organization_key AND ud3.level1_key = employee_demo_tree.level1_key AND ud3.level2_key = employee_demo_tree.level2_key AND ud3.level3_key = employee_demo_tree.level3_key)
                            OR EXISTS (SELECT DISTINCT 1 FROM user_demo_jr_view ud4 WHERE ud4.user_id = {$authId} AND employee_demo_tree.level = 4 AND ud4.organization_key = employee_demo_tree.organization_key AND ud4.level1_key = employee_demo_tree.level1_key AND ud4.level2_key = employee_demo_tree.level2_key AND ud4.level3_key = employee_demo_tree.level3_key AND ud4.level4_key = employee_demo_tree.level4_key)
                        )
                    ");
            })
        ->groupBy('u2.id', 'u2.name', 'goals.display_name');
        
        $all_bankquery = Goal::withoutGlobalScope(NonLibraryScope::class)
        ->select('u2.id as creator_id', 'u2.name as username', 'goals.display_name')        
        ->where('is_library', true)
        ->whereNull('goals.deleted_at')        
        ->join('users', 'goals.user_id', '=', 'users.id')          
        ->leftjoin('users as u2', 'u2.id', '=', 'goals.created_by')
        ->leftjoin('goal_types', 'goal_types.id', '=', 'goals.goal_type_id')    
        ->leftjoin('goal_tags', 'goal_tags.goal_id', '=', 'goals.id')
        ->leftjoin('tags', 'tags.id', '=', 'goal_tags.tag_id');
        $all_bankquery->whereHas('sharedWith', function($query) {
            $query->where('user_id', Auth::id());
        })
        ->groupBy('u2.id', 'u2.name', 'goals.display_name');        
        
        $all_bankquery = $all_bankquery->union($all_adminGoals)->union($all_adminGoalsInherited);
        
        $i = 0;
        $goalCreatedBy = array();
        $all_bankGoals = $all_bankquery->get();
        foreach($all_bankGoals as $item){
            if($item->display_name != '' ){
                $goalCreatedBy[$i]['id'] = $item->display_name; 
                $goalCreatedBy[$i]['name'] = $item->display_name;
            } else {
                $goalCreatedBy[$i]['id'] = $item->creator_id;
                $goalCreatedBy[$i]['name'] = $item->username; 
            }
            
            $i++;
        }   
        foreach($goalCreatedBy as $a=>$t){
            if($t["name"] == ''){
                unset($goalCreatedBy[$a]);
            }
        }

        usort($goalCreatedBy, function ($a, $b) {
            return strcmp($a["name"], $b["name"]);
        });

        $createdBy = collect($goalCreatedBy)->unique('name')->values()->all();
        array_unshift($createdBy , [
            "id" => "0",
            "name" => "Any"
        ]);

        $suggestedGoalsData = DB::table('goals')
            ->select('goals.id', 'goals.title', 'goals.goal_type_id', 'goals.created_at', 'goals.user_id'
                    , 'goals.is_mandatory','goals.display_name','goal_types.name as typename','u2.name as username'
                    ,DB::raw('group_concat(distinct tags.name separator "<br/>") as tagnames')
                    ,DB::raw('group_concat(distinct goals_shared_with.user_id separator ",") as shared_user_id')
                    ,DB::raw('group_concat(distinct shared_users.name separator ",") as shared_user_name'))         
            ->leftJoin('users as u2', 'u2.id', '=', 'goals.created_by')
            ->leftJoin('goal_tags', 'goal_tags.goal_id', '=', 'goals.id')
            ->leftJoin('tags', 'tags.id', '=', 'goal_tags.tag_id')
            ->leftJoin('goal_types', 'goal_types.id', 'goals.goal_type_id')
            ->leftJoin('goals_shared_with', 'goals_shared_with.goal_id', 'goals.id')
            ->leftJoin('users as shared_users', 'shared_users.id', 'goals_shared_with.user_id')    
            ->where('status', 'active')
            ->where('is_library', 1)
            ->where('by_admin', '=', 0)
            ->where('goals.user_id', Auth::id()) 
            ->whereNull('goals.deleted_at');   
                
            if ($request->has('is_mandatory') && $request->is_mandatory !== null) {
                if ($request->is_mandatory == "1") {
                    $suggestedGoalsData = $suggestedGoalsData->where('is_mandatory', $request->is_mandatory);
                }
                else {
                    $suggestedGoalsData = $suggestedGoalsData->where(function ($query) {
                        $query->whereNull('is_mandatory');
                        $query->orWhere('is_mandatory', 0);
                    });
                }
            }

            if ($request->has('goal_type') && $request->goal_type) {
                $suggestedGoalsData = $suggestedGoalsData->where('goals.goal_type_id', "=", "$request->goal_type");
            }

            if ($request->has('tag_id') && $request->tag_id) {
                $suggestedGoalsData = $suggestedGoalsData->where('goal_tags.tag_id', "=", "$request->tag_id");
            }

            if ($request->has('title') && $request->title) {
                $suggestedGoalsData = $suggestedGoalsData->where('goals.title', "LIKE", "%$request->title%");
            }

            if ($request->has('date_added') && $request->date_added && Str::lower($request->date_added) !== 'any') {
                $dateadded = $request->date_added;
                $suggestedGoalsData = $suggestedGoalsData->where('goals.created_at', '>=', $dateadded . " 00:00:00");
                $suggestedGoalsData = $suggestedGoalsData->where('goals.created_at', '<=', $dateadded . " 23:59:59");
            }

            if ($request->has('created_by') && $request->created_by) {
                $suggestedGoalsData = $suggestedGoalsData->where('created_by', $request->created_by);
            }    
                
            $suggestedGoalsData = $suggestedGoalsData->groupBy('goals.id')
            ->orderBy('goals.id', 'desc')    
            ->get(); 
        $team_bankGoals_arr = array();
        
        $i = 0;
        foreach($suggestedGoalsData as $item){
                $team_bankGoals_arr[$i]['id'] = $item->id;
                $team_bankGoals_arr[$i]['title'] = $item->title;
                $team_bankGoals_arr[$i]['goal_type_id'] = $item->goal_type_id;
                
                $date = Carbon::parse($item->created_at); // Parse the date string into a Carbon instance
                $formattedDate = $date->format('Y-m-d');
                $team_bankGoals_arr[$i]['created_at'] = $formattedDate;
                
                $team_bankGoals_arr[$i]['user_id'] = $item->user_id;
                
                if($item->is_mandatory == 1){
                    $team_bankGoals_arr[$i]['is_mandatory'] = 'Mandatory';
                }else{
                    $team_bankGoals_arr[$i]['is_mandatory'] = 'Suggested';
                }
                $team_bankGoals_arr[$i]['display_name'] = $item->display_name;
                $team_bankGoals_arr[$i]['typename'] = $item->typename;
                $team_bankGoals_arr[$i]['username'] = $item->username;
                $team_bankGoals_arr[$i]['tagnames'] = $item->tagnames;
                $team_bankGoals_arr[$i]['shared_user_id'] = $item->shared_user_id;
                $team_bankGoals_arr[$i]['shared_user_name'] = $item->shared_user_name;
                $i++;
        }

        $json_team_goalbanks = json_encode($team_bankGoals_arr);
        
        $type_desc_arr = array();
        foreach($goaltypes as $goalType) {
            if(isset($goalType['description']) && isset($goalType['name'])) {                
                $item = "<b>" . $goalType['name'] . " Goals</b> ". str_replace($goalType['name'] . " Goals","",$goalType['description']);
                array_push($type_desc_arr, $item);
            }
        }
        
        $type_desc_str = implode('<br/><br/>',$type_desc_arr);
        $goals_count = count($bankGoals);
        
        $request->session()->put('is_bank', true);

        // this is redirect from DashboardController with the related id, then open modal box
        $open_modal_id = (session('open_modal_id'));
        
        $from = 'bank';
        $employees = $this->myEmployeesAjax();

        $adminShared=SharedProfile::select('shared_id')
        ->where('shared_with', '=', Auth::id())
        ->where(function ($sh) {
            $sh->where('shared_item', 'like', '%1%')
            ->orWhere('shared_item', 'like', '%2%');
        })
        ->pluck('shared_id');
        $adminemps = User::select('users.*')
        ->whereIn('users.id', $adminShared)->get();
        $employees = $employees->merge($adminemps);
        
        $self = User::select('users.*')
                    ->where('users.id', Auth::id())->get();
        $employees = $employees->merge($self);
        
        $employees_list = array();
        $i = 0;
        if(count($employees)>0) {
            foreach ($employees as $employee) {
                $employees_list[$i]["id"] = $employee->id;
                $employees_list[$i]["name"] = $employee->name;
                $i++;
            }
        }
        usort($employees_list, function($a, $b) {
            return strcmp($a["name"], $b["name"]);
        });
        
        $shared_employees = DB::table('shared_profiles')
                    ->select('shared_profiles.shared_id', 'users.name')
                    ->join('users', 'users.id', '=', 'shared_profiles.shared_id')
                    ->where('shared_profiles.shared_with', Auth::id())
                    ->where('shared_profiles.shared_item', 'like', '%1%')
                    ->get();

        $goaltypes = GoalType::where('name', '!=', 'private')->get()->toArray();

        $goaltypes_filter = $goaltypes;
        array_unshift($goaltypes_filter, [
            "id" => "0",
            "name" => "Any"
        ]);

        return view('goal.bank', compact('bankGoals', 'tags', 'user', 'tagsList', 'goaltypes',  'type_desc_str', 'mandatoryOrSuggested', 'createdBy', 'goals_count', 'sortby','sortorder',
                                'open_modal_id','from','shared_employees', 'json_goalbanks','json_team_goalbanks', 'json_goalbanks_hidden', 'employees_list', 'goaltypes_filter'));
    }
    
    public function myEmployeesAjax() {
        return User::find(Auth::id())->avaliableReportees()->get();
    }

    private function getDropdownValues(&$mandatoryOrSuggested, &$createdBy, &$goalTypes, &$tagsList) {
        $mandatoryOrSuggested = [
            [
                "id" => '',
                "name" => 'Any'
            ],
            [
                "id" => '1',
                "name" => 'Mandatory'
            ],
            [
                "id" => '0',
                "name" => 'Suggested'
            ]
        ];
        $createdBy = Goal::withoutGlobalScope(NonLibraryScope::class)
            ->where('is_library', true)
            ->with('user')
            ->where('user_id', Auth::id())    
            ->whereNull('display_name')
            ->whereNull('deleted_at')        
            ->groupBy('user_id')
            ->get()
            ->pluck('user')
            ->toArray();
       
        $display_names = DB::table('goals')
                    ->select('display_name')
                    ->Join('goals_shared_with', 'goals_shared_with.goal_id', 'goals.id')
                    ->where('goals_shared_with.user_id', Auth::id())
                    ->whereNull('deleted_at')
                    ->distinct()
                    ->pluck('display_name')
                    ->toArray();
        $display_names_by_self = DB::table('goals')
                    ->select('display_name')
                    ->where('user_id', Auth::id())
                    ->whereNull('deleted_at')
                    ->distinct()
                    ->pluck('display_name')
                    ->toArray();
        if(count($display_names_by_self)>0){
            foreach($display_names_by_self as $name){
                if($name != "" && !in_array($name, $display_names)){
                    array_push($display_names, $name);
                }
            }
        }
        
        $i = count($createdBy) + 1;
        foreach($display_names as $display_name){
            if($display_name != ''){
                $createdBy[$i]['id'] = $display_name;
                $createdBy[$i]['name'] = $display_name;
            }
            $i++;
        }
        usort($createdBy, function($a, $b) {
            return strcmp($a["name"], $b["name"]);
        });
        
        array_unshift($createdBy , [
            "id" => "0",
            "name" => "Any"
        ]);

        $goalTypes = GoalType::all()->toArray();        
        array_unshift($goalTypes, [
            "id" => "0",
            "name" => "Any"
        ]);
        
        $tagsList = Tag::all()->sortBy("name")->toArray();
        array_unshift($tagsList, [
            "id" => "0",
            "name" => "Any"
        ]);
    }

    public function library(Request $request)
    {
        $authId = Auth::id();
        $user = User::find($authId);
        
        $query = Goal::whereIn('id', [997, 998, 999]);
        $expanded = false;
        $currentSearch = "";
        if($request->has('search') && $request->search != '') {
            $searchText = $request->search;
            $query->Where(function ($qq) use ($searchText) {
                foreach ($searchText as $search) {
                    $qq->orWhere(function ($q) use ($search) {
                        $q->orWhere('title', 'LIKE', '%' . $search . '%');
                        $q->orWhere('what', 'LIKE', '%' . $search . '%');
                        $q->orWhere('measure_of_success', 'LIKE', '%' . $search . '%');
                    });
                }
            });

            $expanded = true;
            $currentSearch = implode(' ',$request->search);
        }
        $sQuery = clone $query;

        $organizationGoals = $query->whereIn('id', [997, 999])->with('goalType')
        ->with('comments')->get();

        $user = Auth::user();
        $sQuery = Goal::withoutGlobalScope(NonLibraryScope::class)->where('user_id', $user->reportingManager->id);

        // TODO: For User Experience
        // $sQuery = Goal::where('id', 998);
        // TODO: remove duplicate if once we resolve organizational goals
        if ($request->has('search') && $request->search != '') {
            // $searchText = explode(' ', $request->search);
            $searchText = $request->search;
            $sQuery->Where(function ($qq) use ($searchText) {
                foreach ($searchText as $search) {
                    $qq->orWhere(function ($q) use ($search) {
                        $q->orWhere('title', 'LIKE', '%' . $search . '%');
                        $q->orWhere('what', 'LIKE', '%' . $search . '%');
                        $q->orWhere('measure_of_success', 'LIKE', '%' . $search . '%');
                    });
                }
            });

            $expanded = true;
            $currentSearch = implode(' ', $request->search);
        };
        // TODO: For UserExperience Test
        $supervisorGoals = $sQuery->with('goalType')
        ->with('comments')->get();
        return view('goal.library', compact('organizationGoals', 'supervisorGoals', 'currentSearch', 'expanded'));
    }

    public function showForLibrary(Request $request, $id) {
        if ($request->has("add") && $request->add) {
            $showAddBtn = true;
        } else {
            $showAddBtn = false;
        }
        $goal = Goal::withoutGlobalScope(NonLibraryScope::class)->find($id);
        return view('goal.partials.show', compact('goal', 'showAddBtn'));
    }

    private function copyFromLibrary(Goal $goal) {
        $newGoal = new Goal;
        $newGoal->title = $goal->title;
        $newGoal->what = $goal->what;
        $newGoal->measure_of_success = $goal->measure_of_success;
        $newGoal->start_date = $goal->start_date;
        $newGoal->target_date = $goal->target_date;
        $newGoal->status = $goal->status;
        $newGoal->goal_type_id = $goal->goal_type_id;
        $newGoal->user_id = Auth::id();
        $newGoal->created_by = $goal->user_id;
        $newGoal->save();
        return $newGoal;
    }

    public function saveFromLibraryMultiple(Request $request) {
        foreach ($request->goal_ids as $goal_id) {
            $goal = Goal::withoutGlobalScope(NonLibraryScope::class)->find($goal_id);
            $newGoal = $this->copyFromLibrary($goal);
            
            //add tags to new goal
            $orggoal_id = $goal_id;
            $newgoal_id = $newGoal->id;
            $tags = DB::table('goal_tags')                        
                    ->where('goal_id', $orggoal_id)
                    ->get();    
            if(count($tags) > 0) {
                foreach($tags as $tag){
                    $tag_id = $tag->tag_id;
                    DB::table('goal_tags')->insert(
                        array(
                               'goal_id'     =>   $newgoal_id, 
                               'tag_id'   =>   $tag_id,
                               'created_at'   =>   date('Y-m-d h:i:s a', time()),
                               'updated_at'   =>   date('Y-m-d h:i:s a', time())
                        )
                    );
                }
                $actualAuthId = session()->has('original-auth-id') ? session()->get('original-auth-id') : Auth::id();
                $arrayOrigTags = [];
                $arrayNewTags = array_column($newGoal->tags->toArray(), 'id');
                // Insert audits for Goal Tag changes
                if(array_diff($arrayOrigTags, $arrayNewTags) or array_diff($arrayNewTags, $arrayOrigTags)) {
                    $newAudit = new Audit;
                    $newAudit->user_type = 'App\Models\User';
                    $newAudit->user_id = $actualAuthId;
                    $newAudit->original_auth_id = $newAudit->user_id;
                    $newAudit->event = 'created';
                    $newAudit->auditable_type = 'App\Models\GoalTags';
                    $newAudit->auditable_id = $newGoal->id;
                    $newAudit->old_values = json_encode(Tag::whereIn('id', $arrayOrigTags)->pluck('id', 'name'));
                    $newAudit->new_values = json_encode(Tag::whereIn('id', $arrayNewTags)->pluck('id', 'name'));
                    $newAudit->url = (empty($_SERVER['HTTPS']) ? 'http' : 'https')."://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
                    $newAudit->save();
                }
            }             
        }
        return redirect()->route('goal.current');
    }

    public function hideFromLibraryMultiple(Request $request) {
        foreach ($request->goal_ids as $goal_id) {
            DB::table('goal_hide')->insert([
                'goal_id' => $goal_id,
                'user_id' => Auth::id(),
                // Add more columns and values as needed
            ]);
            
        }
        return redirect()->route('goal.library');
    }


    public function showFromLibraryMultiple(Request $request) {
        foreach ($request->goal_ids_hide as $goal_id) {
            DB::table('goal_hide')
                ->where('goal_id', $goal_id)
                ->where('user_id', Auth::id())
                ->delete();

        }
        return redirect()->route('goal.library');
    }

    public function saveFromLibrary(Request $request)
    {
        $goal = Goal::withoutGlobalScope(NonLibraryScope::class)->find($request->selected_goal);
        $newGoal = $this->copyFromLibrary($goal);
        
        //add tags to new goal
        $orggoal_id = $request->selected_goal;
        $newgoal_id = $newGoal->id;
        $tags = DB::table('goal_tags')                        
                ->where('goal_id', $orggoal_id)
                ->get();    
        if(count($tags) > 0) {
            foreach($tags as $tag){
                $tag_id = $tag->tag_id;
                DB::table('goal_tags')->insert(
                    array(
                           'goal_id'     =>   $newgoal_id, 
                           'tag_id'   =>   $tag_id,
                           'created_at'   =>   date('Y-m-d h:i:s a', time()),
                           'updated_at'   =>   date('Y-m-d h:i:s a', time())
                    )
                );
                $actualAuthId = session()->has('original-auth-id') ? session()->get('original-auth-id') : Auth::id();
                $arrayOrigTags = [];
                $arrayNewTags = array_column($newGoal->tags->toArray(), 'id');
                // Insert audits for Goal Tag changes
                if(array_diff($arrayOrigTags, $arrayNewTags) or array_diff($arrayNewTags, $arrayOrigTags)) {
                    $newAudit = new Audit;
                    $newAudit->user_type = 'App\Models\User';
                    $newAudit->user_id = $actualAuthId;
                    $newAudit->original_auth_id = $newAudit->user_id;
                    $newAudit->event = 'created';
                    $newAudit->auditable_type = 'App\Models\GoalTags';
                    $newAudit->auditable_id = $newGoal->id;
                    $newAudit->old_values = json_encode(Tag::whereIn('id', $arrayOrigTags)->pluck('id', 'name'));
                    $newAudit->new_values = json_encode(Tag::whereIn('id', $arrayNewTags)->pluck('id', 'name'));
                    $newAudit->url = (empty($_SERVER['HTTPS']) ? 'http' : 'https')."://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
                    $newAudit->save();
                }
            }
        }        
        
        return response()->json(['success' => true, 'data' => $newGoal, 'message' => 'Goal Added Successfully']);
    }

    public function addComment(Request $request, $id)
    {

        if ($request->comment != null and $request->comment != '') {
            $goal = Goal::findOrFail($id);
            $comment = new GoalComment;

            $comment->goal_id = $goal->id;
            $comment->user_id = Auth::id();
            $comment->parent_id = $request->parent_id ?? null;

            if (session()->get('original-auth-id') != null) {
                $comment->user_id = session()->get('original-auth-id');
            }
            else {
                $comment->user_id = Auth::id();
            }

            $comment->comment = $request->comment;

            $comment->save();

            $user = User::findOrFail($goal->user_id);
            $curr_user = User::findOrFail(Auth::id());
            $comment_user = User::findOrFail($comment->user_id);

            $user_validPreferredSupervisorID = $user->validPreferredSupervisor();
            $user_supervisors = $user->supervisorListPrimaryJob();
            $user_supervisor_emplid = null;
            $user_supervisor_id = null;
            $user_supervisor_default = null;
            $user_supervisor_default_id = null;
            foreach($user_supervisors as $user_sup) {
                if($user_sup->employee_id == $user_validPreferredSupervisorID) {
                    $user_supervisor_emplid = $user_sup->employee_id;
                    $user_supervisor_id = $user_sup->supervisor_id;
                }
                if($user_supervisor_default && $user_sup->employee_id > $user_supervisor_default) {
                    $user_supervisor_default = $user_sup->employee_id;
                    $user_supervisor_default_id = $user_sup->supervisor_id;
                }
                if(!$user_supervisor_default) {
                    $user_supervisor_default = $user_sup->employee_id;
                    $user_supervisor_default_id = $user_sup->supervisor_id;
                }
            }
            if(!$user_supervisor_emplid) {
                $user_supervisor_emplid = $user_supervisor_default;
                $user_supervisor_id = $user_supervisor_default_id;
            }

            $curr_validPreferredSupervisorID = $curr_user->validPreferredSupervisor();
            $curr_supervisors = $curr_user->supervisorListPrimaryJob();
            $curr_supervisor_emplid = null;
            $curr_supervisor_id = null;
            $curr_supervisor_default = null;
            $curr_supervisor_default_id = null;
            foreach($curr_supervisors as $curr_sup) {
                if($curr_sup->employee_id == $curr_validPreferredSupervisorID) {
                    $curr_supervisor_emplid = $curr_sup->employee_id;
                    $curr_supervisor_id = $curr_sup->supervisor_id;
                }
                if($curr_supervisor_default && $curr_sup->employee_id > $curr_supervisor_default) {
                    $curr_supervisor_default = $curr_sup->employee_id;
                    $curr_supervisor_default_id = $curr_sup->supervisor_id;
                }
                if(!$curr_supervisor_default) {
                    $curr_supervisor_default = $curr_sup->employee_id;
                    $curr_supervisor_default_id = $curr_sup->supervisor_id;
                }
            }
            if(!$curr_supervisor_emplid) {
                $curr_supervisor_emplid = $curr_supervisor_default;
                $curr_supervisor_id = $curr_supervisor_default_id;
            }

            if (($goal->last_supervisor_comment != 'Y') and (session()->get('original-auth-id') != null) and ($user_supervisor_id == session()->get('original-auth-id'))) {
                    //update flag
                $goal->last_supervisor_comment = 'Y';
                $goal->save();
            }

            //##1103 Verify notifications for Shared goals.   Expected result: Everybody including the goal owner must receive a notification when any member sharing the goal adds a comment.

            //get all included users
            $goal_owner_id = $goal->user_id;
            $shared_user_ids = DB::table('goals_shared_with')
                                ->select('user_id')
                                ->where('goal_id', $id)
                                ->pluck('user_id')
                                ->toArray();
            array_push($shared_user_ids, $goal_owner_id);
            $all_related_users_ids = $shared_user_ids;

            foreach($all_related_users_ids as $related_users_id){
                $related_user = User::findOrFail($related_users_id);

                if ($related_user) {
                    if($related_user->id != $comment_user->id) {
                        $notification = new \App\MicrosoftGraph\SendDashboardNotification();
                        $notification->user_id = $related_user->id;
                        $notification->notification_type = 'GC';
                        $notification->comment =  $comment_user->name . ' added a comment to your goal.';
                        $notification->related_id = $goal->id;
                        $notification->notify_user_id = $comment_user->id;
                        $notification->send(); 
                    }                    
                }

                if ($related_user && $user->userPreference->goal_comment_flag == 'Y') {
                    if($related_user->id != $comment_user->id) {
                        $sendMail = new SendMail();
                        $sendMail->toRecipients = array( $related_user->id );  
                        $sendMail->sender_id = null;
                        $sendMail->useQueue = true;
                        $sendMail->saveToLog = true;
                        $sendMail->alert_type = 'N';
                        $sendMail->alert_format = 'E';
                        $sendMail->template = 'EMPLOYEE_COMMENT_THE_GOAL';

                        array_push($sendMail->bindvariables, $goal->user->name);    // %1 Recipient of the email
                        array_push($sendMail->bindvariables,  $comment_user->name );        // %2 Person who added the comment
                        array_push($sendMail->bindvariables, $goal->title);         // %3 Goal title
                        array_push($sendMail->bindvariables, $comment->comment );   // %4 added comment
                        $response = $sendMail->sendMailWithGenericTemplate();
                    }
                }
            }

        }
        return redirect()->back();
    }

    public function updateStatus($id, $status)
    {
       $has_goal = DB::table('goals')                        
                            ->where('id', $id)
                            ->count();     
       if($has_goal){
           DB::table('goals')
            ->where('id', $id) 
            ->limit(1) 
            ->update(array('status' => $status)); 
       }
        return redirect()->back();
    }

    public function linkGoal(Request $request)
    {
        $linkedGoalIds = $request->linked_goal_id;
        if ($request->linked_goal_id) {

            $linkedGoalIds = explode(',', $linkedGoalIds);
            foreach ($linkedGoalIds as $key => $value) {
                LinkedGoal::updateOrCreate([
                    'user_goal_id' => $request->current_goal_id,
                    'supervisor_goal_id' => $value,
                ]);
            }
        }

        return redirect()->back();
    }

    public function copyGoal(Request $request, $id) {
        $goal = Goal::findOrFail($id);
        $userId = Auth::Id();

        // TODO: For UserExperience Test
        /* if (!$goal->sharedWith()->where('users.id', $userId)->exists()) {
        abort(403, __('You do not have access to the resource'));
    } */

        $newGoal = $goal->replicate();
        $newGoal->user_id = $userId;
        $newGoal->created_by = $goal->user_id;
        $newGoal->is_shared = 0;
        $newGoal->referenced_from = $goal->id;
        $newGoal->save();

        //add tags to copied goal
        $orggoal_id = $goal->id;
        $newgoal_id = $newGoal->id;
        $tags = DB::table('goal_tags')                        
                ->where('goal_id', $orggoal_id)
                ->get();    
        if(count($tags) > 0) {
            foreach($tags as $tag){
                $tag_id = $tag->tag_id;
                DB::table('goal_tags')->insert(
                    array(
                           'goal_id'     =>   $newgoal_id, 
                           'tag_id'   =>   $tag_id,
                           'created_at'   =>   date('Y-m-d h:i:s a', time()),
                           'updated_at'   =>   date('Y-m-d h:i:s a', time())
                    )
                );
                $actualAuthId = session()->has('original-auth-id') ? session()->get('original-auth-id') : Auth::id();
                $arrayOrigTags = [];
                $arrayNewTags = array_column($newGoal->tags->toArray(), 'id');
                // Insert audits for Goal Tag changes
                if(array_diff($arrayOrigTags, $arrayNewTags) or array_diff($arrayNewTags, $arrayOrigTags)) {
                    $newAudit = new Audit;
                    $newAudit->user_type = 'App\Models\User';
                    $newAudit->user_id = $actualAuthId;
                    $newAudit->original_auth_id = $newAudit->user_id;
                    $newAudit->event = 'created';
                    $newAudit->auditable_type = 'App\Models\GoalTags';
                    $newAudit->auditable_id = $newGoal->id;
                    $newAudit->old_values = json_encode(Tag::whereIn('id', $arrayOrigTags)->pluck('id', 'name'));
                    $newAudit->new_values = json_encode(Tag::whereIn('id', $arrayNewTags)->pluck('id', 'name'));
                    $newAudit->url = (empty($_SERVER['HTTPS']) ? 'http' : 'https')."://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
                    $newAudit->save();
                }
            }
        }        

        return redirect()->route('goal.current');
    }
    
    public function syncGoals(Request $request) {
        if ($request->has("sync_goal_id") && $request->sync_goal_id) {
            $goal_id = $request->sync_goal_id;

            //get users who already shared with this goal
            $previousList = GoalSharedWith::where('goal_id', $goal_id)->pluck('user_id')->toArray();
            if ($request->has("sync_users") && $request->sync_users) { 
                if(!is_array($request->sync_users)){                    
                    GoalSharedWith::where('goal_id', $goal_id)->delete();
                    $users_arr = explode(',', $request->sync_users);
                } else {
                    $users_arr = $request->sync_users;
                }   

                if(count($users_arr)>0){   
                    if(is_numeric($users_arr[0])){
                        GoalSharedWith::where('goal_id', $goal_id)->delete();
                        foreach($users_arr as $userId){
                            $goalSharedWith = GoalSharedWith::create([
                                'goal_id' => $goal_id,
                                'user_id' => $userId,
                            ]);
                        }
                    } else {
                        foreach($users_arr as $userId){
                            if (is_numeric($userId)){
                                $goalSharedWith = GoalSharedWith::create([
                                    'goal_id' => $goal_id,
                                    'user_id' => $userId,
                                ]);
                            }
                        }
                    }
                    $listDifference = array_diff($users_arr, $previousList);
                    error_log(print_r($listDifference,true));
                    foreach($listDifference as $last_item){ 
                        $this->syncGoalNotifications($request, $goal_id, $last_item);
                    }
                } else {
                    GoalSharedWith::where('goal_id', $goal_id)->delete();
                }
            } else {
                GoalSharedWith::where('goal_id', $goal_id)->delete();
            }
        }
        if (!$request->ajax()) {
            return redirect()->back();
        }
    }

    public function syncGoalNotifications(Request $request, $goal_id, $user_id) {
        $user = User::where('id', $user_id)
                    ->with('userPreference')
                    ->first();
        $curr_user = User::where('id',Auth::id())
                    ->with('userPreference')
                    ->first();
        
        $goal = DB::table('goals')  
                ->select('title')
                ->where('id', '=', $goal_id)
                ->where('status', '=', 'active')
                ->get();
                    

        if ($user && $user->allow_inapp_notification) {

            $notification = new \App\MicrosoftGraph\SendDashboardNotification();
            $notification->user_id = $user_id;
            $notification->notification_type = 'GS';
            $notification->comment =  $curr_user->name . ' shared a goal with you.';
            $notification->related_id = $goal_id;
            $notification->notify_user_id = $user_id;
            $notification->send(); 
        }
        if($user && $user->allow_email_notification && $user->userPreference->goal_bank_flag == 'Y') {
            $sendMail = new \App\MicrosoftGraph\SendMail();
            $sendMail->toRecipients = array( $user->id );  
            $sendMail->sender_id = null;  // default sender is System
            $sendMail->useQueue = true;
            $sendMail->template = 'GOAL_SHARED';
            array_push($sendMail->bindvariables, $user->name);  // %1 Recipient of the email
            array_push($sendMail->bindvariables, $curr_user->name);    // %2 Person who shared the goal
            array_push($sendMail->bindvariables, $goal[0]->title);        // %3 Goal title
            $response = $sendMail->sendMailWithGenericTemplate();
        }

    }
    
    public function getAllUsers(Request $request)
    {
        $current_user = '';
        if(session()->has('checking_user') && session()->get('checking_user') != '') {
            $current_user = session()->get('checking_user');
        }
        
        
        $search = $request->search;
        
        if ($current_user == '') {
            $user_query = User::where('name', 'LIKE', "%{$search}%")
                          ->join('employee_demo', 'employee_demo.employee_id','users.employee_id')
                          ->whereNull('employee_demo.date_deleted')  
                          ->whereRaw('employee_demo.pdp_excluded = 0')
                          ->paginate();
        } else {
            $user_query = User::where('name', 'LIKE', "%{$search}%")
                          ->where('id', '<>', $current_user)
                          ->join('employee_demo', 'employee_demo.employee_id','users.employee_id')
                          ->whereNull('employee_demo.date_deleted')  
                          ->whereRaw('employee_demo.pdp_excluded = 0')
                          ->paginate();
        }
        
        return $this->respondeWith($user_query);
    }
    
    public function getAllUsersOptions(Request $request)
    {
        $current_user = '';
        if(session()->has('checking_user') && session()->get('checking_user') != '') {
            $current_user = session()->get('checking_user');
        }
        
        $search = $request->search;
        
        if ($current_user == '') {
            $user_query = EmployeeDemo::join(\DB::raw('users USE INDEX (USERS_EMPLOYEE_ID_EMPL_RECORD_INDEX)'), 'users.employee_id', 'employee_demo.employee_id')
            ->when("{$search}", function($q) use($search) { return $q->where('employee_demo.employee_name', 'LIKE', "%{$search}%"); })
            ->whereNull('employee_demo.date_deleted')
            ->select('users.id', 'employee_demo.employee_name AS name', 'employee_demo.employee_email')
            ->distinct()
            ->paginate();
        } else {
            $user_query = EmployeeDemo::join(\DB::raw('users USE INDEX (USERS_EMPLOYEE_ID_EMPL_RECORD_INDEX)'), 'users.employee_id', 'employee_demo.employee_id')
            ->when("{$search}", function($q) use($search) { return $q->where('employee_demo.employee_name', 'LIKE', "%{$search}%"); })
            ->whereNull('employee_demo.date_deleted')
            ->where('users.id', '<>', $current_user) 
            ->select('users.id', 'employee_demo.employee_name AS name', 'employee_demo.employee_email')
            ->distinct()
            ->paginate();
        }
        
        return $this->respondeWith($user_query);
    }


    

    public function getUser(Request $request)
    {
        $id = $request->id;
        // Retrieve the user details
        $user = User::find($id);
        
        // Check if user is found
        if ($user) {
            return response()->json([
                'success' => true,
                'data' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }
    }
}
