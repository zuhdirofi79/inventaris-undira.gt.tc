<x-mail::message>
# Reset Password Inventaris UNDIRA

Halo **{{ $user->name }}**,

Gunakan kode OTP berikut untuk melanjutkan reset password:

# {{ $otp }}

Kode berlaku selama **{{ $ttlMinutes }} menit**.

Jika Anda tidak meminta reset password, abaikan email ini.

Terima kasih,<br>
{{ config('app.name') }}
</x-mail::message>
