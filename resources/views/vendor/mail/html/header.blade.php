@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
{{--
    The logo is embedded in the message itself (see App\Mail\Concerns\EmbedsAmsLogo)
    rather than linked to a URL, so it renders in clients that block remote
    images and does not depend on the site being publicly reachable.

    Every mailable that renders this header must use that trait, or this image
    resolves to nothing.
--}}
<img src="cid:{{ \App\Mail\AmsLogo::CID }}" class="logo" alt="AMS Training">
</a>
</td>
</tr>
