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

    /**
     * Son 3 ay içindeki giden faturaları çeker - tüm credentials için
     */
    public function syncOutgoingInvoices(): array
    {
        $allResults = [];
        $credentials = $this->authService->getAllCredentials();
        
        if (empty($credentials)) {
            throw new \Exception('No credentials found for E-Fatura authentication');
        }
        
        foreach ($credentials as $index => $credential) {
            try {
                \Log::info("Giden faturalar sync başlatıldı", [
                    'credential_index' => $index + 1,
                    'username' => $credential['username']
                ]);
                
                $sessionId = $this->authService->loginWithCredentials($credential);
                $invoices = $this->getInvoices($sessionId, 'OUT');
                $result = $this->saveInvoices($invoices, 'OUT');
                
                $result['credential_username'] = $credential['username'];
                $result['credential_index'] = $index + 1;
                $allResults[] = $result;
                
                \Log::info("Giden faturalar sync tamamlandı", [
                    'credential_index' => $index + 1,
                    'username' => $credential['username'],
                    'saved' => $result['saved'],
                    'updated' => $result['updated']
                ]);
                
            } catch (\Exception $e) {
                \Log::error("Giden faturalar sync hatası", [
                    'credential_index' => $index + 1,
                    'username' => $credential['username'],
                    'error' => $e->getMessage()
                ]);
                
                $allResults[] = [
                    'total' => 0,
                    'saved' => 0,
                    'updated' => 0,
                    'type' => 'OUT',
                    'credential_username' => $credential['username'],
                    'credential_index' => $index + 1,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $this->combineResults($allResults, 'OUT');
    }

    /**
     * Son 3 ay içindeki gelen faturaları çeker - tüm credentials için
     */
    public function syncIncomingInvoices(): array
    {
        $allResults = [];
        $credentials = $this->authService->getAllCredentials();
        
        if (empty($credentials)) {
            throw new \Exception('No credentials found for E-Fatura authentication');
        }
        
        foreach ($credentials as $index => $credential) {
            try {
                \Log::info("Gelen faturalar sync başlatıldı", [
                    'credential_index' => $index + 1,
                    'username' => $credential['username']
                ]);
                
                $sessionId = $this->authService->loginWithCredentials($credential);
                $invoices = $this->getInvoices($sessionId, 'IN');
                $result = $this->saveInvoices($invoices, 'IN');
                
                $result['credential_username'] = $credential['username'];
                $result['credential_index'] = $index + 1;
                $allResults[] = $result;
                
                \Log::info("Gelen faturalar sync tamamlandı", [
                    'credential_index' => $index + 1,
                    'username' => $credential['username'],
                    'saved' => $result['saved'],
                    'updated' => $result['updated']
                ]);
                
            } catch (\Exception $e) {
                \Log::error("Gelen faturalar sync hatası", [
                    'credential_index' => $index + 1,
                    'username' => $credential['username'],
                    'error' => $e->getMessage()
                ]);
                
                $allResults[] = [
                    'total' => 0,
                    'saved' => 0,
                    'updated' => 0,
                    'type' => 'IN',
                    'credential_username' => $credential['username'],
                    'credential_index' => $index + 1,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $this->combineResults($allResults, 'IN');
    }

    /**
     * E-Arşiv faturalarını çeker - tüm credentials için
     */
    public function syncArchiveInvoices(): array
    {
        $allResults = [];
        $credentials = $this->authService->getAllCredentials();
        
        if (empty($credentials)) {
            throw new \Exception('No credentials found for E-Fatura authentication');
        }
        
        foreach ($credentials as $index => $credential) {
            try {
                \Log::info("E-Arşiv sync başlatıldı", [
                    'credential_index' => $index + 1,
                    'username' => $credential['username']
                ]);
                
                $sessionId = $this->authService->loginWithCredentials($credential);
                $invoices = $this->getArchiveInvoices($sessionId);
                $result = $this->saveInvoices($invoices, 'ARCHIVE');
                
                $result['credential_username'] = $credential['username'];
                $result['credential_index'] = $index + 1;
                $allResults[] = $result;
                
                \Log::info("E-Arşiv sync tamamlandı", [
                    'credential_index' => $index + 1,
                    'username' => $credential['username'],
                    'saved' => $result['saved'],
                    'updated' => $result['updated']
                ]);
                
            } catch (\Exception $e) {
                \Log::error("E-Arşiv sync hatası", [
                    'credential_index' => $index + 1,
                    'username' => $credential['username'],
                    'error' => $e->getMessage()
                ]);
                
                $allResults[] = [
                    'total' => 0,
                    'saved' => 0,
                    'updated' => 0,
                    'type' => 'ARCHIVE',
                    'credential_username' => $credential['username'],
                    'credential_index' => $index + 1,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $this->combineResults($allResults, 'ARCHIVE');
    }


    private function getInvoices(string $sessionId, string $direction): array
    {
        //$dates = $this->getLastThreeMonthsDates();
        $dates = $this->getLastOneMonthsDate($direction);
        $xmlRequest = $this->buildInvoiceRequestXml($sessionId, $direction, $dates['start'], $dates['end']);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->soapUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlRequest);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: text/xml;charset=UTF-8",
            'SOAPAction: ""'
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int) config('efatura.timeout', 30));
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) config('efatura.timeout', 30));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_ENCODING, '');

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrNo = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $curlErrNo !== 0) {
            throw new \Exception("Failed to fetch {$direction} invoices: " . ($curlError ?: 'unknown cURL error'));
        }

        if ($httpCode !== 200) {
            throw new \Exception("HTTP Error {$httpCode} for {$direction} invoices");
        }

        \Log::debug("EFatura {$direction} invoices raw (truncated): " . substr((string) $response, 0, 1000));

        return $this->parseInvoicesResponse($response);
    }

    /**
     * E-Arşiv faturalarını özel endpoint'ten çeker
     */
    private function getArchiveInvoices(string $sessionId): array
    {
        //$dates = $this->getLastThreeMonthsDates();
        $dates = $this->getLastOneMonthsDate('ARCHIVE');
        $xmlRequest = $this->buildArchiveRequestXml($sessionId, $dates['start'], $dates['end']);

        \Log::info('E-Arşiv request gönderiliyor', [
            'url' => $this->archiveUrl,
            'start_date' => $dates['start'],
            'end_date' => $dates['end']
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->archiveUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlRequest);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: text/xml;charset=UTF-8",
            'SOAPAction: ""'
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int) config('efatura.timeout', 30));
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) config('efatura.timeout', 30));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_ENCODING, '');

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrNo = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $curlErrNo !== 0) {
            \Log::error('E-Arşiv CURL hatası', [
                'error' => $curlError,
                'errno' => $curlErrNo,
                'url' => $this->archiveUrl
            ]);
            throw new \Exception("Failed to fetch archive invoices: " . ($curlError ?: 'unknown cURL error'));
        }

        if ($httpCode !== 200) {
            \Log::error('E-Arşiv HTTP hatası', [
                'http_code' => $httpCode,
                'response_preview' => substr($response, 0, 500)
            ]);
            throw new \Exception("HTTP Error {$httpCode} for archive invoices");
        }

        \Log::debug("EFatura ARCHIVE invoices response", [
            'response_length' => strlen($response),
            'response_preview' => substr($response, 0, 1000)
        ]);

        return $this->parseArchiveInvoicesResponse($response);
    }

    /**
     * Fatura listesi request XML'ini oluşturur
     */
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

    /**
     * E-Arşiv için XML request oluşturur - DÜZELTME
     */
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
         
         <!-- Tarih aralığı ile sorgulama -->
         <START_DATE>{$startDate}T00:00:00+03:00</START_DATE>
         <END_DATE>{$endDate}T23:59:00+03:00</END_DATE>
         
      
         <HEADER_ONLY>Y</HEADER_ONLY>
        
         <READ_INCLUDED>Y</READ_INCLUDED>
      </arc:GetEArchiveInvoiceListRequest>
   </soapenv:Body>
