from app.core.security import (
    AdminAuth,
    AuthConfig,
    authenticate_webhook,
    compute_hmac,
    verify_body_secret,
)


def _cfg(**over):
    base = dict(webhook_secret="s3cr3t", url_token="tok123", hmac_required=False)
    base.update(over)
    return AuthConfig(**base)


def test_body_secret_ok():
    cfg = _cfg()
    ok, reason = authenticate_webhook(
        cfg=cfg, url_token="tok123", raw_body=b"{}",
        payload_secret="s3cr3t", signature_header=None, client_ip=None,
    )
    assert ok and reason is None


def test_bad_secret_rejected():
    ok, reason = authenticate_webhook(
        cfg=_cfg(), url_token="tok123", raw_body=b"{}",
        payload_secret="wrong", signature_header=None, client_ip=None,
    )
    assert not ok and reason == "bad_secret"


def test_bad_url_token_rejected():
    ok, reason = authenticate_webhook(
        cfg=_cfg(), url_token="nope", raw_body=b"{}",
        payload_secret="s3cr3t", signature_header=None, client_ip=None,
    )
    assert not ok and reason == "bad_url_token"


def test_ip_allowlist_enforced():
    cfg = _cfg(ip_allowlist=("1.2.3.4",))
    ok, _ = authenticate_webhook(
        cfg=cfg, url_token="tok123", raw_body=b"{}",
        payload_secret="s3cr3t", signature_header=None, client_ip="9.9.9.9",
    )
    assert not ok


def test_hmac_mode():
    cfg = _cfg(hmac_required=True)
    body = b'{"hello":"world"}'
    sig = "sha256=" + compute_hmac(body, "s3cr3t")
    ok, reason = authenticate_webhook(
        cfg=cfg, url_token="tok123", raw_body=body,
        payload_secret=None, signature_header=sig, client_ip=None,
    )
    assert ok and reason is None
    # tampered body fails
    bad, reason = authenticate_webhook(
        cfg=cfg, url_token="tok123", raw_body=b'{"hello":"tampered"}',
        payload_secret=None, signature_header=sig, client_ip=None,
    )
    assert not bad and reason == "bad_hmac"


def test_empty_secret_never_matches():
    assert verify_body_secret("", "") is False
    assert verify_body_secret(None, "x") is False


def test_admin_lockout_after_failures():
    auth = AdminAuth(token="admintok", max_failures=3, lockout_seconds=300)
    for _ in range(3):
        ok, _ = auth.check("wrong")
        assert not ok
    # now locked out even with the correct token
    ok, reason = auth.check("admintok")
    assert not ok and reason == "locked_out"
