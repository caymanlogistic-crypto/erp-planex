#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import re
import sys
from pathlib import Path

DEPLOY_ENGINE_RE = re.compile(r"P07_deploy_erpv2\.sh")
MIGRATION_COMMAND_RE = re.compile(
    r"(?:php\s+artisan\s+migrate|doctrine:migrations|\bLocalMigrationService\b|\bmigrate\.php\b|\bmysql\b[^\n]*(?:database/migrations|migration))",
    re.IGNORECASE,
)
OLD_ERP_WRITE_RE = re.compile(
    r"(?:rsync|cp|mv|install|tar\s+[^\n]*-C)\b[^\n]*(?:public_html/erp)(?:\s|/|$)(?!v2)",
    re.IGNORECASE,
)
CANONICAL_BRANCH = "chatgpt/production-stabilization-20260802"
CI_WORKFLOW_NAME = "ERP PLANEX CI"


def _on_block(text: str) -> str:
    lines = text.splitlines()
    start = None
    for i, line in enumerate(lines):
        if re.fullmatch(r"on:\s*", line):
            start = i + 1
            break
    if start is None:
        return ""
    out: list[str] = []
    for line in lines[start:]:
        if line and not line[0].isspace() and not line.lstrip().startswith("#"):
            break
        out.append(line)
    return "\n".join(out)


def _events(text: str) -> list[str]:
    block = _on_block(text)
    events: list[str] = []
    for line in block.splitlines():
        m = re.match(r"^\s{2}([A-Za-z_][A-Za-z0-9_-]*):", line)
        if m:
            events.append(m.group(1))
    return sorted(set(events))


def _workflow_name(text: str, fallback: str) -> str:
    m = re.search(r"(?m)^name:\s*(.+?)\s*$", text)
    return m.group(1).strip(" '\"") if m else fallback


def _deploy_capable(text: str) -> bool:
    return bool(DEPLOY_ENGINE_RE.search(text))


def _required_input(text: str, name: str) -> bool:
    block = _on_block(text)
    pattern = rf"(?ms)^\s{{6}}{re.escape(name)}:\s*\n(?:(?:\s{{8,}}.*\n?)*)"
    m = re.search(pattern, block)
    return bool(m and re.search(r"(?m)^\s{8,}required:\s*true\s*$", m.group(0)))


def _has_guarded_auto_deploy(text: str, events: list[str]) -> bool:
    if "workflow_run" not in events:
        return False
    required_tokens = [
        f'workflows: ["{CI_WORKFLOW_NAME}"]',
        "types: [completed]",
        f"branches: [{CANONICAL_BRANCH}]",
        "github.event.workflow_run.conclusion == 'success'",
        "github.event.workflow_run.event == 'push'",
        f"github.event.workflow_run.head_branch == '{CANONICAL_BRANCH}'",
        "github.event.workflow_run.head_sha",
    ]
    return all(token in text for token in required_tokens)


