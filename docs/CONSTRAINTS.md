# SYSTEM CONSTRAINTS

- All tables must include clinic_id
- All queries must be scoped by clinic_id
- No cross-clinic data access
- No direct DB queries without scope
- All actions must be logged
- No business logic in controllers
- Use Actions layer for logic