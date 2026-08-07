#!/usr/bin/env python3
from __future__ import annotations

import importlib.util
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location("p21_control_plane_audit", ROOT / "tools" / "p21_control_plane_audit.py")
MODULE = importlib.util.module_from_spec(SPEC)
assert SPEC and SPEC.loader
SPEC.loader.exec_module(MODULE)


def write_workflow(root: Path, name: str, body: str) -> None:
    directory = root / ".github" / "workflows"
    directory.mkdir(parents=True, exist_ok=True)
    (directory / name).write_text(body, encoding="utf-8")


def assert_fail(body: str, expected: str) -> None:
    with tempfile.TemporaryDirectory() as td:
        root = Path(td)
        write_workflow(root, "erpv2_controlled_deploy.yml", body)
        result = MODULE.audit_repository(root)
        rendered = str(result)
        assert result["status"] == "FAIL", rendered
        assert expected in rendered, rendered


current = MODULE.audit_repository(ROOT)
assert current["status"] == "PASS", current
assert current["deploy_capable_workflows"] == 1, current
assert current["auto_push_deploy_workflows"] == 0, current
assert current["pr_deploy_workflows"] == 0, current

base = """name: ERPv2 Controlled Deploy
on:
  workflow_dispatch:
    inputs:
      source_sha:
        required: true
      confirm_deploy:
        required: true
jobs:
  deploy:
    steps:
      - run: |
          SOURCE_SHA=0123456789012345678901234567890123456789
          [[ "$SOURCE_SHA" =~ ^[0-9a-f]{40}$ ]]
          git cat-file -e "${SOURCE_SHA}^{commit}"
          git checkout --detach "$SOURCE_SHA"
          test "$(git rev-parse HEAD)" = "$SOURCE_SHA"
          git archive --format=tar --output=x.tar "$SOURCE_SHA" -- app
          /home/s/spugovxsim/planexp/deploy/P07_deploy_erpv2.sh x y "$SOURCE_SHA"
          marker="$(cat /home/s/spugovxsim/planexp/deploy/P07_erpv2_deployed_commit.txt)"
          test "$marker" = "$SOURCE_SHA"
          echo DEPLOY_ERPV2
          echo secrets.ERPV2_SSH_PRIVATE_KEY secrets.ERPV2_SSH_KNOWN_HOSTS secrets.ERPV2_SSH_HOST secrets.ERPV2_SSH_PORT secrets.ERPV2_SSH_USER
"""

assert_fail(base.replace("on:\n  workflow_dispatch:", "on:\n  push:\n  workflow_dispatch:"), "automatic trigger")
assert_fail(base.replace("on:\n  workflow_dispatch:", "on:\n  pull_request:\n  workflow_dispatch:"), "automatic trigger")
assert_fail(base.replace('git archive --format=tar --output=x.tar "$SOURCE_SHA" -- app', 'git archive --format=tar --output=x.tar "$GITHUB_SHA" -- app'), "GITHUB_SHA")
assert_fail(base.replace('git checkout --detach "$SOURCE_SHA"', 'git checkout main'), "detached exact-SHA checkout")
assert_fail(base.replace('echo DEPLOY_ERPV2', 'php artisan migrate\n          echo DEPLOY_ERPV2'), "migration command")
assert_fail(base.replace('/home/s/spugovxsim/planexp/deploy/P07_deploy_erpv2.sh x y "$SOURCE_SHA"', 'cp x /home/s/spugovxsim/planexp/public_html/erp/app/x\n          /home/s/spugovxsim/planexp/deploy/P07_deploy_erpv2.sh x y "$SOURCE_SHA"'), "old /erp")

print("P21_CONTROL_PLANE_REGRESSION=PASS")
