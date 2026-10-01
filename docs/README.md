# ItrsscilogonAssigner reference

ItrsscilogonAssigner is a COmanage Registry identifier assigner plugin for the ITRSS deployment. ITRSS is part of the University of Missouri System. When a new CO Person record is created in the ITRSS CO, the plugin asks the CILogon OA4MP dbService for the person's CILogon user identifiers and stores them on the record as OIDC sub Identifiers.

This page is for CILogon staff who run the Registry and administer the ITRSS CO. It describes the code on `main` as it is now. The plugin is small, so this one page does the work that the separate pages under `docs/` do for larger plugins such as EntraSource, and its sections use the same names. Code is cited by file and function, not line number. Unless noted, the code is `assign()` in `Model/ItrsscilogonAssigner.php`.

## Related documentation

The [ITRSS solution architecture overview](https://github.com/cilogon/itrss-policies/blob/main/ITRSS-Solution-Architecture.md) explains how this plugin fits with the other ITRSS Registry plugins and services. It is in a private repository for ITRSS and CILogon staff.

## How it works

### Where it fits

- The ITRSS CO is the only CO in the deployment.
- One Identifier Assignment uses this plugin. Its Description is "CILogon user name" and its Identifier type is OIDC sub.
- The plugin runs while a new CO Person record is being built, either by an enrollment flow or by a Pipeline.
- It calls the CILogon OA4MP dbService, which returns the CILogon user identifier (the value CILogon issues as the `sub` claim) for a given IdP and eppn. See [OA4MP dbService contract](#oa4mp-dbservice-contract).

### What it needs from the CO Person record

The plugin needs all of the following on the CO Person record. If any is missing, assignment fails with one of the errors listed under [Troubleshooting](#error-messages).

- An Identifier of type eppn. If there is more than one, the first is used.
- An EmailAddress of type official.
- A Name of type official with both a given name and a family name.

The plugin only assigns identifiers to CO Person records. Any other assignment context fails with `NOT IMPLEMENTED`.

### Steps

1. The plugin splits the person's eppn into a user part and a scope, for example `jdoe` and `umkc.edu` from `jdoe@umkc.edu`.
2. It builds a list of eppns from the scope, as shown in the table below.
3. For each eppn in the list, it calls the dbService with the UM System IdP entity ID, the person's official given and family names, and the official email address. Each call returns a CILogon user identifier.
4. The identifier for the person's own eppn becomes the value of the "CILogon user name" assignment.
5. Any other identifiers are added to the same CO Person record as extra OIDC sub Identifiers with status Active. Provisioning is skipped when they are saved (see [Assumptions and known gaps](#extra-identifiers-skip-provisioning)).

If any dbService call fails, assignment stops with an error and no OIDC sub Identifiers are added.

### Eppns requested, and OIDC sub values to expect

| eppn scope | eppns sent to the dbService | OIDC sub values on a new record | On records created before the fix |
|---|---|---|---|
| `missouri.edu` | own eppn, `uid@umsystem.edu`, `uid@mizzou.edu` | 3 | 3 |
| `umh.edu`, `umkc.edu` | own eppn, `uid@umsystem.edu` | 2 | 2 |
| `mst.edu`, `umsl.edu` | own eppn, `uid@umsystem.edu` | 2 | 3 |
| any other scope, for example `umsystem.edu` | own eppn only | 1 | 3 |

`uid` is the user part of the person's eppn.

"Before the fix" means records created before commit `fcfea53` (2026-09-17) was deployed. Before that fix, a bug in the scope test sent every scope except `umh.edu` and `umkc.edu` down both extra paths, so records with any other scope received both an `@umsystem.edu` and an `@mizzou.edu` identifier. See [Assumptions and known gaps](#identifiers-left-over-from-before-the-fix).

## Configuration

In Registry, the plugin is used only by the ITRSS CO's "CILogon user name" Identifier Assignment, of type OIDC sub.

Two values are fixed in the plugin code, so changing either one means changing the code and redeploying (see [Assumptions and known gaps](#hardcoded-idp-and-dbservice-location)):

- The IdP entity ID sent with every dbService call: `https://shib-idp.umsystem.edu/idp/shibboleth`
- The dbService location: `http://oa4mp-server.cilogon-service.svc.cluster.local:8888/oauth2/dbService`, an address inside the Kubernetes cluster.

## OA4MP dbService contract

- **Request:** an HTTP GET to `/oauth2/dbService` on the OA4MP server, one per eppn in the list, with `action=getUser` and the parameters `idp`, `eppn`, `first_name`, `last_name`, and `email`.
- **Response the plugin reads:** a 200 status, and a body of lines in which each line starting with `user_uid` carries a URL-encoded CILogon user identifier after the `=`. Every such line is collected. Other lines are ignored.
- **Failure:** any status other than 200 stops the assignment with `Unable to map eppn <eppn> to CILogon user identifier`. If every call succeeds but no `user_uid` line comes back, the assignment fails with `dbService returned no CILogon user identifiers`.
- **Identity sent:** every call uses the same IdP entity ID and the person's official name and email, whichever eppn variant is being mapped.

## Troubleshooting

### Checking that it works

Look at CO Person records created since the fix was deployed:

- Each record has the number of OIDC sub values shown in the "new record" column of the [table above](#eppns-requested-and-oidc-sub-values-to-expect) for its eppn scope.
- Each OIDC sub value follows the usual CILogon user identifier (`sub` claim) pattern.

Older records may have more OIDC sub values than the table shows for a new record. See the "before the fix" column.

### Error messages

When assignment fails, Registry reports one of the messages below. The plugin also writes a line beginning `ItrsscilogonAssigner` to the Registry error log. For a dbService failure, the log also includes the full dbService response. The message text is in `Lib/lang.php`.

| Message | Meaning | What to check |
|---|---|---|
| `No eppn Identifier found` | The CO Person has no Identifier of type eppn. | That the enrollment flow or Pipeline sets an eppn Identifier before identifiers are assigned. |
| `No official EmailAddress found` | The CO Person has no EmailAddress of type official. | That an official EmailAddress is recorded for the person. |
| `No official Name found` | The CO Person has no official Name, or it lacks a given or family name. | That an official Name with both given and family names is recorded. |
| `Unable to map eppn <eppn> to CILogon user identifier` | The dbService did not answer successfully for the eppn shown. | The Registry error log for the dbService response, and whether the OA4MP server is reachable from Registry. |
| `dbService returned no CILogon user identifiers` | Every dbService call succeeded, but none returned a user identifier. | The Registry error log, and the dbService itself. |

### Why does this person have one, two, or three OIDC sub values?

Find the person's eppn scope in the [table](#eppns-requested-and-oidc-sub-values-to-expect). A record created since the fix should match the "new record" column. A record created before the fix was deployed matches the "before the fix" column instead. For example, a `umsystem.edu` person created before the fix has three values, even though a new `umsystem.edu` person gets one. That is expected, not a current fault.

### Which OIDC sub value is the person's main CILogon identifier?

The one made from the person's own eppn, which is the value of the "CILogon user name" assignment. The others were added alongside it, as described under [Assumptions and known gaps](#extra-identifiers-for-eppn-variants).

## Assumptions and known gaps

The reasons given here come from the plugin's maintainer.

### Eppn variants for UM campus scopes

- **What the code does:** Adds an `@umsystem.edu` variant for `umh.edu`, `umkc.edu`, `mst.edu`, `umsl.edu`, and `missouri.edu`, and a further `@mizzou.edu` variant for `missouri.edu`. The scope list is written into the code.
- **Why:** University central IT said that eventually all records will change and every SAML IdP assertion will use an eppn with the `umsystem.edu` scope. `mizzou.edu` is an older pattern that may or may not still be in use.

### One fixed IdP

- **What the code does:** Sends the UM System IdP entity ID with every dbService call.
- **Why:** The plugin is specific to ITRSS, which is part of the University of Missouri System.

### Extra identifiers for eppn variants

- **What the code does:** Returns one identifier as the assigned value and saves the rest directly as extra OIDC sub Identifiers.
- **Why:** One identifier is the primary. The others will probably never be used. They were created because, when the plugin was written, it was not known how predictably an eppn's scope maps to the value the SAML IdP actually asserts.

### Extra identifiers skip provisioning

- **What the code does:** Saves the extra Identifiers with provisioning turned off.
- **Why:** It makes saving them faster. The plugin only runs while a new CO Person record is being built through enrollment or a Pipeline, and Registry runs provisioning once that work is complete, so the extra identifiers are provisioned then.

### Hardcoded IdP and dbService location

- **What the code does:** Holds the IdP entity ID and the OA4MP server location in the `$idp` and `$oa4mp` properties of the `ItrsscilogonAssigner` model. Nothing in the Registry UI changes them.
- **Why:** The plugin was written in a hurry. Making them configurable is a known improvement for later.

### Identifiers left over from before the fix

- **What you see:** Records created before the fix in commit `fcfea53` was deployed may carry extra `@umsystem.edu` and `@mizzou.edu` OIDC sub Identifiers that the current code would not create, as the [table](#eppns-requested-and-oidc-sub-values-to-expect) shows.
- **Gap:** The plugin does not remove them. This page assumes no cleanup has been done.

## For maintainers

All behavior is in `assign()` in `Model/ItrsscilogonAssigner.php`. The error messages are in `Lib/lang.php`. When a change alters the plugin's behavior, update this page in the same pull request. `docs/plans/` holds planning artifacts and is not part of the staff documentation.
