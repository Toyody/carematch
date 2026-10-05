<?php

namespace App\Console\Commands;

use App\Modules\Ai\Application\Data\CvExtractionDraft;
use App\Modules\Ai\Infrastructure\Providers\OpenAiProvider;
use Illuminate\Console\Command;

final class SmokeTestAiProvider extends Command
{
    protected $signature = 'ai:provider-smoke';

    protected $description = 'Run one explicitly enabled OpenAI request with a synthetic CV';

    public function handle(OpenAiProvider $provider): int
    {
        if (config('carematch.ai.enabled') !== true
            || config('carematch.ai.provider') !== 'openai'
            || ! is_string(config('services.openai.api_key'))
            || config('services.openai.api_key') === '') {
            $this->error('Set CARE_MATCH_AI_ENABLED=true, AI_PROVIDER=openai, AI_MODEL and OPENAI_API_KEY explicitly.');

            return self::FAILURE;
        }

        $syntheticPdf = $this->syntheticPdf();
        $result = $provider->extractCv($syntheticPdf, 'application/pdf', 'synthetic-ai-provider-smoke.pdf');
        if (! $result->value instanceof CvExtractionDraft) {
            $this->error('The provider did not return the expected structured extraction.');

            return self::FAILURE;
        }

        $this->info('AI provider smoke test passed with validated structured output.');

        return self::SUCCESS;
    }

    private function syntheticPdf(): string
    {
        $text = 'Synthetic Candidate | synthetic.ai-smoke@example.test | Registered Nurse | Melbourne';
        $stream = sprintf('BT /F1 12 Tf 72 720 Td (%s) Tj ET', $text);
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($stream), $stream),
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= sprintf("%d 0 obj\n%s\nendobj\n", $number + 1, $object);
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.sprintf("trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n", $xref);
    }
}
