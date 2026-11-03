# GitFlow & Commit Strategy

## Conventional Commits
We adhere strictly to the Conventional Commits specification to maintain a readable and automated project history.

**Format:**
```
<type>(<scope>): <subject>
```

**Types:**
- `feat:` A new feature.
- `fix:` A bug fix.
- `docs:` Documentation only changes.
- `style:` Changes that do not affect the meaning of the code (white-space, formatting, missing semi-colons, etc).
- `refactor:` A code change that neither fixes a bug nor adds a feature.
- `perf:` A code change that improves performance.
- `test:` Adding missing tests or correcting existing tests.
- `chore:` Changes to the build process or auxiliary tools and libraries such as documentation generation.

## Branching Strategy
- `main`: The production-ready state of the repository.
- `dev`: The main integration branch for active development.
- `feature/*`: Branches created from `dev` for specific features. These are merged back into `dev` via Pull Requests.
- `hotfix/*`: Branches created from `main` to address critical bugs in production.

All work is committed in logical blocks, ensuring that each commit represents a single, cohesive change.
