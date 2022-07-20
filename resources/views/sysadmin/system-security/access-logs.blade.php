<x-side-layout title="{{ __('Dashboard') }}">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-primary leading-tight" role="banner">
            Access Log
        </h2> 
		@include('sysadmin.system-security.partials.tabs')
    </x-slot>

<div class="card">

    <div class="card-body pb-0">
        <h2>Search Criteria</h2>

        <div class="form-row">
            <div class="form-group col-md-3">
                <label for="user">
                    User Name / IDIR / Employee ID
                </label>
                <input name="user" id="user"  class="form-control" />
            </div>

            <div class="form-group col-md-2">
                <label for="login_at_from">Login at (From)</label>
                <input class="form-control date-range-filter" type="date" id="login_at_from" name="login_at_from">
            </div>

            <div class="form-group col-md-2">
                <label for="login_at_to">Login at (To)</label>
                <input class="form-control date-range-filter" type="date" id="login_at_to" name="login_at_to">
            </div>

            <div class="form-group col-md-2">
                <label for="login_method">
                    Login Method
                </label>
                <select name="login_method" id="login_method" value="" class="form-control">
                    <option value="">Select a method</option>
                    <option value="Laravel UI">Laravel</option>
                    <option value="Keycloak">IDIR (Keycloak)</option>
                </select>
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

		<table class="table table-bordered" id="accesslog-table" style="width:100%">
			<thead>
				<tr>
                    <th>Tran ID </th>
                    <th>Login at </th>
                    <th>User Name</th>
                    <th>IDIR</th>
                    <th>Employee ID</th>
                    <th>User ID</th>
                    <th>Login Method</th>
                    <th>Identity Provider</th>
                    <th>Login IP</th>
                    <th>Logout at</th>
				</tr>
			</thead>
		</table>

	</div>
</div>


<x-slot name="css">

    <link href="https://cdn.datatables.net/1.11.4/css/dataTables.bootstrap4.min.css" rel="stylesheet">

	<style>
    #accesslog-table_filter {
        display: none;
    }

    .dataTables_scrollBody {
        margin-bottom: 10px;
    }
    </style>

</x-slot>


<x-slot name="js">

    <script src="https://cdn.datatables.net/1.11.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.4/js/dataTables.bootstrap4.min.js"></script>

    <script>

    $(function() {
 
        // Datatables
        var oTable = $('#accesslog-table').DataTable({
            "scrollX": true,
            retrieve: true,
            "searching": true,
            processing: true,
            serverSide: true,
            // select: true,
            'order': [[ 0, 'desc']],
            
            ajax: {
                url: '{!! route('sysadmin.system_security.access_logs') !!}',
                data: function (data) {
                    data.term = $('#user').val();
                    data.login_at_from = $('#login_at_from').val();
                    data.login_at_to  = $('#login_at_to').val();
                }
            },
            columns: [
                {data: 'id', name: 'id', className: "dt-nowrap" },
                {data: 'login_at', name: 'login_at', className: "dt-nowrap" },
                {data: 'name', name: 'users.name', className: "dt-nowrap" },
                {data: 'idir',  name: 'users.idir',  className: "dt-nowrap" },
                {data: 'employee_id',  name: 'users.employee_id',  className: "dt-nowrap" },
                {data: 'user_id',  name: 'user_id',  className: "dt-nowrap" },
                {data: 'login_method', name: 'login_method'},
                {data: 'identity_provider', name: 'identity_provider'},
                {data: 'login_ip', name: 'login_ip'},
                {data: 'logout_at', name: 'logout_at'},

            ],
            columnDefs: [
                    {
                        // width: '5em',
                        // targets: [0]
                    },
            ],


        });



        $('#user').on('keyup change', function () {
            oTable.draw();
        });

        $('#login_method').on('change', function () {
            oTable.columns( 'login_method:name' ).search( this.value ).draw();            
        });

        $('.date-range-filter').on('change', function () {
            oTable.draw();
        });

        $('#reset-btn').on('click', function() {
            $('#user').val('');
            $('.date-range-filter').val('');
            $('#login_method').val('');

            oTable.search( '' ).columns().search( '' ).draw();
        });

    });

    </script>
</x-slot>    


</x-side-layout>