@extends('adminlte::page')

@section('content_header')

@include('sysadmin.partials.tabs')

    <div class="d-flex mt-3">
        <h4>Auditing</h4>
        <div class="flex-fill"></div>

    </div>
@endsection
@section('content')

<div class="card">

    <div class="card-body pb-0 filter">
        <h2>Search Criteria</h2>

        <div class="form-row">
            <div class="form-group col-md-2">
                <label for="audit_user">
                    Audit User 
                </label>
                <input name="audit_user" placeholder="Name, Employee ID, iDir"  class="form-control" />
            </div>

            <div class="form-group col-md-2">
                <label for="start_time">Start Time</label>
                <input class="form-control datetime-range-filter" type="datetime-local"  name="start_time">
            </div>

            <div class="form-group col-md-2">
                <label for="end_time">End Time</label>
                <input class="form-control datetime-range-filter" type="datetime-local"  name="end_time">
            </div>

            <div class="form-group col-md-2">
                <label for="event_type">
                    Event Type
                </label>
                <select name="event_type"  value="" class="form-control">
                    <option value="">Select a event</option>
                    @foreach( $event_types as $key => $value)
                        <option value="{{ $key }}">{{ $value }}</option>
                    @endforeach 
                </select>
            </div>

            <div class="form-group col-md-2">
                <label for="auditable_type">
                    Audit Type
                </label>
                <select name="auditable_type"  value="" class="form-control">
                    <option value="">Select a audit type</option>
                    @foreach( $auditable_types as $key => $value)
                        <option value="{{ $value }}">{{ $value }}</option>
                    @endforeach 
                </select>
            </div>

            {{-- <div class="form-group col-md-1">
                <label for="search">
                    &nbsp;
                </label>
                <input type="button" id="refresh-btn" value="Refresh" class="form-control btn btn-primary" />
            </div>
            <div class="form-group col-md-1">
                <label for="search">
                    &nbsp;
                </label>
                <input type="button" id="reset-btn" value="Reset" class="form-control btn btn-secondary" />
            </div> --}}
        </div>

        <div class="form-row">

            <div class="form-group col-md-4">
                <label for="old_values">
                    Old Values 
                </label>
                <input name="old_values"   class="form-control" />
            </div>
            <div class="form-group col-md-4">
                <label for="new_values">
                    New Values 
                </label>
                <input name="new_values"  class="form-control" />
            </div>

            <div class="form-group col-md-1">
                <label for="search">
                    &nbsp;
                </label>
                <input type="button" id="refresh-btn" value="Refresh" class="form-control btn btn-primary" />
            </div>
            <div class="form-group col-md-1">
                <label for="search">
                    &nbsp;
                </label>
                <input type="button" id="reset-btn" value="Reset" class="form-control btn btn-secondary" />
            </div>

        </div>

    </div>    
    
    <div class="px-4"></div>

	<div class="card-body">

		<table class="table table-bordered" id="audit-table" style="width:100%">
			<thead>
				<tr>
                    <th>Tran ID </th>
                    <th>Audit User</th>
                    <th>Audit Time</th>
                    <th>Event Type</th>
                    <th>Audit Type</th>
                    <th>Audit ID</th>
                    <th>Old Value </th>
                    <th>New Value </th>
                    <th>Url</th>
                    <th>IP Address</th>
                    <th>User Agent</th>
                    
				</tr>
			</thead>
		</table>

	</div>
</div>


{{-- Modal Box  --}}

{{-- <div class="modal fade" id="audit-show-modal" tabindex="-1" role="dialog" aria-labelledby="auditModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-xl" role="document">
	  <div class="modal-content">
		<div class="modal-header bg-primary">
		  <h5 class="modal-title" id="auditModalLabel">Existing details</h5>
		  <button type="button" class="close" data-dismiss="modal" aria-label="Close">
			<span aria-hidden="true">&times;</span>
		  </button>
		</div>
		<div class="modal-body">

            
                <p class="px-2"><b>Status : </b>
                <span id="modal-status"></span>
                </p>


            <div class="form-group px-2">
                <label for="message">Log Message</label>
                <pre id="modal-message" class="border"></pre>
            </div>
            
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
        </div>
    
    </div>
</div> --}}

@endsection


