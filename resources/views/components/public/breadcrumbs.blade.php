{{--
    Present on every page below the home page; every crumb is a real link —
    including the current page, which still carries `aria-current="page"`.
--}}
@props(['items'])
<nav aria-label="{{ __('public.shell.breadcrumbs.label') }}" class="mx-auto max-w-7xl px-4 py-3 text-sm text-ink-muted lg:px-8">
    <ol class="flex flex-wrap items-center gap-1">
        @foreach ($items as $index => $item)
            <li class="flex items-center gap-1">
                @if ($index > 0)
                    <span aria-hidden="true">/</span>
                @endif
                <a
                    href="{{ $item['url'] }}"
                    class="hover:text-brand hover:underline"
                    @if ($index === count($items) - 1) aria-current="page" @endif
                >
                    {{ $item['label'] }}
                </a>
            </li>
        @endforeach
    </ol>
</nav>
