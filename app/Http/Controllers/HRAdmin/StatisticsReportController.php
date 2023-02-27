<?php

namespace App\Http\Controllers\HRAdmin;

use Carbon\Carbon;
use App\Models\Tag;
use App\Models\Goal;
use App\Models\User;
use App\Models\GoalType;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use Illuminate\Http\Request;
use App\Models\OrganizationTree;
use App\Models\ConversationTopic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Exports\ConversationExport;
use App\Exports\UserGoalCountExport;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SharedEmployeeExport;
use App\Exports\ExcusedEmployeeExport;

class StatisticsReportController extends Controller
{
    //
    private $groups;
    private $overdue_groups;

    
    public function __construct()
    {
        $this->groups = [
            '0' => [0,0],
            '1-5' => [1,5],
            '6-10' => [6,10],
            '>10' => [11,99999],
        ];

        $this->overdue_groups = [
            'overdue' => [-999999,0],
            '< 1 week' => [1,7],
            '1 week to 1 month' => [8,30],
            '> 1 month' => [31,999999],
        ];

        set_time_limit(120);    // 3 mins

    }

    Public function goalSummary_from_statement($goal_type_id)
    {
        $from_stmt = "(select users.id, users.email, users.employee_id, users.empl_record, users.guid, users.reporting_to, 
                        users.excused_start_date, users.excused_end_date, users.due_date_paused,
                        (select count(*) from goals where user_id = users.id
                        and status = 'active' and deleted_at is null and is_library = 0 ";
        if ($goal_type_id)                        
            $from_stmt .= " and goal_type_id =".  $goal_type_id ;
        $from_stmt .= ") as goals_count from users ) AS A";

        return $from_stmt;
    }

    public function goalSummary(Request $request)
    {
       
        // send back the input parameters
        $this->preservedInputParams($request);

        $level0 = $request->dd_level0 ? OrganizationTree::where('id', $request->dd_level0)->first() : null;
        $level1 = $request->dd_level1 ? OrganizationTree::where('id', $request->dd_level1)->first() : null;
        $level2 = $request->dd_level2 ? OrganizationTree::where('id', $request->dd_level2)->first() : null;
        $level3 = $request->dd_level3 ? OrganizationTree::where('id', $request->dd_level3)->first() : null;
        $level4 = $request->dd_level4 ? OrganizationTree::where('id', $request->dd_level4)->first() : null;

        $request->session()->flash('level0', $level0);
        $request->session()->flash('level1', $level1);
        $request->session()->flash('level2', $level2);
        $request->session()->flash('level3', $level3);
        $request->session()->flash('level4', $level4);

        $types = GoalType::orderBy('id')->get();
        $types->prepend( new GoalType()  ) ;


        // $matched_user_ids = User::join('employee_demo', function($join) {
        //                             $join->on('employee_demo.guid', '=', 'users.guid');
        //                             // $join->on('employee_demo.employee_id', '=', 'users.employee_id');
        //                             // $join->on('employee_demo.empl_record', '=', 'users.empl_record');
        //                     }) 
        //                     ->join('admin_orgs', function ($j1) {
        //                         $j1->on(function ($j1a) {
        //                             $j1a->whereRAW('admin_orgs.organization = employee_demo.organization OR ((admin_orgs.organization = "" OR admin_orgs.organization IS NULL) AND (employee_demo.organization = "" OR employee_demo.organization IS NULL))');
        //                         } )
        //                         ->on(function ($j2a) {
        //                             $j2a->whereRAW('admin_orgs.level1_program = employee_demo.level1_program OR ((admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL) AND (employee_demo.level1_program = "" OR employee_demo.level1_program IS NULL))');
        //                         } )
        //                         ->on(function ($j3a) {
        //                             $j3a->whereRAW('admin_orgs.level2_division = employee_demo.level2_division OR ((admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL) AND (employee_demo.level2_division = "" OR employee_demo.level2_division IS NULL))');
        //                         } )
        //                         ->on(function ($j4a) {
        //                             $j4a->whereRAW('admin_orgs.level3_branch = employee_demo.level3_branch OR ((admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL) AND (employee_demo.level3_branch = "" OR employee_demo.level3_branch IS NULL))');
        //                         } )
        //                         ->on(function ($j5a) {
        //                             $j5a->whereRAW('admin_orgs.level4 = employee_demo.level4 OR ((admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL) AND (employee_demo.level4 = "" OR employee_demo.level4 IS NULL))');
        //                         } );
        //                     })
        //                     ->where('admin_orgs.user_id', '=', Auth::id())
        //                     ->pluck('users.id');

        foreach($types as $type)
        {
            $goal_id = $type->id ? $type->id : '';

            $from_stmt = $this->goalSummary_from_statement($type->id);

            $sql = User::selectRaw('AVG(goals_count) as goals_average')
                        ->from(DB::raw( $from_stmt ))
                        ->join('employee_demo', function($join) {
                            $join->on('employee_demo.employee_id', '=', 'A.employee_id');
                            // $join->on('employee_demo.employee_id', '=', 'A.employee_id');
                            // $join->on('employee_demo.empl_record', '=', 'A.empl_record');
                        })
                        // ->join('employee_demo_jr as j', 'employee_demo.guid', 'j.guid')
                        // ->whereRaw("j.id = (select max(j1.id) from employee_demo_jr as j1 where j1.guid = j.guid) and (j.due_date_paused = 'N') ")
                        ->where('A.due_date_paused', 'N')
                        ->when($level0, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                            return $q->where('employee_demo.organization', $level0->name);
                        })
                        ->when( $level1, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                            return $q->where('employee_demo.level1_program', $level1->name);
                        })
                        ->when( $level2, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                            return $q->where('employee_demo.level2_division', $level2->name);
                        })
                        ->when( $level3, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                            return $q->where('employee_demo.level3_branch', $level3->name);
                        })
                        ->when( $level4, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                            return $q->where('employee_demo.level4', $level4->name);
                        })
                        // ->where( function($query) {
                        //     $query->whereRaw('date(SYSDATE()) not between IFNULL(A.excused_start_date,"1900-01-01") and IFNULL(A.excused_end_date,"1900-01-01")')
                        //           ->where('employee_demo.employee_status', 'A');
                        // })
                        // ->whereIn('A.id', $matched_user_ids);
                        ->whereExists(function ($query) {
                            $query->select(DB::raw(1))
                                    ->from('admin_org_users')
                                    ->whereColumn('admin_org_users.allowed_user_id', 'A.id')
                                    ->whereIn('admin_org_users.access_type', [0,1])
                                    ->where('admin_org_users.granted_to_id', '=', Auth::id());
                        });

            $goals_average = $sql->get()->first()->goals_average;

            $data[$goal_id] = [ 
                'name' => $type->name ? ' ' . $type->name : '',
                'goal_type_id' => $goal_id,
                'average' =>  $goals_average, 
                'groups' => []
            ];

            $sql = User::selectRaw("case when goals_count between 0 and 0  then '0'  
                                    when goals_count between 1 and 5  then '1-5'
                                    when goals_count between 6 and 10 then '6-10'
                                    when goals_count  > 10            then '>10'
                            end AS group_key, count(*) as goals_count")
                ->from(DB::raw( $from_stmt ))
                ->groupBy('group_key')
                    ->from(DB::raw( $from_stmt ))
                    ->join('employee_demo', function($join) {
                        $join->on('employee_demo.employee_id', '=', 'A.employee_id');
                        //$join->on('employee_demo.employee_id', '=', 'A.employee_id');
                        //$join->on('employee_demo.empl_record', '=', 'A.empl_record');
                    })
                    // ->join('employee_demo_jr as j', 'employee_demo.guid', 'j.guid')
                    // ->whereRaw("j.id = (select max(j1.id) from employee_demo_jr as j1 where j1.guid = j.guid) and (j.due_date_paused = 'N') ")
                    ->where('A.due_date_paused', 'N')
                    ->when($level0, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                        return $q->where('employee_demo.organization', $level0->name);
                    })
                    ->when( $level1, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                        return $q->where('employee_demo.level1_program', $level1->name);
                    })
                    ->when( $level2, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                        return $q->where('employee_demo.level2_division', $level2->name);
                    })
                    ->when( $level3, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                        return $q->where('employee_demo.level3_branch', $level3->name);
                    })
                    ->when( $level4, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                        return $q->where('employee_demo.level4', $level4->name);
                    })
                    // ->where('acctlock', 0)
                    // ->whereBetween('goals_count', $range)
                    // ->whereIn('A.id', $matched_user_ids);
                    // ->where( function($query) {
                    //     $query->whereRaw('date(SYSDATE()) not between IFNULL(A.excused_start_date,"1900-01-01") and IFNULL(A.excused_end_date,"1900-01-01")')
                    //           ->where('employee_demo.employee_status', 'A');
                    // })
                    ->whereExists(function ($query) {
                        $query->select(DB::raw(1))
                                ->from('admin_org_users')
                                ->whereColumn('admin_org_users.allowed_user_id', 'A.id')
                                ->whereIn('admin_org_users.access_type', [0,1])
                                ->where('admin_org_users.granted_to_id', '=', Auth::id());
                    });
                    // ->whereExists(function ($query) {
                    //     $query->select(DB::raw(1))
                    //             ->from('admin_orgs')
                    //             // ->whereColumn('admin_orgs.organization', 'employee_demo.organization')
                    //             // ->whereColumn('admin_orgs.level1_program', 'employee_demo.level1_program')
                    //             // ->whereColumn('admin_orgs.level2_division', 'employee_demo.level2_division')
                    //             // ->whereColumn('admin_orgs.level3_branch',  'employee_demo.level3_branch')
                    //             // ->whereColumn('admin_orgs.level4', 'employee_demo.level4')
                    //             ->whereRAW('(admin_orgs.organization = employee_demo.organization OR (admin_orgs.organization = "" OR admin_orgs.organization IS NULL))')
                    //             ->whereRAW('(admin_orgs.level1_program = employee_demo.level1_program OR (admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL))')
                    //             ->whereRAW('(admin_orgs.level2_division = employee_demo.level2_division OR (admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL))')
                    //             ->whereRAW('(admin_orgs.level3_branch = employee_demo.level3_branch OR (admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL))')
                    //             ->whereRAW('(admin_orgs.level4 = employee_demo.level4 OR (admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL))')
                    //             ->where('admin_orgs.user_id', '=', Auth::id() );
                    // });

                $goals_count_array = $sql->pluck( 'goals_count','group_key' )->toArray();

                foreach($this->groups as $key => $range) {
                    $goals_count = 0;
                    if (array_key_exists( $key, $goals_count_array)) {
                            $goals_count = $goals_count_array[$key];
                    }
    
                    array_push( $data[$goal_id]['groups'], [ 'name' => $key, 'value' => $goals_count, 
                        'goal_id' => $goal_id, 
                                                                                            
                    ]);
                }
            

        }

        // Goal Tag count 
        $count_raw = "id, name, ";
        $count_raw .= " (select count(*) from goal_tags, goals, users, employee_demo ";
        $count_raw .= "   where goals.id = goal_tags.goal_id "; 
	    $count_raw .= "     and tag_id = tags.id ";  
        $count_raw .= "     and users.id = goals.user_id ";
        // $count_raw .= "     and users.employee_id = employee_demo.employee_id ";
        $count_raw .= "     and users.employee_id = employee_demo.employee_id ";
        $count_raw .= $level0 ? "     and employee_demo.organization = '". addslashes($level0->name) ."'" : '';
        $count_raw .= $level1 ? "     and employee_demo.level1_program = '". addslashes($level1->name) ."'" : '';
        $count_raw .= $level2 ? "     and employee_demo.level2_division = '". addslashes($level2->name) ."'" : '';
        $count_raw .= $level3 ? "     and employee_demo.level3_branch = '". addslashes($level3->name) ."'" : '';
        $count_raw .= $level4 ? "     and employee_demo.level4 = '". addslashes($level4->name) ."'" : '';
        $count_raw .= "     and ( ";
        $count_raw .= "           users.due_date_paused = 'N' ";
        // $count_raw .= "           date(SYSDATE()) not between IFNULL(users.excused_start_date,'1900-01-01')  and IFNULL(users.excused_end_date,'1900-01-01')  "; 
        // $count_raw .= "       and employee_demo.employee_status = 'A' ";
        $count_raw .= "         )";
        $count_raw .= "     and exists (select 1 from admin_org_users ";
        $count_raw .= "                  where admin_org_users.allowed_user_id = users.id ";
        $count_raw .= "                    and admin_org_users.access_type in (0,1) ";
        $count_raw .= "                    and admin_org_users.granted_to_id = ".  Auth::id()  .") ";
        // $count_raw .= "     and exists (select 1 from admin_orgs ";
        // $count_raw .= "                  where admin_orgs.organization = employee_demo.organization ";
        // $count_raw .= "                    and admin_orgs.level1_program = employee_demo.level1_program ";
        // $count_raw .= "                    and admin_orgs.level2_division = employee_demo.level2_division ";
        // $count_raw .= "                    and admin_orgs.level3_branch = employee_demo.level3_branch ";
        // $count_raw .= "                    and admin_orgs.level4 = employee_demo.level4 ";
        // $count_raw .= "                    and admin_orgs.user_id = ".  Auth::id()  .") ";
        $count_raw .= ") as count";

        $sql = Tag::selectRaw($count_raw);
        $sql2 = Goal::join('users', function($join) {
                    $join->on('goals.user_id', '=', 'users.id');
                })
                ->join('employee_demo', function($join) {
                    $join->on('employee_demo.employee_id', '=', 'users.employee_id');
                    // $join->on('employee_demo.employee_id', '=', 'users.employee_id');
                    // $join->on('employee_demo.empl_record', '=', 'A.empl_record');
                })
                // ->join('employee_demo_jr as j', 'employee_demo.guid', 'j.guid')
                // ->whereRaw("j.id = (select max(j1.id) from employee_demo_jr as j1 where j1.guid = j.guid) and (j.due_date_paused = 'N') ")
                ->where('users.due_date_paused', 'N')                
                ->when($level0, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.organization', $level0->name);
                })
                ->when( $level1, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level1_program', $level1->name);
                })
                ->when( $level2, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level2_division', $level2->name);
                })
                ->when( $level3, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level3_branch', $level3->name);
                })
                ->when( $level4, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level4', $level4->name);
                })
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                          ->from('goal_tags')
                          ->whereColumn('goals.id', 'goal_tags.goal_id');
                })
                ->where('employee_demo.guid', '<>', '')
                // ->where( function($query) {
                //     $query->whereRaw('date(SYSDATE()) not between IFNULL(users.excused_start_date,"1900-01-01") and IFNULL(users.excused_end_date,"1900-01-01")')
                //           ->where('employee_demo.employee_status', 'A');
                // })
                // ->whereIn('users.id', $matched_user_ids);
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                            ->from('admin_org_users')
                            ->whereColumn('admin_org_users.allowed_user_id', 'users.id')
                            ->whereIn('admin_org_users.access_type', [0,1])
                            ->where('admin_org_users.granted_to_id', '=', Auth::id());
                });

        $tags = $sql->get();
        $blank_count = $sql2->count();
        
        $data_tag = [ 
            'name' => 'Active Goal Tags',
            'labels' => [],
            'values' => [],
        ];

        // each group 
        array_push($data_tag['labels'], '[Blank]');  
        array_push($data_tag['values'], $blank_count);
        foreach($tags as $key => $tag)
        {
            array_push($data_tag['labels'], $tag->name);  
            array_push($data_tag['values'], $tag->count);
        }

        return view('hradmin.statistics.goalsummary',compact('data','data_tag'));
    }

    public function goalSummaryExport(Request $request)
    {

        $level0 = $request->dd_level0 ? OrganizationTree::where('id', $request->dd_level0)->first() : null;
        $level1 = $request->dd_level1 ? OrganizationTree::where('id', $request->dd_level1)->first() : null;
        $level2 = $request->dd_level2 ? OrganizationTree::where('id', $request->dd_level2)->first() : null;
        $level3 = $request->dd_level3 ? OrganizationTree::where('id', $request->dd_level3)->first() : null;
        $level4 = $request->dd_level4 ? OrganizationTree::where('id', $request->dd_level4)->first() : null;


        $from_stmt = $this->goalSummary_from_statement($request->goal);

        $sql = User::selectRaw('A.*, goals_count, employee_demo.employee_name, 
                                employee_demo.organization, employee_demo.level1_program, employee_demo.level2_division, employee_demo.level3_branch, employee_demo.level4')
                ->from(DB::raw( $from_stmt ))                                
                ->join('employee_demo', function($join) {
                    $join->on('employee_demo.employee_id', '=', 'A.employee_id');
                    // $join->on('employee_demo.employee_id', '=', 'A.employee_id');
                    //$join->on('employee_demo.empl_record', '=', 'A.empl_record');
                })
                // ->join('employee_demo_jr as j', 'employee_demo.guid', 'j.guid')
                // ->whereRaw("j.id = (select max(j1.id) from employee_demo_jr as j1 where j1.guid = j.guid) and (j.due_date_paused = 'N') ")
                ->where('A.due_date_paused', 'N')
                ->whereNotNull('A.guid')
                ->when($level0, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.organization', $level0->name);
                })
                ->when( $level1, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level1_program', $level1->name);
                })
                ->when( $level2, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level2_division', $level2->name);
                })
                ->when( $level3, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level3_branch', $level3->name);
                })
                ->when( $level4, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level4', $level4->name);
                })
                // ->where('acctlock', 0)
                ->when( (array_key_exists($request->range, $this->groups)) , function($q) use($request) {
                    return $q->whereBetween('goals_count', $this->groups[$request->range]);
                })
                // ->where( function($query) {
                //     $query->whereRaw('date(SYSDATE()) not between IFNULL(A.excused_start_date,"1900-01-01") and IFNULL(A.excused_end_date,"1900-01-01") ')
                //           ->where('employee_demo.employee_status', 'A');
                // })
                // ->whereExists(function ($query) {
                //     $query->select(DB::raw(1))
                //             ->from('admin_orgs')
                //             // ->whereColumn('admin_orgs.organization', 'employee_demo.organization')
                //             // ->whereColumn('admin_orgs.level1_program', 'employee_demo.level1_program')
                //             // ->whereColumn('admin_orgs.level2_division', 'employee_demo.level2_division')
                //             // ->whereColumn('admin_orgs.level3_branch',  'employee_demo.level3_branch')
                //             // ->whereColumn('admin_orgs.level4', 'employee_demo.level4')
                //             ->whereRAW('(admin_orgs.organization = employee_demo.organization OR (admin_orgs.organization = "" OR admin_orgs.organization IS NULL))')
                //             ->whereRAW('(admin_orgs.level1_program = employee_demo.level1_program OR (admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL))')
                //             ->whereRAW('(admin_orgs.level2_division = employee_demo.level2_division OR (admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL))')
                //             ->whereRAW('(admin_orgs.level3_branch = employee_demo.level3_branch OR (admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL))')
                //             ->whereRAW('(admin_orgs.level4 = employee_demo.level4 OR (admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL))')
                //             ->where('admin_orgs.user_id', '=', Auth::id() );
                // });
                // ->join('admin_orgs', function ($j1) {
                //     $j1->on(function ($j1a) {
                //         $j1a->whereRAW('admin_orgs.organization = employee_demo.organization OR ((admin_orgs.organization = "" OR admin_orgs.organization IS NULL) AND (employee_demo.organization = "" OR employee_demo.organization IS NULL))');
                //     } )
                //     ->on(function ($j2a) {
                //         $j2a->whereRAW('admin_orgs.level1_program = employee_demo.level1_program OR ((admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL) AND (employee_demo.level1_program = "" OR employee_demo.level1_program IS NULL))');
                //     } )
                //     ->on(function ($j3a) {
                //         $j3a->whereRAW('admin_orgs.level2_division = employee_demo.level2_division OR ((admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL) AND (employee_demo.level2_division = "" OR employee_demo.level2_division IS NULL))');
                //     } )
                //     ->on(function ($j4a) {
                //         $j4a->whereRAW('admin_orgs.level3_branch = employee_demo.level3_branch OR ((admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL) AND (employee_demo.level3_branch = "" OR employee_demo.level3_branch IS NULL))');
                //     } )
                //     ->on(function ($j5a) {
                //         $j5a->whereRAW('admin_orgs.level4 = employee_demo.level4 OR ((admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL) AND (employee_demo.level4 = "" OR employee_demo.level4 IS NULL))');
                //     } );
                // })
                // ->where('admin_orgs.user_id', '=', Auth::id());
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                            ->from('admin_org_users')
                            ->whereColumn('admin_org_users.allowed_user_id', 'A.id')
                            ->whereIn('admin_org_users.access_type', [0,1])
                            ->where('admin_org_users.granted_to_id', '=', Auth::id());
                });

        $users = $sql->get();

      
        // Generating Output file 
        $filename = 'Active Goals Per Employee.csv';
        if ($request->goal) {        
            $type = GoalType::where('id', $request->goal)->first();
            $filename = 'Active ' . ($type ? $type->name . ' ' : '') . 'Goals Per Employee.csv';
        }

        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $columns = ["Employee ID", "Name", "Email", 'Active Goals Count', 
                        "Organization", "Level 1", "Level 2", "Level 3", "Level 4", "Reporting To",
                    ];

        $callback = function() use($users, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($users as $user) {
                $row['Employee ID'] = $user->employee_id;
                $row['Name'] = $user->employee_name;
                $row['Email'] = $user->email;
                $row['Active Goals Count'] = $user->goals_count;
                $row['Organization'] = $user->organization;
                $row['Level 1'] = $user->level1_program;
                $row['Level 2'] = $user->level2_division;
                $row['Level 3'] = $user->level3_branch;
                $row['Level 4'] = $user->level4;
                $row['Reporting To'] = $user->reportingManager ? $user->reportingManager->name : '';

                fputcsv($file, array($row['Employee ID'], $row['Name'], $row['Email'], $row['Active Goals Count'], $row['Organization'],
                            $row['Level 1'], $row['Level 2'], $row['Level 3'], $row['Level 4'], $row['Reporting To'] ));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);

    }

    public function goalSummaryTagExport(Request $request)
    {

        $level0 = $request->dd_level0 ? OrganizationTree::where('id', $request->dd_level0)->first() : null;
        $level1 = $request->dd_level1 ? OrganizationTree::where('id', $request->dd_level1)->first() : null;
        $level2 = $request->dd_level2 ? OrganizationTree::where('id', $request->dd_level2)->first() : null;
        $level3 = $request->dd_level3 ? OrganizationTree::where('id', $request->dd_level3)->first() : null;
        $level4 = $request->dd_level4 ? OrganizationTree::where('id', $request->dd_level4)->first() : null;

        $tags = Tag::when($request->tag, function ($q) use($request) {
                        return $q->where('name', $request->tag);
                    })
                    ->orderBy('name')->get();

        $count_raw = "users.*, ";
        $count_raw .= " employee_demo.employee_name, employee_demo.organization, employee_demo.level1_program, employee_demo.level2_division, employee_demo.level3_branch, employee_demo.level4";
        if (!$request->tag || $request->tag == '[Blank]') {
            $count_raw .= " ,(select count(*) from goals ";
            $count_raw .= "    where users.id = goals.user_id ";
            $count_raw .= "      and not exists (select 'x' from goal_tags ";
            $count_raw .= "                       where goals.id = goal_tags.goal_id) ";
            $count_raw .= "      and goals.deleted_at is null and goals.is_library = 0 ";            
            $count_raw .= "      and employee_demo.guid <> '' ";
            
            $count_raw .= "     and ( ";
            $count_raw .= "            users.due_date_paused = 'N' ";
            // $count_raw .= "            date(SYSDATE()) not between IFNULL(users.excused_start_date, '1900-01-01') and IFNULL(users.excused_end_date,'1900-01-01') "; 
            // $count_raw .= "        and employee_demo.employee_status = 'A' ";
            $count_raw .= "         )";
            
            $count_raw .= " ) as 'tag_0' ";
        }
        foreach ($tags as $tag) {
            $count_raw .= " ,(select count(*) from goal_tags, goals ";
            $count_raw .= "    where goals.id = goal_tags.goal_id "; 
            $count_raw .= "      and tag_id = " . $tag->id;  
            $count_raw .= "      and users.id = goals.user_id ";
            
            $count_raw .= "     and ( ";
            $count_raw .= "            users.due_date_paused = 'N' ";
            // $count_raw .= "            date(SYSDATE()) not between IFNULL(users.excused_start_date,'1900-01-01')  and IFNULL(users.excused_end_date,'1900-01-01') "; 
            // $count_raw .= "        and employee_demo.employee_status = 'A' ";
            $count_raw .= "         )";
            
            $count_raw .= ") as 'tag_". $tag->id ."'";
        }

        $sql = User::selectRaw($count_raw)
                    ->join('employee_demo', function($join) {
                        $join->on('employee_demo.employee_id', '=', 'users.employee_id');
                        // $join->on('employee_demo.employee_id', '=', 'users.employee_id');
                        // $join->on('employee_demo.empl_record', '=', 'A.empl_record');
                    })
                    // ->join('employee_demo_jr as j', 'employee_demo.guid', 'j.guid')
                    // ->whereRaw("j.id = (select max(j1.id) from employee_demo_jr as j1 where j1.guid = j.guid) and (j.due_date_paused = 'N') ")
                    ->where('users.due_date_paused', 'N')
                    ->when($level0, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                        return $q->where('employee_demo.organization', $level0->name);
                    })
                    ->when( $level1, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                        return $q->where('employee_demo.level1_program', $level1->name);
                    })
                    ->when( $level2, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                        return $q->where('employee_demo.level2_division', $level2->name);
                    })
                    ->when( $level3, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                        return $q->where('employee_demo.level3_branch', $level3->name);
                    })
                    ->when( $level4, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                        return $q->where('employee_demo.level4', $level4->name);
                    })
                    ->whereExists(function ($query) {
                        $query->select(DB::raw(1))
                                ->from('goals')
                                ->whereColumn('goals.user_id',  'users.id');
                    })
                    // To show the tag == selected tag name
                    ->when( ($request->tag && $request->tag <> '[Blank]' ), function ($q) use ($request) {
                        $q->whereExists(function ($query) use ($request) {
                              return $query->select(DB::raw(1))
                                        ->from('goals')
                                        ->join('goal_tags', 'goals.id', '=', 'goal_tags.goal_id')
                                        ->join('tags', 'goal_tags.tag_id', '=', 'tags.id')
                                        ->whereColumn('goals.user_id',  'users.id')
                                        ->where('name', $request->tag);
                            });
                    })  
                    // To show the  tag == '[blank]'
                    ->when( ($request->tag && $request->tag == '[Blank]' ), function ($q) {
                        $q->whereNotExists(function ($query) {
                              return $query->select(DB::raw(1))
                                        ->from('goals')
                                        ->join('goal_tags', 'goals.id', '=', 'goal_tags.goal_id')
                                        ->whereColumn('goals.user_id',  'users.id');
                                });
                    })
                    // ->where( function($query) {
                    //     $query->whereRaw('date(SYSDATE()) between users.excused_start_date and users.excused_end_date')
                    //           ->orWhere('employee_demo.employee_status', 'A');
                    // })  
                    // ->whereExists(function ($query) {
                    //     $query->select(DB::raw(1))
                    //             ->from('admin_orgs')
                    //             // ->whereColumn('admin_orgs.organization', 'employee_demo.organization')
                    //             // ->whereColumn('admin_orgs.level1_program', 'employee_demo.level1_program')
                    //             // ->whereColumn('admin_orgs.level2_division', 'employee_demo.level2_division')
                    //             // ->whereColumn('admin_orgs.level3_branch',  'employee_demo.level3_branch')
                    //             // ->whereColumn('admin_orgs.level4', 'employee_demo.level4')
                    //             ->whereRAW('(admin_orgs.organization = employee_demo.organization OR (admin_orgs.organization = "" OR admin_orgs.organization IS NULL))')
                    //             ->whereRAW('(admin_orgs.level1_program = employee_demo.level1_program OR (admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL))')
                    //             ->whereRAW('(admin_orgs.level2_division = employee_demo.level2_division OR (admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL))')
                    //             ->whereRAW('(admin_orgs.level3_branch = employee_demo.level3_branch OR (admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL))')
                    //             ->whereRAW('(admin_orgs.level4 = employee_demo.level4 OR (admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL))')
                    //             ->where('admin_orgs.user_id', '=', Auth::id() );
                    // });
                    // ->join('admin_orgs', function ($j1) {
                    //     $j1->on(function ($j1a) {
                    //         $j1a->whereRAW('admin_orgs.organization = employee_demo.organization OR ((admin_orgs.organization = "" OR admin_orgs.organization IS NULL) AND (employee_demo.organization = "" OR employee_demo.organization IS NULL))');
                    //     } )
                    //     ->on(function ($j2a) {
                    //         $j2a->whereRAW('admin_orgs.level1_program = employee_demo.level1_program OR ((admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL) AND (employee_demo.level1_program = "" OR employee_demo.level1_program IS NULL))');
                    //     } )
                    //     ->on(function ($j3a) {
                    //         $j3a->whereRAW('admin_orgs.level2_division = employee_demo.level2_division OR ((admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL) AND (employee_demo.level2_division = "" OR employee_demo.level2_division IS NULL))');
                    //     } )
                    //     ->on(function ($j4a) {
                    //         $j4a->whereRAW('admin_orgs.level3_branch = employee_demo.level3_branch OR ((admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL) AND (employee_demo.level3_branch = "" OR employee_demo.level3_branch IS NULL))');
                    //     } )
                    //     ->on(function ($j5a) {
                    //         $j5a->whereRAW('admin_orgs.level4 = employee_demo.level4 OR ((admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL) AND (employee_demo.level4 = "" OR employee_demo.level4 IS NULL))');
                    //     } );
                    // })
                    // ->where('admin_orgs.user_id', '=', Auth::id());
                    ->whereExists(function ($query) {
                        $query->select(DB::raw(1))
                                ->from('admin_org_users')
                                ->whereColumn('admin_org_users.allowed_user_id', 'users.id')
                                ->whereIn('admin_org_users.access_type', [0,1])
                                ->where('admin_org_users.granted_to_id', '=', Auth::id());
                    });
        
        $users = $sql->get();



        // Generating Output file 
        $filename = 'Active Goal Tags Per Employee.csv';

        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $columns = ["Employee ID", "Name", "Email", 
                    "Organization", "Level 1", "Level 2", "Level 3", "Level 4", 
                    ];
        if (!$request->tag || $request->tag == '[Blank]') {
            array_push($columns, 'Blank' );
        }
        foreach ($tags as $tag)                    
        {
            array_push($columns, $tag->name); 
        }

        $callback = function() use($users, $columns, $tags, $request) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($users as $user) {
                $row['Employee ID'] = $user->employee_id;
                $row['Name'] = $user->employee_name;
                $row['Email'] = $user->email;
                $row['Organization'] = $user->organization;
                $row['Level 1'] = $user->level1_program;
                $row['Level 2'] = $user->level2_division;
                $row['Level 3'] = $user->level3_branch;
                $row['Level 4'] = $user->level4;

                $row_data = array( $row['Employee ID'], $row['Name'], $row['Email'], $row['Organization'],
                                    $row['Level 1'], $row['Level 2'], $row['Level 3'], $row['Level 4'],
                                );

                if (!$request->tag || $request->tag == '[Blank]') {
                    array_push($row_data, $user->getAttribute('tag_0') );
                }

                foreach ($tags as $tag) 
                {
                    array_push($row_data, $user->getAttribute('tag_'.$tag->id) );
                }

                fputcsv($file, $row_data );

            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
        
    }

    public function conversationSummary(Request $request)
    {

        // send back the input parameters
        $this->preservedInputParams($request);

        $level0 = $request->dd_level0 ? OrganizationTree::where('id', $request->dd_level0)->first() : null;
        $level1 = $request->dd_level1 ? OrganizationTree::where('id', $request->dd_level1)->first() : null;
        $level2 = $request->dd_level2 ? OrganizationTree::where('id', $request->dd_level2)->first() : null;
        $level3 = $request->dd_level3 ? OrganizationTree::where('id', $request->dd_level3)->first() : null;
        $level4 = $request->dd_level4 ? OrganizationTree::where('id', $request->dd_level4)->first() : null;

        $request->session()->flash('level0', $level0);
        $request->session()->flash('level1', $level1);
        $request->session()->flash('level2', $level2);
        $request->session()->flash('level3', $level3);
        $request->session()->flash('level4', $level4);

        // Chart1 -- Overdue
        $sql_2 = User::selectRaw("users.employee_id, users.empl_record, employee_name, 
                                employee_demo.organization, employee_demo.level1_program, employee_demo.level2_division,
                                employee_demo.level3_branch, employee_demo.level4,
                        DATEDIFF ( users.next_conversation_date
                            , curdate() )
                    as overdue_in_days")
                ->join('employee_demo', function($join) {
                    $join->on('employee_demo.employee_id', '=', 'users.employee_id');
                })
                ->join('conversation_participants', function($join) {
                    $join->on('users.id', '=', 'conversation_participants.participant_id');
                })
                    // $join->on('employee_demo.employee_id', '=', 'users.employee_id');
                    // $join->on('employee_demo.empl_record', '=', 'users.empl_record');
                
                // ->join('employee_demo_jr as j', 'employee_demo.guid', 'j.guid' )
                // ->whereRaw("j.id = (select max(j1.id) from employee_demo_jr as j1 where j1.guid = j.guid) and (j.due_date_paused = 'N')")                
                ->where(function($query) {
                    $query->where(function($query) {
                        $query->where('users.due_date_paused', 'N')
                            ->orWhereNull('users.due_date_paused');
                    });
                })        
                ->where('conversation_participants.role', 'emp')
                ->when($level0, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.organization', $level0->name);
                })
                ->when( $level1, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level1_program', $level1->name);
                })
                ->when( $level2, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level2_division', $level2->name);
                })
                ->when( $level3, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level3_branch', $level3->name);
                })
                ->when( $level4, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level4', $level4->name);
                })
                // ->where( function($query) {
                //     $query->whereRaw('date(SYSDATE()) not between IFNULL(users.excused_start_date,"1900-01-01") and IFNULL(users.excused_end_date,"1900-01-01") ')
                //           ->where('employee_demo.employee_status', 'A');
                // })
                // ->whereExists(function ($query) {
                //     $query->select(DB::raw(1))
                //             ->from('admin_orgs')
                //             // ->whereColumn('admin_orgs.organization', 'employee_demo.organization')
                //             // ->whereColumn('admin_orgs.level1_program', 'employee_demo.level1_program')
                //             // ->whereColumn('admin_orgs.level2_division', 'employee_demo.level2_division')
                //             // ->whereColumn('admin_orgs.level3_branch',  'employee_demo.level3_branch')
                //             // ->whereColumn('admin_orgs.level4', 'employee_demo.level4')
                //             ->whereRAW('(admin_orgs.organization = employee_demo.organization OR (admin_orgs.organization = "" OR admin_orgs.organization IS NULL))')
                //             ->whereRAW('(admin_orgs.level1_program = employee_demo.level1_program OR (admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL))')
                //             ->whereRAW('(admin_orgs.level2_division = employee_demo.level2_division OR (admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL))')
                //             ->whereRAW('(admin_orgs.level3_branch = employee_demo.level3_branch OR (admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL))')
                //             ->whereRAW('(admin_orgs.level4 = employee_demo.level4 OR (admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL))')
                //             ->where('admin_orgs.user_id', '=', Auth::id() );
                // });
                // ->join('admin_orgs', function ($j1) {
                //     $j1->on(function ($j1a) {
                //         $j1a->whereRAW('admin_orgs.organization = employee_demo.organization OR ((admin_orgs.organization = "" OR admin_orgs.organization IS NULL) AND (employee_demo.organization = "" OR employee_demo.organization IS NULL))');
                //     } )
                //     ->on(function ($j2a) {
                //         $j2a->whereRAW('admin_orgs.level1_program = employee_demo.level1_program OR ((admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL) AND (employee_demo.level1_program = "" OR employee_demo.level1_program IS NULL))');
                //     } )
                //     ->on(function ($j3a) {
                //         $j3a->whereRAW('admin_orgs.level2_division = employee_demo.level2_division OR ((admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL) AND (employee_demo.level2_division = "" OR employee_demo.level2_division IS NULL))');
                //     } )
                //     ->on(function ($j4a) {
                //         $j4a->whereRAW('admin_orgs.level3_branch = employee_demo.level3_branch OR ((admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL) AND (employee_demo.level3_branch = "" OR employee_demo.level3_branch IS NULL))');
                //     } )
                //     ->on(function ($j5a) {
                //         $j5a->whereRAW('admin_orgs.level4 = employee_demo.level4 OR ((admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL) AND (employee_demo.level4 = "" OR employee_demo.level4 IS NULL))');
                //     } );
                // })
                // ->where('admin_orgs.user_id', '=', Auth::id());
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                            ->from('admin_org_users')
                            ->whereColumn('admin_org_users.allowed_user_id', 'users.id')
                            ->whereIn('admin_org_users.access_type', [0,2,3])
                            ->where('admin_org_users.granted_to_id', '=', Auth::id());
                });

        $next_due_users = $sql_2->get();
        $data = array();

        // Chart1 -- Overdue
        $data['chart1']['chart_id'] = 1;
        $data['chart1']['title'] = 'Next Conversation Due';
        $data['chart1']['legend'] = array_keys($this->overdue_groups);
        $data['chart1']['groups'] = array();
        foreach($this->overdue_groups as $key => $range)
        {
            $subset = $next_due_users->whereBetween('overdue_in_days', $range );
            array_push( $data['chart1']['groups'],  [ 'name' => $key, 'value' => $subset->count(), 
                            // 'ids' => $subset ? $subset->pluck('id')->toArray() : [] 
                        ]);
        }

        // SQL for Chart 2
        $sql = Conversation::join('users', 'users.id', 'conversations.user_id') 
        ->join('employee_demo', function($join) {
            $join->on('employee_demo.employee_id', '=', 'users.employee_id');
        })
        ->join('conversation_participants', function($join) {
            $join->on('users.id', '=', 'conversation_participants.participant_id');   
            // $join->on('employee_demo.employee_id', '=', 'users.employee_id');
            // $join->on('employee_demo.empl_record', '=', 'users.empl_record');
        })
        // ->join('employee_demo_jr as j', 'employee_demo.guid', 'j.guid')
        // ->whereRaw("j.id = (select max(j1.id) from employee_demo_jr as j1 where j1.guid = j.guid) and (j.due_date_paused = 'N')")
        ->where(function($query) {
                    $query->where(function($query) {
                        $query->where('users.due_date_paused', 'N')
                            ->orWhereNull('users.due_date_paused');
                    });
                })
        //->where('conversation_participants.role', 'emp')
        ->when($level0, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
            return $q->where('employee_demo.organization', $level0->name);
        })
        ->when( $level1, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
            return $q->where('employee_demo.level1_program', $level1->name);
        })
        ->when( $level2, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
            return $q->where('employee_demo.level2_division', $level2->name);
        })
        ->when( $level3, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
            return $q->where('employee_demo.level3_branch', $level3->name);
        })
        ->when( $level4, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
            return $q->where('employee_demo.level4', $level4->name);
        })
        // ->where(function ($query)  {
        //     return $query->whereNull('signoff_user_id')
        //                 ->orWhereNull('supervisor_signoff_id');
        // })
        ->where(function($query) {
            $query->where(function($query) {
                $query->whereNull('signoff_user_id')
                    ->orWhereNull('supervisor_signoff_id');
            // })
            // ->orWhere(function($query) {
            //     $query->whereNotNull('signoff_user_id')
            //         ->whereNotNull('supervisor_signoff_id')
            //         ->whereDate('unlock_until', '>=', Carbon::today() );
            });
        })
        ->whereNull('conversations.deleted_at')
        // ->whereRaw("DATEDIFF (
        //             COALESCE (
        //                     (select GREATEST( max(sign_off_time) , max(supervisor_signoff_time) )  
        //                         from conversations A 
        //                     where A.user_id = conversations.user_id
        //                         and signoff_user_id is not null      
        //                         and supervisor_signoff_id is not null),
        //                     (select joining_date from users where id = conversations.user_id)
        //                 ) 
        //         , DATE_ADD( DATE_FORMAT(sysdate(), '%Y-%m-%d'), INTERVAL -122 day) ) < 0 ")
        
        //->where(function($query) {
        //            $query->where(function($query) {
        //                $query->whereRaw("DATEDIFF ( users.next_conversation_date, curdate() ) > 0 ")
        //                    ->orWhereNull('users.next_conversation_date');
        //            });
        //        })        
                
        // ->whereExists(function ($query) {
        //     $query->select(DB::raw(1))
        //                 ->from('admin_orgs')
        //                 // ->whereColumn('admin_orgs.organization', 'employee_demo.organization')
        //                 // ->whereColumn('admin_orgs.level1_program', 'employee_demo.level1_program')
        //                 // ->whereColumn('admin_orgs.level2_division', 'employee_demo.level2_division')
        //                 // ->whereColumn('admin_orgs.level3_branch',  'employee_demo.level3_branch')
        //                 // ->whereColumn('admin_orgs.level4', 'employee_demo.level4')
        //                 ->whereRAW('(admin_orgs.organization = employee_demo.organization OR (admin_orgs.organization = "" OR admin_orgs.organization IS NULL))')
        //                 ->whereRAW('(admin_orgs.level1_program = employee_demo.level1_program OR (admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL))')
        //                 ->whereRAW('(admin_orgs.level2_division = employee_demo.level2_division OR (admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL))')
        //                 ->whereRAW('(admin_orgs.level3_branch = employee_demo.level3_branch OR (admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL))')
        //                 ->whereRAW('(admin_orgs.level4 = employee_demo.level4 OR (admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL))')
        //                 ->where('admin_orgs.user_id', '=', Auth::id() );
        // });
        // ->join('admin_orgs', function ($j1) {
        //     $j1->on(function ($j1a) {
        //         $j1a->whereRAW('admin_orgs.organization = employee_demo.organization OR ((admin_orgs.organization = "" OR admin_orgs.organization IS NULL) AND (employee_demo.organization = "" OR employee_demo.organization IS NULL))');
        //     } )
        //     ->on(function ($j2a) {
        //         $j2a->whereRAW('admin_orgs.level1_program = employee_demo.level1_program OR ((admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL) AND (employee_demo.level1_program = "" OR employee_demo.level1_program IS NULL))');
        //     } )
        //     ->on(function ($j3a) {
        //         $j3a->whereRAW('admin_orgs.level2_division = employee_demo.level2_division OR ((admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL) AND (employee_demo.level2_division = "" OR employee_demo.level2_division IS NULL))');
        //     } )
        //     ->on(function ($j4a) {
        //         $j4a->whereRAW('admin_orgs.level3_branch = employee_demo.level3_branch OR ((admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL) AND (employee_demo.level3_branch = "" OR employee_demo.level3_branch IS NULL))');
        //     } )
        //     ->on(function ($j5a) {
        //         $j5a->whereRAW('admin_orgs.level4 = employee_demo.level4 OR ((admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL) AND (employee_demo.level4 = "" OR employee_demo.level4 IS NULL))');
        //     } );
        // })
        // ->where('admin_orgs.user_id', '=', Auth::id());
        // ->where( function($query) {
        //     $query->whereRaw('date(SYSDATE()) not between IFNULL(users.excused_start_date,"1900-01-01") and IFNULL(users.excused_end_date,"1900-01-01") ')
        //           ->where('employee_demo.employee_status', 'A');
        // })
        ->whereExists(function ($query) {
            $query->select(DB::raw(1))
                    ->from('admin_org_users')
                    ->whereColumn('admin_org_users.allowed_user_id', 'users.id')
                    ->whereIn('admin_org_users.access_type', [0,2,3])
                    ->where('admin_org_users.granted_to_id', '=', Auth::id());
        });
        
        $conversations = $sql->get();
        
        Log::warning('HR Chart 2');
        Log::warning(print_r($sql->toSql(),true));
        Log::warning(print_r($sql->getBindings(),true));
        

        // Chart2 -- Open Conversation
        $topics = ConversationTopic::select('id','name')->get();
        $data['chart2']['chart_id'] = 2;
        $data['chart2']['title'] = 'Topic: Open Conversations';
        $data['chart2']['legend'] = $topics->pluck('name')->toArray();
        $data['chart2']['groups'] = array();

        $open_conversations = $conversations;        
        
        $total_unique_emp = 0;
        foreach($topics as $topic)
        {
            $subset = $open_conversations->where('conversation_topic_id', $topic->id );
            $unique_emp = $subset->unique('participant_id')->count();            
            $total_unique_emp = $total_unique_emp + $unique_emp;
        } 
        
        
        foreach($topics as $topic)
        {
            $subset = $open_conversations->where('conversation_topic_id', $topic->id );
            $unique_emp = $subset->unique('participant_id')->count();
            $per_emp = 0;
            if($total_unique_emp > 0) {
                $per_emp = ($unique_emp / $total_unique_emp) * 100;
            }
            array_push( $data['chart2']['groups'],  [ 'name' => $topic->name, 'value' => $subset->count(),
                        'topic_id' => $topic->id, 'unique_emp'=>$unique_emp, 'total_unique_emp'=>$total_unique_emp, 'per_emp'=>$per_emp,
                            // 'ids' => $subset ? $subset->pluck('id')->toArray() : []
                        ]);
        }    

        // Chart 3 -- Completed Conversation by Topics
        $data['chart3']['chart_id'] = 3;
        $data['chart3']['title'] = 'Topic: Completed Conversations';
        $data['chart3']['legend'] = $topics->pluck('name')->toArray();
        $data['chart3']['groups'] = array();

        // SQL for Chart 3
        $completed_conversations = Conversation::join('users', 'users.id', 'conversations.user_id') 
        ->join('employee_demo', function($join) {
            $join->on('employee_demo.employee_id', '=', 'users.employee_id');
        })
        ->join('conversation_participants', function($join) {
            $join->on('users.id', '=', 'conversation_participants.participant_id');
            // $join->on('employee_demo.employee_id', '=', 'users.employee_id');
            // $join->on('employee_demo.empl_record', '=', 'users.empl_record');
        })
        // ->where(function ($query)  {
        //     return $query->whereNotNull('signoff_user_id')
        //                  ->whereNotNull('supervisor_signoff_id');
        // })
        ->where(function($query) {
            $query->where(function($query) {
                $query->whereNotNull('signoff_user_id')
                      ->whereNotNull('supervisor_signoff_id');                          
            // })
            // ->orWhere(function($query) {
            //     $query->whereNotNull('signoff_user_id')
            //           ->whereNotNull('supervisor_signoff_id')
            //           ->whereDate('unlock_until', '<', Carbon::today() );
            });
        })
        ->whereNull('conversations.deleted_at')   
        // ->join('employee_demo_jr as j', 'employee_demo.guid', 'j.guid')
        // ->whereRaw("j.id = (select max(j1.id) from employee_demo_jr as j1 where j1.guid = j.guid) and (j.due_date_paused = 'N') ")
        ->where(function($query) {
                    $query->where(function($query) {
                        $query->where('users.due_date_paused', 'N')
                            ->orWhereNull('users.due_date_paused');
                    });
                })
        //->where('conversation_participants.role', 'emp')     
        ->when($level0, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
            return $q->where('employee_demo.organization', $level0->name);
        })
        ->when( $level1, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
            return $q->where('employee_demo.level1_program', $level1->name);
        })
        ->when( $level2, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
            return $q->where('employee_demo.level2_division', $level2->name);
        })
        ->when( $level3, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
            return $q->where('employee_demo.level3_branch', $level3->name);
        })
        ->when( $level4, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
            return $q->where('employee_demo.level4', $level4->name);
        })
        // ->whereExists(function ($query) {
        //     $query->select(DB::raw(1))
        //             ->from('admin_orgs')
        //             // ->whereColumn('admin_orgs.organization', 'employee_demo.organization')
        //             // ->whereColumn('admin_orgs.level1_program', 'employee_demo.level1_program')
        //             // ->whereColumn('admin_orgs.level2_division', 'employee_demo.level2_division')
        //             // ->whereColumn('admin_orgs.level3_branch',  'employee_demo.level3_branch')
        //             // ->whereColumn('admin_orgs.level4', 'employee_demo.level4')
        //             ->whereRAW('(admin_orgs.organization = employee_demo.organization OR (admin_orgs.organization = "" OR admin_orgs.organization IS NULL))')
        //             ->whereRAW('(admin_orgs.level1_program = employee_demo.level1_program OR (admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL))')
        //             ->whereRAW('(admin_orgs.level2_division = employee_demo.level2_division OR (admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL))')
        //             ->whereRAW('(admin_orgs.level3_branch = employee_demo.level3_branch OR (admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL))')
        //             ->whereRAW('(admin_orgs.level4 = employee_demo.level4 OR (admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL))')
        //             ->where('admin_orgs.user_id', '=', Auth::id() );
        // })
        // ->join('admin_orgs', function ($j1) {
        //     $j1->on(function ($j1a) {
        //         $j1a->whereRAW('admin_orgs.organization = employee_demo.organization OR ((admin_orgs.organization = "" OR admin_orgs.organization IS NULL) AND (employee_demo.organization = "" OR employee_demo.organization IS NULL))');
        //     } )
        //     ->on(function ($j2a) {
        //         $j2a->whereRAW('admin_orgs.level1_program = employee_demo.level1_program OR ((admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL) AND (employee_demo.level1_program = "" OR employee_demo.level1_program IS NULL))');
        //     } )
        //     ->on(function ($j3a) {
        //         $j3a->whereRAW('admin_orgs.level2_division = employee_demo.level2_division OR ((admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL) AND (employee_demo.level2_division = "" OR employee_demo.level2_division IS NULL))');
        //     } )
        //     ->on(function ($j4a) {
        //         $j4a->whereRAW('admin_orgs.level3_branch = employee_demo.level3_branch OR ((admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL) AND (employee_demo.level3_branch = "" OR employee_demo.level3_branch IS NULL))');
        //     } )
        //     ->on(function ($j5a) {
        //         $j5a->whereRAW('admin_orgs.level4 = employee_demo.level4 OR ((admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL) AND (employee_demo.level4 = "" OR employee_demo.level4 IS NULL))');
        //     } );
        // })
        // ->where('admin_orgs.user_id', '=', Auth::id())
        ->whereExists(function ($query) {
            $query->select(DB::raw(1))
                    ->from('admin_org_users')
                    ->whereColumn('admin_org_users.allowed_user_id', 'users.id')
                    ->whereIn('admin_org_users.access_type', [0,2,3])
                    ->where('admin_org_users.granted_to_id', '=', Auth::id());
        })
        // ->where( function($query) {
        //     $query->whereRaw('date(SYSDATE()) not between IFNULL(users.excused_start_date,"1900-01-01") and IFNULL(users.excused_end_date,"1900-01-01") ')
        //           ->where('employee_demo.employee_status', 'A');
        // })
        ->get();
        
        $total_unique_emp = 0;
        foreach($topics as $topic)
        {
            $subset = $completed_conversations->where('conversation_topic_id', $topic->id );
            $unique_emp = $subset->unique('participant_id')->count();            
            $total_unique_emp = $total_unique_emp + $unique_emp;
        } 
        

        foreach($topics as $topic)
        {
            $subset = $completed_conversations->where('conversation_topic_id', $topic->id );
            $unique_emp = $subset->unique('participant_id')->count();
            $per_emp = 0;
            if($total_unique_emp > 0) {
                $per_emp = ($unique_emp / $total_unique_emp) * 100;
            }
            
            array_push( $data['chart3']['groups'],  [ 'name' => $topic->name, 'value' => $subset->count(), 
                    'topic_id' => $topic->id, 'unique_emp'=>$unique_emp, 'total_unique_emp'=>$total_unique_emp, 'per_emp'=>$per_emp,
                    // 'ids' => $subset ? $subset->pluck('id')->toArray() : []
                ]);
        }    
        
        
        // SQL for Chart 4
        $sql = ConversationParticipant::join('users', 'users.id', 'conversation_participants.participant_id') 
        ->join('employee_demo', function($join) {
            $join->on('employee_demo.employee_id', '=', 'users.employee_id');
        })
        ->join('conversations', function($join) {
            $join->on('conversations.id', '=', 'conversation_participants.conversation_id');  
        })
        ->where(function($query) {
                    $query->where(function($query) {
                        $query->where('users.due_date_paused', 'N')
                            ->orWhereNull('users.due_date_paused');
                    });
                })
        ->where('conversation_participants.role', 'emp')
        ->when($level0, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
            return $q->where('employee_demo.organization', $level0->name);
        })
        ->when( $level1, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
            return $q->where('employee_demo.level1_program', $level1->name);
        })
        ->when( $level2, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
            return $q->where('employee_demo.level2_division', $level2->name);
        })
        ->when( $level3, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
            return $q->where('employee_demo.level3_branch', $level3->name);
        })
        ->when( $level4, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
            return $q->where('employee_demo.level4', $level4->name);
        })
        ->where(function($query) {
            $query->where(function($query) {
                $query->whereNull('signoff_user_id')
                    ->orWhereNull('supervisor_signoff_id');
            });
        })
        ->whereNull('conversations.deleted_at')
        //->where(function($query) {
        //            $query->where(function($query) {
        //                $query->whereRaw("DATEDIFF ( users.next_conversation_date, curdate() ) > 0 ")
        //                    ->orWhereNull('users.next_conversation_date');
        //            });
        //        })   
        ->whereExists(function ($query) {
            $query->select(DB::raw(1))
                    ->from('admin_org_users')
                    ->whereColumn('admin_org_users.allowed_user_id', 'conversations.user_id')
                    ->whereIn('admin_org_users.access_type', [0,2,3])
                    ->where('admin_org_users.granted_to_id', '=', Auth::id());
        });
        
        $emp_conversations = $sql->get();
        
        Log::warning('HR Chart 4');
        Log::warning(print_r($sql->toSql(),true));
        Log::warning(print_r($sql->getBindings(),true));
                
        // Chart4 -- Open Conversation for employees
        $topics = ConversationTopic::select('id','name')->get();
        $data['chart4']['chart_id'] = 4;
        $data['chart4']['title'] = 'Employees: Open Conversations';
        $data['chart4']['legend'] = $topics->pluck('name')->toArray();
        $data['chart4']['groups'] = array();        
        
        $total_unique_emp = 0;
        foreach($topics as $topic)
        {
            $subset = $emp_conversations->where('conversation_topic_id', $topic->id );
            $unique_emp = $subset->unique('participant_id')->count();            
            $total_unique_emp = $total_unique_emp + $unique_emp;
        } 
        
        
        foreach($topics as $topic)
        {
            $subset = $emp_conversations->where('conversation_topic_id', $topic->id );
            $unique_emp = $subset->unique('participant_id')->count();
            $per_emp = 0;
            if($total_unique_emp > 0) {
                $per_emp = ($unique_emp / $total_unique_emp) * 100;
            }
            array_push( $data['chart4']['groups'],  [ 'name' => $topic->name, 'value' => $unique_emp,
                        'topic_id' => $topic->id,
                            // 'ids' => $subset ? $subset->pluck('id')->toArray() : []
                        ]);
        } 
        
        // Chart 5 -- Completed Conversation by Employees
        $data['chart5']['chart_id'] = 5;
        $data['chart5']['title'] = 'Employees: Completed Conversations';
        $data['chart5']['legend'] = $topics->pluck('name')->toArray();
        $data['chart5']['groups'] = array();

        // SQL for Chart 5
        $completed_conversations = ConversationParticipant::join('users', 'users.id', 'conversation_participants.participant_id') 
        ->join('employee_demo', function($join) {
            $join->on('employee_demo.employee_id', '=', 'users.employee_id');
        })
        ->join('conversations', function($join) {
            $join->on('conversations.id', '=', 'conversation_participants.conversation_id');
            // $join->on('employee_demo.employee_id', '=', 'users.employee_id');
            // $join->on('employee_demo.empl_record', '=', 'users.empl_record');
        })
        // ->where(function ($query)  {
        //     return $query->whereNotNull('signoff_user_id')
        //                  ->whereNotNull('supervisor_signoff_id');
        // })
        ->where(function($query) {
            $query->where(function($query) {
                $query->whereNotNull('signoff_user_id')
                      ->whereNotNull('supervisor_signoff_id');                          
            // })
            // ->orWhere(function($query) {
            //     $query->whereNotNull('signoff_user_id')
            //           ->whereNotNull('supervisor_signoff_id')
            //           ->whereDate('unlock_until', '<', Carbon::today() );
            });
        })
        ->whereNull('conversations.deleted_at')   
        // ->join('employee_demo_jr as j', 'employee_demo.guid', 'j.guid')
        // ->whereRaw("j.id = (select max(j1.id) from employee_demo_jr as j1 where j1.guid = j.guid) and (j.due_date_paused = 'N') ")
        ->where(function($query) {
                    $query->where(function($query) {
                        $query->where('users.due_date_paused', 'N')
                            ->orWhereNull('users.due_date_paused');
                    });
                })
        ->where('conversation_participants.role', 'emp')     
        ->when($level0, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
            return $q->where('employee_demo.organization', $level0->name);
        })
        ->when( $level1, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
            return $q->where('employee_demo.level1_program', $level1->name);
        })
        ->when( $level2, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
            return $q->where('employee_demo.level2_division', $level2->name);
        })
        ->when( $level3, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
            return $q->where('employee_demo.level3_branch', $level3->name);
        })
        ->when( $level4, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
            return $q->where('employee_demo.level4', $level4->name);
        })
        ->whereExists(function ($query) {
            $query->select(DB::raw(1))
                    ->from('admin_org_users')
                    ->whereColumn('admin_org_users.allowed_user_id', 'conversations.user_id')
                    ->whereIn('admin_org_users.access_type', [0,2,3])
                    ->where('admin_org_users.granted_to_id', '=', Auth::id());
        })
        // ->where( function($query) {
        //     $query->whereRaw('date(SYSDATE()) not between IFNULL(users.excused_start_date,"1900-01-01") and IFNULL(users.excused_end_date,"1900-01-01") ')
        //           ->where('employee_demo.employee_status', 'A');
        // })
        ->get();
        
        $total_unique_emp = 0;
        foreach($topics as $topic)
        {
            $subset = $completed_conversations->where('conversation_topic_id', $topic->id );
            $unique_emp = $subset->unique('participant_id')->count();            
            $total_unique_emp = $total_unique_emp + $unique_emp;
        } 
        

        foreach($topics as $topic)
        {
            $subset = $completed_conversations->where('conversation_topic_id', $topic->id );
            $unique_emp = $subset->unique('participant_id')->count();
            $per_emp = 0;
            if($total_unique_emp > 0) {
                $per_emp = ($unique_emp / $total_unique_emp) * 100;
            }
            
            array_push( $data['chart5']['groups'],  [ 'name' => $topic->name, 'value' => $unique_emp, 
                    'topic_id' => $topic->id, 
                    // 'ids' => $subset ? $subset->pluck('id')->toArray() : []
                ]);
        }
        

        return view('hradmin.statistics.conversationsummary',compact('data'));

    }


    public function conversationSummaryExport(Request $request)
    {
        $level0 = $request->dd_level0 ? OrganizationTree::where('id', $request->dd_level0)->first() : null;
        $level1 = $request->dd_level1 ? OrganizationTree::where('id', $request->dd_level1)->first() : null;
        $level2 = $request->dd_level2 ? OrganizationTree::where('id', $request->dd_level2)->first() : null;
        $level3 = $request->dd_level3 ? OrganizationTree::where('id', $request->dd_level3)->first() : null;
        $level4 = $request->dd_level4 ? OrganizationTree::where('id', $request->dd_level4)->first() : null;

        // SQL - Chart 1
        $sql_chart1 = User::selectRaw("users.*, employee_demo.employee_name, 
                        employee_demo.organization, employee_demo.level1_program, employee_demo.level2_division, employee_demo.level3_branch, employee_demo.level4,
                    DATEDIFF ( users.next_conversation_date, curdate() )  as overdue_in_days,
                    users.next_conversation_date as next_due_date")
                ->join('employee_demo', function($join) {
                    $join->on('employee_demo.employee_id', '=', 'users.employee_id');
                })
                ->join('conversation_participants', function($join) {
                    $join->on('users.id', '=', 'conversation_participants.participant_id');
                })
                    // $join->on('employee_demo.employee_id', '=', 'users.employee_id');
                    // $join->on('employee_demo.empl_record', '=', 'users.empl_record');
                
                // ->join('employee_demo_jr as j', 'employee_demo.guid', 'j.guid')
                // ->whereRaw("j.id = (select max(j1.id) from employee_demo_jr as j1 where j1.guid = j.guid) and (j.due_date_paused = 'N')")
                ->where(function($query) {
                    $query->where(function($query) {
                        $query->where('users.due_date_paused', 'N')
                            ->orWhereNull('users.due_date_paused');
                    });
                })
                ->where('conversation_participants.role', 'emp')
                ->when($level0, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.organization', $level0->name);
                })
                ->when( $level1, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level1_program', $level1->name);
                })
                ->when( $level2, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level2_division', $level2->name);
                })
                ->when( $level3, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level3_branch', $level3->name);
                })
                ->when( $level4, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level4', $level4->name);
                })
                // ->where( function($query) {
                //     $query->whereRaw('date(SYSDATE()) not between IFNULL(users.excused_start_date,"1900-01-01") and IFNULL(users.excused_end_date,"1900-01-01") ')
                //           ->where('employee_demo.employee_status', 'A');
                // })
                // ->whereExists(function ($query) {
                //     $query->select(DB::raw(1))
                //             ->from('admin_orgs')
                //             // ->whereColumn('admin_orgs.organization', 'employee_demo.organization')
                //             // ->whereColumn('admin_orgs.level1_program', 'employee_demo.level1_program')
                //             // ->whereColumn('admin_orgs.level2_division', 'employee_demo.level2_division')
                //             // ->whereColumn('admin_orgs.level3_branch',  'employee_demo.level3_branch')
                //             // ->whereColumn('admin_orgs.level4', 'employee_demo.level4')
                //             ->whereRAW('(admin_orgs.organization = employee_demo.organization OR (admin_orgs.organization = "" OR admin_orgs.organization IS NULL))')
                //             ->whereRAW('(admin_orgs.level1_program = employee_demo.level1_program OR (admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL))')
                //             ->whereRAW('(admin_orgs.level2_division = employee_demo.level2_division OR (admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL))')
                //             ->whereRAW('(admin_orgs.level3_branch = employee_demo.level3_branch OR (admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL))')
                //             ->whereRAW('(admin_orgs.level4 = employee_demo.level4 OR (admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL))')
                //             ->where('admin_orgs.user_id', '=', Auth::id() );
                // });
                // ->join('admin_orgs', function ($j1) {
                //     $j1->on(function ($j1a) {
                //         $j1a->whereRAW('admin_orgs.organization = employee_demo.organization OR ((admin_orgs.organization = "" OR admin_orgs.organization IS NULL) AND (employee_demo.organization = "" OR employee_demo.organization IS NULL))');
                //     } )
                //     ->on(function ($j2a) {
                //         $j2a->whereRAW('admin_orgs.level1_program = employee_demo.level1_program OR ((admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL) AND (employee_demo.level1_program = "" OR employee_demo.level1_program IS NULL))');
                //     } )
                //     ->on(function ($j3a) {
                //         $j3a->whereRAW('admin_orgs.level2_division = employee_demo.level2_division OR ((admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL) AND (employee_demo.level2_division = "" OR employee_demo.level2_division IS NULL))');
                //     } )
                //     ->on(function ($j4a) {
                //         $j4a->whereRAW('admin_orgs.level3_branch = employee_demo.level3_branch OR ((admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL) AND (employee_demo.level3_branch = "" OR employee_demo.level3_branch IS NULL))');
                //     } )
                //     ->on(function ($j5a) {
                //         $j5a->whereRAW('admin_orgs.level4 = employee_demo.level4 OR ((admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL) AND (employee_demo.level4 = "" OR employee_demo.level4 IS NULL))');
                //     } );
                // })
                // ->where('admin_orgs.user_id', '=', Auth::id());
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                            ->from('admin_org_users')
                            ->whereColumn('admin_org_users.allowed_user_id', 'users.id')
                            ->whereIn('admin_org_users.access_type', [0,2])
                            ->where('admin_org_users.granted_to_id', '=', Auth::id());
                });
               // Log::info($sql_chart1->tosql());
                
        // SQL - Chart 2
        $sql_chart2 = Conversation::selectRaw("conversations.*, users.employee_id, employee_demo.employee_name, users.email,
                        employee_demo.organization, employee_demo.level1_program, employee_demo.level2_division, employee_demo.level3_branch, employee_demo.level4,
                        users.next_conversation_date as next_due_date")
                // ->whereIn('id', $selected_ids)
                // ->whereRaw("DATEDIFF (
                //     COALESCE (
                //             (select GREATEST( max(sign_off_time) , max(supervisor_signoff_time) )  
                //                 from conversations A 
                //             where A.user_id = conversations.user_id
                //                 and signoff_user_id is not null      
                //                 and supervisor_signoff_id is not null),
                //             (select joining_date from users where id = conversations.user_id)
                //         ) 
                // , DATE_ADD( DATE_FORMAT(sysdate(), '%Y-%m-%d'), INTERVAL -122 day) ) < 0 ")
                //->where(function($query) {
                //    $query->where(function($query) {
                //        $query->whereRaw("DATEDIFF ( users.next_conversation_date, curdate() ) > 0 ")
                //            ->orWhereNull('users.next_conversation_date');
                //    });
                //})   
                // ->where(function ($query)  {
                //     return $query->whereNull('signoff_user_id')
                //                  ->orwhereNull('supervisor_signoff_id');
                // })
                ->where(function($query) {
                    $query->where(function($query) {
                        $query->whereNull('signoff_user_id')
                            ->orWhereNull('supervisor_signoff_id');
                    // })
                    // ->orWhere(function($query) {
                    //     $query->whereNotNull('signoff_user_id')
                    //         ->whereNotNull('supervisor_signoff_id')
                    //         ->whereDate('unlock_until', '>=', Carbon::today() );
                    });
                })
                ->whereNull('deleted_at')                
                ->join('users', 'users.id', 'conversations.user_id') 
                ->join('conversation_participants','users.id','conversation_participants.participant_id')
                ->join('employee_demo', function($join) {
                    $join->on('employee_demo.employee_id', '=', 'users.employee_id');
                    // $join->on('employee_demo.employee_id', '=', 'users.employee_id');
                    // $join->on('employee_demo.empl_record', '=', 'users.empl_record');
                })
                // ->join('employee_demo_jr as j', 'employee_demo.guid', 'j.guid')
                // ->whereRaw("j.id = (select max(j1.id) from employee_demo_jr as j1 where j1.guid = j.guid) and (j.due_date_paused = 'N')")
                ->where(function($query) {
                    $query->where(function($query) {
                        $query->where('users.due_date_paused', 'N')
                            ->orWhereNull('users.due_date_paused');
                    });
                })
                //->where('conversation_participants.role','emp')
                ->when($level0, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.organization', $level0->name);
                })
                ->when( $level1, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level1_program', $level1->name);
                })
                ->when( $level2, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level2_division', $level2->name);
                })
                ->when( $level3, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level3_branch', $level3->name);
                })
                ->when( $level4, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level4', $level4->name);
                })
                ->when( $request->topic_id, function($q) use($request) {
                    $q->where('conversations.conversation_topic_id', $request->topic_id);
                })
                // ->where( function($query) {
                //     $query->whereRaw('date(SYSDATE()) not between IFNULL(users.excused_start_date,"1900-01-01") and IFNULL(users.excused_end_date,"1900-01-01") ')
                //           ->where('employee_demo.employee_status', 'A');
                // })
                // ->whereExists(function ($query) {
                //     $query->select(DB::raw(1))
                //             ->from('admin_orgs')
                //             // ->whereColumn('admin_orgs.organization', 'employee_demo.organization')
                //             // ->whereColumn('admin_orgs.level1_program', 'employee_demo.level1_program')
                //             // ->whereColumn('admin_orgs.level2_division', 'employee_demo.level2_division')
                //             // ->whereColumn('admin_orgs.level3_branch',  'employee_demo.level3_branch')
                //             // ->whereColumn('admin_orgs.level4', 'employee_demo.level4')
                //             ->whereRAW('(admin_orgs.organization = employee_demo.organization OR (admin_orgs.organization = "" OR admin_orgs.organization IS NULL))')
                //             ->whereRAW('(admin_orgs.level1_program = employee_demo.level1_program OR (admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL))')
                //             ->whereRAW('(admin_orgs.level2_division = employee_demo.level2_division OR (admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL))')
                //             ->whereRAW('(admin_orgs.level3_branch = employee_demo.level3_branch OR (admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL))')
                //             ->whereRAW('(admin_orgs.level4 = employee_demo.level4 OR (admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL))')
                //             ->where('admin_orgs.user_id', '=', Auth::id() );
                // }) 
                // ->join('admin_orgs', function ($j1) {
                //     $j1->on(function ($j1a) {
                //         $j1a->whereRAW('admin_orgs.organization = employee_demo.organization OR ((admin_orgs.organization = "" OR admin_orgs.organization IS NULL) AND (employee_demo.organization = "" OR employee_demo.organization IS NULL))');
                //     } )
                //     ->on(function ($j2a) {
                //         $j2a->whereRAW('admin_orgs.level1_program = employee_demo.level1_program OR ((admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL) AND (employee_demo.level1_program = "" OR employee_demo.level1_program IS NULL))');
                //     } )
                //     ->on(function ($j3a) {
                //         $j3a->whereRAW('admin_orgs.level2_division = employee_demo.level2_division OR ((admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL) AND (employee_demo.level2_division = "" OR employee_demo.level2_division IS NULL))');
                //     } )
                //     ->on(function ($j4a) {
                //         $j4a->whereRAW('admin_orgs.level3_branch = employee_demo.level3_branch OR ((admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL) AND (employee_demo.level3_branch = "" OR employee_demo.level3_branch IS NULL))');
                //     } )
                //     ->on(function ($j5a) {
                //         $j5a->whereRAW('admin_orgs.level4 = employee_demo.level4 OR ((admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL) AND (employee_demo.level4 = "" OR employee_demo.level4 IS NULL))');
                //     } );
                // })
                // ->where('admin_orgs.user_id', '=', Auth::id())
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                            ->from('admin_org_users')
                            ->whereColumn('admin_org_users.allowed_user_id', 'users.id')
                            ->whereIn('admin_org_users.access_type', [0,2,3])
                            ->where('admin_org_users.granted_to_id', '=', Auth::id());
                })
                ->with('topic:id,name')
                ->with('signoff_user:id,name')
                ->with('signoff_supervisor:id,name');

         // SQL for Chart 3
         $sql_chart3 = Conversation::selectRaw("conversations.*, users.employee_id, employee_demo.employee_name, users.email,
                    employee_demo.organization, employee_demo.level1_program, employee_demo.level2_division, employee_demo.level3_branch, employee_demo.level4,
                    users.next_conversation_date as next_due_date")
            ->join('users', 'users.id', 'conversations.user_id') 
            ->join('conversation_participants','users.id','conversation_participants.participant_id')
            ->join('employee_demo', function($join) {
                $join->on('employee_demo.employee_id', '=', 'users.employee_id');
                // $join->on('employee_demo.employee_id', '=', 'users.employee_id');
                // $join->on('employee_demo.empl_record', '=', 'users.empl_record');
            })
            // ->join('employee_demo_jr as j', 'employee_demo.guid', 'j.guid')
            // ->whereRaw("j.id = (select max(j1.id) from employee_demo_jr as j1 where j1.guid = j.guid) and (j.due_date_paused = 'N')")
            ->where(function($query) {
                    $query->where(function($query) {
                        $query->where('users.due_date_paused', 'N')
                            ->orWhereNull('users.due_date_paused');
                    });
                })

            //->where('conversation_participants.role','emp')       
            ->when($level0, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                return $q->where('employee_demo.organization', $level0->name);
            })
            ->when( $level1, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                return $q->where('employee_demo.level1_program', $level1->name);
            })
            ->when( $level2, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                return $q->where('employee_demo.level2_division', $level2->name);
            })
            ->when( $level3, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                return $q->where('employee_demo.level3_branch', $level3->name);
            })
            ->when( $level4, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                return $q->where('employee_demo.level4', $level4->name);
            })
            // ->where(function ($query)  {
            //     return $query->whereNotNull('signoff_user_id')
            //                  ->whereNotNull('supervisor_signoff_id');
            // })
            ->where(function($query) {
                $query->where(function($query) {
                    $query->whereNotNull('signoff_user_id')
                          ->whereNotNull('supervisor_signoff_id');
                // })
                // ->orWhere(function($query) {
                //     $query->whereNotNull('signoff_user_id')
                //           ->whereNotNull('supervisor_signoff_id')
                //           ->whereDate('unlock_until', '<', Carbon::today() );
                });
            })
            ->whereNull('deleted_at')              
            ->when( $request->topic_id, function($q) use($request) {
                $q->where('conversations.conversation_topic_id', $request->topic_id);
            })
            // ->whereExists(function ($query) {
            //     $query->select(DB::raw(1))
            //             ->from('admin_orgs')
            //             // ->whereColumn('admin_orgs.organization', 'employee_demo.organization')
            //             // ->whereColumn('admin_orgs.level1_program', 'employee_demo.level1_program')
            //             // ->whereColumn('admin_orgs.level2_division', 'employee_demo.level2_division')
            //             // ->whereColumn('admin_orgs.level3_branch',  'employee_demo.level3_branch')
            //             // ->whereColumn('admin_orgs.level4', 'employee_demo.level4')
            //             ->whereRAW('(admin_orgs.organization = employee_demo.organization OR (admin_orgs.organization = "" OR admin_orgs.organization IS NULL))')
            //             ->whereRAW('(admin_orgs.level1_program = employee_demo.level1_program OR (admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL))')
            //             ->whereRAW('(admin_orgs.level2_division = employee_demo.level2_division OR (admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL))')
            //             ->whereRAW('(admin_orgs.level3_branch = employee_demo.level3_branch OR (admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL))')
            //             ->whereRAW('(admin_orgs.level4 = employee_demo.level4 OR (admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL))')
            //             ->where('admin_orgs.user_id', '=', Auth::id() );
            // }) 
            // ->join('admin_orgs', function ($j1) {
            //     $j1->on(function ($j1a) {
            //         $j1a->whereRAW('admin_orgs.organization = employee_demo.organization OR ((admin_orgs.organization = "" OR admin_orgs.organization IS NULL) AND (employee_demo.organization = "" OR employee_demo.organization IS NULL))');
            //     } )
            //     ->on(function ($j2a) {
            //         $j2a->whereRAW('admin_orgs.level1_program = employee_demo.level1_program OR ((admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL) AND (employee_demo.level1_program = "" OR employee_demo.level1_program IS NULL))');
            //     } )
            //     ->on(function ($j3a) {
            //         $j3a->whereRAW('admin_orgs.level2_division = employee_demo.level2_division OR ((admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL) AND (employee_demo.level2_division = "" OR employee_demo.level2_division IS NULL))');
            //     } )
            //     ->on(function ($j4a) {
            //         $j4a->whereRAW('admin_orgs.level3_branch = employee_demo.level3_branch OR ((admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL) AND (employee_demo.level3_branch = "" OR employee_demo.level3_branch IS NULL))');
            //     } )
            //     ->on(function ($j5a) {
            //         $j5a->whereRAW('admin_orgs.level4 = employee_demo.level4 OR ((admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL) AND (employee_demo.level4 = "" OR employee_demo.level4 IS NULL))');
            //     } );
            // })
            // ->where('admin_orgs.user_id', '=', Auth::id())
            // ->where( function($query) {
            //     $query->whereRaw('date(SYSDATE()) not between IFNULL(users.excused_start_date,"1900-01-01") and IFNULL(users.excused_end_date,"1900-01-01") ')
            //           ->where('employee_demo.employee_status', 'A');
            // })
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                        ->from('admin_org_users')
                        ->whereColumn('admin_org_users.allowed_user_id', 'users.id')
                        ->whereIn('admin_org_users.access_type', [0,2,3])
                        ->where('admin_org_users.granted_to_id', '=', Auth::id());
            })
            ->with('topic:id,name')
            ->with('signoff_user:id,name')
            ->with('signoff_supervisor:id,name')
            ;
            
            
        // SQL - Chart 4
        $sql_chart4 = ConversationParticipant::selectRaw("conversations.*, conversation_topics.name as conversation_name, users.employee_id, employee_demo.employee_name, users.email,
                        employee_demo.organization, employee_demo.level1_program, employee_demo.level2_division, employee_demo.level3_branch, employee_demo.level4,
                        users.next_conversation_date as next_due_date")
                //->where(function($query) {
                //    $query->where(function($query) {
                //        $query->whereRaw("DATEDIFF ( users.next_conversation_date, curdate() ) > 0 ")
                //            ->orWhereNull('users.next_conversation_date');
                //    });
                //})   
                ->where(function($query) {
                    $query->where(function($query) {
                        $query->whereNull('signoff_user_id')
                            ->orWhereNull('supervisor_signoff_id');
                    });
                })
                ->whereNull('deleted_at')                
                ->join('users', 'users.id', 'conversation_participants.participant_id') 
                ->join('conversations','conversations.id','conversation_participants.conversation_id')
                ->join('conversation_topics','conversations.conversation_topic_id','conversation_topics.id')            
                ->join('employee_demo', function($join) {
                    $join->on('employee_demo.employee_id', '=', 'users.employee_id');
                })
                ->where(function($query) {
                    $query->where(function($query) {
                        $query->where('users.due_date_paused', 'N')
                            ->orWhereNull('users.due_date_paused');
                    });
                })

                ->where('conversation_participants.role','emp')
                ->when($level0, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.organization', $level0->name);
                })
                ->when( $level1, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level1_program', $level1->name);
                })
                ->when( $level2, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level2_division', $level2->name);
                })
                ->when( $level3, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level3_branch', $level3->name);
                })
                ->when( $level4, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level4', $level4->name);
                })
                ->when( $request->topic_id, function($q) use($request) {
                    $q->where('conversations.conversation_topic_id', $request->topic_id);
                })
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                            ->from('admin_org_users')
                            ->whereColumn('admin_org_users.allowed_user_id', 'conversations.user_id')
                            ->whereIn('admin_org_users.access_type', [0,2,3])
                            ->where('admin_org_users.granted_to_id', '=', Auth::id());
                });
                //->with('topic:id,name')
                //->with('signoff_user:id,name')
                //->with('signoff_supervisor:id,name');  
                
        // SQL for Chart 5
         $sql_chart5 = ConversationParticipant::selectRaw("conversations.*, conversation_topics.name as conversation_name, users.employee_id, employee_demo.employee_name, users.email,
                    employee_demo.organization, employee_demo.level1_program, employee_demo.level2_division, employee_demo.level3_branch, employee_demo.level4,
                    users.next_conversation_date as next_due_date")
            ->join('users', 'users.id', 'conversation_participants.participant_id') 
            ->join('conversations','conversations.id','conversation_participants.conversation_id')
            ->join('conversation_topics','conversations.conversation_topic_id','conversation_topics.id')         
            ->join('employee_demo', function($join) {
                $join->on('employee_demo.employee_id', '=', 'users.employee_id');
            })
            ->where(function($query) {
                    $query->where(function($query) {
                        $query->where('users.due_date_paused', 'N')
                            ->orWhereNull('users.due_date_paused');
                    });
                }) 
            ->where('conversation_participants.role','emp')       
            ->when($level0, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                return $q->where('employee_demo.organization', $level0->name);
            })
            ->when( $level1, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                return $q->where('employee_demo.level1_program', $level1->name);
            })
            ->when( $level2, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                return $q->where('employee_demo.level2_division', $level2->name);
            })
            ->when( $level3, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                return $q->where('employee_demo.level3_branch', $level3->name);
            })
            ->when( $level4, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                return $q->where('employee_demo.level4', $level4->name);
            })
            ->where(function($query) {
                $query->where(function($query) {
                    $query->whereNotNull('signoff_user_id')
                          ->whereNotNull('supervisor_signoff_id');
                });
            })
            ->whereNull('deleted_at')              
            ->when( $request->topic_id, function($q) use($request) {
                $q->where('conversations.conversation_topic_id', $request->topic_id);
            })
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                        ->from('admin_org_users')
                        ->whereColumn('admin_org_users.allowed_user_id', 'conversations.user_id')
                        ->whereIn('admin_org_users.access_type', [0,2,3])
                        ->where('admin_org_users.granted_to_id', '=', Auth::id());
            });
            //->with('topic:id,name')
            //->with('signoff_user:id,name')
            //->with('signoff_supervisor:id,name')
            //;
            
        // Generating Output file 
        $filename = 'Conversations.xlsx';
        switch ($request->chart) {
            case 1:

                $filename = 'Next Conversation Due.csv';
                // $data = $next_due_users;
                $users =  $sql_chart1->get();

                if (array_key_exists($request->range, $this->overdue_groups) ) {
                    $users = $users->whereBetween('overdue_in_days', $this->overdue_groups[$request->range]);  
                }
        
                $headers = array(
                    "Content-type"        => "text/csv",
                    "Content-Disposition" => "attachment; filename=$filename",
                    "Pragma"              => "no-cache",
                    "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                    "Expires"             => "0"
                );
        
                $columns = ["Employee ID", "Employee Name", "Email",
                                "Next Conversation Due", 'Due Date Category',
                                "Organization", "Level 1", "Level 2", "Level 3", "Level 4", "Reporting To",
                           ];
        
                $callback = function() use($users, $columns) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, $columns);

                    foreach ($users as $user) {

                        $group_name = '';
                        foreach ($this->overdue_groups as $key => $range) {
                            if (($user->overdue_in_days >= $range[0] ) && ( $user->overdue_in_days <= $range[1])) {
                                $group_name = $key;
                            }
                        }

                        $row['Employee ID'] = $user->employee_id;
                        $row['Name'] = $user->employee_name;
                        $row['Email'] = $user->email;
                        $row['Next Conversation Due'] = $user->next_due_date;
                        $row['Due Date Category'] = $group_name;
                        $row['Organization'] = $user->organization;
                        $row['Level 1'] = $user->level1_program;
                        $row['Level 2'] = $user->level2_division;
                        $row['Level 3'] = $user->level3_branch;
                        $row['Level 4'] = $user->level4;
                        $row['Reporting To'] = $user->reportingManager ? $user->reportingManager->name : '';
        
                        fputcsv($file, array($row['Employee ID'], $row['Name'], $row['Email'], $row['Next Conversation Due'], 
                                    $row['Due Date Category'], $row['Organization'],
                                    $row['Level 1'], $row['Level 2'], $row['Level 3'], $row['Level 4'], $row['Reporting To'] ));
                    }
        
                    fclose($file);
                };
        
                return response()->stream($callback, 200, $headers);

                break;

            case 2:

                $filename = 'Open Conversation By Topic.csv';
                $conversations =  $sql_chart2->get();
        
                $headers = array(
                    "Content-type"        => "text/csv",
                    "Content-Disposition" => "attachment; filename=$filename",
                    "Pragma"              => "no-cache",
                    "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                    "Expires"             => "0"
                );
        
                $columns = ["Employee ID", "Employee Name", "Email",
                        "Conversation Due Date",
                            "Conversation Participant", "Employee Sign-Off", "Supervisor Sign-off", 
                                "Organization", "Level 1", "Level 2", "Level 3", "Level 4", 
                           ];
        
                $callback = function() use($conversations, $columns) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, $columns);
        
                    foreach ($conversations as $conversation) {
                        $row['Employee ID'] = $conversation->employee_id;
                        $row['Name'] = $conversation->employee_name;
                        $row['Email'] = $conversation->user->email;
                        $row['Conversation Due Date'] = $conversation->next_due_date;
                        $row['Conversation Participant'] = implode(', ', $conversation->conversationParticipants->pluck('participant.name')->toArray() );
                        $row['Employee Sign-Off'] = $conversation->signoff_user  ? $conversation->signoff_user->name : '';
                        $row['Supervisor Sign-off'] = $conversation->signoff_supervisor ? $conversation->signoff_supervisor->name : '';
                        $row['Organization'] = $conversation->organization;
                        $row['Level 1'] = $conversation->level1_program;
                        $row['Level 2'] = $conversation->level2_division;
                        $row['Level 3'] = $conversation->level3_branch;
                        $row['Level 4'] = $conversation->level4;
        
                        fputcsv($file, array($row['Employee ID'], $row['Name'], $row['Email'], // $row['Next Conversation Due'],
                        $row['Conversation Due Date'], $row["Conversation Participant"],
                        $row["Employee Sign-Off"], $row["Supervisor Sign-off"],
                                 $row['Organization'],
                                  $row['Level 1'], $row['Level 2'], $row['Level 3'], $row['Level 4'], 
                                ));
                    }
        
                    fclose($file);
                };
        
                return response()->stream($callback, 200, $headers);


                break;

            case 3:

                $filename = 'Completed Conversation By Topic.csv';
                $conversations =  $sql_chart3->get();

                if (array_key_exists($request->range, $this->overdue_groups) ) {
                    $users = $users->whereBetween('overdue_in_days', $this->overdue_groups[$request->range]);  
                }
        
                $headers = array(
                    "Content-type"        => "text/csv",
                    "Content-Disposition" => "attachment; filename=$filename",
                    "Pragma"              => "no-cache",
                    "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                    "Expires"             => "0"
                );
        
                $columns = ["Employee ID", "Employee Name", "Email",
                        "Conversation Due Date",
                            "Conversation Participant", "Employee Sign-Off", "Supervisor Sign-off", 
                                "Organization", "Level 1", "Level 2", "Level 3", "Level 4", 
                           ];
        
                $callback = function() use($conversations, $columns) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, $columns);
        
                    foreach ($conversations as $conversation) {
                        $row['Employee ID'] = $conversation->employee_id;
                        $row['Name'] = $conversation->employee_name;
                        $row['Email'] = $conversation->user->email;
                        $row['Conversation Due Date'] = $conversation->next_due_date;
                        $row['Conversation Participant'] = implode(', ', $conversation->conversationParticipants->pluck('participant.name')->toArray() );
                        $row['Employee Sign-Off'] = $conversation->signoff_user  ? $conversation->signoff_user->name : '';
                        $row['Supervisor Sign-off'] = $conversation->signoff_supervisor ? $conversation->signoff_supervisor->name : '';
                        $row['Organization'] = $conversation->organization;
                        $row['Level 1'] = $conversation->level1_program;
                        $row['Level 2'] = $conversation->level2_division;
                        $row['Level 3'] = $conversation->level3_branch;
                        $row['Level 4'] = $conversation->level4;
        
                        fputcsv($file, array($row['Employee ID'], $row['Name'], $row['Email'], // $row['Next Conversation Due'],
                        $row['Conversation Due Date'], $row["Conversation Participant"],
                        $row["Employee Sign-Off"], $row["Supervisor Sign-off"],
                                 $row['Organization'],
                                  $row['Level 1'], $row['Level 2'], $row['Level 3'], $row['Level 4'], 
                                ));
                    }
        
                    fclose($file);
                };
        
                return response()->stream($callback, 200, $headers);

                break;  
                
            case 4:

                $filename = 'Employees of Open Conversation By Topic.csv';
                $conversations =  $sql_chart4->get();
                $conversations_unique = array();
                $topics = ConversationTopic::select('id','name')->get();
                foreach($topics as $topic){
                        $subset = $conversations->where('conversation_topic_id', $topic->id );
                        $unique_subset = $subset->unique('employee_id');
                        foreach($unique_subset as $item) {
                            array_push($conversations_unique,$item);
                        }                        
                }
                
                $headers = array(
                    "Content-type"        => "text/csv",
                    "Content-Disposition" => "attachment; filename=$filename",
                    "Pragma"              => "no-cache",
                    "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                    "Expires"             => "0"
                );
        
                $columns = ["Employee ID", "Employee Name", "Email", "Conversation Name", "Conversation Participant",
                        "Conversation Due Date",
                                "Organization", "Level 1", "Level 2", "Level 3", "Level 4", 
                           ];
        
                $callback = function() use($conversations_unique, $columns) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, $columns);
                    
                    foreach ($conversations_unique as $conversation) {
                        $row['Employee ID'] = $conversation->employee_id;
                        $row['Name'] = $conversation->employee_name;
                        $row['Email'] = $conversation->email;
                        $row['Conversation Name'] = $conversation->conversation_name;
                        $row['Conversation Participant'] = implode(', ', $conversation->conversationParticipants->pluck('participant.name')->toArray() );
                        $row['Conversation Due Date'] = $conversation->next_due_date;
                        $row['Organization'] = $conversation->organization;
                        $row['Level 1'] = $conversation->level1_program;
                        $row['Level 2'] = $conversation->level2_division;
                        $row['Level 3'] = $conversation->level3_branch;
                        $row['Level 4'] = $conversation->level4;
        
                        fputcsv($file, array($row['Employee ID'], $row['Name'], $row['Email'], // $row['Next Conversation Due'],
                        $row['Conversation Name'],
                            $row['Conversation Due Date'],
                                 $row['Organization'],
                                  $row['Level 1'], $row['Level 2'], $row['Level 3'], $row['Level 4'], 
                                ));
                    }
        
                    fclose($file);
                };
        
                return response()->stream($callback, 200, $headers);


                break;       
            
            case 5:

                $filename = 'Employees of Completed Conversation By Topic.csv';
                $conversations =  $sql_chart5->get();
                $conversations_unique = array();
                $topics = ConversationTopic::select('id','name')->get();
                foreach($topics as $topic){
                        $subset = $conversations->where('conversation_topic_id', $topic->id );
                        $unique_subset = $subset->unique('employee_id');
                        foreach($unique_subset as $item) {
                            array_push($conversations_unique,$item);
                        }                        
                }

                if (array_key_exists($request->range, $this->overdue_groups) ) {
                    $users = $users->whereBetween('overdue_in_days', $this->overdue_groups[$request->range]);  
                }
        
                $headers = array(
                    "Content-type"        => "text/csv",
                    "Content-Disposition" => "attachment; filename=$filename",
                    "Pragma"              => "no-cache",
                    "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                    "Expires"             => "0"
                );
        
                $columns = ["Employee ID", "Employee Name", "Email","Conversation Name","Conversation Participant",
                        "Conversation Due Date",
                                "Organization", "Level 1", "Level 2", "Level 3", "Level 4", 
                           ];
        
                $callback = function() use($conversations_unique, $columns) {
                    $file = fopen('php://output', 'w');
                    fputcsv($file, $columns);
                    
                    foreach ($conversations_unique as $conversation) {
                            $row['Employee ID'] = $conversation->employee_id;
                            $row['Name'] = $conversation->employee_name;
                            $row['Email'] = $conversation->email;
                            $row['Conversation Name'] = $conversation->conversation_name;
                            $row['Conversation Participant'] = implode(', ', $conversation->conversationParticipants->pluck('participant.name')->toArray() );
                            $row['Conversation Due Date'] = $conversation->next_due_date;
                            $row['Organization'] = $conversation->organization;
                            $row['Level 1'] = $conversation->level1_program;
                            $row['Level 2'] = $conversation->level2_division;
                            $row['Level 3'] = $conversation->level3_branch;
                            $row['Level 4'] = $conversation->level4;

                            fputcsv($file, array($row['Employee ID'], $row['Name'], $row['Email'], // $row['Next Conversation Due'],
                            $row["Conversation Name"],$row['Conversation Due Date'], 
                                     $row['Organization'],
                                      $row['Level 1'], $row['Level 2'], $row['Level 3'], $row['Level 4'], 
                                    ));
                        }
        
                    fclose($file);
                };
        
                return response()->stream($callback, 200, $headers);

                break;   
        }
        
    }

    public function sharedsummary(Request $request) 
    {

        // send back the input parameters
        $this->preservedInputParams($request);

        $level0 = $request->dd_level0 ? OrganizationTree::where('id', $request->dd_level0)->first() : null;
        $level1 = $request->dd_level1 ? OrganizationTree::where('id', $request->dd_level1)->first() : null;
        $level2 = $request->dd_level2 ? OrganizationTree::where('id', $request->dd_level2)->first() : null;
        $level3 = $request->dd_level3 ? OrganizationTree::where('id', $request->dd_level3)->first() : null;
        $level4 = $request->dd_level4 ? OrganizationTree::where('id', $request->dd_level4)->first() : null;

        $request->session()->flash('level0', $level0);
        $request->session()->flash('level1', $level1);
        $request->session()->flash('level2', $level2);
        $request->session()->flash('level3', $level3);
        $request->session()->flash('level4', $level4);


        $sql = User::selectRaw("users.employee_id, users.empl_record,
                case when (select count(*) from shared_profiles A where A.shared_id = users.id) > 0 then 'Yes' else 'No' end as shared")
                ->join('employee_demo', function($join) {
                    $join->on('employee_demo.employee_id', '=', 'users.employee_id');
                    // $join->on('employee_demo.employee_id', '=', 'users.employee_id');
                    // $join->on('employee_demo.empl_record', '=', 'users.empl_record');
                })
                // ->join('employee_demo_jr as j', 'employee_demo.guid', 'j.guid')
                // ->whereRaw("j.id = (select max(j1.id) from employee_demo_jr as j1 where j1.guid = j.guid) and (j.due_date_paused = 'N') ")
                ->where('users.due_date_paused', 'N')
                ->when($level0, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.organization', $level0->name);
                })
                ->when( $level1, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level1_program', $level1->name);
                })
                ->when( $level2, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level2_division', $level2->name);
                })
                ->when( $level3, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level3_branch', $level3->name);
                })
                ->when( $level4, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level4', $level4->name);
                })
                // ->whereExists(function ($query) {
                //     $query->select(DB::raw(1))
                //             ->from('admin_orgs')
                //             // ->whereColumn('admin_orgs.organization', 'employee_demo.organization')
                //             // ->whereColumn('admin_orgs.level1_program', 'employee_demo.level1_program')
                //             // ->whereColumn('admin_orgs.level2_division', 'employee_demo.level2_division')
                //             // ->whereColumn('admin_orgs.level3_branch',  'employee_demo.level3_branch')
                //             // ->whereColumn('admin_orgs.level4', 'employee_demo.level4')
                //             ->whereRAW('(admin_orgs.organization = employee_demo.organization OR (admin_orgs.organization = "" OR admin_orgs.organization IS NULL))')
                //             ->whereRAW('(admin_orgs.level1_program = employee_demo.level1_program OR (admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL))')
                //             ->whereRAW('(admin_orgs.level2_division = employee_demo.level2_division OR (admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL))')
                //             ->whereRAW('(admin_orgs.level3_branch = employee_demo.level3_branch OR (admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL))')
                //             ->whereRAW('(admin_orgs.level4 = employee_demo.level4 OR (admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL))')
                //             ->where('admin_orgs.user_id', '=', Auth::id() );
                // });
                // ->join('admin_orgs', function ($j1) {
                //     $j1->on(function ($j1a) {
                //         $j1a->whereRAW('admin_orgs.organization = employee_demo.organization OR ((admin_orgs.organization = "" OR admin_orgs.organization IS NULL) AND (employee_demo.organization = "" OR employee_demo.organization IS NULL))');
                //     } )
                //     ->on(function ($j2a) {
                //         $j2a->whereRAW('admin_orgs.level1_program = employee_demo.level1_program OR ((admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL) AND (employee_demo.level1_program = "" OR employee_demo.level1_program IS NULL))');
                //     } )
                //     ->on(function ($j3a) {
                //         $j3a->whereRAW('admin_orgs.level2_division = employee_demo.level2_division OR ((admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL) AND (employee_demo.level2_division = "" OR employee_demo.level2_division IS NULL))');
                //     } )
                //     ->on(function ($j4a) {
                //         $j4a->whereRAW('admin_orgs.level3_branch = employee_demo.level3_branch OR ((admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL) AND (employee_demo.level3_branch = "" OR employee_demo.level3_branch IS NULL))');
                //     } )
                //     ->on(function ($j5a) {
                //         $j5a->whereRAW('admin_orgs.level4 = employee_demo.level4 OR ((admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL) AND (employee_demo.level4 = "" OR employee_demo.level4 IS NULL))');
                //     } );
                // })
                // ->where('admin_orgs.user_id', '=', Auth::id())
                // ->where( function($query) {
                //     $query->whereRaw('date(SYSDATE()) not between IFNULL(users.excused_start_date,"1900-01-01") and IFNULL(users.excused_end_date,"1900-01-01") ')
                //           ->where('employee_demo.employee_status', 'A');
                // })
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                            ->from('admin_org_users')
                            ->whereColumn('admin_org_users.allowed_user_id', 'users.id')
                            ->whereIn('admin_org_users.access_type', [0])
                            ->where('admin_org_users.granted_to_id', '=', Auth::id());
                })
                // ->join('admin_org_users', 'users.id', 'admin_org_users.allowed_user_id')
                // ->where('admin_org_users.granted_to_id', '=', Auth::id())
                ;
                


        $users = $sql->get();

        // Chart 1 
        $legends = ['Yes', 'No'];
        $data['chart1']['chart_id'] = 1;
        $data['chart1']['title'] = 'Shared Status';
        $data['chart1']['legend'] = $legends;
        $data['chart1']['groups'] = array();

        foreach($legends as $legend)
        {
            $subset = $users->where('shared', $legend);
            array_push( $data['chart1']['groups'],  [ 'name' => $legend, 'value' => $subset->count(),
                            // 'ids' => $subset ? $subset->pluck('id')->toArray() : []
                            'legend' => $legend, 
                        ]);
        }    

        return view('hradmin.statistics.sharedsummary',compact('data'));

    } 

    public function sharedSummaryExport(Request $request) 
    {

        $level0 = $request->dd_level0 ? OrganizationTree::where('id', $request->dd_level0)->first() : null;
        $level1 = $request->dd_level1 ? OrganizationTree::where('id', $request->dd_level1)->first() : null;
        $level2 = $request->dd_level2 ? OrganizationTree::where('id', $request->dd_level2)->first() : null;
        $level3 = $request->dd_level3 ? OrganizationTree::where('id', $request->dd_level3)->first() : null;
        $level4 = $request->dd_level4 ? OrganizationTree::where('id', $request->dd_level4)->first() : null;


      $selected_ids = $request->ids ? explode(',', $request->ids) : [];

      $sql = User::selectRaw("users.*,
                employee_name, employee_demo.organization, employee_demo.level1_program, employee_demo.level2_division,
                 employee_demo.level3_branch, employee_demo.level4,
            case when (select count(*) from shared_profiles A where A.shared_id = users.id) > 0 then 'Yes' else 'No' end as shared")
            ->join('employee_demo', function($join) {
                $join->on('employee_demo.employee_id', '=', 'users.employee_id');
                // $join->on('employee_demo.employee_id', '=', 'users.employee_id');
                // $join->on('employee_demo.empl_record', '=', 'users.empl_record');
            })
            // ->join('employee_demo_jr as j', 'employee_demo.guid', 'j.guid')
            // ->whereRaw("j.id = (select max(j1.id) from employee_demo_jr as j1 where j1.guid = j.guid) and (j.due_date_paused = 'N') ")
            ->where('users.due_date_paused', 'N')            
            ->when( $request->legend == 'Yes', function($q) use($request) {
                $q->whereRaw(" (select count(*) from shared_profiles A where A.shared_id = users.id) > 0 ");
            }) 
            ->when( $request->legend == 'No', function($q) use($request) {
                $q->whereRaw(" (select count(*) from shared_profiles A where A.shared_id = users.id) = 0 ");
            }) 
            ->when($level0, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                return $q->where('employee_demo.organization', $level0->name);
            })
            ->when( $level1, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                return $q->where('employee_demo.level1_program', $level1->name);
            })
            ->when( $level2, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                return $q->where('employee_demo.level2_division', $level2->name);
            })
            ->when( $level3, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                return $q->where('employee_demo.level3_branch', $level3->name);
            })
            ->when( $level4, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                return $q->where('employee_demo.level4', $level4->name);
            })
            // ->where( function($query) {
            //     $query->whereRaw('date(SYSDATE()) not between IFNULL(users.excused_start_date,"1900-01-01") and IFNULL(users.excused_end_date,"1900-01-01") ')
            //           ->where('employee_demo.employee_status', 'A');
            // })
            // ->whereExists(function ($query) {
            //     $query->select(DB::raw(1))
            //             ->from('admin_orgs')
            //             // ->whereColumn('admin_orgs.organization', 'employee_demo.organization')
            //             // ->whereColumn('admin_orgs.level1_program', 'employee_demo.level1_program')
            //             // ->whereColumn('admin_orgs.level2_division', 'employee_demo.level2_division')
            //             // ->whereColumn('admin_orgs.level3_branch',  'employee_demo.level3_branch')
            //             // ->whereColumn('admin_orgs.level4', 'employee_demo.level4')
            //             ->whereRAW('(admin_orgs.organization = employee_demo.organization OR (admin_orgs.organization = "" OR admin_orgs.organization IS NULL))')
            //             ->whereRAW('(admin_orgs.level1_program = employee_demo.level1_program OR (admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL))')
            //             ->whereRAW('(admin_orgs.level2_division = employee_demo.level2_division OR (admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL))')
            //             ->whereRAW('(admin_orgs.level3_branch = employee_demo.level3_branch OR (admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL))')
            //             ->whereRAW('(admin_orgs.level4 = employee_demo.level4 OR (admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL))')
            //             ->where('admin_orgs.user_id', '=', Auth::id() );
            // })
            // ->join('admin_orgs', function ($j1) {
            //     $j1->on(function ($j1a) {
            //         $j1a->whereRAW('admin_orgs.organization = employee_demo.organization OR ((admin_orgs.organization = "" OR admin_orgs.organization IS NULL) AND (employee_demo.organization = "" OR employee_demo.organization IS NULL))');
            //     } )
            //     ->on(function ($j2a) {
            //         $j2a->whereRAW('admin_orgs.level1_program = employee_demo.level1_program OR ((admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL) AND (employee_demo.level1_program = "" OR employee_demo.level1_program IS NULL))');
            //     } )
            //     ->on(function ($j3a) {
            //         $j3a->whereRAW('admin_orgs.level2_division = employee_demo.level2_division OR ((admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL) AND (employee_demo.level2_division = "" OR employee_demo.level2_division IS NULL))');
            //     } )
            //     ->on(function ($j4a) {
            //         $j4a->whereRAW('admin_orgs.level3_branch = employee_demo.level3_branch OR ((admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL) AND (employee_demo.level3_branch = "" OR employee_demo.level3_branch IS NULL))');
            //     } )
            //     ->on(function ($j5a) {
            //         $j5a->whereRAW('admin_orgs.level4 = employee_demo.level4 OR ((admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL) AND (employee_demo.level4 = "" OR employee_demo.level4 IS NULL))');
            //     } );
            // })
            // ->where('admin_orgs.user_id', '=', Auth::id())
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                        ->from('admin_org_users')
                        ->whereColumn('admin_org_users.allowed_user_id', 'users.id')
                        ->whereIn('admin_org_users.access_type', [0])
                        ->where('admin_org_users.granted_to_id', '=', Auth::id());
            })
            ->with('sharedWith');


      $users = $sql->get();

      // Generating output file 
      $filename = 'Shared Employees.csv';
      
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $columns = ["Employee ID", "Name", "Email", 'Shared', 'Shared with',
                        "Organization", "Level 1", "Level 2", "Level 3", "Level 4",
                    ];

        $callback = function() use($users, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($users as $user) {
                $row['Employee ID'] = $user->employee_id;
                $row['Name'] = $user->employee_name;
                $row['Email'] = $user->email;
                $row['Shared'] = $user->shared;
                $row['Shared with'] = implode(', ', $user->sharedWith->map( function ($item, $key) { return $item ? $item->sharedWith->name : null; })->toArray() );
                $row['Organization'] = $user->organization;
                $row['Level 1'] = $user->level1_program;
                $row['Level 2'] = $user->level2_division;
                $row['Level 3'] = $user->level3_branch;
                $row['Level 4'] = $user->level4;

                fputcsv($file, array($row['Employee ID'], $row['Name'], $row['Email'], 
                        $row['Shared'], $row['Shared with'],
                        $row['Organization'],
                        $row['Level 1'], $row['Level 2'], $row['Level 3'], $row['Level 4'] ));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);

    }


    public function excusedsummary(Request $request) {

        // send back the input parameters
        $this->preservedInputParams($request);

        $level0 = $request->dd_level0 ? OrganizationTree::where('id', $request->dd_level0)->first() : null;
        $level1 = $request->dd_level1 ? OrganizationTree::where('id', $request->dd_level1)->first() : null;
        $level2 = $request->dd_level2 ? OrganizationTree::where('id', $request->dd_level2)->first() : null;
        $level3 = $request->dd_level3 ? OrganizationTree::where('id', $request->dd_level3)->first() : null;
        $level4 = $request->dd_level4 ? OrganizationTree::where('id', $request->dd_level4)->first() : null;

        $request->session()->flash('level0', $level0);
        $request->session()->flash('level1', $level1);
        $request->session()->flash('level2', $level2);
        $request->session()->flash('level3', $level3);
        $request->session()->flash('level4', $level4);

        $sql = User::selectRaw("users.employee_id, users.empl_record, 
                    employee_name, employee_demo.organization, employee_demo.level1_program, employee_demo.level2_division,
                    employee_demo.level3_branch, employee_demo.level4,
                    case when users.due_date_paused = 'N'
                        then 'No' else 'Yes' end as excused")
                    ->join('employee_demo', function($join) {
                         $join->on('employee_demo.employee_id', '=', 'users.employee_id');
                        // $join->on('employee_demo.employee_id', '=', 'users.employee_id');
                        // $join->on('employee_demo.empl_record', '=', 'users.empl_record');
                    })
                    // ->join('employee_demo_jr as j', 'employee_demo.guid', 'j.guid')
                    // ->whereRaw("j.id = (select max(j1.id) from employee_demo_jr as j1 where j1.guid = j.guid) ")
                    // ->where( function($q) {
                    //     $q->whereRaw(" (( date(SYSDATE()) between IFNULL(users.excused_start_date,'1900-01-01') and IFNULL(users.excused_end_date,'1900-01-01')) or employee_demo.employee_status <> 'A' or users.due_date_paused <> 'N') ")
                    //       ->orWhereRaw(" ( date(SYSDATE()) not between IFNULL(users.excused_start_date,'1900-01-01') and IFNULL(users.excused_end_date,'1900-01-01')) and employee_demo.employee_status ='A' and users.due_date_paused = 'N' ");
                    // })
                    ->when($level0, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                        return $q->where('employee_demo.organization', $level0->name);
                    })
                    ->when( $level1, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                        return $q->where('employee_demo.level1_program', $level1->name);
                    })
                    ->when( $level2, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                        return $q->where('employee_demo.level2_division', $level2->name);
                    })
                    ->when( $level3, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                        return $q->where('employee_demo.level3_branch', $level3->name);
                    })
                    ->when( $level4, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                        return $q->where('employee_demo.level4', $level4->name);
                    })
                    // ->whereExists(function ($query) {
                    //     $query->select(DB::raw(1))
                    //           ->from('admin_orgs')
                    //         //   ->whereColumn('admin_orgs.organization', 'employee_demo.organization')
                    //         //   ->whereColumn('admin_orgs.level1_program', 'employee_demo.level1_program')
                    //         //   ->whereColumn('admin_orgs.level2_division', 'employee_demo.level2_division')
                    //         //   ->whereColumn('admin_orgs.level3_branch',  'employee_demo.level3_branch')
                    //         //   ->whereColumn('admin_orgs.level4', 'employee_demo.level4')
                    //         ->whereRAW('(admin_orgs.organization = employee_demo.organization OR (admin_orgs.organization = "" OR admin_orgs.organization IS NULL))')
                    //         ->whereRAW('(admin_orgs.level1_program = employee_demo.level1_program OR (admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL))')
                    //         ->whereRAW('(admin_orgs.level2_division = employee_demo.level2_division OR (admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL))')
                    //         ->whereRAW('(admin_orgs.level3_branch = employee_demo.level3_branch OR (admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL))')
                    //         ->whereRAW('(admin_orgs.level4 = employee_demo.level4 OR (admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL))')
                    //         ->where('admin_orgs.user_id', '=', Auth::id() );
                    // });
                    // ->join('admin_orgs', function ($j1) {
                    //     $j1->on(function ($j1a) {
                    //         $j1a->whereRAW('admin_orgs.organization = employee_demo.organization OR ((admin_orgs.organization = "" OR admin_orgs.organization IS NULL) AND (employee_demo.organization = "" OR employee_demo.organization IS NULL))');
                    //     } )
                    //     ->on(function ($j2a) {
                    //         $j2a->whereRAW('admin_orgs.level1_program = employee_demo.level1_program OR ((admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL) AND (employee_demo.level1_program = "" OR employee_demo.level1_program IS NULL))');
                    //     } )
                    //     ->on(function ($j3a) {
                    //         $j3a->whereRAW('admin_orgs.level2_division = employee_demo.level2_division OR ((admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL) AND (employee_demo.level2_division = "" OR employee_demo.level2_division IS NULL))');
                    //     } )
                    //     ->on(function ($j4a) {
                    //         $j4a->whereRAW('admin_orgs.level3_branch = employee_demo.level3_branch OR ((admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL) AND (employee_demo.level3_branch = "" OR employee_demo.level3_branch IS NULL))');
                    //     } )
                    //     ->on(function ($j5a) {
                    //         $j5a->whereRAW('admin_orgs.level4 = employee_demo.level4 OR ((admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL) AND (employee_demo.level4 = "" OR employee_demo.level4 IS NULL))');
                    //     } );
                    // })
                    // ->where('admin_orgs.user_id', '=', Auth::id());
                    ->whereExists(function ($query) {
                        $query->select(DB::raw(1))
                                ->from('admin_org_users')
                                ->whereColumn('admin_org_users.allowed_user_id', 'users.id')
                                ->whereIn('admin_org_users.access_type', [0])
                                ->where('admin_org_users.granted_to_id', '=', Auth::id());
                    });
                 
        $users = $sql->get();

        // Chart1 -- Excuse 
        $legends = ['Yes', 'No'];
        $data['chart1']['chart_id'] = 1;
        $data['chart1']['title'] = 'Excused Status';
        $data['chart1']['legend'] = $legends;
        $data['chart1']['groups'] = array();


        foreach($legends as $legend)
        {
            $subset = $users->where('excused', $legend);
            array_push( $data['chart1']['groups'],  [ 'name' => $legend, 'value' => $subset->count(),
                            'legend' => $legend, 
                            // 'ids' => $subset ? $subset->pluck('id')->toArray() : []
                        ]);
        }    

        return view('hradmin.statistics.excusedsummary',compact('data'));


    } 


    public function excusedSummaryExport(Request $request) {

        $level0 = $request->dd_level0 ? OrganizationTree::where('id', $request->dd_level0)->first() : null;
        $level1 = $request->dd_level1 ? OrganizationTree::where('id', $request->dd_level1)->first() : null;
        $level2 = $request->dd_level2 ? OrganizationTree::where('id', $request->dd_level2)->first() : null;
        $level3 = $request->dd_level3 ? OrganizationTree::where('id', $request->dd_level3)->first() : null;
        $level4 = $request->dd_level4 ? OrganizationTree::where('id', $request->dd_level4)->first() : null;

      $selected_ids = $request->ids ? explode(',', $request->ids) : [];

      $sql = User::selectRaw("users.employee_id, users.email, users.excused_start_date, users.excused_end_date,
                            users.excused_reason_id, users.reporting_to,
                    employee_demo.employee_name, employee_demo.organization, employee_demo.level1_program, employee_demo.level2_division, employee_demo.level3_branch, employee_demo.level4,
                    case when users.due_date_paused = 'N'
                        then 'No' else 'Yes' end as excused")
                ->join('employee_demo', function($join) {
                    $join->on('employee_demo.employee_id', '=', 'users.employee_id');
                    // $join->on('employee_demo.employee_id', '=', 'users.employee_id');
                    // $join->on('employee_demo.empl_record', '=', 'users.empl_record');
                })
                // ->join('employee_demo_jr as j', 'employee_demo.guid', 'j.guid')
                // ->whereRaw("j.id = (select max(j1.id) from employee_demo_jr as j1 where j1.guid = j.guid) ")
                ->when( $request->legend == 'Yes', function($q) use($request) {
                    $q->whereRaw(" users.due_date_paused = 'Y' ");
                }) 
                ->when( $request->legend == 'No', function($q) use($request) {
                    $q->whereRaw(" users.due_date_paused = 'N' ");
                })
                // ->when( $request->missing('legend'), function($query) {
                //     $query->where( function($q) {
                //         $q->whereRaw(" (( date(SYSDATE()) between IFNULL(users.excused_start_date,'1900-01-01') and IFNULL(users.excused_end_date,'1900-01-01')) or employee_demo.employee_status <> 'A' or users.due_date_paused <> 'N') ")
                //           ->orWhereRaw(" ( date(SYSDATE()) not between IFNULL(users.excused_start_date,'1900-01-01') and IFNULL(users.excused_end_date,'1900-01-01')) and employee_demo.employee_status ='A' and users.due_date_paused = 'N' ");
                //     });
                // })
                ->when($level0, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.organization', $level0->name);
                })
                ->when( $level1, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level1_program', $level1->name);
                })
                ->when( $level2, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level2_division', $level2->name);
                })
                ->when( $level3, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level3_branch', $level3->name);
                })
                ->when( $level4, function ($q) use($level0, $level1, $level2, $level3, $level4 ) {
                    return $q->where('employee_demo.level4', $level4->name);
                })
                // ->whereExists(function ($query) {
                //     $query->select(DB::raw(1))
                //           ->from('admin_orgs')
                //         //   ->whereColumn('admin_orgs.organization', 'employee_demo.organization')
                //         //   ->whereColumn('admin_orgs.level1_program', 'employee_demo.level1_program')
                //         //   ->whereColumn('admin_orgs.level2_division', 'employee_demo.level2_division')
                //         //   ->whereColumn('admin_orgs.level3_branch',  'employee_demo.level3_branch')
                //         //   ->whereColumn('admin_orgs.level4', 'employee_demo.level4')
                //           ->whereRAW('(admin_orgs.organization = employee_demo.organization OR (admin_orgs.organization = "" OR admin_orgs.organization IS NULL))')
                //           ->whereRAW('(admin_orgs.level1_program = employee_demo.level1_program OR (admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL))')
                //           ->whereRAW('(admin_orgs.level2_division = employee_demo.level2_division OR (admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL))')
                //           ->whereRAW('(admin_orgs.level3_branch = employee_demo.level3_branch OR (admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL))')
                //           ->whereRAW('(admin_orgs.level4 = employee_demo.level4 OR (admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL))')
                //            ->where('admin_orgs.user_id', '=', Auth::id() );
                // })
                // ->join('admin_orgs', function ($j1) {
                //     $j1->on(function ($j1a) {
                //         $j1a->whereRAW('admin_orgs.organization = employee_demo.organization OR ((admin_orgs.organization = "" OR admin_orgs.organization IS NULL) AND (employee_demo.organization = "" OR employee_demo.organization IS NULL))');
                //     } )
                //     ->on(function ($j2a) {
                //         $j2a->whereRAW('admin_orgs.level1_program = employee_demo.level1_program OR ((admin_orgs.level1_program = "" OR admin_orgs.level1_program IS NULL) AND (employee_demo.level1_program = "" OR employee_demo.level1_program IS NULL))');
                //     } )
                //     ->on(function ($j3a) {
                //         $j3a->whereRAW('admin_orgs.level2_division = employee_demo.level2_division OR ((admin_orgs.level2_division = "" OR admin_orgs.level2_division IS NULL) AND (employee_demo.level2_division = "" OR employee_demo.level2_division IS NULL))');
                //     } )
                //     ->on(function ($j4a) {
                //         $j4a->whereRAW('admin_orgs.level3_branch = employee_demo.level3_branch OR ((admin_orgs.level3_branch = "" OR admin_orgs.level3_branch IS NULL) AND (employee_demo.level3_branch = "" OR employee_demo.level3_branch IS NULL))');
                //     } )
                //     ->on(function ($j5a) {
                //         $j5a->whereRAW('admin_orgs.level4 = employee_demo.level4 OR ((admin_orgs.level4 = "" OR admin_orgs.level4 IS NULL) AND (employee_demo.level4 = "" OR employee_demo.level4 IS NULL))');
                //     } );
                // })
                // ->where('admin_orgs.user_id', '=', Auth::id())
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                            ->from('admin_org_users')
                            ->whereColumn('admin_org_users.allowed_user_id', 'users.id')
                            ->whereIn('admin_org_users.access_type', [0])
                            ->where('admin_org_users.granted_to_id', '=', Auth::id());
                })
                ->with('excuseReason') ;

        $users = $sql->get();

      // Generating Output file
        $filename = 'Excused Employees.csv';
      
        $headers = array(
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        );

        $columns = ["Employee ID", "Name", "Email", 
                        "Excused", "Excused Start Date", "Excused End Date", "Excused Reason",
                        "Organization", "Level 1", "Level 2", "Level 3", "Level 4",
                    ];

        $callback = function() use($users, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($users as $user) {
                $row['Employee ID'] = $user->employee_id;
                $row['Name'] = $user->employee_name;
                $row['Email'] = $user->email;

                $row['Excused'] = $user->excused;
                $row['Excused Start Date'] = $user->excused_start_date;
                $row['Excused End Date'] = $user->excused_end_date;
                $row['Excused Reason'] = $user->excuseReason ? $user->excuseReason->name : '';
                // $row['Shared'] = $user->shared;
                // $row['Shared with'] = implode(', ', $user->sharedWith->map( function ($item, $key) { return $item ? $item->sharedWith->name : null; })->toArray() );
                $row['Organization'] = $user->organization;
                $row['Level 1'] = $user->level1_program;
                $row['Level 2'] = $user->level2_division;
                $row['Level 3'] = $user->level3_branch;
                $row['Level 4'] = $user->level4;

                fputcsv($file, array($row['Employee ID'], $row['Name'], $row['Email'], 
                        $row['Excused'], $row['Excused Start Date'], $row['Excused End Date'], $row['Excused Reason'],
                        $row['Organization'], $row['Level 1'], $row['Level 2'], $row['Level 3'], $row['Level 4'] ));
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);

    }

    public function getOrganizations(Request $request) {

        $orgs = OrganizationTree::orderby('name','asc')->select('id','name')
            ->where('level',0)
            ->when( $request->q , function ($q) use($request) {
                return $q->whereRaw("LOWER(name) LIKE '%" . strtolower($request->q) . "%'");
            })
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                      ->from('admin_orgs')
                      ->whereColumn('admin_orgs.organization', 'organization_trees.organization')
                      ->where('admin_orgs.user_id', '=', Auth::id() );
            })
            ->get();

        $formatted_orgs = [];
        foreach ($orgs as $org) {
            $formatted_orgs[] = ['id' => $org->id, 'text' => $org->name ];
        }

        return response()->json($formatted_orgs);
    } 

    public function getPrograms(Request $request) {

        $level0 = $request->level0 ? OrganizationTree::where('id',$request->level0)->first() : null;

        $orgs = OrganizationTree::orderby('name','asc')->select(DB::raw('min(id) as id'),'name')
            ->where('level',1)
            ->when( $request->q , function ($q) use($request) {
                return $q->whereRaw("LOWER(name) LIKE '%" . strtolower($request->q) . "%'");
                })
            ->when( $level0 , function ($q) use($level0) {
                return $q->where('organization', $level0->name );
            })
            ->whereExists(function ($query) use($request) {
                $query->select(DB::raw(1))
                    ->from('admin_orgs')
                    ->when( $request->level0, function ($q) { 
                        return $q->whereColumn('admin_orgs.organization', 'organization_trees.organization');
                    })
                    ->whereColumn('admin_orgs.level1_program', 'organization_trees.level1_program')
                    ->where('admin_orgs.user_id', '=', Auth::id() );
            })
            ->groupBy('name')
            ->get();

        $formatted_orgs = [];
        foreach ($orgs as $org) {
            $formatted_orgs[] = ['id' => $org->id, 'text' => $org->name ];
        }

        return response()->json($formatted_orgs);
    } 

    public function getDivisions(Request $request) {

        $level0 = $request->level0 ? OrganizationTree::where('id', $request->level0)->first() : null;
        $level1 = $request->level1 ? OrganizationTree::where('id', $request->level1)->first() : null;

        $orgs = OrganizationTree::orderby('name','asc')->select(DB::raw('min(id) as id'),'name')
            ->where('level',2)
            ->when( $request->q , function ($q) use($request) {
                return $q->whereRaw("LOWER(name) LIKE '%" . strtolower($request->q) . "%'");
                })
            ->when( $level0 , function ($q) use($level0) {
                return $q->where('organization', $level0->name) ;
            })
            ->when( $level1 , function ($q) use($level1) {
                return $q->where('level1_program', $level1->name );
            })
            ->whereExists(function ($query) use($request) {
                $query->select(DB::raw(1))
                    ->from('admin_orgs')
                    ->when( $request->level0, function ($q) { 
                        return $q->whereColumn('admin_orgs.organization', 'organization_trees.organization');
                    })
                    ->when( $request->level1, function ($q) { 
                        return $q->whereColumn('admin_orgs.level1_program', 'organization_trees.level1_program');
                    })
                    ->whereColumn('admin_orgs.level2_division', 'organization_trees.level2_division')
                    ->where('admin_orgs.user_id', '=', Auth::id() );
            })
            ->groupBy('name')
            ->limit(300)
            ->get();


        $formatted_orgs = [];
        foreach ($orgs as $org) {
            $formatted_orgs[] = ['id' => $org->id, 'text' => $org->name ];
        }

        return response()->json($formatted_orgs);
    } 

    public function getBranches(Request $request) {

        $level0 = $request->level0 ? OrganizationTree::where('id', $request->level0)->first() : null;
        $level1 = $request->level1 ? OrganizationTree::where('id', $request->level1)->first() : null;
        $level2 = $request->level2 ? OrganizationTree::where('id', $request->level2)->first() : null;

        $orgs = OrganizationTree::orderby('name','asc')->select(DB::raw('min(id) as id'),'name')
            ->where('level',3)
            ->when( $request->q , function ($q) use($request) {
                return $q->whereRaw("LOWER(name) LIKE '%" . strtolower($request->q) . "%'");
                })
            ->when( $level0 , function ($q) use($level0) {
                return $q->where('organization', $level0->name) ;
            })
            ->when( $level1 , function ($q) use($level1) {
                return $q->where('level1_program', $level1->name );
            })
            ->when( $level2 , function ($q) use($level2) {
                return $q->where('level2_division', $level2->name );
            })
            ->whereExists(function ($query) use($request) {
                $query->select(DB::raw(1))
                    ->from('admin_orgs')
                    ->when( $request->level0, function ($q) { 
                        return $q->whereColumn('admin_orgs.organization', 'organization_trees.organization');
                    })
                    ->when( $request->level1, function ($q) { 
                        return $q->whereColumn('admin_orgs.level1_program', 'organization_trees.level1_program');
                    })
                    ->when( $request->level2, function ($q) { 
                        return $q->whereColumn('admin_orgs.level2_division', 'organization_trees.level2_division');
                    })
                    ->whereColumn('admin_orgs.level3_branch',  'organization_trees.level3_branch')
                    //   ->whereColumn('admin_orgs.level4', 'organization_trees.level4')
                      ->where('admin_orgs.user_id', '=', Auth::id() );
            })
            ->groupBy('name')
            ->limit(300)
            ->get();

        $formatted_orgs = [];
        foreach ($orgs as $org) {
            $formatted_orgs[] = ['id' => $org->id, 'text' => $org->name ];
        }

        return response()->json($formatted_orgs);
    } 

    public function getLevel4(Request $request) {

        $level0 = $request->level0 ? OrganizationTree::where('id', $request->level0)->first() : null;
        $level1 = $request->level1 ? OrganizationTree::where('id', $request->level1)->first() : null;
        $level2 = $request->level2 ? OrganizationTree::where('id', $request->level2)->first() : null;
        $level3 = $request->level3 ? OrganizationTree::where('id', $request->level3)->first() : null;

        $orgs = OrganizationTree::orderby('name','asc')->select(DB::raw('min(id) as id'),'name')
            ->where('level',4)
            ->when( $request->q , function ($q) use($request) {
                return $q->whereRaw("LOWER(name) LIKE '%" . strtolower($request->q) . "%'");
                })
            ->when( $level0 , function ($q) use($level0) {
                return $q->where('organization', $level0->name) ;
            })
            ->when( $level1 , function ($q) use($level1) {
                return $q->where('level1_program', $level1->name );
            })
            ->when( $level2 , function ($q) use($level2) {
                return $q->where('level2_division', $level2->name );
            })
            ->when( $level3 , function ($q) use($level3) {
                return $q->where('level3_branch', $level3->name );
            })
            ->whereExists(function ($query) use($request) {
                $query->select(DB::raw(1))
                    ->from('admin_orgs')
                    ->when( $request->level0, function ($q) { 
                        return $q->whereColumn('admin_orgs.organization', 'organization_trees.organization');
                    })
                    ->when( $request->level1, function ($q) { 
                        return $q->whereColumn('admin_orgs.level1_program', 'organization_trees.level1_program');
                    })
                    ->when( $request->level2, function ($q) { 
                        return $q->whereColumn('admin_orgs.level2_division', 'organization_trees.level2_division');
                    })
                    ->when( $request->level3, function ($q) { 
                        return $q->whereColumn('admin_orgs.level3_branch',  'organization_trees.level3_branch');
                    })
                    ->whereColumn('admin_orgs.level4', 'organization_trees.level4')
                    ->where('admin_orgs.user_id', '=', Auth::id() );
            })
            ->groupBy('name')
            ->limit(300)
            ->get();

        $formatted_orgs = [];
        foreach ($orgs as $org) {
            $formatted_orgs[] = ['id' => $org->id, 'text' => $org->name ];
        }

        return response()->json($formatted_orgs);
    } 


    public function preservedInputParams(Request $request) 
    {
        $errors = session('errors');
        if ($errors) {
            $old = session()->getOldInput();

            $request->dd_level0 = isset($old['dd_level0']) ? $old['dd_level0'] : null;
            $request->dd_level1 = isset($old['dd_level1']) ? $old['dd_level1'] : null;
            $request->dd_level2 = isset($old['dd_level2']) ? $old['dd_level2'] : null;
            $request->dd_level3 = isset($old['dd_level3']) ? $old['dd_level3'] : null;
            $request->dd_level4 = isset($old['dd_level4']) ? $old['dd_level4'] : null;

        } 

        // no validation and move filter variable to old 
        if ($request->btn_search) {

            $filter = '&dd_level0='.$request->dd_level0. 
                        '&dd_level1='.$request->dd_level1. 
                        '&dd_level2='.$request->dd_level2. 
                        '&dd_level3='.$request->dd_level3. 
                        '&dd_level4='.$request->dd_level4; 

            session()->put('_old_input', [
                'dd_level0' => $request->dd_level0,
                'dd_level1' => $request->dd_level1,
                'dd_level2' => $request->dd_level2,
                'dd_level3' => $request->dd_level3,
                'dd_level4' => $request->dd_level4,
                'filter' => $filter, 
            ]);
        } else {

            session()->put('_old_input', [
                'filter' => '', 
            ]);

        }

    }
    
}
