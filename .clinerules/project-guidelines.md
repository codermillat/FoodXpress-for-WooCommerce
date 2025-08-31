## Brief overview
- Project-specific guidelines for documentation, architecture decisions, code style, and testing standards.

## Documentation requirements
- Update documentation in `/docs` when modifying features.
- Keep `README.md` in sync with new capabilities.
- Maintain changelog entries in `CHANGELOG.md`.

## Architecture decision records
- Create ADRs in `/docs/adr` for major dependency changes, architectural pattern changes, new integration patterns, and database schema changes.
- Use the template in `/docs/adr/template.md`.

## Code style & patterns
- Generate API clients using OpenAPI Generator.
- Use TypeScript axios template for API clients.
- Place generated code in `/src/generated`.
- Prefer composition over inheritance.
- Use repository pattern for data access.
- Follow error handling pattern in `/src/utils/errors.ts`.

## Testing standards
- Unit tests required for business logic.
- Integration tests for API endpoints.
- E2E tests for critical user flows.
