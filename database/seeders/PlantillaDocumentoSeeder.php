<?php

namespace Database\Seeders;

use App\Models\PlantillaDocumento;
use Illuminate\Database\Seeder;

class PlantillaDocumentoSeeder extends Seeder
{
    public function run(): void
    {
        $documentosSoporte = [
            'Control de pagos código GF-FO-35',
            'Factura, cuenta de cobro',
            'Acta de entrega y/o recibo a satisfacción CT-FO-01 (copia)',
            'Acta de corte parcial o final de obra (si aplica)',
            'Reporte/Listado Entradas de Almacén ERP-SAP MB51 (cuando aplique)',
            'Certificación Bancaria (cuando aplique)',
        ];

        $documentosExpediente = [
            'Control de pagos código GF-FO-35',
            'Factura, cuenta de cobro',
            'Acta de entrega y/o recibo a satisfacción CT-FO-01 (original)',
            'Acta de corte parcial o final de obra PA-FO-86 (si aplica)',
            'Entradas de Almacén ERP-SAP (cuando aplique)',
            'Certificación Bancaria (cuando aplique)',
            'Certificación pago de seguridad social integral, aportes parafiscales y obligaciones laborales',
            'Planilla de seguridad social en estado pagada',
            'Informe de supervisión',
            'Informe de Actividades (si aplica)',
            'Certificación aplicación Ley 1819 de 2016 y parágrafo 2 art 383 Estatuto Tributario (cuando aplique)',
            'Certificación de asignación de Retiro (Cuando Aplique)',
        ];

        foreach ($documentosSoporte as $i => $nombre) {
            PlantillaDocumento::create([
                'tipo' => 'soporte',
                'nombre_documento' => $nombre,
                'orden' => $i + 1,
            ]);
        }

        foreach ($documentosExpediente as $i => $nombre) {
            PlantillaDocumento::create([
                'tipo' => 'expediente',
                'nombre_documento' => $nombre,
                'orden' => $i + 1,
            ]);
        }
    }
}
