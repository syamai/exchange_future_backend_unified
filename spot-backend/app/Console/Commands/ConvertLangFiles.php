<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use DirectoryIterator;

class ConvertLangFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'convert:langs {locale}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Convert PHP lang files to JSON files';



    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $locale = $this->argument('locale');
        $dictionary = [];
        $dictionary = array_merge($dictionary, $this->parseRawJsonLocaleFiles($locale));
        $dictionary = array_merge($dictionary, $this->parsePhpLocaleFiles($locale));
        ksort($dictionary);

        $path = base_path("resources/lang/{$locale}.json");
        file_put_contents($path, json_encode($dictionary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $this->info(json_encode($dictionary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function parseRawJsonLocaleFiles($locale)
    {
        $dictionary = [];
        $path = base_path("resources/lang/{$locale}.raw");
        $content = file_get_contents($path);
        $content = json_decode($content, true, 512, JSON_UNESCAPED_UNICODE);
        $dictionary = $this->executeParseLocaleFile(null, $content, $dictionary);

        foreach ($dictionary as $key => $value) {
            $dictionary[$key] = $this->convertClientReplacements($value);
        }

        return $dictionary;
    }

    private function convertClientReplacements($string)
    {
        return preg_replace_callback(
            '/{(.*?)}/',
            function ($matches) {
                return ":{$matches[1]}";
            },
            $string
        );
    }

    private function parsePhpLocaleFiles($locale)
    {
        $dictionary = [];

        $path = base_path("resources/lang/{$locale}");
        $directory = new DirectoryIterator($path);
        foreach ($directory as $file) {
            if (!$file->isDot()) {
                $content = $this->parsePhpLocaleFile($file->getRealPath());
                if (isset($content)) {
                    $dictionary = array_merge($dictionary, $content);
                }
            }
        }
        return $dictionary;
    }

    private function parsePhpLocaleFile($path)
    {
        $dictionary = [];

        if (!is_file($path) || pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
            return null;
        }

        $content = include($path);
        if (gettype($content) === 'array') {
            $this->executeParseLocaleFile(basename($path, '.php'), $content, $dictionary);
        }
        return $dictionary;
    }

    private function executeParseLocaleFile($key, $value, &$dictionary)
    {
        if (is_array($value)) {
            foreach ($value as $subKey => $subValue) {
                $this->executeParseLocaleFile($key ? "{$key}.{$subKey}" : $subKey, $subValue, $dictionary);
            }
        } else {
            $dictionary[$key] = $value;
        }
        return $dictionary;
    }
}
