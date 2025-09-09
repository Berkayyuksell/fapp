@extends('layouts.app')
@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    📄 Gelen Fatura Listesi (Son 1 Ay)
                </h5>
                <div class="d-flex align-items-center">
                    <small class="text-muted me-3">
                        Toplam: {{ $invoices->total() }} fatura
                    </small>
                </div>
            </div>
            
            <form method="GET" action="{{ route('invoices.incoming') }}" class="mb-4" id="filterForm">
    <div class="row g-3">
        <!-- Başlangıç -->
        <div class="col-md-3">
            <label class="form-label">Başlangıç Tarihi</label>
            <input type="date" name="start_date" class="form-control"
                   value="{{ request('start_date') }}"
                   onchange="document.getElementById('filterForm').submit();">
        </div>

        <!-- Bitiş -->
        <div class="col-md-3">
            <label class="form-label">Bitiş Tarihi</label>
            <input type="date" name="end_date" class="form-control"
                   value="{{ request('end_date') }}"
                   onchange="document.getElementById('filterForm').submit();">
        </div>

        <!-- Arama -->
        <div class="col-md-3">
            <label class="form-label">Arama</label>
            <input type="text" name="search" class="form-control"
                   placeholder="Tedarikçi, fatura no..."
                   value="{{ request('search') }}">
        </div>

        <!-- Butonlar -->
        <div class="col-md-3 d-flex align-items-end">
            <div class="btn-group w-100">
                <button type="submit" class="btn btn-primary">
                    🔍 Ara
                </button>

                <a href="{{ route('invoices.incoming', array_merge(request()->all(), ['missing' => 1])) }}" 
                   class="btn btn-outline-danger {{ request('missing') ? 'active' : '' }}">
                    ❌ Düşmeyen
                </a>

                <a href="{{ route('invoices.incoming', [
        'start_date' => now()->toDateString(),
        'end_date' => now()->toDateString()
    ]) }}" class="btn btn-success">
    📅 Bugün
</a>

                @if(request()->hasAny(['start_date','end_date','search','missing']))
                    <a href="{{ route('invoices.incoming') }}" class="btn btn-outline-secondary">
                        ❌ Temizle
                    </a>
                @endif
            </div>
        </div>
    </div>
</form>
                <!-- Tablo -->
                @if($invoices->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Fatura No</th>
                                    <th>Tedarikçi</th>
                                    <th>Tutar</th>
                                    <th>Tarih</th>
                                    <th>Nebim</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoices as $invoice)
                                    <tr>
                                        <td><small class="text-muted">{{ $invoice->invoice_id }}</small></td>
                                        <td><div class="text-truncate" style="max-width:250px;" title="{{ $invoice->supplier }}">{{ $invoice->supplier }}</div></td>
                                        <td><strong class="text-danger">{{ number_format($invoice->amount,2) }} ₺</strong></td>
        
                                        <td>{{ date('d.m.Y', strtotime($invoice->issue_date)) }}</td>
                                        <td>
                                            @if($invoice->invoiceIsOkey == 1)
                                                <span class="text-success fw-bold fs-5">✔️</span>
                                            @else
                                                <span class="text-danger fw-bold fs-5">❌</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="{{ route('invoices.show', $invoice->id) }}" class="btn btn-outline-primary" title="Detay">👁️</a>
                                                <a href="{{ route('invoices.html', $invoice->uuid) }}" class="btn btn-outline-success" title="HTML Görünüm" target="_blank">📄</a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Aynı sayfalama -->
                    @if($invoices->hasPages())
                        <div style="text-align:center; margin-top:20px; padding:15px;">
                            @if($invoices->onFirstPage())
                                <span style="padding:8px 12px; margin:0 5px; color:#999; border:1px solid #ddd; border-radius:4px;">Önceki</span>
                            @else
                                <a href="{{ $invoices->previousPageUrl() }}" style="padding:8px 12px; margin:0 5px; color:#007bff; text-decoration:none; border:1px solid #007bff; border-radius:4px;">Önceki</a>
                            @endif
                            <span style="margin:0 15px; color:#666;">
                                Sayfa {{ $invoices->currentPage() }} / {{ $invoices->lastPage() }} ({{ $invoices->total() }} fatura)
                            </span>
                            @if($invoices->hasMorePages())
                                <a href="{{ $invoices->nextPageUrl() }}" style="padding:8px 12px; margin:0 5px; color:#007bff; text-decoration:none; border:1px solid #007bff; border-radius:4px;">Sonraki</a>
                            @else
                                <span style="padding:8px 12px; margin:0 5px; color:#999; border:1px solid #ddd; border-radius:4px;">Sonraki</span>
                            @endif
                        </div>
                    @endif
                @else
                    <div class="text-center py-5">
                        <div style="font-size:4rem; margin-bottom:1rem;">📄</div>
                        <h5 class="text-muted">Henüz gelen fatura bulunmuyor</h5>
                        <p class="text-muted">Faturaları görmek için "Senkronize Et" butonuna tıklayın.</p>
                        <form action="{{ route('invoices.sync') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-primary">🔄 Faturaları Senkronize Et</button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection