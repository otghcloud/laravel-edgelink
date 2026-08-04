[<img src="https://otgh-static-assets.s3.otgh.cloud/branding/logos/otgh_cloud_2024.png" alt="OTGH Cloud" width="200px" />](https://github.com/otghcloud/laravel-edgelink)

# Contributing

We produce these packages for use within our own production environments and offer them freely for both personal and commercial use to give back to the open source community we ourselves rely on.

Whilst we do not currently have a public process for accepting contributions, feel free to email any fixes/suggestions to [open-source@otgh.cloud](mailto:open-source@otgh.cloud) and we'll consider them for inclusion.

## Pull Request Titles

Pull requests targeting `main` are validated in GitHub Actions. Use this format for PR titles:

```text
prefix(optional-scope): Description starting with a capital letter
```

Examples:

- `fix: Correct RTU timeout handling`
- `feat(api): Add runtime device info lookup`
- `ci(actions): Harden release workflow sequencing`
- `refactor(client)!: Remove legacy login helper`

Allowed prefixes:

- `fix`
- `feat`
- `improve`
- `ci`
- `docs`
- `style`
- `build`
- `refactor`
- `perf`
- `test`
- `chore`
- `revert`

Dependabot pull requests are exempt from this title validation.