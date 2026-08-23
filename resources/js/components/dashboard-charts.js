import { Chart } from 'chart.js';

export default function initDashboardCharts() {
  const data = window.dashboardData;
  if (!data) return;

  const isDark = document.documentElement.classList.contains('dark');
  const textColor = isDark ? '#94a3b8' : '#64748b';
  const gridColor = isDark ? 'rgba(148, 163, 184, 0.08)' : 'rgba(100, 116, 139, 0.08)';

  // ── 1. Facturación vs Pagos Mensual (Bar) ──
  new Chart(document.getElementById('chart-facturacion-pagos'), {
    type: 'bar',
    data: {
      labels: data.mesesLabels,
      datasets: [
        {
          label: 'Facturado',
          data: data.facturacionMensual,
          backgroundColor: 'rgba(59, 130, 246, 0.7)',
          borderRadius: 3,
        },
        {
          label: 'Pagado',
          data: data.pagosMensual,
          backgroundColor: 'rgba(124, 58, 237, 0.7)',
          borderRadius: 3,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'top', labels: { color: textColor, usePointStyle: true, pointStyle: 'circle', padding: 16 } },
        tooltip: { callbacks: { label: (ctx) => `${ctx.dataset.label}: $${ctx.raw.toLocaleString('es-CO')}` } },
      },
      scales: {
        x: { ticks: { color: textColor, maxRotation: 45 }, grid: { color: gridColor } },
        y: { ticks: { color: textColor, callback: (v) => '$' + (v / 1000000).toFixed(0) + 'M' }, grid: { color: gridColor } },
      },
    },
  });

  // ── 2. Facturas por Estado (Doughnut) ──
  const facturasEstadoLabels = Object.keys(data.facturasPorEstado);
  const facturasEstadoValues = Object.values(data.facturasPorEstado);
  new Chart(document.getElementById('chart-facturas-estado'), {
    type: 'doughnut',
    data: {
      labels: facturasEstadoLabels,
      datasets: [{
        data: facturasEstadoValues,
        backgroundColor: ['#eab308', '#3b82f6', '#22c55e', '#ef4444'],
        borderWidth: 0,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      cutout: '65%',
      plugins: {
        legend: { position: 'bottom', labels: { color: textColor, usePointStyle: true, pointStyle: 'circle', padding: 12 } },
        tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${ctx.raw}` } },
      },
    },
  });

  // ── 3. Pagos por Estado (Doughnut) ──
  const pagosEstadoLabels = Object.keys(data.pagosPorEstado);
  const pagosEstadoValues = Object.values(data.pagosPorEstado);
  new Chart(document.getElementById('chart-pagos-estado'), {
    type: 'doughnut',
    data: {
      labels: pagosEstadoLabels,
      datasets: [{
        data: pagosEstadoValues,
        backgroundColor: ['#eab308', '#22c55e', '#ef4444'],
        borderWidth: 0,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      cutout: '65%',
      plugins: {
        legend: { position: 'bottom', labels: { color: textColor, usePointStyle: true, pointStyle: 'circle', padding: 12 } },
        tooltip: { callbacks: { label: (ctx) => `${ctx.label}: ${ctx.raw}` } },
      },
    },
  });

  // ── 4. Ejecución Presupuestal (Doughnut) ──
  new Chart(document.getElementById('chart-presupuesto'), {
    type: 'doughnut',
    data: {
      labels: ['Ejecutado', 'Saldo Disponible'],
      datasets: [{
        data: [data.presupuesto.ejecutado, data.presupuesto.saldo],
        backgroundColor: ['#8b5cf6', '#d1d5db'],
        borderWidth: 0,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      cutout: '65%',
      plugins: {
        legend: { position: 'bottom', labels: { color: textColor, usePointStyle: true, pointStyle: 'circle', padding: 12 } },
        tooltip: { callbacks: { label: (ctx) => `${ctx.label}: $${ctx.raw.toLocaleString('es-CO')}` } },
      },
    },
  });

  // ── 5. Retenciones (Bar horizontal) ──
  const retLabels = Object.keys(data.retenciones);
  const retValues = Object.values(data.retenciones);
  new Chart(document.getElementById('chart-retenciones'), {
    type: 'bar',
    data: {
      labels: retLabels,
      datasets: [{
        label: 'Retenido',
        data: retValues,
        backgroundColor: [
          'rgba(239, 68, 68, 0.7)',
          'rgba(249, 115, 22, 0.7)',
          'rgba(234, 179, 8, 0.7)',
          'rgba(34, 197, 94, 0.7)',
          'rgba(59, 130, 246, 0.7)',
          'rgba(124, 58, 237, 0.7)',
        ],
        borderRadius: 3,
      }],
    },
    options: {
      indexAxis: 'y',
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (ctx) => `$${ctx.raw.toLocaleString('es-CO')}` } },
      },
      scales: {
        x: { ticks: { color: textColor, callback: (v) => '$' + (v / 1000000).toFixed(0) + 'M' }, grid: { color: gridColor } },
        y: { ticks: { color: textColor }, grid: { display: false } },
      },
    },
  });
}
