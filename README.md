# ItrsscilogonAssigner Plugin

ItrsscilogonAssigner is an identifier assigner plugin for COmanage Registry 4.x, used by the ITRSS deployment. For each new CO Person in the ITRSS CO it gets the person's CILogon user identifiers from the CILogon OA4MP dbService and stores them as OIDC sub Identifiers.

## Who the documentation is for

The documentation under `docs/` is written for CILogon staff who operate the Registry and administer the ITRSS CO. It describes the plugin as the code behaves today, including the values hardcoded for the University of Missouri System.

## Documentation

- [ItrsscilogonAssigner reference](docs/README.md): how the plugin works, its configuration, the OA4MP dbService contract, troubleshooting and error messages, and the assumptions and known gaps in the code. The plugin is small enough that one page covers what larger plugins split across several.
