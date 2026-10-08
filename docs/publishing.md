# Publishing dm-decimal 0.1.0

## Merge the pull requests

Merge these PRs in order. PR #1 already targets `main`. From PR #2 onward,
retarget each PR to `main` immediately before merging it:

```sh
gh pr merge 1 -R mgballou/dm-decimal --merge --match-head-commit 189ba87851c4e41adaaa48c7946d72688180e708

gh pr edit 2 --base main
gh pr merge 2 -R mgballou/dm-decimal --merge --match-head-commit 5747790d293c06a2f4f2010667d4ae4e9ac9b2d6

gh pr edit 3 --base main
gh pr merge 3 -R mgballou/dm-decimal --merge --match-head-commit 1ce9a294347ec15eca3b4058c132989136d4e44c

gh pr edit 4 --base main
gh pr merge 4 -R mgballou/dm-decimal --merge --match-head-commit 6caf2521700d325532d7dcd336fca14a524d7ef3

gh pr edit 6 --base main
gh pr merge 6 -R mgballou/dm-decimal --merge --match-head-commit a5272400f18155b30651cdb15204677f5dfce70c

gh pr edit 8 --base main
gh pr merge 8 -R mgballou/dm-decimal --merge --match-head-commit fda41a98ed746e8c1011fb57575b5b6cc1199a94

gh pr edit 15 --base main
release_pr_head=$(gh pr view 15 -R mgballou/dm-decimal --json headRefOid --jq .headRefOid)
gh pr merge 15 -R mgballou/dm-decimal --merge --match-head-commit "$release_pr_head"
```

Use `--merge` for every PR in this stack, including the independent #6 and #8.
It adds a merge commit that keeps the PR head's commits in `main`'s ancestry.
That ancestry matters for the next stacked PR: after its base is changed to
`main`, Git can see that the previous PR's commits are already there. Squash
would replace those commits with a new commit, so the next PR would appear to
contain the earlier work again and could conflict. `--match-head-commit` makes
each merge stop if that PR's head has changed; for #15, the command reads the
current head SHA immediately before merging.

The sequence was replayed on a scratch branch from `origin/main` with Git merge
commits for #1, #2, #3, #4, #6, #8, and #15. Every merge was conflict-free, and
`gtimeout 300 composer test` passed with 118 tests and 173 assertions.

## Create the release

After all seven PRs are merged, update local `main` and create the annotated tag
and GitHub release from the release commit:

```sh
git switch main
git pull --ff-only origin main
git tag -a v0.1.0
git push origin v0.1.0
gh release create v0.1.0 --notes-file docs/release-notes-v0.1.0.md
```

## Submit to Packagist

1. Sign in at [packagist.org](https://packagist.org).
2. Choose **Submit**.
3. Paste `https://github.com/mgballou/dm-decimal` and submit it.
4. Confirm the GitHub hook so Packagist updates automatically when later tags
   are pushed.
