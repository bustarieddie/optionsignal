from app.services.analytics import compute_metrics
from app.services.backtest_import import parse_generic_csv, parse_tradingview_csv

TV_CSV = """Trade #,Type,Date/Time,Price,Profit
1,Entry Long,2026-07-01 09:30:00,2400,
1,Exit Long,2026-07-01 11:00:00,2412,120
2,Entry Short,2026-07-02 14:00:00,2410,
2,Exit Short,2026-07-02 15:30:00,2415,-50
"""

GENERIC_CSV = """symbol,side,pnl,r,session
XAUUSD,BUY,120,2.0,new_york
XAUUSD,SELL,-50,-1.0,london
"""


def test_tradingview_pairs_entry_exit():
    trades = parse_tradingview_csv(TV_CSV, symbol="XAUUSD")
    assert len(trades) == 2
    assert trades[0].side == "BUY" and trades[0].pnl == 120.0
    assert trades[1].side == "SELL" and trades[1].pnl == -50.0
    # R defaults to sign when no explicit R column
    assert trades[0].r_multiple == 1.0 and trades[1].r_multiple == -1.0


def test_tradingview_feeds_analytics():
    m = compute_metrics(parse_tradingview_csv(TV_CSV, symbol="XAUUSD"))
    assert m.trades == 2 and m.wins == 1 and m.losses == 1
    assert m.net_profit == 70.0


def test_generic_csv_uses_explicit_r():
    trades = parse_generic_csv(GENERIC_CSV)
    assert len(trades) == 2
    assert trades[0].r_multiple == 2.0
    assert trades[0].session == "new_york"
    m = compute_metrics(trades)
    assert round(m.expectancy, 3) == round(0.5 * 2.0 + 0.5 * -1.0, 3)  # 0.5


def test_currency_symbols_and_commas_parsed():
    csv = "symbol,side,pnl\nUS30,BUY,\"1,250.50\"\nUS30,SELL,-$300\n"
    trades = parse_generic_csv(csv)
    assert trades[0].pnl == 1250.5
    assert trades[1].pnl == -300.0
