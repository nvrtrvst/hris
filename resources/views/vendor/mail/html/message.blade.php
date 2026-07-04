<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
@php
    $subcopy = str_replace(
        ["If you're having trouble clicking the", "button, copy and paste the URL below\ninto your web browser:"],
        ["Jika Anda kesulitan mengklik tombol", "salin dan tempel URL di bawah ini ke browser web Anda:"],
        $subcopy
    );
@endphp
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}<br>
Jl. Raya Cisurupan,<br>
Kabupaten Garut, Jawa Barat 41181
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
