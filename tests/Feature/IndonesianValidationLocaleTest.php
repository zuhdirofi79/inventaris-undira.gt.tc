<?php

namespace Tests\Feature;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\StoreSupplierRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class IndonesianValidationLocaleTest extends TestCase
{
    public function test_app_locale_is_indonesian(): void
    {
        $this->assertSame('id', config('app.locale'));
        $this->assertSame('id', app()->getLocale());
    }

    public function test_required_code_uses_indonesian_message(): void
    {
        $validator = Validator::make(
            ['name' => 'Elektronik'],
            ['code' => ['required'], 'name' => ['required']],
        );

        $this->assertTrue($validator->fails());
        $this->assertSame('Kode wajib diisi.', $validator->errors()->first('code'));
        $this->assertStringNotContainsString('The code field is required', $validator->errors()->first('code'));
    }

    public function test_store_category_request_uses_domain_indonesian_messages(): void
    {
        $request = StoreCategoryRequest::create('/categories', 'POST', []);
        $validator = Validator::make(
            [],
            $request->rules(),
            $request->messages(),
            $request->attributes(),
        );

        $this->assertTrue($validator->fails());
        $this->assertSame('Kode kategori wajib diisi.', $validator->errors()->first('code'));
        $this->assertSame('Nama kategori wajib diisi.', $validator->errors()->first('name'));
    }

    public function test_store_supplier_request_uses_indonesian_messages(): void
    {
        $request = StoreSupplierRequest::create('/suppliers', 'POST', [
            'email' => 'bukan-email',
        ]);
        $validator = Validator::make(
            $request->all(),
            $request->rules(),
            $request->messages(),
            $request->attributes(),
        );

        $this->assertTrue($validator->fails());
        $this->assertSame('Nama supplier wajib diisi.', $validator->errors()->first('name'));
        $this->assertSame('Email supplier tidak valid.', $validator->errors()->first('email'));
    }

    public function test_auth_failed_message_is_indonesian(): void
    {
        $this->assertSame(
            'Email atau kata sandi tidak cocok dengan data kami.',
            __('auth.failed'),
        );
    }
}
