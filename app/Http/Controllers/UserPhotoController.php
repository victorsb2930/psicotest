<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\UserPhoto;

class UserPhotoController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $photos = $user->photos()->orderBy('id','desc')->get();
        return response()->json(['ok' => true, 'photos' => $photos]);
    }

    public function store(Request $request)
    {
        $request->validate([ 'photo' => ['required','image','max:5120'] ]);
        $user = auth()->user();
        $file = $request->file('photo');
        $contents = file_get_contents($file->getRealPath());
        $up = UserPhoto::create([
            'user_id' => $user->id,
            'foto' => $contents,
            'caption' => $request->input('caption'),
            'is_profile' => false,
        ]);
        return response()->json(['ok' => true, 'photo' => $up]);
    }

    public function setProfile(Request $request, UserPhoto $photo)
    {
        $user = auth()->user();
        if ($photo->user_id !== $user->id) return response()->json(['ok'=>false,'message'=>'Foto no válida'],403);
        // clear previous
        UserPhoto::where('user_id', $user->id)->update(['is_profile' => false]);
        $photo->is_profile = true;
        $photo->save();
        // no filesystem path update; photo is in user_photos.foto
        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, UserPhoto $photo)
    {
        $user = auth()->user();
        if ($photo->user_id !== $user->id) return response()->json(['ok'=>false,'message'=>'Foto no válida'],403);
        // photo binary stored in DB; simply delete record
        $photo->delete();
        return response()->json(['ok' => true]);
    }
}
