<?php
require __DIR__ . '/vendor/autoload.php';

use Smalot\PdfParser\Parser;

$parser = new Parser();

$pdfFile = __DIR__ . '/sample.pdf'; // place a small PDF in your project folder

if (!file_exists($pdfFile)) {
    die("PDF file not found!");
}

try {
    $pdf = $parser->parseFile($pdfFile);
    $text = $pdf->getText();
    echo "<pre>$text</pre>";
} catch (Exception $e) {
    echo "Error parsing PDF: " . $e->getMessage();
}



