<?php

namespace App\Repositories;

use App\Consts;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminRepository extends BaseRepository
{
    private $admin;

    public function __construct()
    {
        $this->admin = app(Admin::class);
    }

    public function model()
    {
        return Admin::class;
    }

    public function changeAdminPassword($id, $password)
    {
        $res = Admin::where('id', $id)->first();
        $res->password = Hash::make($password);
        $res->save();
        return $res;
    }
}
