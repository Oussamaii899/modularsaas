<?php

namespace App\Service;

class OpenRouterService
{
    private ?string $apiKey;

    public function __construct()
    {
        // Fetch key from environment
        $this->apiKey = $_ENV['OPENROUTER_API_KEY'] ?? $_SERVER['OPENROUTER_API_KEY'] ?? null;
    }

    /**
     * Generate AI growth report based on sales overview statistics
     */
    public function generateReport(array $metrics): string
    {
        if (!$this->apiKey) {
            return "Error: OpenRouter API key is missing. Please set OPENROUTER_API_KEY in your .env file.";
        }

        $prompt = $this->buildPrompt($metrics);

        $url = 'https://openrouter.ai/api/v1/chat/completions';
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'HTTP-Referer: http://localhost', // Required by OpenRouter
            'X-Title: ModularSaaS', // Required by OpenRouter
        ];

        $postData = [
            'model' => 'liquid/lfm-2.5-1.2b-thinking:free',
            'max_tokens' => 4096,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an elite Chief Financial Officer (CFO) and business growth advisor. Analyze the financial data provided and generate a concise, highly professional growth report with actionable insights. Use markdown structure (bolding, bullet points) for readability. Keep it focused and impactful.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $errorMsg = curl_error($ch);
            curl_close($ch);
            return "Network Error connecting to OpenRouter: " . $errorMsg;
        }

        curl_close($ch);

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $msg = $errorData['error']['message'] ?? 'Unknown API error';
            return "OpenRouter API Error (HTTP $httpCode): " . $msg;
        }

        $responseData = json_decode($response, true);
        return $responseData['choices'][0]['message']['content'] ?? 'No response content returned from AI.';
    }

    private function buildPrompt(array $data): string
    {
        $sales = $data['total_sales'] ?? 0;
        $refunds = abs($data['total_refunded_sales'] ?? 0);
        $refundRate = $sales > 0 ? ($refunds / $sales) * 100 : 0;

        return sprintf(
            "Please analyze our business financial performance for the selected period from %s to %s.\n\n" .
            "Financial Metrics:\n" .
            "- Gross Revenue (Total Sales): $%s\n" .
            "- Cost of Goods/Supplies (Total Purchases): $%s\n" .
            "- Customer Refunds Issued: $%s\n" .
            "- Supplier Refunds Claimed: $%s\n" .
            "- Refund Rate: %s%%\n" .
            "- Outstanding Receivables (Sales Balance Due): $%s\n" .
            "- Outstanding Payables (Purchases Balance Due): $%s\n\n" .
            "Please compile a CFO report. You MUST structure your response with the following exact titles and numbering as Markdown headers. Use standard lists and bolding. Keep it highly professional and concise.\n\n" .
            "## 1. Executive Summary\n" .
            "Financial Health Score: [Insert a score between 0 and 100 based on metrics, formatted exactly like: **XX/100**]\n" .
            "[Provide a brief summary paragraph justifying the health score based on the sales, net profit margins, and liabilities.]\n\n" .
            "## 2. Key Findings\n" .
            "- **Revenue**: [Brief statement on gross revenue performance]\n" .
            "- **Profit**: [Brief statement on net profit (sales minus purchases minus customer refunds)]\n" .
            "- **Refund Rate**: [Brief comment on customer refund rate]\n" .
            "- **Outstanding Receivables**: [Brief comment on outstanding customer invoice balances]\n\n" .
            "## 3. Risks\n" .
            "- **High refund rate**: [Analyze refund risks or write 'Low risk' if refund rate is negligible]\n" .
            "- **Cash tied up in receivables**: [Analyze cash flow risks associated with outstanding receivables]\n\n" .
            "## 4. Opportunities\n" .
            "- **Faster collections**: [Actionable steps to accelerate collections or outstanding balances]\n" .
            "- **Supplier negotiations**: [Actionable advice for optimizing supply chain/purchases/accounts payable]\n\n" .
            "## 5. 30-Day Forecast\n" .
            "[Provide a brief, realistic 30-day projection based on current revenue trends and cash flow metrics]\n\n" .
            "## 6. Top 3 Recommended Actions\n" .
            "- [First specific actionable recommendation]\n" .
            "- [Second specific actionable recommendation]\n" .
            "- [Third specific actionable recommendation]\n",
            $data['start_date'],
            $data['end_date'],
            number_format($data['total_sales'], 2),
            number_format($data['total_purchases'], 2),
            number_format($refunds, 2),
            number_format(abs($data['total_refunded_purchases'] ?? 0), 2),
            number_format($refundRate, 1),
            number_format($data['total_outstanding_sales'], 2),
            number_format($data['total_outstanding_purchases'], 2)
        );
    }
}