def inspect_workflow(path: Path) -> dict:
    text = path.read_text(encoding="utf-8")
    events = _events(text)
    deploy = _deploy_capable(text)
    issues: list[str] = []

    if deploy:
        allowed_events = {"workflow_dispatch", "workflow_run"}
        unknown_events = sorted(set(events) - allowed_events)
        if unknown_events:
            issues.append("deploy-capable workflow has forbidden trigger(s): " + ", ".join(unknown_events))
        if "workflow_dispatch" not in events:
            issues.append("manual workflow_dispatch fallback is required")
        if "workflow_run" in events and not _has_guarded_auto_deploy(text, events):
            issues.append("workflow_run auto-deploy is not strictly bound to successful canonical CI push")
        if "workflow_run" not in events:
            issues.append("guarded canonical CI auto-deploy trigger is missing")
        if not _required_input(text, "source_sha"):
            issues.append("source_sha input is not required")
        if not _required_input(text, "confirm_deploy"):
            issues.append("confirm_deploy input is not required")
        if "DEPLOY_ERPV2" not in text:
            issues.append("explicit DEPLOY_ERPV2 confirmation gate is missing")
        if "^[0-9a-f]{40}$" not in text:
            issues.append("strict 40-character lowercase SHA regex is missing")
        if 'git cat-file -e "${SOURCE_SHA}^{commit}"' not in text:
            issues.append("git cat-file commit existence guard is missing")
        if 'git checkout --detach "$SOURCE_SHA"' not in text:
            issues.append("detached exact-SHA checkout is missing")
        if 'test "$(git rev-parse HEAD)" = "$SOURCE_SHA"' not in text:
            issues.append("HEAD equality guard is missing")
        if not re.search(r"git archive[^\n]*\"\$SOURCE_SHA\"", text):
            issues.append("artifact is not built explicitly from SOURCE_SHA")
        if re.search(r"\$\{\{\s*github\.sha\s*\}\}|\bGITHUB_SHA\b", text):
            issues.append("implicit github.sha/GITHUB_SHA is forbidden in deploy workflow")
        if not re.search(r"P07_deploy_erpv2\.sh[^\n]*\$SOURCE_SHA", text):
            issues.append("P07 invocation is not bound to SOURCE_SHA")
        if MIGRATION_COMMAND_RE.search(text):
            issues.append("migration command present in standard deploy")
        if OLD_ERP_WRITE_RE.search(text):
            issues.append("old /erp appears in a write/deploy command")
        if "P07_erpv2_deployed_commit.txt" not in text or not re.search(r"test\s+\"\$marker\"\s*=\s*\"\$SOURCE_SHA\"", text):
            issues.append("post-deploy marker equality guard is missing")
        for secret in (
            "ERPV2_SSH_PRIVATE_KEY",
            "ERPV2_SSH_KNOWN_HOSTS",
            "ERPV2_SSH_HOST",
            "ERPV2_SSH_PORT",
            "ERPV2_SSH_USER",
        ):
            if f"secrets.{secret}" not in text:
                issues.append(f"required deploy secret name is not referenced: {secret}")

    return {
        "workflow_name": _workflow_name(text, path.name),
        "file": path.as_posix(),
        "events": events,
        "workflow_dispatch": "workflow_dispatch" in events,
        "pull_request_trigger": "pull_request" in events,
        "push_trigger": "push" in events,
        "schedule_trigger": "schedule" in events,
        "workflow_run_trigger": "workflow_run" in events,
        "guarded_auto_deploy": _has_guarded_auto_deploy(text, events) if deploy else False,
        "deploy_capable": deploy,
        "issues": issues,
        "status": "PASS" if not issues else "FAIL",
    }


def audit_repository(root: Path) -> dict:
    workflows_dir = root / ".github" / "workflows"
    files = sorted(list(workflows_dir.glob("*.yml")) + list(workflows_dir.glob("*.yaml")))
    workflows = [inspect_workflow(path) for path in files]
    deploy = [w for w in workflows if w["deploy_capable"]]
    failures = [w for w in workflows if w["issues"]]
    result = {
        "status": "PASS" if not failures else "FAIL",
        "total_workflows": len(workflows),
        "deploy_capable_workflows": len(deploy),
        "auto_push_deploy_workflows": sum(1 for w in deploy if w["push_trigger"]),
        "pr_deploy_workflows": sum(1 for w in deploy if w["pull_request_trigger"]),
        "scheduled_deploy_workflows": sum(1 for w in deploy if w["schedule_trigger"]),
        "workflow_run_deploy_workflows": sum(1 for w in deploy if w["workflow_run_trigger"]),
        "guarded_auto_deploy_workflows": sum(1 for w in deploy if w["guarded_auto_deploy"]),
        "workflows": workflows,
    }
    if len(deploy) != 1:
        result["status"] = "FAIL"
        result["global_issue"] = f"expected exactly one deploy-capable workflow, found {len(deploy)}"
    elif deploy[0]["file"] != (workflows_dir / "erpv2_controlled_deploy.yml").as_posix():
        result["status"] = "FAIL"
        result["global_issue"] = "the only deploy-capable workflow is not erpv2_controlled_deploy.yml"
    elif not deploy[0]["guarded_auto_deploy"]:
        result["status"] = "FAIL"
        result["global_issue"] = "canonical deploy workflow is missing guarded auto-deploy after green CI"
    return result


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", default=".")
    parser.add_argument("--output")
    args = parser.parse_args()
    result = audit_repository(Path(args.root).resolve())
    rendered = json.dumps(result, ensure_ascii=False, indent=2)
    if args.output:
        Path(args.output).write_text(rendered + "\n", encoding="utf-8")
    print(rendered)
    return 0 if result["status"] == "PASS" else 1


if __name__ == "__main__":
    sys.exit(main())
