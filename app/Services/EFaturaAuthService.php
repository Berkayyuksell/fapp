<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class EFaturaAuthService
{
    private $wsdlUrl;
    private $credentials;

    public function __construct()
    {
        $this->wsdlUrl = config('efatura.auth_wsdl', 'https://api.doganedonusum.com/AuthenticationWS?wsdl');
        $this->loadCredentials();
    }

    /**
     * Tüm credentials'ları yükler
     */
    private function loadCredentials(): void
    {
        $results = DB::select("
            SELECT UserName, Password 
            FROM EInvoiceWebService('TR') 
            WHERE EInvoiceWebServiceCode = :code
        ", ['code' => 'Dogan']);

        $this->credentials = [];
        
        if (!empty($results)) {
            foreach ($results as $result) {
                $this->credentials[] = [
                    'username' => trim((string) $result->UserName),
                    'password' => trim((string) base64_decode($result->Password))
                ];
            }
        }
    }

    /**
     * Tüm credentials'ları döndürür
     */
    public function getAllCredentials(): array
    {
        return $this->credentials;
    }

    /**
     * Belirli credentials ile E-Fatura sistemine login olur ve session ID döndürür
     */
    public function loginWithCredentials(array $credential): string
    {
        if (empty($credential['username']) || empty($credential['password'])) {
            throw new \Exception('E-Fatura authentication failed: missing credentials');
        }
        
        $authXml = $this->buildAuthXml($credential['username'], $credential['password']);

        $ch = curl_init($this->wsdlUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $authXml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/xml;charset=UTF-8',
            'SOAPAction: ""'
        ]);
        // Timeouts & SSL options (can be tightened later)
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int) config('efatura.timeout', 30));
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) config('efatura.timeout', 30));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_ENCODING, ''); // enable gzip/deflate if server sends compressed

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrNo = curl_errno($ch);
        curl_close($ch);

        if ($response === false || $curlErrNo !== 0) {
            throw new \Exception('E-Fatura authentication failed: ' . ($curlError ?: 'unknown cURL error'));
        }

        \Log::debug('EFatura auth raw response (truncated): ' . substr((string) $response, 0, 1000));

        return $this->parseSessionId($response);
    }

    /**
     * İlk credential ile login (backward compatibility)
     */
    public function login(): string
    {
        if (empty($this->credentials)) {
            throw new \Exception('E-Fatura authentication failed: no credentials available');
        }
        
        return $this->loginWithCredentials($this->credentials[0]);
    }

    /**
     * Authentication XML'ini oluşturur
     */
    private function buildAuthXml(string $username, string $password): string
    {
        $user = $this->escapeCdata($username);
        $pass = $this->escapeCdata($password);
        return <<<XML
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:wsdl="http://schemas.i2i.com/ei/wsdl">
            <soapenv:Header/>
            <soapenv:Body>
                <wsdl:LoginRequest>
                <REQUEST_HEADER>
                    <SESSION_ID>-1</SESSION_ID>
                    <APPLICATION_NAME>ERP</APPLICATION_NAME>
                    <CHANNEL_NAME>ERP</CHANNEL_NAME>
                </REQUEST_HEADER>
                <USER_NAME><![CDATA[{$user}]]></USER_NAME>
                <PASSWORD><![CDATA[{$pass}]]></PASSWORD>
                </wsdl:LoginRequest>
            </soapenv:Body>
            </soapenv:Envelope>
            XML;
    }

    private function escapeCdata(string $value): string
    {
        // Prevent "]]>" from breaking CDATA
        return str_replace(']]>', ']]]]><![CDATA[>', $value);
    }

    /**
     * XML response'dan session ID'yi parse eder
     */
    private function parseSessionId(string $response): string
    {
        $xml = @simplexml_load_string($response);
        if ($xml === false) {
            throw new \Exception('Invalid authentication XML response');
        }
        $xml->registerXPathNamespace('s', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('ns3', 'http://schemas.i2i.com/ei/wsdl');

        // Try multiple xpath patterns to be resilient to namespace variations
        $paths = [
            '//s:Body/ns3:LoginResponse/SESSION_ID',
            '//s:Body/*/SESSION_ID',
            '//*[local-name()="LoginResponse"]/*[local-name()="SESSION_ID"]',
            '//*[local-name()="RESPONSE_HEADER"]/*[local-name()="SESSION_ID"]',
            '//*[local-name()="SESSION_ID"]'
        ];

        foreach ($paths as $path) {
            $result = $xml->xpath($path);
            if (!empty($result)) {
                return (string) $result[0];
            }
        }

        // Check explicit ERROR_TYPE block if present
        $errorShort = $xml->xpath('//*[local-name()="ERROR_SHORT_DES"]');
        $errorLong = $xml->xpath('//*[local-name()="ERROR_LONG_DES"]');
        $errorCode = $xml->xpath('//*[local-name()="ERROR_CODE"]');
        if (!empty($errorShort) || !empty($errorLong) || !empty($errorCode)) {
            $code = !empty($errorCode) ? (string) $errorCode[0] : 'N/A';
            $short = !empty($errorShort) ? (string) $errorShort[0] : '';
            $long = !empty($errorLong) ? (string) $errorLong[0] : '';
            throw new \Exception("Authentication error [$code]: $short $long");
        }

        // Check SOAP Fault message for clearer error
        $fault = $xml->xpath('//*[local-name()="Fault"]/*[local-name()="faultstring"]');
        if (!empty($fault)) {
            throw new \Exception('Authentication fault: ' . (string) $fault[0]);
        }

        throw new \Exception('Session ID not found in authentication response');
    }
}
