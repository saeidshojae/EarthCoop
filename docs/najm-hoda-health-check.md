# NajmHoda Health Check

Use this checklist to confirm the AI assistant is ready for use and tests.

## Quick automated check

```bash
php scripts/najm_hoda_health_check.php
```

## Manual checklist

- AI config set in .env (.env.example shows the defaults)
- AI provider selected: openai or openrouter
- AI API key is present
- NajmHoda enabled: NAJM_HODA_ENABLED=true
- Mock mode disabled for real calls: NAJM_HODA_MOCK_MODE=false
- Storage is writable: storage/ and storage/logs/
- Knowledge base path exists: storage/najm-hoda/knowledge
- API routes respond (test):
  - GET /api/najm-hoda/welcome
  - POST /api/najm-hoda/chat (requires auth)
- Escalation endpoint token configured (if used): NAJM_HODA_TOKEN

## Notes

- If the API key is missing, the system will use mock responses.
- OpenRouter requires valid model IDs (for example: openai/gpt-4o-mini).
