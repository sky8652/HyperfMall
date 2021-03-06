<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\ServiceException;
use App\Model\Product;
use App\Model\User;
use App\Request\FavorRequest;
use App\Request\ProductRequest;
use App\Services\ProductService;
use Hyperf\Di\Annotation\Inject;

class ProductController extends BaseController
{
    /**
     * @Inject()
     * @var ProductService
     */
    private $productService;

    public function index(ProductRequest $request)
    {
        $search = $request->input('search');
        $order = $request->input('order');
        $field = $request->input('field');
        $builder = Product::query();

        if ($this->request->decodedPath() !== 'center/product')
        {
            $builder->where('on_sale', true);
        }
        if ($search)
        {
            $like = "%$search%";
            $builder->where(function ($query) use ($like)
            {
                $query->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhereHas('skus', function ($query) use ($like)
                    {
                        $query->where('title', 'like', $like)
                            ->orWhere('description', 'like', $like);
                    });
            });
        }
        $builder->with('skus');


        if ($order && $field)
        {
            $builder->orderBy($field, $order);
        }

        $data = $this->getPaginateData($builder->paginate());
        return $this->response->json(responseSuccess(200, '', $data));
    }

    public function show()
    {
        $product = Product::with('skus')->where('id', $this->request->route('id'))->first();
        if (!$product)
        {
            throw new ServiceException(422, '商品不存在');
        }
        if (!$product->on_sale)
        {
            throw new ServiceException(422, '商品没上架');
        }
        return $this->response->json(responseSuccess(200, '', $product));
    }

    public function store(ProductRequest $request)
    {
        $this->productService->createProduct($request->validated());
        return $this->response->json(responseSuccess(201));
    }

    public function update(ProductRequest $request)
    {
        $product = Product::getFirstById($request->route('id'));
        if (!$product)
        {
            throw new ServiceException(403, '商品不存在');
        }
        $product->update($request->validated());
        return $this->response->json(responseSuccess(200, '更新成功'));
    }

    public function delete(ProductRequest $request)
    {
        $product = Product::getFirstById($request->route('id'));
        if (!$product)
        {
            throw new ServiceException(403, '商品不存在');
        }
        $product->delete();
        return $this->response->json(responseSuccess(201, '删除成功'));
    }

    public function favor(FavorRequest $request)
    {
        $productId = $request->route('id');
        $product = Product::getFirstById($productId);
        if (!$product)
        {
            throw new ServiceException(403, '商品不存在');
        }

        /** @var $user User */
        $user = $request->getAttribute('user');
        if ($user->favoriteProducts()->find($productId))
        {
            throw new ServiceException(403, '已经收藏过本商品');
        }

        $user->favoriteProducts()->attach($productId);
        return $this->response->json(responseSuccess(201, '收藏成功'));
    }

    public function detach(FavorRequest $request)
    {
        $productId = $request->route('id');
        $product = Product::getFirstById($productId);
        if (!$product)
        {
            throw new ServiceException(403, '商品不存在');
        }

        /** @var $user User */
        $user = $request->getAttribute('user');
        if (!$user->favoriteProducts()->find($productId))
        {
            throw new ServiceException(403, '没有收藏过本商品');
        }

        $user->favoriteProducts()->detach($productId);
        return $this->response->json(responseSuccess(201, '取消成功'));
    }

    public function favorites()
    {
        /** @var $user User */
        $user = $this->request->getAttribute('user');
        $data = $this->getPaginateData($user->favoriteProducts()->paginate());
        return $this->response->json(responseSuccess(200, '', $data));
    }
}
