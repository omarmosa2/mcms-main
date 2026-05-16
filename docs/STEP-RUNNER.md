# STEP RUNNER

BEFORE:
- read task queue
- read bootstrap
- read RBAC

DURING:
- execute only allowed files

AFTER:
- update logs
- run tests
- stop