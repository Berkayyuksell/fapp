@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">⚙️ E-Fatura Ayarları</h5>
            </div>
            
            <div class="card-body">
                <form method="POST" action="{{ route('settings.update') }}">
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="efatura_username" class="form-label">
                                    👤 Kullanıcı Adı
                                </label>
                                <input type="text" 
                                       class="form-control @error('efatura_username') is-invalid @enderror" 
                                       id="efatura_username" 
                                       name="efatura_username" 
                                       value="{{ old('efatura_username', $settings['efatura_username']) }}" 
                                       required>
                                @error('efatura_username')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="efatura_password" class="form-label">
                                    🔒 Şifre
                                </label>
                                <input type="password" 
                                       class="form-control @error('efatura_password') is-invalid @enderror" 
                                       id="efatura_password" 
                                       name="efatura_password" 
                                       value="{{ old('efatura_password', $settings['efatura_password']) }}" 
                                       required>
                                @error('efatura_password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="alert alert-info">
                            <strong>ℹ️ Bilgi:</strong> Bu bilgiler E-Fatura API'sine bağlanmak için kullanılır. 
                            Değiştirdikten sonra "Senkronize Et" işlemini tekrar deneyin.
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('invoices.incoming') }}" class="btn btn-secondary">
                            ← Fatura Listesi
                        </a>
                        
                        <button type="submit" class="btn btn-primary">
                            💾 Ayarları Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Test Bağlantısı -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">🔧Bilgiler</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>📊 Mevcut Durum</h6>
                        <ul class="list-unstyled">
                            <li><strong>Kullanıcı:</strong> {{ $settings['efatura_username'] }}</li>
             
                        </ul>
                    </div>
                    
                    
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
