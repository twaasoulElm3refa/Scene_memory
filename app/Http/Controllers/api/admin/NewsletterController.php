<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\newsRequest;
use App\Repositories\Contracts\Newsletters\NewsletterRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NewsletterController extends Controller
{
    use ApiResponse;

    private $cacheTime = 600;

    public function __construct(private readonly NewsletterRepositoryInterface $newsletterRepository)
    {
    }

    public function all()
    {
        $page = request()->get('page', 1);
        $perPage = 5;
        $cacheKey = "newsletters:paginated:p{$page}:pp{$perPage}";
        $newsLetter = Cache::remember($cacheKey, $this->cacheTime, function () use ($perPage) {
            return $this->newsletterRepository->paginatedWithResponses($perPage);
        });

        return $this->success($newsLetter);
    }

    public function create(newsRequest $request)
    {
        $data = $request->validated();
        $contact = $this->newsletterRepository->create($data);
        $this->clearCache(1, 5);

        return $this->success($contact, 'Contact Created Successfully');
    }

    public function respond(Request $request)
    {
        $data = $request->all();
        $data['contact_id'] = request('id');
        $respond = $this->newsletterRepository->createResponse($data);
        $this->clearCache(1, 5);

        return $this->success($respond, 'Respond Created Successfully');
    }

    private function clearCache($page = 1, $perPage = 5)
    {
        Cache::forget("categories:paginated:p{$page}:pp{$perPage}");
    }
}
