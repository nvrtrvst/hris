@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('logo.png') }}" class="logo" alt="{{ config('app.name') }} Logo" style="max-height: 75px; width: auto; object-fit: contain;">
</a>
</td>
</tr>
