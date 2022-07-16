<x-side-layout title="{{ __('Dashboard') }}">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-primary leading-tight" role="banner">
            {{ $greetings }}, {{ Auth::user()->name }}
        </h2> 
    </x-slot>
    <div class="container-fluid">
        <div class="row mb-4">
        <div class="col-12 col-sm-4 col-md-4">
                <strong>
                    My Current Supervisor
                    <i class="fa fa-info-circle" data-trigger="click" data-toggle="popover" data-placement="right" data-html="true" data-content="{{ $supervisorTooltip }}"></i>
                </strong>
                <div class="bg-white border-b rounded p-2 mt-2 shadow-sm">
                    {{-- <x-profile-pic></x-profile-pic> --}}
                    {{ Auth::user()->reportingManager ? Auth::user()->reportingManager->name : 'No supervisor' }}
                </div>
            </div>
            <div class="col-12 col-sm-4 col-md-4">
                <strong>
                    My Profile is Shared with
                    <i class="fa fa-info-circle" data-trigger="click" data-toggle="popover" data-placement="right" data-html="true" data-content="{{ $profilesharedTooltip }}"></i>
                </strong>
                <div class="bg-white border-b rounded p-2 mt-2 shadow-sm">
                    <button class="btn p-0" style="width:100%" data-toggle="modal" data-target="#profileSharedWithViewModal">
                        @if(count($sharedList) > 0)
                        <div class="d-flex align-items-center">
                            {{-- <x-profile-pic></x-profile-pic> --}}
                            {{$sharedList[0]->sharedWithUser->name}}
                            @if(count($sharedList) > 1)
                                and {{count($sharedList) - 1}} Others
                            @endif
                            <div class="flex-fill"></div>
                            <i class="fa fa-chevron-right"></i>
                        </div>
                        @else
                            No one
                        @endif
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="p-4 bg-white border-b border-gray-200">
                    @include('dashboard.partials.tabs')
                    @if ($tab == 'todo')
                        @include('dashboard.partials.todo')
                    @else
                        @include('dashboard.partials.notifications')
                    @endif
                </div>
            </div>
        </div>
    </div>

    @include('dashboard.partials.shared_with_view-modal')
    @include('dashboard.partials.message-modal', ['content' => $message->message])

    @push('js')
        {{-- <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap4.min.js"></script> --}}
        <script>
            $(document).ready(function(){
                
                @if(!empty(Session::get('displayModalMessage'))) 
                    $("#messageModal").modal();
                @endif

                $('[data-toggle="popover"]').popover();
            });

            $('body').on('click', function (e) {
                $('[data-toggle=popover]').each(function () {
                    // hide any open popovers when the anywhere else in the body is clicked
                    if (!$(this).is(e.target) && $(this).has(e.target).length === 0 && $('.popover').has(e.target).length === 0) {
                        $(this).popover('hide');
                    }
                });
            });

        </script>


        <script type="text/javascript">
            // $("#modalMessage").modal();

            // @if(!empty(Session::get('displayModalMessage'))) 
            //     // $("#messageModal modal-title").html('{{ $message->title }}');
            //     // $("#messageModal modal-body data-placeholder").html('{{ $message->message }}');
            //     // console.log("Show message");
            //     $("#messageModal").modal('show');
            // @endif
        </script>

    @endpush
</x-side-layout>
