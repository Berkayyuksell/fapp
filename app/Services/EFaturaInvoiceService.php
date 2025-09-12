<?php

namespace App\Services;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EFaturaInvoiceService
{
    private $soapUrl;
    private $archiveUrl;
    private $authService;

    public function __construct(EFaturaAuthService $authService)
    {
        $this->soapUrl = config('efatura.invoice_wsdl', 'https://api.doganedonusum.com/EFaturaOIB?wsdl');
        $this->archiveUrl = config('efatura.archive_wsdl', 'https://api.doganedonusum.com/EIArchiveWS/EFaturaArchive?wsdl');
        $this->authService = $authService;
    }

    public function syncOutgoingInvoices(): array
    {
        return $this->syncInvoicesByType('OUT');
    }

    public function syncIncomingInvoices(): array
    {
        return $this->syncInvoicesByType('IN');
    }

    public function syncArchiveInvoices(): array
    {
        return $this->syncInvoicesByType('ARCHIVE');
    }

    private function syncInvoicesByType(string $type): array
    {
        $allResults = [];
        $credentials = $this->authService->getAllCredentials();

        if (empty($credentials)) {
            throw new \Exception('No credentials found for E-Fatura authentication');
        }

        foreach ($credentials as $index => $credential) {
            try {
                \Log::info("{$type} faturalar sync başlatıldı", [
                    'credential_index' => $index + 1,
                    'username' => $credential['username']
                ]);

                $sessionId = $this->authService->loginWithCredentials($credential);

                if ($type === 'ARCHIVE') {
                    $result = $this->getArchiveInvoicesInChunks($sessionId, $credential);
                } else {
                    $result = $this->getInvoicesInChunks($sessionId, $type, $credential);
                }

                $result['credential_username'] = $credential['username'];
                $result['credential_index'] = $index + 1;
                $allResults[] = $result;

                \Log::info("{$type} faturalar sync tamamlandı", [
                    'credential_index' => $index + 1,
                    'username' => $credential['username'],
                    'saved' => $result['saved'],
                    'updated' => $result['updated'],
                    'chunks_processed' => $result['chunks_processed']
                ]);

            } catch (\Exception $e) {
                \Log::error("{$type} faturalar sync hatası", [
                    'credential_index' => $index + 1,
                    'username' => $credential['username'],
                    'error' => $e->getMessage()
                ]);

                $allResults[] = [
                    'total' => 0,
                    'saved' => 0,
                    'updated' => 0,
                    'type' => $type,
                    'credential_username' => $credential['username'],
                    'credential_index' => $index + 1,
                    'chunks_processed' => 0,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $this->combineResults($allResults, $type);
    }

    private function getInvoicesInChunks(string $sessionId, string $direction, array $credential): array
    {
        $dateRanges = $this->getDateRangesInChunks($direction);
        $allInvoices = [];
        $chunksProcessed = 0;

        \Log::info("Parçalı sync başlatıldı", [
            'direction' => $direction,
            'total_chunks' => count($dateRanges),
            'username' => $credential['username']
        ]);

        foreach ($dateRanges as $dateRange) {
            try {
                \Log::info("Parça işleniyor", [
                    'direction' => $direction,
                    'start_date' => $dateRange['start'],
                    'end_date' => $dateRange['end'],
                    'username' => $credential['username']
                ]);

                $xmlRequest = $this->buildInvoiceRequestXml($sessionId, $direction, $dateRange['start'], $dateRange['end']);
                $response = $this->makeCurlRequest($xmlRequest, $this->soapUrl, "{$direction} invoices for range {$dateRange['start']} to {$dateRange['end']}");
                $invoices = $this->parseInvoicesResponse($response);

                $allInvoices = array_merge($allInvoices, $invoices);
                $chunksProcessed++;

                \Log::info("Parça tamamlandı", [
                    'direction' => $direction,
                    'chunk_invoices' => count($invoices),
                    'total_invoices' => count($allInvoices),
                    'chunks_processed' => $chunksProcessed
                ]);

                usleep(500000);

            } catch (\Exception $e) {
                \Log::error("Parça işleme hatası", [
                    'direction' => $direction,
                    'start_date' => $dateRange['start'],
                    'end_date' => $dateRange['end'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        $result = $this->saveInvoices($allInvoices, $direction);
        $result['chunks_processed'] = $chunksProcessed;

        return $result;
    }

    private function getArchiveInvoicesInChunks(string $sessionId, array $credential): array
    {
        $dateRanges = $this->getDateRangesInChunks('ARCHIVE');
        $chunksProcessed = 0;
        $totalSaved = 0;
        $totalUpdated = 0;

        \Log::info("E-Arşiv parçalı sync başlatıldı", [
            'total_chunks' => count($dateRanges),
            'username' => $credential['username']
        ]);

        foreach ($dateRanges as $dateRange) {
            try {
                \Log::info("E-Arşiv parça işleniyor", [
                    'start_date' => $dateRange['start'],
                    'end_date' => $dateRange['end'],
                    'username' => $credential['username']
                ]);

                $xmlRequest = $this->buildArchiveRequestXml($sessionId, $dateRange['start'], $dateRange['end']);
                $response = $this->makeCurlRequest($xmlRequest, $this->archiveUrl, "Archive invoices for range {$dateRange['start']} to {$dateRange['end']}");
                $invoices = $this->parseArchiveInvoicesResponse($response);

                $result = $this->saveInvoices($invoices, 'ARCHIVE');

                $totalSaved += $result['saved'];
                $totalUpdated += $result['updated'];
                $chunksProcessed++;

                \Log::info("E-Arşiv parça tamamlandı", [
                    'chunk_invoices' => count($invoices),
                    'saved_in_chunk' => $result['saved'],
                    'updated_in_chunk' => $result['updated'],
                    'total_saved' => $totalSaved,
                    'total_updated' => $totalUpdated,
                    'chunks_processed' => $chunksProcessed
                ]);

                usleep(500000);

            } catch (\Exception $e) {
                \Log::error("E-Arşiv parça işleme hatası", [
                    'start_date' => $dateRange['start'],
                    'end_date' => $dateRange['end'],
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'total' => $totalSaved + $totalUpdated,
            'saved' => $totalSaved,
            'updated' => $totalUpdated,
            'type' => 'ARCHIVE',
            'chunks_processed' => $chunksProcessed
        ];
    }

    private function getDateRangesInChunks(string $type): array
    {
        $lastDateInfo = $this->getLastOneMonthsDate($type);
        $startDate = Carbon::parse($lastDateInfo['start']);
        $endDate = Carbon::parse($lastDateInfo['end']);

        $dateRanges = [];
        $chunkSizeDays = 2;

        $currentStart = $startDate->copy();

        while ($currentStart->lte($endDate)) {
            $currentEnd = $currentStart->copy()->addDays($chunkSizeDays - 1);
            if ($currentEnd->gt($endDate)) $currentEnd = $endDate->copy();

            $dateRanges[] = [
                'start' => $currentStart->format('Y-m-d'),
                'end' => $currentEnd->format('Y-m-d')
            ];

            $currentStart = $currentEnd->copy()->addDay();
        }

        \Log::info("Tarih aralıkları oluşturuldu", [
            'type' => $type,
            'total_chunks' => count($dateRanges),
            'date_ranges' => $dateRanges
        ]);

        return $dateRanges;
    }

    private function makeCurlRequest(string $xmlRequest, string $url, string $description): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $xmlRequest,
            CURLOPT_HTTPHEADER => [
                "Content-Type: text/xml;charset=UTF-8",
                'SOAPAction: ""'
            ],
            CURLOPT_CONNECTTIMEOUT => 200,
            CURLOPT_TIMEOUT => 200,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING => ''
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrNo = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $curlErrNo !== 0) {
            throw new \Exception("Failed to fetch {$description}: " . ($curlError ?: 'unknown cURL error'));
        }

        if ($httpCode !== 200) {
            throw new \Exception("HTTP Error {$httpCode} for {$description}");
        }

        return $response;
    }

    private function buildInvoiceRequestXml(string $sessionId, string $direction, string $startDate, string $endDate): string
    {
        return <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:tns="http://schemas.i2i.com/ei/wsdl">
  <soapenv:Header/>
  <soapenv:Body>
    <tns:GetInvoiceRequest>
      <REQUEST_HEADER>
          <SESSION_ID>{$sessionId}</SESSION_ID>
        <APPLICATION_NAME>ERP</APPLICATION_NAME>
        <CHANNEL_NAME>ERP</CHANNEL_NAME>
        <COMPRESSED>Y</COMPRESSED>
      </REQUEST_HEADER>
      <INVOICE_SEARCH_KEY>
        <START_DATE>{$startDate}</START_DATE>
        <END_DATE>{$endDate}</END_DATE>
        <READ_INCLUDED>true</READ_INCLUDED>
        <DIRECTION>{$direction}</DIRECTION>
      </INVOICE_SEARCH_KEY>
      <HEADER_ONLY>Y</HEADER_ONLY>
    </tns:GetInvoiceRequest>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    private function buildArchiveRequestXml(string $sessionId, string $startDate, string $endDate): string
    {
        return <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:arc="http://schemas.i2i.com/ei/wsdl/archive">
   <soapenv:Header/>
   <soapenv:Body>
      <arc:GetEArchiveInvoiceListRequest>
         <REQUEST_HEADER>
            <SESSION_ID>{$sessionId}</SESSION_ID>
            <APPLICATION_NAME>ERP</APPLICATION_NAME>
         </REQUEST_HEADER>
         <START_DATE>{$startDate}T00:00:00+03:00</START_DATE>
         <END_DATE>{$endDate}T23:59:00+03:00</END_DATE>
         <HEADER_ONLY>Y</HEADER_ONLY>
         <READ_INCLUDED>Y</READ_INCLUDED>
      </arc:GetEArchiveInvoiceListRequest>
   </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    private function parseInvoicesResponse(string $response): array
    {
        $xml = simplexml_load_string($response);
        if ($xml === false) throw new \Exception('Response XML parse edilemedi');

        $xml->registerXPathNamespace('soapenv', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('ns', 'http://schemas.i2i.com/ei/wsdl');

        $soapFault = $xml->xpath('//soapenv:Fault');
        if (!empty($soapFault)) {
            $faultString = $xml->xpath('//soapenv:Fault/faultstring');
            $errorMsg = !empty($faultString) ? (string)$faultString[0] : 'SOAP Fault occurred';
            throw new \Exception("SOAP Fault: {$errorMsg}");
        }

        $invoices = $xml->xpath('//ns:GetInvoiceResponse/INVOICE');
        $parsedInvoices = [];

        if ($invoices) {
            foreach ($invoices as $invoice) {
                $parsedInvoices[] = [
                    'invoice_id' => (string)($invoice['ID'] ?? ''),
                    'uuid' => (string)($invoice['UUID'] ?? ''),
                    'supplier' => (string)($invoice->HEADER->SUPPLIER ?? ''),
                    'customer' => (string)($invoice->HEADER->CUSTOMER ?? ''),
                    'amount' => (float)($invoice->HEADER->PAYABLE_AMOUNT ?? 0),
                    'issue_date' => $this->parseDate((string)($invoice->HEADER->ISSUE_DATE ?? '')),
                    'raw_data' => json_encode(json_decode(json_encode($invoice), true))
                ];
            }
        }

        return $parsedInvoices;
    }

    private function parseArchiveInvoicesResponse(string $response): array
    {
        $xml = simplexml_load_string($response);
        if ($xml === false) throw new \Exception('E-Arşiv response XML parse edilemedi');

        $xml->registerXPathNamespace('soapenv', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('arc', 'http://schemas.i2i.com/ei/wsdl/archive');

        $soapFault = $xml->xpath('//soapenv:Fault');
        if (!empty($soapFault)) {
            $faultString = $xml->xpath('//soapenv:Fault/faultstring');
            $errorMsg = !empty($faultString) ? (string)$faultString[0] : 'SOAP Fault occurred';
            throw new \Exception("E-Arşiv SOAP Fault: {$errorMsg}");
        }

        $invoices = $xml->xpath('//arc:GetEArchiveInvoiceListResponse/INVOICE');
        $parsedInvoices = [];

        if (!empty($invoices)) {
            foreach ($invoices as $invoice) {
                $parsedInvoices[] = [
                    'invoice_id' => (string)($invoice['ID'] ?? $invoice->HEADER->INVOICE_ID ?? ''),
                    'uuid'       => (string)($invoice['UUID'] ?? $invoice->HEADER->UUID ?? ''),
                    'supplier' => (string)($invoice->HEADER->SENDER_NAME ?? ''),
                    'customer' => (string)($invoice->HEADER->CUSTOMER_NAME ?? ''),
                    'amount' => (float)($invoice->HEADER->PAYABLE_AMOUNT ?? 0),
                    'issue_date' => $this->parseDate((string)($invoice->HEADER->ISSUE_DATE ?? '')),
                    'raw_data' => json_encode(json_decode(json_encode($invoice), true))
                ];
            }
        }

        return $parsedInvoices;
    }

    private function parseDate(string $dateString): ?string
    {
        if (empty($dateString)) return null;

        try {
            return Carbon::parse($dateString)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            \Log::warning('Tarih parse hatası', [
                'date_string' => $dateString,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function saveInvoices(array $invoices, string $type): array
    {
        if (empty($invoices)) return ['total'=>0,'saved'=>0,'updated'=>0,'type'=>$type];

        $savedCount = 0;
        $updatedCount = 0;
        $batchSize = 100;
        $invoiceBatches = array_chunk($invoices, $batchSize);

        $table = match ($type) {
            'OUT' => 'zt_invoices_out',
            'IN' => 'zt_invoices_in',
            'ARCHIVE' => 'zt_invoices_archive',
            default => throw new \InvalidArgumentException('Invalid invoice type: ' . $type)
        };

        foreach ($invoiceBatches as $batchIndex => $batch) {
            $newInvoices = [];
            $updateInvoices = [];

            try {
                $existingUuids = DB::connection('sqlsrv')
                    ->table($table)
                    ->whereIn('uuid', array_column($batch,'uuid'))
                    ->pluck('uuid')
                    ->toArray();

                foreach ($batch as $invoiceData) {
                    $invoiceData['type']=$type;
                    $invoiceData['created_at']=now();
                    $invoiceData['updated_at']=now();

                    if (in_array($invoiceData['uuid'], $existingUuids)) $updateInvoices[]=$invoiceData;
                    else $newInvoices[]=$invoiceData;
                }

                if(!empty($newInvoices)) DB::connection('sqlsrv')->table($table)->insert($newInvoices);
                $savedCount += count($newInvoices);

                foreach($updateInvoices as $updateData){
                    DB::connection('sqlsrv')->table($table)->where('uuid',$updateData['uuid'])->update($updateData);
                    $updatedCount++;
                }

            } catch (\Exception $e) {
                foreach($batch as $invoiceData){
                    try{
                        $invoiceData['type']=$type;
                        $invoiceData['created_at']=now();
                        $invoiceData['updated_at']=now();

                        $existing = DB::connection('sqlsrv')->table($table)->where('uuid',$invoiceData['uuid'])->first();
                        if($existing){
                            DB::connection('sqlsrv')->table($table)->where('uuid',$invoiceData['uuid'])->update($invoiceData);
                            $updatedCount++;
                        } else {
                            DB::connection('sqlsrv')->table($table)->insert($invoiceData);
                            $savedCount++;
                        }
                    } catch (\Exception $singleError){
                        \Log::error('Tek fatura kaydetme hatası',[
                            'type'=>$type,
                            'invoice_id'=>$invoiceData['invoice_id'] ?? 'unknown',
                            'uuid'=>$invoiceData['uuid'] ?? 'unknown',
                            'error'=>$singleError->getMessage()
                        ]);
                    }
                }
            }
        }

        return ['total'=>count($invoices),'saved'=>$savedCount,'updated'=>$updatedCount,'type'=>$type];
    }

    private function getLastOneMonthsDate(string $type): array
    {
        $today = Carbon::now();
        $table = match ($type) {
            'OUT'=>'zt_invoices_out',
            'IN'=>'zt_invoices_in',
            'ARCHIVE'=>'zt_invoices_archive',
            default=>null
        };

        $lastDate=null;
        if($table){
            try{
                $lastDate=DB::connection('sqlsrv')->table($table)->max('issue_date');
            } catch (\Exception $e){
                \Log::warning("Son sync tarihi alınamadı",['type'=>$type,'error'=>$e->getMessage()]);
            }
        }

        $startDate = $lastDate ? Carbon::parse($lastDate)->subDays(3) : $today->copy()->subMonths(1);
        return ['start'=>$startDate->format('Y-m-d'),'end'=>$today->format('Y-m-d')];
    }

    private function combineResults(array $allResults, string $type): array
    {
        $totalSaved = 0;
        $totalUpdated = 0;
        $totalCount = 0;
        $totalChunks = 0;
        $errors = [];
        $credentials = [];

        foreach($allResults as $result){
            $totalSaved+=$result['saved'];
            $totalUpdated+=$result['updated'];
            $totalCount+=$result['total'];
            $totalChunks+=$result['chunks_processed'] ?? 0;

            $credentials[]=[
                'username'=>$result['credential_username'] ?? 'unknown',
                'index'=>$result['credential_index'] ?? 0,
                'saved'=>$result['saved'],
                'updated'=>$result['updated'],
                'total'=>$result['total'],
                'chunks_processed'=>$result['chunks_processed'] ?? 0
            ];

            if(isset($result['error'])) $errors[]=['username'=>$result['credential_username'],'error'=>$result['error']];
        }

        return [
            'total'=>$totalCount,
            'saved'=>$totalSaved,
            'updated'=>$totalUpdated,
            'type'=>$type,
            'credentials'=>$credentials,
            'errors'=>$errors,
            'credential_count'=>count($allResults),
            'total_chunks_processed'=>$totalChunks
        ];
    }
}
