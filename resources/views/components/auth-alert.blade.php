@if(session('success'))
    <div class="auth-flash auth-flash--success alert alert-dismissible fade show" role="status">
        <i class="fas fa-check-circle" aria-hidden="true"></i>
        <div class="auth-flash__body">{{ session('success') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif

@if(session('error'))
    <div class="auth-flash auth-flash--error alert alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
        <div class="auth-flash__body">{{ session('error') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif

@if(session('warning'))
    <div class="auth-flash auth-flash--warning alert alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
        <div class="auth-flash__body">{{ session('warning') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif

@if(session('info'))
    <div class="auth-flash auth-flash--info alert alert-dismissible fade show" role="status">
        <i class="fas fa-info-circle" aria-hidden="true"></i>
        <div class="auth-flash__body">{{ session('info') }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif

@if($errors->any())
    <div class="auth-flash auth-flash--error alert alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
        <div class="auth-flash__body">
            @if($errors->count() === 1)
                {{ $errors->first() }}
            @else
                <strong>Periksa kembali data Anda.</strong>
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif
