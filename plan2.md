# Plan 2 - Actionable Insights AI Growth Report (OpenRouter)

This plan details the integration of OpenRouter.ai to generate customized growth reports inside the "Actionable Insights" panel of the Sales Overview page.

## Core Features

1. **API Key Management**:
   - Add an environment variable `OPENROUTER_API_KEY` in `.env` (and a configuration setting in Settings database so it can be managed via the UI).
   
2. **AI Growth Report Controller & Service**:
   - Create a backend service or controller endpoint: `/sales/overview/generate-growth-report`.
   - The endpoint queries sales metrics (Gross revenue, net revenue, refunds, outstanding balances, top clients/purchases) for the active/selected date range.
   - Send this structured financial context to the OpenRouter API (using a lightweight model like `google/gemini-2.5-flash` or similar cost-effective model).
   - Prompt instructions will direct the model to act as a CFO/Business Analyst providing specific, actionable advice based on the provided metrics.

3. **Frontend Integration (Actionable Insights)**:
   - Modify the "Generate Growth Report" button in `templates/sales/overview.html.twig`.
   - Clicking it triggers an AJAX request. While loading, show a premium spinner/animation inside the card.
   - Inject the markdown/HTML response from the AI directly into the "Actionable Insights" card.
   - Use dynamic transitions and hover effects to maintain a modern, premium feel.

4. **Caching/Rate-Limiting**:
   - Store the generated report cache or timestamp to respect the "Next report available in 3 days" message, or implement custom throttling to prevent API abuse.
