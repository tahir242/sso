@props(['url'])
{{-- Branded email header (text mark; no remote images, which mail clients block). --}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
<span style="display: inline-block; background: #b50000; color: #ffffff; font-weight: 700; font-size: 13px; letter-spacing: 0.5px; padding: 8px 10px; border-radius: 8px; vertical-align: middle;">SSO</span>
<span style="display: inline-block; color: #18181b; font-weight: 600; font-size: 16px; margin-left: 8px; vertical-align: middle;">{{ $slot }}</span>
</a>
</td>
</tr>
