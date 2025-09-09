@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <!-- Fatura Detay Kartı -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    📄 Fatura Detayı
                </h5>
                <div>
                    <a href="{{ route('invoices.html', $invoice->uuid) }}" 
                       class="btn btn-success btn-sm me-2" 
                       target="_blank">
                        📄 HTML Görünüm
                    </a>
                    <a href="{{ route('invoices.outgoing') }}" class="btn btn-secondary btn-sm">← Geri</a>
                    </a>
                </div>
            </div>
            
            <div class="card-body">
                <div class="row">
                    <!-- Sol Kolon - Temel Bilgiler -->
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 mb-3">Temel Bilgiler</h6>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4">
                                <strong>Fatura No:</strong>
                            </div>
                            <div class="col-sm-8">
                                <code>{{ $invoice->invoice_id }}</code>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4">
                                <strong>UUID:</strong>
                            </div>
                            <div class="col-sm-8">
                                <small class="text-muted">{{ $invoice->uuid }}</small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4">
                                <strong>Tip:</strong>
                            </div>
                            <div class="col-sm-8">
                                @switch($invoice->type)
                                    @case('OUT')
                                        <span class="badge bg-primary">Giden Fatura</span>
                                        @break
                                    @case('IN')
                                        <span class="badge bg-success">Gelen Fatura</span>
                                        @break
                                    @case('ARCHIVE')
                                        <span class="badge bg-warning">E-Arşiv Fatura</span>
                                        @break
                                @endswitch
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4">
                                <strong>Tutar:</strong>
                            </div>
                            <div class="col-sm-8">
                                <h5 class="text-success mb-0">
                                    {{ number_format($invoice->amount, 2) }} ₺
                                </h5>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4">
                                <strong>Fatura Tarihi:</strong>
                            </div>
                            <div class="col-sm-8">
                                {{ \Carbon\Carbon::parse($invoice->issue_date)->format('d.m.Y') }}
                                <small class="text-muted">
                                    ({{ \Carbon\Carbon::parse($invoice->issue_date)->diffForHumans() }})
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sağ Kolon - Taraf Bilgileri -->
                    <div class="col-md-6">
                        <h6 class="border-bottom pb-2 mb-3">Taraf Bilgileri</h6>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4">
                                <strong>Tedarikçi:</strong>
                            </div>
                            <div class="col-sm-8">
                                <div class="p-2 bg-light rounded">
                                    {{ $invoice->supplier }}
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4">
                                <strong>Müşteri:</strong>
                            </div>
                            <div class="col-sm-8">
                                <div class="p-2 bg-light rounded">
                                    {{ $invoice->customer }}
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4">
                                <strong>Oluşturma:</strong>
                            </div>
                            <div class="col-sm-8">
                                <small class="text-muted">
                                    {{ $invoice->created_at ? \Carbon\Carbon::parse($invoice->created_at)->format('d.m.Y H:i') : '-' }}
                                </small>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-sm-4">
                                <strong>Güncelleme:</strong>
                            </div>
                            <div class="col-sm-8">
                                <small class="text-muted">
                                    {{ $invoice->updated_at ? \Carbon\Carbon::parse($invoice->updated_at)->format('d.m.Y H:i') : '-' }}
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- HTML İçerik Kartı -->
        @if($invoice->content)
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        📄 Fatura HTML Görünümü
                    </h6>
                </div>
                <div class="card-body">
                    <div class="border rounded p-3" style="max-height: 600px; overflow-y: auto;">
                        {!! $invoice->content !!}
                    </div>
                </div>
            </div>
        @endif
        
        <!-- Ham Veri Kartı (Geliştirici Modu) -->
        
    </div>
</div>
@endsection

@push('styles')
<style>
    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    
    pre code {
        font-size: 0.875rem;
    }
    
    .bg-light {
        background-color: #f8f9fa !important;
    }
</style>
@endpush
