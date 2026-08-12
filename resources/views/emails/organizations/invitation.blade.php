<x-mail::message>
# You've been invited

You've been invited to join **{{ $organizationName }}**.

<x-mail::button :url="$acceptUrl">
Accept Invitation
</x-mail::button>

This invitation link will expire in 7 days.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
