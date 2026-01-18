<?php

namespace App\Http\Controllers\Admin;

use App\Consts;
use App\Http\Controllers\AppBaseController;
use App\Http\Requests\BlogRequest;
use App\Http\Requests\ImageRequest;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Utils;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class FileController extends AppBaseController
{

    public function uploadImage(ImageRequest $request)
    {
        $image = $request->file;
        $imageUrl = '';
        if (is_file($image)) {
            $path = Utils::saveFileToStorage($image, 'image/upload', null, 'public');
            $imageUrl = Utils::getImageUrl($path);
        }

        if ($imageUrl) {
            return $this->sendResponse($imageUrl);
        }

        return $this->sendError(__('exception.not_upload_image'));

    }

}
