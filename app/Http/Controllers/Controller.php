<?php

namespace App\Http\Controllers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Undocumented function
     *
     * @param Builder $paginator
     * @return void
     */
    public function paginateBuilder(Builder $paginator)
    {
        $paginator = $paginator->latest()->paginate()->withQueryString();

        if ($paginator->isEmpty() && !$paginator->onFirstPage()) {
            $this->redirect($paginator->url(1));
        }

        return $paginator;
    }

    /**
     * Redirects on the fly
     *
     * @param string $url
     * @return HttpResponseException
     */
    public function redirect(string $url)
    {
        throw new HttpResponseException(redirect($url));
    }
}
