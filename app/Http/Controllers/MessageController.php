<?php
namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Message;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function store(Request $request, Group $group)
    {
        $request->validate([
            'message' => 'required|string|max:255',
        ]);

        $group->messages()->create([
            'user_id' => auth()->id(),
            'message' => $request->message,
        ]);

        return redirect()->route('groups.show', $group);
    }
}