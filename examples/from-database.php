<?php

// Coretax XML from a typical ERP schema: an invoice header table joined to
// customers, and a detail table. Column names here are made up; mapping your
// own is the only part you need to change.

require __DIR__ . '/../vendor/autoload.php';

use Elrayn\CoretaxFaktur\Buyer;
use Elrayn\CoretaxFaktur\CoretaxXml;
use Elrayn\CoretaxFaktur\Faktur;
use Elrayn\CoretaxFaktur\Item;
use Elrayn\CoretaxFaktur\Unit;
use Elrayn\CoretaxFaktur\ValidationException;

$pdo = new PDO('mysql:host=localhost;dbname=erp', 'root', '');
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$invoices = $pdo->query("
    SELECT i.no_invoice, i.tanggal, i.include_ppn, c.nama, c.npwp, c.ktp,
           CONCAT_WS(' ', c.alamat, c.alamat2, c.alamat3) AS alamat
    FROM invoice i JOIN customer c ON c.id = i.customer_id
    WHERE i.tanggal BETWEEN '2025-10-01' AND '2025-10-31'
")->fetchAll();

$details = $pdo->prepare('SELECT nama_barang, qty, harga, satuan, disc, disc2, disc3 FROM invoice_detail WHERE no_invoice = ?');

$xml = CoretaxXml::make('1090000000002325');

foreach ($invoices as $invoice) {
    // NPWP if the customer has a real one, else NIK, else Other ID.
    $buyer = Buyer::detect($invoice['npwp'], $invoice['ktp'], $invoice['nama'], $invoice['alamat']);

    $faktur = Faktur::make($invoice['tanggal'], $buyer)
        ->reference($invoice['no_invoice'])
        ->priceIncludesVat((bool) $invoice['include_ppn']);

    $details->execute([$invoice['no_invoice']]);
    foreach ($details->fetchAll() as $row) {
        $faktur->add(
            Item::goods($row['nama_barang'], $row['qty'], $row['harga'], $row['satuan'] === 'LSN' ? Unit::LUSIN : Unit::PIECE)
                ->discountPercent($row['disc'], $row['disc2'], $row['disc3'])
        );
    }

    $xml->add($faktur);
}

// Show warnings (e.g. odd emails) without blocking the export.
foreach ($xml->validate() as $issue) {
    error_log((string) $issue);
}

try {
    $xml->download('faktur_keluaran_2025_10.xml');
} catch (ValidationException $e) {
    // Every problem at once, with faktur/item position, instead of Coretax
    // rejecting the upload one row at a time.
    http_response_code(422);
    foreach ($e->issues() as $issue) {
        echo $issue, "\n";
    }
}
