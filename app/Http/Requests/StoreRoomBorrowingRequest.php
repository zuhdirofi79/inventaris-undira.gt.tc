<?php

namespace App\Http\Requests;

use App\Enums\RoomActivityType;
use App\Rules\IndonesianPhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Validator;

class StoreRoomBorrowingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('room-borrowing.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $usesBudget = $this->boolean('uses_budget');
        $documentation = trim((string) $this->input('documentation_url', ''));

        $this->merge([
            'uses_budget' => $usesBudget,
            'budget_type' => $usesBudget ? trim(strip_tags((string) $this->input('budget_type', ''))) : null,
            'participant_internal' => $this->boolean('participant_internal'),
            'participant_external' => $this->boolean('participant_external'),
            'purpose' => trim(strip_tags((string) $this->input('purpose', ''))),
            'documentation_url' => $documentation !== '' ? $documentation : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer'],
            'borrower_name' => ['required', 'string', 'max:255'],
            'borrower_email' => ['nullable', 'email', 'max:255'],
            'borrower_phone' => ['required', 'string', 'max:50', new IndonesianPhoneNumber],
            'activity_type' => ['required', 'string', 'in:'.implode(',', RoomActivityType::values())],
            'purpose' => ['required', 'string', 'max:2000'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'documentation_url' => ['nullable', 'url:http,https', 'max:2048'],
            'uses_budget' => ['required', 'boolean'],
            'budget_type' => ['nullable', 'string', 'max:255', 'required_if:uses_budget,true,1'],
            'participant_internal' => ['required', 'boolean'],
            'participant_external' => ['required', 'boolean'],
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

            if ($this->boolean('uses_budget') && trim((string) $this->input('budget_type')) === '') {
                $validator->errors()->add('budget_type', 'Jenis anggaran wajib diisi jika anggaran digunakan.');
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
            'borrower_name.required' => 'Nama peminjam wajib diisi.',
            'borrower_phone.required' => 'Nomor telepon wajib diisi.',
            'activity_type.required' => 'Jenis kegiatan wajib dipilih.',
            'activity_type.in' => 'Jenis kegiatan tidak valid.',
            'purpose.required' => 'Tujuan kegiatan wajib diisi.',
            'purpose.max' => 'Tujuan kegiatan terlalu panjang.',
            'starts_at.required' => 'Jadwal mulai wajib diisi.',
            'starts_at.date_format' => 'Jadwal mulai tidak valid.',
            'ends_at.required' => 'Jadwal selesai wajib diisi.',
            'ends_at.date_format' => 'Jadwal selesai tidak valid.',
            'ends_at.after' => 'Jadwal selesai harus setelah jadwal mulai.',
            'documentation_url.url' => 'Link dokumentasi harus berupa URL http atau https.',
            'budget_type.required_if' => 'Jenis anggaran wajib diisi jika anggaran digunakan.',
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
