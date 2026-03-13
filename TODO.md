# Teacher Task Filter Implementation

**Original Task Fixed**: JS onclick syntax error in task.php ✅

**New Task**: Build teacher filter (year_level + section) in task.php using config/teacher_filter.php

## Steps to Complete
- [x] Analyze teacher_filter.php functions (getYearLevelFilter, getSectionFilter)
- [x] Analyze students.php implementation pattern
- [ ] 1. Update TODO.md
- [ ] 2. Read task.php PHP section for subjects query
- [ ] 3. Add include config/teacher_filter.php
- [ ] 4. Modify subjects query: WHERE course_id + year_level IN() + section IN()
- [ ] 5. Add filter display UI (dropdowns + search like students.php)
- [ ] 6. Handle GET parameters (year_level, section)
- [ ] 7. Test filtering works (only shows teacher's assigned classes)
- [ ] 8. Update TODO.md complete
- [ ] 9. attempt_completion

**Dependencies**: config/teacher_filter.php (ready), $_SESSION['teacher_year_levels'], $_SESSION['teacher_sections']

**Current Step**: Update subjects query with teacher filters

