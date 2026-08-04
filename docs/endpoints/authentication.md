# Authentication Endpoint

Class: `AuthEndpoint`

## Methods

- `login(string $password): string`
- `logout(): array|string|null`

## Notes

- `login` delegates to client auth flow and stores session state.
- `logout` clears session state and returns upstream response data.
- Authentication methods do not use the endpoint response formatter.
