<?php

namespace App\Http\Services;

use App\Models\Admin;
use App\Repositories\AdminRepository;

class AdminService
{
    private $model;
    private $adminRepository;

    public function __construct(Admin $model, AdminRepository $adminRepository)
    {
        $this->model = $model;
        $this->adminRepository = $adminRepository;
    }

    function changeAdminPassword($id, $password)
    {
        return $this->adminRepository->changeAdminPassword($id, $password);
    }
}
