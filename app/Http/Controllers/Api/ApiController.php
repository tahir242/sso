<?php

namespace App\Http\Controllers\Api;

use App\Domain\Federation\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Shared helpers: the calling application, and the API's pagination block.
 */
abstract class ApiController
{
    protected function application(Request $request): Application
    {
        return $request->attributes->get('application');
    }

    /**
     * @return array{total: int, page: int, per_page: int, last_page: int}
     */
    protected function meta(LengthAwarePaginator $page): array
    {
        return [
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'per_page' => $page->perPage(),
            'last_page' => $page->lastPage(),
        ];
    }
}
