@extends('sysadmin.layout')
@section('tab-content')
<div>
    <div class="h4 p-3">{{__('Current Employees')}}</div>
    <div class="p-3">
        <div class="row">
            <div class="col">
                <p>Below is a list of all employees in the BC Public Service who are currently active in the performance development process.</p>
            </div>
        </div>
    </div>
</div>
<div>
    <div class="card card-primary shadow mb-3" style="overflow-x: auto;">
        <div class="d-flex" style="width: 2600px">
            <form action="" method="get" id="filter-menu">
                @csrf
                <table class="uk-table m-3">
                    <tbody>
                        @include('sysadmin.partials.organization_filter')
                        <tr style="text-align: left;" class="p-2 form-group">
                            <td style="text-align: left; width: 300px;" class="p-2 form-group">
                                <label for='jobTitles'>Job Titles</label>
                                <select class="form-control" name="jobTitles" id="jobTitles">
                                    <option value="all">All</option>
                                </select>
                            </td>
                            <td style="text-align: left; width: 300px; " class="p-2 form-group">
                                <label for='inactiveSince'>
                                    Active Dates
                                    <input type="text" class="form-control" name="activeSince" value="{{request()->activeSince ?? 'Any'}}">
                                </label>
                            </td>
                            <td style="text-align: left; width: 300px; " class="p-2 form-group">
                                <label for='searchText'>
                                    Search
                                    <input type="text" name="searchText" class="form-control" value="{{request()->searchText}}">
                                </label>
                            </td>
                            <td style="text-align: left; width: 200px; " class"p-2 form-group">
                                <button class="btn btn-primary mt-4 px-5" name="searchBtn2" id="searchBtn2">Filter</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </form>
        </div>
        <div id="collapseOne" class="collapse {{$iEmpl ? 'show' : ''}}" aria-labelledby="headingOne" data-parent="#accordionLibrary">
            <div class="table table-wrapper" style="width: 2600px">
                <div class="md-card-content" style="overflow-x: auto;">
                    <table class="uk-table m-3">
                        <thead>
                            <tr>
                                <th style="text-align: left; width: 300px; "> Employee Name</th>
                                <th style="text-align: left; width: 300px; "> Job Title</th>
                                <th style="text-align: left; width: 400px; "> Organization</th>
                                <th style="text-align: left; width: 400px; "> Organization Level 1</th>
                                <th style="text-align: left; width: 400px; "> Organization Level 2</th>
                                <th style="text-align: left; width: 400px; "> Organization Level 3</th>
                                <th style="text-align: left; width: 400px; "> Organization Level 4</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($iEmpl as $o)
                            <tr>
                                <td style="text-align: left; width: 300px; ">
                                    <a href='# class="edit-goal-detail highlighter" data-id="{{$o->guid}}'>{{ $o->employee_name }}</a>
                                </td>
                                <td style="text-align: left; width: 300px; ">
                                    <a href='# class="edit-goal-detail highlighter" data-id="{{$o->guid}}'>{{ $o->job_title }}</a>
                                </td>
                                <td style="text-align: left; width: 400px; ">
                                    <a href='# class="edit-goal-detail highlighter" data-id="{{$o->guid}}'>{{ $o->organization }}</a>
                                </td>
                                <td style="text-align: left; width: 400px; ">
                                    <a href='# class="edit-goal-detail highlighter" data-id="{{$o->guid}}'>{{ $o->level1_program }}</a>
                                </td>
                                <td style="text-align: left; width: 400px; ">
                                    <a href='# class="edit-goal-detail highlighter" data-id="{{$o->guid}}'>{{ $o->level2_division }}</a>
                                </td>
                                <td style="text-align: left; width: 400px; ">
                                    <a href='# class="edit-goal-detail highlighter" data-id="{{$o->guid}}'>{{ $o->level3_branch }}</a>
                                </td>
                                <td style="text-align: left; width: 400px; ">
                                    <a href='# class="edit-goal-detail highlighter" data-id="{{$o->guid}}'>{{ $o->level4 }}</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    {{ $iEmpl->links() }}
</div>

@include('sysadmin.partials.organization_script')

@push('css')
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
@endpush
@push('js')
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script>
    // $(document).on('click', '#searchBtn', function(e) {
    //     $("#filter-menu").submit();
    // });
    // $('#filter-menu select, #filter-menu input').change(function () {
    //     $("#filter-menu").submit();
    // });

    $('input[name="activeSince"]').daterangepicker({
        // autoUpdateInput: false,
        autoUpdateInput: true,
        locale: {
            cancelLabel: 'Any',
            format: 'MMM DD, YYYY'
        }
    }).on('apply.daterangepicker', function(ev, picker) {
        $(this).val(picker.startDate.format('MMM DD, YYYY') + ' - ' + picker.endDate.format('MMM DD, YYYY'));
    }).on('cancel.daterangepicker', function(ev, picker) {
        $('input[name="activeSince"]').val('Any');
    });
</script>
@endpush

@endsection
