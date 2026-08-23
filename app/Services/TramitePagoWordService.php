<?php

namespace App\Services;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use App\Models\TramitePago;
use DOMDocument;
use DOMXPath;

class TramitePagoWordService
{
    private TramitePago $tramitePago;

    public function __construct(TramitePago $tramitePago)
    {
        $this->tramitePago = $tramitePago->load(['contrato.proveedor', 'documentos']);
    }

    public function generate(): PhpWord
    {
        $templatePath = public_path('Formatos/GF-FO-36 tramite-Pago-Anticipo-Parcial-Total-V4.docx');

        return IOFactory::load($templatePath);
    }

    public function saveToFile(string $path): void
    {
        $templatePath = public_path('Formatos/GF-FO-36 tramite-Pago-Anticipo-Parcial-Total-V4.docx');
        copy($templatePath, $path);

        $xml = $this->loadDocumentXml($path);
        $this->replaceValuesInAllTables($xml);
        $this->saveDocumentXml($path, $xml);
    }

    private function loadDocumentXml(string $docxPath): DOMDocument
    {
        $zip = new \ZipArchive();
        if ($zip->open($docxPath) !== true) {
            throw new \RuntimeException('No se pudo abrir el documento Word.');
        }

        $xmlContent = $zip->getFromName('word/document.xml');
        $zip->close();

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;
        $dom->loadXML($xmlContent, LIBXML_NOENT | LIBXML_NONET);

        return $dom;
    }

