{{--
  Tenant Form Partial — Bootstrap 5

  Purpose:
  - Reusable form for creating/updating tenants.

  Assumptions:
  - Variables:
    - $tenant  (nullable: Tenant|array|null)
    - $action  (string) route URL
    - $method  (string) 'POST' | 'PUT'
    - $templates (optional) Illuminate\Support\Collection of [id, name] for a select

  Notes:
  - Slug is immutable in Update; we show it as read-only when editing.
--}}

<form method="POST" action="{{ $action }}" novalidate>
  @csrf
  @if (strtoupper($method) === 'PUT')
    @method('PUT')
  @endif

  {{-- Name --}}
  <div class="mb-3">
    <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $tenant->name ?? '') }}" required maxlength="191">
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  {{-- Slug (immutable on update) --}}
  <div class="mb-3">
    <label for="slug" class="form-label">Slug <span class="text-danger">*</span></label>
    <input
      type="text"
      name="slug"
      id="slug"
      class="form-control @error('slug') is-invalid @enderror"
      value="{{ old('slug', $tenant->slug ?? '') }}"
      {{ isset($tenant) && ($tenant->id ?? null) ? 'readonly' : '' }}
      maxlength="191"
      pattern="[A-Za-z0-9_-]+"
      required
    >
    <div class="form-text">Only letters, numbers, dashes, and underscores.</div>
    @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  {{-- Status --}}
  @php
    $statusValue = old('status', $tenant->status ?? 'active');
    $statuses = ['active' => 'Active', 'suspended' => 'Suspended', 'pending' => 'Pending'];
  @endphp
  <div class="mb-3">
    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
    <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
      @foreach ($statuses as $val => $label)
        <option value="{{ $val }}" @selected($statusValue === $val)>{{ $label }}</option>
      @endforeach
    </select>
    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  {{-- Template --}}
  <div class="mb-3">
    <label for="template_id" class="form-label">Template</label>
    <select name="template_id" id="template_id" class="form-select @error('template_id') is-invalid @enderror">
      <option value="">— None —</option>
      @isset($templates)
        @foreach ($templates as $tpl)
          <option value="{{ $tpl->id }}"
            @selected((string) old('template_id', $tenant->template_id ?? '') === (string) $tpl->id)
          >{{ $tpl->name }} (ID {{ $tpl->id }})</option>
        @endforeach
      @endisset
    </select>
    @error('template_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  {{-- Primary domain --}}
  <div class="mb-3">
    <label for="primary_domain" class="form-label">Primary Domain</label>
    <input type="text" name="primary_domain" id="primary_domain"
           class="form-control @error('primary_domain') is-invalid @enderror"
           value="{{ old('primary_domain', $tenant->primary_domain ?? '') }}" maxlength="191">
    <div class="form-text">Hostname only (no scheme or path), e.g. <code>acme.example.test</code>.</div>
    @error('primary_domain') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  {{-- Seat limit --}}
  <div class="mb-3">
    <label for="user_seat_limit" class="form-label">Seat Limit <span class="text-danger">*</span></label>
    <input type="number" min="1" step="1" name="user_seat_limit" id="user_seat_limit"
           class="form-control @error('user_seat_limit') is-invalid @enderror"
           value="{{ old('user_seat_limit', $tenant->user_seat_limit ?? 1) }}" required>
    @error('user_seat_limit') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  {{-- Billing email --}}
  <div class="mb-3">
    <label for="billing_email" class="form-label">Billing Email</label>
    <input type="email" name="billing_email" id="billing_email"
           class="form-control @error('billing_email') is-invalid @enderror"
           value="{{ old('billing_email', $tenant->billing_email ?? '') }}" maxlength="191">
    @error('billing_email') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  {{-- Timezone --}}
  <div class="mb-3">
    <label for="timezone" class="form-label">Timezone</label>
    <input type="text" name="timezone" id="timezone"
           class="form-control @error('timezone') is-invalid @enderror"
           value="{{ old('timezone', $tenant->timezone ?? '') }}" maxlength="64" placeholder="UTC">
    @error('timezone') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  {{-- Locale --}}
  <div class="mb-3">
    <label for="locale" class="form-label">Locale</label>
    <input type="text" name="locale" id="locale"
           class="form-control @error('locale') is-invalid @enderror"
           value="{{ old('locale', $tenant->locale ?? '') }}" maxlength="10" placeholder="en, es, en_US, pt-BR">
    @error('locale') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  {{-- Plan --}}
  <div class="mb-3">
    <label for="plan" class="form-label">Plan</label>
    <input type="text" name="plan" id="plan"
           class="form-control @error('plan') is-invalid @enderror"
           value="{{ old('plan', $tenant->plan ?? '') }}" maxlength="100">
    @error('plan') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  {{-- Trial ends at --}}
  @php
    $trial = old('trial_ends_at', optional($tenant->trial_ends_at ?? null)?->format('Y-m-d\TH:i'));
  @endphp
  <div class="mb-3">
    <label for="trial_ends_at" class="form-label">Trial Ends At</label>
    <input type="datetime-local" name="trial_ends_at" id="trial_ends_at"
           class="form-control @error('trial_ends_at') is-invalid @enderror"
           value="{{ $trial }}">
    @error('trial_ends_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
  </div>

  {{-- Submit --}}
  <div class="d-flex justify-content-between">
    <a href="{{ route('admin.tenants.index') }}" class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" class="btn btn-primary">
      {{ strtoupper($method) === 'PUT' ? 'Save changes' : 'Create tenant' }}
    </button>
  </div>
</form>
