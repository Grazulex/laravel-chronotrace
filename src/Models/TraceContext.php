<?php

declare(strict_types=1);

namespace Grazulex\LaravelChronotrace\Models;

/**
 * Contexte d'exécution de la trace
 */
class TraceContext
{
    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, string>  $env_vars
     * @param  array<string, string>  $packages
     * @param  array<string, string>  $middlewares
     * @param  array<string, string>  $providers
     */
    public function __construct(
        public readonly string $laravel_version,
        public readonly string $php_version,
        public readonly array $config,
        public readonly array $env_vars,
        public readonly string $git_commit = '',
        public readonly string $branch = '',
        public readonly array $packages = [],
        public readonly array $middlewares = [],
        public readonly array $providers = [],
    ) {}

    public function toArray(): array
    {
        return [
            'laravel_version' => $this->laravel_version,
            'php_version' => $this->php_version,
            'config' => $this->config,
            'env_vars' => $this->env_vars,
            'git_commit' => $this->git_commit,
            'branch' => $this->branch,
            'packages' => $this->packages,
            'middlewares' => $this->middlewares,
            'providers' => $this->providers,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            laravel_version: $data['laravel_version'],
            php_version: $data['php_version'],
            config: is_array($data['config'] ?? []) ? $data['config'] ?? [] : [],
            env_vars: is_array($data['env_vars'] ?? []) ? $data['env_vars'] ?? [] : [],
            git_commit: $data['git_commit'] ?? '',
            branch: $data['branch'] ?? '',
            packages: is_array($data['packages'] ?? []) ? $data['packages'] ?? [] : [],
            middlewares: is_array($data['middlewares'] ?? []) ? $data['middlewares'] ?? [] : [],
            providers: is_array($data['providers'] ?? []) ? $data['providers'] ?? [] : [],
        );
    }
}
