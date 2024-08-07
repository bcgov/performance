<x-side-layout title="{{ __('Employee List - Performance Development Platform') }}">
    <div name="header" class="container-header p-n2 "> 
        <div class="container-fluid">
            <h3>Employee List</h3>
            @include('shared.employeelists.partials.tabs')
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            {{-- <div class="h4">{{__('Past Employees')}}</div> --}}
            @include('shared.employeelists.partials.filter')
            <div class="p-3"> 
                <table class="table table-bordered listtable table-striped" id="listtable" style="width: 100%; overflow-x: auto; "></table>
            </div>
        </div>    
    </div>   

    @push('css')
        <link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap4.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" >
        <link href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
        <link href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.dataTables.min.css" rel="stylesheet">
        <x-slot name="css">
            <style>
                .text-truncate-30 {
                    white-space: wrap; 
                    overflow: hidden;
                    text-overflow: ellipsis;
                    width: 30em;
                }
            
                .text-truncate-10 {
                    white-space: wrap; 
                    overflow: hidden;
                    text-overflow: ellipsis;
                    width: 5em;
                }

                #listtable_filter label {
                    display: none;
                }

                #listtable_wrapper .dt-buttons {
                    float: none;
                    text-align:right;
                }

                #listtable_wrapper .dataTables_processing {
                    top: 50px;
                }
            </style>
        </x-slot>
    @endpush

    @push('js')
        <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
        <script src="{{ asset('js/bootstrap-multiselect.min.js')}} "></script>
        <script type="text/javascript">
            $(document).ready(function() {
                $('#listtable').DataTable ( {
                    dom: 'lfrtip',
                    serverSide: true,
                    searching: true,
                    processing: true,
                    paging: true,
                    deferRender: true,
                    retrieve: true,
                    scrollCollapse: true,
                    scroller: true,
                    scrollX: true,
                    stateSave: true,
                    ajax: 
                    {
                        url: "{{ route(request()->segment(1).'.employeelists.getpastlist') }}",
                        data: function (d) 
                        {
                            d.dd_level0 = $('#dd_level0').val();
                            d.dd_level1 = $('#dd_level1').val();
                            d.dd_level2 = $('#dd_level2').val();
                            d.dd_level3 = $('#dd_level3').val();
                            d.dd_level4 = $('#dd_level4').val();
                            d.criteria = $('#criteria').val();
                            d.search_text = $('#search_text').val();
                        }
                    },
                    columns: 
                    [
                        {title: 'Employee ID', ariaTitle: 'Employee ID', target: 0, type: 'string', data: 'employee_id', name: 'employee_id', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Name', ariaTitle: 'Name', target: 0, type: 'string', data: 'employee_name', name: 'employee_name', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Name', ariaTitle: 'Name', target: 0, type: 'string', data: 'user_name', name: 'user_name', searchable: false, visible: false, className: 'dt-nowrap show-modal'},
                        {title: 'Email', ariaTitle: 'Email', target: 0, type: 'string', data: 'employee_email', name: 'employee_email', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Position #', ariaTitle: 'Position #', target: 0, type: 'string', data: 'position_number', name: 'position_number', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Reports To Name', ariaTitle: 'Reports To Name', target: 0, type: 'string', data: 'reporting_to_name', name: 'reporting_to_name', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Reports To Position #', ariaTitle: 'Reports To Position #', target: 0, type: 'string', data: 'reporting_to_position_number', name: 'reporting_to_position_number', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Status', ariaTitle: 'Status', target: 0, type: 'string', data: 'employee_status', name: 'employee_status', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Record #', ariaTitle: 'Record #', target: 0, type: 'string', data: 'empl_record', name: 'empl_record', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Classification', ariaTitle: 'Classification', target: 0, type: 'string', data: 'jobcode_desc', name: 'jobcode_desc', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Organization', ariaTitle: 'Organization', target: 0, type: 'string', data: 'organization', name: 'organization', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Level 1', ariaTitle: 'Level 1', target: 0, type: 'string', data: 'level1_program', name: 'level1_program', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Level 2', ariaTitle: 'Level 2', target: 0, type: 'string', data: 'level2_division', name: 'level2_division', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Level 3', ariaTitle: 'Level 3', target: 0, type: 'string', data: 'level3_branch', name: 'level3_branch', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Level 4', ariaTitle: 'Level 4', target: 0, type: 'string', data: 'level4', name: 'level4', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Dept', ariaTitle: 'Dept', target: 0, type: 'string', data: 'deptid', name: 'deptid', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Active Goals', ariaTitle: 'Active Goals', target: 0, type: 'string', data: 'activeGoals', name: 'activeGoals', searchable: false},
                        {title: 'Next Conversation', ariaTitle: 'Next Conversation', target: 0, type: 'date', data: 'nextConversationDue', name: 'nextConversationDue', searchable: false},
                        {title: 'Excused', ariaTitle: 'Excused', target: 0, type: 'string', data: 'excused', name: 'excused', searchable: false},
                        {title: 'Shared', ariaTitle: 'Shared', target: 0, type: 'string', data: 'shared', name: 'shared', searchable: false},
                        {title: 'Reports', ariaTitle: 'Reports', target: 0, type: 'string', data: 'reportees', name: 'reportees', searchable: false},
                        {title: 'Date Deleted', ariaTitle: 'Date Deleted', target: 0, type: 'date', data: 'date_deleted', name: 'date_deleted', searchable: false, className: 'dt-nowrap show-modal'},
                        {title: 'User ID', ariaTitle: 'User ID', target: 0, type: 'num', data: 'id', name: 'id', searchable: true, visible: false},
                    ],
                } );

                // add export button on right
                $("#listtable_filter").append('<button id="export-btn" value="export" class="dt-button buttons-csv buttons-html5">Export</button> ');

                $('#export-btn').on('click', function() {
                    let parray = encodeURIComponent(JSON.stringify([
                        $('#dd_level0').val(), 
                        $('#dd_level1').val(),
                        $('#dd_level2').val(),
                        $('#dd_level3').val(),
                        $('#dd_level4').val(),
                        $('#criteria').val(),
                        $('#search_text').val()
                    ]));
                    var export_url = "{{ route('sysadmin.employeelists.export-past', ':parray') }}";
                    export_url = export_url.replace(':parray', parray);
                    let _url = export_url;
                    window.location.href = _url;
                });

            } );
        </script>
    @endpush


</x-side-layout>
