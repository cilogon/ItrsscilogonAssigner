# CLAUDE.md

## Project overview

`ItrsscilogonAssigner` is a COmanage Registry (CakePHP 2) plugin of type
`identifierassigner`, written for the University of Missouri ITRSS deployment.
Given a CO Person, it reads the person's `eppn` Identifier, official
EmailAddress, and official Name, derives the list of eppns to map (adding
`@umsystem.edu` and `@mizzou.edu` variants for certain UM campus scopes), and
calls the CILogon OA4MP `dbService` (`action=getUser`) to obtain CILogon user
identifiers.

- `Model/ItrsscilogonAssigner.php` -- the assigner logic (`assign()`).
- `Lib/lang.php` -- localized strings (`er.itrsscilogonassigner.*`).
- The remaining directories are the standard CakePHP plugin skeleton and are
  mostly empty placeholders. There is no test suite yet (`Test/` holds only
  placeholders).

The plugin runs inside a COmanage Registry install; it cannot be exercised
standalone.

## Coding style

No PHP formatter or style guide is configured for this repository. Match the
surrounding code (two-space indentation, `if(` with no space, `array()`
syntax). Ask before reformatting or autofixing.

## Pushing

This repository is set up for the machine account `skoranda-agent`; the global
"Machine account" rules apply.

- `bot` -> `https://github.com/skoranda-agent/ItrsscilogonAssigner.git`
  (Claude pushes feature branches here).
- `upstream` -> `https://github.com/cilogon/ItrsscilogonAssigner.git`
  (canonical; pull requests target it).
- `origin` -> `https://github.com/skoranda/ItrsscilogonAssigner.git` (the
  developer's personal fork; Claude does not push here).

Shipping flow: push the feature branch to `bot`, then open a ready-for-review
pull request with
`gh pr create --repo cilogon/ItrsscilogonAssigner --base main --head skoranda-agent:<branch>`.
Before any push, confirm `gh api user --jq .login` prints `skoranda-agent` and
confirm the `bot` URL with `git remote -v`.

Never push to `upstream` or `origin`, never push `main` anywhere, and never
approve or merge a pull request.
