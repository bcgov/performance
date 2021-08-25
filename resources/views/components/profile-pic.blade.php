@props(['size' => 48])
<div {!! $attributes->merge(['class' => 'd-inline mx-2']) !!} >
    <img src="https://placeimg.com/{{$size}}/{{$size}}/people" class="rounded-circle" alt="">
</div>