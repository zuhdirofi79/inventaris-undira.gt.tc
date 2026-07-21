@extends('layouts.app')

@section('title', 'Sub Kategori - ' . $category->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('categories.index') }}">Kategori</a></li>
    <li class="breadcrumb-item active">{{ $category->name }}</li>
@endsection

@section('content')
<div class="page-header-undira d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="fas fa-tag me-2"></i>Sub Kategori: {{ $category->name }}</h1>
    </div>
    <button class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreate"><i class="fas fa-plus me-1"></i>Tambah</button>
</div>

<div class="card card-undira">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Kode</th><th>Nama</th><th>Slug</th><th>Inventaris</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse($subCategories as $sub)
                <tr>
                    <td><code>{{ $sub->code }}</code></td>
                    <td>{{ $sub->name }}</td>
                    <td><code>{{ $sub->slug }}</code></td>
                    <td>{{ $sub->inventory_items_count }}</td>
                    <td><span class="badge {{ $sub->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $sub->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEdit{{ $sub->id }}"><i class="fas fa-edit"></i></button>
                        <form action="{{ route('sub-categories.destroy', $sub) }}" method="POST" class="d-inline"
                              data-confirm-title="Pindahkan data ke Trash?"
                              data-confirm="Data masih dapat dipulihkan dari menu Trash."
                              data-confirm-button="Pindahkan ke Trash">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">Belum ada sub kategori.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($subCategories->hasPages())<div class="card-footer">{{ $subCategories->links() }}</div>@endif
</div>

<div class="modal fade" id="modalCreate" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('sub-categories.store') }}" method="POST" class="modal-content">
            @csrf
            <input type="hidden" name="category_id" value="{{ $category->id }}">
            <div class="modal-header"><h5 class="modal-title">Tambah Sub Kategori</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" required maxlength="20" placeholder="LPT"></div>
                <div class="mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" required></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" checked><label class="form-check-label">Aktif</label></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-undira">Simpan</button></div>
        </form>
    </div>
</div>

@foreach($subCategories as $sub)
<div class="modal fade" id="modalEdit{{ $sub->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('sub-categories.update', $sub) }}" method="POST" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header"><h5 class="modal-title">Edit Sub Kategori</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Kode</label><input type="text" name="code" class="form-control" value="{{ $sub->code }}" required maxlength="20"></div>
                <div class="mb-3"><label class="form-label">Nama</label><input type="text" name="name" class="form-control" value="{{ $sub->name }}" required></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" {{ $sub->is_active ? 'checked' : '' }}><label class="form-check-label">Aktif</label></div>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-undira">Perbarui</button></div>
        </form>
    </div>
</div>
@endforeach
@endsection
