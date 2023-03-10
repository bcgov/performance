<x-side-layout title="{{ __('Goal Bank - Performance Development Platform') }}">
    <div name="header" class="container-header p-n2 "> 
        <div class="container-fluid">
            <h3>Goal Bank</h3>
            @include('shared.goalbank.partials.tabs')
        </div>
    </div>

	<p class="px-3">Create a goal for employees to use in their own profile. Goals can be suggested (for example, a learning goal to help increase skill or capacity in a relevant area) or mandatory (for example, a work goal detailing a new priority that all employees are responsible for).</p>
	<p class="px-3">You can choose to assign the goal to one or more individuals based on things like name, employee ID, and classification, or you can assign the goal to one or more business units. If you assign to individuals, the goal will remain in their goal bank even if their position changes in the future. It stays with the individual. If you assign to business units, the goal will appear for current and future employees of that business unit and will disappear for an employee that leaves the business unit. It stays with the business unit.</p>
	<p class="px-3">Employees will be notified when a new goal has been added to their Goal Bank.</p>

	<form id="notify-form" action="{{ route(request()->segment(1).'.goalbank.savenewgoal') }}" method="post">
		@csrf

        <div class="container-fluid">
                    @if(Session::has('message'))
                    <div class="col-12">                    
                        <div class="alert alert-danger" style="display:">
                            <i class="fa fa-info-circle"></i> {{ Session::get('message') }}
                        </div>
                    </div>
                    @endif
			<br>
			<h6 class="text-bold">Step 1. Enter Goal Details</h6>
			<br>

			<div class="row">
				<div class="col col-md-2">
					<b> Goal Type </b>
					<i class="fa fa-info-circle" data-trigger='click' data-toggle="popover" data-placement="right" data-html="true" data-content="{{$type_desc_str}}"> </i>
					<x-dropdown id="goal_type_id" :list="$goalTypes" name="goal_type_id" data-toggle="tooltip" />
				</div>
				<div class="col col-md-8">
					<b> Goal Title </b>
					<i class="fa fa-info-circle" data-trigger='click' data-toggle="popover" data-placement="right" data-html="true" data-content="A short title (1-3 words) used to reference the goal throughout the Performance Development Platform."> </i>
					<x-input name="title" />
					@if(session()->has('title_miss'))
                                            <small class="text-danger">The title field is required</small>
                                        @endif
				</div>
				<div class="col col-md-2">
					<x-dropdown :list="$mandatoryOrSuggested" label="Mandatory/Suggested" name="is_mandatory" :selected="request()->is_mandatory" />
				</div>
			</div>
			<div class="row">
				<div class="col-md-4">
					<b>Tags</b>
					<i class="fa fa-info-circle" id="tags_label" data-trigger='click' data-toggle="popover" data-placement="right" data-html="true" data-content="Tags help to more accurately identity, sort, and report on your goals. You can add more than one tag to a goal. The list of tags will change and grow over time. <br/><br/>Don't see the goal tag you are looking for? <a href='mailto:performance.development@gov.bc.ca?subject=Suggestion for New Goal Tag'>Suggest a new goal tag</a>."></i>					
					<!-- <x-dropdown :list="$tags" data-tooltip-trigger='hover' data-toggle="tooltip" name="tag_ids[]" id="tags" class="tags" multiple/>					 -->
					<x-dropdown :list="$tags" name="tag_ids[]" id="tags" class="tags" multiple/>						
					@if(session()->has('tags_miss'))
                                            <small class="text-danger">The tags field is required</small>
                                        @endif
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<b>Goal Description</b>
					<p>
						Each goal should include a description of <b>WHAT</b>  
						<i class="fa fa-info-circle" data-trigger="click" data-toggle="popover" data-placement="right" data-html="true" data-content='A concise opening statement of what you plan to achieve. For example, "My goal is to deliver informative Performance Development sessions to ministry audiences".'> </i> you will accomplish, <b>WHY</b> 
						<i class="fa fa-info-circle" data-trigger="click" data-toggle="popover" data-placement="right" data-html="true" data-content='Why this goal is important to you and the organization (value of achievement). For example, "This will improve the consistency and quality of the employee experience across the BCPS".'> </i> it is important,, and <b>HOW</b> 
						<i class="fa fa-info-circle" data-trigger="click" data-toggle="popover" data-placement="right" data-html="true" data-content='A few high level steps to achieve your goal. For example, "I will do this by working closely with ministry colleagues to develop presentations that respond to the needs of their employees in each aspect of the Performance Development process".'> </i> you will achieve it. 
					</p>
					<x-textarea id="what" name="what"/>
					@if(session()->has('what_miss'))
                                            <small class="text-danger">The description field is required</small>
                                        @endif
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<b>Measures of Success</b>
					<i class="fa fa-info-circle" data-trigger='click' data-toggle="popover" data-placement="right" data-html="true" data-content='A qualitative or quantitative measure of success for your goal. For example, "Deliver a minimum of 2 sessions per month that reach at least 100 people"'> </i>
					<x-textarea name="measure_of_success" />
					<small class="text-danger error-measure_of_success"></small>
				</div>
			</div>
			<div class="row">
				<div class="col-md-2">
					<x-input label="Start Date " class="error-start" type="date" name="start_date"  />
					<small  class="text-danger error-start_date"></small>
				</div>
				<div class="col-md-2">
					<x-input label="End Date " class="error-target" type="date" name="target_date"  />
					<small  class="text-danger error-target_date"></small>
				</div>
			</div>
			<div class="row">
				<div class="col col-md-2">
					<b> Display Name </b>
					<i class="fa fa-info-circle" data-trigger='click' data-toggle="popover" data-placement="right" data-html="true" data-content="Organizational title to display when listing in Goal Bank."> </i>
					<x-input name="display_name" />
				</div>
			</div>
		</div>

        <div class="container-fluid">
			<br>
			<h6 class="text-bold">Step 2. Select audience</h6>
			<br>

			<div class="card col-md-4" >
				<div class="card-body">
					<div class="row">
						<div class="col">
							<label>
								<input type="radio" id="opt_audience1" name="opt_audience" value="byEmp" checked> Individual(s)
							</label>
						</div>
						<div class="col">
							<label>
								<input type="radio" id="opt_audience2" name="opt_audience" value="byOrg"> Business Unit(s)
							</label>
						</div>
					</div>
				</div>
			</div>

			<input type="hidden" id="selected_emp_ids" name="selected_emp_ids" value="">
			<input type="hidden" id="selected_org_nodes" name="selected_org_nodes" value="">
			<input type="hidden" id="eselected_emp_ids" name="eselected_emp_ids" value="">
			<input type="hidden" id="eselected_org_nodes" name="eselected_org_nodes" value="">

			@include('shared.goalbank.partials.filter')
			@include('shared.goalbank.partials.filter2')

			<div class="pl-2" id='itemgroup1'>
				<nav>
					<div class="nav nav-tabs" id="nav-tab" role="tablist">
						<a class="nav-item nav-link active" id="nav-list-tab" data-toggle="tab" href="#nav-list" role="tab" aria-controls="nav-list" aria-selected="true">List</a>
						<a class="nav-item nav-link" id="nav-tree-tab" data-toggle="tab" href="#nav-tree" role="tab" aria-controls="nav-tree" aria-selected="false">Tree</a>
					</div>
				</nav>
				<div class="tab-content" id="nav-tabContent">
					<div class="tab-pane fade show active" id="nav-list" role="tabpanel" aria-labelledby="nav-list-tab">
						@include('shared.goalbank.partials.recipient-list')
					</div>
					<div class="tab-pane fade" id="nav-tree" role="tabpanel" aria-labelledby="nav-tree-tab" loaded="">
						<div class="mt-2 fas fa-spinner fa-spin fa-3x fa-fw loading-spinner" id="tree-loading-spinner" role="status" style="display:none">
							<span class="sr-only">Loading...</span>
						</div>
					</div>
				</div>
			</div>

			<div class="pl-2" id='itemgroup2'>
				<nav>
					<div class="nav nav-tabs" id="enav-tab" role="tablist">
						<a class="nav-item nav-link" id="enav-tree-tab" data-toggle="tab" href="#enav-tree" role="tab" aria-controls="enav-tree" aria-selected="false">Tree</a>
					</div>
				</nav>
				<div id="enav-tree" aria-labelledby="enav-tree-tab" loaded="loaded">
					<div class="mt-2 fas fa-spinner fa-spin fa-3x fa-fw loading-spinner" id="etree-loading-spinner" role="status" style="display:none">
						<span class="sr-only">Loading...</span>
					</div>
				</div>
			</div>
		</div>

        <div class="container-fluid">
			<br>
			<h6 class="text-bold">Step 3. Finish</h6>
			<br>
			<div class="row">
				<div class="col-md-3 mb-2">
					<button class="btn btn-primary mt-2" id="obtn_send" type="button" onclick="confirmSaveChangesModal()" name="btn_confirm" value="btn_confirm">Add Goal</button>
					<button class="btn btn-secondary mt-2">Cancel</button>
				</div>
			</div>
		</div>

		<!----modal starts here--->
		<div id="saveGoalModal" class="modal" role='dialog'>
			<div class="modal-dialog">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Confirmation</h5>
						<button type="button" class="close" data-dismiss="modal" aria-label="Close">
							<span aria-hidden="true">&times;</span>
						</button>
					</div>
					<div class="modal-body">
						<p>Default ?</p>
					</div>
					<div class="modal-footer">
						<button class="btn btn-primary mt-2" type="submit" id="btn_send" name="btn_send" value="btn_send">Add New Goal</button>
						<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
					</div>
					
				</div>
			</div>
		</div>
		<!--Modal ends here--->	

	</form>

	<h6 class="m-20">&nbsp;</h6>
	<h6 class="m-20">&nbsp;</h6>
	<h6 class="m-20">&nbsp;</h6>

    @push('css')
        <link rel="stylesheet" href="{{ asset('css/bootstrap-multiselect.min.css') }}">
    @endpush

	<x-slot name="css">
		<style>

			.select2-container .select2-selection--single {
				height: 38px !important;
			}EmployeeID

			.select2-container--default .select2-selection--single .select2-selection__arrow {
				height: 38px !important;
			}

			.pageLoader{
				/* background: url(../images/loader.gif) no-repeat center center; */
				position: fixed;
				top: 0;
				left: 0;
				height: 100%;
				width: 100%;
				z-index: 9999999;
				background-color: #ffffff8c;

			}

			.pageLoader .spinner {
				/* background: url(../images/loader.gif) no-repeat center center; */
				position: fixed;
				top: 25%;
				left: 47%;
				/* height: 100%;
				width: 100%; */
				width: 10em;
				height: 10em;
				z-index: 9000000;
			}

		</style>
	</x-slot>

	<x-slot name="js">
		<script src="{{ asset('js/bootstrap-multiselect.min.js')}} "></script>
		<script src="//cdn.ckeditor.com/4.17.2/standard/ckeditor.js"></script>
		<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
		<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script>
		

		<script>				
				$('body').popover({
					selector: '[data-toggle]',
					trigger: 'click',
				});
                
				$('.modal').popover({
					selector: '[data-toggle-select]',
					trigger: 'click',
				});

				$(".tags").multiselect({
                	enableFiltering: true,
                	enableCaseInsensitiveFiltering: true,
					// nonSelectedText: null,
            	});

				$('body').on('click', function (e) {
                $('[data-toggle=popover]').each(function () {
                    // hide any open popovers when the anywhere else in the body is clicked
                    if (!$(this).is(e.target) && $(this).has(e.target).length === 0 && $('.popover').has(e.target).length === 0) {
                        $(this).popover('hide');
                    }
                	});
            	});	
				$('body').on('click', function (e) {
                $('[data-toggle=dropdown]').each(function () {
                    // hide any open popovers when the anywhere else in the body is clicked
                    if (!$(this).is(e.target) && $(this).has(e.target).length === 0 && $('.popover').has(e.target).length === 0) {
                        $(this).popover('hide');
                    }
                	});
            	});	

		</script>


		<script>
			let g_matched_employees = {!!json_encode($matched_emp_ids)!!};
			let g_selected_employees = {!!json_encode($old_selected_emp_ids)!!};
			let g_selected_orgnodes = {!!json_encode($old_selected_org_nodes)!!};
			let eg_selected_orgnodes = {!!json_encode($eold_selected_org_nodes)!!};
			let g_employees_by_org = [];

			function confirmSaveChangesModal() {
                            
                                $('#obtn_send').prop('disabled',true);
                            
				let count = 0;
				if($('#opt_audience1').prop('checked')) {
					count = g_selected_employees.length;
				};
				if($('#opt_audience2').prop('checked')) {
					count = g_selected_orgnodes.length;
				};
				if (count == 0) {
					$('#saveGoalModal .modal-body p').html('Are you sure you want to create the goal without an audience?');
				} else {
					$('#saveGoalModal .modal-body p').html('Are you sure you want to create the goal and assign to the selected audience?');
				}
                                
                                
                                
				$('#saveGoalModal').modal();
			}

			$(document).ready(function(){

				$('#eblank5th').hide();
				$('#ecriteria_group').hide();
				$('#esearch_text_group').hide();

				switchTree();
                                
                                
                                $( "#btn_send" ).click(function() {
                                    $('#saveGoalModal').modal('toggle');
                                });

				function switchTree(){
					if($('#opt_audience2').prop('checked')) {
						$('#filter1').hide();
						$('#nav-tab').hide();
						$('#nav-tabContent').hide();
						$('#nav-list').hide();
						$('#nav-tree').hide();
						
						$('#filter2').show();
						$('#enav-tab').show();
						$('#enav-tree').show();
					} else {
						$('#filter1').show();
						$('#nav-tab').show();
						$('#nav-tabContent').show();
						$('#nav-list').show();
						$('#nav-tree').show();
						
						$('#filter2').hide();
						$('#enav-tab').hide();
						$('#enav-tree').hide();
					}
				}

				$("#opt_audience1").change(function (e){
					e.preventDefault();
					switchTree();
				});

				$("#opt_audience2").change(function (e){
					e.preventDefault();
					switchTree();
				});

				$(".tags").multiselect({
                	enableFiltering: true,
                	enableCaseInsensitiveFiltering: true
            	});

				$('#pageLoader').hide();

				$('#notify-form').keydown(function (e) {
					if (e.keyCode == 13) {
						e.preventDefault();
						return false;
					}
				});

				$('#notify-form').submit(function() {
					// console.log('Search Button Clicked');			

					// assign back the selected employees to server
					var text = JSON.stringify(g_selected_employees);
					$('#selected_emp_ids').val( text );
					var text2 = JSON.stringify(g_selected_orgnodes);
					$('#selected_org_nodes').val( text2 );
					return true; // return false to cancel form action
				});

				// Tab  -- LIST Page  activate
				$("#nav-list-tab").on("click", function(e) {
					table  = $('#employee-list-table').DataTable();
					table.rows().invalidate().draw();
				});

				CKEDITOR.replace('what', {
					toolbar: [ ["Bold", "Italic", "Underline", "-", "NumberedList", "BulletedList", "-", "Outdent", "Indent", "Link"] ],disableNativeSpellChecker: false});

				CKEDITOR.replace('measure_of_success', {
					toolbar: [ ["Bold", "Italic", "Underline", "-", "NumberedList", "BulletedList", "-", "Outdent", "Indent", "Link"] ],disableNativeSpellChecker: false});

				// Tab  -- TREE activate
				$("#nav-tree-tab").on("click", function(e) {
					target = $('#nav-tree'); 
                    ddnotempty = $('#dd_level0').val() + $('#dd_level1').val() + $('#dd_level2').val() + $('#dd_level3').val() + $('#dd_level4').val();
                    if(ddnotempty) {
                        // To do -- ajax called to load the tree
                        if($.trim($(target).attr('loaded'))=='') {
                            $.when( 
                                $.ajax({
                					url: '{{ "/" . request()->segment(1) . "/goalbank/org-tree" }}',
                                    type: 'GET',
                                    data: $("#notify-form").serialize(),
                                    dataType: 'html',
                                    success: function (result) {
                                        $(target).html(''); 
                                        $(target).html(result);

                                        $('#nav-tree').attr('loaded','loaded');
                                    },
                                    error: function () {
                                        alert("error");
                                        $(target).html('<i class="glyphicon glyphicon-info-sign"></i> Something went wrong, Please try again...');
                                    }
                                })
                                
                            ).then(function( data, textStatus, jqXHR ) {
                                //alert( jqXHR.status ); // Alerts 200
                                nodes = $('#accordion-level0 input:checkbox');
                                redrawTreeCheckboxes();	
                            }); 
                        
                        } else {
                            redrawTreeCheckboxes();
                        }
                    } else {
						// alert("error");
                        $(target).html('<i class="glyphicon glyphicon-info-sign"></i> Please apply the organization filter before creating a tree view.');
					}
				});

				function redrawTreeCheckboxes() {
					// redraw the selection 
					nodes = $('#accordion-level0 input:checkbox');
					$.each( nodes, function( index, chkbox ) {
						if (g_employees_by_org.hasOwnProperty(chkbox.value)) {
							all_emps = g_employees_by_org[ chkbox.value ].map( function(x) {return x.employee_id} );
							b = all_emps.every(v=> g_selected_employees.indexOf(v) !== -1);
							if (all_emps.every(v=> g_selected_employees.indexOf(v) !== -1)) {
								$(chkbox).prop('checked', true);
								$(chkbox).prop("indeterminate", false);
							} else if (all_emps.some(v=> g_selected_employees.indexOf(v) !== -1)) {
								$(chkbox).prop('checked', false);
								$(chkbox).prop("indeterminate", true);
							} else {
								$(chkbox).prop('checked', false);
								$(chkbox).prop("indeterminate", false);
							}
						} else {
							if ( $(chkbox).attr('name') == 'userCheck[]') {
								if (g_selected_orgnodes.includes(chkbox.value)) {
									$(chkbox).prop('checked', true);
								} else {
									$(chkbox).prop('checked', false);
								}
							}
						}
					});

					// reset checkbox state
					reverse_list = nodes.get().reverse();
					$.each( reverse_list, function( index, chkbox ) {
						if (g_employees_by_org.hasOwnProperty(chkbox.value)) {
							pid = $(chkbox).attr('pid');
							do {
								value = '#orgCheck' + pid;
								toggle_indeterminate( value );
								pid = $('#orgCheck' + pid).attr('pid');    
							} 
							while (pid);
						}
					});
				}

				function eredrawTreeCheckboxes() {
					// redraw the selection 
					//console.log('eredraw triggered');
					enodes = $('#eaccordion-level0 input:checkbox');
					$.each( enodes, function( index, chkbox ) {
						if (eg_employees_by_org.hasOwnProperty(chkbox.value)) {
							eall_emps = eg_employees_by_org[ chkbox.value ].map( function(x) {return x.employee_id} );
							b = eall_emps.every(v=> eg_selected_orgnodes.indexOf(v) !== -1);
							if (eall_emps.every(v=> eg_selected_orgnodes.indexOf(v) !== -1)) {
								$(chkbox).prop('checked', true);
								$(chkbox).prop("indeterminate", false);
							} else if (eall_emps.some(v=> eg_selected_orgnodes.indexOf(v) !== -1)) {
								$(chkbox).prop('checked', false);
								$(chkbox).prop("indeterminate", true);
							} else {
								$(chkbox).prop('checked', false);
								$(chkbox).prop("indeterminate", false);
							}
						} else {
							if ( $(chkbox).attr('name') == 'euserCheck[]') {
								if (eg_selected_orgnodes.includes(chkbox.value)) {
									$(chkbox).prop('checked', true);
								} else {
									$(chkbox).prop('checked', false);
								}
							}
						}
					});

					// reset checkbox state
					ereverse_list = enodes.get().reverse();
					$.each( ereverse_list, function( index, chkbox ) {
						if (eg_employees_by_org.hasOwnProperty(chkbox.value)) {
							pid = $(chkbox).attr('pid');
							do {
								value = '#eorgCheck' + pid;
								etoggle_indeterminate( value );
								pid = $('#eorgCheck' + pid).attr('pid');    
							} 
							while (pid);
						}
					});

				}

				// Set parent checkbox
				function toggle_indeterminate( prev_input ) {
					// Loop to checked the child
					var c_indeterminated = 0;
					var c_checked = 0;
					var c_unchecked = 0;
					prev_location = $(prev_input).parent().attr('href');
					nodes = $(prev_location).find("input:checkbox[name='orgCheck[]']");
					$.each( nodes, function( index, chkbox ) {
						if (chkbox.checked) {
							c_checked++;
						} else if ( chkbox.indeterminate ) {
							c_indeterminated++;
						} else {
							c_unchecked++;
						}
					});
					
					if (c_indeterminated > 0) {
						$(prev_input).prop('checked', false);
						$(prev_input).prop("indeterminate", true);
					} else if (c_checked > 0 && c_unchecked > 0) {
						$(prev_input).prop('checked', false);
						$(prev_input).prop("indeterminate", true);
					} else if (c_checked > 0 && c_unchecked == 0 ) {
						$(prev_input).prop('checked', true);
						$(prev_input).prop("indeterminate", false);
					} else {
						$(prev_input).prop('checked', false);
						$(prev_input).prop("indeterminate", false);
					}
				}

				// Set parent checkbox
				function etoggle_indeterminate( prev_input ) {
					// Loop to checked the child
					var c_indeterminated = 0;
					var c_checked = 0;
					var c_unchecked = 0;
					prev_location = $(prev_input).parent().attr('href');
					nodes = $(prev_location).find("input:checkbox[name='eorgCheck[]']");
					$.each( nodes, function( index, chkbox ) {
						if (chkbox.checked) {
							c_checked++;
						} else if ( chkbox.indeterminate ) {
							c_indeterminated++;
						} else {
							c_unchecked++;
						}
					});
					
					if (c_indeterminated > 0) {
						$(prev_input).prop('checked', false);
						$(prev_input).prop("indeterminate", true);
					} else if (c_checked > 0 && c_unchecked > 0) {
						$(prev_input).prop('checked', false);
						$(prev_input).prop("indeterminate", true);
					} else if (c_checked > 0 && c_unchecked == 0 ) {
						$(prev_input).prop('checked', true);
						$(prev_input).prop("indeterminate", false);
					} else {
						$(prev_input).prop('checked', false);
						$(prev_input).prop("indeterminate", false);
					}
				}

				$('#btn_search').click(function(e) {
					e.preventDefault();
					user_selected = [];
					$('#employee-list-table').DataTable().rows().invalidate().draw();
				});

				// Handle click on "Select all" control
				$('#employee-list-select-all').on('click', function() {
					// Check/uncheck all checkboxes in the table
					$('#employee-list-table tbody input:checkbox').prop('checked', this.checked);
					if (this.checked) {
						g_selected_employees = g_matched_employees.map((x) => x);
						$('#employee-list-select-all').prop("checked", true);
						$('#employee-list-select-all').prop("indeterminate", false);    
					} else {
						g_selected_employees = [];
						$('#employee-list-select-all').prop("checked", false);
						$('#employee-list-select-all').prop("indeterminate", false);    
					}    
				});

				$('#dd_level0').change(function (e){
					e.preventDefault();
				});

				$('#dd_level1').change(function (e){
					e.preventDefault();
				});

				$('#dd_level2').change(function (e){
					e.preventDefault();
				});

				$('#dd_level3').change(function (e){
					e.preventDefault();
				});

				$('#criteria').change(function (e){
					e.preventDefault();
					$('#btn_search').click();
				});

				$('#dd_superv').change(function (e){
					e.preventDefault();
					$('#btn_search').click();
				});

				$('#search_text').change(function (e){
					e.preventDefault();
					$('#btn_search').click();
				});

				$('#search_text').keydown(function (e){
					if (e.keyCode == 13) {
						e.preventDefault();
						$('#btn_search').click();
					}
				});

				$('#btn_search_reset').click(function (e){
					e.preventDefault();
					$('#dd_superv').val('all');
					$('#criteria').val('all');
					$('#search_text').val(null);
					$('#dd_level0').val(null).trigger('change');
					$('#dd_level1').val(null).trigger('change');
					$('#dd_level2').val(null).trigger('change');
					$('#dd_level3').val(null).trigger('change');
					$('#dd_level4').val(null).trigger('change');
					$('#btn_search').click();
				});

				$('#dd_level4').change(function (e){
					e.preventDefault();
					$('#btn_search').click();
				});

				$('#edd_level0').change(function (e) {
					e.preventDefault();
				});

				$('#edd_level1').change(function (e) {
					e.preventDefault();
				});

				$('#edd_level2').change(function (e) {
					e.preventDefault();
				});

				$('#edd_level3').change(function (e) {
					e.preventDefault();
				});
				$('#edd_level4').change(function (e) {
					e.preventDefault();
					$('#ebtn_search').click();
				});

				$('#ebtn_search_reset').click(function(e) {
					e.preventDefault();
					$('#ecriteria').val('all');
					$('#esearch_text').val(null);
					$('#edd_level0').val(null);
					$('#edd_level1').val(null);
					$('#edd_level2').val(null);
					$('#edd_level3').val(null);
					$('#edd_level4').val(null);
					$('#ebtn_search').click();
       			});

                $(window).on('beforeunload', function(){
                    $('#pageLoader').show();
                });

                // $(window).resize(function(){
                //     location.reload();
                //     return;
                // });
                
				$('#ebtn_search').click(function(e) {
					e.preventDefault();
					target = $('#enav-tree'); 
					ddnotempty = $('#edd_level0').val() + $('#edd_level1').val() + $('#edd_level2').val() + $('#edd_level3').val() + $('#edd_level4').val();
					if(ddnotempty) {
						// To do -- ajax called to load the tree
						$.when( 
							$.ajax({
                				url: '{{ "/" . request()->segment(1) . "/goalbank/eorg-tree" }}'
								, type: 'GET'
								, data: $("#notify-form").serialize()
								, dataType: 'html'
								, success: function (result) {
									$('#enav-tree').html(''); 
									$('#enav-tree').html(result);
									$('#enav-tree').attr('loaded','loaded');
								},


								error: function () {
									alert("error");
									$(target).html('<i class="glyphicon glyphicon-info-sign"></i> Something went wrong, Please try again...');
								}
							})
							
						).then(function( data, textStatus, jqXHR ) {
							//alert( jqXHR.status ); // Alerts 200
							enodes = $('#eaccordion-level0 input:checkbox');
							eredrawTreeCheckboxes();	
						}); 
					} else {
						$(target).html('<i class="glyphicon glyphicon-info-sign"></i> Please apply the organization filter before creating a tree view.');
					}
				});

				$(window).on('beforeunload', function(){
					$('#pageLoader').show();
				});

				// $(window).resize(function(){
				// 	location.reload();
				// 	return;
				// }); 


				$('body').popover({
					selector: '[data-toggle]',
					trigger: 'hover',
				});
        
				$('.modal').popover({
					selector: '[data-toggle-select]',
					trigger: 'click',
				});

				$('body').on('click', function (e) {
                $('[data-toggle=popover]').each(function () {
                    // hide any open popovers when the anywhere else in the body is clicked
                    if (!$(this).is(e.target) && $(this).has(e.target).length === 0 && $('.popover').has(e.target).length === 0) {
                        $(this).popover('hide');
                    }
                	});
            	});							


			});

			// Model -- Confirmation Box

			var modalConfirm = function(callback) {
				$("#btn-confirm").on("click", function(){
					$("#mi-modal").modal('show');
				});
				$("#modal-btn-si").on("click", function(){
					callback(true);
					$("#mi-modal").modal('hide');
				});
				
				$("#modal-btn-no").on("click", function(){
					callback(false);
					$("#mi-modal").modal('hide');
				});
			};
                        
                        @if(session()->has('title_miss'))                           
                            $('input[name=title]').addClass('is-invalid');
                        @endif
                        
                        
                        
                        

		</script>
	</x-slot>

</x-side-layout>

<style>
    .alert-danger {
        color: #a94442;
        background-color: #f2dede;
        border-color: #ebccd1;
    }
    .multiselect-container{
        height: 350px; 
        overflow-y: scroll;
    }
</style> 