<x-side-layout title="{{ __('Resource Manage - Performance Development Platform') }}">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-primary leading-tight" role="banner">
            Resource Detail
        </h2> 
    </x-slot>

<div class="card">
    <div class="card-body">

    <div class="form-group row">
            <label for="question" class="col-sm-2 col-form-label">Category:</label>
            <div class="col-sm-4">
                <input type="text" class="form-control" id="category" name="category" value="{{ $resource->category }}" readonly>
            </div>
        </div>

        <div class="form-group row">
            <label for="question" class="col-sm-2 col-form-label">Subject:</label>
            <div class="col-sm-4">
                <input type="text" class="form-control" id="question" name="question" value="{{ $resource->question }}" readonly>
            </div>
        </div>

        <div class="form-group row">
        <label for="question" class="col-sm-2 col-form-label">Body:</label>
            <div class="col-sm-9">
                <textarea class="form-control" id="answer" name="answer">{{ $resource->answer }}</textarea>
            </div>
        </div>
</div>
        


<div class="form-row m-3">
    <a href="{{ route('sysadmin.resource-manage') }}"> 
        <button type="button" class="btn btn-primary float-right ">back</button>
        </a>
    </div>
</div>

@push('js')
              <script src="//cdn.ckeditor.com/4.17.2/basic/ckeditor.js"></script>
              <script>

                $(document).ready(function(){
                    CKEDITOR.replace('answer', {
                         readOnly: true,
                         toolbar: "Custom",
                         toolbar_Custom: [
                            ["Bold", "Italic", "Underline"],
                            ["NumberedList", "BulletedList"],
                            ["Outdent", "Indent"],
                        ],
                    });

                });
              </script>
          @endpush

</x-side-layout>