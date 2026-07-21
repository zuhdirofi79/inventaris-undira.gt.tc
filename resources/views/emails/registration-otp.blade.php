<x-mail::message>
# Verifikasi Email Inventaris UNDIRA

Halo **{{ $user->name }}**,

Gunakan kode OTP berikut untuk memverifikasi email Anda:

# {{ $otp }}

Kode berlaku selama **{{ $ttlMinutes }} menit**.

Jika Anda tidak mendaftar di sistem Inventaris UNDIRA, abaikan email ini.

Terima kasih,<br>
{{ config('app.name') }}
</x-mail::message>
