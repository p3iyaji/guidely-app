# Git commit subjects

## Story-keyed subjects

Include the sprint story kebab key in the **subject line** (first line of the message) so `git_evidence.py` can attribute commits during retrospectives. Keys in the body are ignored.

Pattern (keep on one line):

```text
feat(2.5): short why. [2-5-import-template-upload-with-partial-success]
```

- Prefer conventional `type(scope):` prefix.
- Put the exact `sprint-status.yaml` story key in brackets (word-boundary match).
- Do not invent keys; copy from `development_status`.
- Subject length: put the kebab key early enough that it is not truncated by tooling that only keeps the first line.
