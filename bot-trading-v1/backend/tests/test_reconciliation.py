from app.brokers.base import BrokerPosition
from app.schemas.domain import Position, Side
from app.services.reconciliation import reconcile


def _bp(sym):
    return BrokerPosition(position_id="b1", symbol=sym, side=Side.BUY, size=0.1,
                          entry_price=2400, stop_loss=2396, take_profit=2408)


def _lp(sym):
    return Position(symbol=sym, side=Side.BUY, size=0.1, entry_price=2400,
                    stop_loss=2396, open_risk_percent=0.4)


def test_clean_when_matched():
    r = reconcile([_lp("XAUUSD")], [_bp("XAUUSD")])
    assert r.clean is True
    assert r.matched == ["XAUUSD"]


def test_position_at_broker_not_local():
    r = reconcile([], [_bp("NAS100")])
    assert not r.clean
    assert r.at_broker_not_local[0].symbol == "NAS100"
    assert "NAS100" in r.divergent_symbols()


def test_position_local_not_at_broker():
    r = reconcile([_lp("US30")], [])
    assert not r.clean
    assert r.local_not_at_broker[0].symbol == "US30"


def test_mixed_divergence():
    r = reconcile([_lp("XAUUSD"), _lp("US30")], [_bp("XAUUSD"), _bp("SPX500")])
    assert r.matched == ["XAUUSD"]
    assert r.divergent_symbols() == {"US30", "SPX500"}
