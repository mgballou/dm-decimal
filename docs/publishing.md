# Publishing dm-decimal 0.1.0

After PRs #1, #2, #3, #4, #6, #8, and this release PR are merged, update local
`main` and create the annotated tag and GitHub release from the release commit:

```sh
git switch main
git pull --ff-only origin main
git tag -a v0.1.0
git push origin v0.1.0
gh release create v0.1.0 --notes-file docs/release-notes-v0.1.0.md
```

Then submit the package to Packagist:

1. Sign in at [packagist.org](https://packagist.org).
2. Choose **Submit**.
3. Paste `https://github.com/mgballou/dm-decimal` and submit it.
4. Confirm the GitHub hook so Packagist updates automatically when later tags
   are pushed.
