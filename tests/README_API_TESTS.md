# API Integration Tests

## Overview

This project includes real API integration tests for AI translation services (OpenAI and Anthropic). These tests are
designed to validate that our translation system works correctly with actual API providers.

## Running Tests Locally

### Prerequisites

1. Set up your API keys in `.env`:

```bash
OPENAI_API_KEY=your_openai_key_here
ANTHROPIC_API_KEY=your_anthropic_key_here
```

2. Configure the AI provider (optional, defaults to OpenAI):

```bash
AI_PROVIDER=openai  # or 'anthropic'
```

### Running Tests

#### Run all real API tests:

```bash
php artisan test --group=real-api
```

#### Run only OpenAI tests:

```bash
php artisan test --group=openai
```

#### Run only Anthropic tests:

```bash
php artisan test --group=anthropic
```

#### Run tests inside Docker:

```bash
docker exec laravel.test php artisan test --group=real-api
```

## CI/CD Configuration

### Automatic Tests (GitHub Actions)

The main test workflow (`tests.yml`) **excludes** real API tests by default to avoid unnecessary API costs. These tests
run on every push and pull request.

### Manual API Tests (GitHub Actions)

A separate workflow (`api-tests.yml`) allows manual execution of API tests:

1. Go to the Actions tab in GitHub
2. Select "API Integration Tests" workflow
3. Click "Run workflow"
4. Choose which provider to test:
    - `all` - Tests both OpenAI and Anthropic
    - `openai` - Tests only OpenAI
    - `anthropic` - Tests only Anthropic

**Note:** You must configure the following secrets in your GitHub repository:

- `OPENAI_API_KEY`
- `ANTHROPIC_API_KEY`

## Test Output

The tests generate translation output files:

- `tests/output_openai_translation.md` - OpenAI translation result
- `tests/output_anthropic_translation.md` - Anthropic translation result

These files are automatically uploaded as artifacts in GitHub Actions for review.

## Cost Considerations

- Each test run consumes API credits
- Tests use approximately 4500 characters of text
- OpenAI: Uses `gpt-4o-mini` model (most cost-effective)
- Anthropic: Uses `claude-3-5-sonnet` model

## Troubleshooting

### Tests are skipped

- Check that API keys are properly configured in `.env` or GitHub secrets
- Verify the API keys are valid and have credits

### JSON parsing errors

- The system includes robust JSON parsing with automatic recovery
- Check `storage/logs/laravel.log` for detailed error information
- Raw API responses are saved to `storage/logs/anthropic_raw_response.txt` for debugging

### Timeout errors

- Tests have a 120-second timeout for API calls
- Large translations may take 15-30 seconds
- Consider increasing timeout in `config/ai-provider.php` if needed