/**
 * OptionSignal Pro — live quote feed.
 * Loads the latest cached quotes, then updates them in real time via the
 * Reverb public "quotes" channel. Degrades gracefully when Reverb/feed is off.
 *
 * Markup hooks (per ticker):
 *   <span data-quote-price="NVDA">—</span>
 *   <span data-quote-change="NVDA"></span>
 */
import './osp-echo';

function applyQuotes(quotes) {
  if (!quotes) return;
  Object.keys(quotes).forEach(ticker => {
    const q = quotes[ticker];
    if (!q || q.price == null) return;

    document.querySelectorAll(`[data-quote-price="${ticker}"]`).forEach(el => {
      el.textContent = '$' + Number(q.price).toFixed(2);
    });

    document.querySelectorAll(`[data-quote-change="${ticker}"]`).forEach(el => {
      if (q.change_pct == null) {
        el.textContent = '';
        return;
      }
      const up = q.change_pct >= 0;
      el.textContent = (up ? '▲ ' : '▼ ') + Math.abs(q.change_pct).toFixed(2) + '%';
      el.classList.remove('text-success', 'text-danger');
      el.classList.add(up ? 'text-success' : 'text-danger');
    });
  });
}

document.addEventListener('DOMContentLoaded', function () {
  // Initial snapshot.
  fetch('/quotes', { headers: { Accept: 'application/json' } })
    .then(r => (r.ok ? r.json() : {}))
    .then(applyQuotes)
    .catch(() => {});

  // Live updates.
  if (typeof window.Echo !== 'undefined') {
    try {
      window.Echo.channel('quotes').listen('.quote.updated', e => applyQuotes(e.quotes));
    } catch (err) {
      console.warn('[OptionSignal] Live quotes unavailable:', err);
    }
  }
});
