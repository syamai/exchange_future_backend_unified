<?php

namespace App\Http\Controllers\Admin;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Models\UserRegisterSource;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class UTMController extends AppBaseController
{

    public function getList(Request $request)
    {
		$input = $request->all();
		$limit = Arr::get($input, 'limit', Consts::DEFAULT_PER_PAGE);
		$s = $request->s ?? '';
		$data = UserRegisterSource::userWithWhereHas($s)
			->filter($input)
			->orderBy('updated_at', 'desc')
			->paginate($limit);

		return $this->sendResponse($data);

    }

}
