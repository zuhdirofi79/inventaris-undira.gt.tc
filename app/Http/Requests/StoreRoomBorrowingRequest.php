<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\BindsAuthenticatedBorrowerEmail;
use App\Rules\IndonesianPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRoomBorrowingRequest extends FormRequest
{
    use BindsAuthenticatedBorrowerEmail;

    public function authorize(): bool
    {
        return $this->user()?->can('room-borrowing.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $budgetCode = trim(strip_tags((string) $this->input('budget_type', '')));
        $hasInternal = $this->boolean('participant_internal');
        $hasExternal = $this->boolean('participant_external');

        $this->merge([
            // Empty dropdown = tidak menggunakan anggaran. Selected code = menggunakan anggaran.
            'budget_type' => $budgetCode !== '' ? $budgetCode : null,
            'uses_budget' => $budgetCode !== '',
            'participant_internal' => $hasInternal,
            'participant_external' => $hasExternal,
            // Unchecked categories must not retain stale counts from the client.
            'participant_internal_count' => $hasInternal
                ? $this->input('participant_internal_count')
                : null,
            'participant_external_count' => $hasExternal
                ? $this->input('participant_external_count')
                : null,
            'purpose' => trim(strip_tags((string) $this->input('purpose', ''))),
            // Link dokumentasi dihapus dari form; pengajuan baru tidak menyimpan nilai baru.
            'documentation_url' => null,
            'borrower_affiliation' => trim(strip_tags((string) $this->input('borrower_affiliation', ''))),
        ]);

        $this->bindBorrowerIdentityForCreate();
    }

    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer'],
            'borrower_name' => ['required', 'string', 'max:255'],
            'borrower_email' => ['required', 'email', 'max:255'],
            'borrower_phone' => ['required', 'string', 'max:50', new IndonesianPhoneNumber],
            'borrower_affiliation' => ['required', 'string', 'max:255'],
            'activity_type' => [
                'required',
                'string',
                'max:40',
                Rule::exists('room_activity_types', 'code')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'purpose' => ['required', 'string', 'max:2000'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'uses_budget' => ['required', 'boolean'],
            'budget_type' => [
                'nullable',
                'string',
                'max:40',
                Rule::exists('room_budget_types', 'code')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'participant_internal' => ['required', 'boolean'],
            'participant_external' => ['required', 'boolean'],
            'participant_internal_count' => [
                Rule::requiredIf(fn () => $this->boolean('participant_internal')),
                'nullable',
                'integer',
                'min:1',
                'max:99999',
            ],
            'participant_external_count' => [
                Rule::requiredIf(fn () => $this->boolean('participant_external')),
                'nullable',
                'integer',
                'min:1',
                'max:99999',
            ],
            'approval_document' => $this->documentRules(true),
            'layout_document' => $this->documentRules(false),
            'participant_document' => $this->documentRules(false),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('participant_internal') && ! $this->boolean('participant_external')) {
                $validator->errors()->add('participants', 'Pilih minimal satu jenis peserta, Internal atau External.');
            }

            foreach ([
                'approval_document' => 'Dokumen persetujuan pimpinan',
                'layout_document' => 'Layout acara',
                'participant_document' => 'Daftar peserta',
            ] as $field => $label) {
                $this->guardFile($validator, $field, $label);
            }
        });
    }

    public function messages(): array
    {
        return [
            'room_id.required' => 'Ruangan wajib dipilih.',
            'borrower_name.required' => $this->canProxyBorrowerIdentity()
                ? 'Nama peminjam wajib diisi.'
                : 'Akun Anda belum memiliki nama. Perbarui profil sebelum mengajukan peminjaman.',
            'borrower_email.required' => $this->canProxyBorrowerIdentity()
                ? 'Email peminjam wajib diisi.'
                : 'Akun Anda belum memiliki email yang valid. Perbarui profil sebelum mengajukan peminjaman.',
            'borrower_email.email' => $this->canProxyBorrowerIdentity()
                ? 'Format email tidak valid.'
                : 'Email akun Anda tidak valid. Perbarui profil sebelum mengajukan peminjaman.',
            'borrower_phone.required' => 'Nomor telepon wajib diisi.',
            'borrower_affiliation.required' => 'Kolom Unit wajib diisi.',
            'borrower_affiliation.max' => 'Kolom Unit maksimal 255 karakter.',
            'activity_type.required' => 'Jenis kegiatan wajib dipilih.',
            'activity_type.exists' => 'Jenis kegiatan tidak valid atau sudah tidak aktif.',
            'purpose.required' => 'Tujuan kegiatan wajib diisi.',
            'purpose.max' => 'Tujuan kegiatan terlalu panjang.',
            'starts_at.required' => 'Jadwal mulai wajib diisi.',
            'starts_at.date_format' => 'Jadwal mulai tidak valid.',
            'ends_at.required' => 'Jadwal selesai wajib diisi.',
            'ends_at.date_format' => 'Jadwal selesai tidak valid.',
            'ends_at.after' => 'Jadwal selesai harus setelah jadwal mulai.',
            'budget_type.exists' => 'Jenis anggaran tidak valid atau sudah tidak aktif.',
            'participant_internal_count.required' => 'Jumlah peserta Internal wajib diisi.',
            'participant_internal_count.integer' => 'Jumlah peserta Internal harus berupa bilangan bulat.',
            'participant_internal_count.min' => 'Jumlah peserta Internal minimal 1 orang.',
            'participant_external_count.required' => 'Jumlah peserta External wajib diisi.',
            'participant_external_count.integer' => 'Jumlah peserta External harus berupa bilangan bulat.',
            'participant_external_count.min' => 'Jumlah peserta External minimal 1 orang.',
            'approval_document.required' => 'Dokumen persetujuan pimpinan wajib diunggah.',
            'approval_document.mimes' => 'Dokumen persetujuan pimpinan harus PDF, JPG, atau JPEG.',
            'approval_document.max' => 'Dokumen persetujuan pimpinan maksimal 5 MB.',
            'layout_document.mimes' => 'Layout acara harus PDF, JPG, atau JPEG.',
            'layout_document.max' => 'Layout acara maksimal 5 MB.',
            'participant_document.mimes' => 'Daftar peserta harus PDF, JPG, atau JPEG.',
            'participant_document.max' => 'Daftar peserta maksimal 5 MB.',
        ];
    }

    /**
     * @return list<string>
     */
    private function documentRules(bool $required): array
    {
        return [$required ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg', 'max:5120'];
    }

    private function guardFile(Validator $validator, string $field, string $label): void
    {
        $file = $this->file($field);
        if (! $file instanceof UploadedFile) {
            return;
        }

        if (! $file->isValid()) {
            $validator->errors()->forget($field);
            $validator->errors()->add($field, $this->uploadMessage($file, $label));
            Log::warning('Dokumen peminjaman ruangan ditolak sebelum disimpan.', [
                'field' => $field,
                'php_upload_error' => $file->getError(),
            ]);

            return;
        }

        if ($validator->errors()->has($field)) {
            return;
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($extension, ['pdf', 'jpg', 'jpeg'], true)) {
            $validator->errors()->add($field, $label.' harus PDF, JPG, atau JPEG.');
        }
    }

    private function uploadMessage(UploadedFile $file, string $label): string
    {
        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $label.' maksimal 5 MB.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => $label.' tidak dapat disimpan saat ini. Silakan coba kembali.',
            default => $label.' gagal diunggah. Silakan coba kembali.',
        };
    }
}
