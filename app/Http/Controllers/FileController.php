<?php
namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Group;
use Illuminate\Http\Request;

class FileController extends Controller
{
    public function store(Request $request, Group $group)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
        ]);

        $path = $request->file('file')->store('files');

        $group->files()->create([
            'user_id' => auth()->id(),
            'filename' => $path,
        ]);

        return redirect()->route('groups.show', $group);
    }
}