<?php

namespace App\Services\Investments;

use Generator;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;

class SocrataClient
{
    public function __construct(private readonly Factory $http) {}

    /** @return array<string, mixed> */
    public function metadata(string $datasetId): array
    {
        return $this->request()->get("api/views/{$datasetId}")->throw()->json();
    }

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function rows(string $datasetId, ?string $where = null, ?int $maximum = null, array $select = []): Generator
    {
        $pageSize = max(1, (int) config('investments.page_size', 1000));
        $offset = 0;
        $received = 0;

        do {
            $limit = $maximum === null ? $pageSize : min($pageSize, $maximum - $received);
            if ($limit <= 0) {
                break;
            }

            $query = ['$limit' => $limit, '$offset' => $offset];
            if ($where !== null) {
                $query['$where'] = $where;
            }
            if ($select !== []) {
                $query['$select'] = implode(',', $select);
            }

            $rows = $this->request()->get("resource/{$datasetId}.json", $query)->throw()->json();
            foreach ($rows as $row) {
                $received++;
                yield $row;
            }
            $offset += count($rows);
        } while (count($rows) === $limit && ($maximum === null || $received < $maximum));
    }

    private function request(): PendingRequest
    {
        $request = $this->http->baseUrl(rtrim((string) config('investments.socrata_base_url'), '/'))
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(60)
            ->retry(3, 1000, throw: false);

        if ($token = config('investments.socrata_app_token')) {
            $request->withHeaders(['X-App-Token' => $token]);
        }

        return $request;
    }
}
