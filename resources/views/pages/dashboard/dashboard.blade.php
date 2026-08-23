<x-app-layout>
    <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">

        <!-- Header -->
        <div class="sm:flex sm:justify-between sm:items-center mb-8">
            <div class="mb-4 sm:mb-0">
                <h1 class="text-2xl md:text-3xl text-gray-800 dark:text-gray-100 font-bold">Dashboard</h1>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

            <!-- Total Contratos -->
            <div class="flex flex-col border border-gray-200 dark:border-gray-700/60 rounded-sm bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
                <div class="px-5 pt-5 pb-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Contratos</span>
                        <svg class="w-8 h-8 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                    </div>
                    <div class="text-3xl font-bold text-gray-800 dark:text-gray-100">{{ number_format($data['totalContratos']) }}</div>
                </div>
                <div class="px-5 pb-4">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total de contratos activos</span>
                </div>
            </div>

            <!-- Total Facturado -->
            <div class="flex flex-col border border-gray-200 dark:border-gray-700/60 rounded-sm bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
                <div class="px-5 pt-5 pb-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Facturado</span>
                        <svg class="w-8 h-8 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                        </svg>
                    </div>
                    <div class="text-3xl font-bold text-gray-800 dark:text-gray-100">$ {{ number_format($data['totalFacturado'], 0, ',', '.') }}</div>
                </div>
                <div class="px-5 pb-4">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total facturado (sin anuladas)</span>
                </div>
            </div>

            <!-- Total Pagado -->
            <div class="flex flex-col border border-gray-200 dark:border-gray-700/60 rounded-sm bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
                <div class="px-5 pt-5 pb-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pagado</span>
                        <svg class="w-8 h-8 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3" />
                        </svg>
                    </div>
                    <div class="text-3xl font-bold text-gray-800 dark:text-gray-100">$ {{ number_format($data['totalPagado'], 0, ',', '.') }}</div>
                </div>
                <div class="px-5 pb-4">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Pagos confirmados (cerrados)</span>
                </div>
            </div>

            <!-- Saldo Disponible -->
            <div class="flex flex-col border border-gray-200 dark:border-gray-700/60 rounded-sm bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
                <div class="px-5 pt-5 pb-3">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Saldo Disponible</span>
                        <svg class="w-8 h-8 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                        </svg>
                    </div>
                    <div class="text-3xl font-bold text-gray-800 dark:text-gray-100">$ {{ number_format($data['saldoDisponible'], 0, ',', '.') }}</div>
                </div>
                <div class="px-5 pb-4">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Saldo por ejecutar en contratos</span>
                </div>
            </div>

        </div>

        <!-- Charts Row 1: Facturación vs Pagos mensual -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
            <div class="col-span-full">
                <div class="border border-gray-200 dark:border-gray-700/60 rounded-sm bg-white dark:bg-gray-800 shadow-sm">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/60">
                        <h2 class="font-semibold text-gray-800 dark:text-gray-100">Facturación vs Pagos Mensual</h2>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Últimos 12 meses</span>
                    </div>
                    <div class="px-5 py-5">
                        <canvas id="chart-facturacion-pagos" class="w-full h-80"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row 2: Doughnuts -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">

            <!-- Facturas por estado -->
            <div class="lg:col-span-4">
                <div class="border border-gray-200 dark:border-gray-700/60 rounded-sm bg-white dark:bg-gray-800 shadow-sm">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/60">
                        <h2 class="font-semibold text-gray-800 dark:text-gray-100">Facturas por Estado</h2>
                    </div>
                    <div class="px-5 py-5 flex justify-center">
                        <canvas id="chart-facturas-estado" class="w-64 h-64"></canvas>
                    </div>
                </div>
            </div>

            <!-- Pagos por estado -->
            <div class="lg:col-span-4">
                <div class="border border-gray-200 dark:border-gray-700/60 rounded-sm bg-white dark:bg-gray-800 shadow-sm">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/60">
                        <h2 class="font-semibold text-gray-800 dark:text-gray-100">Pagos por Estado</h2>
                    </div>
                    <div class="px-5 py-5 flex justify-center">
                        <canvas id="chart-pagos-estado" class="w-64 h-64"></canvas>
                    </div>
                </div>
            </div>

            <!-- Ejecución presupuestal -->
            <div class="lg:col-span-4">
                <div class="border border-gray-200 dark:border-gray-700/60 rounded-sm bg-white dark:bg-gray-800 shadow-sm">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/60">
                        <h2 class="font-semibold text-gray-800 dark:text-gray-100">Ejecución Presupuestal</h2>
                    </div>
                    <div class="px-5 py-5 flex justify-center">
                        <canvas id="chart-presupuesto" class="w-64 h-64"></canvas>
                    </div>
                </div>
            </div>

        </div>

        <!-- Charts Row 3: Retenciones -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-6">
            <div class="col-span-full">
                <div class="border border-gray-200 dark:border-gray-700/60 rounded-sm bg-white dark:bg-gray-800 shadow-sm">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700/60">
                        <h2 class="font-semibold text-gray-800 dark:text-gray-100">Retenciones Aplicadas</h2>
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total retenido por tipo</span>
                    </div>
                    <div class="px-5 py-5">
                        <canvas id="chart-retenciones" class="w-full h-64"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard data for JS -->
        <script>
            window.dashboardData = @json($data);
        </script>

    </div>
</x-app-layout>
