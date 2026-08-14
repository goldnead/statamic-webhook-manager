# Released migrations — frozen, not maintained

Each directory here is the migration set **exactly as a released version shipped
it**. `MigrationsWithExistingDataTest` installs one of these, fills it with
data, and then runs today's migrations over the top — which is the only way to
find out whether an upgrade path still works against the schema people actually
have.

That only means something if these files stay byte-identical to what was
released. A fixture quietly brought in line with today's style is no longer
evidence of anything: the test would then be checking today's code against
today's code.

So:

- **Never edit a file in here.** Not to fix a lint finding, not to modernise a
  closure, not to make it match the current copy in `database/migrations/`.
- **A new directory per released version**, copied in unchanged.
- If one of these files genuinely misbehaves, the fix belongs in today's
  migrations — the released one already ran on real installations and cannot be
  changed retroactively.

## The trap

`pint.json` excludes this directory, and that exclusion **is ignored when Pint
is given an explicit path**:

```bash
./vendor/bin/pint            # excluded, as intended
./vendor/bin/pint tests/     # reformats the fixtures
```

It cost a revert on 2026-08-15. Run Pint without a path argument.
