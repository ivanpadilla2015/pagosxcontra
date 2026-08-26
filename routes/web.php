<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Controllers\DataFeedController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PdfpagosController;
use App\Models\TramitePago;
use App\Services\PlantillaWordService;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::redirect('/', 'login');

Route::middleware(['auth:sanctum', 'verified'])->group(function () {

    // Route for the getting the data feed
    Route::get('/json-data-feed', [DataFeedController::class, 'getDataFeed'])->name('json_data_feed');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/analytics', [DashboardController::class, 'analytics'])->name('analytics');
    Route::get('/dashboard/fintech', [DashboardController::class, 'fintech'])->name('fintech');

    
    Route::livewire('otros/directors', 'directors.director')->name('directors');
    Route::livewire('otros/presupuestos', 'presupuestos.presupuesto')->name('presupuestos');
    Route::livewire('otros/regionals', 'regionals.regional')->name('regionals');
    Route::livewire('proveedores/tipopers', 'tipopers.tipopers')->name('tipopers');
    Route::livewire('proveedores/regimen_tributario', 'regimen_tributario.regimen_tributario')->name('regimen_tributario');
    Route::livewire('proveedores/retenciones', 'retenciones.retenciones')->name('retenciones');
    Route::livewire('proveedores/tipocuentas', 'tipocuentas.tipocuentas')->name('tipocuentas');
    Route::livewire('proveedores/proveedors', 'proveedors.proveedors')->name('proveedors');
    Route::livewire('contratos/tipocontratos', 'tipocontratos.tipocontrato')->name('tipocontratos');
    Route::livewire('contratos/contrainters', 'contrainters.contrainter')->name('contrainters');
    Route::livewire('contratos/contratos', 'contratos.contrato')->name('contratos');
    Route::livewire('contratos/importrubrosusos', 'rubros.importrubrosusos')->name('importrubrosusos');
    Route::livewire('contratos/listadorubrosusos', 'rubros.listadorubrosusos')->name('listadorubrosusos');
    Route::livewire('contratos/productos', 'contratos.productos')->name('productos');
    Route::livewire('contratos/importar-productos', 'contratos.importar-productos')->name('importar.productos');
    Route::get('contratos/importar-productos/plantilla', [\App\Http\Controllers\ProductosController::class, 'plantillaExcel'])->name('importar.productos.plantilla');
    Route::livewire('contratos/asignar-productos', 'contratos.asignar-productos')->name('asignar.productos');
    Route::livewire('contratos/importar-asignacion', 'contratos.importar-asignacion')->name('importar.asignacion');
    Route::get('contratos/importar-asignacion/plantilla/{contratoId}/{rubroId}', [\App\Http\Controllers\ItemContratoController::class, 'plantillaExcel'])->name('importar.asignacion.plantilla');
    Route::livewire('contratos/facturacion/{id?}', 'contratos.facturacion')->name('facturacion');

    // Facturas - listado, edición y PDF
    Route::livewire('contratos/facturas', 'contratos.facturas-lista')->name('facturas');
    Route::livewire('contratos/facturas/{id}/editar', 'contratos.factura-editar')->name('facturacion.editar');
    Route::get('contratos/facturas/{id}/pdf', [\App\Http\Controllers\FacturaPdfController::class, 'show'])->name('facturas.pdf');

    // Facturar (nueva forma)
    Route::livewire('contratos/facturar/{id?}', 'contratos.facturar')->name('facturar');

    // Actas de Recibo
    Route::livewire('contratos/actas', 'contratos.actas')->name('actas');
    Route::livewire('contratos/actas/{id}', 'contratos.acta-editar')->name('actas.editar');
    Route::get('/actas/imprimir/{id}', [PdfpagosController::class, 'imprimirActas'])->name('actas.imprimir')->middleware('auth');

    // Catálogos auxiliares
    Route::livewire('otros/municipios', 'municipios.municipios')->name('municipios');
    Route::livewire('otros/dependencias', 'dependencias.dependencias')->name('dependencias');
    Route::livewire('otros/reteica-tarifas', 'tarifas.reteica-tarifas')->name('reteica.tarifas');
    Route::livewire('otros/estampilla-tarifas', 'tarifas.estampilla-tarifas')->name('estampilla.tarifas');
    Route::livewire('otros/retencion-tarifas', 'tarifas.retencion-tarifas')->name('retencion.tarifas');

    // Pagos
    Route::livewire('contratos/pagos', 'pagos.crearpagos')->name('pagos.lista');
    Route::livewire('contratos/pagos/crear', 'pagos.pago-crear')->name('pagos.crear');
    Route::livewire('contratos/pagos/{id}/editar', 'pagos.pago-editar')->name('pagos.editar');
    Route::livewire('pagos/imprimir', 'pagos.imprimepagos')->name('pagos.imprimir');
    Route::get('/pagos/imprimir/{id}', [ PdfpagosController::class, 'imprimepdfxuso'])->name('/imprexuso')->middleware('auth');
    
    // Informes
    Route::livewire('informes/obligacion', 'obligacion.obligacion')->name('informes.obligacion');
    Route::livewire('informes/importar-obligaciones', 'obligacion.importar-obligaciones')->name('importar.obligaciones');
    Route::get('informes/importar-obligaciones/plantilla', [\App\Http\Controllers\ObligacionesController::class, 'plantillaExcel'])->name('importar.obligaciones.plantilla');
    Route::livewire('informes/riesgos', 'riesgo.riesgo')->name('informes.riesgos');
    Route::livewire('informes/importar-riesgos', 'riesgo.importar-riesgos')->name('importar.riesgos');
    Route::get('informes/importar-riesgos/plantilla', [\App\Http\Controllers\RiesgosController::class, 'plantillaExcel'])->name('importar.riesgos.plantilla');
    Route::livewire('informes/informes', 'informes.informes')->name('informes.informes');
    Route::get('/informes/imprimir/{id}', [ PdfpagosController::class, 'imprimepdfinfo'])->name('/impreinfo')->middleware('auth');
    Route::get('/informes/imprimircomedor/{id}', [ PdfpagosController::class, 'imprimepdfinfocomedor'])->name('/impreinfocomedor')->middleware('auth');    
    
    // Reportes
    Route::livewire('reportes/facturacion', 'reportes.facturacion')->name('reportes.facturacion');

    // Administración
    Route::livewire('admin/roles', 'admin.roles')->middleware('permission:admin.manage-roles')->name('admin.roles');
    Route::livewire('admin/usuarios', 'admin.usuarios')->middleware('permission:admin.manage-roles')->name('admin.usuarios');
    Route::livewire('admin/backups', 'admin.backups')->middleware('permission:admin.manage-roles')->name('admin.backups');
    Route::get('admin/backups/download/{fileName}', function (string $fileName) {
        $backupName = config('backup.backup.name');
        $filePath = storage_path('app/' . $backupName . '/' . $fileName);

        if (!file_exists($filePath)) {
            abort(404);
        }

        return response()->download($filePath, $fileName);
    })->middleware('permission:admin.manage-roles')->name('admin.backups.download');

    Route::get('admin/backups/download-latest', function () {
        $backupName = config('backup.backup.name');
        $backupPath = storage_path('app/' . $backupName);

        $latestFile = collect(\Illuminate\Support\Facades\File::files($backupPath))
            ->filter(fn ($file) => $file->getExtension() === 'zip')
            ->sortByDesc('filename')
            ->first();

        if (!$latestFile) {
            abort(404, 'No hay backups disponibles.');
        }

        return response()->download($latestFile->getPathname(), $latestFile->getFilename());
    })->middleware('permission:admin.manage-roles')->name('admin.backups.download-latest');

    Route::get('admin/backups/descargar-fuente', function () {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "fuente_{$timestamp}.zip";
        $tempPath = storage_path('app/' . $filename);

        $zip = new \ZipArchive();
        if ($zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'No se pudo crear el archivo zip.');
        }

        $baseDir = base_path();
        $excludeDirs = ['vendor', 'node_modules', '.git', 'storage/framework', 'storage/logs', 'storage/app'];
        $excludeFiles = ['.env'];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            $relativePath = ltrim(str_replace($baseDir, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $relativePath = str_replace('\\', '/', $relativePath);

            $excluded = false;
            foreach ($excludeDirs as $dir) {
                if (str_starts_with($relativePath, $dir . '/') || $relativePath === $dir) {
                    $excluded = true;
                    break;
                }
            }
            foreach ($excludeFiles as $ef) {
                if ($relativePath === $ef) {
                    $excluded = true;
                    break;
                }
            }

            if (!$excluded && $file->isFile()) {
                $zip->addFile($file->getPathname(), $relativePath);
            }
        }

        $zip->close();

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    })->middleware('permission:admin.manage-roles')->name('admin.backups.descargar-fuente');

    Route::get('admin/backups/descargar-base-datos', function () {
        $phpBinary = PHP_BINARY;
        $artisanPath = base_path('artisan');
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "base_datos_{$timestamp}.sql";
        $tempPath = storage_path('app/' . $filename);

        $dbConfig = config('database.connections.mysql');
        $host = $dbConfig['host'];
        $port = $dbConfig['port'];
        $database = $dbConfig['database'];
        $username = $dbConfig['username'];
        $password = $dbConfig['password'];
        $mysqldumpPath = $dbConfig['dump']['dump_binary_path'] ?? '';

        $cmd = '"' . rtrim($mysqldumpPath, '/') . '/mysqldump.exe"';
        $cmd .= ' --host=' . $host;
        $cmd .= ' --port=' . $port;
        $cmd .= ' --user=' . $username;
        if ($password !== '') {
            $cmd .= ' --password=' . $password;
        }
        $cmd .= ' ' . $database;
        $cmd .= ' > "' . $tempPath . '" 2>&1';

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($tempPath) || filesize($tempPath) === 0) {
            @unlink($tempPath);
            abort(500, 'Error al generar el dump de la base de datos: ' . implode("\n", $output));
        }

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    })->middleware('permission:admin.manage-roles')->name('admin.backups.descargar-base-datos');

    // Registros
    Route::livewire('registros/registro', 'registros.registro')->name('registros');
    Route::livewire('registros/tiporegistros', 'registros.tiporegistros')->name('registros.tiporegistros');
    Route::livewire('registros/adicion', 'registros.adicion-registro')->name('registros.adicion');
    Route::livewire('registros/reduccion', 'registros.reduccion-registro')->name('registros.reduccion');
    Route::livewire('registros/traslados', 'registros.traslados')->name('registros.traslados');

    // Reportes
    Route::livewire('reportes/contratos', 'reportes.reporte-contratos')->name('reportes.contratos');
    Route::livewire('reportes/retenciones', 'reportes.retenciones')->name('reportes.retenciones');
    Route::get('reportes/retenciones/excel', [\App\Http\Controllers\ReporteRetencionesController::class, 'excel'])->name('reportes.retenciones.excel');
    Route::get('reportes/retenciones/pdf', [\App\Http\Controllers\ReporteRetencionesController::class, 'pdf'])->name('reportes.retenciones.pdf');

    // Reporte Pagos con Retenciones
    Route::livewire('reportes/pagos-retenciones', 'reportes.reporte-pagos-retenciones')->name('reportes.pagos.retenciones');
    Route::get('reportes/pagos-retenciones/excel', [\App\Http\Controllers\ReportePagosRetencionesController::class, 'excel'])->name('reportes.pagos.retenciones.excel');
    Route::get('reportes/pagos-retenciones/pdf', [\App\Http\Controllers\ReportePagosRetencionesController::class, 'pdf'])->name('reportes.pagos.retenciones.pdf');

    // Trámite de Pagos
    Route::livewire('tramite-pagos', 'tramite-pagos.⚡tramite-pago')->name('tramite-pagos');
    Route::livewire('tramite-pagos/plantilla-documentos', 'tramite-pagos.⚡plantilla-documentos')->name('tramite-pagos.plantilla');
    Route::get('tramites/pagos/{tramitePago}/download-plantilla', function (TramitePago $tramitePago) {
        $service = new PlantillaWordService($tramitePago);
        $filename = 'GF-FO-36_Plantilla_'.$tramitePago->id.'_'.now()->format('Ymd_His').'.docx';
        $tempPath = storage_path('app/'.$filename);
        $service->saveToFile($tempPath);

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    })->name('tramite-pagos.download-plantilla');

    Route::fallback(function() {
        return view('pages/utility/404');
    });    
});
