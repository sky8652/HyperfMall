<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\ServiceException;
use App\Model\Role;
use App\Model\User;
use App\Request\AdminRequest;

class AdminController extends BaseController
{
    public function index()
    {
        $data = $this->getPaginateData(User::with('roles')->paginate());
        return $this->response->json(responseSuccess(200, '', $data));
    }

    public function store(AdminRequest $request)
    {
        $data = $request->validated();
        $data['password'] = md5($data['password']);
        User::query()->create($data);
        return $this->response->json(responseSuccess(201));
    }

    public function update(AdminRequest $request)
    {
        $data = $request->validated();
        if (array_key_exists('password', $data))
        {
            $data['password'] = md5($data['password']);
        }

        $user = User::getFirstById($request->route('id'));
        if (!$user)
        {
            throw new ServiceException(403, '用户不存在');
        }
        $user->fill($data)->save();
        return $this->response->json(responseSuccess(200, '更新成功'));
    }

    public function delete(AdminRequest $request)
    {
        $user = User::getFirstById($request->route('id'));
        if (!$user)
        {
            throw new ServiceException(403, '用户不存在');
        }

        if ($user->id === 1)
        {
            throw new ServiceException(-403, '超级管理员不允许删除');
        }
        $user->delete();

        return $this->response->json(responseSuccess(200, '删除成功'));
    }

    public function AssigningRole()
    {
        $user = User::getFirstById($this->request->route('id'));
        if (!$user)
        {
            throw new ServiceException(403, '用户不存在');
        }
        $role = Role::findById((int)$this->request->route('role_id'));
        if (!$role)
        {
            throw new ServiceException(403, '角色不存在');
        }
        $user->assignRole($role);

        return $this->response->json(responseSuccess(200, '分配成功'));
    }

    public function resetPassword()
    {
        $user = User::getFirstById($this->request->route('id'));
        if (!$user)
        {
            throw new ServiceException(403, '用户不存在');
        }

        $password = $user->resetPassword();
        return $this->response->json(responseSuccess(200, '重置密码成功', [
            'password' => $password
        ]));
    }

    public function disable()
    {
        $user = User::getFirstById($this->request->route('id'));
        if (!$user)
        {
            throw new ServiceException(403, '用户不存在');
        }
        $user->changeDisablesStatus();
        return $this->response->json(responseSuccess(200, '修改成功'));
    }
}