</soapenv:Envelope>

XML;
    }

    /**
     * Son 3 ayın tarih aralığını döndürür
     */
    private function getLastThreeMonthsDates(): array
    {
            $today = Carbon::now();

            $startDate = $today->copy()->subMonths(3);


            $date = [
                'start' => $startDate->format('Y-m-d'),
                'end'   => $today->format('Y-m-d'),
            ];

            return $date;
    }

    /**
     * E-Arşiv response'unu parse eder - DÜZELTME
     */
    private function parseArchiveInvoicesResponse(string $response): array
    {
        $xml = simplexml_load_string($response);
        
        if ($xml === false) {
            throw new \Exception('E-Arşiv response XML parse edilemedi');
        }
        
        $xml->registerXPathNamespace('soapenv', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('arc', 'http://schemas.i2i.com/ei/wsdl/archive');

        // SOAP Fault kontrolü
        $soapFault = $xml->xpath('//soapenv:Fault');
        if (!empty($soapFault)) {
            $faultString = $xml->xpath('//soapenv:Fault/faultstring');
            $errorMsg = !empty($faultString) ? (string)$faultString[0] : 'SOAP Fault occurred';
            throw new \Exception("E-Arşiv SOAP Fault: {$errorMsg}");
        }

        // E-Arşiv için doğru XPath
        $invoices = $xml->xpath('//arc:GetEArchiveInvoiceListResponse/INVOICE');

        \Log::info('E-Arşiv XPath sonucu', [
            'invoice_count' => count($invoices),
            'xpath_used' => '//arc:GetEArchiveInvoiceListResponse/INVOICE'
        ]);

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

    /**
     * API response'unu parse eder ve fatura listesi döndürür
     */
    private function parseInvoicesResponse(string $response): array
    {
        $xml = simplexml_load_string($response);
        
        if ($xml === false) {
            throw new \Exception('Response XML parse edilemedi');
        }
        
        $xml->registerXPathNamespace('soapenv', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('ns', 'http://schemas.i2i.com/ei/wsdl');

        // SOAP Fault kontrolü
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

    /**
     * Tarih parse işlemi
     */
    private function parseDate(string $dateString): ?string
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            // ISO 8601 formatını parse et (2025-08-21+03:00)
            $date = Carbon::parse($dateString);
            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            \Log::warning('Tarih parse hatası', [
                'date_string' => $dateString,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Faturaları database'e kaydeder
     */
    private function saveInvoices(array $invoices, string $type): array
    {
        $savedCount = 0;
        $updatedCount = 0;

        foreach ($invoices as $invoiceData) {
            $invoiceData['type'] = $type;
            $invoiceData['created_at'] = now();
            $invoiceData['updated_at'] = now();
            
            try {
                // Route persistence to MSSQL zt_ tables by type
                if ($type === 'OUT') {
                    $existing = \DB::connection('sqlsrv')->table('zt_invoices_out')
                        ->where('uuid', $invoiceData['uuid'])->first();
                    
                    if ($existing) {
                        \DB::connection('sqlsrv')->table('zt_invoices_out')
                            ->where('uuid', $invoiceData['uuid'])
                            ->update($invoiceData);
                        $updatedCount++;
                    } else {
                        \DB::connection('sqlsrv')->table('zt_invoices_out')->insert($invoiceData);
                        $savedCount++;
                    }
                } elseif ($type === 'IN') {
                    $existing = \DB::connection('sqlsrv')->table('zt_invoices_in')
                        ->where('uuid', $invoiceData['uuid'])->first();
                    
                    if ($existing) {
                        \DB::connection('sqlsrv')->table('zt_invoices_in')
                            ->where('uuid', $invoiceData['uuid'])
                            ->update($invoiceData);
                        $updatedCount++;
                    } else {
                        \DB::connection('sqlsrv')->table('zt_invoices_in')->insert($invoiceData);
                        $savedCount++;
                    }
                } elseif ($type === 'ARCHIVE') {
                    $existing = \DB::connection('sqlsrv')->table('zt_invoices_archive')
                        ->where('uuid', $invoiceData['uuid'])->first();
                    
                    if ($existing) {
                        \DB::connection('sqlsrv')->table('zt_invoices_archive')
                            ->where('uuid', $invoiceData['uuid'])
                            ->update($invoiceData);
                        $updatedCount++;
                    } else {
                        \DB::connection('sqlsrv')->table('zt_invoices_archive')->insert($invoiceData);
                        $savedCount++;
                    }
                }
            } catch (\Exception $e) {
                \Log::error('Fatura kaydetme hatası', [
                    'type' => $type,
                    'invoice_id' => $invoiceData['invoice_id'] ?? 'unknown',
                    'uuid' => $invoiceData['uuid'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'total' => count($invoices),
            'saved' => $savedCount,
            'updated' => $updatedCount,
            'type' => $type
        ];
    }




private function getLastsOneMonthsDate(string $type): array
{
    $connection = DB::connection('sqlsrv');
    $table = '';

    switch ($type) {
        case 'OUT':
            $table = 'zt_invoices_out';
            $dateColumn = 'issue_date';
            break;
        case 'IN':
            $table = 'zt_invoices_in';
            $dateColumn = 'issue_date';
            break;
        case 'ARCHIVE':
            $table = 'zt_invoices_archive';
            $dateColumn = 'issue_date';
            break;
        default:
            throw new \InvalidArgumentException('Geçersiz fatura tipi: ' . $type);
    }

    $lastDate = $connection->table($table)->max($dateColumn); // En son tarih
    $endDate = $lastDate ? Carbon::parse($lastDate) : Carbon::now();
    $startDate = $endDate->copy()->subMonths(3); // Son 1 ay

    return [
        'start' => $startDate->format('Y-m-d'),
        'end' => $endDate->format('Y-m-d')
    ];
}

private function getLastOneMonthsDate(string $type): array
{
    $today = Carbon::now();


    // Hangi tabloya bakacağını seç
    $table = match ($type) {
        'OUT'     => 'zt_invoices_out',
        'IN'      => 'zt_invoices_in',
        'ARCHIVE' => 'zt_invoices_archive',
        default   => null,
    };

    $lastDate = null;

    if ($table) {
        try {
            $lastDate = DB::connection('sqlsrv')
                ->table($table)
                ->max('issue_date'); // en son kaydedilen fatura tarihi
        } catch (\Exception $e) {
            \Log::warning("Son sync tarihi alınamadı", [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
        }
    }

    if (!$lastDate) {
        $startDate = $today->copy()->subMonths(3);
    } else {
        //$startDate = Carbon::parse($lastDate)->subDay(1); 
        //$startDate = $today->copy()->subMonths(1);
        $startDate = Carbon::parse($lastDate)->subDays(3);
    }



    return [
        'start' => $startDate->format('Y-m-d'),
        'end'   => $today->format('Y-m-d'),
    ];
}

    /**
     * Multiple credential results'ları birleştirir
     */
    private function combineResults(array $allResults, string $type): array
    {
        $totalSaved = 0;
        $totalUpdated = 0;
        $totalCount = 0;
        $errors = [];
        $credentials = [];

        foreach ($allResults as $result) {
            $totalSaved += $result['saved'];
            $totalUpdated += $result['updated'];
            $totalCount += $result['total'];
            
            $credentials[] = [
                'username' => $result['credential_username'],
                'index' => $result['credential_index'],
                'saved' => $result['saved'],
                'updated' => $result['updated'],
                'total' => $result['total']
            ];
            
            if (isset($result['error'])) {
                $errors[] = [
                    'username' => $result['credential_username'],
                    'error' => $result['error']
                ];
            }
        }

        return [
            'total' => $totalCount,
            'saved' => $totalSaved,
            'updated' => $totalUpdated,
            'type' => $type,
            'credentials' => $credentials,
            'errors' => $errors,
            'credential_count' => count($allResults)
        ];
    }

}