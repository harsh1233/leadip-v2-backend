<?php

use Illuminate\Support\Facades\Storage;


if (!function_exists('uploadFile')) {
    function uploadFile($attachment, $directory)
    {
        $originalName = $attachment->getClientOriginalName();
        $extension = $attachment->getClientOriginalExtension();
        $fileName = pathinfo($originalName, PATHINFO_FILENAME);
        $path = $directory . '/' . $fileName . '-' . mt_rand(1000000000, time()) . '.' . $extension;
        
        Storage::disk('s3')->put($path, fopen($attachment, 'r+'), 'public');
        $url = Storage::disk('s3')->url($path);
        return $url;
    }
}

if (!function_exists('deleteFile')) {
    function deleteFile($url)
    {
        $path = parse_url($url)['path'];

        if ($path && Storage::disk('s3')->exists($path)) {
            Storage::disk('s3')->delete($path);
        }
    }
}