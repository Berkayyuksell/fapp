@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    📄 Giden Fatura Listesi (Son 1 Ay)
                </h5>
                <div class="d-flex align-items-center">
                    <small class="text-muted me-3">
                        Toplam: {{ $invoices->total() }} fatura
                    </small>
                </div>
            </div>
            
            <div class="card-body">
    <!-- Filtreler -->
    <form method="GET" action="{{ route('invoices.outgoing') }}" class="mb-4" id="filterForm">
        <div class="row g-3">

            <!-- Fatura Tipi -->
            <div class="col-md-2">
                <label class="form-label">Fatura Tipi</label>
                <select name="type" class="form-select" onchange="document.getElementById('filterForm').submit();">
                    <option value="">Tümü</option>
                    <option value="OUT" {{ request('type') == 'OUT' ? 'selected' : '' }}>Giden E-Fatura</option>
                    <option value="ARCHIVE" {{ request('type') == 'ARCHIVE' ? 'selected' : '' }}>E-Arşiv</option>
                </select>
            </div>

            <!-- Başlangıç Tarihi -->
            <div class="col-md-2">
                <label class="form-label">Başlangıç Tarihi</label>
                <input type="date" name="start_date" class="form-control"
                       value="{{ request('start_date') }}"
                       onchange="document.getElementById('filterForm').submit();">
            </div>

            <!-- Bitiş Tarihi -->
            <div class="col-md-2">
                <label class="form-label">Bitiş Tarihi</label>
                <input type="date" name="end_date" class="form-control"
                       value="{{ request('end_date') }}"
                       onchange="document.getElementById('filterForm').submit();">
            </div>

            <!-- Arama & Butonlar -->
            <div class="col-md-6">
                <label class="form-label">Arama</label>
                <div class="input-group">
                    <input type="text" name="search" class="form-control"
                           placeholder="Müşteri, fatura no..." value="{{ request('search') }}">

                    <!-- Düşmeyen -->
                    <a href="{{ route('invoices.outgoing', array_merge(request()->all(), ['missing' => 1])) }}"
                       class="btn btn-outline-danger {{ request('missing') ? 'active' : '' }}">
                        ❌ Düşmeyen
                    </a>

                    <!-- Bugün Butonu -->
                    <a href="{{ route('invoices.outgoing', array_merge(request()->all(), [
                            'start_date' => now()->format('Y-m-d'),
                            'end_date'   => now()->format('Y-m-d')
                        ])) }}"
                       class="btn btn-outline-info {{ request('start_date') == now()->format('Y-m-d') && request('end_date') == now()->format('Y-m-d') ? 'active' : '' }}">
                        📅 Bugün
                    </a>

                    <!-- Ara -->
                    <button type="submit" class="btn btn-primary">🔍</button>
                </div>
            </div>

            <!-- Temizle -->
            @if(request()->hasAny(['type','start_date','end_date','search','missing']))
            <div class="col-md-2 d-flex align-items-end">
                <a href="{{ route('invoices.outgoing') }}" class="btn btn-outline-secondary w-100">
                    ❌ Temizle
                </a>
            </div>
            @endif
        </div>
    </form>
</div>

                <!-- Tablo -->
                @if($invoices->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Fatura No</th>
                                    <th>Fatura Ref</th>
                                    <th>Tip</th>
                                    <th>Müşteri</th>
                                    <th>Tutar</th>
                                    <th>Tarih</th>
                                    <th>E-Doğan</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoices as $invoice)
                                    <tr>
                                        <td><small class="text-muted">{{ $invoice->invoice_id }}</small></td>
                                        <td><small class="text-muted">{{ $invoice->InvoiceNumber }}</small></td>
                                        <td>
                                            @if($invoice->type == 'OUT')
                                                <span class="badge bg-primary">E-Fatura</span>
                                            @elseif($invoice->type == 'ARCHIVE')
                                                <span class="badge bg-warning text-dark">E-Arşiv</span>
                                            @elseif(!$invoice->type)
                                            <span class="badge rounded-pill bg-danger">düşmeyen</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="text-truncate" style="max-width:250px;" title="{{ $invoice->customer }}">
                                                {{ $invoice->customer }}
                                            </div>
                                        </td>
                                        <td><strong class="text-success">{{ number_format($invoice->amount,2) }} ₺</strong></td>
                                        <td>{{ date('d.m.Y', strtotime($invoice->issue_date)) }}</td>
                                        <td >
                                      @if($invoice->invoiceIsOkey == 1)
                                      <span class="text-success fw-bold fs-5">✔️</span>
                                       @else
                                            <span class="text-danger fw-bold fs-5">❌</span>
                                        @endif
                                    </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                
                                                <a href="{{ route('invoices.html', $invoice->uuid) }}" class="btn btn-outline-success" title="HTML Görünüm" target="_blank">📄</a>
                                            </div>
                                        </td>
                                        
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Sayfalama (incoming ile aynı yapı) -->
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
                        <h5 class="text-muted">Henüz giden fatura bulunmuyor</h5>
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