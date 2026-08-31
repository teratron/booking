{{--
    Shared feedback overlay, invokable from any page. Figma's own frame
    (node 244:230, "поп ап обратная связь") shows a phone field and a
    personal-data-processing consent checkbox alongside name and email,
    neither of which this component carried before — the phone number is
    a real, callable contact detail worth having on a portal whose whole
    model is handing visitors to a phone/messenger, and the consent
    checkbox is enforced server-side (FeedbackSubmissionController) the
    same way the object review form's CAPTCHA is, not left as a
    client-only formality.
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
                <div>
                    <h2 class="text-lg font-semibold text-ink">{{ __('public.shell.feedback.heading') }}</h2>
                    <p class="mt-1 text-sm text-ink-muted">{{ __('public.shell.feedback.subtitle') }}</p>
                </div>
                <button type="button" @click="open = false" aria-label="{{ __('public.shell.feedback.close') }}" class="shrink-0 text-ink-muted hover:text-ink">
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
                        <label class="block text-sm font-medium text-ink" for="feedback-phone">{{ __('public.shell.feedback.phone') }}</label>
                        <input id="feedback-phone" name="phone" type="tel" class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-ink" for="feedback-message">{{ __('public.shell.feedback.message') }}</label>
                        <textarea id="feedback-message" name="message" rows="4" required class="mt-1 w-full rounded border border-gray-300 px-3 py-2 text-sm"></textarea>
                    </div>

                    <label class="flex items-start gap-2 text-sm text-ink-muted" for="feedback-consent">
                        <input id="feedback-consent" name="consent" type="checkbox" required class="mt-0.5 h-4 w-4 shrink-0 rounded-sm border-gray-400 accent-brand">
                        <span>
                            {{ __('public.shell.feedback.consent') }}
                            <a
                                href="{{ route('public.legal.privacy', ['lang' => app()->getLocale()]) }}"
                                target="_blank"
                                rel="noopener"
                                class="text-brand underline"
                            >{{ __('public.shell.feedback.consent_link') }}</a>.
                        </span>
                    </label>
                    {{-- Not @error('consent') — that directive assumes the
                         $errors view variable, shared only by the 'web'
                         middleware group's ShareErrorsFromSession. This
                         overlay is included on every page via the shared
                         layout, including test routes that render it
                         outside that group, so it reads the same
                         session-stored MessageBag defensively instead. --}}
                    @if (session('errors')?->has('consent'))
                        <p class="text-sm text-red-600">{{ session('errors')->first('consent') }}</p>
                    @endif

                    <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:opacity-90">
                        {{ __('public.shell.feedback.submit') }}
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>
