@if(!empty($limitReached))
@php
  $upgradeUrl = ($isSA && $tenantId)
    ? route('admin.seats.upgrade.show', ['tenant_id' => $tenantId])
    : route('admin.seats.upgrade.show');
@endphp
  <a href="{{ $upgradeUrl }}"
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