    private function saveDocumentXml(string $docxPath, DOMDocument $dom): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($docxPath) === true) {
            $zip->deleteName('word/document.xml');
            $zip->addFromString('word/document.xml', $dom->saveXML());
            $zip->close();
        }
    }

    private function normalizeText(string $text): string
    {
        return mb_strtolower(strtr($text, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Ü' => 'U', 'ü' => 'u', 'Ñ' => 'N', 'ñ' => 'n',
        ]));
    }

    private function containsNormalized(string $haystack, string $needle): bool
    {
        return str_contains($this->normalizeText($haystack), $this->normalizeText($needle));
    }

    private function replaceValuesInAllTables(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $tables = $xpath->query('//w:tbl');
        $t = $this->tramitePago;
        $c = $t->contrato;
        $p = $c->proveedor;

        foreach ($tables as $table) {
            $tableText = $this->getTableText($xpath, $table);
            $normTable = $this->normalizeText($tableText);

            if (str_contains($normTable, 'fecha tramite de pago') || str_contains($normTable, 'valor de pago solicitado')) {
                $this->replaceByLabel($xpath, $table, 'Fecha tramite de pago', $t->fecha_tramite?->format('d/m/Y') ?? '');
                $this->replaceByLabel($xpath, $table, 'Numero de Pago', (string) $t->numero_pago);
                $this->replaceByLabel($xpath, $table, 'Valor de pago solicitado', '$' . number_format($t->valor_pago_solicitado, 0, ',', '.'));
            }

            if (str_contains($normTable, 'contrato y/u orden de compra')) {
                $this->replaceByLabel($xpath, $table, 'Contrato y/u Orden de Compra', 'No.' . $c->numcontrato);
                $this->replaceByLabel($xpath, $table, 'Nombre o Razon Social del Contratista', $p->nombre ?? '');
                $this->replaceByLabel($xpath, $table, 'Identificacion', 'NIT o C.C: ' . ($p->nit ?? '') . ' / SAP (acreedor): ' . ($c->sape_acreedor ?? ''));
                $this->replaceByLabel($xpath, $table, 'Objeto del Contrato', $c->objetocontrato ?? '');
                $this->replaceByLabel($xpath, $table, 'Orden o Pedido ERP SAP', 'No. ' . $c->orden_erp_sap);
                $this->replaceByLabel($xpath, $table, 'Expediente Orfeo', 'No. ' . $c->expediente_orfeo);
                $this->replaceByLabel($xpath, $table, 'Registro Presupuestal', 'No. ' . ($t->registro_presupuestal ?? ''));
                $this->replaceByLabel($xpath, $table, 'Vigencia', $t->vigencia_actual ? 'x VIGENCIA ACTUAL ☐ REZAGO' : '☐ VIGENCIA ACTUAL x REZAGO');
                $this->replaceByLabel($xpath, $table, 'Valor Inicial del Contrato', '$' . number_format($t->valor_inicial_contrato, 2, ',', '.'));
                $this->replaceByLabel($xpath, $table, 'Valor Adiciones', '+ $' . number_format($t->valor_adiciones, 2, ',', '.'));
                $this->replaceByLabel($xpath, $table, 'Valor Reducciones', '- $' . number_format($t->valor_reducciones, 2, ',', '.'));
                $this->replaceByLabelInCell($xpath, $table, 'Valor Total del Contrato', 1, '$' . number_format($t->valor_total_contrato, 2, ',', '.'));
                $this->replaceByLabel($xpath, $table, 'No. Contrato interadministrativo', $t->contrato_interadministrativo ?? 'N/A');
                $this->replaceByLabel($xpath, $table, 'Fecha de legalizacion del contrato', $t->fecha_legalizacion?->format('d/m/Y') ?? '');
                $this->replaceByLabel($xpath, $table, 'Fecha de finalizacion del contrato', $t->fecha_finalizacion?->format('d/m/Y') ?? '');
                $this->replaceByLabel($xpath, $table, 'Porcentaje de ejecucion del contrato', $t->porcentaje_ejecucion . '%');

                $mods = [];
                if ($t->mod_adicion) { $mods[] = 'x Adición'; }
                if ($t->mod_modificacion) { $mods[] = 'x Modificación'; }
                if ($t->mod_suspension) { $mods[] = 'x Suspensión'; }
                if ($t->mod_prorroga) { $mods[] = 'x Prórroga'; }
                if ($t->mod_cesion) { $mods[] = 'x Cesión'; }
                $this->replaceByLabel($xpath, $table, 'Modificaciones del contrato', implode(' / ', $mods) ?: 'Ninguna');
                $this->replaceByLabel($xpath, $table, 'Novedades del contrato', $t->novedades_contrato ?? '');

                $this->replacePolizaRow($xpath, $table, 'Póliza de cumplimiento',
                    $t->poliza_cumplimiento_numero,
                    $t->poliza_cumplimiento_valor,
                    $t->poliza_cumplimiento_inicio,
                    $t->poliza_cumplimiento_fin
                );
                $this->replacePolizaRow($xpath, $table, 'Póliza de responsabilidad civil extracontractual',
                    $t->poliza_rc_numero,
                    $t->poliza_rc_valor,
                    $t->poliza_rc_inicio,
                    $t->poliza_rc_fin
                );
            }

            if (str_contains($normTable, 'cuenta bancaria') && str_contains($normTable, 'entidad')) {
                $this->replaceByLabel($xpath, $table, 'Cuenta Bancaria', $t->cuenta_bancaria_entidad ?? '');
                $this->replaceExactInTable($xpath, $table, '69238463408', $t->numero_cuenta ?? '');

                $regimenMap = [
                    'iva'    => 'x   RESPONSABLE DE IVA            ☐   NO RESPONSABLE DE IVA                     ☐   RÉGIMEN SIMPLE DE TRIBUTACIÓN',
                    'no_iva' => '☐   RESPONSABLE DE IVA            x   NO RESPONSABLE DE IVA                     ☐   RÉGIMEN SIMPLE DE TRIBUTACIÓN',
                    'simple' => '☐   RESPONSABLE DE IVA            ☐   NO RESPONSABLE DE IVA                     x   RÉGIMEN SIMPLE DE TRIBUTACIÓN',
                ];
                $this->replaceByLabel($xpath, $table, 'Regimen Tributario', $regimenMap[$t->regimen_tributario] ?? '');

                $tipoFactMap = [
                    'electronica'  => 'x ELECTRÓNICA ☐ CUENTA DE COBRO',
                    'cuenta_cobro' => '☐ ELECTRÓNICA x CUENTA DE COBRO',
                ];
                $this->replaceByLabel($xpath, $table, 'Tipo de Facturacion', $tipoFactMap[$t->tipo_facturacion] ?? '');

                $this->replaceByLabel($xpath, $table, 'CUMPLIMIENTO OBLIGACIONES LEY 50', $t->cumple_ley_50 ? 'CUMPLE' : 'NO CUMPLE');
                $this->replaceByLabel($xpath, $table, 'Planilla pagada seguridad social integral', $t->planilla_seguridad_social ? 'X' : '');
                $this->replaceByLabel($xpath, $table, 'Certificacion de pago seguridad social', $t->certificacion_seguridad_social ? 'X' : '');
                $this->replaceByLabel($xpath, $table, 'Certificacion de obligaciones laborales', $t->certificacion_obligaciones_laborales ? 'X' : '');
                $this->replaceExactInTable($xpath, $table, '7986627655', $t->numero_planilla_ss ?? '');
                $this->replaceByLabel($xpath, $table, 'Periodo Salud', $t->periodo_salud ?? '');
                $this->replaceByLabel($xpath, $table, 'Periodo Pension', $t->periodo_pension ?? '');
            }

            if (str_contains($normTable, 'aprobacion de facturas') || str_contains($normTable, 'secop ii')) {
                if (str_contains($normTable, 'aprobacion de facturas')) {
                    $this->replaceApprovalCheckboxes($xpath, $table, $t->secop_ii, $t->siif);
                }
            }

            if (str_contains($normTable, 'documentos soporte') || (str_contains($normTable, 'valor') && str_contains($normTable, 'folio') && str_contains($normTable, 'item'))) {
                $this->replaceDocumentRows($xpath, $table, $t->documentosSoporte, true);
            }

            if (str_contains($normTable, 'certificacion de documentos') || str_contains($normTable, 'reposa en el expediente contractual') || str_contains($normTable, 'expediente del contrato')) {
                $this->replaceDocumentRows($xpath, $table, $t->documentosExpediente, false);
            }

            if (str_contains($normTable, 'cargar rit en secop') || str_contains($normTable, 'solo primer pago')) {
                $this->replaceByLabel($xpath, $table, 'Cargar RIT en SECOP II', $t->cargar_rit_secop ? 'SI' : 'NO');
                $this->replaceByLabel($xpath, $table, 'Cargar RUT en SECOP II', $t->cargar_rut_secop ? 'SI' : 'NO');
            }

            if (str_contains($normTable, 'responsable del tramite') || str_contains($normTable, 'validacion cargue')) {
                $this->replaceFirmas($xpath, $table, $t);
            }
        }
    }

    private function replaceApprovalCheckboxes(DOMXPath $xpath, $table, bool $secop, bool $siif): void
    {
        $rows = $xpath->query('.//w:tr', $table);
        foreach ($rows as $row) {
            $cells = $xpath->query('.//w:tc', $row);
            if ($cells->length < 2) {
                continue;
            }

            $cellText = $this->getCellText($xpath, $cells->item(1));
            $normCell = $this->normalizeText($cellText);

            if (str_contains($normCell, 'secop ii') || str_contains($normCell, 'siif')) {
                $secopPart = $secop ? 'SECOP II                                           X   Si     ☐   No' : 'SECOP II                                           ☐   Si     X   No';
                $siifPart = $siif ? 'SIIF  (cuando sea Electrónica)             X   Si     ☐   No' : 'SIIF  (cuando sea Electrónica)             ☐   Si     X   No';
                $this->setCellText($xpath, $cells->item(1), $secopPart . $siifPart);
            }
        }
    }

    private function getTableText(DOMXPath $xpath, $table): string
    {
        $texts = $xpath->query('.//w:t', $table);
        $result = '';
        foreach ($texts as $t) {
            $result .= $t->nodeValue . ' ';
        }
        return $result;
    }

    private function replaceByLabel(DOMXPath $xpath, $table, string $label, string $newValue): void
    {
        $rows = $xpath->query('.//w:tr', $table);

        foreach ($rows as $row) {
            $cells = $xpath->query('.//w:tc', $row);
            if ($cells->length < 2) {
                continue;
            }

            $cellCount = $cells->length;

            for ($i = 0; $i < $cellCount - 1; $i++) {
                $labelText = $this->getCellText($xpath, $cells->item($i));

                if ($this->containsNormalized($labelText, $label)) {
                    $this->setCellText($xpath, $cells->item($i + 1), $newValue);
                    return;
                }
            }
        }
    }

    private function replaceByLabelInCell(DOMXPath $xpath, $table, string $label, int $cellIndex, string $newValue): void
    {
        $rows = $xpath->query('.//w:tr', $table);

        foreach ($rows as $row) {
            $cells = $xpath->query('.//w:tc', $row);
            if ($cells->length <= $cellIndex + 1) {
                continue;
            }

            $labelText = $this->getCellText($xpath, $cells->item($cellIndex));

            if ($this->containsNormalized($labelText, $label)) {
                $this->setCellText($xpath, $cells->item($cellIndex + 1), $newValue);
                return;
            }
        }
    }

    private function replaceExactInTable(DOMXPath $xpath, $table, string $search, string $replace): void
    {
        $texts = $xpath->query('.//w:t', $table);
        foreach ($texts as $textNode) {
            if (trim($textNode->nodeValue) === $search) {
                $textNode->nodeValue = $replace;
            }
        }
    }

    private function replacePolizaRow(DOMXPath $xpath, $table, string $label, ?string $numero, ?float $valor, ?string $inicio, ?string $fin): void
    {
        $rows = $xpath->query('.//w:tr', $table);

        foreach ($rows as $row) {
            $cells = $xpath->query('.//w:tc', $row);
            if ($cells->length < 5) {
                continue;
            }

            $labelText = $this->getCellText($xpath, $cells->item(0));

            if ($this->containsNormalized($labelText, $label)) {
                $this->setCellText($xpath, $cells->item(1), $numero ?? 'N/A');
                $this->setCellText($xpath, $cells->item(2), $valor ? '$' . number_format($valor, 0, ',', '.') : 'N/A');
                $this->setCellText($xpath, $cells->item(3), $inicio ? \Carbon\Carbon::parse($inicio)->format('d/m/Y') : 'N/A');
                $this->setCellText($xpath, $cells->item(4), $fin ? \Carbon\Carbon::parse($fin)->format('d/m/Y') : 'N/A');
                return;
            }
        }
    }

    private function replaceFirmas(DOMXPath $xpath, $table, TramitePago $t): void
    {
        $rows = $xpath->query('.//w:tr', $table);
        $rowArray = [];
        foreach ($rows as $row) {
            $rowArray[] = $row;
        }

        // Secciones: Responsable, Gestor, Directivo
        // Cada sección tiene: R-header, R-nombre, R-cargo, R-firma
        $sections = [
            ['nombre' => $t->responsable_tramite, 'cargo' => $t->cargo_responsable],
            ['nombre' => $t->validacion_gestor, 'cargo' => $t->cargo_gestor],
            ['nombre' => $t->vb_directivo, 'cargo' => $t->cargo_directivo],
        ];

        $sectionIdx = 0;
        $skipNext = 0;

        foreach ($rowArray as $rIdx => $row) {
            if ($skipNext > 0) {
                $skipNext--;
                continue;
            }

            $cells = $xpath->query('.//w:tc', $row);
            if ($cells->length < 2) {
                continue;
            }

            $cell1Text = $this->getCellText($xpath, $cells->item(1));

            if ($cell1Text === 'Nombre' && $sectionIdx < count($sections)) {
                $this->setCellText($xpath, $cells->item(1), $sections[$sectionIdx]['nombre'] ?? 'Nombre');
                $skipNext = 1;

                $nextRow = $rowArray[$rIdx + 1] ?? null;
                if ($nextRow) {
                    $nextCells = $xpath->query('.//w:tc', $nextRow);
                    if ($nextCells->length >= 2) {
                        $this->setCellText($xpath, $nextCells->item(1), $sections[$sectionIdx]['cargo'] ?? 'Cargo');
                    }
                }

                $sectionIdx++;
            }
        }
    }

    private function replaceDocumentRows(DOMXPath $xpath, $table, $documentos, bool $isSoporte): void
    {
        $rows = $xpath->query('.//w:tr', $table);
        $docIndex = 0;

        foreach ($rows as $row) {
            $cells = $xpath->query('.//w:tc', $row);
            if ($cells->length < 3) {
                continue;
            }

            $firstCellText = trim($this->getCellText($xpath, $cells->item(0)));
            $normFirst = $this->normalizeText($firstCellText);

            if (str_contains($normFirst, 'item') || str_contains($normFirst, 'documento')) {
                continue;
            }

            $itemNumber = (int) filter_var($firstCellText, FILTER_SANITIZE_NUMBER_INT);

            if ($itemNumber > 0 && $docIndex < $documentos->count()) {
                $doc = $documentos[$docIndex];

                $this->setCellText($xpath, $cells->item(1), $doc->nombre_documento);

                if ($isSoporte) {
                    $this->setCellText($xpath, $cells->item(2), $doc->fecha?->format('d/m/Y') ?? 'N/A');
                    if ($cells->length > 3) {
                        $this->setCellText($xpath, $cells->item(3), $doc->valor ? '$' . number_format($doc->valor, 0, ',', '.') : 'N/A');
                    }
                    if ($cells->length > 4) {
                        $this->setCellText($xpath, $cells->item(4), $doc->folio ? (string) $doc->folio : '');
                    }
                } else {
                    $this->setCellText($xpath, $cells->item(2), $doc->reposa_expediente ? 'X' : '');
                    if ($cells->length > 3) {
                        $this->setCellText($xpath, $cells->item(3), $doc->fecha?->format('d/m/Y') ?? 'N/A');
                    }
                    if ($cells->length > 4) {
                        $this->setCellText($xpath, $cells->item(4), $doc->folio ? (string) $doc->folio : '');
                    }
                }

                $docIndex++;
            }
        }
    }

    private function getCellText(DOMXPath $xpath, $cell): string
    {
        $texts = $xpath->query('.//w:t', $cell);
        $result = '';
        foreach ($texts as $t) {
            $result .= $t->nodeValue;
        }
        return $result;
    }

    private function setCellText(DOMXPath $xpath, $cell, string $value): void
    {
        $paragraphs = $xpath->query('.//w:p', $cell);
        if ($paragraphs->length === 0) {
            return;
        }

        $firstParagraph = $paragraphs->item(0);

        // Clear all existing content in the first paragraph
        $runs = $xpath->query('.//w:r', $firstParagraph);
        foreach ($runs as $run) {
            $run->parentNode->removeChild($run);
        }

        // Create new run with the value
        $run = $firstParagraph->ownerDocument->createElement('w:r');
        $tNode = $run->ownerDocument->createElement('w:t');
        $tNode->setAttribute('xml:space', 'preserve');
        $tNode->nodeValue = $value;
        $run->appendChild($tNode);
        $firstParagraph->appendChild($run);

        // Remove all other paragraphs in the cell (they contain leftover text)
        for ($i = 1; $i < $paragraphs->length; $i++) {
            $paragraphs->item($i)->parentNode->removeChild($paragraphs->item($i));
        }
    }

    public function download(): \Symfony\Component\HttpFoundation\Response
    {
        $filename = 'GF-FO-36_Tramite_Pago_' . $this->tramitePago->id . '_' . now()->format('Ymd_His') . '.docx';
        $tempPath = storage_path('app/' . $filename);

        $this->saveToFile($tempPath);

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }
}
