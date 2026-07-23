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
        curl_setopt($ch, CURLOPT_TIMEOUT, 90); // Increased timeout for thinking models

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
        $netProfit = $sales - ($data['total_purchases'] ?? 0) - $refunds;

        return sprintf(
            "Please analyze our business financial performance for the selected period from %s to %s.\n\n" .
            "Financial Metrics:\n" .
            "- Gross Revenue (Total Sales): $%s\n" .
            "- Cost of Goods/Supplies (Total Purchases): $%s\n" .
            "- Net Profit (Sales - Purchases - Customer Refunds): $%s\n" .
            "- Customer Refunds Issued: $%s\n" .
            "- Supplier Refunds Claimed: $%s\n" .
            "- Refund Rate: %s%%\n" .
            "- Outstanding Receivables (Sales Balance Due): $%s\n" .
            "- Outstanding Payables (Purchases Balance Due): $%s\n\n" .
            "Please compile an in-depth CFO report. You MUST structure your response with the following exact titles and numbering as Markdown headers. Use standard lists and bolding. Be comprehensive, detailed, and highly professional.\n\n" .
            "## 1. Executive Summary\n" .
            "Financial Health Score: [Insert a score between 0 and 100 based on metrics, formatted exactly like: **XX/100**]\n" .
            "[Provide a detailed, professional analysis justifying the health score based on the sales volume, net profit margins, refund rates, and ratio of outstanding receivables to payables.]\n\n" .
            "## 2. Key Findings\n" .
            "- **Revenue**: [Detailed statement analyzing gross revenue performance and transaction volume impact]\n" .
            "- **Profitability & Margins**: [Detailed statement on net profit performance, margin analysis, and how to improve profit ratios]\n" .
            "- **Refund Rate & Quality Control**: [Detailed analysis of customer refund rate, customer satisfaction implications, and quality concerns]\n" .
            "- **Working Capital & Liquidity**: [Detailed comment on outstanding customer invoice balances compared with supplier liabilities]\n\n" .
            "## 3. Risks\n" .
            "- **Refund Risk**: [Deconstruct refund risks, cash outflow pressure, or state 'Low risk' if refund rate is negligible with tips to keep it low]\n" .
            "- **Receivables & Aging Risk**: [Deeply analyze cash flow risks associated with outstanding receivables, collection delays, and potential bad debts]\n\n" .
            "## 4. Opportunities\n" .
            "- **Collections Optimization**: [Detailed, step-by-step actionable strategy to accelerate collections of outstanding customer balances]\n" .
            "- **Supplier & Cost Management**: [Detailed actionable advice for optimizing supply chain costs, renegotiating payment terms, and accounts payable management]\n\n" .
            "## 5. 30-Day Forecast\n" .
            "[Provide a detailed, realistic 30-day outlook and projection based on current revenue velocity, average monthly liabilities, and working capital trends]\n\n" .
            "## 6. Top 3 Recommended Actions\n" .
            "- [First specific actionable recommendation with a clear explanation of how to execute it and the expected outcome]\n" .
            "- [Second specific actionable recommendation with a clear explanation of how to execute it and the expected outcome]\n" .
            "- [Third specific actionable recommendation with a clear explanation of how to execute it and the expected outcome]\n",
            $data['start_date'],
            $data['end_date'],
            number_format($data['total_sales'], 2),
            number_format($data['total_purchases'], 2),
            number_format($netProfit, 2),
            number_format($refunds, 2),
            number_format(abs($data['total_refunded_purchases'] ?? 0), 2),
            number_format($refundRate, 1),
            number_format($data['total_outstanding_sales'], 2),
            number_format($data['total_outstanding_purchases'], 2)
        );
    }
}
