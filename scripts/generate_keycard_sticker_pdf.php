<?php

require __DIR__.'/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$root = dirname(__DIR__);
$outputDir = $root.'/public/printables';
$logoPath = 'C:/Users/Carmela Hernandez/Downloads/Southern_Leyte_State_University.png';

if (! is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

$logoSrc = 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath));

$html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { size: A4 portrait; margin: 12mm; }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: DejaVu Sans, Arial, sans-serif;
        color: #101a4d;
        background: #ffffff;
    }
    .title {
        margin: 0 0 6mm;
        font-size: 9pt;
        color: #64748b;
    }
    .sheet {
        width: 186mm;
        margin: 0 auto;
    }
    .row {
        display: table;
        border-spacing: 8mm 0;
        margin-left: -8mm;
    }
    .cell {
        display: table-cell;
        vertical-align: top;
    }
    .label {
        margin: 0 0 2mm 1mm;
        font-size: 7pt;
        font-weight: bold;
        color: #64748b;
        letter-spacing: .4pt;
        text-transform: uppercase;
    }
    .card {
        position: relative;
        width: 85.6mm;
        height: 54mm;
        overflow: hidden;
        border: .25mm dashed #94a3b8;
        border-radius: 3mm;
        background: #ffffff;
    }
    .card::before {
        content: "";
        position: absolute;
        inset: 1.2mm;
        border: .15mm solid #dbeafe;
        border-radius: 2.4mm;
    }
    .corner-top {
        position: absolute;
        top: 0;
        right: 0;
        width: 27mm;
        height: 4mm;
        background: #111a56;
        border-bottom-left-radius: 7mm;
    }
    .corner-top::before {
        content: "";
        position: absolute;
        left: -4mm;
        top: 0;
        width: 6mm;
        height: 4mm;
        background: #6aa9dc;
        transform: skewX(28deg);
    }
    .bottom-band {
        position: absolute;
        left: 0;
        bottom: 0;
        width: 100%;
        height: 4.8mm;
        background: #111a56;
    }
    .bottom-band::after {
        content: "";
        position: absolute;
        right: 0;
        top: 0;
        width: 27mm;
        height: 4.8mm;
        background: #6aa9dc;
        border-top-left-radius: 7mm;
    }
    .bottom-band::before {
        content: "";
        position: absolute;
        right: 24mm;
        top: 0;
        width: 2.4mm;
        height: 5.5mm;
        background: #ffffff;
        transform: skewX(-32deg);
        z-index: 2;
    }
    .logo {
        position: absolute;
        left: 7mm;
        top: 6mm;
        width: 11.5mm;
        height: 11.5mm;
        object-fit: contain;
    }
    .divider {
        position: absolute;
        left: 22mm;
        top: 6mm;
        width: .35mm;
        height: 16mm;
        background: #6aa9dc;
    }
    .brand {
        position: absolute;
        left: 25mm;
        top: 6.5mm;
        font-size: 12.5pt;
        line-height: 1.05;
        font-weight: 800;
        letter-spacing: -.1pt;
        color: #111a56;
    }
    .subbrand {
        position: absolute;
        left: 25.2mm;
        top: 16mm;
        font-size: 8.5pt;
        color: #4f97cf;
    }
    .guard-name {
        position: absolute;
        left: 7mm;
        top: 25mm;
        font-size: 19.5pt;
        line-height: 1;
        font-weight: 800;
        color: #111a56;
    }
    .guard-no {
        position: absolute;
        left: 7mm;
        top: 35.3mm;
        min-width: 21mm;
        height: 7mm;
        padding: 1.3mm 3mm 0;
        border-radius: 2mm;
        background: #6aa9dc;
        color: #ffffff;
        font-size: 11pt;
        line-height: 1;
        font-weight: 800;
        text-align: center;
    }
    .role {
        position: absolute;
        left: 7mm;
        top: 43.6mm;
        font-size: 8.8pt;
        font-weight: 700;
        color: #111a56;
    }
    .rfid-icon {
        position: absolute;
        right: 8mm;
        bottom: 11mm;
        width: 13mm;
        height: 13mm;
        color: #111a56;
    }
    .uid-pill {
        position: absolute;
        left: 24mm;
        top: 12mm;
        width: 38mm;
        height: 8mm;
        border: .35mm solid #6aa9dc;
        border-radius: 2mm;
        text-align: center;
        font-size: 9.8pt;
        line-height: 7.1mm;
        font-weight: 800;
        color: #111a56;
    }
    .back-line {
        position: absolute;
        left: 15mm;
        top: 26mm;
        width: 56mm;
        height: .2mm;
        background: #6aa9dc;
    }
    .back-title {
        position: absolute;
        left: 0;
        right: 0;
        top: 29mm;
        text-align: center;
        font-size: 12pt;
        font-weight: 800;
        color: #111a56;
    }
    .back-copy {
        position: absolute;
        left: 10mm;
        right: 10mm;
        top: 38mm;
        text-align: center;
        font-size: 7.8pt;
        line-height: 1.55;
        color: #111827;
    }
    .print-note {
        margin-top: 8mm;
        width: 178mm;
        border: .2mm solid #dbeafe;
        padding: 3mm 4mm;
        font-size: 8pt;
        line-height: 1.55;
        color: #334155;
    }
