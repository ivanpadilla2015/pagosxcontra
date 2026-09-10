<?php

namespace App\Services;

class XmlFacturaReader
{
    private const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    public function parse(string $xmlContent): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlContent);

        if ($xml === false) {
            $errores = libxml_get_errors();
            libxml_clear_errors();
            $mensaje = $errores[0]->message ?? 'Error desconocido';
            throw new \Exception("El archivo no es un XML válido: {$mensaje}");
        }

        $nombreRaiz = $xml->getName();

        if ($nombreRaiz === 'AttachedDocument') {
            return $this->parsearAttachedDocument($xml);
        }

        if (in_array($nombreRaiz, ['Invoice', 'CreditNote'])) {
            return $this->parsearDocumentoDirecto($xml);
        }

        throw new \Exception("Tipo de documento no soportado: {$nombreRaiz}");
    }

    private function parsearAttachedDocument(\SimpleXMLElement $doc): array
    {
        $NS_CAC = self::NS_CAC;
        $NS_CBC = self::NS_CBC;

        $cac = $doc->children($NS_CAC);
        if (!isset($cac->Attachment)) {
            throw new \Exception("El AttachedDocument no contiene un Attachment.");
        }

        $attCac = $cac->Attachment->children($NS_CAC);
        if (!isset($attCac->ExternalReference)) {
            throw new \Exception("El Attachment no contiene ExternalReference.");
        }

        $extCbc = $attCac->ExternalReference->children($NS_CBC);
        if (!isset($extCbc->Description)) {
            throw new \Exception("El ExternalReference no contiene Description (CDATA).");
        }

        $cdata = trim((string) $extCbc->Description);
        if (empty($cdata)) {
            throw new \Exception("El CDATA está vacío.");
        }

        return $this->parsearCdata($cdata);
    }

    private function parsearCdata(string $cdata): array
    {
        $tipo = null;
        if (preg_match('~<(Invoice|CreditNote)\s~', $cdata, $m)) {
            $tipo = $m[1];
        }

        if (!$tipo) {
            throw new \Exception("No se pudo detectar el tipo de documento en el CDATA.");
        }

        $numero = $this->regex($cdata, '~<cbc:ID[^>]*>(.*?)</cbc:ID~');
        if (empty($numero)) {
            throw new \Exception("No se encontró el número de {$tipo} (ID).");
        }

        $fecha = $this->regex($cdata, '~<cbc:IssueDate[^>]*>(.*?)</cbc:IssueDate~');
        if (empty($fecha)) {
            throw new \Exception("No se encontró la fecha de emisión (IssueDate).");
        }

        $lineas = $this->extraerLineasRegex($cdata, $tipo);
        if (empty($lineas)) {
            throw new \Exception("No se encontraron líneas de productos.");
        }

        return [
            'numero' => trim($numero),
            'fecha'  => $fecha,
            'lineas' => $lineas,
        ];
    }

    private function extraerLineasRegex(string $cdata, string $tipo): array
    {
        $lineas = [];
        $tagLinea = $tipo === 'CreditNote' ? 'CreditNoteLine' : 'InvoiceLine';
        $tagCantidad = $tipo === 'CreditNote' ? 'CreditedQuantity' : 'InvoicedQuantity';

        // Los tags en el CDATA usan prefijos de namespace (cac:CreditNoteLine)
        $pattern = '~<(?:\w+:)?' . $tagLinea . '>(.*?)</(?:\w+:)?' . $tagLinea . '~s';
        if (!preg_match_all($pattern, $cdata, $matches)) {
            return [];
        }

        foreach ($matches[1] as $bloque) {
            $nombre = $this->regex($bloque, '~<cbc:Description[^>]*>(.*?)</cbc:Description~');
            if (empty($nombre)) {
                $nombre = $this->regex($bloque, '~<cbc:Name[^>]*>(.*?)</cbc:Name~');
            }
            if (empty($nombre)) continue;

            $cantidad = $this->regex($bloque, '~<(?:\w+:)?' . $tagCantidad . '[^>]*>([\d.]+)~');
            $cantidad = max(1, (int) round((float) ($cantidad ?: 1)));

            $valorUnitario = $this->regex($bloque, '~<cbc:PriceAmount[^>]*>([\d.]+)~');
            $valorUnitario = (float) ($valorUnitario ?: 0);

            $porcentajeIva = 19.0;
            if (preg_match('~<cbc:Percent[^>]*>([\d.]+)~', $bloque, $ivaMatch)) {
                $porcentajeIva = (float) $ivaMatch[1];
            } elseif (preg_match('~<cbc:TaxableAmount[^>]*>([\d.]+)~', $bloque, $baseMatch) &&
                      preg_match('~<cbc:TaxAmount[^>]*>([\d.]+)~', $bloque, $taxMatch)) {
                $base = (float) $baseMatch[1];
                $imp = (float) $taxMatch[1];
                if ($base > 0) {
                    $porcentajeIva = round(($imp / $base) * 100, 2);
                }
            }

            $lineas[] = [
                'nombre'         => trim($nombre),
                'cantidad'       => $cantidad,
                'valor_unitario' => $valorUnitario,
                'porcentaje_iva' => $porcentajeIva,
            ];
        }

        return $lineas;
    }

    private function parsearDocumentoDirecto(\SimpleXMLElement $xml): array
    {
        $NS_CBC = self::NS_CBC;
        $NS_CAC = self::NS_CAC;

        $numero = $this->campo($xml, 'ID');
        $fecha = $this->campo($xml, 'IssueDate');

        if (empty($numero)) throw new \Exception("No se encontró el número de factura (ID).");
        if (empty($fecha)) throw new \Exception("No se encontró la fecha de emisión (IssueDate).");

        $lineas = [];
        $nsCac = $xml->children($NS_CAC);
        $lineasXml = $nsCac->InvoiceLine ?? $nsCac->CreditNoteLine ?? [];

        foreach ($lineasXml as $lineaXml) {
            $nsItem = $lineaXml->children($NS_CAC);
            if (!isset($nsItem->Item)) continue;
            $nsCbcItem = $nsItem->Item->children($NS_CBC);

            $nombre = null;
            if (isset($nsCbcItem->Description)) $nombre = trim((string) $nsCbcItem->Description);
            elseif (isset($nsCbcItem->Name)) $nombre = trim((string) $nsCbcItem->Name);
            if (!$nombre) continue;

            $nsCbc = $lineaXml->children($NS_CBC);
            $cantidad = isset($nsCbc->InvoicedQuantity)
                ? (float) $nsCbc->InvoicedQuantity
                : (isset($nsCbc->CreditedQuantity) ? (float) $nsCbc->CreditedQuantity : 1);

            $valorUnitario = 0;
            if (isset($nsItem->Price)) {
                $nsPrice = $nsItem->Price->children($NS_CBC);
                $valorUnitario = isset($nsPrice->PriceAmount) ? (float) $nsPrice->PriceAmount : 0;
            }

            $porcentajeIva = 19.0;
            if (isset($nsItem->TaxTotal) && isset($nsItem->TaxTotal->TaxSubtotal)) {
                $nsTax = $nsItem->TaxTotal->TaxSubtotal->children($NS_CBC);
                if (isset($nsTax->Percent)) {
                    $porcentajeIva = (float) $nsTax->Percent;
                } elseif (isset($nsTax->TaxableAmount) && isset($nsTax->TaxAmount)) {
                    $base = (float) $nsTax->TaxableAmount;
                    $imp = (float) $nsTax->TaxAmount;
                    if ($base > 0) $porcentajeIva = round(($imp / $base) * 100, 2);
                }
            }

            $lineas[] = [
                'nombre'         => $nombre,
                'cantidad'       => max(1, (int) round($cantidad)),
                'valor_unitario' => $valorUnitario,
                'porcentaje_iva' => $porcentajeIva,
            ];
        }

        if (empty($lineas)) throw new \Exception("No se encontraron líneas de productos.");

        return [
            'numero' => trim($numero),
            'fecha'  => $fecha,
            'lineas' => $lineas,
        ];
    }

    private function campo(\SimpleXMLElement $xml, string $campo): ?string
    {
        $cbc = $xml->children(self::NS_CBC);
        if (isset($cbc->{$campo})) return trim((string) $cbc->{$campo});
        if (isset($xml->{$campo})) return trim((string) $xml->{$campo});
        return null;
    }

    private function regex(string $text, string $pattern): ?string
    {
        return preg_match($pattern, $text, $m) ? trim($m[1]) : null;
    }
}
