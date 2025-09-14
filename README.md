# KURULUM 

- PHP Versiyon kontrol et
php -v komutu ile. 
- Php 8.3.25 versiyonu kur.

# SQL Tabloları

## Out Invoices
```sql 
CREATE TABLE zt_invoices_out (
    id BIGINT IDENTITY(1,1) PRIMARY KEY,
    invoice_id NVARCHAR(255) NOT NULL UNIQUE,
    uuid NVARCHAR(255) NOT NULL UNIQUE,
    supplier NVARCHAR(255) NOT NULL,
    customer NVARCHAR(255) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    issue_date DATE NOT NULL,
    content NTEXT NULL,
    raw_data NVARCHAR(MAX) NULL,
    created_at DATETIME2 NOT NULL DEFAULT GETDATE(),
    updated_at DATETIME2 NOT NULL DEFAULT GETDATE(),
    type NVARCHAR(255) NOT NULL
);
````

## In Invoices
```sql 
CREATE TABLE zt_invoices_in (
id BIGINT IDENTITY(1,1) PRIMARY KEY,
invoice_id NVARCHAR(255) NOT NULL UNIQUE,
uuid NVARCHAR(255) NOT NULL UNIQUE,
supplier NVARCHAR(255) NOT NULL,
customer NVARCHAR(255) NOT NULL,
amount DECIMAL(15,2) NOT NULL,
issue_date DATE NOT NULL,
content NTEXT NULL,
raw_data NVARCHAR(MAX) NULL,
created_at DATETIME2 NOT NULL DEFAULT GETDATE(),
updated_at DATETIME2 NOT NULL DEFAULT GETDATE(),
type NVARCHAR(255) NOT NULL
);
````

## Archive Invoices
```sql 
CREATE TABLE zt_invoices_archive (
id BIGINT IDENTITY(1,1) PRIMARY KEY,
invoice_id NVARCHAR(255) NOT NULL UNIQUE,
uuid NVARCHAR(255) NOT NULL UNIQUE,
supplier NVARCHAR(255) NOT NULL,
customer NVARCHAR(255) NOT NULL,
amount DECIMAL(15,2) NOT NULL,
issue_date DATE NOT NULL,
content NTEXT NULL,
raw_data NVARCHAR(MAX) NULL,
created_at DATETIME2 NOT NULL DEFAULT GETDATE(),
updated_at DATETIME2 NOT NULL DEFAULT GETDATE(),
type NVARCHAR(255) NOT NULL
);
````

- PHP SQL SERVER DLL VE PHP INI yükle !!!!!

DEPLOY
https://gist.github.com/amestsantim/d79cc93fe98d164d13a11eda473a7a9a



DOSYALARA İZİN VER

icacls "C:\inetpub\wwwroot\fapp\storage" /grant "IIS_IUSRS:(OI)(CI)F" /T
icacls "C:\inetpub\wwwroot\fapp\bootstrap\cache" /grant "IIS_IUSRS:(OI)(CI)F" /T

