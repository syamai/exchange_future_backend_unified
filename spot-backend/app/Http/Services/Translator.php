<?php

namespace App\Http\Services;

use Illuminate\Translation\Translator as BaseTranslator;

class Translator extends BaseTranslator
{
    public function get($key, array $replace = [], $locale = null, $fallback = true)
    {
        return $this->getFromJson($key, $replace, $locale);
    }

    /**
     * Get the translation for the given key.
     *
     * @param  string  $key
     * @param  array   $replace
     * @param  string|null  $locale
     * @param  bool  $fallback
     * @return string|array|null
     */
    public function _get($key, array $replace = [], $locale = null, $fallback = true)
    {
        list($namespace, $group, $item) = $this->parseKey($key);

        // Here we are getting the locale that should be used for the language line. If one
        // was not passed, we will use the default locales which was given to us when
        // the translator was instantiated. Then, we can load the lines and return.
        $locales = $fallback ? $this->localeArray($locale) : [$locale ?: $this->locale];

        foreach ($locales as $locale) {
            if (! is_null($line = $this->getLine(
                $namespace,
                $group,
                $locale,
                $item,
                $replace
            ))) {
                break;
            }
        }

        // If the line doesn't exist, we will return back the key which was requested as
        // that will be quick to spot in the UI if language keys are wrong or missing
        // from the application's language files. Otherwise we can return the line.
        if (isset($line)) {
            return $line;
        }

        return $key;
    }

    /**
     * Get the translation for a given key from the JSON translation files.
     *
     * @param  string  $key
     * @param  array  $replace
     * @param  string  $locale
     * @return string
     */
    public function getFromJson($key, array $replace = [], $locale = null)
    {
        $locale = $locale ?: $this->locale;

        // For JSON translations, there is only one file per locale, so we will simply load
        // that file and then we will be ready to check the array for the key. These are
        // only one level deep so we do not need to do any fancy searching through it.
        $this->load('*', '*', $locale);

        $line = $this->loaded['*']['*'][$locale][$key] ?? null;

        // If we can't find a translation for the JSON key, we will attempt to translate it
        // using the typical translation file. This way developers can always just use a
        // helper such as __ instead of having to pick between trans or __ with views.
        if (! isset($line)) {
            $fallback = $this->_get($key, $replace, $locale);

            if ($fallback !== $key) {
                return $fallback;
            }
        }

        $replace = $this->appendReplacement($replace);

        return $this->makeReplacements($line ?: $key, $replace);
    }

    private function appendReplacement($replace)
    {
        $commonParams = ['APP_NAME' => env('APP_NAME')];
        return array_merge($commonParams, $replace);
    }
}
