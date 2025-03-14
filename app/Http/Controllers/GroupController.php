<?php
namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function show(Group $group)
    {
        $messages = $group->messages()->latest()->get();
        return view('groups.show', compact('group', 'messages'));
    }
}