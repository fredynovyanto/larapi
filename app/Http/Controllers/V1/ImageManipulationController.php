<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\ImageManipulation;
use App\Http\Requests\ResizeImageRequest;
use App\Http\Resources\V1\ImageManipulationResource;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Str;
use App\Models\Album;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

class ImageManipulationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return ImageManipulationResource::collection(ImageManipulation::where('user_id', $request->user()->id)->paginate()); 
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreImageManipulationRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function resize(ResizeImageRequest $request)
    {
        $all = $request->all();

        /** @var UploadFile/string $image */
        $image = $all['image'];
        unset($all['image']);
        $data = [
            'type' => ImageManipulation::TYPE_RESIZE,
            'data' => json_encode($all),
            'user_id' => $request->user()->id,
        ];

        if(isset($all['album_id'])){
            $album = Album::find($all['album_id']);
            if($request->user()->id != $album->user_id){
                return abort(403, 'Unauthorized');
            }

            $data['album_id'] = $all['album_id'];
        }

        // make folder images/random211/test.jpg
        // make folder images/random211/test-resized.jpg
        $dir = 'images/'.Str::random().'/';
        $absolutePath = public_path($dir);
        File::makeDirectory($absolutePath);

        // check $image from upload file or url
        if($image instanceof UploadedFile){
            $data['name'] = $image->getClientOriginalName();

            // make test.jpg -> test-resized.jpg
            $filename = pathinfo($data['name'], PATHINFO_FILENAME);
            $extension = $image->getClientOriginalExtension();
            $originalPath = $absolutePath.$data['name'];

            $image->move($absolutePath, $data['name']);
        }else{
            $data['name'] = pathinfo($image, PATHINFO_BASENAME);
            $filename = pathinfo($image, PATHINFO_FILENAME);
            $extension = pathinfo($image, PATHINFO_EXTENSION);
            $originalPath = $absolutePath.$data['name'];

            copy($image, $originalPath);
        }
        $data['path'] = $dir.$data['name'];

        $w = $all['w'];
        $h = $all['h'] ?? false;

        list($width, $height, $image) = $this->getImageWidthAndHeight($w, $h, $originalPath);
        
        $resizedFilename = $filename.'-resized.'.$extension;

        $image->resize($width, $height)->save($absolutePath.$resizedFilename);
        $data['output_path'] = $dir.$resizedFilename;

        $imageManipulation = ImageManipulation::create($data);

        return new ImageManipulationResource($imageManipulation);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ImageManipulation  $imageManipulation
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, ImageManipulation $image)
    {
        if($request->user()->id != $image->user_id){
            return abort(403, 'Unauthorized');
        }
        return new ImageManipulationResource($image);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ImageManipulation  $imageManipulation
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, ImageManipulation $image)
    {
        if($request->user()->id != $image->user_id){
            return abort(403, 'Unauthorized');
        }
        $image->delete();
        return response('', 204);
    }

    protected function getImageWidthAndHeight($w, $h, $originalPath)
    {
        // 1000 - 50% => 500px
        $image = Image::make($originalPath);
        $originalWidth = $image->width();
        $originalHeight = $image->height();

        if(str_ends_with($w, '%')){
            $ratioW = (float)str_replace('%', '', $w);
            $ratioH = $h ? (float)str_replace('%', '', $h) : $ratioW;

            $newWidth = $originalWidth * $ratioW / 100;
            $newHeight = $originalHeight * $ratioH / 100;
        }else{
            $newWidth = (float)$w;
            $newHeight = $h ? (float)$h : $originalHeight * $newWidth/$originalWidth;
        }

        return [$newWidth, $newHeight, $image];
    }

    public function byAlbum(Request $request, Album $album)
    {
        if($request->user()->id != $album->user_id){
            return abort(403, 'Unauthorized');
        }
        $album_id = [
            'album_id' => $album->id,
        ];

        return ImageManipulationResource::collection(ImageManipulation::where($album_id)->paginate());
    }
}
