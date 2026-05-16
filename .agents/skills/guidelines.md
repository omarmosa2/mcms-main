# MCMS EXECUTION SYSTEM (CRITICAL)

This project uses a strict execution system.

## REQUIRED BEHAVIOR

You MUST:
- Read all files inside /docs before doing anything
- Follow EXECUTION.md strictly
- Execute ONLY one step at a time
- Never skip steps
- Never assume or guess logic
- Always check /progress/agent-log.md for NEXT_STEP
- Always update progress after execution

## SYSTEM CONSTRAINTS

- Every table MUST include clinic_id
- No cross-clinic access allowed
- All queries must be scoped by clinic_id
- No business logic in controllers
- Use Actions layer for business logic
- All actions must be auditable

## FAILURE RULE

If any error occurs:
- STOP immediately
- Mark as FAILED
- Do NOT continue

## STRICT RULES

- No UI before backend is complete
- No multi-step execution
- No skipping task queue
- Only execute current STEP from task-queue.md