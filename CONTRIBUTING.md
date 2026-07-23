# Contributing to Secure Question Renderer

First off, thank you for considering contributing to the Secure Question Renderer! It's people like you that make the open-source community such a powerful place to learn, inspire, and create.

## 1. Where do I go from here?

If you've noticed a bug or have a feature request, make sure to check our [Issues](../../issues) to see if someone else has already created a ticket. If not, go ahead and make one!

## 2. Fork & create a branch

If this is something you think you can fix, then fork the repository and create a branch with a descriptive name.

A good branch name would be (where issue #325 is the ticket you're working on):

```sh
git checkout -b fix/issue-325-pdf-alignment
```

## 3. Implementation Guidelines

When contributing, please ensure you follow the project's architectural standards:
- **SOLID Principles:** Keep controllers clean and rely on Services/Jobs.
- **GitFlow:** All new features or fixes must be branched off of the `dev` branch.
- **Code Style:** We use Laravel Pint for code styling. Run `./vendor/bin/pint` before committing any PHP changes.

## 4. Make a Pull Request

At this point, you should switch back to your master branch and make sure it's up to date with the main repository's `dev` branch:

```sh
git remote add upstream git@github.com:Pooria82/secure-question-renderer.git
git fetch upstream
git rebase upstream/dev
```

Then push your branch to your fork and submit a Pull Request to our `dev` branch!

## 5. Security Vulnerabilities

If you discover a security vulnerability within the rendering pipeline, please DO NOT open a public issue. Instead, send an e-mail to the repository owner directly. All security vulnerabilities will be promptly addressed.
