<x-side-layout title="{{ __('Dashboard') }}">
    <div name="header" class="container-header p-n2 "> 
        <div class="container-fluid">
            <h3>Shared Employees</h3>
            @include('shared.employeeshares.partials.tabs')
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            @include('shared.employeeshares.partials.loader')
            <div class="p-3">  
                <table class="table table-bordered generictable table-striped" id="generictable" style="width: 100%; overflow-x: auto; "></table>
            </div>
        </div>    
    </div>   

    @include('shared.employeeshares.partials.share-edit-modal')

    @push('css')
        <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <x-slot name="css">
            <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap4.min.css" rel="stylesheet">
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
                
                #generictable_filter label {
                    text-align: right !important;
                }
            </style>
        </x-slot>
    @endpush

    @push('js')
        <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script src="{{ asset('js/bootstrap-multiselect.min.js')}} "></script>
        <script type="text/javascript">

            function showModal ($id) {
                $("#edit-modal").modal('show');
            }

			$(document).ready( function() {

                $('#generictable').DataTable ( {
                    processing: true,
                    serverSide: true,
                    scrollX: true,
                    stateSave: true,
                    deferRender: true,
                    ajax: {
                        url: "{{ route(request()->segment(1) . '.employeeshares.manageindexlist') }}",
                        type: 'GET',
                        data: function(d) {
                            d.dd_level0 = $('#dd_level0').val();
                            d.dd_level1 = $('#dd_level1').val();
                            d.dd_level2 = $('#dd_level2').val();
                            d.dd_level3 = $('#dd_level3').val();
                            d.dd_level4 = $('#dd_level4').val();
                            d.criteria = $('#criteria').val();
                            d.search_text = $('#search_text').val();
                        }
                    },
                    columns: [
                        {title: 'ID', ariaTitle: 'ID', target: 0, type: 'string', data: 'employee_id'
                            , name: 'employee_demo.employee_id', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Name', ariaTitle: 'Name', target: 0, type: 'string', data: 'employee_name'
                            , name: 'employee_demo.employee_name', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Delegate ID', ariaTitle: 'Delegate ID', target: 0, type: 'string', data: 'delegate_ee_id'
                            , name: 'e2.employee_id', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Delegate Name', ariaTitle: 'Delegate Name', target: 0, type: 'string', data: 'delegate_ee_name'
                            , name: 'e2.employee_name', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Shared Item', ariaTitle: 'Shared Item', target: 0, type: 'string', data: 'shared_item'
                            , name: 'shared_profiles.shared_item', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Classification', ariaTitle: 'Classification', target: 0, type: 'string', data: 'jobcode_desc'
                            , name: 'employee_demo.jobcode_desc', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Organization', ariaTitle: 'Organization', target: 0, type: 'string', data: 'organization'
                            , name: 'employee_demo.organization', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Level 1', ariaTitle: 'Level 1', target: 0, type: 'string', data: 'level1_program'
                            , name: 'employee_demo.level1_program', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Level 2', ariaTitle: 'Level 2', target: 0, type: 'string', data: 'level2_division'
                            , name: 'employee_demo.level2_division', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Level 3', ariaTitle: 'Level 3', target: 0, type: 'string', data: 'level3_branch'
                            , name: 'employee_demo.level3_branch', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Level 4', ariaTitle: 'Level 4', target: 0, type: 'string', data: 'level4'
                            , name: 'employee_demo.level4', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Dept', ariaTitle: 'Dept', target: 0, type: 'string', data: 'deptid'
                            , name: 'employee_demo.deptid', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Shared By', ariaTitle: 'Shared By', target: 0, type: 'string', data: 'created_name'
                            , name: 'ec.employee_name', searchable: true, className: 'dt-nowrap show-modal'},
                        {title: 'Created At', ariaTitle: 'Created At', target: 0, type: 'string', data: 'created_at'
                            , name: 'shared_profiles.created_at', searchable: false, className: 'dt-nowrap show-modal'},
                        {title: 'Updated At', ariaTitle: 'Updated At', target: 0, type: 'string', data: 'updated_at'
                            , name: 'shared_profiles.updated_at', searchable: false, className: 'dt-nowrap show-modal'},
                        {title: 'Action', ariaTitle: 'Action', target: 0, type: 'string', data: 'action', name: 'action', orderable: false, searchable: false, className: 'dt-nowrap'},
                        {title: 'Shared Profile ID', ariaTitle: 'Shared Profile ID', target: 0, type: 'num', data: 'shared_profile_id'
                            , name: 'shared_profiles.shared_profile_id', searchable: false, visible: false},
                    ]
                } );

                $('#btn_search').click(function(e) {
                    e.preventDefault();
                    console.log('btn_search clicked');
					$('#generictable').DataTable().rows().invalidate().draw();
                } );

                $('#cancelButton').on('click', function(e) {
                     e.preventDefault();
                    if($.fn.dataTable.isDataTable('#generictable')) {
                        $('#generictable').DataTable().clear();
                        $('#generictable').DataTable().destroy();
                        $('#generictable').empty();
                    }
                    $('#generictable').DataTable().rows().invalidate().draw();
                });

                $('#removeButton').on('click', function(e) {

                });

                $(window).on('beforeunload', function(e){
                    $('#pageLoader').show();
                });

            });

        </script>
    @endpush

</x-side-layout>