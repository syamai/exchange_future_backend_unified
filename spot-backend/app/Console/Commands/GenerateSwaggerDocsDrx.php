<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use OpenApi\Generator as OpenApiGenerator;

class GenerateSwaggerDocsDrx extends Command
{
    protected $signature = 'docsDRX:generate';
    protected $description = 'Generate Swagger DRX documentation';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $outputDir = storage_path('api-docs');
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $openapi = OpenApiGenerator::scan([app_path()]);
        $openapi->saveAs($outputDir . '/api-docs.json');

        $this->info('Swagger documentation generated successfully.');
    }
}
