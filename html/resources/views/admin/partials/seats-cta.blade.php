@if(!empty($limitReached) && $limitReached)
  {{-- CTA inoperant when there are NO seats available (for now) --}}
  <a href="#"
     class="btn btn-primary disabled"
     aria-disabled="true"
     title="No seats available">
    {{ $buyMore }}
  </a>
@else
  {{-- CTA normal cuando sí hay seats --}}
  @isset($primaryRoute)
    <a href="{{ $primaryRoute }}" class="btn btn-primary">
      {{ $primaryLabel }}
    </a>
  @endisset
@endif