</style>
</head>
<body>
<div class="sheet">
    <p class="title">Print at 100% scale. Each sticker/card area is 85.6 mm x 54 mm.</p>
    <div class="row">
        <div class="cell">
            <p class="label">Front Sticker</p>
            <div class="card">
                <div class="corner-top"></div>
                <div class="bottom-band"></div>
                <img class="logo" src="LOGO_SRC" alt="SLSU logo">
                <div class="divider"></div>
                <div class="brand">SLSU Bontoc Patrol</div>
                <div class="subbrand">RFID Security Guard Card</div>
                <div class="guard-name">Cherry Ann Himo</div>
                <div class="guard-no">SG-001</div>
                <div class="role">Security Guard</div>
                <svg class="rfid-icon" viewBox="0 0 64 64" aria-hidden="true">
                    <circle cx="17" cy="32" r="5" fill="currentColor"/>
                    <path d="M28 22c5 5 5 15 0 20" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
                    <path d="M39 14c10 10 10 26 0 36" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
                    <path d="M50 7c14 15 14 35 0 50" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
                </svg>
            </div>
        </div>
        <div class="cell">
            <p class="label">Back Sticker</p>
            <div class="card">
                <div class="corner-top"></div>
                <div class="bottom-band"></div>
                <div class="uid-pill">RFID UID: F33C8D37</div>
                <div class="back-line"></div>
                <div class="back-title">Device Access Card</div>
                <div class="back-copy">
                    Property of SLSU Bontoc Security Office<br>
                    If found, return to the Security Office.
                </div>
                <svg class="rfid-icon" style="right:36mm; bottom:9mm; width:11mm; height:11mm;" viewBox="0 0 64 64" aria-hidden="true">
                    <circle cx="17" cy="32" r="5" fill="currentColor"/>
                    <path d="M28 22c5 5 5 15 0 20" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
                    <path d="M39 14c10 10 10 26 0 36" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round"/>
                </svg>
            </div>
        </div>
    </div>
    <div class="print-note">
        Recommended sticker material: thin matte sticker paper or thin non-metallic vinyl. Avoid foil, metallic ink, thick laminate, or aluminum-backed sticker because it may weaken RFID scanning.
    </div>
</div>
</body>
</html>
HTML;

$html = str_replace('LOGO_SRC', $logoSrc, $html);
$htmlPath = $outputDir.'/slsu-keycard-sticker-sg-001.html';
$pdfPath = $outputDir.'/slsu-keycard-sticker-sg-001.pdf';

file_put_contents($htmlPath, $html);

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('isFontSubsettingEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

file_put_contents($pdfPath, $dompdf->output());

echo "HTML: {$htmlPath}\n";
echo "PDF: {$pdfPath}\n";
echo 'PDF size: '.filesize($pdfPath)." bytes\n";
