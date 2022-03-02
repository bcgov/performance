<x-side-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Edit: {{ $goal-> title}}
        </h2>
        <small><a href="{{ route('goal.index') }}">Back to list</a></small>
    </x-slot>

    <div class="container-fluid">
        <form action="{{ route ('goal.update', $goal->id)}}" method="POST" onsubmit="confirm('Are you sure you want to update Goal ?')">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-12">
                    <x-dropdown :list="$goaltypes" label="Goal Type" name="goal_type_id" />
                    <x-input label="Goal Title" name="title" :value="$goal->title"/>
                    <x-textarea label="What" name="what" :value="$goal->what" />
                    <x-textarea label="Why" name="why" :value="$goal->why" />
                    <x-textarea label="How" name="how" :value="$goal->how"/>
                        <!-- <x-textarea label="Measures of Success" name="measure_of_success" :value="$goal->measure_of_success" /> -->
                    <label for='measure_of_success'>Measure of Success</label>
                    <textarea class="ckeditor form-control" name="measure_of_success" :value="$goal->measure_of_success">{{$goal->measure_of_success}}</textarea>
                </div>
                <div class="col-sm-6">
                    <x-input label="Start Date" type="date" name="start_date" :value="$goal->start_date ? $goal->start_date->format('Y-m-d') : ''" />
                </div>
                <div class="col-sm-6">
                    <x-input label="End Date" type="date" name="target_date" :value="$goal->target_date ? $goal->target_date->format('Y-m-d') : ''" />
                </div>
                <div class="col-12 text-center">
                    <x-button type="submit" class="btn-lg"> Save </x-button>
                </div>
            </div>
        </form>
    </div>
</x-side-layout>

<script src="//cdn.ckeditor.com/4.17.2/basic/ckeditor.js"></script>
<script type="test/javascript">
    $(document).ready(function(){
        $('.ckeditor').ckeditor();
        CKEDITOR.config.removeButtons='About';
        CKEDITOR.replace('content');
        });
    });
</script>
