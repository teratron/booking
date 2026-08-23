{{--
    Not shown in any Figma frame — this shell-level requirement is named in
    the specification's own scope but has no visual reference, matching how
    the feedback overlay handles the same gap. Consent is remembered in
    localStorage rather than a cookie: the notice's own concern is never
    reappearing once dismissed, a purely client-side fact this page never
    needs to read server-side.
--}}
<div x-data="{ visible: false }" x-init="visible = ! localStorage.getItem('cookie-consent-accepted')">
    <div
        x-show="visible"
        x-cloak
        class="fixed inset-x-0 bottom-0 z-50 flex flex-wrap items-center justify-between gap-4 bg-brand px-4 py-4 text-white lg:px-8"
        role="dialog"
        aria-label="{{ __('public.shell.cookie_consent.message') }}"
    >
        <p class="text-sm">{{ __('public.shell.cookie_consent.message') }}</p>
        <button
            type="button"
            @click="localStorage.setItem('cookie-consent-accepted', '1'); visible = false"
            class="shrink-0 rounded-lg bg-white px-4 py-2 text-sm font-semibold text-brand hover:opacity-90"
        >
            {{ __('public.shell.cookie_consent.accept') }}
        </button>
    </div>
</div>
