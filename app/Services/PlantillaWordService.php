<?php

namespace App\Services;

use App\Models\TramitePago;
use DOMDocument;
use DOMXPath;

class PlantillaWordService
{
    private TramitePago $tramitePago;

    public function __construct(TramitePago $tramitePago)
    {
        $this->tramitePago = $tramitePago->load([
            'contrato.proveedor',
            'documentosSoporte' => fn ($q) => $q->orderBy('id'),
            'documentosExpediente' => fn ($q) => $q->orderBy('id'),
        ]);
    }

    public function saveToFile(string $path): void
    {
        $templatePath = public_path('Formatos/GF-FO-36-Plantilla.docx');

        if (!file_exists($templatePath)) {
            throw new \RuntimeException('No se encontró el template GF-FO-36-Plantilla.docx');
        }

        copy($templatePath, $path);

        $xml = $this->loadDocumentXml($path);
        $this->replacePlaceholders($xml);
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

    private function replacePlaceholders(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $t = $this->tramitePago;
        $c = $t->contrato;
        $p = $c->proveedor;

        $map = [
            'fecha_tramite' => $t->fecha_tramite?->format('d/m/Y') ?? '',
            'numero_pago' => (string) $t->numero_pago,
            'valor_pago_solicitado' => '$' . number_format($t->valor_pago_solicitado, 0, ',', '.'),

            'numcontrato' => $c->numcontrato ?? '',
            'nombre_proveedor' => $p->nombre ?? '',
            'nit_proveedor' => $p->nit ?? '',
            'sape_acreedor' => $c->sape_acreedor ?? '',
            'acreedor_contrato' => $c->sape_acreedor ?? '',
            'objeto_contrato' => $c->objetocontrato ?? '',
            'orden_erp_sap' => $c->orden_erp_sap ?? '',
            'expediente_orfeo' => $c->expediente_orfeo ?? '',
            'registro_presupuestal' => $t->registro_presupuestal ?? '',
            'valor_inicial_contrato' => '$' . number_format($t->valor_inicial_contrato, 2, ',', '.'),
            'valor_adiciones' => '+ $' . number_format($t->valor_adiciones, 2, ',', '.'),
            'valor_reducciones' => '- $' . number_format($t->valor_reducciones, 2, ',', '.'),
            'valor_total_contrato' => '$' . number_format($t->valor_total_contrato, 2, ',', '.'),
            'contrato_interadministrativo' => $t->contrato_interadministrativo ?? 'N/A',
            'fecha_legalizacion' => $t->fecha_legalizacion?->format('d/m/Y') ?? '',
            'fecha_finalizacion' => $t->fecha_finalizacion?->format('d/m/Y') ?? '',
            'porcentaje_ejecucion' => $t->porcentaje_ejecucion . '%',
            'novedades_contrato' => $t->novedades_contrato ?? '',

            'poliza_cumplimiento_numero' => $t->poliza_cumplimiento_numero ?? 'N/A',
            'poliza_cumplimiento_valor' => $t->poliza_cumplimiento_valor ? '$' . number_format($t->poliza_cumplimiento_valor, 0, ',', '.') : 'N/A',
            'poliza_cumplimiento_inicio' => $t->poliza_cumplimiento_inicio ? \Carbon\Carbon::parse($t->poliza_cumplimiento_inicio)->format('d/m/Y') : 'N/A',
            'poliza_cumplimiento_fin' => $t->poliza_cumplimiento_fin ? \Carbon\Carbon::parse($t->poliza_cumplimiento_fin)->format('d/m/Y') : 'N/A',
            'poliza_rc_numero' => $t->poliza_rc_numero ?? 'N/A',
            'poliza_rc_valor' => $t->poliza_rc_valor ? '$' . number_format($t->poliza_rc_valor, 0, ',', '.') : 'N/A',
            'poliza_rc_inicio' => $t->poliza_rc_inicio ? \Carbon\Carbon::parse($t->poliza_rc_inicio)->format('d/m/Y') : 'N/A',
            'poliza_rc_fin' => $t->poliza_rc_fin ? \Carbon\Carbon::parse($t->poliza_rc_fin)->format('d/m/Y') : 'N/A',

            'cuenta_bancaria_entidad' => $t->cuenta_bancaria_entidad ?? '',
            'numero_cuenta' => $t->numero_cuenta ?? '',
            'periodo_salud' => $t->periodo_salud ?? '',
            'periodo_pension' => $t->periodo_pension ?? '',
            'numero_planilla' => $t->numero_planilla_ss ?? '',
            'ibc' => $t->ibc ?? '',

            'aprobacion_secop_siif' => $this->getAprobacionText(),
            'cargar_rit_secop' => $t->cargar_rit_secop ? 'SI' : 'NO',
            'cargar_rut_secop' => $t->cargar_rut_secop ? 'SI' : 'NO',

            'responsable_tramite' => $t->responsable_tramite ?? '',
            'cargo_responsable' => $t->cargo_responsable ?? '',
            'validacion_gestor' => $t->validacion_gestor ?? '',
            'cargo_gestor' => $t->cargo_gestor ?? '',
            'vb_directivo' => $t->vb_directivo ?? '',
            'cargo_directivo' => $t->cargo_directivo ?? '',
        ];

        // Documentos Soporte
        $soporte = $t->documentosSoporte->values();
        for ($i = 0; $i < 6; $i++) {
            $doc = $soporte->get($i);
            $num = $i + 1;
            $map["doc_soporte_nombre_{$num}"] = $doc?->nombre_documento ?? '';
            $map["doc_soporte_fecha_{$num}"] = $doc?->fecha?->format('d/m/Y') ?? '';
            $map["doc_soporte_valor_{$num}"] = $doc?->valor ? '$' . number_format($doc->valor, 0, ',', '.') : '';
            $map["doc_soporte_folio_{$num}"] = $doc?->folio ? (string) $doc->folio : '';
        }

        // Documentos Expediente
        $expediente = $t->documentosExpediente->values();
        for ($i = 0; $i < 12; $i++) {
            $doc = $expediente->get($i);
            $num = $i + 1;
            $map["doc_exp_reposa_{$num}"] = $doc?->reposa_expediente ? 'X' : '';
            $map["doc_exp_fecha_{$num}"] = $doc?->fecha?->format('d/m/Y') ?? '';
            $map["doc_exp_folio_{$num}"] = $doc?->folio ? (string) $doc->folio : '';
        }

        // Consecutivo informe para "Informe de supervisión"
        $map['doc_exp_informe_consecutivo'] = ($c->cansecu_infor + 1);

        $textNodeList = $xpath->query('//w:t');
        $textNodes = [];
        foreach ($textNodeList as $node) {
            $textNodes[] = $node;
        }

        $buffer = '';
        $bufferedNodes = [];
        $i = 0;
        $n = count($textNodes);

        while ($i < $n) {
            $node = $textNodes[$i];
            $buffer .= $node->nodeValue;
            $bufferedNodes[] = $node;

            $foundPlaceholder = false;
            foreach ($map as $key => $value) {
                $placeholder = '{{' . $key . '}}';
                if (str_contains($buffer, $placeholder)) {
                    $buffer = str_replace($placeholder, $value, $buffer);
                    $foundPlaceholder = true;
                    break;
                }
            }

            if ($foundPlaceholder) {
                while (true) {
                    $foundAnother = false;
                    foreach ($map as $key => $value) {
                        $placeholder = '{{' . $key . '}}';
                        if (str_contains($buffer, $placeholder)) {
                            $buffer = str_replace($placeholder, $value, $buffer);
                            $foundAnother = true;
                            break;
                        }
                    }
                    if (!$foundAnother) break;
                }

                $bufferedNodes[0]->nodeValue = $buffer;
                for ($j = 1; $j < count($bufferedNodes); $j++) {
                    $bufferedNodes[$j]->nodeValue = '';
                }

                $lastOpen = strrpos($buffer, '{{');
                if ($lastOpen !== false) {
                    $buffer = substr($buffer, $lastOpen);
                    $bufferedNodes = [$bufferedNodes[0]];
                } else {
                    $buffer = '';
                    $bufferedNodes = [];
                }
            } elseif (!str_contains($buffer, '{{')) {
                $buffer = '';
                $bufferedNodes = [];
            }

            $i++;
        }

        if (!empty($bufferedNodes) && $buffer !== '') {
            $bufferedNodes[0]->nodeValue = $buffer;
            for ($j = 1; $j < count($bufferedNodes); $j++) {
                $bufferedNodes[$j]->nodeValue = '';
            }
        }
    }

    private function getAprobacionText(): string
    {
        $secop = $this->tramitePago->secop_ii
            ? 'SECOP II                                           X   Si     ☐   No'
            : 'SECOP II                                           ☐   Si     X   No';

        $siif = $this->tramitePago->siif
            ? 'SIIF  (cuando sea Electrónica)             X   Si     ☐   No'
            : 'SIIF  (cuando sea Electrónica)             ☐   Si     X   No';

        return $secop . $siif;
    }

    public function download(): \Symfony\Component\HttpFoundation\Response
    {
        $filename = 'GF-FO-36_Plantilla_' . $this->tramitePago->id . '_' . now()->format('Ymd_His') . '.docx';
        $tempPath = storage_path('app/' . $filename);

        $this->saveToFile($tempPath);

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }
}
