---
name: session-end
description: Review session work and update project docs. Use at the end of a coding session, when wrapping up, or when the user says "session end", "wrap up", "end of session", or "update docs".
disable-model-invocation: true
---

# Session End

Review everything done in this session and bring project documentation up to date.

Execute all steps below before finishing.

## Step 1: Identify changes

Review all files created, modified, or deleted during this session. Build a list of what changed and why.

## Step 2: Check ADRs

Compare the session's changes against existing ADR files in `adr/`.

- If a change introduces a **new** architectural decision (new tool, new pattern, new convention, structural change), create a new ADR file following the existing naming and format in `adr/0000-template.md`.
- If a change **modifies or reverses** an existing decision, update the relevant ADR: set its status to `Superseded` or `Amended` and explain what changed.
- If no architectural decisions were made or changed, skip this step — do not create ADRs for routine code changes.

## Step 3: Update CLAUDE.md

Be VERY careful about this. Only update `CLAUDE.md` if a significant change was introduced that would contradict the current contents or represents a clear gap that would mislead a future session.

## Step 4: Summary

Provide a one sentence summary of documentation changes made (or confirm none were needed).
