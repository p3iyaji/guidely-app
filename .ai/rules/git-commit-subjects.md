# Git commit subjects

## Story-keyed subjects

Include the sprint story kebab key in the commit subject so `git_evidence.py` can attribute commits during retrospectives.

Pattern:

```text
feat(2.5): short why. [2-5-import-template-upload-with-partial-success]
```

- Prefer conventional `type(scope):` prefix.
- Put the exact `sprint-status.yaml` story key in brackets (word-boundary match).
- Do not invent keys; copy from `development_status`.
