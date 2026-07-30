from app.core.states import ENTRY_ALLOWED, StateMachine, SystemState


def test_kill_switch_blocks_entries():
    sm = StateMachine(SystemState.READY)
    assert sm.entries_allowed() is True
    sm.transition(SystemState.EMERGENCY_STOP, reason="kill_switch")
    assert sm.entries_allowed() is False
    assert SystemState.EMERGENCY_STOP not in ENTRY_ALLOWED


def test_pause_and_resume():
    sm = StateMachine(SystemState.READY)
    sm.transition(SystemState.PAUSED, reason="operator_pause")
    assert not sm.entries_allowed()
    sm.transition(SystemState.READY, reason="operator_resume")
    assert sm.entries_allowed()


def test_risk_lock_blocks_and_history_recorded():
    sm = StateMachine(SystemState.MONITORING)
    sm.transition(SystemState.RISK_LOCKED, reason="daily_loss_limit", correlation_id="c1")
    assert not sm.entries_allowed()
    last = sm.history[-1]
    assert last.prev == SystemState.MONITORING
    assert last.nxt == SystemState.RISK_LOCKED
    assert last.reason == "daily_loss_limit"
    assert last.correlation_id == "c1"


def test_every_transition_is_logged():
    sm = StateMachine(SystemState.OFFLINE)
    sm.transition(SystemState.STARTING, "boot")
    sm.transition(SystemState.PAPER_MODE, "env=paper")
    sm.transition(SystemState.READY, "ready")
    assert len(sm.history) == 3
