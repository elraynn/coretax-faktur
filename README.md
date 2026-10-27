# coretax-faktur

Library PHP untuk membuat file XML Faktur Pajak Keluaran yang bisa langsung di-import ke Coretax.

## Kenapa saya bikin ini

Sejak e-Faktur pindah ke Coretax, alur yang dipakai kebanyakan kantor kira-kira begini: data penjualan diekspor dari aplikasi ke template Excel DJP, Excel itu dibuka di aplikasi *Converter e-Faktur Coretax* (yang hanya jalan di Windows), hasilnya jadi XML, lalu XML itu di-upload ke Coretax.

Saya membuat fitur ekspor Excel itu untuk aplikasi penjualan di kantor, dan awalnya masalahnya banyak. Tanggal kebaca terbalik antara hari dan bulan. Angka desimal pakai koma, padahal yang diminta titik. NPWP kehilangan angka nol di depannya karena Excel menganggapnya angka. Ada customer yang NPWP-nya di database cuma diisi `000000000000000`, tapi tetap terkirim sebagai pembeli ber-NPWP. PPN selisih satu rupiah karena pembulatannya beda di satu langkah. Semua itu baru ketahuan setelah file ditolak, dan biasanya yang pertama tahu justru bagian pajak, bukan programmernya.

Library ini memotong jalur itu. Data dari aplikasi langsung jadi XML, tanpa Excel dan tanpa converter. Sebelum file dibuat, semua aturan kolom dari DJP dicek dulu, jadi kalau ada yang salah, ketahuannya di aplikasi kita sendiri, lengkap dengan nomor faktur dan barisnya.

## Kapan ini berguna

- Aplikasimu (ERP, POS, aplikasi penjualan, apa pun) sudah menyimpan data invoice dan sekarang harus mengirim faktur keluaran ke Coretax.
- Kamu masih memakai jalur Excel → converter dan capek membetulkan file yang ditolak.
- Kamu harus mendukung beberapa kasus sekaligus: harga termasuk PPN, diskon bertingkat, customer tanpa NPWP, kode transaksi 07/08 dengan fasilitas.
- Bagian pajak butuh daftar masalah sebelum upload, bukan setelah ditolak.

Kalau fakturmu cuma beberapa per bulan dan diinput manual di Coretax, kamu tidak butuh library ini.

## Instalasi

```
composer require elrayn/coretax-faktur
```

Butuh PHP 7.4 ke atas dan ekstensi `dom` (biasanya sudah aktif).

Format XML yang dihasilkan mengikuti Converter e-Faktur Coretax v1.6 dan template Excel v1.6.1 dari DJP, versi terbaru saat library ini ditulis. Kalau DJP merilis converter baru dan formatnya berubah, library ini akan ikut diperbarui.

## Contoh paling sederhana

```php
use Elrayn\CoretaxFaktur\Buyer;
use Elrayn\CoretaxFaktur\CoretaxXml;
use Elrayn\CoretaxFaktur\Faktur;
use Elrayn\CoretaxFaktur\Item;
use Elrayn\CoretaxFaktur\Unit;

$faktur = Faktur::make('2025-10-01', Buyer::npwp('01.234.567.8-901.000', 'CV Contoh Jaya', 'Jl. Contoh No. 1, Bogor'))
    ->reference('INV/2025/10/0001')
    ->add(Item::goods('Sabun Mandi 80 gr', 25, 1000000, Unit::PIECE))
    ->add(Item::goods('Kartu Remi', 10, 900000, Unit::LUSIN)->discountPercent(3));

CoretaxXml::make('1090000000002325')   // NPWP penjual
    ->add($faktur)
    ->download('faktur_keluaran.xml');
```

Kamu cukup mengisi harga, jumlah, dan diskon. DPP, DPP Nilai Lain, dan PPN dihitung oleh library. Kode transaksi bawaannya `04` (PPN 12% dari DPP Nilai Lain 11/12), yang dipakai untuk penjualan biasa sejak 2025. Untuk item pertama di atas hasilnya:

```xml
<Price>1000000</Price>
<Qty>25</Qty>
<TotalDiscount>0</TotalDiscount>
<TaxBase>25000000</TaxBase>
<OtherTaxBase>22916666.67</OtherTaxBase>
<VATRate>12</VATRate>
<VAT>2750000</VAT>
```

