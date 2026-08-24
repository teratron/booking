{{-- Section heading shared by every public listing page block. --}}
<h2 {{ $attributes->merge(['class' => 'text-xl font-semibold text-ink']) }}>{{ $slot }}</h2>
