# ItrsscilogonAssigner reference

ItrsscilogonAssigner is a COmanage Registry identifier assigner plugin for the ITRSS deployment. ITRSS is part of the University of Missouri System. When a new CO Person record is created in the ITRSS CO, the plugin asks the CILogon OA4MP dbService for the person's CILogon user identifiers and stores them on the record as OIDC sub Identifiers.

This page covers what the plugin needs, how it decides which identifiers to request, how it is configured, how to tell it is working, what its errors mean, and why it was built this way.

## Related documentation

The overview of the ITRSS solution architecture, which explains how this plugin fits with the other ITRSS Registry plugins and services, has not been written yet. It will be linked here when it exists.

## Where it fits

- The ITRSS CO is the only CO in the deployment.
- One Identifier Assignment uses this plugin. Its Description is "CILogon user name" and its Identifier type is OIDC sub.
- The plugin runs while a new CO Person record is being built, either by an enrollment flow or by a Pipeline.
- It calls the CILogon OA4MP dbService, which returns the CILogon user identifier (the value CILogon issues as the `sub` claim) for a given IdP and eppn.

## What it needs from the CO Person record

The plugin needs all of the following on the CO Person record. If any is missing, assignment fails with the error listed under [Errors](#errors).

- An Identifier of type eppn. If there is more than one, the first is used.
- An EmailAddress of type official.
- A Name of type official with both a given name and a family name.

The plugin only assigns identifiers to CO Person records. Any other assignment context fails with `NOT IMPLEMENTED`.

## How it works

1. The plugin splits the person's eppn into a user part and a scope, for example `jdoe` and `umkc.edu` from `jdoe@umkc.edu`.
2. It builds a list of eppns from the scope, as shown in the table below.
3. For each eppn in the list, it calls the dbService with the UM System IdP entity ID, the person's official given and family names, and the official email address. Each call returns a CILogon user identifier.
4. The identifier for the person's own eppn becomes the value of the "CILogon user name" assignment.
5. Any other identifiers are added to the same CO Person record as extra OIDC sub Identifiers with status Active. Provisioning is skipped when they are saved (see [Design rationale](#design-rationale)).

If any dbService call fails, assignment stops with an error and no OIDC sub Identifiers are added.

### Eppns requested, and OIDC sub values to expect

| eppn scope | eppns sent to the dbService | OIDC sub values on a new record | On records created before the fix |
|---|---|---|---|
| `missouri.edu` | own eppn, `uid@umsystem.edu`, `uid@mizzou.edu` | 3 | 3 |
| `umh.edu`, `umkc.edu` | own eppn, `uid@umsystem.edu` | 2 | 2 |
| `mst.edu`, `umsl.edu` | own eppn, `uid@umsystem.edu` | 2 | 3 |
| any other scope, for example `umsystem.edu` | own eppn only | 1 | 3 |

`uid` is the user part of the person's eppn.

"Before the fix" means records created before commit `fcfea53` (2026-09-17) was deployed. Before that fix, a bug in the scope test sent every scope except `umh.edu` and `umkc.edu` down both extra paths, so those records received an `@umsystem.edu` and an `@mizzou.edu` identifier whatever their scope. Those extra Identifiers are still on the records. This plugin does not remove them.

## Configuration

In Registry, the plugin is used only by the ITRSS CO's "CILogon user name" Identifier Assignment, of type OIDC sub.

Two values are fixed in the plugin code, so changing either one means changing the code and redeploying:

- The IdP entity ID sent with every dbService call: `https://shib-idp.umsystem.edu/idp/shibboleth`
- The dbService location: `http://oa4mp-server.cilogon-service.svc.cluster.local:8888/oauth2/dbService`, an address inside the Kubernetes cluster.

## Checking that it works

Look at CO Person records created since the fix was deployed:

- Each record has the number of OIDC sub values shown in the "new record" column of the table above for its eppn scope.
- Each OIDC sub value follows the usual CILogon user identifier (`sub` claim) pattern.

Older records may have more OIDC sub values than the table shows for a new record. See the "before the fix" column and [Troubleshooting](#troubleshooting).

## Errors

When assignment fails, Registry reports one of the messages below. The plugin also writes a line beginning `ItrsscilogonAssigner` to the Registry error log. For a dbService failure, the log also includes the full dbService response.

| Message | Meaning | What to check |
|---|---|---|
| `No eppn Identifier found` | The CO Person has no Identifier of type eppn. | That the enrollment flow or Pipeline sets an eppn Identifier before identifiers are assigned. |
| `No official EmailAddress found` | The CO Person has no EmailAddress of type official. | That an official EmailAddress is recorded for the person. |
| `No official Name found` | The CO Person has no official Name, or it lacks a given or family name. | That an official Name with both given and family names is recorded. |
| `Unable to map eppn <eppn> to CILogon user identifier` | The dbService did not answer successfully for the eppn shown. | The Registry error log for the dbService response, and whether the OA4MP server is reachable from Registry. |
| `dbService returned no CILogon user identifiers` | Every dbService call succeeded, but none returned a user identifier. | The Registry error log, and the dbService itself. |

## Troubleshooting

**Why does this person have one, two, or three OIDC sub values?**

Find the person's eppn scope in the table under [How it works](#eppns-requested-and-oidc-sub-values-to-expect). A record created since the fix should match the "new record" column. A record created before the fix was deployed matches the "before the fix" column instead. For example, a `umsystem.edu` person created before the fix has three values, even though a new `umsystem.edu` person gets one. That is expected, not a current fault.

**Which OIDC sub value is the person's main CILogon identifier?**

The one made from the person's own eppn, which is the value of the "CILogon user name" assignment. The others were added alongside it as described below.

## Design rationale

These reasons come from the plugin's maintainer.

- **Why the `@umsystem.edu` variant:** University central IT said that eventually all records will change and every SAML IdP assertion will use an eppn with the `umsystem.edu` scope. `mizzou.edu` is an older pattern that may or may not still be in use.
- **Why one fixed IdP:** The plugin is specific to ITRSS, which is part of the University of Missouri System, so every request uses the UM System IdP.
- **Why extra identifiers:** One identifier is the primary. The others will probably never be used. They were created because, when the plugin was written, it was not known how predictably an eppn's scope maps to the value the SAML IdP actually asserts.
- **Why extra identifiers skip provisioning:** It makes saving them faster. The plugin only runs while a new CO Person record is being built through enrollment or a Pipeline, and Registry runs provisioning once that work is complete, so the extra identifiers are provisioned then.
- **Why the IdP and dbService location are hardcoded:** The plugin was written in a hurry. Making them configurable is a known improvement for later.
