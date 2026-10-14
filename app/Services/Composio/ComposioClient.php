<?php

declare(strict_types=1);

namespace App\Services\Composio;

use App\Exceptions\Jarvis\ComposioException;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin client around the Composio v3 API.
 *
 * Composio brokers OAuth for 100+ products, which is what allows Jarvis to
 * offer "connect and done" integrations without the user registering their own
 * OAuth applications.
 */
final class ComposioClient
{
    private readonly ?string $apiKey;

    private readonly string $baseUrl;

    private readonly int $timeout;

    public function __construct()
    {
        $key = config('jarvis.composio.key');

        $this->apiKey = is_string($key) && $key !== '' ? $key : null;
        $this->baseUrl = mb_rtrim(Config::string('jarvis.composio.base_url', 'https://backend.composio.dev/api/v3'), '/');
        $this->timeout = Config::integer('jarvis.composio.timeout', 30);
    }

    /**
     * Determine whether the Composio API key has been configured.
     */
    public function configured(): bool
    {
        return $this->apiKey !== null;
    }

    /**
     * Get the configurable toolkit catalog grouped by category.
     *
     * @return array<string, array{label: string, toolkits: list<array{slug: string, name: string, description: string}>}>
     */
    public function catalog(): array
    {
        /** @var array<string, array{label: string, toolkits: list<array{slug: string, name: string, description: string}>}> $catalog */
        $catalog = config('jarvis.composio.toolkits', []);

        return $catalog;
    }

    /**
     * Find a toolkit definition in the catalog.
     *
     * @return array{slug: string, name: string, description: string}|null
     */
    public function findToolkit(string $slug): ?array
    {
        foreach ($this->catalog() as $group) {
            foreach ($group['toolkits'] as $toolkit) {
                if ($toolkit['slug'] === $slug) {
                    return $toolkit;
                }
            }
        }

        return null;
    }

    /**
     * Get the toolkit slugs in the catalog.
     *
     * @return list<string>
     */
    public function catalogSlugs(): array
    {
        $slugs = [];

        foreach ($this->catalog() as $group) {
            foreach ($group['toolkits'] as $toolkit) {
                $slugs[] = $toolkit['slug'];
            }
        }

        return $slugs;
    }

    /**
     * Resolve (and cache) the managed auth config for a toolkit.
     */
    public function authConfigId(string $toolkit): string
    {
        $ttl = Config::integer('jarvis.composio.toolkit_cache_ttl', 3600);

        return Cache::remember(
            "jarvis.composio.auth_config.{$toolkit}",
            $ttl,
            function () use ($toolkit): string {
                $response = $this->request()->post('/auth_configs', [
                    'toolkit' => ['slug' => $toolkit],
                    'auth_config' => ['type' => 'use_composio_managed_auth'],
                ]);

                $this->ensureSuccessful($response, '/auth_configs');

                $id = $response->json('auth_config.id');

                if (! is_string($id) || $id === '') {
                    throw ComposioException::requestFailed('/auth_configs', $response->status(), 'The response did not contain an auth config id.');
                }

                return $id;
            },
        );
    }

    /**
     * Create a one-step connect link for the given toolkit.
     *
     * @return array{redirect_url: string, connected_account_id: string|null, auth_config_id: string}
     */
    public function connectLink(User $user, string $toolkit, ?string $callbackUrl = null): array
    {
        $authConfigId = $this->authConfigId($toolkit);

        $response = $this->request()->post('/connected_accounts/link', array_filter([
            'auth_config_id' => $authConfigId,
            'user_id' => $this->userId($user),
            'callback_url' => $callbackUrl,
        ]));

        $this->ensureSuccessful($response, '/connected_accounts/link');

        $redirectUrl = $response->json('redirect_url');

        if (! is_string($redirectUrl) || $redirectUrl === '') {
            throw ComposioException::requestFailed('/connected_accounts/link', $response->status(), 'The response did not contain a redirect url.');
        }

        $connectedAccountId = $response->json('connected_account_id');

        return [
            'redirect_url' => $redirectUrl,
            'connected_account_id' => is_string($connectedAccountId) ? $connectedAccountId : null,
            'auth_config_id' => $authConfigId,
        ];
    }

