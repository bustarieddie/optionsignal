/**
 * OptionSignal Pro — dashboard live updates.
 * Subscribes to the authenticated user's private channel and surfaces new
 * graded signals as toasts. Degrades gracefully when Reverb isn't running.
 */
import './osp-echo';

document.addEventListener('DOMContentLoaded', function () {
  renderCharts();

  const meta = document.querySelector('meta[name="osp-user-id"]');
  const userId = meta ? meta.getAttribute('content') : null;

  if (!userId || typeof window.Echo === 'undefined') {
    return;
  }

  subscribeToSignals(userId);
});

/** Render the dashboard ApexCharts from the embedded JSON payload. */
function renderCharts() {
  const dataEl = document.getElementById('osp-chart-data');
  if (!dataEl || typeof ApexCharts === 'undefined') {
    return;
  }

  let data;
  try {
    data = JSON.parse(dataEl.textContent);
  } catch (e) {
    return;
  }

  // Equity curve
  const equityEl = document.getElementById('osp-equity-chart');
  if (equityEl && data.equity) {
    new ApexCharts(equityEl, {
      chart: { type: 'area', height: 300, toolbar: { show: false } },
      series: [{ name: 'Cumulative P/L', data: data.equity.series }],
      xaxis: { categories: data.equity.labels },
      stroke: { curve: 'smooth', width: 2 },
      dataLabels: { enabled: false },
      colors: ['#26a69a'],
      noData: { text: 'No closed trades yet' },
      yaxis: { labels: { formatter: v => '$' + Number(v).toFixed(0) } },
    }).render();
  }

  // Win / loss donut
  const wlEl = document.getElementById('osp-winloss-chart');
  if (wlEl && data.win_loss) {
    new ApexCharts(wlEl, {
      chart: { type: 'donut', height: 220 },
      series: [data.win_loss.wins, data.win_loss.losses],
      labels: ['Wins', 'Losses'],
      colors: ['#26a69a', '#ef5350'],
      legend: { position: 'bottom' },
      noData: { text: 'No closed trades yet' },
    }).render();
  }

  // Grade distribution bar
  const gradesEl = document.getElementById('osp-grades-chart');
  if (gradesEl && data.grades) {
    new ApexCharts(gradesEl, {
      chart: { type: 'bar', height: 220, toolbar: { show: false } },
      series: [{ name: 'Signals', data: data.grades.series }],
      xaxis: { categories: data.grades.labels },
      plotOptions: { bar: { borderRadius: 4, distributed: true } },
      colors: ['#28c76f', '#7367f0', '#00cfe8', '#ff9f43', '#82868b'],
      legend: { show: false },
      dataLabels: { enabled: false },
    }).render();
  }
}

/** Subscribe to the user's private channel for live signal toasts. */
function subscribeToSignals(userId) {
  if (!userId || typeof window.Echo === 'undefined') {
    return;
  }

  try {
    window.Echo.private(`App.Models.User.${userId}`).listen('.signal.new', signal => {
      const side = (signal.signal_type || '').replace('buy_', '').toUpperCase();
      const msg = `${signal.ticker} ${side} (${signal.timeframe}) — Grade ${signal.grade}, score ${signal.total_score}`;

      if (window.Notyf) {
        new window.Notyf({ duration: 6000, position: { x: 'right', y: 'top' } }).success(msg);
      } else {
        console.info('[OptionSignal] New signal:', msg);
      }
    });
  } catch (e) {
    console.warn('[OptionSignal] Live updates unavailable:', e);
  }
}
