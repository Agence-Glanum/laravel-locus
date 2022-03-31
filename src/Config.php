<?php

namespace Glanum\Locus;

use Glanum\Locus\enums\config\Method;
use Illuminate\Support\Arr;

class Config
{
    public string $key = 'locus';

    public array $config;

    public function __construct($config = null)
    {
        $this->config = app('config')->get($this->key);
    }

    public function getLocales(): array
    {
        return Arr::get($this->config, 'locales', []);
    }

    public function getMethod(): string
    {
        return Arr::get($this->config, 'method', Method::PREFIX);
    }

    public function setConfig($config = [])
    {
        $this->config = array_merge($this->config, $config);
    }
}
