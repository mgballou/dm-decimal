# Publishing on Packagist

This repository is named `mgballou/dm-decimal` in its root `composer.json`. The
public GitHub repository to submit is
[`https://github.com/mgballou/dm-decimal`](https://github.com/mgballou/dm-decimal).

## First release

After the package-ready changes are merged, create the first release tag as
**`v0.1.0`**, pointing to that reviewed commit. This is the recommended
starting version for this new package, not a Packagist requirement. Packagist
recommends Semantic Versioning and accepts `X.Y.Z` or `vX.Y.Z` tags; Composer
removes the optional `v` when interpreting a tag. Composer infers versions from
VCS tags, so keep the `version` field out of `composer.json`. See the official
[Packagist versioning guide](https://packagist.org/about#managing-package-versions),
[Composer version guide](https://getcomposer.org/doc/articles/versions.md), and
[Composer library guide](https://getcomposer.org/doc/02-libraries.md#library-versioning).

Before publishing, run `composer validate` from the repository root and commit
any required metadata corrections. Once the release commit is ready, create
and push the tag:

```sh
git tag v0.1.0
git push origin v0.1.0
```

Packagist imports releases from VCS tags. For future releases, create and push a
new SemVer tag after committing the release contents; omit a `version` field
from `composer.json`.

## Submit the package

1. Confirm the GitHub repository is public and its root `composer.json` has
   `"name": "mgballou/dm-decimal"`.
2. Sign in or register at [Packagist.org](https://packagist.org/), preferably
   using the GitHub account that owns or can administer the repository.
3. Choose **Submit** in the Packagist menu.
4. Enter `https://github.com/mgballou/dm-decimal` as the repository URL and
   submit it. Packagist reads the package name from `composer.json` and crawls
   the repository.
5. Confirm the package page identifies it as `mgballou/dm-decimal` and lists
   version `0.1.0` from tag `v0.1.0`.

Packagist's [submission guide](https://packagist.org/about#how-to-submit-packages)
requires a public repository URL and recommends validating `composer.json`
before submission.

## Keep Packagist in sync with GitHub

Choose one of these methods. Automatic updates make new tags and commits
available without waiting for Packagist's scheduled crawl.

### Packagist GitHub application

1. In Packagist, log in through GitHub. If the Packagist account was originally
   created another way, connect GitHub in the Packagist profile or log out and
   back in through GitHub to grant the required access.
2. When GitHub asks which repositories the Packagist application may access,
   grant access to `mgballou/dm-decimal` (or to the owner account if that is the
   selected scope). If GitHub presents an organization approval step, have an
   organization owner approve the installation.
3. In Packagist, check the package list for a warning that the package is not
   automatically synced. If it appears, use Packagist's manual account sync to
   have it configure the GitHub hook, then check the package list again.

Packagist says the GitHub application needs access to the relevant GitHub
account or organization and that manual account sync can retry hook setup. See
its official [GitHub hook instructions](https://packagist.org/about#github-hook).

### GitHub webhook configured manually

Use this if you do not want to grant Packagist application access to configure
repository webhooks:

1. In Packagist, open your profile and copy your **API token**. Keep it private.
2. On GitHub, open `mgballou/dm-decimal` → **Settings** → **Webhooks** → **Add
   webhook**.
3. Set **Payload URL** to
   `https://packagist.org/api/github?username=PACKAGIST_USERNAME`, replacing
   `PACKAGIST_USERNAME` with the Packagist account name that maintains the
   package.
4. Set **Content type** to `application/json`.
5. Paste the Packagist API token into **Secret**.
6. Under **Which events would you like to trigger this webhook?**, choose
   **Just the push event**, then add the webhook.

These are Packagist's [documented GitHub webhook values](https://packagist.org/about#github-hook).
After setup, a push to GitHub should trigger a Packagist update; the package
page also offers a manual update for maintainers.
