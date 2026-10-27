# course-management

## Enrolment

Enrolment work follows docs/ENROLMENT_V2-spec.md, and the behaviour must match
reference/wp-ams2.php, which is the live, tested WordPress implementation.
Where the spec and wp-ams2.php disagree, wp-ams2.php wins. Flag the difference
and I'll correct the spec.

Reference both files by path only. Don't @-import either one, and never load the dump
at docs/reference/wp-functions-dump.php.

## Dev database

Never run any of these without asking me first, every time:

- `migrate:fresh`, `migrate:refresh`, `migrate:rollback`, `db:wipe`
- `truncate()` on any table
- `delete()` / `DELETE` against rows you did not create in the same command

This applies to cleaning up after your own test rows too. The dev database holds
real RTO Data enrolments that cannot be recreated: an ams2_enrolments row carries
the RTO Data enrolment_id and learner_id of a learner who is actually enrolled,
and deleting the row does not undo the enrolment.

Note that TRUNCATE also resets AUTO_INCREMENT, so it changes the ids of rows
created afterwards even when it deletes nothing of value.

Leave test rows in place and tell me about them instead.
