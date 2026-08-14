{{--
    Shared feedback overlay, invokable from any page. Not shown in any
    Figma frame — this shell-level requirement is added here per the
    specification rather than dropped for lack of a visual reference.
--}}
<div x-data="{ open: false }">
    <button
        type="button"
        @click="open = true"
        class="fixed bottom-6 right-6 z-30 rounded-full bg-brand px-4 py-3 text-sm font-semibold text-white shadow-lg hover:opacity-90"
    >
        {{ __('public.shell.feedback.trigger') }}
    </button>

    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4"
        @keydown.escape.window="open = false"
    >
        <div
            class="w-full max-w-md rounded-lg bg-white p-6"
            @click.outside="open = false"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('public.shell.feedback.trigger') }}"
        >
            <div class="flex items-start justify-between gap-4">
                <h2 class="text-lg font-semibold text-ink">{{ __('public.shell.feedback.heading') }}</h2>
                <button type="button" @click="open = false" aria-label="{{ __('public.shell.feedback.close') }}" class="text-ink-muted hover:text-ink">
                    &times;
                </button>
            </div>

            @if (session('public-feedback-submitted'))
                <p class="mt-4 text-sm text-emerald-600">{{ __('public.shell.feedback.thanks') }}</p>
            @else
                <form method="POST" action="{{ route('public.feedback.submit', ['lang' => app()->getLocale()]) }}" class="mt-4 flex flex-col gap-3">
                    @csrf
                    <input type="hidden" name="page_url" value="{{ url()->current() }}">

                    <div>
                        <label class="block text-sm font-medium text-ink" for="feedback-name">{{ __('public.shell.feedback.name') }}</label>
                        <input id="feedback-name" name="name" type="text" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-ink" for="feedback-email">{{ __('public.shell.feedback.email') }}</label>
                        <input id="feedback-email" name="email" type="email" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-ink" for="feedback-message">{{ __('public.shell.feedback.message') }}</label>
                        <textarea id="feedback-message" name="message" rows="4" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm"></textarea>
                    </div>

                    <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:opacity-90">
                        {{ __('public.shell.feedback.submit') }}
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>
