@php
    $impersonatorId = session(\App\Services\Audit\ImpersonationContext::SESSION_KEY);
@endphp

@if ($impersonatorId !== null)
    <div class="flex items-center justify-center gap-x-4 bg-amber-600 px-4 py-2 text-sm font-medium text-white">
        <span>{{ __('panel.impersonation.banner_text', ['owner' => auth()->user()?->name]) }}</span>
        <a href="{{ route('support-mode.exit') }}" class="underline hover:no-underline">
            {{ __('panel.impersonation.return_to_admin') }}
        </a>
    </div>
@endif