Contoh yang lebih mirip kondisi nyata, mengambil data dari tabel customer, invoice, dan detail invoice, ada di [examples/from-database.php](examples/from-database.php). Setiap aplikasi menamai kolomnya sendiri-sendiri, jadi bagian yang perlu kamu sesuaikan hanya pemetaan kolom itu.

## Data pembeli

Coretax minta isian yang berbeda tergantung jenis identitas pembeli. Pembeli tanpa NPWP, misalnya, harus diisi 16 angka nol di kolom NPWP dan `000000` di NITKU. Supaya tidak perlu hafal aturannya, ada beberapa cara membuat pembeli:

```php
Buyer::npwp('0012345678901000', $nama, $alamat);            // punya NPWP, NITKU otomatis NPWP + 000000 (pusat)
Buyer::npwp('0012345678901000', $nama, $alamat, $nitku);    // punya NPWP, NITKU cabang
Buyer::nik('3174061502560010', $nama, $alamat);             // pakai NIK
Buyer::passport('X1234567', 'SGP', $nama, $alamat);         // pakai paspor
Buyer::other('OtherID', $nama, $alamat);                    // dokumen lain
```

NPWP boleh ditulis dengan titik dan strip, atau masih 15 digit. Library akan mengubahnya ke 16 digit.

Di banyak aplikasi, kolom NPWP dan NIK customer boleh kosong, dan isinya macam-macam: kosong, `-`, atau nol semua. Untuk kasus seperti ini pakai `detect()`:

```php
Buyer::detect($row['npwp'], $row['nik'], $row['nama'], $row['alamat']);
```

Kalau NPWP-nya terlihat asli, itu yang dipakai. Kalau tidak, dicoba NIK. Kalau dua-duanya tidak ada, pembeli dianggap "Other ID".

## Harga termasuk PPN dan diskon

```php
Faktur::make($tanggal, $pembeli)
    ->priceIncludesVat()                                     // harga 1.110.000 sudah termasuk PPN -> DPP 1.000.000
    ->add(Item::goods('Barang', 100, 1110000)
        ->discountPercent(10, 5, 2.5))                       // disc1, disc2, disc3 bertingkat
    ->add(Item::service('Jasa Pasang', 1, 500000)
        ->discount(50000));                                  // diskon nominal
```

Diskon persen dihitung bertingkat seperti di kebanyakan aplikasi penjualan: diskon kedua dihitung dari sisa setelah diskon pertama, dan seterusnya. Nilai kosong atau `null` dilewati, jadi kolom `disc2` dan `disc3` dari database bisa langsung dimasukkan.

## Kalau aplikasimu sudah menghitung PPN sendiri

Mungkin angka DPP dan PPN sudah tersimpan di database dan kamu ingin angka itu yang masuk ke XML:

```php
Item::goods('Barang', 1, 35550)->amounts($dpp, $dppNilaiLain, $ppn);
```

Angka itu tetap dicek. Selisih satu rupiah hanya dianggap peringatan, karena biasanya itu cuma perbedaan cara pembulatan. Selisih yang lebih besar untuk kode transaksi 01, 04, dan 09 dianggap error, karena untuk kode-kode itu DJP mewajibkan PPN persis tarif × DPP Nilai Lain.

## Kode transaksi lain

```php
Faktur::make($tanggal, $pembeli)
    ->trxCode('07')
    ->addInfo('TD.00502')                                    // keterangan tambahan
    ->facilityStamp('TD.01102')                              // cap fasilitas
    ->customDoc('00004092893220241216000270', '122024');     // dokumen pendukung + periode MMYYYY

Faktur::make($tanggal, $pembeli)->trxCode('01');             // DPP Nilai Lain sama dengan DPP
Faktur::make($tanggal, $pembeli)->otherTaxBaseRatio(11, 12); // atur rasio DPP Nilai Lain sendiri
Item::goods(...)->stlgRate(20);                              // ada PPnBM 20%
```

## Pengecekan sebelum file dibuat

