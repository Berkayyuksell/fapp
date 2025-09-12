<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\EFaturaInvoiceService;
use App\Services\EFaturaHtmlService;
use Carbon\Carbon;

class InvoiceController extends Controller
{
    private $invoiceService;
    private $htmlService;

    public function __construct(EFaturaInvoiceService $invoiceService, EFaturaHtmlService $htmlService)
    {
        $this->invoiceService = $invoiceService;
        $this->htmlService = $htmlService;
        ini_set('max_execution_time', 0);
    }

    public function indexOutgoing(Request $request)
    {
        $threeMonthsAgo = now()->subMonths(3)->format('Y-m-d');
    
        // OUT
        $outQuery = DB::connection('sqlsrv')->table('zt_invoices_out')
            ->select([
                'invoice_id',
                'uuid',
                'supplier',
                'customer',
                'amount',
                'issue_date',
                DB::raw("'OUT' as type")
            ])
            ->where('issue_date', '>=', $threeMonthsAgo);
    
        // ARCHIVE
        $archiveQuery = DB::connection('sqlsrv')->table('zt_invoices_archive')
            ->select([
                'invoice_id',
                'uuid',
                'supplier',
                'customer',
                'amount',
                'issue_date',
                DB::raw("'ARCHIVE' as type")
            ])
            ->where('issue_date', '>=', $threeMonthsAgo);
    
        // UNION
        $unionQuery = $outQuery->unionAll($archiveQuery);
    
        // Ana query (LEFT JOIN trInvoiceHeader)
        $query = DB::connection('sqlsrv')->table('trInvoiceHeader as h')
            ->leftJoinSub($unionQuery, 'z', function ($join) {
                $join->on('h.InvoiceHeaderID', '=', 'z.uuid');
            })
            ->select([
                'h.InvoiceHeaderID',
                //'z.invoice_id',
                'h.EInvoiceNumber as invoice_id',
                'h.InvoiceHeaderID as uuid',
                'z.supplier',
                'z.customer',
                'z.amount',
                'h.InvoiceNumber',
                //'z.issue_date',
                'h.InvoiceDate as issue_date',
                'z.type',
                DB::raw("CASE WHEN z.uuid IS NOT NULL THEN 1 ELSE 0 END as invoiceIsOkey")
            ])
            ->where('h.InvoiceDate', '>=', $threeMonthsAgo)
            ->where('h.TransTypeCode', 2)
            ->where('h.IsReturn', 0)
            ->where('h.InvoiceTypeCode' ,'!=' ,0);
             
    
        // Filtreler
        if ($request->filled('type')) {
            $query->where('z.type', $request->type);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('h.InvoiceDate', '>=', Carbon::parse($request->start_date)->format('Y-m-d'));
        }
        if ($request->filled('missing') && $request->missing == 1) {
            $query->where('z.uuid', null); // ya da invoiceIsOkey = 0
        }
        if ($request->filled('end_date')) {
            $query->whereDate('h.InvoiceDate', '<=', Carbon::parse($request->end_date)->format('Y-m-d'));
        }
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('z.customer', 'like', "%{$search}%")
                  ->orWhere('z.invoice_id', 'like', "%{$search}%")
                  ->orWhere('h.InvoiceNumber', 'like', "%{$search}%");
            });
        }
    
        // Sonuç
        $sort = request('sort', 'issue_date');     // default tarih
        $direction = request('direction', 'desc'); // default yeni → eski

        $invoices = $query->orderBy($sort, $direction)
            ->paginate(20)
            ->withQueryString();
    
        return view('invoices.outgoing', compact('invoices'));
    }
    
 


    
    /**
     * Gelen Faturalar (IN) listesi.
     * Veriler son 3 ayı kapsar.
     */
    public function indexIncoming(Request $request){
    $threeMonthsAgo = now()->subMonths(3)->format('Y-m-d');

        $query = DB::connection('sqlsrv')->table('zt_invoices_in as n')
            ->leftJoin('trInvoiceHeader as k', function ($join) {
                $join->on('k.EInvoiceNumber', '=', 'n.invoice_id')
                    ->where('k.transTypeCode', 1)
                    ->where('k.IsReturn', 0)
                    ->where('k.InvoiceTypeCode', '!=', 0);
            })
            ->leftJoin('e_InboxInvoiceHeader as m', function ($join) {
                $join->on('m.UUID', '=', 'n.uuid');
                // burada istersen m tablosuna özel filtreler ekleyebilirsin
                // ör: ->where('m.IsActive', 1);
            })
            ->select([
                'n.*',
                DB::raw("'IN' as type"),
                'k.EInvoiceNumber as invoice_id',
                'k.InvoiceNumber as ref_number',
                DB::raw('CASE WHEN m.UUID IS NULL THEN 0 ELSE 1 END as invoiceIsOkey')
            ])
            ->where('n.issue_date', '>=', $threeMonthsAgo);



        if ($request->filled('start_date')) {
        $query->whereDate('n.issue_date', '>=', Carbon::parse($request->start_date)->format('Y-m-d'));
    }

    if ($request->filled('end_date')) {
        $query->whereDate('n.issue_date', '<=', Carbon::parse($request->end_date)->format('Y-m-d'));
    }
    if ($request->filled('missing') && $request->missing == 1) {
        $query->whereNull('m.UUID'); // Sadece Nebim'e düşmeyen faturaları göster
    }
        if ($request->filled('unprocessed') && $request->unprocessed == 1) {
            $query->whereNull('k.EInvoiceNumber'); }

    if ($request->filled('search')) {
        $search = trim($request->search);
        $query->where(function($q) use ($search) {
            $q->where('n.supplier', 'like', "%{$search}%")
              ->orWhere('n.invoice_id', 'like', "%{$search}%")
                ->orWhere('k.InvoiceNumber', 'like', "%{$search}%");

        });
    }


        $sort = request('sort', 'n.issue_date');     // default: tarih
        $direction = request('direction', 'desc');   // default: yeni → eski

        $invoices = $query->orderBy($sort, $direction)
            ->paginate(20)
            ->withQueryString();

    return view('invoices.incoming', compact('invoices'));
}



    public function sync()
    {
        try {
            $results = [];

            Log::info('Starting multi-account sync process');
            
            Log::info('Starting outgoing invoices sync');
            $results['outgoing'] = $this->invoiceService->syncOutgoingInvoices();
            Log::info('Outgoing invoices result: ', $results['outgoing']);

            Log::info('Starting archive invoices sync');
            $results['archive'] = $this->invoiceService->syncArchiveInvoices();
            Log::info('Archive invoices result: ', $results['archive']);
            
            Log::info('Starting incoming invoices sync');
            $results['incoming'] = $this->invoiceService->syncIncomingInvoices();
            Log::info('Incoming invoices result: ', $results['incoming']);

            // Toplam istatistikleri hesapla
            $totalSaved = $results['outgoing']['saved'] + $results['incoming']['saved'] + $results['archive']['saved'];
            $totalUpdated = $results['outgoing']['updated'] + $results['incoming']['updated'] + $results['archive']['updated'];
            $totalCredentials = $results['outgoing']['credential_count'] ?? 0;

            // Hataları topla
            $allErrors = [];
            foreach ($results as $type => $result) {
                if (!empty($result['errors'])) {
                    $allErrors[$type] = $result['errors'];
                }
            }

            // Detaylı mesaj oluştur
            $message = "Senkronizasyon tamamlandı! ";
            $message .= "Toplam {$totalSaved} yeni fatura kaydedildi, {$totalUpdated} fatura güncellendi. ";
            $message .= "({$totalCredentials} hesap işlendi)";

            // Detayları ekle
            $details = [];
            foreach ($results as $type => $result) {
                $typeName = match($type) {
                    'outgoing' => 'Giden',
                    'incoming' => 'Gelen', 
                    'archive' => 'E-Arşiv',
                    default => $type
                };
                $details[] = "{$typeName}: {$result['saved']} yeni, {$result['updated']} güncellendi";
            }
            
            if (!empty($details)) {
                $message .= " | " . implode(" | ", $details);
            }

            // Hatalar varsa uyarı olarak ekle
            if (!empty($allErrors)) {
                $errorCount = array_sum(array_map('count', $allErrors));
                $message .= " (⚠️ {$errorCount} hesapta hata oluştu)";
            }
            
            Log::info('Multi-account sync completed', [
                'total_saved' => $totalSaved,
                'total_updated' => $totalUpdated,
                'credential_count' => $totalCredentials,
                'errors' => $allErrors
            ]);
            
            return redirect()->route('invoices.outgoing')
                             ->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Multi-account sync error: ' . $e->getMessage());
            return redirect()->back()
                             ->with('error', 'Senkronizasyon hatası: ' . $e->getMessage());
        }
    }

    /**
     * Fatura detayı göster
     */
    public function show($id)
    {
        // Bu metod, hangi tablodan olursa olsun ID'ye göre faturayı bulur
        $invoice = DB::connection('sqlsrv')->table('zt_invoices_out')->where('id', $id)->first();
        $type = 'OUT';
        if (!$invoice) {
            $invoice = DB::connection('sqlsrv')->table('zt_invoices_in')->where('id', $id)->first();
            $type = $invoice ? 'IN' : $type;
        }
        if (!$invoice) {
            $invoice = DB::connection('sqlsrv')->table('zt_invoices_archive')->where('id', $id)->first();
            $type = $invoice ? 'ARCHIVE' : $type;
        }
        if (!$invoice) {
            abort(404);
        }

        return view('invoices.show', ['invoice' => (object) array_merge((array) $invoice, ['type' => $type])]);
    }

    /**
     * Faturanın HTML görünümünü göster
     */
    public function showHtml($uuid)
    {
        $invoice = null;
        $type = 'OUT';
        
        $invoice = DB::connection('sqlsrv')->table('zt_invoices_out')->where('uuid', $uuid)->first();
        if (!$invoice) {
            $invoice = DB::connection('sqlsrv')->table('zt_invoices_in')->where('uuid', $uuid)->first();
            if ($invoice) $type = 'IN';
        }
        if (!$invoice) {
            $invoice = DB::connection('sqlsrv')->table('zt_invoices_archive')->where('uuid', $uuid)->first();
            if ($invoice) $type = 'ARCHIVE';
        }
        
        if (!$invoice) {
            abort(404, 'Fatura bulunamadı');
        }
        
        try {
            if ($type === 'ARCHIVE') {
                $htmlContent = $this->htmlService->getArchiveInvoiceHtml($invoice->uuid);
            } else {
                $htmlContent = $this->htmlService->getInvoiceHtml($invoice->uuid, $type);
            }
            
            if (empty($htmlContent)) {
                throw new \Exception('API boş HTML içeriği döndürdü.');
            }
            
        } catch (\Exception $e) {
            $errorHtml = '
            <!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><title>Hata</title><style>body{font-family:sans-serif;margin:40px;}.alert{padding:20px;background-color:#f8d7da;color:#721c24;border:1px solid #f5c6cb;}</style></head>
            <body><div class="alert"><h3>Fatura HTML içeriği yüklenemedi</h3><p><strong>Hata:</strong> '.htmlspecialchars($e->getMessage()).'</p></div></body></html>';
            return response($errorHtml, 500)->header('Content-Type', 'text/html; charset=utf-8');
        }
        
        return response($htmlContent)->header('Content-Type', 'text/html; charset=utf-8');
    }








}