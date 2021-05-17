<x-side-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $goal['title'] }}
            <x-button icon="edit" :href="route('goal.edit', $goal->id)">Edit</x-button>
        </h2>
        <small><a href="{{ route('goal.index') }}">Back to list</a></small>
    </x-slot>
    <div class="container-fluid">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-12 col-sm-7">
                        <div class="card">
                            @include("goal.partials.show")
                            <hr>
                            <div class="px-3">
                                {{ $goal->user->name}}
                                <span class="float-right">
                                    @include("goal.partials.status-change")
                                </span>
                            </div>
                            <hr>
                             <div class="px-3">
                               <b>Linked Goals</b>
                                <span class="float-right">
                                 <button class="btn  btn-sm" data-toggle="modal" data-target="#supervisorGoalModal"> <i class="fa fa-plus"></i></button>
                                  
                                </span>
                            </div>
                            @forelse ($linkedGoals as $l)
                            <div class="card mx-3">
                            <div class="card-body py-2">
                             <p class="mb-0"><a href="{{route("goal.show", $l->id)}}" > {{ $l->title }}</a></p>
                            
                             <span class="mr-2">{{ $l->user->name }}</span>
                           <span class="mx-3">  | &nbsp; &nbsp; &nbsp; 
                          
                            <div class="d-inline-flex flex-row align-items-center">
                                <div class="bg-{{ \Config::get("global.status.$l->status.color") }} rounded-circle mr-2" style="width:15px; height:15px;"></div>
                                <div class="text-capitalize">
                                    {{ $l->status }}
                                </div>
                            </div>
                            </span>
                             <span class="mx-3">| &nbsp;&nbsp; &nbsp; {{\Carbon\Carbon::now()->format('M d, Y') }}</span>
                            </div>
                           
                            </div>
                            @empty
                           
                            <div class="p-3 text-center">
                              
                                <p class="text-center">No Goals to Display</p>
                                <p class="text-center font-weight-bold">Start linking to your supervisor's goal</p>
                                <button class="btn btn-outline-primary btn-sm" data-toggle="modal" data-target="#supervisorGoalModal">Link Goals</button>
                            </div>
                            @endforelse


                        </div>
                    </div>
                    <div class="col-12 col-sm-5">
                        <b>Comments</b>
                        @foreach ($goal->comments as $comment) 
                            <div class="d-flex flex-row my-2">
                                <x-profile-pic></x-profile-pic>
                                <div class="border flex-fill p-2 rounded">
                                    {{$comment->comment}}
                                    <small class="float-right" data-toggle="tooltip" title="{{$comment->created_at}}">
                                        {{$comment->created_at->diffForHumans()}}
                                    </small>
                                </div>
                            </div>
                        @endforeach
                        <form action="{{route('goal.add-comment', $goal->id)}}" method="POST">
                            @csrf
                            <div class="d-flex flex-row my-2">
                                <x-profile-pic></x-profile-pic>
                                <x-textarea label="" name="comment" placeholder="" required info="Ctrl+Enter to submit"/>
                            </div>
                        </form>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('js')
        <script>
            $('form').keydown(function (event) {
                if (event.ctrlKey && event.keyCode === 13) {
                    $(this).trigger('submit');
                }
            });
            $(function () {
                $('[data-toggle="tooltip"]').tooltip()
            })
            
        </script>
    @endpush
</x-side-layout>