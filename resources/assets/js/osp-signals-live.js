/**
 * OptionSignal Pro — live Signals table.
 * Subscribes to the authenticated user's private channel and prepends new
 * signals to the table the instant the webhook is processed (no refresh).
 * Degrades gracefully when Reverb isn't running.
 */
import './osp-echo';

const GRADE_COLOR = { 'A+': 'success', A: 'primary', B: 'info', C: 'warning', ignore: 'secondary' };

function sideBadge(type) {
  if (type === 'buy_call') return 'bg-label-success';
  if (type === 'buy_put') return 'bg-label-danger';
  return 'bg-label-warning';
}

function buildRow(s) {
  const side = (s.signal_type || '').replace('buy_', '').toUpperCase();
  const grade = s.grade || 'ignore';
  const price = s.price != null ? '$' + Number(s.price).toFixed(2) : '—';
  const tr = document.createElement('tr');
  tr.className = 'table-active';
  tr.innerHTML =
    `<td class="fw-medium">${s.ticker}</td>` +
    `<td><span class="badge ${sideBadge(s.signal_type)}">${side}</span></td>` +
    `<td>${s.timeframe || ''}</td>` +
    `<td><span class="badge bg-label-${GRADE_COLOR[grade] || 'secondary'}">${grade}</span></td>` +
    `<td>${s.total_score ?? ''}</td>` +
    `<td>${price}</td>` +
    `<td><small>${s.strategy || '—'}</small></td>` +
    `<td><span class="d-block text-nowrap">${s.occurred_at || 'just now'}</span><small class="text-body-secondary">just now</small></td>` +
    `<td><a href="${s.url || '#'}" class="btn btn-sm btn-text-primary">View</a></td>`;
  setTimeout(() => tr.classList.remove('table-active'), 4000);
  return tr;
}

document.addEventListener('DOMContentLoaded', function () {
  const body = document.getElementById('osp-signals-body');
  const meta = document.querySelector('meta[name="osp-user-id"]');
  const userId = meta ? meta.getAttribute('content') : null;

  if (!body || !userId || typeof window.Echo === 'undefined') return;

  try {
    window.Echo.private(`App.Models.User.${userId}`).listen('.signal.new', signal => {
      const empty = body.querySelector('[data-empty-row]');
      if (empty) empty.remove();
      body.prepend(buildRow(signal));
      if (window.Notyf) {
        const side = (signal.signal_type || '').replace('buy_', '').toUpperCase();
        new window.Notyf({ duration: 5000, position: { x: 'right', y: 'top' } })
          .success(`${signal.ticker} ${side} · ${signal.grade}`);
      }
    });
  } catch (e) {
    console.warn('[OptionSignal] Live signals unavailable:', e);
  }
});
