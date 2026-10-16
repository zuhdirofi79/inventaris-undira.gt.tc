<?php

namespace App\Http\Requests;

use App\Enums\VehicleServiceType;
use App\Http\Requests\Concerns\BindsAuthenticatedBorrowerEmail;
use App\Http\Requests\Concerns\ValidatesSupportingDocument;
use App\Rules\GoogleMapsUrl;
use App\Rules\IndonesianPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVehicleBorrowingRequest extends FormRequest
{
    use BindsAuthenticatedBorrowerEmail;
    use ValidatesSupportingDocument;

    public function authorize(): bool
    {
        return $this->user()?->can('vehicle-borrowing.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->bindBorrowerIdentityForCreate();

        $maps = trim((string) $this->input('destination_maps_url', ''));
        $this->merge([
            'destination_maps_url' => $maps !== '' ? $maps : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'campus_id' => ['required', 'integer'],
            'borrower_name' => ['required', 'string', 'max:255'],
            'borrower_email' => ['required', 'email', 'max:255'],
            'borrower_phone' => ['required', 'string', 'max:50', new IndonesianPhoneNumber],
            'unit' => ['nullable', 'string', 'max:255'],
            'route_id' => ['required', 'integer'],
            'destination' => ['required', 'string', 'max:255'],
            'destination_maps_url' => ['nullable', 'string', 'max:2048', 'url:http,https', new GoogleMapsUrl],
            'purpose' => ['required', 'string', 'max:2000'],
            'service_type' => ['required', Rule::in(VehicleServiceType::requestValues())],
            'passenger_count' => ['required', 'integer', 'min:1', 'max:50'],
            'passengers' => ['required', 'array', 'min:1', 'max:50'],
            'passengers.*.name' => ['required', 'string', 'max:255'],
            'passengers.*.unit' => ['nullable', 'string', 'max:255'],
            'passengers.*.title' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'supporting_document' => $this->supportingDocumentRules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->guardSupportingDocument($validator);

            $count = (int) $this->input('passenger_count');
            $names = collect($this->input('passengers', []))
                ->filter(fn ($row) => is_array($row) && trim((string) ($row['name'] ?? '')) !== '')
                ->count();

            if ($count >= 1 && $names !== $count) {
                $validator->errors()->add('passengers', 'Jumlah nama penumpang harus sama dengan jumlah penumpang.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'campus_id.required' => 'Lokasi kampus wajib dipilih.',
            'borrower_name.required' => $this->canProxyBorrowerIdentity()
                ? 'Nama peminjam wajib diisi.'
                : 'Akun Anda belum memiliki nama. Perbarui profil sebelum mengajukan peminjaman.',
            'borrower_email.required' => $this->canProxyBorrowerIdentity()
                ? 'Email peminjam wajib diisi.'
                : 'Akun Anda belum memiliki email yang valid. Perbarui profil sebelum mengajukan peminjaman.',
            'borrower_email.email' => $this->canProxyBorrowerIdentity()
                ? 'Format email tidak valid.'
                : 'Email akun Anda tidak valid. Perbarui profil sebelum mengajukan peminjaman.',
            'borrower_phone.required' => 'Nomor Telepon / No. HP wajib diisi.',
            'route_id.required' => 'Rute wajib dipilih.',
            'destination.required' => 'Tujuan wajib diisi.',
            'destination_maps_url.url' => 'Link Google Maps Tujuan harus berupa URL yang valid.',
            'destination_maps_url.max' => 'Link Google Maps Tujuan maksimal 2048 karakter.',
            'purpose.required' => 'Keperluan wajib diisi.',
            'service_type.required' => 'Jenis pengantaran wajib dipilih.',
            'passenger_count.required' => 'Jumlah penumpang wajib diisi.',
            'passenger_count.integer' => 'Jumlah penumpang harus berupa bilangan bulat.',
            'passenger_count.min' => 'Jumlah penumpang minimal 1.',
            'passenger_count.max' => 'Jumlah penumpang maksimal 50.',
            'passengers.required' => 'Daftar penumpang wajib diisi.',
            'passengers.*.name.required' => 'Nama penumpang wajib diisi.',
            'starts_at.required' => 'Tanggal dan jam mulai wajib diisi.',
            'starts_at.date_format' => 'Tanggal dan jam mulai tidak valid.',
            'ends_at.required' => 'Tanggal dan jam selesai wajib diisi.',
            'ends_at.date_format' => 'Tanggal dan jam selesai tidak valid.',
            'ends_at.after' => 'Jadwal selesai harus setelah jadwal mulai.',
            ...$this->supportingDocumentMessages(),
        ];
    }
}
