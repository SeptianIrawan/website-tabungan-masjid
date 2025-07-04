<?php
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Create new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set document properties
$spreadsheet->getProperties()
    ->setCreator("Admin System")
    ->setLastModifiedBy("Admin System")
    ->setTitle("Template Import Pengguna")
    ->setSubject("Template untuk import data pengguna")
    ->setDescription("Template untuk mengimport data pengguna ke sistem")
    ->setKeywords("import pengguna template excel")
    ->setCategory("Template");

// Set header row with style
$sheet->setCellValue('A1', 'Nama Lengkap')
      ->setCellValue('B1', 'Role')
      ->setCellValue('C1', 'Email')
      ->setCellValue('D1', 'Password (Opsional)');

// Make header bold
$sheet->getStyle('A1:D1')->getFont()->setBold(true);

// Set column widths
$sheet->getColumnDimension('A')->setWidth(25);
$sheet->getColumnDimension('B')->setWidth(15);
$sheet->getColumnDimension('C')->setWidth(30);
$sheet->getColumnDimension('D')->setWidth(25);

// Add sample data
$sampleData = [
    ['Admin Utama', 'admin', 'admin@example.com', 'admin123'],
    ['User Biasa', 'user', 'user@example.com', ''],
    ['John Doe', 'admin', 'john.doe@mail.com', 'johndoe123'],
    ['Jane Smith', 'user', 'jane.smith@mail.com', '']
];

$sheet->fromArray($sampleData, null, 'A2');

// Add data validation for Role column
$validation = $sheet->getCell('B2')->getDataValidation();
$validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
$validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
$validation->setAllowBlank(false);
$validation->setShowInputMessage(true);
$validation->setShowErrorMessage(true);
$validation->setShowDropDown(true);
$validation->setErrorTitle('Input error');
$validation->setError('Value is not in list');
$validation->setPromptTitle('Pilih Role');
$validation->setPrompt('Pilih antara admin atau user');
$validation->setFormula1('"admin,user"');

// Apply validation to entire column (except header)
for ($row = 2; $row <= 100; $row++) {
    $sheet->getCell('B'.$row)->setDataValidation(clone $validation);
}

// Add instructions as comments
$sheet->getComment('A1')->getText()->createTextRun("Wajib diisi\nContoh: John Doe");
$sheet->getComment('B1')->getText()->createTextRun("Wajib diisi\nPilih: admin atau user");
$sheet->getComment('C1')->getText()->createTextRun("Wajib diisi\nFormat email valid\nHarus unik");
$sheet->getComment('D1')->getText()->createTextRun("Opsional\nMinimal 6 karakter jika diisi\nJika kosong akan dibuatkan password default");

// Set headers to download the file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="template_import_pengguna.xlsx"');
header('Cache-Control: max-age=0');

// Create writer and output to browser
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;