`toString()`, `save()`, dan `download()` tidak akan membuat file kalau masih ada error. Yang keluar adalah `ValidationException` berisi semua masalahnya sekaligus, bukan satu per satu:

```
[error] faktur #1 BuyerTin: NPWP pembeli '0000000000000000' tidak valid. Kalau pembeli tidak punya NPWP, pakai Buyer::nik(), Buyer::other() atau Buyer::detect()
[error] faktur #23 BuyerIDTKU: NITKU pembeli harus 22 digit, didapat '000000000'
```

Nama field-nya sama dengan nama elemen di XML, jadi mudah dicocokkan dengan pesan dari Coretax. Pesannya berbahasa Indonesia supaya bisa langsung ditampilkan ke bagian pajak.

Kalau hanya ingin melihat daftar masalah tanpa membuat file, misalnya untuk halaman review sebelum ekspor, panggil `validate()`. Hasilnya error dan peringatan dalam bentuk objek `Issue`.

Yang dicek, sesuai sheet *Keterangan* di template Excel DJP v1.6.1:

- NPWP 16 digit dan NITKU 22 digit, untuk penjual dan pembeli ber-NPWP
- NIK 16 digit untuk pembeli dengan NIK, kode negara 3 huruf untuk paspor
- Kode transaksi 01 sampai 10, dan untuk 07/08 keterangan tambahan serta cap fasilitas wajib diisi
- Kode satuan harus ada di daftar referensi (UM.0001 sampai UM.0039), kode barang 6 digit
- Diskon tidak boleh lebih besar dari harga × jumlah
- Kalau angka diisi sendiri: DPP, DPP Nilai Lain, PPN, dan PPnBM harus cocok satu sama lain

Untuk tanggal, yang diterima hanya `Y-m-d`, `Y-m-d H:i:s` (langsung dari kolom DATETIME), dan `d/m/Y`. Format lain ditolak, begitu juga tanggal yang tidak ada seperti `31/02/2025`. Ini sengaja dibuat ketat. Tanggal `03/04/2025` yang terbaca 4 Maret padahal maksudnya 3 April bisa membuat faktur masuk ke masa pajak yang salah.

## Soal pembulatan

Semua perhitungan memakai bilangan bulat dalam satuan sen, bukan float, supaya tidak ada selisih aneh di belakang koma. Aturan bawaannya mengikuti template DJP: maksimal 2 angka desimal, pembulatan biasa (0,5 ke atas), dan PPN serta PPnBM dalam rupiah bulat.

Kalau aplikasimu selama ini membulatkan dengan cara bankir (`PHP_ROUND_HALF_EVEN`, misalnya 3.910,5 jadi 3.910) dan kamu ingin hasilnya tetap sama, ada opsinya:

```php
CoretaxXml::make($npwp)->rounding(Rounding::HALF_EVEN);
```

## Seberapa yakin hasilnya diterima Coretax

Saya tidak punya akses langsung ke Coretax, jadi saya tidak bisa mencoba upload setiap kombinasi kode transaksi. Pengujiannya saya lakukan dengan dua cara:

- Struktur XML-nya saya samakan dengan hasil aplikasi *Converter e-Faktur Coretax* v1.6 dari DJP, termasuk urutan elemen, cara menulis elemen kosong, BOM di awal file, dan nama elemen `BuyerAdress` (memang begitu ejaannya di format DJP).
- Saya memakai file hasil converter dari aplikasi penjualan yang sudah rutin di-upload ke Coretax tanpa keluhan: 42 faktur dengan 212 baris barang. Data yang sama saya hitung ulang lewat library ini, dan 210 dari 212 baris hasilnya sama persis sampai sen. Dua sisanya adalah PPN yang di aplikasi asal dibulatkan dengan cara bankir, dan library menandainya sebagai peringatan selisih satu rupiah. Dari file yang sama, library juga menemukan 3 faktur yang pembelinya dikirim sebagai pemilik NPWP padahal NPWP-nya nol semua.

Ini bukan aplikasi resmi DJP. Kalau ada file dari library ini yang ditolak Coretax, tolong buka issue dan sertakan pesan error-nya beserta contoh faktur yang datanya sudah disamarkan. Laporan seperti itu yang paling membantu memperbaiki library ini.

## Lisensi

MIT
