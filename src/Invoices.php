<?php

declare(strict_types=1);

namespace Shieldz;

class Invoices
{
    public function __construct(private Shieldz $client)
    {
    }

    public function create(array $params): array
    {
        return $this->client->request('POST', '/invoices', [], $params);
    }

    public function retrieve(string $id): array
    {
        return $this->client->request('GET', '/invoices/' . rawurlencode($id));
    }

    public function list(array $params = []): array
    {
        return $this->client->request('GET', '/invoices', [
            'limit' => $params['limit'] ?? null,
            'starting_after' => $params['starting_after'] ?? null,
            'status' => $params['status'] ?? null,
        ]);
    }

    /**
     * Iterate every invoice, following the cursor across pages.
     *
     * @return \Generator<array>
     */
    public function listAll(array $params = []): \Generator
    {
        $startingAfter = $params['starting_after'] ?? null;
        while (true) {
            $page = $this->list([
                'limit' => $params['limit'] ?? null,
                'status' => $params['status'] ?? null,
                'starting_after' => $startingAfter,
            ]);
            $data = $page['data'] ?? [];
            foreach ($data as $invoice) {
                yield $invoice;
            }
            if (empty($page['has_more']) || count($data) === 0) {
                return;
            }
            $startingAfter = $data[count($data) - 1]['id'];
        }
    }
}
