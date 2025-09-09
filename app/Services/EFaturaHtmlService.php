<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
class EFaturaHtmlService
{
    private $soapUrl;
    private $archiveSoapUrl;
    private $authService;

    public function __construct(EFaturaAuthService $authService)
    {
        $this->soapUrl = config('efatura.invoice_wsdl', 'https://efaturatest.doganedonusum.com/EFaturaOIB?wsdl');
        $this->archiveSoapUrl = config('efatura.archive_wsdl', 'https://efaturatest.doganedonusum.com/EArchiveOIB?wsdl');
        $this->authService = $authService;
    }

    /**
     * Fatura HTML görünümünü çeker (Normal E-Fatura)
     */
    public function getInvoiceHtml(string $invoiceId, string $direction = 'OUT'): string
    {
        try {
            // Session ID al
            $sessionId = $this->authService->login();
            
            if (empty($sessionId)) {
                throw new \Exception("Authentication failed - Session ID is empty");
            }
            
            \Log::info('E-Fatura HTML Request Started', [
                'invoice_id' => $invoiceId,
                'direction' => $direction,
                'session_id' => substr($sessionId, 0, 10) . '...' // Güvenlik için kısalt
            ]);
            
            $xmlRequest = $this->buildHtmlRequestXml($sessionId, $invoiceId, $direction);
            
            $response = $this->sendSoapRequest($this->soapUrl, $xmlRequest);
            
            return $this->parseHtmlResponse($response);
            
        } catch (\Exception $e) {
            \Log::error('E-Fatura HTML Service Error', [
                'invoice_id' => $invoiceId,
                'direction' => $direction,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);
            
            throw new \Exception("Failed to fetch HTML for invoice {$invoiceId}: " . $e->getMessage());
        }
    }

    /**
     * E-Arşiv fatura HTML görünümünü çeker
     */
    public function getArchiveInvoiceHtml(string $invoiceId, string $direction = 'OUT'): string
    {
        try {
            // Session ID al
            $sessionId = $this->authService->login();
            
            if (empty($sessionId)) {
                throw new \Exception("Authentication failed - Session ID is empty");
            }
            
            \Log::info('E-Archive HTML Request Started', [
                'invoice_id' => $invoiceId,
                'direction' => $direction,
                'session_id' => substr($sessionId, 0, 10) . '...'
            ]);
            
            $xmlRequest = $this->buildArchiveHtmlRequestXml($sessionId, $invoiceId, $direction);
            
            $response = $this->sendSoapRequest($this->archiveSoapUrl, $xmlRequest);
            
            return $this->parseArchiveHtmlResponse($response);
            
        } catch (\Exception $e) {
            \Log::error('E-Archive HTML Service Error', [
                'invoice_id' => $invoiceId,
                'direction' => $direction,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);
            
            throw new \Exception("Failed to fetch Archive HTML for invoice {$invoiceId}: " . $e->getMessage());
        }
    }

    /**
     * Fatura tipine göre otomatik HTML çeker
     */
    public function getInvoiceHtmlByType(string $invoiceId, string $invoiceType, string $direction = 'OUT'): string
    {
        if (strtoupper($invoiceType) === 'ARCHIVE') {
            return $this->getArchiveInvoiceHtml($invoiceId, $direction);
        } else {
            return $this->getInvoiceHtml($invoiceId, $direction);
        }
    }

    /**
     * Normal E-Fatura HTML request XML'ini oluşturur
     */
    private function buildHtmlRequestXml(string $sessionId, string $invoiceId, string $direction): string
    {
        return <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:wsdl="http://schemas.i2i.com/ei/wsdl">
  <soapenv:Header/>
  <soapenv:Body>
    <wsdl:GetInvoiceWithTypeRequest>
      <REQUEST_HEADER>
        <SESSION_ID>{$sessionId}</SESSION_ID>
        <APPLICATION_NAME>ERP</APPLICATION_NAME>
        <CHANNEL_NAME>ERP</CHANNEL_NAME>
        <COMPRESSED>N</COMPRESSED>
      </REQUEST_HEADER>
      <INVOICE_SEARCH_KEY>
        <UUID>{$invoiceId}</UUID>
        <TYPE>HTML</TYPE>
        <DIRECTION>{$direction}</DIRECTION>
      </INVOICE_SEARCH_KEY>
      <HEADER_ONLY>N</HEADER_ONLY>
    </wsdl:GetInvoiceWithTypeRequest>
  </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    /**
     * E-Arşiv HTML request XML'ini oluşturur
     */
    private function buildArchiveHtmlRequestXml(string $sessionId, string $invoiceId, string $direction): string
    {
        return <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" 
                  xmlns:arc="http://schemas.i2i.com/ei/wsdl/archive">
   <soapenv:Header/>
   <soapenv:Body>
      <arc:ArchiveInvoiceReadRequest>
         <REQUEST_HEADER>
            <SESSION_ID>{$sessionId}</SESSION_ID>
            <COMPRESSED>N</COMPRESSED>
         </REQUEST_HEADER>
         <INVOICEID>{$invoiceId}</INVOICEID>
         <PORTAL_DIRECTION>{$direction}</PORTAL_DIRECTION>
         <PROFILE>HTML</PROFILE>
      </arc:ArchiveInvoiceReadRequest>
   </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    /**
     * SOAP request gönder
     */
    private function sendSoapRequest(string $url, string $xmlRequest): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlRequest);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: text/xml;charset=UTF-8",
            "SOAPAction: ''"
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlInfo = curl_getinfo($ch);
        curl_close($ch);
        
        // Detaylı hata kontrolü
        if ($response === false) {
            throw new \Exception("CURL failed: {$curlError}");
        }
        
        if ($httpCode !== 200) {
            \Log::error('SOAP HTTP Error', [
                'http_code' => $httpCode,
                'url' => $url,
                'curl_info' => $curlInfo,
                'response_preview' => substr($response, 0, 500)
            ]);
            throw new \Exception("HTTP {$httpCode} error. Response: " . substr($response, 0, 200));
        }
        
        \Log::info('SOAP Response Received', [
            'http_code' => $httpCode,
            'response_length' => strlen($response),
            'url' => $url
        ]);
        
        return $response;
    }

    /**
     * Normal E-Fatura HTML response'unu parse eder
     */
    private function parseHtmlResponse(string $response): string
    {
        // XML'i yükle
        $xml = simplexml_load_string($response);
        
        if ($xml === false) {
            throw new \Exception('Invalid XML response');
        }
        
        // Namespace'leri kaydet
        $xml->registerXPathNamespace('soapenv', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('wsdl', 'http://schemas.i2i.com/ei/wsdl');
        
        // Doğru XPath - dönen yanıta göre
        $htmlContent = $xml->xpath('//wsdl:GetInvoiceWithTypeResponse/INVOICE/CONTENT');
        
        if (empty($htmlContent)) {
            // Hata durumu kontrolü
            $errorContent = $xml->xpath('//wsdl:GetInvoiceWithTypeResponse/ERROR_TYPE');
            if (!empty($errorContent)) {
                throw new \Exception('E-Fatura servisi hatası: ' . (string)$errorContent[0]);
            }
            
            throw new \Exception('HTML content not found in response');
        }
        
        // Base64 decode edilmiş HTML içeriği
        $decodedContent = base64_decode((string)$htmlContent[0]);
        
        if ($decodedContent === false) {
            throw new \Exception('Failed to decode base64 HTML content');
        }
        
        return $decodedContent;
    }

    /**
     * E-Arşiv HTML response'unu parse eder
     */
    private function parseArchiveHtmlResponse(string $response): string
    {
        // XML'i yükle
        $xml = simplexml_load_string($response);
        
        if ($xml === false) {
            throw new \Exception('Invalid XML response');
        }
        
        // Namespace'leri kaydet
        $xml->registerXPathNamespace('soapenv', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('arc', 'http://schemas.i2i.com/ei/wsdl/archive');
        
        // E-Arşiv response yapısına göre XPath
        $htmlContent = $xml->xpath('//arc:ArchiveInvoiceReadResponse/INVOICE');
        
        if (empty($htmlContent)) {
            // Hata durumu kontrolü
            $errorContent = $xml->xpath('//arc:ArchiveInvoiceReadResponse/REQUEST_RETURN/RETURN_CODE');
            if (!empty($errorContent) && (string)$errorContent[0] !== '0') {
                $errorDesc = $xml->xpath('//arc:ArchiveInvoiceReadResponse/REQUEST_RETURN/ERROR_TYPE');
                $errorMessage = !empty($errorDesc) ? (string)$errorDesc[0] : 'Unknown error';
                throw new \Exception('E-Arşiv servisi hatası: ' . $errorMessage);
            }
            
            throw new \Exception('Archive HTML content not found in response');
        }
        
        // Base64 decode edilmiş HTML içeriği
        $decodedContent = base64_decode((string)$htmlContent[0]);
        
        if ($decodedContent === false) {
            throw new \Exception('Failed to decode base64 Archive HTML content');
        }
        
        return $decodedContent;
    }

    /**
     * Fatura HTML'ini database'e kaydeder
     */
    public function saveInvoiceHtml(string $invoiceId, string $htmlContent): bool
    {
        $invoice = \App\Models\Invoice::where('invoice_id', $invoiceId)->first();
        
        if ($invoice) {
            $invoice->update(['content' => $htmlContent]);
            return true;
        }
        
        return false;
    }
}