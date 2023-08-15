<?php

namespace Dedoc\Scramble\Tests\Infer\Reflector\Files;

use App\Http\Requests\PostRequest;
use App\Repositories\PostRepository;
use App\Repositories\Repository;
use Dedoc\Scramble\Tests\Infer\Reflector\Other\BaseController;
use Illuminate\Support\Facades\Auth;

class PostController extends BaseController
{
    /**
     * The repository instance.
     *
     * @var PostRepository
     */
    protected Repository $repository;

    /**
     * Create a new controller instance.
     *
     * @param PostRepository $repository
     * @return void
     */
    public function __construct(PostRepository $repository)
    {
        $this->repository = $repository;
    }

    protected function relations()
    {
        return ['field', 'images', 'account'];
    }

    protected function selectRelations(): array
    {
        return ['field' => ['id', 'name']];
    }


    protected function countRelations()
    {
        return ['likes', 'shares'];
    }

    protected function where(): array
    {
        return [['status', '=', 'published']];
    }

    protected function searchColumns()
    {
        return ['title', 'content', 'id'];
    }

    protected function whereHasRelations()
    {
        return ['account' => function ($query) {
            $query->where('status', '=', 'active');
        }, 'field' => function ($query) {
            $query->where('status', '=', 'active');
        }, 'images' => function ($query) {
            $query->where('status', '=', 'active');
        },];
    }

    protected function beforeStore(array $data): array
    {
        $data['account_id'] = Auth::user()->id;

        return $data;
    }

    protected function beforeUpdate($id, array $data): array
    {
        $post = $this->repository->where('account_id', '=', Auth::user()->id)->find($id);
        if (!$post) {
            return [];
        }
        return $data;

    }

    protected function beforeDestroy(int $id): bool
    {
        $post = $this->repository->where('account_id', '=', Auth::user()->id)->find($id);
        if (!$post) {
            return false;
        }
        return true;
    }

    protected function formRequest(): string
    {
        return PostRequest::class;
    }
}
