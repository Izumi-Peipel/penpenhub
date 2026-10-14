<x-mail::message>
# Undangan PenpenHub

{{ $inviterName }} mengundang Anda bergabung ke **{{ $companyName }}** sebagai **{{ $roleLabel }}**.

<x-mail::button :url="$url">
Terima undangan
</x-mail::button>

Tautan berlaku sampai {{ $expiresAt->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB dan hanya bisa dipakai sekali.
</x-mail::message>
