Run the Playwright browser test suite inside Docker.

```bash
docker compose run --rm -e PLAYWRIGHT_BASE_URL="http://rs_web" -e PLAYWRIGHT_BROWSERS_PATH=/workspace/.pw-browsers -e PLAYWRIGHT_API_BASE_URL="http://api" -v $(pwd)/.pw-browsers:/workspace/.pw-browsers --workdir /workspace/deploy/browser-tests playwright bash -lc "npm ci --ignore-scripts && npx playwright install chromium && npx playwright test"
```