    /**
     * List the connected accounts for the given user.
     *
     * @param  list<string>|null  $toolkits
     * @return list<array{id: string|null, toolkit: string|null, status: string|null, alias: string|null}>
     *
     * @throws ComposioException
     * @throws ConnectionException
     */
    public function connectedAccounts(User $user, ?array $toolkits = null): array
    {
        $response = $this->request()->get('/connected_accounts', array_filter([
            'user_ids' => [$this->userId($user)],
            'toolkit_slugs' => $toolkits,
            'limit' => 100,
        ]));

        $this->ensureSuccessful($response, '/connected_accounts');

        /** @var list<array<string, mixed>> $items */
        $items = $response->json('items') ?? [];

        return array_map(fn (array $item): array => [
            'id' => is_string($item['id'] ?? null) ? $item['id'] : null,
            'toolkit' => is_string(data_get($item, 'toolkit.slug')) ? (string) data_get($item, 'toolkit.slug') : null,
            'status' => is_string($item['status'] ?? null) ? $item['status'] : null,
            'alias' => is_string($item['alias'] ?? null) ? $item['alias'] : null,
        ], $items);
    }

    /**
     * List the connected accounts for a user without throwing on failure.
     *
     * @return list<array{id: string|null, toolkit: string|null, status: string|null, alias: string|null}>
     */
    public function connectedAccountsOrEmpty(User $user): array
    {
        try {
            return $this->connectedAccounts($user);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * List the tools available for a toolkit.
     *
     * @return list<array{slug: string, name: string, description: string, parameters: array<string, mixed>}>
     */
    public function tools(string $toolkit, int $limit = 50): array
    {
        $response = $this->request()->get('/tools', [
            'toolkit_slug' => $toolkit,
            'limit' => $limit,
        ]);

        $this->ensureSuccessful($response, '/tools');

        /** @var list<array<string, mixed>> $items */
        $items = $response->json('items') ?? [];

        $tools = [];

        foreach ($items as $item) {
            $slug = $item['slug'] ?? null;

            if (! is_string($slug) || $slug === '') {
                continue;
            }

            /** @var array<string, mixed> $parameters */
            $parameters = is_array($item['input_parameters'] ?? null) ? $item['input_parameters'] : [];

            $tools[] = [
                'slug' => $slug,
                'name' => is_string($item['name'] ?? null) ? $item['name'] : $slug,
                'description' => is_string($item['description'] ?? null) ? $item['description'] : '',
                'parameters' => $parameters,
            ];
        }

        return $tools;
    }

    /**
     * Execute a Composio tool on behalf of the user.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{successful: bool, data: mixed, error: string|null}
     */
    public function execute(string $tool, User $user, array $arguments = []): array
    {
        $endpoint = '/tools/execute/'.$tool;

        $response = $this->request()->post($endpoint, [
            'user_id' => $this->userId($user),
            'arguments' => $arguments,
        ]);

        $this->ensureSuccessful($response, $endpoint);

        $error = $response->json('error');

        return [
            'successful' => (bool) $response->json('successful', false),
            'data' => $response->json('data'),
            'error' => is_string($error) ? $error : null,
        ];
    }

    /**
     * Disconnect (revoke) a connected account.
     */
    public function disconnect(string $connectedAccountId): void
    {
        $endpoint = '/connected_accounts/'.$connectedAccountId;

        $response = $this->request()->delete($endpoint);

        $this->ensureSuccessful($response, $endpoint);
    }

    /**
     * Get the identifier Jarvis uses to represent the user in Composio.
     */
    public function userId(User $user): string
    {
        $key = $user->getKey();

        return is_scalar($key) ? (string) $key : '';
    }

    /**
     * Build a pending request to the Composio API.
     */
    private function request(): PendingRequest
    {
        if (! $this->configured()) {
            throw ComposioException::notConfigured();
        }

        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['x-api-key' => $this->apiKey ?? ''])
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout);
    }

    /**
     * Ensure the given response was successful.
     */
    private function ensureSuccessful(Response $response, string $endpoint): void
    {
        if ($response->successful()) {
            return;
        }

        throw ComposioException::requestFailed($endpoint, $response->status(), $response->body());
    }
}