@push('css')


    <link href="https://cdn.datatables.net/1.11.4/css/dataTables.bootstrap4.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/fixedheader/3.2.4/css/fixedHeader.dataTables.min.css" rel="stylesheet">
    <link href="{{ asset('vendor/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css') }}" rel="stylesheet">

	<style>
	/* #audit-table_filter label {
		text-align: right !important;
        padding-right: 10px;
	} */
    
    #audit-table_filter {
        display: none;
    }

    .dataTables_scrollBody {
        margin-bottom: 10px;
    }

</style>
@endpush


@push('js')

    <script src="https://cdn.datatables.net/1.11.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.4/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/fixedheader/3.2.4/js/dataTables.fixedHeader.min.js"></script>
    
    <script src="{{ asset('vendor/sweetalert2/sweetalert2.min.js') }}" ></script>

    <script>

    $(function() {

        $.ajaxSetup({
            headers: {
            'X-CSRF-TOKEN': '{{csrf_token()}}'
            }
        });
 
        // Datatables
        var oTable = $('#audit-table').DataTable({
            "scrollX": true,
            retrieve: true,
            "searching": true,
            processing: true,
            serverSide: true,
            // select: true,
            'order': [[ 0, 'desc']],
            fixedHeader: true,   
            fixedColumn: true,         
            ajax: {
                url: '{!! route('sysadmin.auditing.index') !!}',
                data: function (data) {
                    // data.term = $('#user').val();
                    data.audit_user = $("input[name='audit_user']").val();
                    data.event_type = $("select[name='event_type']").val();
                    data.auditable_type = $("select[name='auditable_type']").val();
                    data.start_time = $("input[name='start_time']").val();
                    data.end_time  = $("input[name='end_time']").val();
                    data.old_values  = $("input[name='old_values']").val();
                    data.new_values  = $("input[name='new_values']").val();
                }
            },
            columns: [
                {data: 'id', name: 'id', className: "dt-nowrap" },
                {data: 'audit_user.name', name: 'audit_user.name', className: "dt-nowrap" },
                {data: 'audit_timestamp', name: 'created_at', className: "dt-nowrap" },
                {data: 'event', name: 'event', className: "dt-nowrap" },
                {data: 'auditable_type_name',  name: 'auditable_type_name',  className: "dt-nowrap" },
                {data: 'auditable_id',  name: 'auditable_id'},
                {data: 'old_values', name: 'old_values', orderable: false, searchable: false},
                {data: 'new_values', name: 'new_values', orderable: false, searchable: false},
                {data: 'url', name: 'url',},
                {data: 'ip_address', name: 'ip_address', className: "dt-nowrap"},
                {data: 'user_agent', name: 'user_agent', render: function (data, type, row) {
                                return "<div style='width: 20em;'>" + data  + '</div>';
                    }
                },
                // {data: 'deleted_by', name: 'delete_by', orderable: false, searchable: false, className: "dt-nowrap"},
                // {data: 'deleted_at', name: 'delete_at', orderable: false, searchable: false, className: "dt-nowrap"},
            ],
            columnDefs: [
                    {
                            render: function (data, type, row) {
                                return "<div style='max-width: 40em;'>" + data  + '</div>';
                            },
                            targets: [6,7]
                    },
                 
            ],

        });
     

        $('#refresh-btn').on('click', function() {
            // oTable.ajax.reload(null, true);
            oTable.draw();
        });

        $('#reset-btn').on('click', function() {
            $("input[name='audit_user']").val('');
            $("select[name='event_type']").val('');
            $("select[name='auditable_type']").val('');
            $("input[name='start_time']").val('');
            $("input[name='end_time']").val('');
            $("input[name='old_values']").val('');
            $("input[name='new_values']").val('');

            oTable.search( '' ).columns().search( '' ).draw();
        });

        // // Model -- Show
    	// $(document).on("click", ".more-link , .show-audit" , function(e) {
		// 	e.preventDefault();

        //     id =  $(this).attr('data-id');
        //     $.ajax({
        //         method: "GET",
        //         url:  '/sysadmin/job-schedule-audit/' + id,
        //         dataType: 'json',
        //         success: function(data)
        //         {
        //             $('#auditModalLabel').html('Job : ' + data.id + ' (' + data.job_name + ')' );
        //             //  started at ' + data.start_time);
        //             $('#modal-status').html(data.status);
        //             $('#modal-message').html(data.details);
        //             $('#audit-show-modal').modal('show');
        //         },
        //         error: function(response) {
        //             console.log('Error');
        //         }
        //     });
    	// });

    });

    </script>
@endpush
