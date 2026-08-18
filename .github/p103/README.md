# P103 invoice timeline overdue balance

Acceptance target for partial carrier payment display:
- paid late portions are explicitly labeled as paid;
- paid early/on-time portions remain facts, not outstanding debt;
- unpaid remainder is shown as a separate row;
- the red aggregate is the overdue unpaid remainder only.

Production target example: invoice №26/3947, plan 58,500 + 136,500, paid 145,000 on 11.08.2026 => 58,500 paid late, 86,500 paid early, 50,000 unpaid overdue remainder.