<?php
namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Group;
use Illuminate\Http\Request;
use App\Events\FileUploaded;

class FileController extends Controller
{
    public function store(Request $request, Group $group)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $path = $request->file('file')->store('files');

        $file = $group->files()->create([
            'user_id' => auth()->id(),
            'filename' => $path,
        ]);

        // پخش رویداد FileUploaded
        broadcast(new FileUploaded($file))->toOthers();

        return redirect()->route('groups.show', $group);
    }
}